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
            if (self::recordValuesEqual($cell->record($textEncoding)->values, $recordValues)) {
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
            if (self::recordValuesEqual($cell->record($textEncoding)->values, $recordValues)) {
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

    /**
     * @param list<list<mixed>> $recordValuesList
     * @param callable(int, int): list<int> $overflowPageNumbers
     * @return array{page:string,record_values:list<list<mixed>>,obsolete_overflow_page_numbers:list<int>,deleted:list<array{record_values:list<mixed>,obsolete_overflow_page_numbers:list<int>,deleted_local_payload_length:int,deleted_cell_bytes:int}>}
     */
    public static function deleteCellsByRecordValuesWithOverflowRelease(
        string $page,
        array $recordValuesList,
        callable $overflowPageNumbers,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        int $textEncoding = 1,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): array {
        if ($recordValuesList === []) {
            throw new \InvalidArgumentException('SQLite index leaf bulk overflow deletion requires at least one record value list');
        }

        $deletedPage = $page;
        $deleted = [];
        $obsoleteOverflowPageNumbers = [];
        foreach ($recordValuesList as $recordValues) {
            if (!is_array($recordValues)) {
                throw new \InvalidArgumentException('SQLite index leaf bulk overflow deletion records must be value lists');
            }

            $delete = self::deleteCellByRecordValuesWithOverflowRelease(
                $deletedPage,
                $recordValues,
                $overflowPageNumbers,
                $pageSize,
                $headerOffset,
                $usableSize,
                $textEncoding,
                $secureDelete,
                $overflowReader,
            );
            $deletedPage = $delete['page'];
            $obsoleteOverflowPageNumbers = array_values(array_unique(array_merge(
                $obsoleteOverflowPageNumbers,
                $delete['obsolete_overflow_page_numbers'],
            )));
            $deleted[] = [
                'record_values' => $delete['record_values'],
                'obsolete_overflow_page_numbers' => $delete['obsolete_overflow_page_numbers'],
                'deleted_local_payload_length' => $delete['deleted_local_payload_length'],
                'deleted_cell_bytes' => $delete['deleted_cell_bytes'],
            ];
        }

        sort($obsoleteOverflowPageNumbers);

        return [
            'page' => $deletedPage,
            'record_values' => array_values($recordValuesList),
            'obsolete_overflow_page_numbers' => $obsoleteOverflowPageNumbers,
            'deleted' => $deleted,
        ];
    }

    /**
     * @param list<mixed> $recordValues
     */
    public static function insertCellByRecordValuesReusingFreeblock(
        string $page,
        array $recordValues,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        int $textEncoding = 1,
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
            throw new \InvalidArgumentException('SQLite index leaf insertion requires an index leaf page');
        }

        $cells = SQLiteIndexCell::parsePageCells($page, $header, $usableSize);
        $insertIndex = 0;
        foreach ($cells as $cell) {
            $existingValues = $cell->record($textEncoding)->values;
            if ($existingValues === $recordValues) {
                throw new \InvalidArgumentException('SQLite index leaf insertion record already exists');
            }
            if (self::compareRecordValues($existingValues, $recordValues) < 0) {
                $insertIndex++;
            }
        }

        return self::insertCellBytesReusingFreeblock(
            $page,
            $header,
            SQLiteIndexCell::encode(SQLiteRecord::encode($recordValues)),
            $insertIndex,
            $usableSize,
        );
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
            SQLiteIndexCell::parsePageCells(
                $page,
                $header,
                $usableSize,
                static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount),
            ),
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
        $coalescedFragmentBytes = 0;
        foreach ($blocks as $block) {
            $lastIndex = count($coalesced) - 1;
            $lastEnd = $lastIndex >= 0 ? $coalesced[$lastIndex]['offset'] + $coalesced[$lastIndex]['size'] : null;
            if ($lastEnd !== null && $lastEnd === $block['offset']) {
                $coalesced[$lastIndex]['size'] += $block['size'];
                continue;
            }
            if ($lastEnd !== null && $lastEnd < $block['offset'] && $block['offset'] - $lastEnd < 4) {
                $coalescedFragmentBytes += $block['offset'] - $lastEnd;
                $coalesced[$lastIndex]['size'] = ($block['offset'] + $block['size']) - $coalesced[$lastIndex]['offset'];
                continue;
            }
            if ($lastEnd !== null && $lastEnd > $block['offset']) {
                throw new \InvalidArgumentException('SQLite index leaf deletion produced overlapping freeblocks');
            }
            $coalesced[] = $block;
        }
        if ($coalescedFragmentBytes > 0) {
            $fragmented = ord($page[$header->headerOffset + 7]);
            if ($coalescedFragmentBytes > $fragmented) {
                throw new \InvalidArgumentException('SQLite index leaf deletion current/next fragments exceed fragmented free byte count');
            }
            $page[$header->headerOffset + 7] = chr($fragmented - $coalescedFragmentBytes);
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

    private static function insertCellBytesReusingFreeblock(
        string $page,
        SQLiteBTreePageHeader $header,
        string $cell,
        int $insertIndex,
        int $usableSize,
    ): string {
        if ($cell === '') {
            throw new \InvalidArgumentException('SQLite index leaf insertion requires a non-empty cell');
        }
        if ($header->cellPointerArrayEnd() + 2 > $header->cellContentAreaStart) {
            throw new \InvalidArgumentException('SQLite index leaf insertion has no room for another cell pointer');
        }

        $cellLength = strlen($cell);
        $freeblocks = $header->freeblocks($page, $usableSize);
        $selected = null;
        foreach ($freeblocks as $freeblock) {
            if ($freeblock->size >= $cellLength) {
                $selected = $freeblock;
                break;
            }
        }
        if ($selected === null) {
            throw new \InvalidArgumentException('SQLite index leaf insertion found no reusable freeblock large enough for the cell');
        }

        $remainderSize = $selected->size - $cellLength;
        $replacementBlocks = [];
        foreach ($freeblocks as $freeblock) {
            if ($freeblock->offset === $selected->offset) {
                if ($remainderSize >= 4) {
                    $replacementBlocks[] = [
                        'offset' => $selected->offset + $cellLength,
                        'size' => $remainderSize,
                    ];
                } elseif ($remainderSize > 0) {
                    $fragmented = $header->fragmentedFreeBytes + $remainderSize;
                    if ($fragmented > 60) {
                        throw new \InvalidArgumentException('SQLite index leaf insertion would exceed fragmented free byte limit');
                    }
                    $page[$header->headerOffset + 7] = chr($fragmented);
                }
                continue;
            }
            $replacementBlocks[] = ['offset' => $freeblock->offset, 'size' => $freeblock->size];
        }

        usort($replacementBlocks, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);
        $page = substr_replace($page, $cell, $selected->offset, $cellLength);
        $page = self::writeFreeblocks($page, $header, $replacementBlocks);

        $pointers = $header->cellPointers($page);
        array_splice($pointers, $insertIndex, 0, [$selected->offset]);
        $page = substr_replace($page, pack('n', count($pointers)), $header->headerOffset + 3, 2);
        foreach ($pointers as $index => $pointer) {
            $page = substr_replace($page, pack('n', $pointer), $header->cellPointerArrayOffset() + ($index * 2), 2);
        }

        return $page;
    }

    /**
     * @param list<array{offset:int,size:int}> $blocks
     */
    private static function writeFreeblocks(string $page, SQLiteBTreePageHeader $header, array $blocks): string
    {
        $page = substr_replace($page, pack('n', $blocks[0]['offset'] ?? 0), $header->headerOffset + 1, 2);
        foreach ($blocks as $index => $block) {
            $nextOffset = $blocks[$index + 1]['offset'] ?? 0;
            $page = substr_replace($page, pack('n', $nextOffset) . pack('n', $block['size']), $block['offset'], 4);
        }

        return $page;
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function compareRecordValues(array $left, array $right): int
    {
        $count = min(count($left), count($right));
        for ($index = 0; $index < $count; $index++) {
            $comparison = SQLiteAffinityComparison::compare($left[$index], $right[$index], 'NONE', 'NONE', 'BINARY') ?? 0;
            if ($comparison !== 0) {
                return $comparison < 0 ? -1 : 1;
            }
        }

        return count($left) <=> count($right);
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function recordValuesEqual(array $left, array $right): bool
    {
        return count($left) === count($right) && self::compareRecordValues($left, $right) === 0;
    }

    private static function validatePageSize(int $pageSize): void
    {
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite page size must be a power of two between 512 and 65536 bytes');
        }
    }
}
