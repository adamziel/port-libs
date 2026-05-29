<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext157Plan
{
    /**
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $transitionRows,
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
        bool $secureDelete = false,
    ): self {
        return self::fromBasePlan(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next144TableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan): self
    {
        return new self($basePlan, self::buildTransitionRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transitionRows(): array
    {
        return $this->transitionRows;
    }

    /**
     * @return list<int>
     */
    public function severedCurrentSourceNextPointers(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter(
                $this->transitionRows,
                static fn (array $row): bool => $row['released_overflow_page'] === true
                    && $row['current_source_next_page'] !== 0
                    && $row['current_source_next_page'] !== null
                    && $row['next_materialized_next_page'] === null,
            ),
        ));
    }

    /**
     * @return list<int>
     */
    public function materializedFreeblockPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter(
                $this->transitionRows,
                static fn (array $row): bool => $row['transition_status'] === 'leaf-freeblock-preserved',
            ),
        ));
    }

    /**
     * @return list<int>
     */
    public function survivingFreelistPagesWithClearedNext(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter(
                $this->transitionRows,
                static fn (array $row): bool => $row['transition_status'] === 'surviving-free-page-cleared-next',
            ),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next157',
            'leaf_page' => $this->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'materialized_freeblock_pages' => $this->materializedFreeblockPages(),
            'surviving_freelist_pages_with_cleared_next' => $this->survivingFreelistPagesWithClearedNext(),
            'severed_current_source_next_pointers' => $this->severedCurrentSourceNextPointers(),
            'final_database_page_count' => $this->basePlan->basePlan->basePlan->nextDatabase->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->basePlan->basePlan->nextDatabase->freelistPageNumbers(),
            'transition_rows' => $this->transitionRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $rows = [];
        $releasedOverflowPages = array_fill_keys($basePlan->basePlan->basePlan->releasedOverflowPages(), true);
        foreach ($basePlan->rows as $row) {
            $kind = (string) $row['kind'];
            $pageNumber = (int) $row['page_number'];
            $materialized = (bool) $row['materialized'];
            $releasedOverflowPage = isset($releasedOverflowPages[$pageNumber]);
            $currentNextPage = $releasedOverflowPage
                && array_key_exists('source_overflow_next_page', $row)
                ? (int) $row['source_overflow_next_page']
                : null;
            $nextMaterializedNextPage = $releasedOverflowPage && $materialized
                && array_key_exists('next_overflow_next_page', $row)
                ? (int) $row['next_overflow_next_page']
                : null;

            $rows[] = [
                'kind' => $kind,
                'page_number' => $pageNumber,
                'released_overflow_page' => $releasedOverflowPage,
                'current_source_next_page' => $currentNextPage,
                'next_materialized_next_page' => $nextMaterializedNextPage,
                'current_pointer_map_type' => $row['source_pointer_map_type'],
                'next_pointer_map_type' => $row['next_pointer_map_type'],
                'current_pointer_map_parent' => $row['source_pointer_map_parent'],
                'next_pointer_map_parent' => $row['next_pointer_map_parent'],
                'current_page_hash' => $row['source_page_hash'],
                'next_page_hash' => $row['next_page_hash'],
                'materialized' => $materialized,
                'transition_status' => self::transitionStatus($kind, $materialized, $currentNextPage, $nextMaterializedNextPage, $row),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function transitionStatus(
        string $kind,
        bool $materialized,
        ?int $currentNextPage,
        ?int $nextMaterializedNextPage,
        array $row,
    ): string {
        if ($kind === 'deleted-leaf-freeblock') {
            return 'leaf-freeblock-preserved';
        }
        if (!$materialized) {
            return $currentNextPage === 0 ? 'truncated-terminal-overflow' : 'truncated-current-next-pointer';
        }
        if (($row['next_pointer_map_type'] ?? null) === 'free-page' && $nextMaterializedNextPage === 0) {
            return 'surviving-free-page-cleared-next';
        }

        return 'materialized-overflow';
    }
}
