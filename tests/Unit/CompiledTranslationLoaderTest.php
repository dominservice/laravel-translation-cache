<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Unit;

use Dominservice\LaravelTranslationCache\CompiledTranslationLoader;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Dominservice\LaravelTranslationCache\Tests\Support\CountingLoader;
use Dominservice\LaravelTranslationCache\TranslationCacheStore;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class CompiledTranslationLoaderTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cachePath = sys_get_temp_dir().'/translation-cache-test-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->cachePath);

        parent::tearDown();
    }

    public function test_cached_group_is_loaded_without_loading_another_source_group(): void
    {
        $source = new CountingLoader([
            'en|*|user' => ['name' => 'Source user'],
        ]);
        $store = new TranslationCacheStore(new Filesystem, $this->cachePath);
        $admin = TranslationIdentity::make('en', 'admin');

        $store->replace([$admin->cacheKey() => ['title' => 'Cached admin']], []);

        $loader = new CompiledTranslationLoader($source, $store);

        self::assertSame(['title' => 'Cached admin'], $loader->load('en', 'admin', '*'));
        self::assertSame([], $source->loads);

        self::assertSame(['name' => 'Source user'], $loader->load('en', 'user', '*'));
        self::assertSame([
            ['locale' => 'en', 'group' => 'user', 'namespace' => '*'],
        ], $source->loads);
    }

    public function test_registration_calls_are_forwarded_to_the_laravel_loader(): void
    {
        $source = new CountingLoader;
        $loader = new CompiledTranslationLoader(
            $source,
            new TranslationCacheStore(new Filesystem, $this->cachePath),
        );

        $loader->addNamespace('package', '/translations/package');
        $loader->addPath('/translations/root');
        $loader->addJsonPath('/translations/json');

        self::assertSame(['package' => '/translations/package'], $source->registeredNamespaces);
        self::assertSame(['/translations/root'], $source->paths);
        self::assertSame(['/translations/json'], $source->jsonPaths);
    }

    public function test_corrupted_cache_is_treated_as_a_cache_miss(): void
    {
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->cachePath);
        $files->put($this->cachePath.'/manifest.php', '<?php return false;');
        $source = new CountingLoader([
            'en|*|admin' => ['title' => 'Source admin'],
        ]);
        $store = new TranslationCacheStore($files, $this->cachePath);
        $loader = new CompiledTranslationLoader($source, $store);

        self::assertSame(['title' => 'Source admin'], $loader->load('en', 'admin', '*'));

        $identity = TranslationIdentity::make('en', 'admin');
        $store->replace([$identity->cacheKey() => ['title' => 'Rebuilt admin']], []);

        self::assertSame(['title' => 'Rebuilt admin'], $loader->load('en', 'admin', '*'));
    }
}
