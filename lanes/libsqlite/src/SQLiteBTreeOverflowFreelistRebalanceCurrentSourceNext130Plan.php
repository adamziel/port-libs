<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNext130Plan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $releasedRows
     * @param list<array<string, mixed>> $allocatedRows
     */
    private function __construct(
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $releasedRows,
        private readonly array $allocatedRows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        int $parentBtreePageNumber,
        string $replacementOverflowPayload,
        bool $secureDelete = false,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite overflow freelist rebalance next130 requires an auto-vacuum database');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow freelist rebalance next130 parent b-tree page must be at page 2 or later');
        }
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite overflow freelist rebalance next130 requires replacement overflow payload bytes');
        }

        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, $deleteResults, $secureDelete);
        $databaseAfterRelease = $database->applyPageFreePlan($releasePlan->freePlan);
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $databaseAfterRelease->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterRelease->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildReleasedRows($database, $databaseAfterRelease, $databaseAfterAllocation, $releasePlan),
            self::buildAllocatedRows($database, $databaseAfterRelease, $databaseAfterAllocation, $allocationPlan),
        );
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPages(): array
    {
        return $this->releasePlan->releasedOverflowPages;
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
    public function deferredReleasedOverflowPages(): array
    {
        return array_values(array_diff($this->releasedOverflowPages(), $this->allocatedOverflowPages()));
    }

    /**
     * @return list<int>
     */
    public function reusedExistingFreelistPages(): array
    {
        return array_values(array_diff($this->allocatedOverflowPages(), $this->releasedOverflowPages()));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function releasedRows(): array
    {
        return $this->releasedRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allocatedRows(): array
    {
        return $this->allocatedRows;
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
        $pageImages = $this->releasePlan->freePlan->pageImages();
        foreach ($this->allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        foreach ($this->overflowPageImages as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freelist-rebalance-current-source-next130',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'deferred_released_overflow_pages' => $this->deferredReleasedOverflowPages(),
            'reused_existing_freelist_pages' => $this->reusedExistingFreelistPages(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'released_rows' => $this->releasedRows,
            'allocated_rows' => $this->allocatedRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReleasedRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
    ): array {
        $releaseSources = self::releaseSourceByPage($releasePlan);
        $allocated = array_fill_keys($databaseAfterAllocation->freelistAllocationOrder(), true);
        $rows = [];

        foreach ($releasePlan->releasedOverflowPages as $pageNumber) {
            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $finalEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();

            $rows[] = [
                'page_number' => $pageNumber,
                'release_source' => $releaseSources[$pageNumber] ?? null,
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'final_pointer_map_type' => $finalEntry['type_name'],
                'final_pointer_map_parent' => $finalEntry['parent_page_number'],
                'deferred_on_freelist' => isset($allocated[$pageNumber]),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildAllocatedRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $steps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $finalEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $page = $databaseAfterAllocation->page($pageNumber);
            $step = $steps[$position] ?? [];

            $rows[] = [
                'page_number' => $pageNumber,
                'chain_position' => $position,
                'allocation_source' => $step['source'] ?? null,
                'freelist_trunk_page' => $step['trunk_page'] ?? null,
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'final_pointer_map_type' => $finalEntry['type_name'],
                'final_pointer_map_parent' => $finalEntry['parent_page_number'],
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'payload_prefix' => substr($page, 4, 16),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private static function releaseSourceByPage(SQLiteOverflowFreelistReleasePlan $releasePlan): array
    {
        $sources = [];
        foreach ($releasePlan->sources as $source) {
            foreach ($source['pages'] as $pageNumber) {
                $sources[$pageNumber] = $source['source'];
            }
        }

        return $sources;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite overflow freelist rebalance next130 could not read uint32');
        }

        return $value[1];
    }
}
