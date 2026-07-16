<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FileDownloadProgressUpdate
{
    public const TYPE_APPEND = 0;
    public const TYPE_FORGET = 1;

    /**
     * @param list<int> $blockIndexes
     */
    public function __construct(
        public readonly int $updateType = self::TYPE_APPEND,
        public readonly string $name = '',
        public readonly VersionVector $version = new VersionVector(),
        public readonly array $blockIndexes = [],
        public readonly int $blockSize = 0,
    ) {
        if ($this->updateType < 0 || $this->blockSize < 0) {
            throw new \InvalidArgumentException('Download progress numeric fields must not be negative');
        }
        foreach ($this->blockIndexes as $blockIndex) {
            if (!is_int($blockIndex)) {
                throw new \InvalidArgumentException('Download progress block indexes must be integers');
            }
        }
    }

    public function isAppend(): bool
    {
        return $this->updateType === self::TYPE_APPEND;
    }

    public function isForget(): bool
    {
        return $this->updateType === self::TYPE_FORGET;
    }

    public function withName(string $name): self
    {
        return new self(
            updateType: $this->updateType,
            name: $name,
            version: $this->version,
            blockIndexes: $this->blockIndexes,
            blockSize: $this->blockSize,
        );
    }
}
