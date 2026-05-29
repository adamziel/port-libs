<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan
{
    private function __construct(
        public readonly SQLiteBTreeOverflowCellReuseDeleteApplyPlan $cellApplyPlan,
        public readonly SQLiteDatabase $databaseBefore,
        public readonly SQLiteDatabase $databaseAfter,
    ) {
    }

    /**
     * @param callable(int, int): list<int> $overflowPageNumbers
     */
    public static function tableLeafCurrentSource(
        SQLiteDatabase $database,
        int $leafPageNumber,
        int $deleteRowId,
        int $replacementRowId,
        string $replacementRecordPayload,
        callable $overflowPageNumbers,
        bool $secureDelete = false,
    ): self {
        return self::fromCellApplyPlan(
            $database,
            SQLiteBTreeOverflowCellReuseDeleteApplyPlan::tableCell(
                $database,
                $leafPageNumber,
                $database->page($leafPageNumber),
                $deleteRowId,
                $replacementRowId,
                $replacementRecordPayload,
                $overflowPageNumbers,
                $secureDelete,
            ),
        );
    }

    /**
     * @param list<mixed> $deleteRecordValues
     * @param list<mixed> $replacementRecordValues
     * @param callable(int, int): list<int> $overflowPageNumbers
     */
    public static function indexLeafCurrentSource(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteRecordValues,
        array $replacementRecordValues,
        callable $overflowPageNumbers,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): self {
        return self::fromCellApplyPlan(
            $database,
            SQLiteBTreeOverflowCellReuseDeleteApplyPlan::indexCell(
                $database,
                $leafPageNumber,
                $database->page($leafPageNumber),
                $deleteRecordValues,
                $replacementRecordValues,
                $overflowPageNumbers,
                $secureDelete,
                $overflowReader,
            ),
        );
    }

    /**
     * @return list<int>
     */
    public function materializedPageNumbers(): array
    {
        return $this->cellApplyPlan->updatedPageNumbers();
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPageNumbers(): array
    {
        return $this->cellApplyPlan->obsoleteOverflowPageNumbers;
    }

    /**
     * @return list<array{page_number:int,before_pointer_map_type:?string,after_pointer_map_type:?string,before_pointer_map_parent:?int,after_pointer_map_parent:?int,before_freelist_member:bool,after_freelist_member:bool,secure_delete_cleared:bool}>
     */
    public function releasedPageRows(): array
    {
        $beforeFreelist = array_fill_keys($this->databaseBefore->freelistPageNumbers(), true);
        $afterFreelist = array_fill_keys($this->databaseAfter->freelistPageNumbers(), true);
        $cleared = array_fill_keys($this->cellApplyPlan->freePlan->clearedPageNumbers, true);
        $rows = [];

        foreach ($this->releasedOverflowPageNumbers() as $pageNumber) {
            $beforeEntry = $this->databaseBefore->pointerMapEntryForPage($pageNumber);
            $afterEntry = $this->databaseAfter->pointerMapEntryForPage($pageNumber);
            $rows[] = [
                'page_number' => $pageNumber,
                'before_pointer_map_type' => $beforeEntry?->typeName(),
                'after_pointer_map_type' => $afterEntry?->typeName(),
                'before_pointer_map_parent' => $beforeEntry?->parentPageNumber,
                'after_pointer_map_parent' => $afterEntry?->parentPageNumber,
                'before_freelist_member' => isset($beforeFreelist[$pageNumber]),
                'after_freelist_member' => isset($afterFreelist[$pageNumber]),
                'secure_delete_cleared' => isset($cleared[$pageNumber]),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-rebalance-cell-apply-current-source-next107',
            'cell_apply' => $this->cellApplyPlan->toArray(),
            'materialized_page_numbers' => $this->materializedPageNumbers(),
            'released_overflow_pages' => $this->releasedOverflowPageNumbers(),
            'released_page_rows' => $this->releasedPageRows(),
            'database_page_count_before' => $this->databaseBefore->pageCount(),
            'database_page_count_after' => $this->databaseAfter->pageCount(),
            'freelist_page_count_before' => $this->databaseBefore->header->freelistPageCount,
            'freelist_page_count_after' => $this->databaseAfter->header->freelistPageCount,
            'first_freelist_trunk_page_after' => $this->databaseAfter->header->firstFreelistTrunkPage,
        ];
    }

    private static function fromCellApplyPlan(SQLiteDatabase $database, SQLiteBTreeOverflowCellReuseDeleteApplyPlan $plan): self
    {
        return new self($plan, $database, self::databaseWithPageImages($database, $plan->pageImages));
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pageCount = $database->pageCount();
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite overflow rebalance cell apply page numbers must be one-based integers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite overflow rebalance cell apply page image length does not match page size');
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
