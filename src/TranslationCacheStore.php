<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache;

use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Illuminate\Filesystem\Filesystem;
use LogicException;
use Throwable;

final class TranslationCacheStore
{
    /**
     * @var array{version: int, build: string, groups: array<string, string>, catalogs: array<string, string>}|false|null
     */
    private array|false|null $manifest = null;

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $path,
        private readonly bool $enabled = true,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function load(TranslationIdentity $identity): ?array
    {
        $manifest = $this->manifest();

        if ($manifest === false || ! isset($manifest['groups'][$identity->cacheKey()])) {
            return null;
        }

        return $this->loadArray($this->path.DIRECTORY_SEPARATOR.$manifest['groups'][$identity->cacheKey()]);
    }

    /**
     * @return list<array{locale: string, namespace: string, group: string, key: string, original: mixed, value: mixed, overridden: bool}>
     */
    public function catalog(string $locale): array
    {
        $manifest = $this->manifest();

        if ($manifest === false || ! isset($manifest['catalogs'][$locale])) {
            return [];
        }

        /** @var list<array{locale: string, namespace: string, group: string, key: string, original: mixed, value: mixed, overridden: bool}> $catalog */
        $catalog = $this->loadArray($this->path.DIRECTORY_SEPARATOR.$manifest['catalogs'][$locale]);

        return $catalog;
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, list<array{locale: string, namespace: string, group: string, key: string, original: mixed, value: mixed, overridden: bool}>>  $catalogs
     */
    public function replace(array $groups, array $catalogs): void
    {
        $this->files->ensureDirectoryExists($this->path);

        $lock = fopen($this->path.DIRECTORY_SEPARATOR.'build.lock', 'c');

        if ($lock === false) {
            throw new LogicException('Unable to create the translation cache lock.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new LogicException('Unable to lock the translation cache.');
            }

            $previousBuild = $this->activeBuild();
            $build = 'build-'.date('YmdHis').'-'.bin2hex(random_bytes(6));
            $buildPath = $this->path.DIRECTORY_SEPARATOR.$build;
            $this->files->ensureDirectoryExists($buildPath);

            $manifest = [
                'version' => 1,
                'build' => $build,
                'groups' => [],
                'catalogs' => [],
            ];

            foreach ($groups as $cacheKey => $translations) {
                $relativePath = $build.DIRECTORY_SEPARATOR.'group-'.$cacheKey.'.php';
                $this->writeArray($this->path.DIRECTORY_SEPARATOR.$relativePath, $translations);
                $manifest['groups'][$cacheKey] = $relativePath;
            }

            foreach ($catalogs as $locale => $catalog) {
                $relativePath = $build.DIRECTORY_SEPARATOR.'catalog-'.hash('sha256', $locale).'.php';
                $this->writeArray($this->path.DIRECTORY_SEPARATOR.$relativePath, $catalog);
                $manifest['catalogs'][$locale] = $relativePath;
            }

            $temporaryManifest = $this->manifestPath().'.tmp.'.bin2hex(random_bytes(6));
            $this->writeArray($temporaryManifest, $manifest);

            if (! $this->files->move($temporaryManifest, $this->manifestPath())) {
                throw new LogicException('Unable to activate the translation cache manifest.');
            }

            $this->invalidateOpcodeCache($this->manifestPath());
            $this->manifest = $manifest;
            $this->cleanStaleBuilds([$build, $previousBuild]);
        } catch (Throwable $exception) {
            if (isset($buildPath) && $this->files->isDirectory($buildPath)) {
                $this->files->deleteDirectory($buildPath);
            }

            throw $exception;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function clear(): void
    {
        if (! $this->files->isDirectory($this->path)) {
            $this->manifest = false;

            return;
        }

        foreach ($this->files->directories($this->path) as $directory) {
            if (str_starts_with(basename($directory), 'build-')) {
                $this->files->deleteDirectory($directory);
            }
        }

        $this->files->delete($this->manifestPath());
        $this->invalidateOpcodeCache($this->manifestPath());
        $this->manifest = false;
    }

    public function exists(): bool
    {
        return $this->manifest() !== false;
    }

    public function forgetManifest(): void
    {
        $this->manifest = null;
    }

    /**
     * @return array{version: int, build: string, groups: array<string, string>, catalogs: array<string, string>}|false
     */
    private function manifest(): array|false
    {
        if (! $this->enabled) {
            return false;
        }

        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (! $this->files->isFile($this->manifestPath())) {
            return $this->manifest = false;
        }

        $manifest = $this->loadArray($this->manifestPath());

        if (($manifest['version'] ?? null) !== 1
            || ! is_string($manifest['build'] ?? null)
            || ! is_array($manifest['groups'] ?? null)
            || ! is_array($manifest['catalogs'] ?? null)) {
            throw new LogicException('The translation cache manifest is invalid.');
        }

        /** @var array{version: int, build: string, groups: array<string, string>, catalogs: array<string, string>} $manifest */
        return $this->manifest = $manifest;
    }

    /**
     * @param  array<mixed>  $values
     */
    private function writeArray(string $path, array $values): void
    {
        $written = $this->files->put(
            $path,
            '<?php return '.var_export($values, true).';'.PHP_EOL,
            true,
        );

        if ($written === false) {
            throw new LogicException("Unable to write translation cache file [{$path}].");
        }

        $this->invalidateOpcodeCache($path);

        if (! is_array(require $path)) {
            throw new LogicException("Translation cache file [{$path}] is invalid.");
        }
    }

    /**
     * @return array<mixed>
     */
    private function loadArray(string $path): array
    {
        $loaded = require $path;

        if (! is_array($loaded)) {
            throw new LogicException("Translation cache file [{$path}] must return an array.");
        }

        return $loaded;
    }

    private function manifestPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'manifest.php';
    }

    private function activeBuild(): ?string
    {
        $this->forgetManifest();
        $manifest = $this->manifest();

        return $manifest === false ? null : $manifest['build'];
    }

    /**
     * @param  list<string|null>  $retainedBuilds
     */
    private function cleanStaleBuilds(array $retainedBuilds): void
    {
        foreach ($this->files->directories($this->path) as $directory) {
            $build = basename($directory);

            if (str_starts_with($build, 'build-') && ! in_array($build, $retainedBuilds, true)) {
                $this->files->deleteDirectory($directory);
            }
        }
    }

    private function invalidateOpcodeCache(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}
