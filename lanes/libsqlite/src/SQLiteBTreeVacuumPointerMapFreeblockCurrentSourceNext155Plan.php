<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext155Plan
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param array<int, string> $btreePageImages
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $vacuumPlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        public readonly array $rows,
        private readonly array $btreePageImages,
    ) {
    }

    /**
     * @param array<int, string> $btreePageImages
     */
    public static function fromVacuumPlan(
        SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $vacuumPlan,
        ?int $parentBtreePageNumber,
        array $btreePageImages,
    ): self {
        if ($btreePageImages === []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next155 requires a replacement b-tree page image');
        }
        if ($parentBtreePageNumber !== null && $parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next155 parent page must be null or at page 2 or later');
        }

        $nextDatabase = $vacuumPlan->basePlan->nextDatabase;
        $allocationPlan = $nextDatabase->planBtreePageAllocation(count($btreePageImages), $parentBtreePageNumber, false);
        $allocated = $allocationPlan->allocatedPageNumbers;
        $btreePageImages = self::normalizeImages($btreePageImages, $allocated, $nextDatabase->header->pageSize);
        $databaseAfterAllocation = $nextDatabase->applyPageAllocationPlan($allocationPlan, $btreePageImages);

        return new self(
            $vacuumPlan,
            $allocationPlan,
            $databaseAfterAllocation,
            self::buildRows($vacuumPlan, $allocationPlan, $databaseAfterAllocation),
            $btreePageImages,
        );
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @param array<int, string> $btreePageImages
     */
    public static function tableLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        ?int $parentBtreePageNumber,
        array $btreePageImages,
        bool $secureDelete = false,
    ): self {
        return self::fromVacuumPlan(
            SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan::tableLeafFromDeleteResult(
                $database,
                $leafPageNumber,
                $deleteResult,
                $maxTruncatedPages,
                $secureDelete,
            ),
            $parentBtreePageNumber,
            $btreePageImages,
        );
    }

    /**
     * @return list<int>
     */
    public function allocatedBtreePages(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function reusedVacuumFreePages(): array
    {
        return array_values(array_intersect(
            $this->allocatedBtreePages(),
            $this->vacuumPlan->survivingReleasedOverflowPages(),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->vacuumPlan->basePlan->pageImages;
        foreach ($this->allocationPlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        foreach ($this->btreePageImages as $pageNumber => $page) {
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
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next155',
            'leaf_page' => $this->vacuumPlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->vacuumPlan->basePlan->releasedOverflowPages(),
            'surviving_vacuum_free_pages' => $this->vacuumPlan->survivingReleasedOverflowPages(),
            'truncated_vacuum_pages' => $this->vacuumPlan->truncatedReleasedOverflowPages(),
            'allocated_btree_pages' => $this->allocatedBtreePages(),
            'reused_vacuum_free_pages' => $this->reusedVacuumFreePages(),
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'updated_page_numbers' => array_keys($this->pageImages()),
            'btree_vacuum_pointermap_freeblock_current_source_next155' => $this->rows,
            'vacuum' => $this->vacuumPlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
        ];
    }

    /**
     * @param array<int, string> $images
     * @param list<int> $allocatedPageNumbers
     * @return array<int, string>
     */
    private static function normalizeImages(array $images, array $allocatedPageNumbers, int $pageSize): array
    {
        $normalized = [];
        $indexed = array_values($images) === $images;
        foreach ($allocatedPageNumbers as $index => $pageNumber) {
            $page = $indexed ? ($images[$index] ?? null) : ($images[$pageNumber] ?? null);
            if (!is_string($page) || strlen($page) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next155 page image length does not match page size');
            }
            $normalized[$pageNumber] = $page;
        }

        if (count($normalized) !== count($images)) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next155 page images must match allocated pages');
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildRows(
        SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $vacuumPlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterAllocation,
    ): array {
        $sourceDatabase = $vacuumPlan->basePlan->sourceDatabase;
        $afterVacuum = $vacuumPlan->basePlan->nextDatabase;
        $steps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $sourceEntry = $sourceDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            $vacuumEntry = $afterVacuum->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $header = SQLiteBTreePageHeader::parsePage(
                $databaseAfterAllocation->page($pageNumber),
                $databaseAfterAllocation->header->pageSize,
            );

            $rows[] = [
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'allocation_source' => $steps[$position]['source'] ?? null,
                'allocation_trunk_page' => $steps[$position]['trunk_page'] ?? null,
                'source_pointer_map_type' => $sourceEntry['type_name'],
                'source_pointer_map_parent' => $sourceEntry['parent_page_number'],
                'vacuum_pointer_map_type' => $vacuumEntry['type_name'],
                'vacuum_pointer_map_parent' => $vacuumEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'btree_page_type' => $header->pageType,
                'btree_cell_count' => $header->cellCount,
                'btree_freeblock_status' => $header->freeblockIntegrityReport($databaseAfterAllocation->page($pageNumber))['status'],
                'freelist_count_after' => $steps[$position]['freelist_page_count_after'] ?? null,
            ];
        }

        return $rows;
    }
}
