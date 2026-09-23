<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Tests\Support;

use Illuminate\Contracts\Translation\Loader;

final class CountingLoader implements Loader
{
    /**
     * @var list<array{locale: mixed, group: mixed, namespace: mixed}>
     */
    public array $loads = [];

    /**
     * @var array<string, string>
     */
    public array $registeredNamespaces = [];

    /**
     * @var list<string>
     */
    public array $jsonPaths = [];

    /**
     * @var list<string>
     */
    public array $paths = [];

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    public function __construct(private readonly array $translations = []) {}

    public function load($locale, $group, $namespace = null): array
    {
        $this->loads[] = compact('locale', 'group', 'namespace');
        $key = implode('|', [(string) $locale, (string) ($namespace ?: '*'), (string) $group]);

        return $this->translations[$key] ?? [];
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->registeredNamespaces[(string) $namespace] = (string) $hint;
    }

    public function addPath($path): void
    {
        $this->paths[] = (string) $path;
    }

    public function addJsonPath($path): void
    {
        $this->jsonPaths[] = (string) $path;
    }

    public function namespaces(): array
    {
        return $this->registeredNamespaces;
    }
}
