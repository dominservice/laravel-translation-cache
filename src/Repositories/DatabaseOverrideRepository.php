<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Repositories;

use Dominservice\LaravelTranslationCache\Contracts\OverrideRepository;
use Dominservice\LaravelTranslationCache\Models\TranslationOverride;
use Dominservice\LaravelTranslationCache\Support\TranslationIdentity;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class DatabaseOverrideRepository implements OverrideRepository
{
    public function __construct(private ConnectionResolverInterface $connections) {}

    public function forIdentity(TranslationIdentity $identity): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return $this->query($identity)
            ->orderBy('translation_key')
            ->pluck('value', 'translation_key')
            ->all();
    }

    public function identities(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        /** @var Collection<int, TranslationOverride> $overrides */
        $overrides = TranslationOverride::query()
            ->select(['locale', 'namespace', 'group'])
            ->distinct()
            ->orderBy('locale')
            ->orderBy('namespace')
            ->orderBy('group')
            ->get();

        return $overrides
            ->map(fn (TranslationOverride $override): TranslationIdentity => new TranslationIdentity(
                $override->locale,
                $override->namespace,
                $override->group,
            ))
            ->all();
    }

    public function put(TranslationIdentity $identity, string $key, string $value): void
    {
        TranslationOverride::query()->updateOrCreate(
            ['identity_hash' => $identity->overrideHash($key)],
            [
                'locale' => $identity->locale,
                'namespace' => $identity->namespace,
                'group' => $identity->group,
                'translation_key' => $key,
                'value' => $value,
            ],
        );
    }

    public function delete(TranslationIdentity $identity, string $key): void
    {
        TranslationOverride::query()
            ->where('identity_hash', $identity->overrideHash($key))
            ->delete();
    }

    private function query(TranslationIdentity $identity): Builder
    {
        return TranslationOverride::query()
            ->where('locale', $identity->locale)
            ->where('namespace', $identity->namespace)
            ->where('group', $identity->group);
    }

    private function tableExists(): bool
    {
        $model = new TranslationOverride;
        $connection = $this->connections->connection($model->getConnectionName());

        return $connection->getSchemaBuilder()->hasTable($model->getTable());
    }
}
