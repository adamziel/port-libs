<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowRootRedistributePlan
{
    /**
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly SQLiteBTreeTableDeleteRebalancePlan $rebalancePlan,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param callable(int, int): list<int> $overflowPageNumbers
     */
    public static function deleteCurrentAndRedistributeNext(
        SQLiteDatabase $database,
        int $rootPageNumber,
        int $currentPageNumber,
        int $nextPageNumber,
        int $dividerIndex,
        int $rowId,
        callable $overflowPageNumbers,
        bool $secureDelete = false,
    ): self {
        $rebalancePlan = SQLiteBTreeTableDeleteRebalancePlan::deleteFromLeftAndRebalanceRight(
            $database,
            $rootPageNumber,
            $currentPageNumber,
            $nextPageNumber,
            $dividerIndex,
            $rowId,
            $overflowPageNumbers,
            $secureDelete,
        );

        if ($rebalancePlan->obsoleteOverflowPageNumbers === []) {
            throw new \InvalidArgumentException('SQLite overflow root redistribution requires obsolete overflow pages to release');
        }

        $rebalancedDatabase = self::databaseWithPageImages($database, $rebalancePlan->pageImages);
        $freePlan = $rebalancedDatabase->planPageFreeList($rebalancePlan->obsoleteOverflowPageNumbers, $secureDelete);
        $pageImages = $rebalancePlan->pageImages;
        foreach ($freePlan->pageImages() as $pageNumber => $pageImage) {
            $pageImages[$pageNumber] = $pageImage;
        }
        ksort($pageImages);

        return new self($rebalancePlan, $freePlan, $pageImages);
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
        $free = $this->freePlan->toArray();

        return [
            'action' => 'overflow-root-redistribute-current-next',
            'root_page' => $rebalance['parent_page'],
            'current_page' => $rebalance['left_page'],
            'next_page' => $rebalance['right_page'],
            'divider_index' => $rebalance['divider_index'],
            'deleted_rowid' => $rebalance['deleted_rowid'],
            'obsolete_overflow_pages' => $rebalance['obsolete_overflow_pages'],
            'after_rebalance_cells' => $rebalance['after_rebalance_cells'],
            'updated_parent_divider' => $rebalance['updated_parent_divider'],
            'moved_cell_count' => $rebalance['moved_cell_count'],
            'freed_pages' => $free['freed_page_numbers'],
            'freelist_page_count' => $free['freelist_page_count'],
            'first_freelist_trunk_page' => $free['first_freelist_trunk_page'],
            'updated_freelist_page_numbers' => $free['updated_freelist_page_numbers'],
            'updated_pointer_map_page_numbers' => $free['updated_pointer_map_page_numbers'] ?? [],
            'secure_delete_cleared_pages' => $free['cleared_page_numbers'] ?? [],
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
