<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PackDataEntry
{
    public function __construct(
        public readonly string $kind,
        public readonly int $decompressedSize,
        public readonly int $packOffset,
        public readonly int $dataOffset,
        public readonly int $headerSize,
        public readonly string $data,
        public readonly ?int $baseDistance = null,
        public readonly ?string $baseObjectId = null,
    ) {
        if (!in_array($kind, ['commit', 'tree', 'blob', 'tag', 'ofs-delta', 'ref-delta'], true)) {
            throw new \InvalidArgumentException("Unsupported pack data entry kind: {$kind}");
        }
        if ($decompressedSize < 0 || $packOffset < 0 || $dataOffset < 0 || $headerSize <= 0) {
            throw new \InvalidArgumentException('Pack data entry offsets and sizes must be positive');
        }
    }

    public function isDelta(): bool
    {
        return $this->kind === 'ofs-delta' || $this->kind === 'ref-delta';
    }

    public function object(): GitObject
    {
        if ($this->isDelta()) {
            throw new \RuntimeException('Delta pack entries are parsed but not resolved in this slice');
        }

        return new GitObject($this->kind, $this->data);
    }
}
