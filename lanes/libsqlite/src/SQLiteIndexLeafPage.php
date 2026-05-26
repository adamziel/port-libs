<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexLeafPage
{
    /**
     * @param list<string> $cells
     */
    public static function assemble(
        array $cells,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?string $basePage = null,
        ?int $usableSize = null,
    ): string {
        self::validatePageSize($pageSize);
        $usableSize ??= $pageSize;
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite index leaf usable size is outside the page');
        }
        if ($headerOffset < 0 || $headerOffset + 8 > $usableSize) {
            throw new \InvalidArgumentException('SQLite index leaf page header offset is outside the usable page');
        }
        if (count($cells) > 65535) {
            throw new \InvalidArgumentException('SQLite index leaf page cannot hold more than 65535 cell pointers');
        }

        $page = $basePage ?? str_repeat("\0", $pageSize);
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite index leaf base page length does not match page size');
        }

        $cellCount = count($cells);
        $minimumCellOffset = $headerOffset + 8 + ($cellCount * 2);
        $offset = $usableSize;
        $pointers = [];
        foreach ($cells as $cell) {
            if (!is_string($cell) || $cell === '') {
                throw new \InvalidArgumentException('SQLite index leaf page cells must be non-empty byte strings');
            }
            $offset -= strlen($cell);
            if ($offset < $minimumCellOffset) {
                throw new \InvalidArgumentException('SQLite index leaf page cells overlap the header or pointer array');
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

        $page[$headerOffset] = "\x0a";
        $page = substr_replace($page, pack('n', 0), $headerOffset + 1, 2);
        $page = substr_replace($page, pack('n', $cellCount), $headerOffset + 3, 2);
        $page = substr_replace(
            $page,
            pack('n', $contentStart === 65536 ? 0 : $contentStart),
            $headerOffset + 5,
            2,
        );
        $page[$headerOffset + 7] = "\x00";

        foreach ($pointers as $index => $pointer) {
            $page = substr_replace($page, pack('n', $pointer), $headerOffset + 8 + ($index * 2), 2);
        }

        return $page;
    }

    /**
     * @param list<mixed> $recordValues
     */
    public static function deleteCellByRecordValues(
        string $page,
        array $recordValues,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        int $textEncoding = 1,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): string {
        self::validatePageSize($pageSize);
        $usableSize ??= $pageSize;
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite index leaf page length does not match page size');
        }
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite index leaf usable size is outside the page');
        }

        $header = SQLiteBTreePageHeader::parsePage($page, $pageSize, $headerOffset);
        if ($header->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite index leaf deletion requires an index leaf page');
        }

        $cells = SQLiteIndexCell::parsePageCells($page, $header, $usableSize, $overflowReader);
        $deleteIndex = null;
        foreach ($cells as $index => $cell) {
            if ($cell->record($textEncoding)->values === $recordValues) {
                $deleteIndex = $index;
                break;
            }
        }
        if ($deleteIndex === null) {
            throw new \InvalidArgumentException('SQLite index leaf cell record was not found');
        }

        $deletedCell = $cells[$deleteIndex];
        $newPage = $page;
        $remainingPointers = $header->cellPointers($page);
        array_splice($remainingPointers, $deleteIndex, 1);

        $cellCount = count($remainingPointers);
        $newPage = substr_replace($newPage, pack('n', $cellCount), $headerOffset + 3, 2);
        $pointerArrayOffset = $header->cellPointerArrayOffset();
        foreach ($remainingPointers as $index => $pointer) {
            $newPage = substr_replace($newPage, pack('n', $pointer), $pointerArrayOffset + ($index * 2), 2);
        }
        $newPage = substr_replace($newPage, "\0\0", $pointerArrayOffset + ($cellCount * 2), 2);

        if ($secureDelete && $deletedCell->bytesRead > 4) {
            $newPage = substr_replace(
                $newPage,
                str_repeat("\0", $deletedCell->bytesRead - 4),
                $deletedCell->offset + 4,
                $deletedCell->bytesRead - 4,
            );
        }

        return self::insertFreeblock($newPage, $header, $deletedCell->offset, $deletedCell->bytesRead, $usableSize, $secureDelete);
    }

    /**
     * @param list<mixed> $recordValues
     * @param callable(int, int): list<int> $overflowPageNumbers
     * @return array{page:string,record_values:list<mixed>,obsolete_overflow_page_numbers:list<int>,deleted_local_payload_length:int,deleted_cell_bytes:int}
     */
    public static function deleteCellByRecordValuesWithOverflowRelease(
        string $page,
        array $recordValues,
        callable $overflowPageNumbers,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        int $textEncoding = 1,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): array {
        self::validatePageSize($pageSize);
        $usableSize ??= $pageSize;
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite index leaf page length does not match page size');
        }
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite index leaf usable size is outside the page');
        }

        $header = SQLiteBTreePageHeader::parsePage($page, $pageSize, $headerOffset);
        if ($header->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite index leaf deletion requires an index leaf page');
        }

        $cells = SQLiteIndexCell::parsePageCells(
            $page,
            $header,
            $usableSize,
            $overflowReader ?? static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount),
        );
        $deletedCell = null;
        foreach ($cells as $cell) {
            if ($cell->record($textEncoding)->values === $recordValues) {
                $deletedCell = $cell;
                break;
            }
        }
        if ($deletedCell === null) {
            throw new \InvalidArgumentException('SQLite index leaf cell record was not found');
        }

        $obsoleteOverflowPageNumbers = [];
        if ($deletedCell->firstOverflowPage !== null) {
            $obsoleteOverflowPageNumbers = $overflowPageNumbers(
                $deletedCell->firstOverflowPage,
                $deletedCell->payloadLength - $deletedCell->localPayloadLength,
            );
        }

        return [
            'page' => self::deleteCellByRecordValues(
                $page,
                $recordValues,
                $pageSize,
                $headerOffset,
                $usableSize,
                $textEncoding,
                $secureDelete,
                $overflowReader ?? static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount),
            ),
            'record_values' => $recordValues,
            'obsolete_overflow_page_numbers' => $obsoleteOverflowPageNumbers,
            'deleted_local_payload_length' => $deletedCell->localPayloadLength,
            'deleted_cell_bytes' => $deletedCell->bytesRead,
        ];
    }

    /**
     * @param list<list<mixed>> $recordValuesList
     */
    public static function deleteCellsByRecordValues(
        string $page,
        array $recordValuesList,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        int $textEncoding = 1,
        bool $secureDelete = false,
    ): string {
        if ($recordValuesList === []) {
            throw new \InvalidArgumentException('SQLite index leaf bulk deletion requires at least one record value list');
        }

        $deletedPage = $page;
        foreach ($recordValuesList as $recordValues) {
            if (!is_array($recordValues)) {
                throw new \InvalidArgumentException('SQLite index leaf bulk deletion records must be value lists');
            }
            $deletedPage = self::deleteCellByRecordValues(
                $deletedPage,
                $recordValues,
                $pageSize,
                $headerOffset,
                $usableSize,
                $textEncoding,
                $secureDelete,
            );
        }

        return $deletedPage;
    }

    public static function defragment(
        string $page,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        bool $clearFreeSpace = false,
    ): string {
        self::validatePageSize($pageSize);
        $usableSize ??= $pageSize;
        if (strlen($page) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite index leaf page length does not match page size');
        }

        $header = SQLiteBTreePageHeader::parsePage($page, $pageSize, $headerOffset);
        if ($header->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite index leaf defragmentation requires an index leaf page');
        }

        $cells = array_map(
            static fn (SQLiteIndexCell $cell): array => [
                'offset' => $cell->offset,
                'bytes' => $cell->bytesRead,
            ],
            SQLiteIndexCell::parsePageCells($page, $header, $usableSize),
        );

        return SQLiteBTreeLeafPageCompactor::compact($page, $header, $cells, $usableSize, $clearFreeSpace);
    }

    private static function insertFreeblock(
        string $page,
        SQLiteBTreePageHeader $header,
        int $offset,
        int $size,
        int $usableSize,
        bool $secureDelete,
    ): string {
        if ($size < 4) {
            $fragmented = ord($page[$header->headerOffset + 7]) + $size;
            if ($fragmented > 60) {
                throw new \InvalidArgumentException('SQLite index leaf deletion would exceed fragmented free byte limit');
            }

            $page[$header->headerOffset + 7] = chr($fragmented);
            return $page;
        }

        $blocks = array_map(
            static fn (SQLiteBTreeFreeblock $freeblock): array => [
                'offset' => $freeblock->offset,
                'size' => $freeblock->size,
            ],
            $header->freeblocks($page, $usableSize),
        );
        $blocks[] = ['offset' => $offset, 'size' => $size];
        usort($blocks, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        $coalesced = [];
        foreach ($blocks as $block) {
            $lastIndex = count($coalesced) - 1;
            if ($lastIndex >= 0 && $coalesced[$lastIndex]['offset'] + $coalesced[$lastIndex]['size'] === $block['offset']) {
                $coalesced[$lastIndex]['size'] += $block['size'];
                continue;
            }
            if ($lastIndex >= 0 && $coalesced[$lastIndex]['offset'] + $coalesced[$lastIndex]['size'] > $block['offset']) {
                throw new \InvalidArgumentException('SQLite index leaf deletion produced overlapping freeblocks');
            }
            $coalesced[] = $block;
        }

        $page = substr_replace(
            $page,
            pack('n', $coalesced[0]['offset']),
            $header->headerOffset + 1,
            2,
        );
        foreach ($coalesced as $index => $block) {
            $nextOffset = $coalesced[$index + 1]['offset'] ?? 0;
            if ($secureDelete) {
                $page = substr_replace($page, str_repeat("\0", $block['size']), $block['offset'], $block['size']);
            }
            $page = substr_replace($page, pack('n', $nextOffset) . pack('n', $block['size']), $block['offset'], 4);
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
