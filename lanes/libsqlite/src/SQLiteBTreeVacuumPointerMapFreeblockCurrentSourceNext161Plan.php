<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext161Plan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        public readonly array $rows,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self {
        return self::fromBasePlan(
            SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next144TableLeafFromDeleteResult(
                $database,
                $leafPageNumber,
                $deleteResult,
                $maxTruncatedPages,
                $secureDelete,
            ),
            $replacementOverflowPayload,
            $parentBtreePageNumber,
        );
    }

    public static function fromBasePlan(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
    ): self {
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next161 parent b-tree page must be at page 2 or later');
        }

        $vacuumDatabase = $basePlan->basePlan->basePlan->nextDatabase;
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $vacuumDatabase->header->pageSize,
            $vacuumDatabase->usablePageSize(),
        );
        $allocationPlan = $vacuumDatabase->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, true);
        if ($allocationPlan->appendedPageNumbers === []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum');
        }

        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $vacuumDatabase->header->pageSize,
            $vacuumDatabase->usablePageSize(),
        );
        $databaseAfterAllocation = $vacuumDatabase->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $basePlan,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildRows($basePlan, $allocationPlan, $databaseAfterAllocation),
        );
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
    public function appendedOverflowPages(): array
    {
        return $this->allocationPlan->appendedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function reusedSurvivingReleasedOverflowPages(): array
    {
        $surviving = array_fill_keys($this->basePlan->basePlan->survivingReleasedOverflowPages(), true);

        return array_values(array_filter(
            $this->allocatedOverflowPages(),
            static fn (int $pageNumber): bool => isset($surviving[$pageNumber]),
        ));
    }

    /**
     * @return list<int>
     */
    public function appendedPreviouslyTruncatedOverflowPages(): array
    {
        $truncated = array_fill_keys($this->basePlan->basePlan->truncatedReleasedOverflowPages(), true);

        return array_values(array_filter(
            $this->appendedOverflowPages(),
            static fn (int $pageNumber): bool => isset($truncated[$pageNumber]),
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
        $images = $this->basePlan->basePlan->basePlan->pageImages;
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
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next161',
            'leaf_page' => $this->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'surviving_released_overflow_pages' => $this->basePlan->basePlan->survivingReleasedOverflowPages(),
            'truncated_released_overflow_pages' => $this->basePlan->basePlan->truncatedReleasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'appended_overflow_pages' => $this->appendedOverflowPages(),
            'reused_surviving_released_overflow_pages' => $this->reusedSurvivingReleasedOverflowPages(),
            'appended_previously_truncated_overflow_pages' => $this->appendedPreviouslyTruncatedOverflowPages(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'rows' => $this->rows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildRows(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterAllocation,
    ): array {
        $allocated = array_fill_keys($allocationPlan->allocatedPageNumbers, true);
        $appended = array_fill_keys($allocationPlan->appendedPageNumbers, true);
        $truncated = array_fill_keys($basePlan->basePlan->truncatedReleasedOverflowPages(), true);
        $rows = [];

        foreach ($basePlan->rows as $row) {
            $pageNumber = (int) $row['page_number'];
            $isAllocated = isset($allocated[$pageNumber]);
            $isAppended = isset($appended[$pageNumber]);
            $finalEntry = ($isAllocated || $pageNumber <= $databaseAfterAllocation->pageCount())
                ? self::pointerMapEntry($databaseAfterAllocation, $pageNumber)
                : null;

            $rows[] = [
                'kind' => $row['kind'],
                'page_number' => $pageNumber,
                'post_vacuum_status' => $row['vacuum_status'],
                'post_vacuum_materialized' => (bool) $row['materialized'],
                'allocated_for_replacement' => $isAllocated,
                'appended_for_replacement' => $isAppended,
                'was_truncated_by_vacuum' => isset($truncated[$pageNumber]),
                'appended_after_truncate' => $isAppended && isset($truncated[$pageNumber]),
                'source_pointer_map_type' => $row['source_pointer_map_type'],
                'source_pointer_map_parent' => $row['source_pointer_map_parent'],
                'post_vacuum_pointer_map_type' => $row['next_pointer_map_type'],
                'post_vacuum_pointer_map_parent' => $row['next_pointer_map_parent'],
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
                'final_overflow_next_page' => $isAllocated ? self::readUInt32($databaseAfterAllocation->page($pageNumber), 0) : null,
                'final_materialized' => $pageNumber <= $databaseAfterAllocation->pageCount(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next161 could not read uint32');
        }

        return $value[1];
    }
}
