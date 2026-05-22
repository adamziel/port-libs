<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class Token
{
    public function __construct(
        public readonly string $kind,
        public readonly string $text,
        public readonly ?string $delimiterRole = null,
        public readonly int $depth = 0,
        public readonly int $start = 0,
        public readonly int $end = 0,
    ) {
    }
}
