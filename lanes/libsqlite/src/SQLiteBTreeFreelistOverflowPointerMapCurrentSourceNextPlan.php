<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $currentSourceRows
     * @param list<array<string, mixed>> $reuseRows
     */
    private function __construct(
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $currentSourceRows,
        private readonly array $reuseRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $releasedChains
     */
    public static function fromOverflowChains(
        SQLiteDatabase $database,
        array $releasedChains,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree freelist overflow pointer-map next132 requires an auto-vacuum database');
        }
        if ($replacementPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree freelist overflow pointer-map next132 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree freelist overflow pointer-map next132 parent b-tree page must be at page 2 or later');
        }

        $currentSourceRows = self::buildCurrentSourceRows($database, $releasedChains);
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromOverflowChains($database, $releasedChains, $secureDelete);
        $databaseAfterRelease = $database->applyPageFreePlan($releasePlan->freePlan);
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

        return new self(
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            $currentSourceRows,
            self::buildReuseRows($database, $databaseAfterRelease, $databaseAfterAllocation, $releasePlan, $allocationPlan),
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
    public function reusedReleasedOverflowPages(): array
    {
        return array_values(array_intersect($this->allocatedOverflowPages(), $this->releasedOverflowPages()));
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
    public function reuseRows(): array
    {
        return $this->reuseRows;
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
            'action' => 'btree-freelist-overflow-pointermap-current-source-next132',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_released_overflow_pages' => $this->reusedReleasedOverflowPages(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'btree_freelist_overflow_pointermap_current_source_next132' => $this->reuseRows,
        ];
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $chains
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteDatabase $database, array $chains): array
    {
        $rows = [];
        foreach (array_values($chains) as $chainIndex => $chain) {
            if (!is_array($chain)) {
                throw new \InvalidArgumentException('SQLite b-tree freelist overflow pointer-map next132 chains must be arrays');
            }
            $firstPage = $chain['first_page'] ?? null;
            $payloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite b-tree freelist overflow pointer-map next132 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree freelist overflow pointer-map next132 chain is missing an overflow payload byte count');
            }

            $source = $chain['source'] ?? "overflow-chain-{$chainIndex}";
            $links = SQLiteOverflowPage::chainLinksFromDatabase($database, $firstPage, $payloadBytes);
            foreach ($links as $position => $link) {
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

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReuseRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $releaseSources = self::releaseSourcesByPage($releasePlan);
        $allocationSteps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $page = $databaseAfterAllocation->page($pageNumber);
            $step = $allocationSteps[$position] ?? [];

            $rows[] = [
                'page_number' => $pageNumber,
                'replacement_chain_position' => $position,
                'page_origin' => isset($released[$pageNumber]) ? 'released-overflow-page' : 'existing-freelist-page',
                'release_source' => $releaseSources[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'freelist_trunk_page' => $step['trunk_page'] ?? null,
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
            throw new \InvalidArgumentException('SQLite b-tree freelist overflow pointer-map next132 could not read uint32');
        }

        return $value[1];
    }
}
