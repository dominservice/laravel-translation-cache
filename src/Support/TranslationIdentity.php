<?php

declare(strict_types=1);

namespace Dominservice\LaravelTranslationCache\Support;

use InvalidArgumentException;

final readonly class TranslationIdentity
{
    public const ROOT_NAMESPACE = '*';

    public const JSON_GROUP = '*';

    public function __construct(
        public string $locale,
        public string $namespace,
        public string $group,
    ) {
        if ($this->locale === '' || $this->namespace === '' || $this->group === '') {
            throw new InvalidArgumentException('Translation locale, namespace, and group cannot be empty.');
        }
    }

    public static function make(string $locale, string $group, ?string $namespace = null): self
    {
        return new self($locale, $namespace ?: self::ROOT_NAMESPACE, $group);
    }

    public function cacheKey(): string
    {
        return hash('sha256', implode("\0", [$this->locale, $this->namespace, $this->group]));
    }

    public function overrideHash(string $key): string
    {
        return hash('sha256', implode("\0", [$this->locale, $this->namespace, $this->group, $key]));
    }

    public function isJson(): bool
    {
        return $this->namespace === self::ROOT_NAMESPACE && $this->group === self::JSON_GROUP;
    }
}
