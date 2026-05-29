<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNext117Plan
{
    /**
     * @param list<array<string, mixed>> $reuseRows
     * @param list<array<string, mixed>> $pointerMapPageRows
     */
    private function __construct(
        public readonly SQLiteBTreeFreelistVacuumReuseCurrentSourceNext104Plan $reusePlan,
        public readonly array $reuseRows,
        public readonly array $pointerMapPageRows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     * @param array<int, string> $allocatedPageImages
     */
    public static function fromOverflowDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        int $maxTruncatedPages,
        int $allocationCount,
        ?int $parentPageNumber,
        array $allocatedPageImages = [],
        bool $secureDelete = false,
    ): self {
        $reusePlan = SQLiteBTreeFreelistVacuumReuseCurrentSourceNext104Plan::fromOverflowDeleteResults(
            $database,
            $deleteResults,
            $maxTruncatedPages,
            $allocationCount,
            $parentPageNumber,
            $allocatedPageImages,
            $secureDelete,
        );

        $rows = self::reuseRows($reusePlan);

        return new self(
            $reusePlan,
            $rows,
            self::pointerMapPageRows($reusePlan, $rows),
        );
    }

    /**
     * @return list<int>
     */
    public function allocatedPageNumbers(): array
    {
        return $this->reusePlan->allocatedPageNumbers();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pointerMapVacuumReuseRows(): array
    {
        return $this->reuseRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function touchedPointerMapPageRows(): array
    {
        return $this->pointerMapPageRows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freelist-pointermap-vacuum-reuse-current-source-next117',
            'vacuum_final_database_page_count' => $this->reusePlan->vacuumPlan->finalDatabasePageCount(),
            'vacuum_surviving_freed_pages' => $this->reusePlan->vacuumPlan->survivingFreedPointerMapPages(),
            'vacuum_truncated_freed_pages' => $this->reusePlan->vacuumPlan->truncatedFreedPointerMapPages(),
            'allocated_page_numbers' => $this->reusePlan->allocatedPageNumbers(),
            'final_first_freelist_trunk_page' => $this->reusePlan->databaseAfterReuse->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->reusePlan->databaseAfterReuse->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->reusePlan->databaseAfterReuse->freelistPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->reusePlan->allocationPlan->updatedPointerMapPages),
            'btree_freelist_pointermap_vacuum_reuse_current_source_next117' => $this->reuseRows,
            'pointer_map_page_rewrites_current_source_next117' => $this->pointerMapPageRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function reuseRows(SQLiteBTreeFreelistVacuumReuseCurrentSourceNext104Plan $reusePlan): array
    {
        $afterEntries = [];
        foreach ($reusePlan->allocationPlan->allocatedPointerMapEntries() as $entry) {
            $afterEntries[(int) $entry['page_number']] = $entry;
        }

        $stepByPage = [];
        foreach ($reusePlan->allocationPlan->allocationSteps() as $step) {
            $stepByPage[(int) $step['allocated_page']] = $step;
        }

        $survivors = array_fill_keys($reusePlan->vacuumPlan->survivingFreedPointerMapPages(), true);
        $rows = [];
        foreach ($reusePlan->allocatedPageNumbers() as $position => $pageNumber) {
            $beforeEntry = $reusePlan->vacuumPlan->nextDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            $afterEntry = $afterEntries[$pageNumber] ?? $reusePlan->databaseAfterReuse->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $stepByPage[$pageNumber] ?? [];
            $afterPage = $reusePlan->databaseAfterReuse->page($pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'allocation_source' => $step['source'] ?? null,
                'trunk_page' => $step['trunk_page'] ?? null,
                'reused_vacuum_survivor' => isset($survivors[$pageNumber]),
                'before_pointer_map_page' => $beforeEntry['pointer_map_page'],
                'before_pointer_map_offset' => $beforeEntry['offset'],
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'after_pointer_map_page' => $afterEntry['pointer_map_page'],
                'after_pointer_map_offset' => $afterEntry['offset'],
                'after_pointer_map_type' => $afterEntry['type_name'],
                'after_pointer_map_parent' => $afterEntry['parent_page_number'],
                'pointer_map_page_rewritten' => $beforeEntry['pointer_map_page'] === $afterEntry['pointer_map_page']
                    && isset($reusePlan->allocationPlan->updatedPointerMapPages[$afterEntry['pointer_map_page']]),
                'materialized_page_type_byte' => ord($afterPage[0]),
                'freelist_page_count_after_step' => $step['freelist_page_count_after'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function pointerMapPageRows(
        SQLiteBTreeFreelistVacuumReuseCurrentSourceNext104Plan $reusePlan,
        array $rows,
    ): array {
        $grouped = [];
        foreach ($rows as $row) {
            $pointerMapPage = (int) $row['after_pointer_map_page'];
            $grouped[$pointerMapPage][] = $row;
        }

        $pageRows = [];
        foreach ($grouped as $pointerMapPage => $pageRowsForMap) {
            $pageRows[] = [
                'pointer_map_page' => $pointerMapPage,
                'allocated_pages' => array_values(array_map(
                    static fn (array $row): int => (int) $row['page_number'],
                    $pageRowsForMap,
                )),
                'slot_offsets' => array_values(array_map(
                    static fn (array $row): int => (int) $row['after_pointer_map_offset'],
                    $pageRowsForMap,
                )),
                'rewritten' => isset($reusePlan->allocationPlan->updatedPointerMapPages[$pointerMapPage]),
                'before_types' => array_values(array_map(
                    static fn (array $row): string => (string) $row['before_pointer_map_type'],
                    $pageRowsForMap,
                )),
                'after_types' => array_values(array_map(
                    static fn (array $row): string => (string) $row['after_pointer_map_type'],
                    $pageRowsForMap,
                )),
            ];
        }

        usort($pageRows, static fn (array $a, array $b): int => $a['pointer_map_page'] <=> $b['pointer_map_page']);

        return $pageRows;
    }
}
