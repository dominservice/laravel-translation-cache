<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Feature;

use Dominservice\LaravelTranslationCache\CompiledTranslationLoader;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Dominservice\LaravelTranslationCache\Tests\TestCase;
use Dominservice\LaravelTranslationCache\TranslationCacheCompiler;
use Dominservice\LaravelTranslationCache\TranslationCacheStore;
use Dominservice\LaravelTranslationCache\TranslationSource;
use Illuminate\Filesystem\Filesystem;

final class TranslationCacheServiceProviderTest extends TestCase
{
    public function test_decorates_laravels_translation_loader(): void
    {
        self::assertInstanceOf(
            CompiledTranslationLoader::class,
            $this->app->make('translation.loader'),
        );
    }

    public function test_cache_and_clear_commands_are_registered(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('translations:cache')
            ->expectsOutputToContain('translations:clear')
            ->assertSuccessful();
    }

    public function test_source_resolves_late_registered_translation_namespaces(): void
    {
        $identities = $this->app->make(TranslationSource::class)->identities();

        self::assertContains('late::messages', array_map(
            static fn (TranslationIdentity $identity): string => $identity->namespace.'::'.$identity->group,
            $identities,
        ));
    }

    public function test_missing_cached_key_is_loaded_from_source_and_rebuilds_cache(): void
    {
        $files = new Filesystem;
        $path = storage_path('framework/testing/missing-translation-source');
        $store = $this->app->make(TranslationCacheStore::class);

        $files->ensureDirectoryExists($path.'/en');
        $files->put($path.'/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'welcome' => 'Welcome',
];

PHP);
        $this->app->make('translator')->addNamespace('late', $path);
        $this->app->make(TranslationCacheCompiler::class)->compile();

        $files->put($path.'/en/messages.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'welcome' => 'Welcome',
    'added_later' => 'Added later',
];
PHP);

        try {
            self::assertSame('Added later', __('late::messages.added_later'));

            $cached = $store->load(TranslationIdentity::make('en', 'messages', 'late'));

            self::assertSame('Added later', $cached['added_later'] ?? null);
        } finally {
            $files->deleteDirectory($path);
        }
    }
}
