<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        public readonly array $rows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        int $maxTruncatedPages,
        int $parentBtreePageNumber,
        string $newOverflowPayload,
        bool $secureDelete = true,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map vacuum overflow next133 requires an auto-vacuum database');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map vacuum overflow next133 parent b-tree page must be at page 2 or later');
        }
        if ($newOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map vacuum overflow next133 requires replacement overflow payload bytes');
        }

        $vacuumPlan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
            $database,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
        );
        $databaseAfterVacuum = $vacuumPlan->materializedDatabase();
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($newOverflowPayload),
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $allocationPlan = $databaseAfterVacuum->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, true);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $newOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterVacuum->applyPageAllocationPlan($allocationPlan, $overflowPageImages);
        $rows = self::rows($database, $vacuumPlan, $databaseAfterAllocation, $allocationPlan);

        if ($allocationPlan->appendedPageNumbers === []) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map vacuum overflow next133 requires appended overflow pages after vacuum truncation');
        }
        if (!self::hasRecreatedPointerMapPage($vacuumPlan, $allocationPlan)) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map vacuum overflow next133 requires a recreated auto-vacuum pointer-map page');
        }

        return new self(
            $vacuumPlan,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            $rows,
        );
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
    public function recreatedPointerMapPages(): array
    {
        return array_values(array_intersect(
            $this->truncatedPageNumbers(),
            array_keys($this->allocationPlan->updatedPointerMapPages),
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
            'action' => 'btree-pointermap-vacuum-overflow-current-source-next133',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'recreated_pointer_map_pages' => $this->recreatedPointerMapPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'appended_overflow_pages' => $this->allocationPlan->appendedPageNumbers,
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'updated_page_numbers' => array_keys($this->pageImages()),
            'vacuum' => $this->vacuumPlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'btree_pointermap_vacuum_overflow_current_source_next133' => $this->rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(
        SQLiteDatabase $sourceDatabase,
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $sourceByPage = self::releaseSourcesByPage($vacuumPlan->releasePlan);
        $truncated = array_fill_keys($vacuumPlan->truncatedPageNumbers(), true);
        $allocated = array_fill_keys($allocationPlan->allocatedPageNumbers, true);
        $allocationSteps = [];
        foreach ($allocationPlan->allocationSteps() as $step) {
            if (isset($step['allocated_page']) && is_int($step['allocated_page'])) {
                $allocationSteps[$step['allocated_page']] = $step;
            }
        }

        $pageNumbers = array_values(array_unique(array_merge(
            $vacuumPlan->releasedOverflowPages(),
            $vacuumPlan->truncatedPageNumbers(),
            array_intersect($vacuumPlan->truncatedPageNumbers(), array_keys($allocationPlan->updatedPointerMapPages)),
            $allocationPlan->allocatedPageNumbers,
        )));
        sort($pageNumbers);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $isPointerMapPage = $sourceDatabase->isAutoVacuum() && $sourceDatabase->isPointerMapPage($pageNumber);
            $sourceEntry = null;
            if (!$isPointerMapPage && $pageNumber <= $sourceDatabase->pageCount()) {
                $sourceEntry = $sourceDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }
            $nextEntry = null;
            if (!$databaseAfterAllocation->isPointerMapPage($pageNumber) && $pageNumber <= $databaseAfterAllocation->pageCount()) {
                $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            }
            $step = $allocationSteps[$pageNumber] ?? null;

            $rows[] = [
                'page_number' => $pageNumber,
                'release_source' => $sourceByPage[$pageNumber] ?? null,
                'was_pointer_map_page' => $isPointerMapPage,
                'released_overflow' => array_key_exists($pageNumber, $sourceByPage),
                'truncated_by_vacuum' => isset($truncated[$pageNumber]),
                'allocated_after_vacuum' => isset($allocated[$pageNumber]),
                'recreated_pointer_map_page' => isset($allocationPlan->updatedPointerMapPages[$pageNumber]),
                'allocation_source' => $step['source'] ?? null,
                'current_pointer_map_type' => $sourceEntry['type_name'] ?? null,
                'current_pointer_map_parent' => $sourceEntry['parent_page_number'] ?? null,
                'next_pointer_map_type' => $nextEntry['type_name'] ?? null,
                'next_pointer_map_parent' => $nextEntry['parent_page_number'] ?? null,
                'current_overflow_next_page' => (!$isPointerMapPage && $pageNumber <= $sourceDatabase->pageCount())
                    ? self::readUInt32($sourceDatabase->page($pageNumber), 0)
                    : null,
                'next_overflow_next_page' => (isset($allocated[$pageNumber]) && $pageNumber <= $databaseAfterAllocation->pageCount())
                    ? self::readUInt32($databaseAfterAllocation->page($pageNumber), 0)
                    : null,
                'page_status' => self::pageStatus($isPointerMapPage, $pageNumber, $sourceByPage, $truncated, $allocated, $allocationPlan),
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

    private static function pageStatus(
        bool $isPointerMapPage,
        int $pageNumber,
        array $sourceByPage,
        array $truncated,
        array $allocated,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): string {
        if (isset($allocationPlan->updatedPointerMapPages[$pageNumber])) {
            return 'recreated-pointer-map-page';
        }
        if (isset($allocated[$pageNumber])) {
            return 'allocated-overflow-page';
        }
        if ($isPointerMapPage && isset($truncated[$pageNumber])) {
            return 'vacuum-truncated-pointer-map-page';
        }
        if (array_key_exists($pageNumber, $sourceByPage) && isset($truncated[$pageNumber])) {
            return 'released-overflow-truncated';
        }
        if (array_key_exists($pageNumber, $sourceByPage)) {
            return 'released-overflow-survives-free';
        }

        return isset($truncated[$pageNumber]) ? 'vacuum-truncated-free-page' : 'unchanged';
    }

    private static function hasRecreatedPointerMapPage(
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): bool {
        foreach ($vacuumPlan->truncatedPageNumbers() as $pageNumber) {
            if (isset($allocationPlan->updatedPointerMapPages[$pageNumber])) {
                return true;
            }
        }

        return false;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map vacuum overflow next133 could not read uint32');
        }

        return $value[1];
    }
}
