<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class AstNode
{
    /**
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     */
    public function __construct(
        public readonly string $type,
        public readonly array $attrs = [],
        public readonly array $children = [],
    ) {
    }

    public function attr(string $name, mixed $default = null): mixed
    {
        return $this->attrs[$name] ?? $default;
    }
}

