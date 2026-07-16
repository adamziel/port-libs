<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTableInteriorPage
{
    /**
     * @param list<string> $cells
     */
    public static function assemble(
        array $cells,
        int $rightMostPage,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?string $basePage = null,
        ?int $usableSize = null,
    ): string {
        self::validatePageSize($pageSize);
        if ($rightMostPage < 1) {
            throw new \InvalidArgumentException('SQLite table interior right-most page must be positive');
        }

        $usableSize ??= $pageSize;
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite table interior usable size is outside the page');
        }
        if ($headerOffset < 0 || $headerOffset + 12 > $usableSize) {
            throw new \InvalidArgumentException('SQLite table interior page header offset is outside the usable page');
        }
        if (count($cells) > 65535) {
            throw new \InvalidArgumentException('SQLite table interior page cannot hold more than 65535 cell pointers');
        }

        $page = $basePage ?? str_repeat("\0", $pageSize);
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite table interior base page length does not match page size');
        }

        $cellCount = count($cells);
        $minimumCellOffset = $headerOffset + 12 + ($cellCount * 2);
        $offset = $usableSize;
        $pointers = [];
        foreach ($cells as $cell) {
            if (!is_string($cell) || $cell === '') {
                throw new \InvalidArgumentException('SQLite table interior page cells must be non-empty byte strings');
            }
            $offset -= strlen($cell);
            if ($offset < $minimumCellOffset) {
                throw new \InvalidArgumentException('SQLite table interior page cells overlap the header or pointer array');
            }
            $page = substr_replace($page, $cell, $offset, strlen($cell));
            $pointers[] = $offset;
        }

        $contentStart = $cellCount === 0 ? $usableSize : min($pointers);
        $page = substr_replace(
            $page,
            str_repeat("\0", $contentStart - $headerOffset),
            $headerOffset,
            $contentStart - $headerOffset,
        );

        $page[$headerOffset] = "\x05";
        $page = substr_replace($page, pack('n', 0), $headerOffset + 1, 2);
        $page = substr_replace($page, pack('n', $cellCount), $headerOffset + 3, 2);
        $page = substr_replace(
            $page,
            pack('n', $contentStart === 65536 ? 0 : $contentStart),
            $headerOffset + 5,
            2,
        );
        $page[$headerOffset + 7] = "\x00";
        $page = substr_replace($page, pack('N', $rightMostPage), $headerOffset + 8, 4);

        foreach ($pointers as $index => $pointer) {
            $page = substr_replace($page, pack('n', $pointer), $headerOffset + 12 + ($index * 2), 2);
        }

        return $page;
    }

    private static function validatePageSize(int $pageSize): void
    {
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite page size must be a power of two between 512 and 65536 bytes');
        }
    }
}
