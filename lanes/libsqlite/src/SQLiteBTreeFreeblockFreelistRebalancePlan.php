<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreeblockFreelistRebalancePlan
{
    /**
     * @param list<int> $deletedRowIds
     * @param list<list<mixed>> $deletedRecordValues
     * @param list<array{offset:int,size:int,end_offset:int,next_offset:?int}> $leafFreeblocks
     * @param list<int> $obsoleteOverflowPageNumbers
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $leafPageNumber,
        public readonly string $leafPageType,
        public readonly array $deletedRowIds,
        public readonly array $deletedRecordValues,
        public readonly array $leafFreeblocks,
        public readonly int $leafCellCount,
        public readonly int $leafFreeblockBytes,
        public readonly int $leafFreeSpaceBytes,
        public readonly array $obsoleteOverflowPageNumbers,
        public readonly SQLiteFreelistFreePlan $freePlan,
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
            throw new \InvalidArgumentException('SQLite freeblock/freelist table rebalance requires deleted rowids');
        }

        $deletedRowIds = [];
        foreach (array_values($rowIds) as $rowId) {
            if (!is_int($rowId)) {
                throw new \InvalidArgumentException('SQLite freeblock/freelist table rowids must be integers');
            }
            $deletedRowIds[] = $rowId;
        }

        return self::fromDeleteResult(
            $database,
            $leafPageNumber,
            'table-leaf',
            $deleteResult,
            $deletedRowIds,
            [],
            $secureDelete,
        );
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function indexLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        bool $secureDelete = false,
    ): self {
        $recordValues = $deleteResult['record_values'] ?? null;
        if (!is_array($recordValues)) {
            throw new \InvalidArgumentException('SQLite freeblock/freelist index rebalance requires deleted record values');
        }
        if ($recordValues !== [] && !is_array($recordValues[0] ?? null)) {
            $recordValues = [$recordValues];
        }

        $deletedRecordValues = [];
        foreach (array_values($recordValues) as $recordValue) {
            if (!is_array($recordValue)) {
                throw new \InvalidArgumentException('SQLite freeblock/freelist index record values must be arrays');
            }
            $deletedRecordValues[] = array_values($recordValue);
        }

        return self::fromDeleteResult(
            $database,
            $leafPageNumber,
            'index-leaf',
            $deleteResult,
            [],
            $deletedRecordValues,
            $secureDelete,
        );
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
            'action' => 'btree-freeblock-freelist-rebalance',
            'leaf_page' => $this->leafPageNumber,
            'leaf_page_type' => $this->leafPageType,
            'deleted_rowids' => $this->deletedRowIds,
            'deleted_record_values' => $this->deletedRecordValues,
            'leaf_cell_count' => $this->leafCellCount,
            'leaf_freeblock_bytes' => $this->leafFreeblockBytes,
            'leaf_free_space_bytes' => $this->leafFreeSpaceBytes,
            'leaf_freeblocks' => $this->leafFreeblocks,
            'obsolete_overflow_pages' => $this->obsoleteOverflowPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'freed_pages' => $this->freePlan->freedPageNumbers,
            'new_freelist_trunk_pages' => $this->freePlan->newTrunkPageNumbers,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'freed_pointer_map_entries' => $this->freePlan->freedPointerMapEntries,
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
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
    ): self {
        if ($leafPageNumber < 2 || $leafPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite freeblock/freelist rebalance leaf page is outside the database image');
        }

        $page = $deleteResult['page'] ?? null;
        if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
            throw new \InvalidArgumentException('SQLite freeblock/freelist rebalance requires a deleted leaf page image');
        }

        $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize);
        if ($header->pageType !== $leafPageType) {
            throw new \InvalidArgumentException("SQLite freeblock/freelist rebalance expected {$leafPageType} page image");
        }
        if ($header->cellCount === 0) {
            throw new \InvalidArgumentException('SQLite freeblock/freelist rebalance keeps non-empty leaves; empty leaves should use the empty-leaf free plan');
        }

        $freeblocks = [];
        $freeblockBytes = 0;
        foreach ($header->freeblocks($page, $database->usablePageSize()) as $freeblock) {
            $freeblocks[] = $freeblock->toArray();
            $freeblockBytes += $freeblock->size;
        }
        if ($freeblocks === []) {
            throw new \InvalidArgumentException('SQLite freeblock/freelist rebalance requires at least one reusable leaf freeblock');
        }

        $obsoleteOverflowPages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
        if (!is_array($obsoleteOverflowPages)) {
            throw new \InvalidArgumentException('SQLite freeblock/freelist rebalance requires obsolete overflow page numbers');
        }

        $normalizedOverflowPages = [];
        $seenOverflowPages = [];
        foreach (array_values($obsoleteOverflowPages) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite freeblock/freelist overflow page numbers must be integers');
            }
            if (isset($seenOverflowPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite freeblock/freelist overflow page {$pageNumber} appears more than once");
            }
            $seenOverflowPages[$pageNumber] = true;
            $normalizedOverflowPages[] = $pageNumber;
        }
        if ($normalizedOverflowPages === []) {
            throw new \InvalidArgumentException('SQLite freeblock/freelist rebalance requires at least one obsolete overflow page');
        }

        $freePlan = $database->planPageFreeList($normalizedOverflowPages, $secureDelete);
        $pageImages = $freePlan->pageImages();
        $pageImages[$leafPageNumber] = $page;
        ksort($pageImages);

        return new self(
            $leafPageNumber,
            $leafPageType,
            $deletedRowIds,
            $deletedRecordValues,
            $freeblocks,
            $header->cellCount,
            $freeblockBytes,
            $header->freeSpaceBytes($page, $database->usablePageSize()),
            $normalizedOverflowPages,
            $freePlan,
            $pageImages,
        );
    }
}
