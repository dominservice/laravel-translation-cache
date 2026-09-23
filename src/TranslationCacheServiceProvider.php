<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache;

use Dominservice\LaravelTranslationCache\Console\CacheTranslationsCommand;
use Dominservice\LaravelTranslationCache\Console\ClearTranslationCacheCommand;
use Dominservice\LaravelTranslationCache\Contracts\OverrideRepository;
use Dominservice\LaravelTranslationCache\Repositories\DatabaseOverrideRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

final class TranslationCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/translation-cache.php', 'translation-cache');

        $this->app->singleton(OverrideRepository::class, DatabaseOverrideRepository::class);

        $this->app->singleton(TranslationCacheStore::class, function (Application $application): TranslationCacheStore {
            $configuredPath = $application['config']->get('translation-cache.path');

            return new TranslationCacheStore(
                $application->make(Filesystem::class),
                is_string($configuredPath) && $configuredPath !== ''
                    ? $configuredPath
                    : $application->bootstrapPath('cache/translation-cache'),
                (bool) $application['config']->get('translation-cache.enabled', true),
            );
        });

        $this->app->extend('translation.loader', function (Loader $loader, Application $application): Loader {
            return new CompiledTranslationLoader(
                $loader,
                $application->make(TranslationCacheStore::class),
            );
        });

        $this->app->singleton(TranslationSource::class, function (Application $application): TranslationSource {
            $application->make('translator');

            $loader = $application->make('translation.loader');
            $source = $loader instanceof CompiledTranslationLoader ? $loader->source() : $loader;
            $locales = $application['config']->get('translation-cache.locales', []);

            return new TranslationSource(
                $source,
                $application->make(Filesystem::class),
                $application->make(OverrideRepository::class),
                is_array($locales) ? $locales : [],
            );
        });

        $this->app->singleton(TranslationCacheCompiler::class);
        $this->app->singleton(TranslationCatalog::class);
        $this->app->singleton(TranslationManager::class, function (Application $application): TranslationManager {
            return new TranslationManager(
                $application->make(TranslationSource::class),
                $application->make(OverrideRepository::class),
                $application->make(TranslationCacheCompiler::class),
                (bool) $application['config']->get('translation-cache.rebuild_after_write', true),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/translation-cache.php' => config_path('translation-cache.php'),
        ], 'translation-cache-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheTranslationsCommand::class,
                ClearTranslationCacheCommand::class,
            ]);
        }

        if (method_exists($this, 'optimizes')) {
            $this->optimizes(
                optimize: 'translations:cache',
                clear: 'translations:clear',
                key: 'translations',
            );
        }
    }
}
