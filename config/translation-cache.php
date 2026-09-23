<?php

declare(strict_types=1);

return [
    'enabled' => true,

    'path' => null,

    'table' => 'translation_overrides',

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Translation locales are discovered from registered Laravel language
    | paths. Add locales here only when they contain database-only entries.
    |
    */
    'locales' => [],

    'rebuild_after_write' => true,
];
