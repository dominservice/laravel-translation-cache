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
    public function search(
        string $locale,
        string $query = '',
        int $limit = 100,
        int $offset = 0,
        string $sort = 'key',
        string $direction = 'asc',
    ): array {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);
        $needle = Str::lower(trim($query));
        $sort = in_array($sort, ['key', 'original', 'value'], true) ? $sort : 'key';
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
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

        usort($entries, function (array $left, array $right) use ($sort, $direction): int {
            $comparison = $sort === 'key'
                ? $this->compareIdentity($left, $right)
                : strnatcasecmp(
                    $this->sortableValue($left[$sort] ?? null),
                    $this->sortableValue($right[$sort] ?? null),
                );

            if ($comparison === 0) {
                $comparison = $this->compareIdentity($left, $right);
            }

            return $direction === 'desc' ? -$comparison : $comparison;
        });

        return [
            'data' => array_slice($entries, $offset, $limit),
            'total' => count($entries),
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    /**
     * @param  array{namespace: string, group: string, key: string}  $left
     * @param  array{namespace: string, group: string, key: string}  $right
     */
    private function compareIdentity(array $left, array $right): int
    {
        return [
            $left['namespace'],
            $left['group'],
            $left['key'],
        ] <=> [
            $right['namespace'],
            $right['group'],
            $right['key'],
        ];
    }

    private function sortableValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) ($value ?? '');
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
