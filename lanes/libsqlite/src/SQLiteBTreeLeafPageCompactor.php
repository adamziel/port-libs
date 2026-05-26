<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeLeafPageCompactor
{
    /**
     * @param list<array{offset:int,bytes:int}> $cells
     */
    public static function compact(
        string $page,
        SQLiteBTreePageHeader $header,
        array $cells,
        int $usableSize,
        bool $clearFreeSpace = false,
    ): string {
        if (!$header->isLeaf()) {
            throw new \InvalidArgumentException('SQLite b-tree defragmentation currently supports leaf pages only');
        }
        if (strlen($page) !== $header->pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree page length does not match page size');
        }
        if ($usableSize < 480 || $usableSize > $header->pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree usable size is outside the page');
        }
        if (count($cells) !== $header->cellCount) {
            throw new \InvalidArgumentException('SQLite b-tree defragmentation cell count does not match the page header');
        }

        $cellPointerArrayEnd = $header->cellPointerArrayEnd();
        $newPage = $page;
        $offset = $usableSize;
        $newPointers = [];

        foreach ($cells as $cell) {
            if (!is_int($cell['offset']) || !is_int($cell['bytes']) || $cell['bytes'] <= 0) {
                throw new \InvalidArgumentException('SQLite b-tree defragmentation cells require positive offsets and sizes');
            }
            if ($cell['offset'] < $header->cellContentAreaStart || $cell['offset'] + $cell['bytes'] > $usableSize) {
                throw new \InvalidArgumentException('SQLite b-tree defragmentation cell extends outside usable content');
            }

            $cellBytes = substr($page, $cell['offset'], $cell['bytes']);
            $offset -= $cell['bytes'];
            if ($offset < $cellPointerArrayEnd) {
                throw new \InvalidArgumentException('SQLite b-tree defragmentation cells overlap the pointer array');
            }
            $newPage = substr_replace($newPage, $cellBytes, $offset, $cell['bytes']);
            $newPointers[] = $offset;
        }

        $contentStart = $header->cellCount === 0 ? $usableSize : min($newPointers);
        if ($clearFreeSpace || $contentStart > $header->cellPointerArrayEnd()) {
            $newPage = substr_replace(
                $newPage,
                str_repeat("\0", $contentStart - $header->headerOffset),
                $header->headerOffset,
                $contentStart - $header->headerOffset,
            );
        }

        $newPage[$header->headerOffset] = chr($header->pageTypeFlag);
        $newPage = substr_replace($newPage, "\0\0", $header->headerOffset + 1, 2);
        $newPage = substr_replace($newPage, pack('n', $header->cellCount), $header->headerOffset + 3, 2);
        $newPage = substr_replace(
            $newPage,
            pack('n', $contentStart === 65536 ? 0 : $contentStart),
            $header->headerOffset + 5,
            2,
        );
        $newPage[$header->headerOffset + 7] = "\x00";

        foreach ($newPointers as $index => $pointer) {
            $newPage = substr_replace(
                $newPage,
                pack('n', $pointer),
                $header->cellPointerArrayOffset() + ($index * 2),
                2,
            );
        }

        return $newPage;
    }
}
