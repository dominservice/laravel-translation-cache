<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Feature;

use Dominservice\LaravelTranslationCache\CompiledTranslationLoader;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Dominservice\LaravelTranslationCache\Tests\Support\InMemoryOverrideRepository;
use Dominservice\LaravelTranslationCache\Tests\TestCase;
use Dominservice\LaravelTranslationCache\TranslationCacheCompiler;
use Dominservice\LaravelTranslationCache\TranslationCacheStore;
use Dominservice\LaravelTranslationCache\TranslationCatalog;
use Dominservice\LaravelTranslationCache\TranslationSource;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;

final class TranslationCacheCompilerTest extends TestCase
{
    public function test_compiles_root_json_and_namespaced_groups_without_changing_sources(): void
    {
        $files = new Filesystem;
        $root = dirname(__DIR__).'/Fixtures/lang';
        $namespace = dirname(__DIR__).'/Fixtures/namespaced';
        $sourceHash = hash_file('sha256', $root.'/en/admin.php');
        $loader = new FileLoader($files, [$root]);
        $loader->addNamespace('package', $namespace);
        $overrides = new InMemoryOverrideRepository;
        $overrides->put(TranslationIdentity::make('en', 'admin'), 'title', 'Control panel');
        $overrides->put(TranslationIdentity::make('en', '*'), 'Sentence.with.dots', 'Changed sentence');
        $overrides->put(TranslationIdentity::make('en', 'panel', 'package'), 'heading', 'Changed package');
        $store = new TranslationCacheStore(
            $files,
            (string) config('translation-cache.path'),
        );
        $source = new TranslationSource($loader, $files, $overrides, ['en']);
        $compiler = new TranslationCacheCompiler($source, $overrides, $store, $this->app);

        self::assertSame(4, $compiler->compile());
        self::assertSame($sourceHash, hash_file('sha256', $root.'/en/admin.php'));

        $compiled = new CompiledTranslationLoader($loader, $store);

        self::assertSame('Control panel', $compiled->load('en', 'admin', '*')['title']);
        self::assertSame('User name', $compiled->load('en', 'user', '*')['name']);
        self::assertSame('Changed sentence', $compiled->load('en', '*', '*')['Sentence.with.dots']);
        self::assertSame('Changed package', $compiled->load('en', 'panel', 'package')['heading']);

        $results = (new TranslationCatalog($store))->search('en', 'control panel');

        self::assertSame(1, $results['total']);
        self::assertSame('Administration', $results['data'][0]['original']);
        self::assertSame('Control panel', $results['data'][0]['value']);
        self::assertTrue($results['data'][0]['overridden']);
    }

    public function test_new_manifest_replaces_the_active_cache_atomically(): void
    {
        $files = new Filesystem;
        $store = new TranslationCacheStore(
            $files,
            (string) config('translation-cache.path'),
        );
        $identity = TranslationIdentity::make('en', 'admin');

        $store->replace([$identity->cacheKey() => ['title' => 'First']], []);
        self::assertSame(['title' => 'First'], $store->load($identity));

        $store->replace([$identity->cacheKey() => ['title' => 'Second']], []);
        self::assertSame(['title' => 'Second'], $store->load($identity));
    }
}
