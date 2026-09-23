<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache;

use Dominservice\LaravelTranslationCache\Contracts\OverrideRepository;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Filesystem\Filesystem;
use SplFileInfo;

final readonly class TranslationSource
{
    public function __construct(
        private Loader $loader,
        private Filesystem $files,
        private OverrideRepository $overrides,
        private array $configuredLocales = [],
    ) {}

    /**
     * @return list<TranslationIdentity>
     */
    public function identities(): array
    {
        /** @var array<string, TranslationIdentity> $identities */
        $identities = [];

        foreach ($this->locales() as $locale) {
            if ($this->hasJsonTranslations($locale)) {
                $identity = TranslationIdentity::make($locale, TranslationIdentity::JSON_GROUP);
                $identities[$identity->cacheKey()] = $identity;
            }

            foreach ($this->groupsForPaths($this->paths(), $locale) as $group) {
                $identity = TranslationIdentity::make($locale, $group);
                $identities[$identity->cacheKey()] = $identity;
            }

            foreach ($this->namespaces() as $namespace => $hint) {
                foreach ($this->namespacedPaths($namespace, $hint) as $path) {
                    foreach ($this->groupsForPaths([$path], $locale) as $group) {
                        $identity = TranslationIdentity::make($locale, $group, $namespace);
                        $identities[$identity->cacheKey()] = $identity;
                    }
                }
            }
        }

        foreach ($this->overrides->identities() as $identity) {
            $identities[$identity->cacheKey()] = $identity;
        }

        uasort($identities, fn (TranslationIdentity $left, TranslationIdentity $right): int => [
            $left->locale,
            $left->namespace,
            $left->group,
        ] <=> [
            $right->locale,
            $right->namespace,
            $right->group,
        ]);

        return array_values($identities);
    }

    /**
     * @return array<string, mixed>
     */
    public function load(TranslationIdentity $identity): array
    {
        return $this->loader->load(
            $identity->locale,
            $identity->group,
            $identity->namespace,
        );
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = [];

        foreach ($this->configuredLocales as $locale) {
            if (is_string($locale) && $locale !== '') {
                $locales[$locale] = true;
            }
        }

        foreach ([$this->applicationLocale(), $this->fallbackLocale()] as $locale) {
            if ($locale !== null && $locale !== '') {
                $locales[$locale] = true;
            }
        }

        foreach (array_merge($this->paths(), $this->jsonPaths()) as $path) {
            foreach ($this->localeDirectories($path) as $locale) {
                $locales[$locale] = true;
            }

            foreach ($this->jsonLocales($path) as $locale) {
                $locales[$locale] = true;
            }
        }

        foreach ($this->namespaces() as $namespace => $hint) {
            foreach ($this->namespacedPaths($namespace, $hint) as $path) {
                foreach ($this->localeDirectories($path) as $locale) {
                    $locales[$locale] = true;
                }
            }
        }

        foreach ($this->overrides->identities() as $identity) {
            $locales[$identity->locale] = true;
        }

        $result = array_keys($locales);
        sort($result);

        return $result;
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        if (! method_exists($this->loader, 'paths')) {
            return [];
        }

        return array_values(array_filter(
            $this->loader->paths(),
            static fn (mixed $path): bool => is_string($path),
        ));
    }

    /**
     * @return list<string>
     */
    private function jsonPaths(): array
    {
        if (! method_exists($this->loader, 'jsonPaths')) {
            return [];
        }

        return array_values(array_filter(
            $this->loader->jsonPaths(),
            static fn (mixed $path): bool => is_string($path),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function namespaces(): array
    {
        return array_filter(
            $this->loader->namespaces(),
            static fn (mixed $path, mixed $namespace): bool => is_string($namespace) && is_string($path),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return list<string>
     */
    private function namespacedPaths(string $namespace, string $hint): array
    {
        $paths = [$hint];

        foreach ($this->paths() as $path) {
            $paths[] = $path.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$namespace;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function groupsForPaths(array $paths, string $locale): array
    {
        $groups = [];

        foreach ($paths as $path) {
            $localePath = $path.DIRECTORY_SEPARATOR.$locale;

            if (! $this->files->isDirectory($localePath)) {
                continue;
            }

            foreach ($this->files->allFiles($localePath) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = $this->relativePath($localePath, $file);
                $groups[] = substr($relativePath, 0, -4);
            }
        }

        $groups = array_values(array_unique($groups));
        sort($groups);

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function localeDirectories(string $path): array
    {
        if (! $this->files->isDirectory($path)) {
            return [];
        }

        return array_values(array_filter(
            array_map('basename', $this->files->directories($path)),
            static fn (string $locale): bool => $locale !== 'vendor',
        ));
    }

    /**
     * @return list<string>
     */
    private function jsonLocales(string $path): array
    {
        if (! $this->files->isDirectory($path)) {
            return [];
        }

        $locales = [];

        foreach ($this->files->files($path) as $file) {
            if ($file->getExtension() === 'json') {
                $locales[] = $file->getBasename('.json');
            }
        }

        return $locales;
    }

    private function hasJsonTranslations(string $locale): bool
    {
        foreach (array_merge($this->jsonPaths(), $this->paths()) as $path) {
            if ($this->files->isFile($path.DIRECTORY_SEPARATOR.$locale.'.json')) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $basePath, SplFileInfo $file): string
    {
        $prefixLength = strlen(rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), $prefixLength));
    }

    private function applicationLocale(): ?string
    {
        $locale = config('app.locale');

        return is_string($locale) ? $locale : null;
    }

    private function fallbackLocale(): ?string
    {
        $locale = config('app.fallback_locale');

        return is_string($locale) ? $locale : null;
    }
}
