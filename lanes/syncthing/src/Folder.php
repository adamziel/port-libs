<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class Folder
{
    public const TYPE_SEND_RECEIVE = 0;
    public const TYPE_SEND_ONLY = 1;
    public const TYPE_RECEIVE_ONLY = 2;
    public const TYPE_RECEIVE_ENCRYPTED = 3;

    public const STOP_REASON_RUNNING = 0;
    public const STOP_REASON_PAUSED = 1;

    /**
     * @param list<Device> $devices
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label = '',
        public readonly int $type = self::TYPE_SEND_RECEIVE,
        public readonly int $stopReason = self::STOP_REASON_RUNNING,
        public readonly array $devices = [],
    ) {
        if (!in_array($this->type, [
            self::TYPE_SEND_RECEIVE,
            self::TYPE_SEND_ONLY,
            self::TYPE_RECEIVE_ONLY,
            self::TYPE_RECEIVE_ENCRYPTED,
        ], true)) {
            throw new \InvalidArgumentException('Unknown folder type');
        }
        if (!in_array($this->stopReason, [self::STOP_REASON_RUNNING, self::STOP_REASON_PAUSED], true)) {
            throw new \InvalidArgumentException('Unknown folder stop reason');
        }
        foreach ($this->devices as $device) {
            if (!$device instanceof Device) {
                throw new \InvalidArgumentException('Expected only Device instances');
            }
        }
    }

    public function isRunning(): bool
    {
        return $this->stopReason !== self::STOP_REASON_PAUSED;
    }

    public function description(): string
    {
        if ($this->label === '') {
            return $this->id;
        }

        return sprintf('"%s" (%s)', $this->label, $this->id);
    }
}

