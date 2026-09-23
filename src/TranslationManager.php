<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache;

use Dominservice\LaravelTranslationCache\Contracts\OverrideRepository;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final readonly class TranslationManager
{
    public function __construct(
        private TranslationSource $source,
        private OverrideRepository $overrides,
        private TranslationCacheCompiler $compiler,
        private bool $rebuildAfterWrite = true,
    ) {}

    public function put(
        string $locale,
        string $group,
        string $key,
        string $value,
        ?string $namespace = null,
    ): void {
        $this->assertValidKey($key);

        $identity = TranslationIdentity::make($locale, $group, $namespace);
        $original = $this->originalValue($identity, $key);

        if ($original === $value) {
            $this->overrides->delete($identity, $key);
        } else {
            $this->overrides->put($identity, $key, $value);
        }

        $this->rebuildIfEnabled();
    }

    public function delete(
        string $locale,
        string $group,
        string $key,
        ?string $namespace = null,
    ): void {
        $this->assertValidKey($key);
        $this->overrides->delete(TranslationIdentity::make($locale, $group, $namespace), $key);
        $this->rebuildIfEnabled();
    }

    private function originalValue(TranslationIdentity $identity, string $key): ?string
    {
        $translations = $this->source->load($identity);
        $value = $identity->isJson()
            ? ($translations[$key] ?? null)
            : Arr::get($translations, $key);

        return is_string($value) ? $value : null;
    }

    private function rebuildIfEnabled(): void
    {
        if ($this->rebuildAfterWrite) {
            $this->compiler->compile();
        }
    }

    private function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('The translation key cannot be empty.');
        }
    }
}
