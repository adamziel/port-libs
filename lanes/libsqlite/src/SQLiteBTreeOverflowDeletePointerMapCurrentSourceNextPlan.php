<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowDeletePointerMapCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $pointerMapTransitions
     */
    private function __construct(
        public readonly SQLiteBTreeDeleteOverflowCurrentNextPlan $deletePlan,
        public readonly array $pointerMapTransitions,
    ) {
    }

    public static function tableLeafCurrentNext(
        SQLiteDatabase $database,
        int $leafPageNumber,
        int $currentRowId,
        int $nextRowId,
        bool $secureDelete = false,
    ): self {
        $currentDelete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
            $database->page($leafPageNumber),
            $currentRowId,
            self::overflowPageNumbers($database),
            $database->header->pageSize,
            $leafPageNumber === 1 ? 100 : 0,
            $database->usablePageSize(),
            $secureDelete,
        );

        $deletePlan = SQLiteBTreeDeleteOverflowCurrentNextPlan::tableLeafCurrentNext(
            $database,
            $leafPageNumber,
            $currentDelete,
            $nextRowId,
            self::nextTableOverflowPages($database, $leafPageNumber, $currentDelete, $nextRowId, $secureDelete),
            $secureDelete,
        );

        return new self($deletePlan, self::pointerMapTransitions($database, $deletePlan));
    }

    /**
     * @param list<mixed> $currentRecordValues
     * @param list<mixed> $nextRecordValues
     */
    public static function indexLeafCurrentNext(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentRecordValues,
        array $nextRecordValues,
        bool $secureDelete = false,
        int $textEncoding = 1,
        ?callable $overflowReader = null,
    ): self {
        $currentDelete = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
            $database->page($leafPageNumber),
            $currentRecordValues,
            self::overflowPageNumbers($database),
            $database->header->pageSize,
            $leafPageNumber === 1 ? 100 : 0,
            $database->usablePageSize(),
            $textEncoding,
            $secureDelete,
            $overflowReader,
        );

        $deletePlan = SQLiteBTreeDeleteOverflowCurrentNextPlan::indexLeafCurrentNext(
            $database,
            $leafPageNumber,
            $currentDelete,
            $nextRecordValues,
            self::nextIndexOverflowPages($database, $leafPageNumber, $currentDelete, $nextRecordValues, $secureDelete, $textEncoding, $overflowReader),
            $secureDelete,
            $overflowReader,
        );

        return new self($deletePlan, self::pointerMapTransitions($database, $deletePlan));
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPageNumbers(): array
    {
        return $this->deletePlan->releasedOverflowPageNumbers();
    }

    /**
     * @return list<int>
     */
    public function materializedPageNumbers(): array
    {
        return $this->deletePlan->materializedPageNumbers();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-delete-pointermap-current-source-next86',
            'leaf_page_type' => $this->deletePlan->leafPageType,
            'leaf_page' => $this->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->releasedOverflowPageNumbers(),
            'materialized_page_numbers' => $this->materializedPageNumbers(),
            'current_freelist_count' => $this->deletePlan->current->freePlan->freelistPageCount,
            'next_freelist_count' => $this->deletePlan->next->freePlan->freelistPageCount,
            'pointer_map_transitions' => $this->pointerMapTransitions,
            'delete' => $this->deletePlan->toArray(),
        ];
    }

    /**
     * @return callable(int, int): list<int>
     */
    private static function overflowPageNumbers(SQLiteDatabase $database): callable
    {
        return static fn (int $firstOverflowPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase(
            $database,
            $firstOverflowPage,
            $byteCount,
        );
    }

    /**
     * @param array<string, mixed> $currentDelete
     * @return list<int>
     */
    private static function nextTableOverflowPages(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentDelete,
        int $nextRowId,
        bool $secureDelete,
    ): array {
        $afterCurrent = self::databaseWithPageImages($database, self::pageImagesFromTableDelete($database, $leafPageNumber, $currentDelete, $secureDelete));
        $nextDelete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
            $afterCurrent->page($leafPageNumber),
            $nextRowId,
            self::overflowPageNumbers($afterCurrent),
            $afterCurrent->header->pageSize,
            $leafPageNumber === 1 ? 100 : 0,
            $afterCurrent->usablePageSize(),
            $secureDelete,
        );

        return $nextDelete['obsolete_overflow_page_numbers'];
    }

    /**
     * @param array<string, mixed> $currentDelete
     * @param list<mixed> $nextRecordValues
     * @return list<int>
     */
    private static function nextIndexOverflowPages(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentDelete,
        array $nextRecordValues,
        bool $secureDelete,
        int $textEncoding,
        ?callable $overflowReader,
    ): array {
        $afterCurrent = self::databaseWithPageImages($database, self::pageImagesFromIndexDelete($database, $leafPageNumber, $currentDelete, $secureDelete, $overflowReader));
        $nextDelete = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
            $afterCurrent->page($leafPageNumber),
            $nextRecordValues,
            self::overflowPageNumbers($afterCurrent),
            $afterCurrent->header->pageSize,
            $leafPageNumber === 1 ? 100 : 0,
            $afterCurrent->usablePageSize(),
            $textEncoding,
            $secureDelete,
            $overflowReader,
        );

        return $nextDelete['obsolete_overflow_page_numbers'];
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @return array<int, string>
     */
    private static function pageImagesFromTableDelete(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, bool $secureDelete): array
    {
        return SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $secureDelete,
        )->pageImages;
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @return array<int, string>
     */
    private static function pageImagesFromIndexDelete(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, bool $secureDelete, ?callable $overflowReader): array
    {
        return SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $secureDelete,
            $overflowReader,
        )->pageImages;
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pageCount = $database->pageCount();
        foreach (array_keys($pageImages) as $pageNumber) {
            $pageCount = max($pageCount, $pageNumber);
        }

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pointerMapTransitions(SQLiteDatabase $current, SQLiteBTreeDeleteOverflowCurrentNextPlan $plan): array
    {
        if (!$current->isAutoVacuum()) {
            return [];
        }

        $transitions = [];
        foreach ($plan->releasedOverflowPageNumbers() as $pageNumber) {
            $currentEntry = $current->pointerMapEntryForPage($pageNumber);
            $afterCurrentEntry = $plan->databaseAfterCurrent->pointerMapEntryForPage($pageNumber);
            $afterNextEntry = $plan->databaseAfterNext->pointerMapEntryForPage($pageNumber);
            $transitions[] = [
                'page_number' => $pageNumber,
                'current_type_name' => $currentEntry->typeName(),
                'current_parent_page_number' => $currentEntry->parentPageNumber,
                'after_current_type_name' => $afterCurrentEntry->typeName(),
                'after_current_parent_page_number' => $afterCurrentEntry->parentPageNumber,
                'after_next_type_name' => $afterNextEntry->typeName(),
                'after_next_parent_page_number' => $afterNextEntry->parentPageNumber,
            ];
        }

        return $transitions;
    }
}
