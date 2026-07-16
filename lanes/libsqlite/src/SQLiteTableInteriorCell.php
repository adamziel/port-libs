<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTableInteriorCell
{
    public function __construct(
        public readonly int $leftChildPage,
        public readonly int $key,
        public readonly int $offset,
        public readonly int $bytesRead,
    ) {
    }

    public static function encode(int $leftChildPage, int $key): string
    {
        if ($leftChildPage < 1) {
            throw new \InvalidArgumentException('SQLite table interior cell child page must be positive');
        }
        if ($key < 0) {
            throw new \InvalidArgumentException('SQLite table interior cell key encoding currently supports non-negative rowids only');
        }

        return pack('N', $leftChildPage) . SQLiteVarint::encode($key);
    }

    public static function parse(string $page, int $offset): self
    {
        if ($offset < 0 || $offset + 4 > strlen($page)) {
            throw new \InvalidArgumentException('SQLite table interior cell offset is outside the page');
        }

        $leftChildPage = unpack('N', substr($page, $offset, 4))[1];
        if ($leftChildPage < 1) {
            throw new \InvalidArgumentException('SQLite table interior cell child page must be positive');
        }

        [$key, $keyBytes] = SQLiteVarint::decode($page, $offset + 4);

        return new self($leftChildPage, $key, $offset, 4 + $keyBytes);
    }

    /**
     * @return list<self>
     */
    public static function parsePageCells(string $page, SQLiteBTreePageHeader $header): array
    {
        if ($header->pageType !== 'table-interior') {
            throw new \InvalidArgumentException('SQLite table interior cells require a table interior b-tree page');
        }

        $cells = [];
        foreach ($header->cellPointers($page) as $pointer) {
            $cells[] = self::parse($page, $pointer);
        }

        return $cells;
    }
}
