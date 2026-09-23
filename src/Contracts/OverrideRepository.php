<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Contracts;

use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;

interface OverrideRepository
{
    /**
     * @return array<string, string>
     */
    public function forIdentity(TranslationIdentity $identity): array;

    /**
     * @return list<TranslationIdentity>
     */
    public function identities(): array;

    public function put(TranslationIdentity $identity, string $key, string $value): void;

    public function delete(TranslationIdentity $identity, string $key): void;
}
