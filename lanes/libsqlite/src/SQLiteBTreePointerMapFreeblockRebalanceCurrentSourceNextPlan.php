<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $currentRows
     * @param list<array<string, mixed>> $nextRows
     */
    private function __construct(
        public readonly SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $rebalancePlan,
        public readonly SQLiteDatabase $databaseAfterRebalance,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $currentRows,
        private readonly array $nextRows,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromCurrentSourceDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $parentBtreePageNumber,
        string $replacementOverflowPayload,
        bool $secureDelete = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freeblock rebalance next146 requires an auto-vacuum database');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freeblock rebalance next146 parent b-tree page must be at page 2 or later');
        }
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freeblock rebalance next146 requires replacement overflow payload bytes');
        }

        $rebalancePlan = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $secureDelete,
        );
        if ($rebalancePlan->obsoleteOverflowPageNumbers === []) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freeblock rebalance next146 requires obsolete overflow pages');
        }

        $databaseAfterRebalance = self::databaseWithPageImages($database, $rebalancePlan->pageImages);
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $databaseAfterRebalance->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterRebalance->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $rebalancePlan,
            $databaseAfterRebalance,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildCurrentRows($database, $rebalancePlan),
            self::buildNextRows($database, $databaseAfterRebalance, $databaseAfterAllocation, $rebalancePlan, $allocationPlan),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currentRows(): array
    {
        return $this->currentRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nextRows(): array
    {
        return $this->nextRows;
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPages(): array
    {
        return $this->rebalancePlan->obsoleteOverflowPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function allocatedOverflowPages(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function reusedReleasedOverflowPages(): array
    {
        return array_values(array_intersect($this->releasedOverflowPages(), $this->allocatedOverflowPages()));
    }

    /**
     * @return array<int, string>
     */
    public function overflowPageImages(): array
    {
        return $this->overflowPageImages;
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->rebalancePlan->pageImages;
        foreach ($this->allocationPlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        foreach ($this->overflowPageImages as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        ksort($images);

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-pointermap-freeblock-rebalance-current-source-next146',
            'leaf_page' => $this->rebalancePlan->leafPageNumber,
            'leaf_page_type' => $this->rebalancePlan->leafPageType,
            'deleted_rowids' => $this->rebalancePlan->deletedRowIds,
            'freeblock_bytes_before' => $this->rebalancePlan->freeblockBytesBefore,
            'freeblock_bytes_after' => $this->rebalancePlan->freeblockBytesAfter,
            'fragmented_bytes_after' => $this->rebalancePlan->fragmentedBytesAfter,
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_released_overflow_pages' => $this->reusedReleasedOverflowPages(),
            'current_source_rows' => $this->currentRows,
            'btree_pointermap_freeblock_rebalance_current_source_next146' => $this->nextRows,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'updated_page_numbers' => array_keys($this->pageImages()),
            'rebalance' => $this->rebalancePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentRows(SQLiteDatabase $database, SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $rebalancePlan): array
    {
        $rows = [];
        foreach ($rebalancePlan->obsoleteOverflowPageNumbers as $position => $pageNumber) {
            $entry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $rows[] = [
                'leaf_page' => $rebalancePlan->leafPageNumber,
                'page_number' => $pageNumber,
                'obsolete_position' => $position,
                'before_pointer_map_type' => $entry['type_name'],
                'before_pointer_map_parent' => $entry['parent_page_number'],
                'freeblock_bytes_before' => $rebalancePlan->freeblockBytesBefore,
                'freeblock_bytes_after' => $rebalancePlan->freeblockBytesAfter,
                'cell_count_after_rebalance' => $rebalancePlan->cellCountAfter,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildNextRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRebalance,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $rebalancePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $released = array_fill_keys($rebalancePlan->obsoleteOverflowPageNumbers, true);
        $allocationSteps = $allocationPlan->allocationSteps();
        $rows = [];
        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRebalance->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $page = $databaseAfterAllocation->page($pageNumber);
            $rows[] = [
                'leaf_page' => $rebalancePlan->leafPageNumber,
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'page_origin' => isset($released[$pageNumber]) ? 'released-overflow-page' : 'existing-freelist-page',
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'allocation_source' => $allocationSteps[$position]['source'] ?? null,
                'allocation_trunk_page' => $allocationSteps[$position]['trunk_page'] ?? null,
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'next_overflow_is_tail' => self::readUInt32($page, 0) === 0,
                'freeblock_bytes_after_rebalance' => $rebalancePlan->freeblockBytesAfter,
                'leaf_fragmented_bytes_after_rebalance' => $rebalancePlan->fragmentedBytesAfter,
            ];
        }

        return $rows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freeblock rebalance next146 could not read uint32');
        }

        return $value[1];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
