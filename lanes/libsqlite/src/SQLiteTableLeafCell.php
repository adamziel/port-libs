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

    public static function parse(string $page, int $offset): self
    {
        if ($offset < 0 || $offset >= strlen($page)) {
            throw new \InvalidArgumentException('SQLite table leaf cell offset is outside the page');
        }

        [$payloadLength, $payloadLengthBytes] = SQLiteVarint::decode($page, $offset);
        [$rowId, $rowIdBytes] = SQLiteVarint::decode($page, $offset + $payloadLengthBytes);
        $payloadOffset = $offset + $payloadLengthBytes + $rowIdBytes;
        if ($payloadLength < 0 || $payloadOffset + $payloadLength > strlen($page)) {
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
    public static function parsePageCells(string $page, SQLiteBTreePageHeader $header): array
    {
        if (!$header->hasLeafData()) {
            throw new \InvalidArgumentException('SQLite table leaf cells require a table leaf b-tree page');
        }

        $cells = [];
        foreach ($header->cellPointers($page) as $pointer) {
            $cells[] = self::parse($page, $pointer);
        }

        return $cells;
    }
}
