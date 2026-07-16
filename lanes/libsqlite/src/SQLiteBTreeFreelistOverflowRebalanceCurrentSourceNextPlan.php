<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan
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
     * @param list<int> $rowIds
     */
    public static function tableLeaf(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $rowIds,
        bool $secureDelete = false,
    ): self {
        if ($rowIds === []) {
            throw new \InvalidArgumentException('SQLite freelist overflow rebalance current-source next120 requires at least one table rowid');
        }

        $currentDatabase = $database;
        $steps = [];
        $events = [];
        $pageImages = [];
        foreach (array_values($rowIds) as $index => $rowId) {
            if (!is_int($rowId)) {
                throw new \InvalidArgumentException('SQLite freelist overflow rebalance current-source next120 table rowids must be integers');
            }

            $delete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
                $currentDatabase->page($leafPageNumber),
                $rowId,
                static fn (int $firstPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase(
                    $currentDatabase,
                    $firstPage,
                    $byteCount,
                ),
                $currentDatabase->header->pageSize,
                0,
                $currentDatabase->usablePageSize(),
                $secureDelete,
            );
            $step = self::tableStep($currentDatabase, $leafPageNumber, $delete, $secureDelete);
            $steps[] = $step;
            $events[] = self::event($index, 'table-delete-current-source', $step);
            $pageImages = self::mergePageImages($pageImages, $step->pageImages);
            $currentDatabase = self::databaseWithPageImages($currentDatabase, $step->pageImages);
        }

        return new self('table-leaf', $leafPageNumber, $database, $currentDatabase, $steps, $events, $pageImages);
    }

    /**
     * @param list<list<mixed>> $recordValuesList
     */
    public static function indexLeaf(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $recordValuesList,
        bool $secureDelete = false,
    ): self {
        if ($recordValuesList === []) {
            throw new \InvalidArgumentException('SQLite freelist overflow rebalance current-source next120 requires at least one index record');
        }

        $currentDatabase = $database;
        $steps = [];
        $events = [];
        $pageImages = [];
        foreach (array_values($recordValuesList) as $index => $recordValues) {
            if (!is_array($recordValues)) {
                throw new \InvalidArgumentException('SQLite freelist overflow rebalance current-source next120 index records must be value arrays');
            }
            $recordValues = array_values($recordValues);
            $overflowReader = static fn (int $firstPage, int $byteCount): string => self::overflowPayloadFromDatabase(
                $currentDatabase,
                $firstPage,
                $byteCount,
            );
            $delete = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
                $currentDatabase->page($leafPageNumber),
                $recordValues,
                static fn (int $firstPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase(
                    $currentDatabase,
                    $firstPage,
                    $byteCount,
                ),
                $currentDatabase->header->pageSize,
                0,
                $currentDatabase->usablePageSize(),
                1,
                $secureDelete,
                $overflowReader,
            );
            $step = self::indexStep($currentDatabase, $leafPageNumber, $delete, $secureDelete, $overflowReader);
            $steps[] = $step;
            $events[] = self::event($index, 'index-delete-current-source', $step);
            $pageImages = self::mergePageImages($pageImages, $step->pageImages);
            $currentDatabase = self::databaseWithPageImages($currentDatabase, $step->pageImages);
        }

        return new self('index-leaf', $leafPageNumber, $database, $currentDatabase, $steps, $events, $pageImages);
    }

    public function databaseAfter(): SQLiteDatabase
    {
        return $this->databaseAfter;
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
     * @return list<int>
     */
    public function finalFreelistAllocationOrder(): array
    {
        return $this->databaseAfter->freelistAllocationOrder();
    }

    /**
     * @return list<array{step:int,phase:string,step_type:string,freed_pages:list<int>,freelist_page_count:int,updated_page_numbers:list<int>}>
     */
    public function transitionRows(): array
    {
        return array_map(
            static fn (array $event): array => [
                'step' => (int) $event['index'],
                'phase' => (string) $event['phase'],
                'step_type' => (string) $event['step_type'],
                'freed_pages' => $event['freed_pages'],
                'freelist_page_count' => (int) $event['freelist_page_count'],
                'updated_page_numbers' => $event['updated_page_numbers'],
            ],
            $this->events,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freelist-overflow-rebalance-current-source-next120',
            'leaf_page_type' => $this->leafPageType,
            'leaf_page' => $this->leafPageNumber,
            'step_count' => count($this->steps),
            'events' => $this->events,
            'current_source_transition_rows' => $this->transitionRows(),
            'derived_overflow_page_numbers' => array_map(
                static fn (array $event): array => $event['obsolete_overflow_pages'],
                $this->events,
            ),
            'materialized_page_numbers' => $this->materializedPageNumbers(),
            'released_pages' => $this->releasedPageNumbers(),
            'final_freelist_page_count' => $this->finalFreelistPageCount(),
            'final_freelist_allocation_order' => $this->finalFreelistAllocationOrder(),
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
        callable $overflowReader,
    ): SQLiteBTreeDeleteRebalanceFreeblockApplyPlan|SQLiteBTreeEmptyLeafFreePlan {
        $header = SQLiteBTreePageHeader::parsePage($deleteResult['page'], $database->header->pageSize);
        if ($header->cellCount === 0) {
            return SQLiteBTreeEmptyLeafFreePlan::indexLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $secureDelete);
        }

        return SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $secureDelete,
            $overflowReader,
        );
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
            'write_order_page_numbers' => $step instanceof SQLiteBTreeDeleteRebalanceFreeblockApplyPlan
                ? $step->writeOrderPageNumbers
                : $step->updatedPageNumbers(),
        ];
    }

    private static function overflowPayloadFromDatabase(SQLiteDatabase $database, int $firstPageNumber, int $byteCount): string
    {
        $payload = '';
        $remaining = $byteCount;
        foreach (SQLiteOverflowPage::pageNumbersFromDatabase($database, $firstPageNumber, $byteCount) as $pageNumber) {
            $page = $database->page($pageNumber);
            $chunk = min($remaining, $database->usablePageSize() - 4);
            $payload .= substr($page, 4, $chunk);
            $remaining -= $chunk;
        }

        return $payload;
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
                throw new \InvalidArgumentException('SQLite freelist overflow rebalance current-source next120 page numbers must be one-based integers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite freelist overflow rebalance current-source next120 page image length does not match page size');
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
