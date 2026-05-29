<?php
declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan
{
    private function __construct(private readonly object $inner)
    {
    }

    public static function __callStatic(string $name, array $args): self
    {
        $args = self::unwrapArgs($args);
        if (method_exists(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextExtendedVariantPlan::class, $name)) {
            return new self(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextExtendedVariantPlan::{$name}(...$args));
        }
        if (method_exists(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextBaseVariantPlan::class, $name)) {
            return new self(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextBaseVariantPlan::{$name}(...$args));
        }

        throw new \BadMethodCallException(sprintf('Unknown %s factory method %s', self::class, $name));
    }

    public static function overflowFreelistPointerMapFromDeleteResults(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextBaseVariantPlan::fromDeleteResults(...$args));
    }

    public static function currentSourcePointerMapFromDeleteResults(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextExtendedVariantPlan::fromDeleteResults(...$args));
    }

    public static function currentSourcePointerMapFromBasePlan(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextExtendedVariantPlan::fromBasePlan(...$args));
    }

    /**
     * @param list<mixed> $args
     * @return list<mixed>
     */
    private static function unwrapArgs(array $args): array
    {
        return array_map(static fn (mixed $arg): mixed => $arg instanceof self ? $arg->inner : $arg, $args);
    }

    public function __call(string $name, array $args): mixed
    {
        return $this->inner->{$name}(...$args);
    }

    public function __get(string $name): mixed
    {
        return $this->inner->{$name};
    }

    public function __isset(string $name): bool
    {
        return isset($this->inner->{$name});
    }

    public function toArray(): array
    {
        return $this->inner->toArray();
    }
}

final class SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextBaseVariantPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
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
        array $deleteResults,
        int $maxTruncatedPages,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum pointer-map next139 requires an auto-vacuum database');
        }
        if ($replacementPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum pointer-map next139 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum pointer-map next139 parent b-tree page must be at page 2 or later');
        }

        $vacuumPlan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults($database, $deleteResults, $maxTruncatedPages, $secureDelete);
        $databaseAfterVacuum = $vacuumPlan->materializedDatabase();
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementPayload),
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $allocationPlan = $databaseAfterVacuum->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
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
            self::buildTransitionRows($database, $vacuumPlan, $allocationPlan, $databaseAfterAllocation),
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
    public function reusedSurvivingFreelistPages(): array
    {
        return array_values(array_intersect(
            $this->allocatedOverflowPages(),
            $this->vacuumPlan->survivingFreedPointerMapPages(),
        ));
    }

    /**
     * @return list<int>
     */
    public function attemptedTruncatedReuses(): array
    {
        return array_values(array_intersect(
            $this->allocatedOverflowPages(),
            $this->vacuumPlan->truncatedFreedPointerMapPages(),
        ));
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
            'action' => 'btree-freelist-vacuum-pointermap-current-source-next139',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'vacuum_surviving_freed_pages' => $this->vacuumPlan->survivingFreedPointerMapPages(),
            'vacuum_truncated_freed_pages' => $this->vacuumPlan->truncatedFreedPointerMapPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_surviving_freelist_pages' => $this->reusedSurvivingFreelistPages(),
            'attempted_truncated_reuses' => $this->attemptedTruncatedReuses(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'vacuum' => $this->vacuumPlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'btree_freelist_vacuum_pointermap_current_source_next139' => $this->transitionRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(
        SQLiteDatabase $sourceDatabase,
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterAllocation,
    ): array {
        $allocationSteps = $allocationPlan->allocationSteps();
        $allocationPositionByPage = [];
        $allocationStepByPage = [];
        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $allocationPositionByPage[$pageNumber] = $position;
            $allocationStepByPage[$pageNumber] = $allocationSteps[$position] ?? [];
        }

        $sourceByPage = [];
        foreach ($vacuumPlan->releasePlan->sources as $source) {
            foreach ($source['pages'] as $pageNumber) {
                $sourceByPage[(int) $pageNumber] = $source['source'];
            }
        }

        $rows = [];
        foreach ($vacuumPlan->pointerMapVacuumTransitions() as $transition) {
            $pageNumber = (int) $transition['page_number'];
            $allocated = array_key_exists($pageNumber, $allocationPositionByPage);
            $nextEntry = null;
            if ($allocated && $pageNumber <= $databaseAfterAllocation->pageCount()) {
                $nextEntry = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            }
            $page = $allocated ? $databaseAfterAllocation->page($pageNumber) : null;
            $step = $allocationStepByPage[$pageNumber] ?? [];

            $rows[] = [
                'source' => $sourceByPage[$pageNumber] ?? null,
                'page_number' => $pageNumber,
                'vacuum_status' => $transition['status'],
                'vacuum_current_type' => $transition['current_type_name'],
                'vacuum_next_type' => $transition['next_type_name'],
                'truncated_type' => $transition['truncated_type_name'],
                'allocated_after_vacuum' => $allocated,
                'allocation_position' => $allocationPositionByPage[$pageNumber] ?? null,
                'allocation_source' => $step['source'] ?? null,
                'allocation_trunk_page' => $step['trunk_page'] ?? null,
                'final_pointer_map_type' => $nextEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $nextEntry['parent_page_number'] ?? null,
                'final_overflow_next_page' => $page === null ? null : self::readUInt32($page, 0),
                'payload_prefix' => $page === null ? null : substr($page, 4, 16),
                'source_pointer_map_type' => $sourceDatabase->pointerMapEntryForPage($pageNumber)->typeName(),
                'source_pointer_map_parent' => $sourceDatabase->pointerMapEntryForPage($pageNumber)->parentPageNumber,
            ];
        }

        return $rows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum pointer-map next139 could not read uint32');
        }

        return $value[1];
    }
}

