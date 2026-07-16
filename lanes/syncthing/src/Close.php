<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Close
{
    public function __construct(
        public readonly string $reason = '',
    ) {
    }
}
