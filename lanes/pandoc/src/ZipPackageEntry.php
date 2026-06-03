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
        public readonly int $lastModifiedTime = 0,
        public readonly int $lastModifiedDate = 0,
        public readonly int $externalFileAttributes = 0,
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

    public function modifiedDosTime(): int
    {
        return $this->lastModifiedTime;
    }

    public function modifiedDosDate(): int
    {
        return $this->lastModifiedDate;
    }

    public function lastModifiedTimestamp(): ?int
    {
        if ($this->lastModifiedTime === 0 && $this->lastModifiedDate === 0) {
            return null;
        }

        $year = (($this->lastModifiedDate >> 9) & 0x7f) + 1980;
        $month = ($this->lastModifiedDate >> 5) & 0x0f;
        $day = $this->lastModifiedDate & 0x1f;
        $hour = ($this->lastModifiedTime >> 11) & 0x1f;
        $minute = ($this->lastModifiedTime >> 5) & 0x3f;
        $second = ($this->lastModifiedTime & 0x1f) * 2;

        if (
            !checkdate($month, $day, $year)
            || $hour > 23
            || $minute > 59
            || $second > 59
        ) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
            new \DateTimeZone('UTC')
        );

        return $date instanceof \DateTimeImmutable ? $date->getTimestamp() : null;
    }
}
