<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $vacuumPlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        public readonly array $rows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResults,
        int $maxTruncatedPages,
        int $parentBtreePageNumber,
        string $replacementPayload,
        bool $secureDelete = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite overflow vacuum freeblock next137 requires an auto-vacuum database');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow vacuum freeblock next137 parent b-tree page must be at page 2 or later');
        }
        if ($replacementPayload === '') {
            throw new \InvalidArgumentException('SQLite overflow vacuum freeblock next137 requires replacement overflow payload bytes');
        }

        $vacuumPlan = SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan::coalescedOverflowFreeblockFromDeleteResults(
            $database,
            $leafPageNumber,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
            true,
        );
        $databaseAfterVacuum = $vacuumPlan->materializedDatabase();
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementPayload),
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $allocationPlan = $databaseAfterVacuum->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementPayload,
            $allocationPlan->allocatedPageNumbers,
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterVacuum->applyPageAllocationPlan($allocationPlan, $overflowPageImages);
        $rows = self::rows($database, $vacuumPlan, $databaseAfterVacuum, $databaseAfterAllocation, $allocationPlan);

        if (self::reusedSurvivingPages($vacuumPlan, $allocationPlan) === []) {
            throw new \InvalidArgumentException('SQLite overflow vacuum freeblock next137 requires reuse of a surviving vacuum free page');
        }

        return new self($vacuumPlan, $allocationPlan, $databaseAfterAllocation, $overflowPageImages, $rows);
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPages(): array
    {
        return $this->vacuumPlan->releasedOverflowPages();
    }

    /**
     * @return list<int>
     */
    public function survivingFreedPointerMapPages(): array
    {
        return $this->vacuumPlan->survivingFreedPointerMapPages();
    }

    /**
     * @return list<int>
     */
    public function truncatedFreedPointerMapPages(): array
    {
        return $this->vacuumPlan->truncatedFreedPointerMapPages();
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
    public function reusedSurvivingFreedPages(): array
    {
        return self::reusedSurvivingPages($this->vacuumPlan, $this->allocationPlan);
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
        $images = $this->vacuumPlan->vacuumPlan->pageImages;
        foreach ($this->vacuumPlan->coalescePlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
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
            'action' => 'btree-overflow-vacuum-freeblock-current-source-next137',
            'leaf_page' => $this->vacuumPlan->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->vacuumPlan->coalescePlan->coalescedFragmentBytes,
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'surviving_freed_pointer_map_pages' => $this->survivingFreedPointerMapPages(),
            'truncated_freed_pointer_map_pages' => $this->truncatedFreedPointerMapPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_surviving_freed_pages' => $this->reusedSurvivingFreedPages(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'vacuum' => $this->vacuumPlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'btree_overflow_vacuum_freeblock_current_source_next137' => $this->rows,
        ];
    }

    /**
     * @return list<int>
     */
    private static function reusedSurvivingPages(
        SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $vacuumPlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        return array_values(array_intersect($vacuumPlan->survivingFreedPointerMapPages(), $allocationPlan->allocatedPageNumbers));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(
        SQLiteDatabase $sourceDatabase,
        SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $vacuumPlan,
        SQLiteDatabase $databaseAfterVacuum,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $vacuumRows = [];
        foreach ($vacuumPlan->overflowFreeblockVacuumRows() as $row) {
            $vacuumRows[(int) $row['page_number']] = $row;
        }

        $allocated = array_fill_keys($allocationPlan->allocatedPageNumbers, true);
        $allocationSteps = [];
        foreach ($allocationPlan->allocationSteps() as $step) {
            if (isset($step['allocated_page']) && is_int($step['allocated_page'])) {
                $allocationSteps[$step['allocated_page']] = $step;
            }
        }

        $pageNumbers = array_values(array_unique(array_merge(
            $vacuumPlan->releasedOverflowPages(),
            $allocationPlan->allocatedPageNumbers,
        )));
        sort($pageNumbers);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $vacuumRow = $vacuumRows[$pageNumber] ?? [];
            $allocatedAfterVacuum = isset($allocated[$pageNumber]);
            $vacuumSurvivor = ($vacuumRow['vacuum_status'] ?? null) === 'survives-as-free-page';
            $truncated = ($vacuumRow['vacuum_status'] ?? null) === 'truncated-from-database';
            $step = $allocationSteps[$pageNumber] ?? null;
            $sourceEntry = $sourceDatabase->isAutoVacuum() && !$sourceDatabase->isPointerMapPage($pageNumber)
                ? $sourceDatabase->pointerMapEntryForPage($pageNumber)->toArray()
                : null;
            $freeEntry = null;
            if ($vacuumSurvivor && !$databaseAfterVacuum->isPointerMapPage($pageNumber)) {
                $freeEntry = $databaseAfterVacuum->pointerMapEntryForPage($pageNumber)->toArray();
            }
            $nextEntry = null;
            if ($allocatedAfterVacuum && !$databaseAfterAllocation->isPointerMapPage($pageNumber)) {
                $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'source' => $vacuumRow['source'] ?? null,
                'chain_position' => $vacuumRow['chain_position'] ?? null,
                'current_overflow_next_page' => $vacuumRow['current_overflow_next_page'] ?? null,
                'current_pointer_map_type' => $sourceEntry['type_name'] ?? null,
                'current_pointer_map_parent' => $sourceEntry['parent_page_number'] ?? null,
                'vacuum_status' => $vacuumRow['vacuum_status'] ?? 'not-released-by-delete',
                'free_pointer_map_type' => $freeEntry['type_name'] ?? null,
                'free_pointer_map_parent' => $freeEntry['parent_page_number'] ?? null,
                'allocated_after_vacuum' => $allocatedAfterVacuum,
                'allocation_source' => $step['source'] ?? null,
                'allocation_trunk_page' => $step['trunk_page'] ?? null,
                'next_pointer_map_type' => $nextEntry['type_name'] ?? null,
                'next_pointer_map_parent' => $nextEntry['parent_page_number'] ?? null,
                'next_overflow_next_page' => $allocatedAfterVacuum ? self::readUInt32($databaseAfterAllocation->page($pageNumber), 0) : null,
                'payload_prefix' => $allocatedAfterVacuum ? substr($databaseAfterAllocation->page($pageNumber), 4, 12) : null,
                'coalesced_fragment_bytes' => $vacuumPlan->coalescePlan->coalescedFragmentBytes,
                'truncated' => $truncated,
            ];
        }

        return $rows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite overflow vacuum freeblock next137 could not read uint32');
        }

        return $value[1];
    }
}
