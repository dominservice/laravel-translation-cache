<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Feature;

use Dominservice\LaravelTranslationCache\CompiledTranslationLoader;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Dominservice\LaravelTranslationCache\Tests\TestCase;
use Dominservice\LaravelTranslationCache\TranslationSource;

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
}
