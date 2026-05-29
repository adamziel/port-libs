<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeDeleteOverflowCurrentNextPlan
{
    /**
     * @param array<int, string> $currentPageImages
     * @param array<int, string> $nextPageImages
     * @param list<array<string, mixed>> $events
     */
    private function __construct(
        public readonly string $leafPageType,
        public readonly int $leafPageNumber,
        public readonly SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $current,
        public readonly SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $next,
        public readonly SQLiteDatabase $databaseAfterCurrent,
        public readonly SQLiteDatabase $databaseAfterNext,
        public readonly array $currentPageImages,
        public readonly array $nextPageImages,
        public readonly array $events,
    ) {
    }

    /**
     * @param array<string, mixed> $currentDeleteResult
     * @param list<int> $nextObsoleteOverflowPageNumbers
     */
    public static function tableLeafCurrentNext(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentDeleteResult,
        int $nextRowId,
        array $nextObsoleteOverflowPageNumbers,
        bool $secureDelete = false,
    ): self {
        $current = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $currentDeleteResult,
            $secureDelete,
        );
        $databaseAfterCurrent = self::databaseWithPageImages($database, $current->pageImages);
        $nextDeletePage = SQLiteTableLeafPage::deleteCellByRowId(
            $databaseAfterCurrent->page($leafPageNumber),
            $nextRowId,
            $databaseAfterCurrent->header->pageSize,
            $leafPageNumber === 1 ? 100 : 0,
            $databaseAfterCurrent->usablePageSize(),
            secureDelete: $secureDelete,
            overflowReader: static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount),
        );
        $next = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult(
            $databaseAfterCurrent,
            $leafPageNumber,
            [
                'page' => $nextDeletePage,
                'rowid' => $nextRowId,
                'obsolete_overflow_page_numbers' => self::normalizeOverflowPages($nextObsoleteOverflowPageNumbers),
            ],
            $secureDelete,
        );

        return self::fromPlans('table-leaf', $leafPageNumber, $database, $current, $next);
    }

    /**
     * @param array<string, mixed> $currentDeleteResult
     * @param list<mixed> $nextRecordValues
     * @param list<int> $nextObsoleteOverflowPageNumbers
     */
    public static function indexLeafCurrentNext(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentDeleteResult,
        array $nextRecordValues,
        array $nextObsoleteOverflowPageNumbers,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): self {
        $current = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $currentDeleteResult,
            $secureDelete,
            $overflowReader,
        );
        $databaseAfterCurrent = self::databaseWithPageImages($database, $current->pageImages);
        $nextDeletePage = SQLiteIndexLeafPage::deleteCellByRecordValues(
            $databaseAfterCurrent->page($leafPageNumber),
            $nextRecordValues,
            $databaseAfterCurrent->header->pageSize,
            $leafPageNumber === 1 ? 100 : 0,
            $databaseAfterCurrent->usablePageSize(),
            1,
            secureDelete: $secureDelete,
            overflowReader: $overflowReader ?? static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount),
        );
        $next = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult(
            $databaseAfterCurrent,
            $leafPageNumber,
            [
                'page' => $nextDeletePage,
                'record_values' => $nextRecordValues,
                'obsolete_overflow_page_numbers' => self::normalizeOverflowPages($nextObsoleteOverflowPageNumbers),
            ],
            $secureDelete,
            $overflowReader,
        );

        return self::fromPlans('index-leaf', $leafPageNumber, $database, $current, $next);
    }

    /**
     * @return list<int>
     */
    public function materializedPageNumbers(): array
    {
        $pageNumbers = array_fill_keys(array_merge(array_keys($this->currentPageImages), array_keys($this->nextPageImages)), true);
        $pageNumbers = array_map('intval', array_keys($pageNumbers));
        sort($pageNumbers);

        return $pageNumbers;
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPageNumbers(): array
    {
        return array_values(array_merge($this->current->obsoleteOverflowPageNumbers, $this->next->obsoleteOverflowPageNumbers));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-delete-overflow-materialization-current-next',
            'leaf_page_type' => $this->leafPageType,
            'leaf_page' => $this->leafPageNumber,
            'current' => $this->current->toArray(),
            'next' => $this->next->toArray(),
            'events' => $this->events,
            'current_page_numbers' => array_keys($this->currentPageImages),
            'next_page_numbers' => array_keys($this->nextPageImages),
            'materialized_page_numbers' => $this->materializedPageNumbers(),
            'released_overflow_pages' => $this->releasedOverflowPageNumbers(),
            'current_freelist_count' => $this->current->freePlan->freelistPageCount,
            'next_freelist_count' => $this->next->freePlan->freelistPageCount,
            'current_first_freelist_trunk_page' => $this->current->freePlan->firstFreelistTrunkPage,
            'next_first_freelist_trunk_page' => $this->next->freePlan->firstFreelistTrunkPage,
            'current_database_page_count' => $this->databaseAfterCurrent->pageCount(),
            'next_database_page_count' => $this->databaseAfterNext->pageCount(),
        ];
    }

    private static function fromPlans(
        string $leafPageType,
        int $leafPageNumber,
        SQLiteDatabase $database,
        SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $current,
        SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $next,
    ): self {
        $databaseAfterCurrent = self::databaseWithPageImages($database, $current->pageImages);
        $databaseAfterNext = self::databaseWithPageImages($databaseAfterCurrent, $next->pageImages);
        $events = [
            self::event('current-delete', $current),
            self::event('next-delete', $next),
        ];

        return new self(
            $leafPageType,
            $leafPageNumber,
            $current,
            $next,
            $databaseAfterCurrent,
            $databaseAfterNext,
            $current->pageImages,
            $next->pageImages,
            $events,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function event(string $phase, SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $plan): array
    {
        return [
            'phase' => $phase,
            'leaf_page' => $plan->leafPageNumber,
            'cell_count_before' => $plan->cellCountBefore,
            'cell_count_after' => $plan->cellCountAfter,
            'deleted_rowids' => $plan->deletedRowIds,
            'deleted_record_values' => $plan->deletedRecordValues,
            'obsolete_overflow_pages' => $plan->obsoleteOverflowPageNumbers,
            'freed_pages' => $plan->freePlan->freedPageNumbers,
            'freelist_page_count' => $plan->freePlan->freelistPageCount,
            'updated_page_numbers' => $plan->updatedPageNumbers(),
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pageCount = $database->pageCount();
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite current/next delete materialization page numbers must be one-based integers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite current/next delete materialization page image length does not match page size');
            }
            $pageCount = max($pageCount, $pageNumber);
        }

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<int>
     */
    private static function normalizeOverflowPages(array $pageNumbers): array
    {
        $normalized = [];
        foreach (array_values($pageNumbers) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite current/next delete materialization overflow page numbers must be integers');
            }
            $normalized[] = $pageNumber;
        }

        return $normalized;
    }
}
