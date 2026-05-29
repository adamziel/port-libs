<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $reuseRows
     */
    private function __construct(
        public readonly SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $deletePlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $reuseRows,
    ) {
    }

    /**
     * @param list<int> $rowIds
     */
    public static function tableLeafReplacement(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $rowIds,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite overflow rebalance freelist current-source next134 requires an auto-vacuum database');
        }
        if ($replacementPayload === '') {
            throw new \InvalidArgumentException('SQLite overflow rebalance freelist current-source next134 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite overflow rebalance freelist current-source next134 parent b-tree page must be at page 2 or later');
        }

        $deletePlan = SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::tableLeaf(
            $database,
            $leafPageNumber,
            $rowIds,
            $secureDelete,
        );
        $databaseAfterDelete = $deletePlan->databaseAfter();
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementPayload),
            $databaseAfterDelete->header->pageSize,
            $databaseAfterDelete->usablePageSize(),
        );
        $allocationPlan = $databaseAfterDelete->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementPayload,
            $allocationPlan->allocatedPageNumbers,
            $databaseAfterDelete->header->pageSize,
            $databaseAfterDelete->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterDelete->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $deletePlan,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildReuseRows($database, $databaseAfterDelete, $databaseAfterAllocation, $deletePlan, $allocationPlan),
        );
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPages(): array
    {
        return $this->deletePlan->releasedPageNumbers();
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
        $images = $this->deletePlan->pageImages;
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
            'action' => 'btree-overflow-rebalance-freelist-current-source-next134',
            'delete_rebalance' => $this->deletePlan->toArray(),
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_released_overflow_pages' => $this->reusedReleasedOverflowPages(),
            'allocation' => $this->allocationPlan->toArray(),
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'updated_page_numbers' => array_keys($this->pageImages()),
            'btree_overflow_rebalance_freelist_current_source_next134' => $this->reuseRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReuseRows(
        SQLiteDatabase $databaseBefore,
        SQLiteDatabase $databaseAfterDelete,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $deletePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $released = array_fill_keys($deletePlan->releasedPageNumbers(), true);
        $deleteSources = self::deleteSourcesByPage($deletePlan);
        $allocationSteps = $allocationPlan->allocationSteps();
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $beforeEntry = $databaseBefore->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterDelete->pointerMapEntryForPage($pageNumber)->toArray();
            $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationSteps[$position] ?? [];

            $rows[] = [
                'chain_position' => $position,
                'page_number' => $pageNumber,
                'page_origin' => isset($released[$pageNumber]) ? 'deleted-overflow-page' : 'existing-freelist-page',
                'delete_phase' => $deleteSources[$pageNumber]['phase'] ?? null,
                'delete_step' => $deleteSources[$pageNumber]['step'] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'allocation_trunk_page' => $step['trunk_page'] ?? null,
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'next_pointer_map_type' => $nextEntry['type_name'],
                'next_pointer_map_parent' => $nextEntry['parent_page_number'],
                'next_overflow_next_page' => unpack('N', substr($databaseAfterAllocation->page($pageNumber), 0, 4))[1],
                'payload_prefix' => substr($databaseAfterAllocation->page($pageNumber), 4, 16),
                'final_freelist_page_count' => $databaseAfterAllocation->header->freelistPageCount,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{phase:string,step:int}>
     */
    private static function deleteSourcesByPage(SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $deletePlan): array
    {
        $sources = [];
        foreach ($deletePlan->transitionRows() as $row) {
            foreach ($row['freed_pages'] as $pageNumber) {
                $sources[$pageNumber] = [
                    'phase' => (string) $row['phase'],
                    'step' => (int) $row['step'],
                ];
            }
        }

        return $sources;
    }
}
