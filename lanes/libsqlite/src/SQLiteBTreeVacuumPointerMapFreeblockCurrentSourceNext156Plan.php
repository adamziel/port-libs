<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Plan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNext144Plan $basePlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        public readonly array $rows,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self {
        return self::fromBasePlan(
            SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNext144Plan::tableLeafFromDeleteResult(
                $database,
                $leafPageNumber,
                $deleteResult,
                $maxTruncatedPages,
                $secureDelete,
            ),
            $replacementOverflowPayload,
            $parentBtreePageNumber,
        );
    }

    public static function fromBasePlan(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNext144Plan $basePlan,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
    ): self {
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next156 parent b-tree page must be at page 2 or later');
        }

        $vacuumDatabase = $basePlan->basePlan->basePlan->nextDatabase;
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $vacuumDatabase->header->pageSize,
            $vacuumDatabase->usablePageSize(),
        );
        $allocationPlan = $vacuumDatabase->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $vacuumDatabase->header->pageSize,
            $vacuumDatabase->usablePageSize(),
        );
        $databaseAfterAllocation = $vacuumDatabase->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $basePlan,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildRows($basePlan, $allocationPlan, $databaseAfterAllocation),
        );
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
    public function survivingReleasedOverflowPagesReused(): array
    {
        return array_values(array_intersect(
            $this->basePlan->basePlan->survivingReleasedOverflowPages(),
            $this->allocatedOverflowPages(),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedReleasedOverflowPagesRejected(): array
    {
        $allocated = array_fill_keys($this->allocatedOverflowPages(), true);

        return array_values(array_filter(
            $this->basePlan->basePlan->truncatedReleasedOverflowPages(),
            static fn (int $pageNumber): bool => !isset($allocated[$pageNumber]),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedPointerMapPagesRejected(): array
    {
        $allocated = array_fill_keys($this->allocatedOverflowPages(), true);

        return array_values(array_filter(
            $this->basePlan->basePlan->truncatedPointerMapPages(),
            static fn (int $pageNumber): bool => !isset($allocated[$pageNumber]),
        ));
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
        $images = $this->basePlan->basePlan->basePlan->pageImages;
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
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next156',
            'leaf_page' => $this->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'surviving_released_overflow_pages' => $this->basePlan->basePlan->survivingReleasedOverflowPages(),
            'truncated_released_overflow_pages' => $this->basePlan->basePlan->truncatedReleasedOverflowPages(),
            'truncated_pointer_map_pages' => $this->basePlan->basePlan->truncatedPointerMapPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'surviving_released_overflow_pages_reused' => $this->survivingReleasedOverflowPagesReused(),
            'truncated_released_overflow_pages_rejected' => $this->truncatedReleasedOverflowPagesRejected(),
            'truncated_pointer_map_pages_rejected' => $this->truncatedPointerMapPagesRejected(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'rows' => $this->rows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildRows(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNext144Plan $basePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterAllocation,
    ): array {
        $allocated = array_fill_keys($allocationPlan->allocatedPageNumbers, true);
        $rows = [];

        foreach ($basePlan->rows as $row) {
            $pageNumber = (int) $row['page_number'];
            $isMaterialized = (bool) $row['materialized'];
            $isAllocated = isset($allocated[$pageNumber]);
            $finalEntry = $isMaterialized || $isAllocated
                ? self::pointerMapEntry($databaseAfterAllocation, $pageNumber)
                : null;

            $rows[] = [
                'kind' => $row['kind'],
                'page_number' => $pageNumber,
                'source_pointer_map_type' => $row['source_pointer_map_type'],
                'source_pointer_map_parent' => $row['source_pointer_map_parent'],
                'post_vacuum_pointer_map_type' => $row['next_pointer_map_type'],
                'post_vacuum_pointer_map_parent' => $row['next_pointer_map_parent'],
                'post_vacuum_status' => $row['vacuum_status'],
                'post_vacuum_materialized' => $isMaterialized,
                'allocated_for_replacement' => $isAllocated,
                'rejected_after_truncate' => !$isMaterialized && !$isAllocated,
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
                'final_materialized' => $pageNumber <= $databaseAfterAllocation->pageCount(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }
}
