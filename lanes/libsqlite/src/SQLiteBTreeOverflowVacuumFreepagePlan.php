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
            'overflow_pointermap_freepage_current_source_next91' => $this->overflowPointerMapFreepageCurrentSourceNext91(),
        ];
    }

    /**
     * @return list<array{source:string,page_number:int,current_type_name:string|null,current_parent_page_number:int|null,next_type_name:string|null,next_parent_page_number:int|null,freelist_role:string,freelist_position:int|null,next_allocation_position:int|null,secure_deleted:bool,pointer_map_page:int|null}>
     */
    public function overflowPointerMapFreepageCurrentSourceNext91(): array
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
