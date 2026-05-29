<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $deleteResults
     * @param list<array<string, mixed>> $mergeRows
     */
    private function __construct(
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly array $deleteResults,
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        public readonly array $mergeRows,
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
        $vacuumPlan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
            $database,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
        );

        return new self(
            $database,
            $deleteResults,
            $vacuumPlan,
            self::mergeRows($database, $deleteResults, $vacuumPlan),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pointerMapOverflowVacuumMergeRows(): array
    {
        return $this->mergeRows;
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
    public function survivingFreelistPages(): array
    {
        return $this->vacuumPlan->survivingFreedPointerMapPages();
    }

    /**
     * @return list<int>
     */
    public function truncatedFreelistPages(): array
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
     * @return array<int, string>
     */
    public function materializedPageImages(): array
    {
        return $this->vacuumPlan->materializedPageImages();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-pointermap-overflow-vacuum-merge-current-source-next123',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'surviving_freelist_pages' => $this->survivingFreelistPages(),
            'truncated_freelist_pages' => $this->truncatedFreelistPages(),
            'final_database_page_count' => $this->vacuumPlan->finalDatabasePageCount(),
            'final_first_freelist_trunk_page' => $this->vacuumPlan->finalFirstFreelistTrunkPage(),
            'final_freelist_page_count' => $this->vacuumPlan->finalFreelistPageCount(),
            'merge_rows' => $this->mergeRows,
            'materialized_apply' => $this->materializedApplySummary(),
            'vacuum_plan' => $this->vacuumPlan->toArray(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     * @return list<array<string, mixed>>
     */
    private static function mergeRows(
        SQLiteDatabase $database,
        array $deleteResults,
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
    ): array {
        $sourceByPage = [];
        foreach (array_values($deleteResults) as $deleteIndex => $deleteResult) {
            if (!is_array($deleteResult)) {
                throw new \InvalidArgumentException('SQLite next123 overflow vacuum merge delete results must be arrays');
            }

            $source = self::sourceLabel($deleteResult, $deleteIndex);
            $leafPage = self::leafPageNumber($deleteResult);
            $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
            if (!is_array($pages)) {
                throw new \InvalidArgumentException('SQLite next123 overflow vacuum merge requires obsolete overflow pages');
            }

            foreach (array_values($pages) as $chainPosition => $pageNumber) {
                if (!is_int($pageNumber)) {
                    throw new \InvalidArgumentException('SQLite next123 overflow vacuum merge page numbers must be integers');
                }
                $sourceByPage[$pageNumber] = [
                    'source' => $source,
                    'leaf_page' => $leafPage,
                    'chain_position' => $chainPosition,
                ];
            }
        }

        $freelistRoleByPage = [];
        foreach ($vacuumPlan->nextDatabase->freelistTrunkPages() as $trunkPage) {
            $freelistRoleByPage[$trunkPage->pageNumber] = 'trunk';
            foreach ($trunkPage->leafPageNumbers as $leafPageNumber) {
                $freelistRoleByPage[$leafPageNumber] = 'leaf';
            }
        }

        $transitionByPage = [];
        foreach ($vacuumPlan->pointerMapVacuumTransitions() as $transition) {
            $transitionByPage[(int) $transition['page_number']] = $transition;
        }

        $releasedPages = $vacuumPlan->releasedOverflowPages();
        $rows = [];
        foreach ($releasedPages as $releaseIndex => $pageNumber) {
            $source = $sourceByPage[$pageNumber] ?? [
                'source' => null,
                'leaf_page' => null,
                'chain_position' => null,
            ];
            $transition = $transitionByPage[$pageNumber] ?? [];
            $truncated = ($transition['status'] ?? null) === 'truncated-from-database';
            $currentEntry = null;
            if ($database->isAutoVacuum() && !$database->isPointerMapPage($pageNumber)) {
                $currentEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            }

            $rows[] = [
                'release_index' => $releaseIndex,
                'source' => $source['source'],
                'leaf_page' => $source['leaf_page'],
                'chain_position' => $source['chain_position'],
                'page_number' => $pageNumber,
                'current_next_page' => self::overflowNextPage($database, $pageNumber),
                'current_pointer_map_type' => $currentEntry['type_name'] ?? null,
                'next_pointer_map_type' => $transition['next_type_name'] ?? null,
                'truncated_pointer_map_type' => $transition['truncated_type_name'] ?? null,
                'pointer_map_page' => $currentEntry['pointer_map_page'] ?? null,
                'freelist_role' => $truncated ? null : ($freelistRoleByPage[$pageNumber] ?? null),
                'merge_status' => $truncated ? 'truncated-after-merge' : 'merged-into-freelist',
                'materialized' => !$truncated,
                'truncated' => $truncated,
            ];
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

    private static function overflowNextPage(SQLiteDatabase $database, int $pageNumber): ?int
    {
        if ($pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        $value = unpack('N', substr($database->page($pageNumber), 0, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite next123 overflow vacuum merge could not read overflow next page');
        }

        return $value[1];
    }
}
