<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class ModuleImport
{
    /**
     * @param list<array{imported:string, local:?string}> $specifiers
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $source,
        public readonly array $specifiers,
        public readonly int $offset,
        public readonly ?string $attributesKeyword = null,
        public readonly array $attributes = [],
    ) {
    }

    public function isRelative(): bool
    {
        return str_starts_with($this->source, './')
            || str_starts_with($this->source, '../')
            || str_starts_with($this->source, '/');
    }

    public function isPackage(): bool
    {
        return !$this->isRelative()
            && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $this->source);
    }

    public function isWordPressPackage(): bool
    {
        return str_starts_with($this->source, '@wordpress/');
    }

    public function hasJsonTypeAttribute(): bool
    {
        return ($this->attributes['type'] ?? null) === 'json';
    }
}
