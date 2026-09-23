<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests;

use Dominservice\LaravelTranslationCache\TranslationCacheServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TranslationCacheServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set(
            'translation-cache.path',
            storage_path('framework/testing/translation-cache'),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(storage_path('framework/testing/translation-cache'));

        parent::tearDown();
    }
}
