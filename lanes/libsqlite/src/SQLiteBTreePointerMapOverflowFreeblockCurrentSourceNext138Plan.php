<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNext138Plan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $currentSourceRows
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
        private readonly array $currentSourceRows,
        private readonly array $transitionRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $currentOverflowChains
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromCurrentSourceDeleteResults(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentOverflowChains,
        array $deleteResults,
        int $parentBtreePageNumber,
        string $replacementPayload,
        bool $secureDelete = false,
        bool $clearCoalescedFragments = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 requires an auto-vacuum database');
        }
        if ($replacementPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 parent b-tree page must be at page 2 or later');
        }

        $currentSourceRows = self::buildCurrentSourceRows($database, $currentOverflowChains);
        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage(
            $database,
            $leafPageNumber,
            $clearCoalescedFragments,
        );
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($coalescedDatabase, $deleteResults, $secureDelete);
        $databaseAfterRelease = $coalescedDatabase->applyPageFreePlan($releasePlan->freePlan);
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $databaseAfterRelease->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterRelease->applyPageAllocationPlan($allocationPlan, $overflowPageImages);
        $transitionRows = self::buildTransitionRows(
            $database,
            $databaseAfterRelease,
            $databaseAfterAllocation,
            $currentSourceRows,
            $coalescePlan,
            $releasePlan,
            $allocationPlan,
        );

        return new self(
            $database,
            $coalescePlan,
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            $currentSourceRows,
            $transitionRows,
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
    public function transitionRows(): array
    {
        return $this->transitionRows;
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
    public function reusedCurrentSourcePages(): array
    {
        $currentPages = array_fill_keys(array_column($this->currentSourceRows, 'page_number'), true);

        return array_values(array_filter(
            $this->allocatedOverflowPages(),
            static fn (int $pageNumber): bool => isset($currentPages[$pageNumber]),
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
            'action' => 'btree-pointermap-overflow-freeblock-current-source-next138',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_current_source_pages' => $this->reusedCurrentSourcePages(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'btree_pointermap_overflow_freeblock_current_source_next138' => $this->transitionRows,
        ];
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $chains
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteDatabase $database, array $chains): array
    {
        $rows = [];
        foreach (array_values($chains) as $chainIndex => $chain) {
            $firstPage = $chain['first_page'] ?? null;
            $payloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 chain is missing an overflow payload byte count');
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
                    'current_payload_bytes' => $link['payload_bytes'],
                    'current_terminal' => $link['terminal'],
                    'current_pointer_map_type' => $entry['type_name'],
                    'current_pointer_map_parent' => $entry['parent_page_number'],
                ];
            }
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 requires at least one current-source overflow page');
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $currentSourceRows
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(
        SQLiteDatabase $databaseBefore,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        array $currentSourceRows,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $currentByPage = [];
        foreach ($currentSourceRows as $row) {
            $currentByPage[(int) $row['page_number']] = $row;
        }
        $releaseSources = self::releaseSourceByPage($releasePlan);
        $allocationSteps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            if (!isset($currentByPage[$pageNumber])) {
                continue;
            }

            $current = $currentByPage[$pageNumber];
            $beforeEntry = $databaseBefore->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationSteps[$position] ?? [];

            $rows[] = [
                'source' => $current['source'],
                'page_number' => $pageNumber,
                'current_chain_position' => $current['chain_position'],
                'replacement_chain_position' => $position,
                'current_next_page' => $current['current_next_page'],
                'replacement_next_page' => self::readUInt32($databaseAfterAllocation->page($pageNumber), 0),
                'release_source' => $releaseSources[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'freelist_trunk_page' => $step['trunk_page'] ?? null,
                'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
                'current_pointer_map_type' => $current['current_pointer_map_type'],
                'current_pointer_map_parent' => $current['current_pointer_map_parent'],
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'payload_prefix' => substr($databaseAfterAllocation->page($pageNumber), 4, 16),
            ];
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 did not reuse a current-source overflow page');
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

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[$pageNumber] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow freeblock next138 could not read uint32');
        }

        return $value[1];
    }
}
