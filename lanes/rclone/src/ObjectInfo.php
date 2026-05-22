<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ObjectInfo
{
    public function __construct(
        public readonly string $path,
        public readonly int $size,
        public readonly string $sha256,
    ) {
    }
}

