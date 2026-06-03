<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipPackageEntry
{
    public function __construct(
        public readonly string $name,
        public readonly int $compressionMethod,
        public readonly int $generalPurposeFlags,
        public readonly int $crc32,
        public readonly int $compressedSize,
        public readonly int $uncompressedSize,
        public readonly int $localHeaderOffset,
        public readonly string $comment = '',
    ) {
    }

    public function isDirectory(): bool
    {
        return str_ends_with($this->name, '/');
    }

    public function crc32Hex(): string
    {
        return sprintf('%08x', $this->crc32);
    }
}
