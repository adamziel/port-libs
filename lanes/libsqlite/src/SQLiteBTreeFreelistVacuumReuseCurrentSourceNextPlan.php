<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $reuseRows
     */
    private function __construct(
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterReuse,
        public readonly array $reuseRows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     * @param array<int, string> $allocatedPageImages
     */
    public static function fromOverflowDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        int $maxTruncatedPages,
        int $allocationCount,
        ?int $parentPageNumber,
        array $allocatedPageImages = [],
        bool $secureDelete = false,
    ): self {
        if ($allocationCount < 1) {
            throw new \InvalidArgumentException('SQLite vacuum reuse allocation count must be positive');
        }

        $vacuumPlan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
            $database,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
        );
        $allocationPlan = $vacuumPlan->nextDatabase->planBtreePageAllocation(
            $allocationCount,
            $parentPageNumber,
            allowAppend: false,
        );
        $databaseAfterReuse = $vacuumPlan->nextDatabase->applyPageAllocationPlan($allocationPlan, $allocatedPageImages);

        return new self(
            $vacuumPlan,
            $allocationPlan,
            $databaseAfterReuse,
            self::reuseRows($vacuumPlan, $allocationPlan, $databaseAfterReuse),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function btreeFreelistVacuumReuseRows(): array
    {
        return $this->reuseRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incrementalVacuumReuseRows(): array
    {
        $allocated = array_fill_keys($this->allocationPlan->allocatedPageNumbers, true);
        $survivors = array_fill_keys($this->vacuumPlan->survivingFreedPointerMapPages(), true);
        $truncated = array_fill_keys($this->vacuumPlan->truncatedFreedPointerMapPages(), true);

        $sourceByPage = [];
        foreach ($this->vacuumPlan->releasePlan->sources as $source) {
            foreach ($source['pages'] as $position => $pageNumber) {
                $sourceByPage[$pageNumber] = [
                    'source' => $source['source'],
                    'source_position' => $position,
                ];
            }
        }

        $rows = [];
        foreach ($this->vacuumPlan->releasedOverflowPages() as $pageNumber) {
            $currentEntry = null;
            if ($this->vacuumPlan->sourceDatabase->isAutoVacuum() && !$this->vacuumPlan->sourceDatabase->isPointerMapPage($pageNumber)) {
                $currentEntry = $this->vacuumPlan->sourceDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }

            $nextEntry = null;
            if (!isset($truncated[$pageNumber]) && $this->databaseAfterReuse->isAutoVacuum() && !$this->databaseAfterReuse->isPointerMapPage($pageNumber)) {
                $nextEntry = $this->databaseAfterReuse->pointerMapEntryForPage($pageNumber)->toArray();
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'source' => $sourceByPage[$pageNumber]['source'] ?? null,
                'source_position' => $sourceByPage[$pageNumber]['source_position'] ?? null,
                'current_source_state' => 'obsolete-overflow-page',
                'current_pointer_map_type' => $currentEntry['type_name'] ?? null,
                'current_pointer_map_parent' => $currentEntry['parent_page_number'] ?? null,
                'survived_incremental_vacuum' => isset($survivors[$pageNumber]),
                'reused_by_next_btree' => isset($allocated[$pageNumber]),
                'tail_truncated_by_incremental_vacuum' => isset($truncated[$pageNumber]),
                'next_source_state' => isset($truncated[$pageNumber])
                    ? 'truncated-from-database'
                    : (isset($allocated[$pageNumber]) ? 'reused-as-btree-page' : 'survives-as-free-page'),
                'next_pointer_map_type' => $nextEntry['type_name'] ?? null,
                'next_pointer_map_parent' => $nextEntry['parent_page_number'] ?? null,
                'next_page_materialized' => !isset($truncated[$pageNumber]) && $this->databaseAfterReuse->page($pageNumber) !== str_repeat("\0", $this->databaseAfterReuse->header->pageSize),
            ];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    public function allocatedPageNumbers(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function survivorPagesNotReused(): array
    {
        $allocated = array_fill_keys($this->allocationPlan->allocatedPageNumbers, true);

        return array_values(array_filter(
            $this->vacuumPlan->survivingFreedPointerMapPages(),
            static fn (int $pageNumber): bool => !isset($allocated[$pageNumber]),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedPagesNotReused(): array
    {
        $allocated = array_fill_keys($this->allocationPlan->allocatedPageNumbers, true);

        return array_values(array_filter(
            $this->vacuumPlan->truncatedFreedPointerMapPages(),
            static fn (int $pageNumber): bool => !isset($allocated[$pageNumber]),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->allocationPlan->pageImages();
        foreach ($this->allocationPlan->allocatedPageNumbers as $pageNumber) {
            $images[$pageNumber] = $this->databaseAfterReuse->page($pageNumber);
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
            'action' => 'btree-freelist-pointermap-vacuum-reuse',
            'vacuum_final_database_page_count' => $this->vacuumPlan->finalDatabasePageCount(),
            'vacuum_surviving_freed_pages' => $this->vacuumPlan->survivingFreedPointerMapPages(),
            'vacuum_truncated_freed_pages' => $this->vacuumPlan->truncatedFreedPointerMapPages(),
            'allocated_page_numbers' => $this->allocationPlan->allocatedPageNumbers,
            'appended_page_numbers' => $this->allocationPlan->appendedPageNumbers,
            'survivor_pages_not_reused' => $this->survivorPagesNotReused(),
            'truncated_pages_not_reused' => $this->truncatedPagesNotReused(),
            'final_database_page_count' => $this->databaseAfterReuse->pageCount(),
            'final_first_freelist_trunk_page' => $this->databaseAfterReuse->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterReuse->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterReuse->freelistPageNumbers(),
            'allocation_steps' => $this->allocationPlan->allocationSteps(),
            'allocated_pointer_map_entries' => $this->allocationPlan->allocatedPointerMapEntries(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'btree_freelist_pointermap_vacuum_reuse_rows' => $this->reuseRows,
            'incremental_vacuum_reuse_rows' => $this->incrementalVacuumReuseRows(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function reuseRows(
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterReuse,
    ): array {
        $survivors = array_fill_keys($vacuumPlan->survivingFreedPointerMapPages(), true);
        $truncated = array_fill_keys($vacuumPlan->truncatedFreedPointerMapPages(), true);
        $allocationSteps = [];
        foreach ($allocationPlan->allocationSteps() as $step) {
            $allocationSteps[(int) $step['allocated_page']] = $step;
        }

        $rows = [];
        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $beforeEntry = $vacuumPlan->nextDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            $afterEntry = $databaseAfterReuse->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationSteps[$pageNumber] ?? [];

            $rows[] = [
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'source' => $step['source'] ?? null,
                'reused_vacuum_survivor' => isset($survivors[$pageNumber]),
                'was_truncated_tail_page' => isset($truncated[$pageNumber]),
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_parent_page_number' => $beforeEntry['parent_page_number'],
                'after_pointer_map_type' => $afterEntry['type_name'],
                'after_parent_page_number' => $afterEntry['parent_page_number'],
                'freelist_page_count_after_step' => $step['freelist_page_count_after'] ?? null,
                'trunk_page' => $step['trunk_page'] ?? null,
                'materialized_as_btree_page' => $databaseAfterReuse->page($pageNumber) !== str_repeat("\0", $databaseAfterReuse->header->pageSize),
            ];
        }

        return $rows;
    }
}
