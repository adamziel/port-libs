<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePageMoveFreelistRebalanceCurrentSourceNext85Plan
{
    /**
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan $rebalanceFreelistPlan,
        public readonly SQLiteBTreePageMovePlan $pageMovePlan,
        public readonly array $pageImages,
        public readonly SQLiteDatabase $database,
    ) {
    }

    /**
     * @param list<mixed> $recordValues
     * @param callable(int, int): list<int> $overflowPageNumbers
     * @param null|callable(int, int): string $overflowReader
     */
    public static function deleteRebalanceFreeAndMoveLastIndexLeaf(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $leftPageNumber,
        int $rightPageNumber,
        int $dividerIndex,
        array $recordValues,
        callable $overflowPageNumbers,
        int $sourcePageNumber,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
        int $textEncoding = 1,
    ): self {
        $rebalanceFreelistPlan = SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan::deleteFromLeftAndRebalanceRight(
            $database,
            $parentPageNumber,
            $leftPageNumber,
            $rightPageNumber,
            $dividerIndex,
            $recordValues,
            $overflowPageNumbers,
            $secureDelete,
            $overflowReader,
            $textEncoding,
        );

        $pageMovePlan = SQLiteBTreePageMovePlan::moveLastIndexLeafIntoFreelistSlot(
            $rebalanceFreelistPlan->database,
            $sourcePageNumber,
            $parentPageNumber,
        );

        $pageImages = $rebalanceFreelistPlan->pageImages;
        foreach ($pageMovePlan->pageImages as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        return new self(
            $rebalanceFreelistPlan,
            $pageMovePlan,
            $pageImages,
            self::databaseWithPageImages($database, $pageImages, $pageMovePlan->databasePageCount),
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
        $rebalance = $this->rebalanceFreelistPlan->toArray();
        $move = $this->pageMovePlan->toArray();

        return [
            'action' => 'btree-page-move-freelist-rebalance-current-source-next85',
            'parent_page' => $rebalance['parent_page'],
            'left_page' => $rebalance['left_page'],
            'right_page' => $rebalance['right_page'],
            'obsolete_overflow_pages' => $this->rebalanceFreelistPlan->obsoleteOverflowPageNumbers,
            'freed_pages_before_move' => $this->rebalanceFreelistPlan->freePlan->freedPageNumbers,
            'moved_source_page' => $this->pageMovePlan->sourcePageNumber,
            'moved_target_page' => $this->pageMovePlan->targetPageNumber,
            'database_page_count' => $this->pageMovePlan->databasePageCount,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_values(array_unique(array_merge(
                $rebalance['updated_pointer_map_page_numbers'],
                $move['updated_pointer_map_page_numbers'],
            ))),
            'rebalance_freelist' => $rebalance,
            'page_move' => $move,
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, int $pageCount): SQLiteDatabase
    {
        $pages = [];
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
