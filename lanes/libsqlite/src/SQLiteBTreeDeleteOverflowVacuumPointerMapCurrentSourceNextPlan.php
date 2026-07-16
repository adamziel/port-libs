<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $deleteResults
     * @param list<array<string, mixed>> $deleteRows
     */
    private function __construct(
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly array $deleteResults,
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        public readonly array $deleteRows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        int $maxTruncatedPages,
        bool $secureDelete = false,
    ): self {
        $plan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
            $database,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
        );

        return new self($database, $deleteResults, $plan, self::deleteRows($database, $deleteResults, $plan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deleteOverflowVacuumPointerMapRows(): array
    {
        return $this->deleteRows;
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
    public function survivingFreedPointerMapPages(): array
    {
        return $this->vacuumPlan->survivingFreedPointerMapPages();
    }

    /**
     * @return list<int>
     */
    public function truncatedFreedPointerMapPages(): array
    {
        return $this->vacuumPlan->truncatedFreedPointerMapPages();
    }

    /**
     * @return array<string, mixed>
     */
    public function materializedApplySummary(): array
    {
        return $this->vacuumPlan->materializedApplySummary();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-delete-overflow-vacuum-pointermap-current-source-next119',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'surviving_freed_pointer_map_pages' => $this->survivingFreedPointerMapPages(),
            'truncated_freed_pointer_map_pages' => $this->truncatedFreedPointerMapPages(),
            'final_database_page_count' => $this->vacuumPlan->finalDatabasePageCount(),
            'final_first_freelist_trunk_page' => $this->vacuumPlan->finalFirstFreelistTrunkPage(),
            'final_freelist_page_count' => $this->vacuumPlan->finalFreelistPageCount(),
            'delete_overflow_vacuum_pointermap_current_source_next119' => $this->deleteRows,
            'pointer_map_vacuum_transitions' => $this->vacuumPlan->pointerMapVacuumTransitions(),
            'materialized_apply' => $this->materializedApplySummary(),
            'vacuum_plan' => $this->vacuumPlan->toArray(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     * @return list<array<string, mixed>>
     */
    private static function deleteRows(SQLiteDatabase $database, array $deleteResults, SQLiteOverflowVacuumTruncatePlan $plan): array
    {
        $transitionByPage = [];
        foreach ($plan->pointerMapVacuumTransitions() as $transition) {
            $transitionByPage[(int) $transition['page_number']] = $transition;
        }

        $rows = [];
        foreach (array_values($deleteResults) as $deleteIndex => $deleteResult) {
            if (!is_array($deleteResult)) {
                throw new \InvalidArgumentException('SQLite delete overflow vacuum pointer-map next119 delete results must be arrays');
            }

            $source = self::sourceLabel($deleteResult, $deleteIndex);
            $leafPageNumber = self::leafPageNumber($deleteResult);
            $deletedPage = $deleteResult['page'] ?? null;
            $leafHash = is_string($deletedPage) ? hash('sha256', $deletedPage) : null;
            $currentHash = $leafPageNumber !== null ? hash('sha256', $database->page($leafPageNumber)) : null;
            $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
            if (!is_array($pages)) {
                throw new \InvalidArgumentException('SQLite delete overflow vacuum pointer-map next119 requires obsolete overflow pages');
            }

            foreach (array_values($pages) as $chainPosition => $pageNumber) {
                if (!is_int($pageNumber)) {
                    throw new \InvalidArgumentException('SQLite delete overflow vacuum pointer-map next119 overflow pages must be integers');
                }
                $currentEntry = $database->isAutoVacuum() && !$database->isPointerMapPage($pageNumber)
                    ? $database->pointerMapEntryForPage($pageNumber)->toArray()
                    : null;
                $transition = $transitionByPage[$pageNumber] ?? null;

                $rows[] = [
                    'source' => $source,
                    'leaf_page' => $leafPageNumber,
                    'chain_position' => $chainPosition,
                    'page_number' => $pageNumber,
                    'current_leaf_page_hash' => $currentHash,
                    'deleted_leaf_page_hash' => $leafHash,
                    'current_overflow_next_page' => self::readUInt32($database->page($pageNumber), 0),
                    'current_pointer_map_type' => $currentEntry['type_name'] ?? null,
                    'current_pointer_map_parent' => $currentEntry['parent_page_number'] ?? null,
                    'vacuum_status' => $transition['status'] ?? null,
                    'next_pointer_map_type' => $transition['next_type_name'] ?? null,
                    'truncated_pointer_map_type' => $transition['truncated_type_name'] ?? null,
                    'materialized' => ($transition['status'] ?? null) === 'survives-as-free-page',
                    'truncated' => ($transition['status'] ?? null) === 'truncated-from-database',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    private static function sourceLabel(array $deleteResult, int $deleteIndex): string
    {
        $source = $deleteResult['source'] ?? null;
        if (is_string($source) && $source !== '') {
            return $source;
        }

        return "delete-{$deleteIndex}";
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    private static function leafPageNumber(array $deleteResult): ?int
    {
        $pageNumber = $deleteResult['leaf_page'] ?? $deleteResult['page_number'] ?? null;

        return is_int($pageNumber) ? $pageNumber : null;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite delete overflow vacuum pointer-map next119 could not read uint32');
        }

        return $value[1];
    }
}
