<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistRebalanceTruncateCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan $rebalanceFreelistPlan,
        public readonly SQLiteFreelistTruncatePlan $truncatePlan,
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteDatabase $releasedDatabase,
        public readonly SQLiteDatabase $nextDatabase,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param list<mixed> $recordValues
     * @param callable(int, int): list<int> $overflowPageNumbers
     * @param null|callable(int, int): string $overflowReader
     */
    public static function indexDeleteRebalanceAndTruncate(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $leftPageNumber,
        int $rightPageNumber,
        int $dividerIndex,
        array $recordValues,
        callable $overflowPageNumbers,
        int $maxTruncatedPages,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
        int $textEncoding = 1,
    ): self {
        if ($maxTruncatedPages < 1) {
            throw new \InvalidArgumentException('SQLite btree freelist rebalance truncate current-source next90 requires a positive truncation limit');
        }

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
        $releasedDatabase = self::databaseWithPageImages($database, $rebalanceFreelistPlan->pageImages);
        $truncatePlan = $releasedDatabase->planFreelistTailTruncation($maxTruncatedPages);

        $pageImages = [];
        foreach ($rebalanceFreelistPlan->pageImages as $pageNumber => $page) {
            if ($pageNumber <= $truncatePlan->databasePageCount) {
                $pageImages[$pageNumber] = $page;
            }
        }
        foreach ($truncatePlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        return new self(
            $rebalanceFreelistPlan,
            $truncatePlan,
            $database,
            $releasedDatabase,
            self::databaseWithPageImages($database, $pageImages, $truncatePlan->databasePageCount),
            $pageImages,
        );
    }

    /**
     * @return list<int>
     */
    public function obsoleteOverflowPageNumbers(): array
    {
        return $this->rebalanceFreelistPlan->obsoleteOverflowPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function truncatedPageNumbers(): array
    {
        return $this->truncatePlan->truncatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function survivingFreelistPageNumbers(): array
    {
        $truncated = array_fill_keys($this->truncatedPageNumbers(), true);
        $survivors = [];
        foreach ($this->obsoleteOverflowPageNumbers() as $pageNumber) {
            if (!isset($truncated[$pageNumber])) {
                $survivors[] = $pageNumber;
            }
        }

        return $survivors;
    }

    public function materializedBytes(): string
    {
        return $this->nextDatabase->toBytes();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function overflowRebalanceTruncateRows(): array
    {
        $truncatedEntries = [];
        foreach ($this->truncatePlan->truncatedPointerMapEntries as $entry) {
            $truncatedEntries[(int) $entry['page_number']] = $entry;
        }

        $rows = [];
        foreach ($this->obsoleteOverflowPageNumbers() as $pageNumber) {
            $truncated = $pageNumber > $this->nextDatabase->pageCount();
            $sourceEntry = $this->pointerMapEntry($this->sourceDatabase, $pageNumber);
            $releasedEntry = $this->pointerMapEntry($this->releasedDatabase, $pageNumber);
            $nextEntry = $truncated ? null : $this->pointerMapEntry($this->nextDatabase, $pageNumber);
            $rows[] = [
                'page_number' => $pageNumber,
                'current_status' => 'obsolete-overflow',
                'after_rebalance_status' => 'freelist-page',
                'next_status' => $truncated ? 'truncated-from-database' : 'survives-as-freelist-page',
                'current_pointer_map_type' => $sourceEntry['type_name'] ?? null,
                'after_rebalance_pointer_map_type' => $releasedEntry['type_name'] ?? null,
                'next_pointer_map_type' => $nextEntry['type_name'] ?? null,
                'truncated_pointer_map_type' => $truncatedEntries[$pageNumber]['type_name'] ?? null,
                'current_next_page' => $this->overflowNextPage($this->sourceDatabase, $pageNumber),
                'after_rebalance_next_page' => $this->overflowNextPage($this->releasedDatabase, $pageNumber),
                'next_next_page' => $truncated ? null : $this->overflowNextPage($this->nextDatabase, $pageNumber),
                'materialized' => !$truncated,
                'truncated' => $truncated,
                'secure_deleted' => !$truncated && $this->pageIsSecureDeleted($pageNumber),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function materializedApplySummary(): array
    {
        return [
            'database_page_count' => $this->nextDatabase->pageCount(),
            'byte_length' => strlen($this->materializedBytes()),
            'first_freelist_trunk_page' => $this->nextDatabase->header->firstFreelistTrunkPage,
            'freelist_page_count' => $this->nextDatabase->header->freelistPageCount,
            'freelist_page_numbers' => $this->nextDatabase->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages),
            'omitted_truncated_page_numbers' => array_values(array_filter(
                $this->truncatedPageNumbers(),
                fn (int $pageNumber): bool => $pageNumber > $this->nextDatabase->pageCount(),
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freelist-rebalance-truncate-current-source-next90',
            'rebalance_action' => $this->rebalanceFreelistPlan->toArray()['action'],
            'obsolete_overflow_pages' => $this->obsoleteOverflowPageNumbers(),
            'surviving_freelist_pages' => $this->survivingFreelistPageNumbers(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'source_database_page_count' => $this->sourceDatabase->pageCount(),
            'released_database_page_count' => $this->releasedDatabase->pageCount(),
            'next_database_page_count' => $this->nextDatabase->pageCount(),
            'released_freelist_page_count' => $this->releasedDatabase->header->freelistPageCount,
            'next_freelist_page_count' => $this->nextDatabase->header->freelistPageCount,
            'updated_page_numbers' => array_keys($this->pageImages),
            'overflow_rebalance_truncate_rows' => $this->overflowRebalanceTruncateRows(),
            'materialized_apply' => $this->materializedApplySummary(),
            'rebalance_freelist' => $this->rebalanceFreelistPlan->toArray(),
            'truncate_plan' => $this->truncatePlan->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $database->isPointerMapPage($pageNumber) || $pageNumber > $database->pageCount()) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }

    private function overflowNextPage(SQLiteDatabase $database, int $pageNumber): ?int
    {
        if ($pageNumber > $database->pageCount()) {
            return null;
        }

        return unpack('N', substr($database->page($pageNumber), 0, 4))[1];
    }

    private function pageIsSecureDeleted(int $pageNumber): bool
    {
        if ($pageNumber > $this->nextDatabase->pageCount()) {
            return false;
        }

        return $this->nextDatabase->page($pageNumber) === str_repeat("\0", $this->nextDatabase->header->pageSize);
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, ?int $pageCountOverride = null): SQLiteDatabase
    {
        $pageCount = $pageCountOverride ?? max($database->pageCount(), $database->header->databaseSizePages);
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite btree freelist rebalance truncate page images must use one-based page numbers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite btree freelist rebalance truncate page image length does not match page size');
            }
            if ($pageCountOverride === null) {
                $pageCount = max($pageCount, $pageNumber);
            }
        }

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
