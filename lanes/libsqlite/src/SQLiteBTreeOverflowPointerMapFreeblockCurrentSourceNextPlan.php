<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $currentSourceRows
     * @param list<array<string, mixed>> $nextRows
     */
    private function __construct(
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $currentSourceRows,
        private readonly array $nextRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $currentOverflowChains
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromCurrentSourceDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentOverflowChains,
        array $deleteResult,
        int $parentBtreePageNumber,
        string $replacementOverflowPayload,
        bool $secureDelete = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree overflow pointer-map freeblock next142 requires an auto-vacuum database');
        }
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree overflow pointer-map freeblock next142 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree overflow pointer-map freeblock next142 parent b-tree page must be at page 2 or later');
        }

        $currentSourceRows = self::buildCurrentSourceRows($database, $currentOverflowChains);
        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage($database, $leafPageNumber, true);
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($coalescedDatabase, [$deleteResult], $secureDelete);
        $databaseAfterRelease = $coalescedDatabase->applyPageFreePlan($releasePlan->freePlan);
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
        $nextRows = self::buildNextRows(
            $database,
            $databaseAfterRelease,
            $databaseAfterAllocation,
            $coalescePlan,
            $releasePlan,
            $allocationPlan,
            $currentSourceRows,
        );

        return new self(
            $coalescePlan,
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            $currentSourceRows,
            $nextRows,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currentSourceRows(): array
    {
        return $this->currentSourceRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nextRows(): array
    {
        return $this->nextRows;
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
    public function reusedReleasedOverflowPages(): array
    {
        return array_values(array_intersect($this->releasedOverflowPages(), $this->allocatedOverflowPages()));
    }

    /**
     * @return list<int>
     */
    public function allocatedExistingFreelistPages(): array
    {
        return array_values(array_diff($this->allocatedOverflowPages(), $this->releasedOverflowPages()));
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
            'action' => 'btree-overflow-pointermap-freeblock-current-source-next142',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_released_overflow_pages' => $this->reusedReleasedOverflowPages(),
            'allocated_existing_freelist_pages' => $this->allocatedExistingFreelistPages(),
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'btree_overflow_pointermap_freeblock_current_source_next142' => $this->nextRows,
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'coalesce' => $this->coalescePlan->toArray(),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
        ];
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $chains
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteDatabase $database, array $chains): array
    {
        if ($chains === []) {
            throw new \InvalidArgumentException('SQLite b-tree overflow pointer-map freeblock next142 requires current-source overflow chains');
        }

        $rows = [];
        foreach (array_values($chains) as $chainIndex => $chain) {
            $firstPage = $chain['first_page'] ?? null;
            $payloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow pointer-map freeblock next142 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow pointer-map freeblock next142 chain is missing an overflow payload byte count');
            }

            $source = $chain['source'] ?? "current-overflow-chain-{$chainIndex}";
            foreach (SQLiteOverflowPage::chainLinksFromDatabase($database, $firstPage, $payloadBytes) as $position => $link) {
                $entry = $database->pointerMapEntryForPage($link['current_page'])->toArray();
                $rows[] = [
                    'source' => $source,
                    'chain_index' => $chainIndex,
                    'chain_position' => $position,
                    'page_number' => $link['current_page'],
                    'current_next_page' => $link['next_page'],
                    'current_terminal' => $link['terminal'],
                    'current_payload_bytes' => $link['payload_bytes'],
                    'current_pointer_map_type' => $entry['type_name'],
                    'current_pointer_map_parent' => $entry['parent_page_number'],
                ];
            }
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite b-tree overflow pointer-map freeblock next142 requires at least one current-source overflow page');
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $currentSourceRows
     * @return list<array<string, mixed>>
     */
    private static function buildNextRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        array $currentSourceRows,
    ): array {
        $currentByPage = [];
        foreach ($currentSourceRows as $row) {
            $currentByPage[(int) $row['page_number']] = $row;
        }

        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $allocationSteps = $allocationPlan->allocationSteps();
        $nextRows = [];
        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $current = $currentByPage[$pageNumber] ?? null;
            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationSteps[$position] ?? [];
            $page = $databaseAfterAllocation->page($pageNumber);

            $nextRows[] = [
                'leaf_page' => $coalescePlan->pageNumber,
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'page_origin' => isset($released[$pageNumber]) ? 'released-overflow-page' : 'existing-freelist-page',
                'current_source' => $current['source'] ?? null,
                'current_chain_position' => $current['chain_position'] ?? null,
                'current_next_page' => $current['current_next_page'] ?? null,
                'current_terminal' => $current['current_terminal'] ?? null,
                'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
                'fragmented_bytes_before' => $coalescePlan->fragmentedBytesBefore,
                'fragmented_bytes_after' => $coalescePlan->fragmentedBytesAfter,
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'allocation_source' => $step['source'] ?? null,
                'allocation_trunk_page' => $step['trunk_page'] ?? null,
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'next_overflow_is_tail' => self::readUInt32($page, 0) === 0,
                'payload_prefix' => substr($page, 4, 12),
            ];
        }

        return $nextRows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree overflow pointer-map freeblock next142 could not read uint32');
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