final class SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextExtendedVariantPlan
{
    /**
     * @param list<array<string, mixed>> $boundaryRows
     */
    private function __construct(
        public readonly SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextBaseVariantPlan $basePlan,
        private readonly array $boundaryRows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        int $maxTruncatedPages,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
    ): self {
        return self::fromBasePlan(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextBaseVariantPlan::fromDeleteResults(
            $database,
            $deleteResults,
            $maxTruncatedPages,
            $replacementPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextBaseVariantPlan $basePlan): self
    {
        if (!$basePlan->vacuumPlan->sourceDatabase->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum pointer-map next148 requires an auto-vacuum database');
        }

        return new self($basePlan, self::buildBoundaryRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function boundaryRows(): array
    {
        return $this->boundaryRows;
    }

    /**
     * @return list<int>
     */
    public function truncatedPointerMapPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->boundaryRows, static fn (array $row): bool => $row['pointer_map_page'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedFreelistPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->boundaryRows, static fn (array $row): bool => $row['freelist_page'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function allocatedAfterBoundaryPages(): array
    {
        return array_values(array_filter(
            $this->basePlan->allocatedOverflowPages(),
            fn (int $pageNumber): bool => $pageNumber <= $this->basePlan->databaseAfterAllocation->pageCount(),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedBoundaryAllocations(): array
    {
        $allocated = array_fill_keys($this->basePlan->allocatedOverflowPages(), true);

        return array_values(array_filter(
            array_column($this->boundaryRows, 'page_number'),
            static fn (int $pageNumber): bool => !isset($allocated[$pageNumber]),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freelist-vacuum-pointermap-current-source-next148',
            'released_overflow_pages' => $this->basePlan->releasedOverflowPages(),
            'truncated_page_numbers' => $this->basePlan->truncatedPageNumbers(),
            'truncated_pointer_map_pages' => $this->truncatedPointerMapPages(),
            'truncated_freelist_pages' => $this->truncatedFreelistPages(),
            'allocated_overflow_pages' => $this->basePlan->allocatedOverflowPages(),
            'allocated_after_boundary_pages' => $this->allocatedAfterBoundaryPages(),
            'rejected_boundary_allocations' => $this->rejectedBoundaryAllocations(),
            'final_database_page_count' => $this->basePlan->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'boundary_rows' => $this->boundaryRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildBoundaryRows(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextBaseVariantPlan $basePlan): array
    {
        $sourceDatabase = $basePlan->vacuumPlan->sourceDatabase;
        $currentDatabase = $basePlan->vacuumPlan->currentDatabase;
        $nextDatabase = $basePlan->vacuumPlan->nextDatabase;
        $finalDatabase = $basePlan->databaseAfterAllocation;
        $released = array_fill_keys($basePlan->releasedOverflowPages(), true);
        $allocated = array_fill_keys($basePlan->allocatedOverflowPages(), true);
        $currentFreelist = array_fill_keys($basePlan->vacuumPlan->currentFreelistPageNumbers(), true);
        $rows = [];

        foreach ($basePlan->truncatedPageNumbers() as $pageNumber) {
            $pointerMapPage = $sourceDatabase->isPointerMapPage($pageNumber);
            $freelistPage = isset($currentFreelist[$pageNumber]);
            $rows[] = [
                'page_number' => $pageNumber,
                'source_page_count' => $sourceDatabase->pageCount(),
                'current_page_count' => $currentDatabase->pageCount(),
                'post_vacuum_page_count' => $nextDatabase->pageCount(),
                'final_page_count' => $finalDatabase->pageCount(),
                'pointer_map_page' => $pointerMapPage,
                'freelist_page' => $freelistPage,
                'released_overflow_page' => isset($released[$pageNumber]),
                'allocated_after_vacuum' => isset($allocated[$pageNumber]),
                'source_pointer_map_page' => $pointerMapPage ? null : $sourceDatabase->pointerMapPageFor($pageNumber),
                'current_pointer_map_type' => $pointerMapPage ? null : $sourceDatabase->pointerMapEntryForPage($pageNumber)->typeName(),
                'current_pointer_map_parent' => $pointerMapPage ? null : $sourceDatabase->pointerMapEntryForPage($pageNumber)->parentPageNumber,
                'final_materialized' => $pageNumber <= $finalDatabase->pageCount(),
                'boundary_status' => $pointerMapPage
                    ? 'truncated-auto-vacuum-pointer-map-page'
                    : ($freelistPage ? 'truncated-freelist-page' : 'truncated-tail-page'),
            ];
        }

        return $rows;
    }
}
