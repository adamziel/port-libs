<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTableLeafCell
{
    public function __construct(
        public readonly int $payloadLength,
        public readonly int $rowId,
        public readonly string $payload,
        public readonly int $offset,
        public readonly int $bytesRead,
    ) {
    }

    public static function parse(string $page, int $offset, ?int $usableSize = null): self
    {
        $usableSize ??= strlen($page);
        if ($usableSize < 0 || $usableSize > strlen($page)) {
            throw new \InvalidArgumentException('SQLite table leaf cell usable size is outside the page');
        }
        if ($offset < 0 || $offset >= $usableSize) {
            throw new \InvalidArgumentException('SQLite table leaf cell offset is outside the page');
        }

        [$payloadLength, $payloadLengthBytes] = SQLiteVarint::decode($page, $offset);
        [$rowId, $rowIdBytes] = SQLiteVarint::decode($page, $offset + $payloadLengthBytes);
        $payloadOffset = $offset + $payloadLengthBytes + $rowIdBytes;
        if ($payloadLength > self::maxLocalTablePayload($usableSize)) {
            throw new \InvalidArgumentException('SQLite table leaf overflow payloads are not yet supported');
        }
        if ($payloadLength < 0 || $payloadOffset + $payloadLength > $usableSize) {
            throw new \InvalidArgumentException('SQLite table leaf cell payload extends beyond the page');
        }

        return new self(
            $payloadLength,
            $rowId,
            substr($page, $payloadOffset, $payloadLength),
            $offset,
            $payloadLengthBytes + $rowIdBytes + $payloadLength,
        );
    }

    /**
     * @return list<self>
     */
    public static function parsePageCells(string $page, SQLiteBTreePageHeader $header, ?int $usableSize = null): array
    {
        if (!$header->hasLeafData()) {
            throw new \InvalidArgumentException('SQLite table leaf cells require a table leaf b-tree page');
        }
        $usableSize ??= $header->pageSize;

        $cells = [];
        foreach ($header->cellPointers($page) as $pointer) {
            $cells[] = self::parse($page, $pointer, $usableSize);
        }

        return $cells;
    }

    private static function maxLocalTablePayload(int $usableSize): int
    {
        if ($usableSize < 480) {
            throw new \InvalidArgumentException('SQLite table leaf usable size must be at least 480 bytes');
        }

        return $usableSize - 35;
    }
}
