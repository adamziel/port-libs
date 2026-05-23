<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanCheckpointSnapshot
{
    public function __construct(
        public readonly FolderScanCheckpoint $checkpoint,
        public readonly int $revision,
        public readonly int $updatedAt,
        public readonly ?int $expiresAt = null,
    ) {
        if ($this->revision <= 0) {
            throw new \InvalidArgumentException('Folder scan checkpoint snapshot revision must be positive');
        }
        if ($this->updatedAt < 0) {
            throw new \InvalidArgumentException('Folder scan checkpoint snapshot update time must not be negative');
        }
        if ($this->expiresAt !== null && $this->expiresAt < $this->updatedAt) {
            throw new \InvalidArgumentException('Folder scan checkpoint snapshot expiry must not be before update time');
        }
    }

    public function folderId(): string
    {
        return $this->checkpoint->folderId();
    }

    public function isExpired(int $now): bool
    {
        if ($now < 0) {
            throw new \InvalidArgumentException('Folder scan checkpoint snapshot clock must not be negative');
        }

        return $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRestStatus(): array
    {
        return $this->checkpoint->toRestStatus() + [
            'revision' => $this->revision,
            'updatedAt' => $this->updatedAt,
            'expiresAt' => $this->expiresAt,
            'expired' => false,
        ];
    }
}
