<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class Token
{
    public function __construct(
        public readonly string $kind,
        public readonly string $text,
    ) {
    }
}

