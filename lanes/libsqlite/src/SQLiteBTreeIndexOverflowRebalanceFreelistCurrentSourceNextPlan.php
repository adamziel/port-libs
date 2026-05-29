<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan
{
    /**
     * @param list<int> $obsoleteOverflowPageNumbers
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly SQLiteBTreeIndexLeafBalanceApplyPlan $rebalancePlan,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $obsoleteOverflowPageNumbers,
        public readonly array $pageImages,
        public readonly SQLiteDatabase $database,
    ) {
    }

    /**
     * @param list<mixed> $recordValues
     * @param callable(int, int): list<int> $overflowPageNumbers
     * @param null|callable(int, int): string $overflowReader
     */
    public static function deleteFromLeftAndRebalanceRight(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $leftPageNumber,
        int $rightPageNumber,
        int $dividerIndex,
        array $recordValues,
        callable $overflowPageNumbers,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
        int $textEncoding = 1,
    ): self {
        if ($recordValues === []) {
            throw new \InvalidArgumentException('SQLite index overflow rebalance current-source next82 requires record values');
        }

        $pageSize = $database->header->pageSize;
        $deleteResult = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
            $database->page($leftPageNumber),
            $recordValues,
            $overflowPageNumbers,
            $pageSize,
            $leftPageNumber === 1 ? 100 : 0,
            $database->usablePageSize(),
            $textEncoding,
            $secureDelete,
            $overflowReader,
        );
        $obsoleteOverflowPages = self::obsoleteOverflowPages($deleteResult);
        if ($obsoleteOverflowPages === []) {
            throw new \InvalidArgumentException('SQLite index overflow rebalance current-source next82 requires an overflow-backed deleted cell');
        }

        $deleteDatabase = self::databaseWithPageImages($database, [$leftPageNumber => $deleteResult['page']]);
        $rebalancePlan = SQLiteBTreeIndexLeafBalanceApplyPlan::apply(
            $deleteDatabase,
            $parentPageNumber,
            $leftPageNumber,
            $rightPageNumber,
            $dividerIndex,
            $textEncoding,
        );
        $rebalanceDatabase = self::databaseWithPageImages($database, $rebalancePlan->pageImages);
        $freePlan = $rebalanceDatabase->planPageFreeList($obsoleteOverflowPages, $secureDelete);

        $pageImages = $rebalancePlan->pageImages;
        foreach ($freePlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        return new self(
            $rebalancePlan,
            $freePlan,
            $obsoleteOverflowPages,
            $pageImages,
            self::databaseWithPageImages($database, $pageImages),
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
            'action' => 'btree-index-overflow-rebalance-freelist-current-source-next82',
            'parent_page' => $rebalance['parent_page'],
            'left_page' => $rebalance['left_page'],
            'right_page' => $rebalance['right_page'],
            'divider_index' => $rebalance['divider_index'],
            'after_rebalance_cells' => $rebalance['after_cells'],
            'updated_parent_divider' => $rebalance['updated_parent_divider'],
            'moved_cell_count' => $rebalance['moved_cell_count'],
            'obsolete_overflow_pages' => $this->obsoleteOverflowPageNumbers,
            'freed_pages' => $this->freePlan->freedPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'freed_pointer_map_entries' => $this->freePlan->freedPointerMapEntries,
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
            'rebalance' => $rebalance,
            'freelist_release' => $this->freePlan->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @return list<int>
     */
    private static function obsoleteOverflowPages(array $deleteResult): array
    {
        $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
        if (!is_array($pages)) {
            throw new \InvalidArgumentException('SQLite index overflow rebalance current-source next82 requires obsolete overflow page numbers');
        }

        $normalized = [];
        $seen = [];
        foreach (array_values($pages) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite index overflow rebalance current-source next82 overflow page numbers must be integers');
            }
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite index overflow rebalance current-source next82 overflow page {$pageNumber} appears more than once");
            }
            $seen[$pageNumber] = true;
            $normalized[] = $pageNumber;
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        $pageCount = max($database->pageCount(), $database->header->databaseSizePages);
        foreach (array_keys($pageImages) as $pageNumber) {
            $pageCount = max($pageCount, $pageNumber);
        }

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? (
                $pageNumber <= $database->pageCount()
                    ? $database->page($pageNumber)
                    : str_repeat("\0", $database->header->pageSize)
            );
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
