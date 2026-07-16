<?php

declare(strict_types=1);

namespace PortLibs\Esbuild;

final class Token
{
    public function __construct(
        public readonly string $kind,
        public readonly string $text,
        public readonly int $offset,
        public readonly ?float $numberValue = null,
    ) {
    }
}
