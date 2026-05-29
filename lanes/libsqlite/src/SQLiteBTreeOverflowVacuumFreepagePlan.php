<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowVacuumFreepagePlan
{
    /**
     * @param array<int, string> $currentPageImages
     * @param list<int> $currentFreelistPages
     * @param list<int> $nextAllocationOrder
     * @param list<array{source:string,pages:list<int>,count:int}> $sources
     */
    private function __construct(
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteDatabase $currentDatabase,
        public readonly array $currentPageImages,
        public readonly array $currentFreelistPages,
        public readonly array $nextAllocationOrder,
        public readonly array $sources,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        bool $secureDelete = false,
        ?int $nextAllocationLimit = null,
    ): self {
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, $deleteResults, $secureDelete);

        return self::fromReleasePlan($database, $releasePlan, $nextAllocationLimit);
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $chains
     */
    public static function fromOverflowChains(
        SQLiteDatabase $database,
        array $chains,
        bool $secureDelete = false,
        ?int $nextAllocationLimit = null,
    ): self {
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromOverflowChains($database, $chains, $secureDelete);

        return self::fromReleasePlan($database, $releasePlan, $nextAllocationLimit);
    }

    public function currentFirstFreelistTrunkPage(): int
    {
        return $this->currentDatabase->header->firstFreelistTrunkPage;
    }

    public function currentFreelistPageCount(): int
    {
        return $this->currentDatabase->header->freelistPageCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-vacuum-freepage-current-next',
            'sources' => $this->sources,
            'released_overflow_pages' => $this->releasePlan->releasedOverflowPages,
            'current_first_freelist_trunk_page' => $this->currentFirstFreelistTrunkPage(),
            'current_freelist_page_count' => $this->currentFreelistPageCount(),
            'current_freelist_pages' => $this->currentFreelistPages,
            'next_allocation_order' => $this->nextAllocationOrder,
            'current_page_numbers' => array_keys($this->currentPageImages),
            'updated_pointer_map_page_numbers' => array_keys($this->releasePlan->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->releasePlan->freePlan->clearedPageNumbers,
            'overflow_pointer_map_freepage_rows' => $this->overflowPointerMapFreepageRows(),
            'overflow_freepage_vacuum_rows' => $this->overflowFreepageVacuumRows(
                count($this->releasePlan->releasedOverflowPages),
                $this->firstReusableSourceParentPage(),
            ),
        ];
    }

    /**
     * @return list<array{source:string,page_number:int,current_type_name:string|null,current_parent_page_number:int|null,next_type_name:string|null,next_parent_page_number:int|null,freelist_role:string,freelist_position:int|null,next_allocation_position:int|null,secure_deleted:bool,pointer_map_page:int|null}>
     */
    public function overflowPointerMapFreepageRows(): array
    {
        $currentEntries = [];
        if ($this->releasePlan->freePlan->freedPointerMapEntries !== []) {
            foreach ($this->releasePlan->releasedOverflowPages as $pageNumber) {
                if ($this->releasePlan->freePlan->databasePageCount < $pageNumber) {
                    continue;
                }
                if (!$this->sourceDatabase->isAutoVacuum() || $this->sourceDatabase->isPointerMapPage($pageNumber)) {
                    continue;
                }
                $currentEntries[$pageNumber] = $this->sourceDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }
        }

        $nextEntries = [];
        foreach ($this->releasePlan->freePlan->freedPointerMapEntries as $entry) {
            $nextEntries[(int) $entry['page_number']] = $entry;
        }

        $freelistPositions = [];
        foreach ($this->currentFreelistPages as $index => $pageNumber) {
            $freelistPositions[$pageNumber] = $index;
        }

        $allocationPositions = [];
        foreach ($this->nextAllocationOrder as $index => $pageNumber) {
            $allocationPositions[$pageNumber] = $index;
        }

        $newTrunks = array_fill_keys($this->releasePlan->freePlan->newTrunkPageNumbers, true);
        $leaves = array_fill_keys($this->releasePlan->freePlan->leafPageNumbers, true);
        $cleared = array_fill_keys($this->releasePlan->freePlan->clearedPageNumbers, true);

        $rows = [];
        foreach ($this->releasePlan->sources as $source) {
            foreach ($source['pages'] as $pageNumber) {
                $current = $currentEntries[$pageNumber] ?? null;
                $next = $nextEntries[$pageNumber] ?? null;

                $role = 'freelist-existing';
                if (isset($newTrunks[$pageNumber])) {
                    $role = 'freelist-trunk';
                } elseif (isset($leaves[$pageNumber])) {
                    $role = 'freelist-leaf';
                }

                $rows[] = [
                    'source' => $source['source'],
                    'page_number' => $pageNumber,
                    'current_type_name' => $current['type_name'] ?? null,
                    'current_parent_page_number' => $current['parent_page_number'] ?? null,
                    'next_type_name' => $next['type_name'] ?? null,
                    'next_parent_page_number' => $next['parent_page_number'] ?? null,
                    'freelist_role' => $role,
                    'freelist_position' => $freelistPositions[$pageNumber] ?? null,
                    'next_allocation_position' => $allocationPositions[$pageNumber] ?? null,
                    'secure_deleted' => isset($cleared[$pageNumber]),
                    'pointer_map_page' => $next['pointer_map_page'] ?? ($current['pointer_map_page'] ?? null),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{source:string|null,page_number:int,current_status:string,next_status:string,freelist_role:string|null,freelist_position:int|null,allocation_position:int|null,allocation_source:string|null,allocation_trunk_page:int|null,current_pointer_map_type:string|null,current_pointer_map_parent:int|null,next_pointer_map_type:string|null,next_pointer_map_parent:int|null,pointer_map_page:int|null,secure_deleted_before_reuse:bool}>
     */
    public function overflowFreepageVacuumRows(int $allocationCount, ?int $parentPageNumber): array
    {
        if ($allocationCount < 0) {
            throw new \InvalidArgumentException('SQLite overflow freepage vacuum current-source overflow freepage vacuum allocation count cannot be negative');
        }
        if ($allocationCount === 0) {
            return [];
        }
        if ($parentPageNumber !== null && $parentPageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow freepage vacuum current-source overflow freepage vacuum parent page must be null or at page 2 or later');
        }

        $allocationPlan = $this->currentDatabase->planBtreePageAllocation($allocationCount, $parentPageNumber, false);

        $sourceByPage = [];
        foreach ($this->releasePlan->sources as $source) {
            foreach ($source['pages'] as $pageNumber) {
                $sourceByPage[$pageNumber] = $source['source'];
            }
        }

        $freelistPositions = [];
        foreach ($this->currentFreelistPages as $index => $pageNumber) {
            $freelistPositions[$pageNumber] = $index;
        }

        $newTrunks = array_fill_keys($this->releasePlan->freePlan->newTrunkPageNumbers, true);
        $leaves = array_fill_keys($this->releasePlan->freePlan->leafPageNumbers, true);
        $cleared = array_fill_keys($this->releasePlan->freePlan->clearedPageNumbers, true);

        $nextEntries = [];
        foreach ($allocationPlan->allocatedPointerMapEntries() as $entry) {
            $nextEntries[(int) $entry['page_number']] = $entry;
        }

        $rows = [];
        foreach ($allocationPlan->allocationSteps() as $index => $step) {
            $pageNumber = (int) $step['allocated_page'];
            $current = null;
            if ($this->currentDatabase->isAutoVacuum() && !$this->currentDatabase->isPointerMapPage($pageNumber)) {
                $current = $this->currentDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }
            $next = $nextEntries[$pageNumber] ?? null;

            $role = null;
            if (isset($newTrunks[$pageNumber])) {
                $role = 'freelist-trunk';
            } elseif (isset($leaves[$pageNumber])) {
                $role = 'freelist-leaf';
            } elseif (array_key_exists($pageNumber, $freelistPositions)) {
                $role = 'freelist-existing';
            }

            $rows[] = [
                'source' => $sourceByPage[$pageNumber] ?? null,
                'page_number' => $pageNumber,
                'current_status' => $current === null ? 'current-freepage' : 'current-pointer-map-free-page',
                'next_status' => $next === null ? 'allocated-without-pointer-map' : 'allocated-btree-page',
                'freelist_role' => $role,
                'freelist_position' => $freelistPositions[$pageNumber] ?? null,
                'allocation_position' => $index,
                'allocation_source' => is_string($step['source'] ?? null) ? $step['source'] : null,
                'allocation_trunk_page' => is_int($step['trunk_page'] ?? null) ? $step['trunk_page'] : null,
                'current_pointer_map_type' => $current['type_name'] ?? null,
                'current_pointer_map_parent' => $current['parent_page_number'] ?? null,
                'next_pointer_map_type' => $next['type_name'] ?? null,
                'next_pointer_map_parent' => $next['parent_page_number'] ?? null,
                'pointer_map_page' => $next['pointer_map_page'] ?? ($current['pointer_map_page'] ?? null),
                'secure_deleted_before_reuse' => isset($cleared[$pageNumber]),
            ];
        }

        return $rows;
    }

    private function firstReusableSourceParentPage(): ?int
    {
        foreach ($this->releasePlan->releasedOverflowPages as $pageNumber) {
            if (!$this->sourceDatabase->isAutoVacuum() || $this->sourceDatabase->isPointerMapPage($pageNumber)) {
                continue;
            }

            $entry = $this->sourceDatabase->pointerMapEntryForPage($pageNumber);
            if ($entry->parentPageNumber >= 2) {
                return $entry->parentPageNumber;
            }
        }

        return null;
    }

    private static function fromReleasePlan(
        SQLiteDatabase $database,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        ?int $nextAllocationLimit,
    ): self {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[$pageNumber] = $database->page($pageNumber);
        }
        foreach ($releasePlan->freePlan->pageImages() as $pageNumber => $page) {
            $pages[$pageNumber] = $page;
        }
        ksort($pages);

        $currentDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
        $currentPageImages = $releasePlan->freePlan->pageImages();

        return new self(
            $releasePlan,
            $database,
            $currentDatabase,
            $currentPageImages,
            $currentDatabase->freelistPageNumbers(),
            $currentDatabase->freelistAllocationOrder($nextAllocationLimit),
            $releasePlan->sources,
        );
    }
}
