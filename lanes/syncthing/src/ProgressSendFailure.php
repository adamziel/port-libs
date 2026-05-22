<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class ProgressSendFailure
{
    public function __construct(
        public readonly string $deviceId,
        public readonly string $folder,
        public readonly \Throwable $throwable,
    ) {
    }
}
