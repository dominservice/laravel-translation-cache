<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Support;

use Dominservice\LaravelTranslationCache\Contracts\OverrideRepository;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;

final class InMemoryOverrideRepository implements OverrideRepository
{
    /**
     * @var array<string, array{identity: TranslationIdentity, values: array<string, string>}>
     */
    private array $groups = [];

    public function forIdentity(TranslationIdentity $identity): array
    {
        return $this->groups[$identity->cacheKey()]['values'] ?? [];
    }

    public function identities(): array
    {
        return array_values(array_map(
            static fn (array $group): TranslationIdentity => $group['identity'],
            $this->groups,
        ));
    }

    public function put(TranslationIdentity $identity, string $key, string $value): void
    {
        $this->groups[$identity->cacheKey()]['identity'] = $identity;
        $this->groups[$identity->cacheKey()]['values'][$key] = $value;
    }

    public function delete(TranslationIdentity $identity, string $key): void
    {
        unset($this->groups[$identity->cacheKey()]['values'][$key]);

        if (($this->groups[$identity->cacheKey()]['values'] ?? []) === []) {
            unset($this->groups[$identity->cacheKey()]);
        }
    }
}
