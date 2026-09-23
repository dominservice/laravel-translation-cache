<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Feature;

use Dominservice\LaravelTranslationCache\CompiledTranslationLoader;
use Dominservice\LaravelTranslationCache\Tests\TestCase;

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
}
