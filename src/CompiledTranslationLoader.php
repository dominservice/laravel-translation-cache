<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache;

use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Illuminate\Contracts\Translation\Loader;

final readonly class CompiledTranslationLoader implements Loader
{
    public function __construct(
        private Loader $source,
        private TranslationCacheStore $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $identity = TranslationIdentity::make((string) $locale, (string) $group, $namespace);

        return $this->cache->load($identity)
            ?? $this->source->load($locale, $group, $namespace);
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->source->addNamespace($namespace, $hint);
    }

    public function addPath($path): void
    {
        if (method_exists($this->source, 'addPath')) {
            $this->source->addPath($path);
        }
    }

    public function addJsonPath($path): void
    {
        $this->source->addJsonPath($path);
    }

    public function namespaces(): array
    {
        return $this->source->namespaces();
    }

    public function source(): Loader
    {
        return $this->source;
    }
}
