<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNext148Plan
{
    /**
     * @param list<array<string, mixed>> $boundaryRows
     */
    private function __construct(
        public readonly SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNext139Plan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNext139Plan::fromDeleteResults(
            $database,
            $deleteResults,
            $maxTruncatedPages,
            $replacementPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNext139Plan $basePlan): self
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
    private static function buildBoundaryRows(SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNext139Plan $basePlan): array
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
