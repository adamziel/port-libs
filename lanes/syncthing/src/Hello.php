<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Hello
{
    public const MESSAGE_MAGIC = 0x2EA7D90B;
    public const VERSION_13_MAGIC = 0x9F79BC40;

    public function __construct(
        public readonly string $deviceName = '',
        public readonly string $clientName = '',
        public readonly string $clientVersion = '',
        public readonly int $numConnections = 0,
        public readonly int $timestamp = 0,
    ) {
        if ($this->numConnections < 0 || $this->timestamp < 0) {
            throw new \InvalidArgumentException('Hello numeric fields must not be negative');
        }
    }
}
