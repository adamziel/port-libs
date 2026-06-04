<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipPackageEntry
{
    private const FILETIME_UNIX_EPOCH_OFFSET_SECONDS = 11644473600;
    private const FILETIME_TICKS_PER_SECOND = 10000000;
    private const UINT32_FACTOR = 4294967296;

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
        public readonly string $centralExtraFieldData = '',
    ) {
        self::parseExtraFields($this->centralExtraFieldData, "central extra fields for {$this->name}");
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
        $extendedTimestamp = $this->extendedLastModifiedTimestamp();
        if ($extendedTimestamp !== null) {
            return $extendedTimestamp;
        }

        $ntfsTimestamp = $this->ntfsLastModifiedTimestamp();
        if ($ntfsTimestamp !== null) {
            return $ntfsTimestamp;
        }

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

    /**
     * @return list<array{id:int, data:string}>
     */
    public function centralExtraFields(): array
    {
        return self::parseExtraFields($this->centralExtraFieldData, "central extra fields for {$this->name}");
    }

    public function centralExtraField(int $id): ?string
    {
        if ($id < 0 || $id > 0xffff) {
            throw new \InvalidArgumentException('ZIP extra field id must fit in an unsigned 16-bit field');
        }

        foreach ($this->centralExtraFields() as $field) {
            if ($field['id'] === $id) {
                return $field['data'];
            }
        }

        return null;
    }

    public function extendedLastModifiedTimestamp(): ?int
    {
        return self::extendedTimestampFromExtraField(
            $this->centralExtraField(0x5455),
            $this->name
        );
    }

    /**
     * @return array{modifiedAt:int, accessedAt:int, createdAt:int}|null
     */
    public function ntfsTimestamps(): ?array
    {
        return self::ntfsTimestampsFromExtraField(
            $this->centralExtraField(0x000a),
            "central extra fields for {$this->name}"
        );
    }

    public function ntfsLastModifiedTimestamp(): ?int
    {
        $timestamps = $this->ntfsTimestamps();

        return $timestamps['modifiedAt'] ?? null;
    }

    /**
     * @return list<array{id:int, data:string}>
     */
    public static function extraFieldsFromData(string $bytes, string $label): array
    {
        return self::parseExtraFields($bytes, $label);
    }

    public static function extendedTimestampFromExtraField(?string $data, string $label): ?int
    {
        if ($data === null || strlen($data) < 1) {
            return null;
        }

        $flags = ord($data[0]);
        if (($flags & 0x01) === 0) {
            return null;
        }

        if (strlen($data) < 5) {
            throw new \RuntimeException("ZIP extended timestamp extra field for {$label} is truncated");
        }

        $values = unpack('Vtimestamp', substr($data, 1, 4));
        if (!is_array($values)) {
            throw new \RuntimeException("Unable to read ZIP extended timestamp extra field for {$label}");
        }

        return (int) $values['timestamp'];
    }

    /**
     * @return array{modifiedAt:int, accessedAt:int, createdAt:int}|null
     */
    public static function ntfsTimestampsFromExtraField(?string $data, string $label): ?array
    {
        if ($data === null) {
            return null;
        }

        return self::parseNtfsTimestamps($data, $label);
    }

    public static function validateExtraFieldData(string $bytes, string $label): void
    {
        self::parseExtraFields($bytes, $label);
    }

    /**
     * @return list<array{id:int, data:string}>
     */
    private static function parseExtraFields(string $bytes, string $label): array
    {
        $fields = [];
        $cursor = 0;
        $length = strlen($bytes);

        while ($cursor < $length) {
            if ($cursor + 4 > $length) {
                throw new \RuntimeException("ZIP {$label} contains a truncated extra field header");
            }

            $header = unpack('vid/vsize', substr($bytes, $cursor, 4));
            if (!is_array($header)) {
                throw new \RuntimeException("Unable to read ZIP {$label}");
            }

            $id = (int) $header['id'];
            $size = (int) $header['size'];
            $dataStart = $cursor + 4;
            if ($dataStart + $size > $length) {
                throw new \RuntimeException("ZIP {$label} contains a truncated extra field payload");
            }

            $data = substr($bytes, $dataStart, $size);
            if ($id === 0x5455 && strlen($data) > 0 && (ord($data[0]) & 0x01) !== 0 && strlen($data) < 5) {
                throw new \RuntimeException("ZIP extended timestamp extra field in {$label} is truncated");
            }
            if ($id === 0x000a) {
                self::parseNtfsTimestamps($data, $label);
            }

            $fields[] = [
                'id' => $id,
                'data' => $data,
            ];
            $cursor = $dataStart + $size;
        }

        return $fields;
    }

    /**
     * @return array{modifiedAt:int, accessedAt:int, createdAt:int}|null
     */
    private static function parseNtfsTimestamps(string $data, string $label): ?array
    {
        $length = strlen($data);
        if ($length < 4) {
            throw new \RuntimeException("ZIP NTFS extra field for {$label} is truncated");
        }

        $cursor = 4;
        while ($cursor < $length) {
            if ($cursor + 4 > $length) {
                throw new \RuntimeException("ZIP NTFS extra field for {$label} contains a truncated attribute header");
            }

            $header = unpack('vtag/vsize', substr($data, $cursor, 4));
            if (!is_array($header)) {
                throw new \RuntimeException("Unable to read ZIP NTFS extra field for {$label}");
            }

            $tag = (int) $header['tag'];
            $size = (int) $header['size'];
            $valueStart = $cursor + 4;
            if ($valueStart + $size > $length) {
                throw new \RuntimeException("ZIP NTFS extra field for {$label} contains a truncated attribute payload");
            }

            $value = substr($data, $valueStart, $size);
            if ($tag === 0x0001) {
                if ($size !== 24) {
                    throw new \RuntimeException("ZIP NTFS timestamp attribute for {$label} must contain three FILETIME values");
                }

                return [
                    'modifiedAt' => self::fileTimeToUnixTimestamp(substr($value, 0, 8), $label),
                    'accessedAt' => self::fileTimeToUnixTimestamp(substr($value, 8, 8), $label),
                    'createdAt' => self::fileTimeToUnixTimestamp(substr($value, 16, 8), $label),
                ];
            }

            $cursor = $valueStart + $size;
        }

        return null;
    }

    private static function fileTimeToUnixTimestamp(string $bytes, string $label): int
    {
        $parts = unpack('Vlow/Vhigh', $bytes);
        if (!is_array($parts)) {
            throw new \RuntimeException("Unable to read ZIP NTFS FILETIME value for {$label}");
        }

        $low = (int) $parts['low'];
        $high = (int) $parts['high'];
        $maxHigh = intdiv(PHP_INT_MAX, self::UINT32_FACTOR);
        if ($high > $maxHigh) {
            throw new \RuntimeException("ZIP NTFS FILETIME value for {$label} is too large for this platform");
        }

        $fileTime = ($high * self::UINT32_FACTOR) + $low;
        if ($fileTime > PHP_INT_MAX) {
            throw new \RuntimeException("ZIP NTFS FILETIME value for {$label} is too large for this platform");
        }

        return intdiv($fileTime, self::FILETIME_TICKS_PER_SECOND) - self::FILETIME_UNIX_EPOCH_OFFSET_SECONDS;
    }
}
