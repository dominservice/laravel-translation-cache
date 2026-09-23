<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache;

use Dominservice\LaravelTranslationCache\Contracts\OverrideRepository;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Translation\Translator;

final readonly class TranslationCacheCompiler
{
    public function __construct(
        private TranslationSource $source,
        private OverrideRepository $overrides,
        private TranslationCacheStore $cache,
        private Application $application,
    ) {}

    public function compile(): int
    {
        $groups = [];
        $catalogs = [];

        foreach ($this->source->identities() as $identity) {
            $original = $this->source->load($identity);
            $overrideValues = $this->overrides->forIdentity($identity);
            $effective = $this->applyOverrides($identity, $original, $overrideValues);

            $groups[$identity->cacheKey()] = $effective;
            $catalogs[$identity->locale] = array_merge(
                $catalogs[$identity->locale] ?? [],
                $this->catalogEntries($identity, $original, $effective, $overrideValues),
            );
        }

        foreach ($catalogs as &$catalog) {
            usort($catalog, static fn (array $left, array $right): int => [
                $left['namespace'],
                $left['group'],
                $left['key'],
            ] <=> [
                $right['namespace'],
                $right['group'],
                $right['key'],
            ]);
        }
        unset($catalog);

        $this->cache->replace($groups, $catalogs);
        $this->resetRuntimeTranslator();

        return count($groups);
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, string>  $overrides
     * @return array<string, mixed>
     */
    private function applyOverrides(
        TranslationIdentity $identity,
        array $original,
        array $overrides,
    ): array {
        foreach ($overrides as $key => $value) {
            if ($identity->isJson()) {
                $original[$key] = $value;

                continue;
            }

            Arr::set($original, $key, $value);
        }

        return $original;
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $effective
     * @param  array<string, string>  $overrides
     * @return list<array{locale: string, namespace: string, group: string, key: string, original: mixed, value: mixed, overridden: bool}>
     */
    private function catalogEntries(
        TranslationIdentity $identity,
        array $original,
        array $effective,
        array $overrides,
    ): array {
        $flatOriginal = $identity->isJson() ? $original : Arr::dot($original);
        $flatEffective = $identity->isJson() ? $effective : Arr::dot($effective);
        $keys = array_values(array_unique(array_merge(array_keys($flatOriginal), array_keys($flatEffective))));
        sort($keys);

        return array_map(
            static fn (string $key): array => [
                'locale' => $identity->locale,
                'namespace' => $identity->namespace,
                'group' => $identity->group,
                'key' => $key,
                'original' => $flatOriginal[$key] ?? null,
                'value' => $flatEffective[$key] ?? null,
                'overridden' => array_key_exists($key, $overrides),
            ],
            $keys,
        );
    }

    private function resetRuntimeTranslator(): void
    {
        $this->cache->forgetManifest();

        if (! $this->application->resolved('translator')) {
            return;
        }

        $translator = $this->application->make('translator');

        if ($translator instanceof Translator) {
            $translator->setLoaded([]);
        }
    }
}
