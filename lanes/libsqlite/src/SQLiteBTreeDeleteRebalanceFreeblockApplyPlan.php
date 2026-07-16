<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeDeleteRebalanceFreeblockApplyPlan
{
    /**
     * @param list<int> $deletedRowIds
     * @param list<list<mixed>> $deletedRecordValues
     * @param list<array{offset:int,size:int,end_offset:int,next_offset:?int}> $freeblocksBefore
     * @param list<array{offset:int,size:int,end_offset:int,next_offset:?int}> $freeblocksAfter
     * @param list<int> $cellPointersBefore
     * @param list<int> $cellPointersAfter
     * @param list<int> $obsoleteOverflowPageNumbers
     * @param list<int> $writeOrderPageNumbers
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $leafPageNumber,
        public readonly string $leafPageType,
        public readonly string $currentSourcePageHash,
        public readonly string $deletedLeafPageHash,
        public readonly string $nextLeafPageHash,
        public readonly array $deletedRowIds,
        public readonly array $deletedRecordValues,
        public readonly int $cellCountBefore,
        public readonly int $cellCountAfter,
        public readonly int $cellCountDelta,
        public readonly int $freeblockBytesBefore,
        public readonly int $freeblockBytesAfter,
        public readonly int $freeblockBytesDelta,
        public readonly int $fragmentedBytesBefore,
        public readonly int $fragmentedBytesAfter,
        public readonly int $fragmentedBytesDelta,
        public readonly int $cellContentStartBefore,
        public readonly int $cellContentStartAfter,
        public readonly int $cellContentStartDelta,
        public readonly array $freeblocksBefore,
        public readonly array $freeblocksAfter,
        public readonly array $cellPointersBefore,
        public readonly array $cellPointersAfter,
        public readonly array $obsoleteOverflowPageNumbers,
        public readonly array $writeOrderPageNumbers,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly string $leafPageImage,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        bool $secureDelete = false,
    ): self {
        $rowIds = $deleteResult['rowids'] ?? null;
        if (!is_array($rowIds)) {
            $rowId = $deleteResult['rowid'] ?? null;
            $rowIds = is_int($rowId) ? [$rowId] : null;
        }
        if (!is_array($rowIds)) {
            throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply requires deleted table rowids');
        }

        $deletedRowIds = [];
        foreach (array_values($rowIds) as $rowId) {
            if (!is_int($rowId)) {
                throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply table rowids must be integers');
            }
            $deletedRowIds[] = $rowId;
        }

        return self::fromDeleteResult($database, $leafPageNumber, 'table-leaf', $deleteResult, $deletedRowIds, [], $secureDelete);
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function indexLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): self {
        $recordValues = $deleteResult['record_values'] ?? null;
        if (!is_array($recordValues)) {
            throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply requires deleted index record values');
        }
        if ($recordValues !== [] && !is_array($recordValues[0] ?? null)) {
            $recordValues = [$recordValues];
        }

        $deletedRecordValues = [];
        foreach (array_values($recordValues) as $recordValue) {
            if (!is_array($recordValue)) {
                throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply index record values must be arrays');
            }
            $deletedRecordValues[] = array_values($recordValue);
        }

        return self::fromDeleteResult($database, $leafPageNumber, 'index-leaf', $deleteResult, [], $deletedRecordValues, $secureDelete, $overflowReader);
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-delete-rebalance-freeblock-apply-current-next',
            'leaf_page' => $this->leafPageNumber,
            'leaf_page_type' => $this->leafPageType,
            'current_source_page_hash' => $this->currentSourcePageHash,
            'deleted_leaf_page_hash' => $this->deletedLeafPageHash,
            'next_leaf_page_hash' => $this->nextLeafPageHash,
            'deleted_rowids' => $this->deletedRowIds,
            'deleted_record_values' => $this->deletedRecordValues,
            'cell_count_before' => $this->cellCountBefore,
            'cell_count_after' => $this->cellCountAfter,
            'cell_count_delta' => $this->cellCountDelta,
            'freeblock_bytes_before' => $this->freeblockBytesBefore,
            'freeblock_bytes_after' => $this->freeblockBytesAfter,
            'freeblock_bytes_delta' => $this->freeblockBytesDelta,
            'fragmented_bytes_before' => $this->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->fragmentedBytesAfter,
            'fragmented_bytes_delta' => $this->fragmentedBytesDelta,
            'cell_content_start_before' => $this->cellContentStartBefore,
            'cell_content_start_after' => $this->cellContentStartAfter,
            'cell_content_start_delta' => $this->cellContentStartDelta,
            'freeblocks_before' => $this->freeblocksBefore,
            'freeblocks_after' => $this->freeblocksAfter,
            'cell_pointers_before' => $this->cellPointersBefore,
            'cell_pointers_after' => $this->cellPointersAfter,
            'obsolete_overflow_pages' => $this->obsoleteOverflowPageNumbers,
            'freed_pages' => $this->freePlan->freedPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'write_order_page_numbers' => $this->writeOrderPageNumbers,
            'updated_freelist_page_numbers' => array_keys($this->freePlan->updatedFreelistPages),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
            'freed_pointer_map_entries' => $this->freePlan->freedPointerMapEntries,
        ];
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @param list<int> $deletedRowIds
     * @param list<list<mixed>> $deletedRecordValues
     */
    private static function fromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        string $leafPageType,
        array $deleteResult,
        array $deletedRowIds,
        array $deletedRecordValues,
        bool $secureDelete,
        ?callable $overflowReader = null,
    ): self {
        if ($leafPageNumber < 2 || $leafPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply leaf page is outside the database image');
        }

        $page = $deleteResult['page'] ?? null;
        if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
            throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply requires a deleted leaf page image');
        }

        $before = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize);
        if ($before->pageType !== $leafPageType) {
            throw new \InvalidArgumentException("SQLite delete rebalance freeblock apply expected {$leafPageType} page image");
        }
        if ($before->cellCount === 0) {
            throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply keeps non-empty leaves; empty leaves should use the empty-leaf free plan');
        }

        $currentPage = $database->page($leafPageNumber);
        $freeblocksBefore = self::freeblockArrays($before, $page, $database->usablePageSize());
        if ($freeblocksBefore === []) {
            throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply requires at least one current leaf freeblock');
        }
        self::assertDeleteResultMatchesCurrentLeaf(
            $database,
            $leafPageNumber,
            $leafPageType,
            $page,
            $deletedRowIds,
            $deletedRecordValues,
            $overflowReader,
        );

        $obsoleteOverflowPages = self::obsoleteOverflowPages($deleteResult);
        $freePlan = $database->planPageFreeList($obsoleteOverflowPages, $secureDelete);
        $leafPageImage = $leafPageType === 'table-leaf'
            ? SQLiteTableLeafPage::defragment($page, $database->header->pageSize, 0, $database->usablePageSize(), $secureDelete)
            : SQLiteIndexLeafPage::defragment($page, $database->header->pageSize, 0, $database->usablePageSize(), $secureDelete);

        $after = SQLiteBTreePageHeader::parsePage($leafPageImage, $database->header->pageSize);
        $pageImages = $freePlan->pageImages();
        $pageImages[$leafPageNumber] = $leafPageImage;
        ksort($pageImages);
        $writeOrderPageNumbers = self::writeOrderPageNumbers($leafPageNumber, $freePlan, $pageImages);

        return new self(
            $leafPageNumber,
            $leafPageType,
            hash('sha256', $currentPage),
            hash('sha256', $page),
            hash('sha256', $leafPageImage),
            $deletedRowIds,
            $deletedRecordValues,
            $before->cellCount,
            $after->cellCount,
            $after->cellCount - $before->cellCount,
            array_sum(array_column($freeblocksBefore, 'size')),
            0,
            -array_sum(array_column($freeblocksBefore, 'size')),
            $before->fragmentedFreeBytes,
            $after->fragmentedFreeBytes,
            $after->fragmentedFreeBytes - $before->fragmentedFreeBytes,
            $before->cellContentAreaStart,
            $after->cellContentAreaStart,
            $after->cellContentAreaStart - $before->cellContentAreaStart,
            $freeblocksBefore,
            self::freeblockArrays($after, $leafPageImage, $database->usablePageSize()),
            $before->cellPointers($page),
            $after->cellPointers($leafPageImage),
            $obsoleteOverflowPages,
            $writeOrderPageNumbers,
            $freePlan,
            $leafPageImage,
            $pageImages,
        );
    }

    /**
     * @param list<int> $deletedRowIds
     * @param list<list<mixed>> $deletedRecordValues
     */
    private static function assertDeleteResultMatchesCurrentLeaf(
        SQLiteDatabase $database,
        int $leafPageNumber,
        string $leafPageType,
        string $deletedPage,
        array $deletedRowIds,
        array $deletedRecordValues,
        ?callable $overflowReader,
    ): void {
        $currentPage = $database->page($leafPageNumber);
        $currentHeader = SQLiteBTreePageHeader::parsePage($currentPage, $database->header->pageSize);
        $deletedHeader = SQLiteBTreePageHeader::parsePage($deletedPage, $database->header->pageSize);
        if ($currentHeader->pageType !== $leafPageType || $deletedHeader->pageType !== $leafPageType) {
            throw new \InvalidArgumentException("SQLite delete rebalance freeblock apply expected {$leafPageType} current-source page image");
        }

        if ($leafPageType === 'table-leaf') {
            $expected = self::remainingTableEntries($currentPage, $currentHeader, $deletedRowIds, $database->usablePageSize());
            $actual = self::tableEntries($deletedPage, $deletedHeader, $database->usablePageSize());
            if ($expected !== $actual) {
                throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply rejected stale table leaf delete result');
            }

            return;
        }

        $expected = self::remainingIndexEntries($currentPage, $currentHeader, $deletedRecordValues, $database->usablePageSize(), $overflowReader);
        $actual = self::indexEntries($deletedPage, $deletedHeader, $database->usablePageSize(), $overflowReader);
        if ($expected !== $actual) {
            throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply rejected stale index leaf delete result');
        }
    }

    /**
     * @param list<int> $deletedRowIds
     * @return list<array{rowid:int,payload_hash:string}>
     */
    private static function remainingTableEntries(string $page, SQLiteBTreePageHeader $header, array $deletedRowIds, int $usableSize): array
    {
        $deleteCounts = array_count_values($deletedRowIds);
        $remaining = [];
        foreach (self::tableEntries($page, $header, $usableSize) as $entry) {
            $rowId = $entry['rowid'];
            if (($deleteCounts[$rowId] ?? 0) > 0) {
                --$deleteCounts[$rowId];
                continue;
            }
            $remaining[] = $entry;
        }

        foreach ($deleteCounts as $count) {
            if ($count !== 0) {
                throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply table rowid is not present in the current source leaf');
            }
        }

        return $remaining;
    }

    /**
     * @return list<array{rowid:int,payload_hash:string}>
     */
    private static function tableEntries(string $page, SQLiteBTreePageHeader $header, int $usableSize): array
    {
        return array_map(
            static fn (SQLiteTableLeafCell $cell): array => [
                'rowid' => $cell->rowId,
                'payload_hash' => hash('sha256', $cell->payload),
            ],
            SQLiteTableLeafCell::parsePageCells($page, $header, $usableSize, static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount)),
        );
    }

    /**
     * @param list<list<mixed>> $deletedRecordValues
     * @return list<array{values:list<mixed>,payload_hash:string}>
     */
    private static function remainingIndexEntries(string $page, SQLiteBTreePageHeader $header, array $deletedRecordValues, int $usableSize, ?callable $overflowReader): array
    {
        $deleteKeys = array_map(static fn (array $values): string => serialize($values), $deletedRecordValues);
        $deleteCounts = array_count_values($deleteKeys);
        $remaining = [];
        foreach (self::indexEntries($page, $header, $usableSize, $overflowReader) as $entry) {
            $key = serialize($entry['values']);
            if (($deleteCounts[$key] ?? 0) > 0) {
                --$deleteCounts[$key];
                continue;
            }
            $remaining[] = $entry;
        }

        foreach ($deleteCounts as $count) {
            if ($count !== 0) {
                throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply index record is not present in the current source leaf');
            }
        }

        return $remaining;
    }

    /**
     * @return list<array{values:list<mixed>,payload_hash:string}>
     */
    private static function indexEntries(string $page, SQLiteBTreePageHeader $header, int $usableSize, ?callable $overflowReader): array
    {
        return array_map(
            static fn (SQLiteIndexCell $cell): array => [
                'values' => array_values($cell->record()->values),
                'payload_hash' => hash('sha256', $cell->payload),
            ],
            SQLiteIndexCell::parsePageCells(
                $page,
                $header,
                $usableSize,
                $overflowReader ?? static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount),
            ),
        );
    }

    /**
     * @return list<array{offset:int,size:int,end_offset:int,next_offset:?int}>
     */
    private static function freeblockArrays(SQLiteBTreePageHeader $header, string $page, int $usableSize): array
    {
        return array_map(
            static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(),
            $header->freeblocks($page, $usableSize),
        );
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @return list<int>
     */
    private static function obsoleteOverflowPages(array $deleteResult): array
    {
        $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
        if (!is_array($pages)) {
            throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply requires obsolete overflow page numbers');
        }

        $normalized = [];
        $seen = [];
        foreach (array_values($pages) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite delete rebalance freeblock apply overflow page numbers must be integers');
            }
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite delete rebalance freeblock apply overflow page {$pageNumber} appears more than once");
            }
            $seen[$pageNumber] = true;
            $normalized[] = $pageNumber;
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $pageImages
     * @return list<int>
     */
    private static function writeOrderPageNumbers(int $leafPageNumber, SQLiteFreelistFreePlan $freePlan, array $pageImages): array
    {
        $ordered = [$leafPageNumber];
        foreach ($freePlan->freedPageNumbers as $pageNumber) {
            $ordered[] = $pageNumber;
        }
        foreach (array_keys($freePlan->updatedFreelistPages) as $pageNumber) {
            $ordered[] = $pageNumber;
        }
        foreach (array_keys($freePlan->updatedPointerMapPages) as $pageNumber) {
            $ordered[] = $pageNumber;
        }
        foreach (array_keys($pageImages) as $pageNumber) {
            if ($pageNumber !== 1) {
                $ordered[] = $pageNumber;
            }
        }
        if (isset($pageImages[1])) {
            $ordered[] = 1;
        }

        return array_values(array_unique($ordered));
    }
}
