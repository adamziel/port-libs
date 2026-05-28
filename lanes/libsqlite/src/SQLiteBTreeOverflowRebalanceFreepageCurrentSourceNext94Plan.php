<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext94Plan
{
    /**
     * @param list<SQLiteBTreeDeleteRebalanceFreeblockApplyPlan|SQLiteBTreeEmptyLeafFreePlan> $steps
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
            throw new \InvalidArgumentException('SQLite overflow rebalance freepage current-source next94 requires at least one table delete');
        }

        $currentDatabase = $database;
        $steps = [];
        $events = [];
        $pageImages = [];
        foreach (array_values($deletions) as $index => $delete) {
            $rowId = $delete['rowid'] ?? null;
            if (!is_int($rowId)) {
                throw new \InvalidArgumentException('SQLite overflow rebalance freepage current-source next94 table rowid must be an integer');
            }

            $deletePage = SQLiteTableLeafPage::deleteCellByRowId(
                $currentDatabase->page($leafPageNumber),
                $rowId,
                secureDelete: $secureDelete,
            );
            $deleteResult = [
                'page' => $deletePage,
                'rowid' => $rowId,
                'obsolete_overflow_page_numbers' => self::overflowPages($delete),
            ];
            $step = self::tableStep($currentDatabase, $leafPageNumber, $deleteResult, $secureDelete);
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
            throw new \InvalidArgumentException('SQLite overflow rebalance freepage current-source next94 requires at least one index delete');
        }

        $currentDatabase = $database;
        $steps = [];
        $events = [];
        $pageImages = [];
        foreach (array_values($deletions) as $index => $delete) {
            $recordValues = $delete['record_values'] ?? null;
            if (!is_array($recordValues)) {
                throw new \InvalidArgumentException('SQLite overflow rebalance freepage current-source next94 index record values must be an array');
            }
            $recordValues = array_values($recordValues);
            $deletePage = SQLiteIndexLeafPage::deleteCellByRecordValues(
                $currentDatabase->page($leafPageNumber),
                $recordValues,
                secureDelete: $secureDelete,
            );
            $deleteResult = [
                'page' => $deletePage,
                'record_values' => $recordValues,
                'obsolete_overflow_page_numbers' => self::overflowPages($delete),
            ];
            $step = self::indexStep($currentDatabase, $leafPageNumber, $deleteResult, $secureDelete);
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
    public function releasedPageNumbers(): array
    {
        $released = [];
        foreach ($this->steps as $step) {
            array_push($released, ...$step->freePlan->freedPageNumbers);
        }

        return array_values(array_unique($released));
    }

    public function finalFreelistPageCount(): int
    {
        return $this->steps[count($this->steps) - 1]->freePlan->freelistPageCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-rebalance-freepage-current-source-next94',
            'leaf_page_type' => $this->leafPageType,
            'leaf_page' => $this->leafPageNumber,
            'step_count' => count($this->steps),
            'events' => $this->events,
            'materialized_page_numbers' => $this->materializedPageNumbers(),
            'released_pages' => $this->releasedPageNumbers(),
            'final_freelist_page_count' => $this->finalFreelistPageCount(),
            'database_page_count_before' => $this->databaseBefore->pageCount(),
            'database_page_count_after' => $this->databaseAfter->pageCount(),
        ];
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    private static function tableStep(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        bool $secureDelete,
    ): SQLiteBTreeDeleteRebalanceFreeblockApplyPlan|SQLiteBTreeEmptyLeafFreePlan {
        $header = SQLiteBTreePageHeader::parsePage($deleteResult['page'], $database->header->pageSize);
        if ($header->cellCount === 0) {
            return SQLiteBTreeEmptyLeafFreePlan::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $secureDelete);
        }

        return SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $secureDelete);
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    private static function indexStep(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        bool $secureDelete,
    ): SQLiteBTreeDeleteRebalanceFreeblockApplyPlan|SQLiteBTreeEmptyLeafFreePlan {
        $header = SQLiteBTreePageHeader::parsePage($deleteResult['page'], $database->header->pageSize);
        if ($header->cellCount === 0) {
            return SQLiteBTreeEmptyLeafFreePlan::indexLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $secureDelete);
        }

        return SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $secureDelete);
    }

    /**
     * @param array<string, mixed> $delete
     * @return list<int>
     */
    private static function overflowPages(array $delete): array
    {
        $pages = $delete['obsolete_overflow_page_numbers'] ?? null;
        if (!is_array($pages)) {
            throw new \InvalidArgumentException('SQLite overflow rebalance freepage current-source next94 requires obsolete overflow page numbers');
        }

        $normalized = [];
        foreach (array_values($pages) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite overflow rebalance freepage current-source next94 overflow page numbers must be integers');
            }
            $normalized[] = $pageNumber;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private static function event(int $index, string $phase, SQLiteBTreeDeleteRebalanceFreeblockApplyPlan|SQLiteBTreeEmptyLeafFreePlan $step): array
    {
        return [
            'index' => $index,
            'phase' => $phase,
            'leaf_page' => $step->leafPageNumber,
            'step_type' => $step instanceof SQLiteBTreeEmptyLeafFreePlan ? 'empty-leaf-free' : 'freeblock-rebalance',
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
                throw new \InvalidArgumentException('SQLite overflow rebalance freepage current-source next94 page numbers must be one-based integers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite overflow rebalance freepage current-source next94 page image length does not match page size');
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
