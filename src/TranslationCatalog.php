<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache;

use Illuminate\Support\Str;

final readonly class TranslationCatalog
{
    public function __construct(private TranslationCacheStore $cache) {}

    /**
     * @return array{data: list<array{locale: string, namespace: string, group: string, key: string, original: mixed, value: mixed, overridden: bool}>, total: int, offset: int, limit: int}
     */
    public function search(string $locale, string $query = '', int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);
        $needle = Str::lower(trim($query));
        $entries = $this->cache->catalog($locale);

        if ($needle !== '') {
            $entries = array_values(array_filter(
                $entries,
                static function (array $entry) use ($needle): bool {
                    $haystack = implode("\n", [
                        $entry['namespace'],
                        $entry['group'],
                        $entry['key'],
                        is_scalar($entry['original']) ? (string) $entry['original'] : '',
                        is_scalar($entry['value']) ? (string) $entry['value'] : '',
                    ]);

                    return Str::contains(Str::lower($haystack), $needle);
                },
            ));
        }

        return [
            'data' => array_slice($entries, $offset, $limit),
            'total' => count($entries),
            'offset' => $offset,
            'limit' => $limit,
        ];
    }
}
