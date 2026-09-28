<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Feature;

use Dominservice\LaravelTranslationCache\Tests\TestCase;
use Dominservice\LaravelTranslationCache\TranslationCacheStore;
use Dominservice\LaravelTranslationCache\TranslationCatalog;

final class TranslationCatalogTest extends TestCase
{
    public function test_it_sorts_the_filtered_catalog_before_applying_pagination(): void
    {
        $store = app(TranslationCacheStore::class);
        $store->replace([], [
            'en' => [
                $this->entry('beta', 'Beta'),
                $this->entry('alpha', 'Alpha'),
                $this->entry('gamma', 'Gamma'),
                $this->entry('excluded', 'Unrelated value'),
            ],
        ]);

        $result = app(TranslationCatalog::class)->search(
            locale: 'en',
            query: 'translation',
            limit: 1,
            offset: 1,
            sort: 'original',
            direction: 'desc',
        );

        self::assertSame(3, $result['total']);
        self::assertSame('beta', $result['data'][0]['key']);
    }

    public function test_it_falls_back_to_identity_sorting_for_unallowlisted_state(): void
    {
        $store = app(TranslationCacheStore::class);
        $store->replace([], [
            'en' => [
                $this->entry('zeta', 'First'),
                $this->entry('alpha', 'Second'),
            ],
        ]);

        $result = app(TranslationCatalog::class)->search(
            locale: 'en',
            sort: 'value desc; drop table translations',
            direction: 'sideways',
        );

        self::assertSame(['alpha', 'zeta'], array_column($result['data'], 'key'));
    }

    /**
     * @return array{locale: string, namespace: string, group: string, key: string, original: string, value: string, overridden: bool}
     */
    private function entry(string $key, string $value): array
    {
        $value = $key === 'excluded' ? $value : $value.' translation';

        return [
            'locale' => 'en',
            'namespace' => '*',
            'group' => 'admin',
            'key' => $key,
            'original' => $value,
            'value' => $value,
            'overridden' => false,
        ];
    }
}
