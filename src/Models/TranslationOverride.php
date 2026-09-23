<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Models;

use Illuminate\Database\Eloquent\Model;

final class TranslationOverride extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'locale',
        'namespace',
        'group',
        'translation_key',
        'value',
        'identity_hash',
    ];

    public function getTable(): string
    {
        return (string) config('translation-cache.table', parent::getTable());
    }
}
