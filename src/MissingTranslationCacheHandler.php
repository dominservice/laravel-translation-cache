<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache;

use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Illuminate\Support\Arr;
use Illuminate\Translation\Translator;

final readonly class MissingTranslationCacheHandler
{
    public function __construct(
        private TranslationSource $source,
        private TranslationCacheCompiler $compiler,
        private Translator $translator,
    ) {}

    /**
     * @param  array<string, mixed>  $replace
     */
    public function handle(string $key, array $replace, ?string $locale, bool $fallback): ?string
    {
        $locale ??= $this->translator->getLocale();

        foreach ($this->locales($locale, $fallback) as $candidateLocale) {
            if (! $this->sourceContains($key, $candidateLocale)) {
                continue;
            }

            $this->compiler->compile();
            $translated = $this->translator->get($key, [], $locale, $fallback);

            return is_string($translated) ? $translated : null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function locales(string $locale, bool $fallback): array
    {
        $locales = [$locale];

        if ($fallback) {
            $configuredFallback = $this->translator->getFallback();
            $locales = array_merge($locales, is_array($configuredFallback) ? $configuredFallback : [$configuredFallback]);
        }

        return array_values(array_unique(array_filter(
            $locales,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        )));
    }

    private function sourceContains(string $key, string $locale): bool
    {
        $json = $this->source->load(TranslationIdentity::make($locale, TranslationIdentity::JSON_GROUP));

        if (isset($json[$key])) {
            return true;
        }

        [$namespace, $group, $item] = $this->translator->parseKey($key);
        $translations = $this->source->load(TranslationIdentity::make($locale, $group, $namespace));

        return Arr::get($translations, $item) !== null;
    }
}
