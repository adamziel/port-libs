<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $trunkReuseRows
     * @param array<int, string> $allocatedPageImages
     */
    private function __construct(
        public readonly SQLiteDatabase $currentDatabase,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterReuse,
        public readonly array $trunkReuseRows,
        private readonly array $allocatedPageImages,
    ) {
    }

    /**
     * @param array<int, string> $allocatedPageImages
     */
    public static function fromDatabase(
        SQLiteDatabase $database,
        int $allocationCount,
        ?int $parentPageNumber,
        array $allocatedPageImages = [],
        bool $allowAppend = false,
    ): self {
        if ($allocationCount < 1) {
            throw new \InvalidArgumentException('SQLite freelist trunk pointer-map reuse allocation count must be positive');
        }

        $allocationPlan = $database->planBtreePageAllocation($allocationCount, $parentPageNumber, $allowAppend);
        $databaseAfterReuse = $database->applyPageAllocationPlan($allocationPlan, $allocatedPageImages);

        return new self(
            $database,
            $allocationPlan,
            $databaseAfterReuse,
            self::trunkReuseRows($database, $allocationPlan, $databaseAfterReuse, $allocatedPageImages),
            $allocatedPageImages,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trunkPointerMapReuseRows(): array
    {
        return $this->trunkReuseRows;
    }

    /**
     * @return list<int>
     */
    public function allocatedPageNumbers(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
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
            'action' => 'btree-freelist-trunk-pointermap-reuse-current-source-next113',
            'allocated_page_numbers' => $this->allocationPlan->allocatedPageNumbers,
            'appended_page_numbers' => $this->allocationPlan->appendedPageNumbers,
            'final_first_freelist_trunk_page' => $this->databaseAfterReuse->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterReuse->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterReuse->freelistPageNumbers(),
            'allocation_steps' => $this->allocationPlan->allocationSteps(),
            'allocated_pointer_map_entries' => $this->allocationPlan->allocatedPointerMapEntries(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'btree_freelist_trunk_pointermap_reuse_current_source_next113' => $this->trunkReuseRows,
        ];
    }

    /**
     * @param array<int, string> $allocatedPageImages
     * @return list<array<string, mixed>>
     */
    private static function trunkReuseRows(
        SQLiteDatabase $database,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterReuse,
        array $allocatedPageImages,
    ): array {
        $rows = [];

        foreach ($allocationPlan->allocationSteps() as $position => $step) {
            if (($step['source'] ?? null) !== 'freelist-trunk') {
                continue;
            }

            $pageNumber = (int) $step['allocated_page'];
            $trunkPage = SQLiteFreelistTrunkPage::parse(
                $pageNumber,
                $database->page($pageNumber),
                $database->usablePageSize(),
                $database->pageCount(),
            );
            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $afterEntry = $databaseAfterReuse->pointerMapEntryForPage($pageNumber)->toArray();
            $afterPage = $databaseAfterReuse->page($pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'current_source_state' => 'freelist-trunk',
                'current_next_trunk_page' => $trunkPage->nextTrunkPage,
                'current_leaf_count' => count($trunkPage->leafPageNumbers),
                'current_trunk_header_bytes' => substr($database->page($pageNumber), 0, 8),
                'next_source_state' => 'reused-as-btree-page',
                'next_first_freelist_trunk_page' => $allocationPlan->firstFreelistTrunkPage,
                'freelist_page_count_after_step' => $step['freelist_page_count_after'] ?? null,
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'after_pointer_map_type' => $afterEntry['type_name'],
                'after_pointer_map_parent' => $afterEntry['parent_page_number'],
                'materialized_with_supplied_image' => isset($allocatedPageImages[$pageNumber]),
                'stale_trunk_header_overwritten' => substr($afterPage, 0, 8) !== substr($database->page($pageNumber), 0, 8),
                'next_page_type_byte' => ord($afterPage[0]),
            ];
        }

        return $rows;
    }
}
