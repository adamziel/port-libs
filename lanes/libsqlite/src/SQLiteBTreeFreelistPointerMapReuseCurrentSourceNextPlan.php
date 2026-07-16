<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $allocatedPageImages
     * @param list<array<string, mixed>> $reuseRows
     */
    private function __construct(
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly SQLiteDatabase $databaseAfterFree,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterReuse,
        private readonly array $allocatedPageImages,
        public readonly array $reuseRows,
    ) {
    }

    /**
     * @param list<int> $freedPageNumbers
     * @param array<int, string> $allocatedPageImages
     */
    public static function fromFreedPages(
        SQLiteDatabase $database,
        array $freedPageNumbers,
        int $allocationCount,
        ?int $parentPageNumber,
        array $allocatedPageImages = [],
        bool $secureDelete = false,
    ): self {
        if ($allocationCount < 1) {
            throw new \InvalidArgumentException('SQLite b-tree freelist pointer-map reuse allocation count must be positive');
        }

        $freePlan = $database->planPageFreeList($freedPageNumbers, $secureDelete);
        $databaseAfterFree = $database->applyPageFreePlan($freePlan);
        $allocationPlan = $databaseAfterFree->planBtreePageAllocation($allocationCount, $parentPageNumber, false);
        $databaseAfterReuse = $databaseAfterFree->applyPageAllocationPlan($allocationPlan, $allocatedPageImages);

        return new self(
            $freePlan,
            $databaseAfterFree,
            $allocationPlan,
            $databaseAfterReuse,
            $allocatedPageImages,
            self::reuseRows($database, $databaseAfterFree, $freePlan, $allocationPlan, $databaseAfterReuse, $allocatedPageImages),
        );
    }

    /**
     * @return list<int>
     */
    public function promotedTrunkPageNumbers(): array
    {
        return $this->freePlan->newTrunkPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function reusedPromotedTrunkPageNumbers(): array
    {
        return array_values(array_intersect(
            $this->freePlan->newTrunkPageNumbers,
            $this->allocationPlan->allocatedPageNumbers,
        ));
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->freePlan->pageImages();
        foreach ($this->allocationPlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
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
            'action' => 'btree-freelist-pointermap-reuse-current-source-next124',
            'freed_page_numbers' => $this->freePlan->freedPageNumbers,
            'promoted_trunk_page_numbers' => $this->promotedTrunkPageNumbers(),
            'allocated_page_numbers' => $this->allocationPlan->allocatedPageNumbers,
            'reused_promoted_trunk_page_numbers' => $this->reusedPromotedTrunkPageNumbers(),
            'final_first_freelist_trunk_page' => $this->databaseAfterReuse->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterReuse->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterReuse->freelistPageNumbers(),
            'free_plan' => $this->freePlan->toArray(),
            'allocation_plan' => $this->allocationPlan->toArray(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'btree_freelist_pointermap_reuse_current_source_next124' => $this->reuseRows,
        ];
    }

    /**
     * @param array<int, string> $allocatedPageImages
     * @return list<array<string, mixed>>
     */
    private static function reuseRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterFree,
        SQLiteFreelistFreePlan $freePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterReuse,
        array $allocatedPageImages,
    ): array {
        $promotedTrunks = array_fill_keys($freePlan->newTrunkPageNumbers, true);
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            if (!isset($promotedTrunks[$pageNumber])) {
                continue;
            }

            $step = $allocationPlan->allocationSteps()[$position] ?? [];
            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterFree->pointerMapEntryForPage($pageNumber)->toArray();
            $reuseEntry = $databaseAfterReuse->pointerMapEntryForPage($pageNumber)->toArray();
            $promotedTrunkPage = SQLiteFreelistTrunkPage::parse(
                $pageNumber,
                $databaseAfterFree->page($pageNumber),
                $databaseAfterFree->usablePageSize(),
                $databaseAfterFree->pageCount(),
            );

            $rows[] = [
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'free_source_state' => 'promoted-freelist-trunk',
                'allocation_source' => $step['source'] ?? null,
                'promoted_next_trunk_page' => $promotedTrunkPage->nextTrunkPage,
                'promoted_leaf_count' => count($promotedTrunkPage->leafPageNumbers),
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'reuse_pointer_map_type' => $reuseEntry['type_name'],
                'reuse_pointer_map_parent' => $reuseEntry['parent_page_number'],
                'materialized_with_supplied_image' => isset($allocatedPageImages[$pageNumber]),
                'promoted_trunk_header_overwritten' => substr($databaseAfterReuse->page($pageNumber), 0, 8) !== substr($databaseAfterFree->page($pageNumber), 0, 8),
                'next_page_type_byte' => ord($databaseAfterReuse->page($pageNumber)[0]),
            ];
        }

        return $rows;
    }
}
