<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ReaderComparisonResult
{
    public function __construct(
        public readonly bool $equal,
        public readonly ?\Throwable $error = null,
    ) {
    }
}
