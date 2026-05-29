<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $allocatedPageImages
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $allocatedPageImages,
        private readonly array $transitionRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $releasedChains
     */
    public static function fromOverflowChains(
        SQLiteDatabase $database,
        array $releasedChains,
        int $newOverflowPayloadBytes,
        int $parentBtreePageNumber,
        string $newOverflowPayload,
        bool $secureDelete = false,
    ): self {
        if ($newOverflowPayloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map freelist next125 payload byte count must be positive');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map freelist next125 parent b-tree page must be at page 2 or later');
        }
        if (strlen($newOverflowPayload) < $newOverflowPayloadBytes) {
            throw new \InvalidArgumentException('SQLite overflow pointer-map freelist next125 payload image is shorter than requested bytes');
        }

        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromOverflowChains($database, $releasedChains, $secureDelete);
        $databaseAfterRelease = $database->applyPageFreePlan($releasePlan->freePlan);
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            $newOverflowPayloadBytes,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $databaseAfterRelease->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $allocatedPageImages = SQLiteOverflowPage::encodeChainAtPages(
            substr($newOverflowPayload, 0, $newOverflowPayloadBytes),
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
        );
        $databaseAfterAllocation = $databaseAfterRelease->applyPageAllocationPlan($allocationPlan, $allocatedPageImages);

        return new self(
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $allocatedPageImages,
            self::buildTransitionRows($database, $databaseAfterRelease, $databaseAfterAllocation, $releasePlan, $allocationPlan),
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
    public function reusedOverflowPages(): array
    {
        return array_values(array_intersect($this->releasedOverflowPages(), $this->allocatedOverflowPages()));
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
    public function allocatedPageImages(): array
    {
        return $this->allocatedPageImages;
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
        foreach ($this->allocatedPageImages as $pageNumber => $page) {
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
            'action' => 'btree-overflow-pointermap-freelist-current-source-next125',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_overflow_pages' => $this->reusedOverflowPages(),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'btree_overflow_pointermap_freelist_current_source_next125' => $this->transitionRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $releaseSources = self::releaseSourceByPage($releasePlan);
        $allocationSteps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            if (!isset($released[$pageNumber])) {
                continue;
            }

            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $nextPage = $databaseAfterAllocation->page($pageNumber);
            $step = $allocationSteps[$position] ?? [];

            $rows[] = [
                'page_number' => $pageNumber,
                'chain_position' => $position,
                'release_source' => $releaseSources[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'freelist_trunk_page' => $step['trunk_page'] ?? null,
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'next_overflow_next_page' => self::readUInt32($nextPage, 0),
                'payload_prefix' => substr($nextPage, 4, 12),
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
            throw new \InvalidArgumentException('SQLite overflow pointer-map freelist next125 could not read uint32');
        }

        return $value[1];
    }
}
