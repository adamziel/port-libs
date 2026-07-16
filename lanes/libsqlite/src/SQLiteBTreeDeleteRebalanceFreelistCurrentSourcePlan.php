<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan
{
    /**
     * @param list<SQLiteBTreeDeleteRebalanceFreeblockApplyPlan> $steps
     * @param list<array<string, mixed>> $events
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly string $leafPageType,
        public readonly int $leafPageNumber,
        public readonly SQLiteDatabase $databaseBefore,
        public readonly SQLiteDatabase $databaseAfter,
        public readonly array $steps,
        public readonly array $events,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param list<array{rowid:int,obsolete_overflow_page_numbers:list<int>}> $deletions
     */
    public static function tableLeaf(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deletions,
        bool $secureDelete = false,
    ): self {
        if ($deletions === []) {
            throw new \InvalidArgumentException('SQLite current-source delete rebalance requires at least one table delete');
        }

        $currentDatabase = $database;
        $steps = [];
        $events = [];
        $pageImages = [];
        foreach (array_values($deletions) as $index => $delete) {
            $rowId = $delete['rowid'] ?? null;
            if (!is_int($rowId)) {
                throw new \InvalidArgumentException('SQLite current-source table delete rowid must be an integer');
            }
            $deletePage = SQLiteTableLeafPage::deleteCellByRowId(
                $currentDatabase->page($leafPageNumber),
                $rowId,
                secureDelete: $secureDelete,
            );
            $step = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult(
                $currentDatabase,
                $leafPageNumber,
                [
                    'page' => $deletePage,
                    'rowid' => $rowId,
                    'obsolete_overflow_page_numbers' => self::overflowPages($delete),
                ],
                $secureDelete,
            );
            $steps[] = $step;
            $events[] = self::event($index, 'table-delete', $step);
            $pageImages = self::mergePageImages($pageImages, $step->pageImages);
            $currentDatabase = self::databaseWithPageImages($currentDatabase, $step->pageImages);
        }

        return new self('table-leaf', $leafPageNumber, $database, $currentDatabase, $steps, $events, $pageImages);
    }

    /**
     * @param list<array{record_values:list<mixed>,obsolete_overflow_page_numbers:list<int>}> $deletions
     */
    public static function indexLeaf(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deletions,
        bool $secureDelete = false,
    ): self {
        if ($deletions === []) {
            throw new \InvalidArgumentException('SQLite current-source delete rebalance requires at least one index delete');
        }

        $currentDatabase = $database;
        $steps = [];
        $events = [];
        $pageImages = [];
        foreach (array_values($deletions) as $index => $delete) {
            $recordValues = $delete['record_values'] ?? null;
            if (!is_array($recordValues)) {
                throw new \InvalidArgumentException('SQLite current-source index delete record values must be an array');
            }
            $recordValues = array_values($recordValues);
            $deletePage = SQLiteIndexLeafPage::deleteCellByRecordValues(
                $currentDatabase->page($leafPageNumber),
                $recordValues,
                secureDelete: $secureDelete,
            );
            $step = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult(
                $currentDatabase,
                $leafPageNumber,
                [
                    'page' => $deletePage,
                    'record_values' => $recordValues,
                    'obsolete_overflow_page_numbers' => self::overflowPages($delete),
                ],
                $secureDelete,
            );
            $steps[] = $step;
            $events[] = self::event($index, 'index-delete', $step);
            $pageImages = self::mergePageImages($pageImages, $step->pageImages);
            $currentDatabase = self::databaseWithPageImages($currentDatabase, $step->pageImages);
        }

        return new self('index-leaf', $leafPageNumber, $database, $currentDatabase, $steps, $events, $pageImages);
    }

    /**
     * @return list<int>
     */
    public function materializedPageNumbers(): array
    {
        $pageNumbers = array_map('intval', array_keys($this->pageImages));
        sort($pageNumbers);

        return $pageNumbers;
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPageNumbers(): array
    {
        $released = [];
        foreach ($this->steps as $step) {
            array_push($released, ...$step->obsoleteOverflowPageNumbers);
        }

        return $released;
    }

    public function finalFreelistPageCount(): int
    {
        return $this->steps[count($this->steps) - 1]->freePlan->freelistPageCount;
    }

    /**
     * @return list<array{page_number:int,next_trunk_page:?int,leaf_page_numbers:list<int>,page_count:int,allocation_order:list<int>}>
     */
    public function finalFreelistTrunkPages(): array
    {
        return array_map(
            static fn (SQLiteFreelistTrunkPage $trunk): array => $trunk->toArray(),
            $this->databaseAfter->freelistTrunkPages(),
        );
    }

    /**
     * @return list<int>
     */
    public function finalFreelistAllocationOrder(): array
    {
        $allocationOrder = [];
        foreach ($this->databaseAfter->freelistTrunkPages() as $trunk) {
            array_push($allocationOrder, ...$trunk->allocationOrder());
        }

        return $allocationOrder;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-delete-rebalance-freelist-current-source-next84',
            'leaf_page_type' => $this->leafPageType,
            'leaf_page' => $this->leafPageNumber,
            'step_count' => count($this->steps),
            'events' => $this->events,
            'materialized_page_numbers' => $this->materializedPageNumbers(),
            'released_overflow_pages' => $this->releasedOverflowPageNumbers(),
            'final_freelist_page_count' => $this->finalFreelistPageCount(),
            'final_freelist_trunk_pages' => $this->finalFreelistTrunkPages(),
            'final_freelist_allocation_order' => $this->finalFreelistAllocationOrder(),
            'database_page_count_before' => $this->databaseBefore->pageCount(),
            'database_page_count_after' => $this->databaseAfter->pageCount(),
        ];
    }

    /**
     * @param array<string, mixed> $delete
     * @return list<int>
     */
    private static function overflowPages(array $delete): array
    {
        $pages = $delete['obsolete_overflow_page_numbers'] ?? null;
        if (!is_array($pages)) {
            throw new \InvalidArgumentException('SQLite current-source delete rebalance requires obsolete overflow page numbers');
        }

        $normalized = [];
        foreach (array_values($pages) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite current-source delete rebalance overflow page numbers must be integers');
            }
            $normalized[] = $pageNumber;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private static function event(int $index, string $phase, SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $step): array
    {
        return [
            'index' => $index,
            'phase' => $phase,
            'leaf_page' => $step->leafPageNumber,
            'cell_count_before' => $step->cellCountBefore,
            'cell_count_after' => $step->cellCountAfter,
            'deleted_rowids' => $step->deletedRowIds,
            'deleted_record_values' => $step->deletedRecordValues,
            'obsolete_overflow_pages' => $step->obsoleteOverflowPageNumbers,
            'freed_pages' => $step->freePlan->freedPageNumbers,
            'freelist_page_count' => $step->freePlan->freelistPageCount,
            'updated_page_numbers' => $step->updatedPageNumbers(),
        ];
    }

    /**
     * @param array<int, string> $left
     * @param array<int, string> $right
     * @return array<int, string>
     */
    private static function mergePageImages(array $left, array $right): array
    {
        foreach ($right as $pageNumber => $page) {
            $left[$pageNumber] = $page;
        }
        ksort($left);

        return $left;
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pageCount = $database->pageCount();
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite current-source page numbers must be one-based integers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite current-source page image length does not match page size');
            }
            $pageCount = max($pageCount, $pageNumber);
        }

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
