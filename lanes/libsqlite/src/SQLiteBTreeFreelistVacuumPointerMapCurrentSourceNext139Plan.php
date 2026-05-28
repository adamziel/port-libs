<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNext139Plan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $transitionRows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        int $maxTruncatedPages,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum pointer-map next139 requires an auto-vacuum database');
        }
        if ($replacementPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum pointer-map next139 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum pointer-map next139 parent b-tree page must be at page 2 or later');
        }

        $vacuumPlan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults($database, $deleteResults, $maxTruncatedPages, $secureDelete);
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

        return new self(
            $vacuumPlan,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildTransitionRows($database, $vacuumPlan, $allocationPlan, $databaseAfterAllocation),
        );
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
    public function truncatedPageNumbers(): array
    {
        return $this->vacuumPlan->truncatedPageNumbers();
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
    public function reusedSurvivingFreelistPages(): array
    {
        return array_values(array_intersect(
            $this->allocatedOverflowPages(),
            $this->vacuumPlan->survivingFreedPointerMapPages(),
        ));
    }

    /**
     * @return list<int>
     */
    public function attemptedTruncatedReuses(): array
    {
        return array_values(array_intersect(
            $this->allocatedOverflowPages(),
            $this->vacuumPlan->truncatedFreedPointerMapPages(),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transitionRows(): array
    {
        return $this->transitionRows;
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
        $images = $this->vacuumPlan->pageImages;
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
            'action' => 'btree-freelist-vacuum-pointermap-current-source-next139',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'vacuum_surviving_freed_pages' => $this->vacuumPlan->survivingFreedPointerMapPages(),
            'vacuum_truncated_freed_pages' => $this->vacuumPlan->truncatedFreedPointerMapPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_surviving_freelist_pages' => $this->reusedSurvivingFreelistPages(),
            'attempted_truncated_reuses' => $this->attemptedTruncatedReuses(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'vacuum' => $this->vacuumPlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'btree_freelist_vacuum_pointermap_current_source_next139' => $this->transitionRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(
        SQLiteDatabase $sourceDatabase,
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterAllocation,
    ): array {
        $allocationSteps = $allocationPlan->allocationSteps();
        $allocationPositionByPage = [];
        $allocationStepByPage = [];
        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $allocationPositionByPage[$pageNumber] = $position;
            $allocationStepByPage[$pageNumber] = $allocationSteps[$position] ?? [];
        }

        $sourceByPage = [];
        foreach ($vacuumPlan->releasePlan->sources as $source) {
            foreach ($source['pages'] as $pageNumber) {
                $sourceByPage[(int) $pageNumber] = $source['source'];
            }
        }

        $rows = [];
        foreach ($vacuumPlan->pointerMapVacuumTransitions() as $transition) {
            $pageNumber = (int) $transition['page_number'];
            $allocated = array_key_exists($pageNumber, $allocationPositionByPage);
            $nextEntry = null;
            if ($allocated && $pageNumber <= $databaseAfterAllocation->pageCount()) {
                $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            }
            $page = $allocated ? $databaseAfterAllocation->page($pageNumber) : null;
            $step = $allocationStepByPage[$pageNumber] ?? [];

            $rows[] = [
                'source' => $sourceByPage[$pageNumber] ?? null,
                'page_number' => $pageNumber,
                'vacuum_status' => $transition['status'],
                'vacuum_current_type' => $transition['current_type_name'],
                'vacuum_next_type' => $transition['next_type_name'],
                'truncated_type' => $transition['truncated_type_name'],
                'allocated_after_vacuum' => $allocated,
                'allocation_position' => $allocationPositionByPage[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'allocation_trunk_page' => $step['trunk_page'] ?? null,
                'final_pointer_map_type' => $nextEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $nextEntry['parent_page_number'] ?? null,
                'final_overflow_next_page' => $page === null ? null : self::readUInt32($page, 0),
                'payload_prefix' => $page === null ? null : substr($page, 4, 16),
                'source_pointer_map_type' => $sourceDatabase->pointerMapEntryForPage($pageNumber)->typeName(),
                'source_pointer_map_parent' => $sourceDatabase->pointerMapEntryForPage($pageNumber)->parentPageNumber,
            ];
        }

        return $rows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum pointer-map next139 could not read uint32');
        }

        return $value[1];
    }
}
