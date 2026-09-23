<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Feature;

use Dominservice\LaravelTranslationCache\Repositories\DatabaseOverrideRepository;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Dominservice\LaravelTranslationCache\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class DatabaseOverrideRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('translation_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 20);
            $table->string('namespace');
            $table->string('group');
            $table->text('translation_key');
            $table->longText('value');
            $table->char('identity_hash', 64)->unique();
            $table->timestamps();
        });
    }

    public function test_stores_updates_and_deletes_one_exact_override(): void
    {
        $repository = $this->app->make(DatabaseOverrideRepository::class);
        $identity = TranslationIdentity::make('pl', 'admin', 'cms');

        $repository->put($identity, 'menu.users', 'Użytkownicy');
        $repository->put($identity, 'menu.users', 'Konta użytkowników');

        self::assertSame(
            ['menu.users' => 'Konta użytkowników'],
            $repository->forIdentity($identity),
        );
        self::assertEquals([$identity], $repository->identities());
        $this->assertDatabaseCount('translation_overrides', 1);

        $repository->delete($identity, 'menu.users');

        self::assertSame([], $repository->forIdentity($identity));
        $this->assertDatabaseCount('translation_overrides', 0);
    }
}
