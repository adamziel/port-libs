<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapFreelistOverflowCurrentSourceNext141Plan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $preparedSourceRows
     * @param list<array<string, mixed>> $currentSourceRows
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteDatabase $preparedDatabase,
        public readonly SQLiteDatabase $currentDatabase,
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $preparedSourceRows,
        private readonly array $currentSourceRows,
        private readonly array $transitionRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $preparedOverflowChains
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $currentOverflowChains
     */
    public static function fromPreparedAndCurrentOverflowChains(
        SQLiteDatabase $preparedDatabase,
        SQLiteDatabase $currentDatabase,
        array $preparedOverflowChains,
        array $currentOverflowChains,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
    ): self {
        if (!$preparedDatabase->isAutoVacuum() || !$currentDatabase->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freelist overflow next141 requires auto-vacuum databases');
        }
        if ($preparedDatabase->header->pageSize !== $currentDatabase->header->pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freelist overflow next141 requires matching page sizes');
        }
        if ($replacementPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freelist overflow next141 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freelist overflow next141 parent b-tree page must be at page 2 or later');
        }

        $preparedSourceRows = self::buildSourceRows($preparedDatabase, $preparedOverflowChains, 'prepared');
        $currentSourceRows = self::buildSourceRows($currentDatabase, $currentOverflowChains, 'current');
        if ($currentSourceRows === []) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freelist overflow next141 requires at least one current overflow page');
        }

        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromOverflowChains($currentDatabase, $currentOverflowChains, $secureDelete);
        $databaseAfterRelease = $currentDatabase->applyPageFreePlan($releasePlan->freePlan);
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementPayload),
            $currentDatabase->header->pageSize,
            $currentDatabase->usablePageSize(),
        );
        $allocationPlan = $databaseAfterRelease->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementPayload,
            $allocationPlan->allocatedPageNumbers,
            $currentDatabase->header->pageSize,
            $currentDatabase->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterRelease->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $preparedDatabase,
            $currentDatabase,
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            $preparedSourceRows,
            $currentSourceRows,
            self::buildTransitionRows(
                $preparedDatabase,
                $currentDatabase,
                $databaseAfterRelease,
                $databaseAfterAllocation,
                $preparedSourceRows,
                $currentSourceRows,
                $releasePlan,
                $allocationPlan,
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function preparedSourceRows(): array
    {
        return $this->preparedSourceRows;
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
    public function stalePreparedOverflowPages(): array
    {
        return array_values(array_diff(
            array_column($this->preparedSourceRows, 'page_number'),
            array_column($this->currentSourceRows, 'page_number'),
        ));
    }

    /**
     * @return list<int>
     */
    public function reusedCurrentOverflowPages(): array
    {
        return array_values(array_intersect($this->allocatedOverflowPages(), $this->releasedOverflowPages()));
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
        $images = $this->releasePlan->freePlan->pageImages();
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
            'action' => 'btree-pointermap-freelist-overflow-current-source-next141',
            'prepared_source_overflow_pages' => array_column($this->preparedSourceRows, 'page_number'),
            'current_source_overflow_pages' => array_column($this->currentSourceRows, 'page_number'),
            'stale_prepared_overflow_pages' => $this->stalePreparedOverflowPages(),
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_current_overflow_pages' => $this->reusedCurrentOverflowPages(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'prepared_source_rows' => $this->preparedSourceRows,
            'current_source_rows' => $this->currentSourceRows,
            'btree_pointermap_freelist_overflow_current_source_next141' => $this->transitionRows,
            'dependency_closure' => 'no new support component needed; reuses SQLiteOverflowFreelistReleasePlan, SQLiteFreelistAllocationPlan, and auto-vacuum pointer-map page image application',
            'non_overlap' => 'compares stale prepared overflow chain pages against current-source overflow pages before freelist release; does not repeat next132 single-source reuse, accepted overflow freelist release, or page-move/root-collapse clusters',
        ];
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $chains
     * @return list<array<string, mixed>>
     */
    private static function buildSourceRows(SQLiteDatabase $database, array $chains, string $sourceKind): array
    {
        $rows = [];
        foreach (array_values($chains) as $chainIndex => $chain) {
            if (!is_array($chain)) {
                throw new \InvalidArgumentException('SQLite b-tree pointer-map freelist overflow next141 chains must be arrays');
            }
            $firstPage = $chain['first_page'] ?? null;
            $payloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite b-tree pointer-map freelist overflow next141 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree pointer-map freelist overflow next141 chain is missing an overflow payload byte count');
            }

            $source = $chain['source'] ?? "{$sourceKind}-overflow-chain-{$chainIndex}";
            foreach (SQLiteOverflowPage::chainLinksFromDatabase($database, $firstPage, $payloadBytes) as $position => $link) {
                $entry = $database->pointerMapEntryForPage($link['current_page'])->toArray();
                $rows[] = [
                    'source_kind' => $sourceKind,
                    'source' => $source,
                    'chain_index' => $chainIndex,
                    'chain_position' => $position,
                    'page_number' => $link['current_page'],
                    'next_page' => $link['next_page'],
                    'payload_bytes' => $link['payload_bytes'],
                    'terminal' => $link['terminal'],
                    'pointer_map_type' => $entry['type_name'],
                    'pointer_map_parent' => $entry['parent_page_number'],
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $preparedSourceRows
     * @param list<array<string, mixed>> $currentSourceRows
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(
        SQLiteDatabase $preparedDatabase,
        SQLiteDatabase $currentDatabase,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        array $preparedSourceRows,
        array $currentSourceRows,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $preparedPages = array_fill_keys(array_column($preparedSourceRows, 'page_number'), true);
        $currentPages = array_fill_keys(array_column($currentSourceRows, 'page_number'), true);
        $releasedPages = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $allocationSteps = $allocationPlan->allocationSteps();
        $releaseSources = self::releaseSourcesByPage($releasePlan);
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $preparedEntry = $preparedDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            $currentEntry = $currentDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $page = $databaseAfterAllocation->page($pageNumber);
            $step = $allocationSteps[$position] ?? [];

            $rows[] = [
                'page_number' => $pageNumber,
                'replacement_chain_position' => $position,
                'prepared_chain_page' => isset($preparedPages[$pageNumber]),
                'current_chain_page' => isset($currentPages[$pageNumber]),
                'released_current_page' => isset($releasedPages[$pageNumber]),
                'release_source' => $releaseSources[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'prepared_pointer_map_type' => $preparedEntry['type_name'],
                'prepared_pointer_map_parent' => $preparedEntry['parent_page_number'],
                'current_pointer_map_type' => $currentEntry['type_name'],
                'current_pointer_map_parent' => $currentEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'payload_prefix' => substr($page, 4, 16),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private static function releaseSourcesByPage(SQLiteOverflowFreelistReleasePlan $releasePlan): array
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
            throw new \InvalidArgumentException('SQLite b-tree pointer-map freelist overflow next141 could not read uint32');
        }

        return $value[1];
    }
}
