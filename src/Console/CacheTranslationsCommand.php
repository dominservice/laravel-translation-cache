<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Console;

use Dominservice\LaravelTranslationCache\TranslationCacheCompiler;
use Illuminate\Console\Command;

final class CacheTranslationsCommand extends Command
{
    protected $signature = 'translations:cache';

    protected $description = 'Compile Laravel translations and database overrides';

    public function handle(TranslationCacheCompiler $compiler): int
    {
        $groups = $compiler->compile();

        $this->components->info("Translations cached ({$groups} groups).");

        return self::SUCCESS;
    }
}
