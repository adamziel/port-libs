<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNext122Plan
{
    /**
     * @param list<array<string, mixed>> $deleteResults
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        public readonly array $deleteResults,
        public readonly array $rows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResults,
        int $maxTruncatedPages,
        bool $secureDelete = false,
        bool $clearCoalescedFragments = true,
    ): self {
        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage(
            $database,
            $leafPageNumber,
            $clearCoalescedFragments,
        );
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $vacuumPlan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
            $coalescedDatabase,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
        );

        return new self(
            $database,
            $coalescePlan,
            $vacuumPlan,
            $deleteResults,
            self::rows($database, $coalescePlan, $vacuumPlan, $deleteResults),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function overflowFreeblockVacuumRows(): array
    {
        return $this->rows;
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
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        $pages = array_merge(array_keys($this->coalescePlan->pageImages()), array_keys($this->vacuumPlan->pageImages));
        $pages = array_values(array_unique(array_map('intval', $pages)));
        sort($pages);

        return $pages;
    }

    /**
     * @return array<string, mixed>
     */
    public function materializedApplySummary(): array
    {
        return $this->vacuumPlan->materializedApplySummary();
    }

    public function materializedDatabase(): SQLiteDatabase
    {
        return $this->vacuumPlan->materializedDatabase();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freeblock-vacuum-current-source-next122',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'fragmented_bytes_before' => $this->coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->coalescePlan->fragmentedBytesAfter,
            'freeblock_count_before' => count($this->coalescePlan->beforeFreeblocks),
            'freeblock_count_after' => count($this->coalescePlan->afterFreeblocks),
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'surviving_freed_pointer_map_pages' => $this->survivingFreedPointerMapPages(),
            'truncated_freed_pointer_map_pages' => $this->truncatedFreedPointerMapPages(),
            'final_database_page_count' => $this->vacuumPlan->finalDatabasePageCount(),
            'final_first_freelist_trunk_page' => $this->vacuumPlan->finalFirstFreelistTrunkPage(),
            'final_freelist_page_count' => $this->vacuumPlan->finalFreelistPageCount(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'overflow_freeblock_vacuum_current_source_next122' => $this->rows,
            'pointer_map_vacuum_transitions' => $this->vacuumPlan->pointerMapVacuumTransitions(),
            'materialized_apply' => $this->materializedApplySummary(),
            'coalesce_plan' => $this->coalescePlan->toArray(),
            'vacuum_plan' => $this->vacuumPlan->toArray(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     * @return list<array<string, mixed>>
     */
    private static function rows(
        SQLiteDatabase $database,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        array $deleteResults,
    ): array {
        $transitionByPage = [];
        foreach ($vacuumPlan->pointerMapVacuumTransitions() as $transition) {
            $transitionByPage[(int) $transition['page_number']] = $transition;
        }

        $rows = [];
        foreach (array_values($deleteResults) as $deleteIndex => $deleteResult) {
            if (!is_array($deleteResult)) {
                throw new \InvalidArgumentException('SQLite overflow freeblock vacuum next122 delete results must be arrays');
            }

            $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
            if (!is_array($pages)) {
                throw new \InvalidArgumentException('SQLite overflow freeblock vacuum next122 requires obsolete overflow pages');
            }

            foreach (array_values($pages) as $chainPosition => $pageNumber) {
                if (!is_int($pageNumber)) {
                    throw new \InvalidArgumentException('SQLite overflow freeblock vacuum next122 overflow pages must be integers');
                }

                $currentEntry = $database->isAutoVacuum() && !$database->isPointerMapPage($pageNumber)
                    ? $database->pointerMapEntryForPage($pageNumber)->toArray()
                    : null;
                $transition = $transitionByPage[$pageNumber] ?? null;

                $rows[] = [
                    'source' => self::sourceLabel($deleteResult, $deleteIndex),
                    'leaf_page' => $coalescePlan->pageNumber,
                    'chain_position' => $chainPosition,
                    'page_number' => $pageNumber,
                    'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
                    'fragmented_bytes_before' => $coalescePlan->fragmentedBytesBefore,
                    'fragmented_bytes_after' => $coalescePlan->fragmentedBytesAfter,
                    'freeblock_count_before' => count($coalescePlan->beforeFreeblocks),
                    'freeblock_count_after' => count($coalescePlan->afterFreeblocks),
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
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite overflow freeblock vacuum next122 could not read uint32');
        }

        return $value[1];
    }
}
