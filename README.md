# Laravel Translation Cache

Compile Laravel PHP, JSON, and namespaced translations together with database
overrides into an atomic file cache. Original language files are never changed.

Runtime reads stay lazy: resolving `admin.*` loads the cached `admin` group, not
`user`. Once the cache exists, translation rendering performs no database
queries.

## Requirements

- PHP 8.3+
- Laravel 13.32+

The current `2.x` line targets DominPress and Laravel 13. Laravel 12 remains available through the previous `1.x` release line.

## Installation

For a GitHub VCS repository that is not published on Packagist:

```bash
composer config repositories.dominservice-translation-cache vcs https://github.com/dominservice/laravel-translation-cache
composer require dominservice/laravel-translation-cache
php artisan migrate
php artisan translations:cache
```

The package participates in Laravel's standard optimization lifecycle:

```bash
php artisan optimize
php artisan optimize:clear
```

Publish the optional configuration file with:

```bash
php artisan vendor:publish --tag=translation-cache-config
```

## How it works

`translations:cache` discovers the locales and groups registered in Laravel's
translation loader, reads database overrides once, and writes a versioned cache
under `bootstrap/cache/translation-cache`. A manifest switch activates the new
version only after all files have been written and validated.

The cache contains:

- one PHP file per locale, namespace, and group for normal translation calls;
- one catalog per locale for admin search and editing screens.

If the cache does not exist or a newly added group has not been compiled yet,
the loader falls back to Laravel's original file loader. Database overrides are
intentionally not queried during that fallback. Rebuild the cache after adding
translation files or deploying code.

## Managing overrides

Use `TranslationManager`; it stores only values that differ from the original
translation. With the default configuration, each write rebuilds the cache.

```php
use Dominservice\LaravelTranslationCache\TranslationManager;

$translations = app(TranslationManager::class);

$translations->put(
    locale: 'pl',
    group: 'admin',
    key: 'menu.users',
    value: 'Konta użytkowników',
);

$translations->put(
    locale: 'pl',
    group: 'admin',
    key: 'title',
    value: 'Panel',
    namespace: 'projectcms',
);

$translations->delete(
    locale: 'pl',
    group: 'admin',
    key: 'menu.users',
);
```

Setting a value equal to the original automatically removes its database
override.

## Searching the compiled catalog

The catalog reads only the cache for the selected locale. It searches keys,
groups, namespaces, original values, and effective values. It can also sort by
the translation identity (`key`), original value, or effective value. Filtering
and sorting are applied before the requested page is sliced.

```php
use Dominservice\LaravelTranslationCache\TranslationCatalog;

$page = app(TranslationCatalog::class)->search(
    locale: 'pl',
    query: 'użytkownik',
    limit: 100,
    offset: 0,
    sort: 'original',
    direction: 'asc',
);
```

`sort` accepts only `key`, `original`, or `value`; `direction` accepts `asc` or
`desc`. Invalid values fall back to `key` and `asc`. Every order uses the full
translation identity as a deterministic tie-breaker, so pagination remains
stable.

Each result contains `original`, `value`, and `overridden`, which maps directly
to a two-column translation editor.

## Configuration

```php
return [
    'enabled' => true,
    'path' => null, // defaults to bootstrap/cache/translation-cache
    'table' => 'translation_overrides',
    'locales' => [],
    'rebuild_after_write' => true,
];
```

Locales are discovered from registered PHP and JSON translation paths. Add a
locale to `locales` only when it has database-only translations and no source
file from which it can be discovered.

## Testing

```bash
composer install
composer test
```

## License

MIT
