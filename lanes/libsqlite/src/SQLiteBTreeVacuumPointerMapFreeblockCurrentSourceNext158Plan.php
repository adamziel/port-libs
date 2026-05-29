<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext158Plan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $vacuumPlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $rows,
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
        int $parentBtreePageNumber,
        string $replacementOverflowPayload,
        bool $secureDelete = true,
    ): self {
        return self::fromVacuumPlan(
            SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next144TableLeafFromDeleteResult(
                $database,
                $leafPageNumber,
                $deleteResult,
                $maxTruncatedPages,
                $secureDelete,
            ),
            $parentBtreePageNumber,
            $replacementOverflowPayload,
        );
    }

    public static function fromVacuumPlan(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $vacuumPlan,
        int $parentBtreePageNumber,
        string $replacementOverflowPayload,
    ): self {
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next158 requires replacement overflow payload bytes');
        }

        $databaseAfterVacuum = $vacuumPlan->basePlan->basePlan->nextDatabase;
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $allocationPlan = $databaseAfterVacuum->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
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
            self::buildRows($vacuumPlan, $databaseAfterVacuum, $databaseAfterAllocation, $allocationPlan),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
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
    public function reusedVacuumFreelistPages(): array
    {
        return array_values(array_intersect(
            $this->vacuumPlan->toArray()['final_freelist_page_numbers'],
            $this->allocationPlan->allocatedPageNumbers,
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedPagesNotReused(): array
    {
        return array_values(array_diff(
            $this->vacuumPlan->toArray()['truncated_page_numbers'],
            $this->allocationPlan->allocatedPageNumbers,
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
        $updated = array_fill_keys($this->vacuumPlan->toArray()['updated_page_numbers'], true);
        foreach (array_keys($this->allocationPlan->pageImages()) as $pageNumber) {
            $updated[$pageNumber] = true;
        }
        foreach ($this->overflowPageImages as $pageNumber => $page) {
            $updated[$pageNumber] = true;
        }

        $images = [];
        foreach (array_keys($updated) as $pageNumber) {
            if ($pageNumber <= $this->databaseAfterAllocation->pageCount()) {
                $images[$pageNumber] = $this->databaseAfterAllocation->page($pageNumber);
            }
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
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next158',
            'leaf_page' => $this->vacuumPlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->vacuumPlan->toArray()['released_overflow_pages'],
            'surviving_released_overflow_pages' => $this->vacuumPlan->toArray()['surviving_released_overflow_pages'],
            'truncated_page_numbers' => $this->vacuumPlan->toArray()['truncated_page_numbers'],
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_vacuum_freelist_pages' => $this->reusedVacuumFreelistPages(),
            'truncated_pages_not_reused' => $this->truncatedPagesNotReused(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'rows' => $this->rows,
            'vacuum' => $this->vacuumPlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildRows(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $vacuumPlan,
        SQLiteDatabase $databaseAfterVacuum,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $vacuumRowsByPage = [];
        foreach ($vacuumPlan->rows as $row) {
            $vacuumRowsByPage[(int) $row['page_number']] = $row;
        }

        $steps = $allocationPlan->allocationSteps();
        $rows = [];
        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $before = $databaseAfterVacuum->pointerMapEntryForPage($pageNumber)->toArray();
            $after = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $page = $databaseAfterAllocation->page($pageNumber);
            $vacuumRow = $vacuumRowsByPage[$pageNumber] ?? null;
            $rows[] = [
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'allocation_source' => $steps[$position]['source'] ?? null,
                'allocation_trunk_page' => $steps[$position]['trunk_page'] ?? null,
                'vacuum_status' => $vacuumRow['vacuum_status'] ?? null,
                'vacuum_freelist_role' => $vacuumRow['freelist_role'] ?? null,
                'before_pointer_map_type' => $before['type_name'],
                'before_pointer_map_parent' => $before['parent_page_number'],
                'next_pointer_map_type' => $after['type_name'],
                'next_pointer_map_parent' => $after['parent_page_number'],
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'next_overflow_is_tail' => self::readUInt32($page, 0) === 0,
                'payload_prefix' => substr($page, 4, 12),
            ];
        }

        return $rows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next158 could not read uint32');
        }

        return $value[1];
    }
}
