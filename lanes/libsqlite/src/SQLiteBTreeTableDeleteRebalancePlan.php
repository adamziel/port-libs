<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeTableDeleteRebalancePlan
{
    /**
     * @param list<int> $obsoleteOverflowPageNumbers
     * @param list<array{rowid:int,payload:string}> $remainingLeftEntries
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $parentPageNumber,
        public readonly int $leftPageNumber,
        public readonly int $rightPageNumber,
        public readonly int $dividerIndex,
        public readonly int $deletedRowId,
        public readonly int $deletedPayloadBytes,
        public readonly array $obsoleteOverflowPageNumbers,
        public readonly int $beforeLeftCellCount,
        public readonly int $afterDeleteLeftCellCount,
        public readonly array $remainingLeftEntries,
        public readonly SQLiteBTreeTableLeafBalanceApplyPlan $rebalancePlan,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param callable(int, int): list<int> $overflowPageNumbers
     */
    public static function deleteFromLeftAndRebalanceRight(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $leftPageNumber,
        int $rightPageNumber,
        int $dividerIndex,
        int $rowId,
        callable $overflowPageNumbers,
        bool $secureDelete = false,
    ): self {
        $pageSize = $database->header->pageSize;
        $usableSize = $database->usablePageSize();
        $leftPage = $database->page($leftPageNumber);
        $leftHeader = SQLiteBTreePageHeader::parsePage($leftPage, $pageSize, $leftPageNumber === 1 ? 100 : 0);
        if ($leftHeader->pageType !== 'table-leaf') {
            throw new \InvalidArgumentException('SQLite table delete rebalance requires a table-leaf delete page');
        }

        $deleted = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
            $leftPage,
            $rowId,
            $overflowPageNumbers,
            $pageSize,
            $leftPageNumber === 1 ? 100 : 0,
            $usableSize,
            $secureDelete,
        );
        $deletedLeftPage = $deleted['page'];
        $deletedHeader = SQLiteBTreePageHeader::parsePage($deletedLeftPage, $pageSize, $leftPageNumber === 1 ? 100 : 0);
        if ($deletedHeader->cellCount === 0) {
            throw new \InvalidArgumentException('SQLite table delete rebalance keeps non-empty leaves; empty leaves should use a merge/free plan');
        }

        $rebalanceDatabase = self::databaseWithPageImages($database, [$leftPageNumber => $deletedLeftPage]);
        $rebalancePlan = SQLiteBTreeTableLeafBalanceApplyPlan::apply(
            $rebalanceDatabase,
            $parentPageNumber,
            $leftPageNumber,
            $rightPageNumber,
            $dividerIndex,
        );

        return new self(
            $parentPageNumber,
            $leftPageNumber,
            $rightPageNumber,
            $dividerIndex,
            $rowId,
            $deleted['deleted_local_payload_length'],
            $deleted['obsolete_overflow_page_numbers'],
            $leftHeader->cellCount,
            $deletedHeader->cellCount,
            $rebalancePlan->leftEntries,
            $rebalancePlan,
            $rebalancePlan->pageImages,
        );
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $rebalance = $this->rebalancePlan->toArray();

        return [
            'action' => 'table-delete-rebalance-apply',
            'parent_page' => $this->parentPageNumber,
            'left_page' => $this->leftPageNumber,
            'right_page' => $this->rightPageNumber,
            'divider_index' => $this->dividerIndex,
            'deleted_rowid' => $this->deletedRowId,
            'deleted_payload_bytes' => $this->deletedPayloadBytes,
            'obsolete_overflow_pages' => $this->obsoleteOverflowPageNumbers,
            'before_left_cell_count' => $this->beforeLeftCellCount,
            'after_delete_left_cell_count' => $this->afterDeleteLeftCellCount,
            'after_rebalance_cells' => $rebalance['after_cells'],
            'updated_parent_divider' => $rebalance['updated_parent_divider'],
            'moved_cell_count' => $rebalance['moved_cell_count'],
            'updated_page_numbers' => $this->updatedPageNumbers(),
        ];
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
}
