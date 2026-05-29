<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeRebalancePointerMapCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $pointerMapTransitions
     */
    private function __construct(
        public readonly SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan $rebalanceFreelistPlan,
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteDatabase $deleteDatabase,
        public readonly SQLiteDatabase $rebalanceDatabase,
        public readonly SQLiteDatabase $nextDatabase,
        public readonly array $pointerMapTransitions,
    ) {
    }

    /**
     * @param list<mixed> $recordValues
     * @param callable(int, int): list<int> $overflowPageNumbers
     * @param null|callable(int, int): string $overflowReader
     */
    public static function indexDeleteRebalanceCurrentSource(
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
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite rebalance pointer-map current-source next88 requires auto-vacuum pointer-map pages');
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
            throw new \InvalidArgumentException('SQLite rebalance pointer-map current-source next88 requires an overflow-backed deleted cell');
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

        return new self(
            $rebalanceFreelistPlan,
            $database,
            $deleteDatabase,
            $rebalanceDatabase,
            $rebalanceFreelistPlan->database,
            self::pointerMapTransitions(
                $database,
                $deleteDatabase,
                $rebalanceDatabase,
                $rebalanceFreelistPlan->database,
                array_values(array_unique(array_merge(
                    [$leftPageNumber, $rightPageNumber],
                    $obsoleteOverflowPages,
                    $rebalancePlan->updatedPageNumbers(),
                    $rebalanceFreelistPlan->freePlan->freedPageNumbers,
                ))),
            ),
        );
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return $this->rebalanceFreelistPlan->updatedPageNumbers();
    }

    /**
     * @return list<int>
     */
    public function changedPointerMapPageNumbers(): array
    {
        return array_values(array_map(
            static fn (array $transition): int => $transition['page_number'],
            array_filter(
                $this->pointerMapTransitions,
                static fn (array $transition): bool => $transition['current_type_name'] !== $transition['next_type_name']
                    || $transition['current_parent_page_number'] !== $transition['next_parent_page_number'],
            ),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $rebalance = $this->rebalanceFreelistPlan->toArray();

        return [
            'action' => 'btree-rebalance-pointermap-current-source-next88',
            'parent_page' => $rebalance['parent_page'],
            'left_page' => $rebalance['left_page'],
            'right_page' => $rebalance['right_page'],
            'divider_index' => $rebalance['divider_index'],
            'obsolete_overflow_pages' => $this->rebalanceFreelistPlan->obsoleteOverflowPageNumbers,
            'freed_pages' => $this->rebalanceFreelistPlan->freePlan->freedPageNumbers,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => $rebalance['updated_pointer_map_page_numbers'],
            'changed_pointer_map_pages' => $this->changedPointerMapPageNumbers(),
            'pointer_map_transitions' => $this->pointerMapTransitions,
            'rebalance_freelist' => $rebalance,
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
            throw new \InvalidArgumentException('SQLite rebalance pointer-map current-source next88 requires obsolete overflow page numbers');
        }

        $normalized = [];
        foreach (array_values($pages) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite rebalance pointer-map current-source next88 overflow page numbers must be integers');
            }
            $normalized[] = $pageNumber;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string, mixed>>
     */
    private static function pointerMapTransitions(
        SQLiteDatabase $source,
        SQLiteDatabase $delete,
        SQLiteDatabase $rebalance,
        SQLiteDatabase $next,
        array $pageNumbers,
    ): array {
        sort($pageNumbers);
        $transitions = [];
        foreach ($pageNumbers as $pageNumber) {
            if ($pageNumber === 2) {
                continue;
            }

            $sourceEntry = $source->pointerMapEntryForPage($pageNumber);
            $deleteEntry = $delete->pointerMapEntryForPage($pageNumber);
            $rebalanceEntry = $rebalance->pointerMapEntryForPage($pageNumber);
            $nextEntry = $next->pointerMapEntryForPage($pageNumber);
            $transitions[] = [
                'page_number' => $pageNumber,
                'current_type_name' => $sourceEntry->typeName(),
                'current_parent_page_number' => $sourceEntry->parentPageNumber,
                'after_delete_type_name' => $deleteEntry->typeName(),
                'after_delete_parent_page_number' => $deleteEntry->parentPageNumber,
                'after_rebalance_type_name' => $rebalanceEntry->typeName(),
                'after_rebalance_parent_page_number' => $rebalanceEntry->parentPageNumber,
                'next_type_name' => $nextEntry->typeName(),
                'next_parent_page_number' => $nextEntry->parentPageNumber,
                'changed_by_rebalance' => $sourceEntry->type !== $rebalanceEntry->type
                    || $sourceEntry->parentPageNumber !== $rebalanceEntry->parentPageNumber,
                'changed_by_freelist_release' => $rebalanceEntry->type !== $nextEntry->type
                    || $rebalanceEntry->parentPageNumber !== $nextEntry->parentPageNumber,
            ];
        }

        return $transitions;
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
