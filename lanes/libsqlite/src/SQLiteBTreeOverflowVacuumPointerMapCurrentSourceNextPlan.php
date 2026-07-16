<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $currentSourceRows
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
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
    public static function fromCurrentSourceOverflowChains(
        SQLiteDatabase $database,
        array $currentOverflowChains,
        array $deleteResults,
        int $maxTruncatedPages,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree overflow vacuum pointer-map next145 requires an auto-vacuum database');
        }
        if ($currentOverflowChains === []) {
            throw new \InvalidArgumentException('SQLite b-tree overflow vacuum pointer-map next145 requires current-source overflow chains');
        }
        if ($replacementPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree overflow vacuum pointer-map next145 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree overflow vacuum pointer-map next145 parent b-tree page must be at page 2 or later');
        }

        $currentSourceRows = self::buildCurrentSourceRows($database, $currentOverflowChains);
        $vacuumPlan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
            $database,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
        );
        $databaseAfterVacuum = $vacuumPlan->materializedDatabase();
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementPayload),
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $allocationPlan = $databaseAfterVacuum->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, true);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementPayload,
            $allocationPlan->allocatedPageNumbers,
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterVacuum->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $vacuumPlan,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            $currentSourceRows,
            self::buildTransitionRows($database, $vacuumPlan, $allocationPlan, $databaseAfterAllocation, $currentSourceRows),
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
        return $this->vacuumPlan->releasedOverflowPages();
    }

    /**
     * @return list<int>
     */
    public function truncatedPageNumbers(): array
    {
        return $this->vacuumPlan->truncatedPageNumbers();
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
    public function reusedSurvivingOverflowPages(): array
    {
        return array_values(array_intersect(
            $this->allocatedOverflowPages(),
            $this->vacuumPlan->survivingFreedPointerMapPages(),
        ));
    }

    /**
     * @return list<int>
     */
    public function appendedOverflowPages(): array
    {
        return $this->allocationPlan->appendedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function truncatedOverflowPagesNotReused(): array
    {
        return array_values(array_intersect(
            $this->releasedOverflowPages(),
            $this->vacuumPlan->truncatedFreedPointerMapPages(),
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
        $images = $this->vacuumPlan->pageImages;
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
            'action' => 'btree-overflow-vacuum-pointermap-current-source-next145',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'vacuum_surviving_freed_pages' => $this->vacuumPlan->survivingFreedPointerMapPages(),
            'vacuum_truncated_freed_pages' => $this->vacuumPlan->truncatedFreedPointerMapPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_surviving_overflow_pages' => $this->reusedSurvivingOverflowPages(),
            'appended_overflow_pages' => $this->appendedOverflowPages(),
            'truncated_overflow_pages_not_reused' => $this->truncatedOverflowPagesNotReused(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'btree_overflow_vacuum_pointermap_current_source_next145' => $this->transitionRows,
            'vacuum' => $this->vacuumPlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
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
                throw new \InvalidArgumentException('SQLite b-tree overflow vacuum pointer-map next145 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow vacuum pointer-map next145 chain is missing an overflow payload byte count');
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
            throw new \InvalidArgumentException('SQLite b-tree overflow vacuum pointer-map next145 requires at least one current-source overflow page');
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $currentSourceRows
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(
        SQLiteDatabase $sourceDatabase,
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterAllocation,
        array $currentSourceRows,
    ): array {
        $currentByPage = [];
        foreach ($currentSourceRows as $row) {
            $currentByPage[(int) $row['page_number']] = $row;
        }

        $allocationSteps = $allocationPlan->allocationSteps();
        $allocationPositionByPage = [];
        $allocationStepByPage = [];
        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $allocationPositionByPage[$pageNumber] = $position;
            $allocationStepByPage[$pageNumber] = $allocationSteps[$position] ?? [];
        }

        $transitionByPage = [];
        foreach ($vacuumPlan->pointerMapVacuumTransitions() as $transition) {
            $transitionByPage[(int) $transition['page_number']] = $transition;
        }

        $pages = array_values(array_unique(array_merge(
            array_column($currentSourceRows, 'page_number'),
            $allocationPlan->allocatedPageNumbers,
        )));
        sort($pages);

        $rows = [];
        foreach ($pages as $pageNumber) {
            $pageNumber = (int) $pageNumber;
            $allocated = array_key_exists($pageNumber, $allocationPositionByPage);
            $appended = in_array($pageNumber, $allocationPlan->appendedPageNumbers, true);
            $transition = $transitionByPage[$pageNumber] ?? null;
            $current = $currentByPage[$pageNumber] ?? null;
            $step = $allocationStepByPage[$pageNumber] ?? [];
            $finalEntry = null;
            $finalNextPage = null;
            $payloadPrefix = null;
            if ($allocated && $pageNumber <= $databaseAfterAllocation->pageCount()) {
                $finalEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
                $page = $databaseAfterAllocation->page($pageNumber);
                $finalNextPage = self::readUInt32($page, 0);
                $payloadPrefix = substr($page, 4, 16);
            }
            $sourceEntry = null;
            if ($pageNumber <= $sourceDatabase->pageCount() && !$sourceDatabase->isPointerMapPage($pageNumber)) {
                $sourceEntry = $sourceDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'current_source' => $current['source'] ?? null,
                'current_chain_position' => $current['chain_position'] ?? null,
                'current_next_page' => $current['current_next_page'] ?? null,
                'current_terminal' => $current['current_terminal'] ?? null,
                'current_pointer_map_type' => $current['current_pointer_map_type'] ?? ($sourceEntry['type_name'] ?? null),
                'current_pointer_map_parent' => $current['current_pointer_map_parent'] ?? ($sourceEntry['parent_page_number'] ?? null),
                'vacuum_status' => $transition['status'] ?? ($appended ? 'appended-after-vacuum' : null),
                'vacuum_next_pointer_map_type' => $transition['next_type_name'] ?? null,
                'vacuum_truncated_pointer_map_type' => $transition['truncated_type_name'] ?? null,
                'allocated_after_vacuum' => $allocated,
                'allocation_position' => $allocationPositionByPage[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? ($appended ? 'append' : null),
                'allocation_trunk_page' => $step['trunk_page'] ?? null,
                'appended_after_vacuum' => $appended,
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
                'final_overflow_next_page' => $finalNextPage,
                'payload_prefix' => $payloadPrefix,
            ];
        }

        return $rows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree overflow vacuum pointer-map next145 could not read uint32');
        }

        return $value[1];
    }
}
