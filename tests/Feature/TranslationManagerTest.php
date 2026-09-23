<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Feature;

use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Dominservice\LaravelTranslationCache\Tests\Support\InMemoryOverrideRepository;
use Dominservice\LaravelTranslationCache\Tests\TestCase;
use Dominservice\LaravelTranslationCache\TranslationCacheCompiler;
use Dominservice\LaravelTranslationCache\TranslationCacheStore;
use Dominservice\LaravelTranslationCache\TranslationManager;
use Dominservice\LaravelTranslationCache\TranslationSource;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;

final class TranslationManagerTest extends TestCase
{
    public function test_stores_only_a_value_that_differs_from_the_original_and_rebuilds_cache(): void
    {
        [$manager, $overrides, $store] = $this->services();
        $identity = TranslationIdentity::make('en', 'admin');

        $manager->put('en', 'admin', 'title', 'Control panel');

        self::assertSame(['title' => 'Control panel'], $overrides->forIdentity($identity));
        self::assertSame('Control panel', $store->load($identity)['title']);

        $manager->put('en', 'admin', 'title', 'Administration');

        self::assertSame([], $overrides->forIdentity($identity));
        self::assertSame('Administration', $store->load($identity)['title']);
    }

    public function test_delete_restores_the_original_value_in_the_cache(): void
    {
        [$manager, $overrides, $store] = $this->services();
        $identity = TranslationIdentity::make('en', 'admin');
        $overrides->put($identity, 'title', 'Control panel');

        $manager->delete('en', 'admin', 'title');

        self::assertSame([], $overrides->forIdentity($identity));
        self::assertSame('Administration', $store->load($identity)['title']);
    }

    /**
     * @return array{TranslationManager, InMemoryOverrideRepository, TranslationCacheStore}
     */
    private function services(): array
    {
        $files = new Filesystem;
        $loader = new FileLoader($files, [dirname(__DIR__).'/Fixtures/lang']);
        $overrides = new InMemoryOverrideRepository;
        $source = new TranslationSource($loader, $files, $overrides, ['en']);
        $store = new TranslationCacheStore(
            $files,
            (string) config('translation-cache.path'),
        );
        $compiler = new TranslationCacheCompiler($source, $overrides, $store, $this->app);

        return [
            new TranslationManager($source, $overrides, $compiler),
            $overrides,
            $store,
        ];
    }
}
