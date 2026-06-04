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

            $fields[] = [
                'id' => $id,
                'data' => $data,
            ];
            $cursor = $dataStart + $size;
        }

        return $fields;
    }
}
