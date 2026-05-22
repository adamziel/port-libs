<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Availability
{
    public function __construct(
        public readonly string $deviceId,
        public readonly bool $fromTemporary = false,
    ) {
        if ($this->deviceId === '') {
            throw new \InvalidArgumentException('Availability device ID must not be empty');
        }
    }

    /**
     * @return array{device:string, fromTemporary:bool}
     */
    public function toArray(): array
    {
        return [
            'device' => $this->deviceId,
            'fromTemporary' => $this->fromTemporary,
        ];
    }
}
