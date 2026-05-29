<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNext128Plan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
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
        int $leafPageNumber,
        array $deleteResults,
        int $parentBtreePageNumber,
        string $newOverflowPayload,
        bool $secureDelete = false,
        bool $clearCoalescedFragments = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 requires an auto-vacuum database');
        }
        if ($newOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 parent b-tree page must be at page 2 or later');
        }

        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage(
            $database,
            $leafPageNumber,
            $clearCoalescedFragments,
        );
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($coalescedDatabase, $deleteResults, $secureDelete);
        $databaseAfterRelease = $coalescedDatabase->applyPageFreePlan($releasePlan->freePlan);
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($newOverflowPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $databaseAfterRelease->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $newOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterRelease->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $database,
            $coalescePlan,
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildTransitionRows(
                $database,
                $databaseAfterRelease,
                $databaseAfterAllocation,
                $coalescePlan,
                $releasePlan,
                $allocationPlan,
            ),
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
    public function overflowPageImages(): array
    {
        return $this->overflowPageImages;
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->coalescePlan->pageImages();
        foreach ($this->releasePlan->freePlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
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
            'action' => 'btree-overflow-freeblock-pointermap-current-source-next128',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'fragmented_bytes_before' => $this->coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->coalescePlan->fragmentedBytesAfter,
            'freeblock_count_before' => count($this->coalescePlan->beforeFreeblocks),
            'freeblock_count_after' => count($this->coalescePlan->afterFreeblocks),
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_overflow_pages' => $this->reusedOverflowPages(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'coalesce' => $this->coalescePlan->toArray(),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'btree_overflow_freeblock_pointermap_current_source_next128' => $this->transitionRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $sourceByPage = self::releaseSourceByPage($releasePlan);
        $allocationSteps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            if (!isset($released[$pageNumber])) {
                continue;
            }

            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationSteps[$position] ?? [];
            $page = $databaseAfterAllocation->page($pageNumber);

            $rows[] = [
                'leaf_page' => $coalescePlan->pageNumber,
                'page_number' => $pageNumber,
                'chain_position' => $position,
                'release_source' => $sourceByPage[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'freelist_trunk_page' => $step['trunk_page'] ?? null,
                'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
                'fragmented_bytes_before' => $coalescePlan->fragmentedBytesBefore,
                'fragmented_bytes_after' => $coalescePlan->fragmentedBytesAfter,
                'freeblock_count_before' => count($coalescePlan->beforeFreeblocks),
                'freeblock_count_after' => count($coalescePlan->afterFreeblocks),
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'payload_prefix' => substr($page, 4, 16),
            ];
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 did not reuse an obsolete overflow page');
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
            throw new \InvalidArgumentException('SQLite overflow freeblock pointer-map next128 could not read uint32');
        }

        return $value[1];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
