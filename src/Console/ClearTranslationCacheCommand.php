<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Console;

use Dominservice\LaravelTranslationCache\TranslationCacheStore;
use Illuminate\Console\Command;

final class ClearTranslationCacheCommand extends Command
{
    protected $signature = 'translations:clear';

    protected $description = 'Remove the compiled translation cache';

    public function handle(TranslationCacheStore $cache): int
    {
        $cache->clear();
        $this->components->info('Translation cache cleared.');

        return self::SUCCESS;
    }
}
