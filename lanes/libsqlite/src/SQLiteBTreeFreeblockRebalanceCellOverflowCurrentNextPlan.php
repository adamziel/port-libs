<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param array<int, string> $pageImages
     * @param list<int> $reusedObsoleteOverflowPages
     * @param list<array{current_page:int,next_page:int,payload_bytes:int,terminal:bool}> $replacementChainLinks
     */
    private function __construct(
        public readonly SQLiteBTreeTableDeleteRebalancePlan $rebalancePlan,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly array $overflowPageImages,
        public readonly array $pageImages,
        public readonly array $reusedObsoleteOverflowPages,
        public readonly array $replacementChainLinks,
        public readonly SQLiteDatabase $database,
    ) {
    }

    /**
     * @param callable(int, int): list<int> $overflowPageNumbers
     */
    public static function tableDeleteRebalanceThenReplaceOverflow(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $currentPageNumber,
        int $nextPageNumber,
        int $dividerIndex,
        int $deleteRowId,
        callable $overflowPageNumbers,
        string $replacementOverflowPayload,
        bool $secureDelete = false,
        bool $allowAppend = true,
    ): self {
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite freeblock rebalance current/next replacement requires overflow payload bytes');
        }

        $rebalancePlan = SQLiteBTreeTableDeleteRebalancePlan::deleteFromLeftAndRebalanceRight(
            $database,
            $parentPageNumber,
            $currentPageNumber,
            $nextPageNumber,
            $dividerIndex,
            $deleteRowId,
            $overflowPageNumbers,
            $secureDelete,
        );
        if ($rebalancePlan->obsoleteOverflowPageNumbers === []) {
            throw new \InvalidArgumentException('SQLite freeblock rebalance current/next requires obsolete overflow pages from the deleted cell');
        }

        $rebalancedDatabase = self::databaseWithPageImages($database, $rebalancePlan->pageImages);
        $freePlan = $rebalancedDatabase->planPageFreeList($rebalancePlan->obsoleteOverflowPageNumbers, $secureDelete);
        $releasedImages = $rebalancePlan->pageImages;
        foreach ($freePlan->pageImages() as $pageNumber => $pageImage) {
            $releasedImages[$pageNumber] = $pageImage;
        }

        $releasedDatabase = self::databaseWithPageImages($database, $releasedImages);
        $replacementPageCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $releasedDatabase->planOverflowPageAllocation(
            $replacementPageCount,
            $currentPageNumber,
            $allowAppend,
        );
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );

        $pageImages = $releasedImages;
        foreach ($allocationPlan->pageImages() as $pageNumber => $pageImage) {
            $pageImages[$pageNumber] = $pageImage;
        }
        foreach ($overflowPageImages as $pageNumber => $pageImage) {
            $pageImages[$pageNumber] = $pageImage;
        }
        ksort($pageImages);

        $firstOverflowPage = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if ($firstOverflowPage === null) {
            throw new \InvalidArgumentException('SQLite freeblock rebalance current/next did not allocate a replacement overflow page');
        }

        $postDatabase = self::databaseWithPageImages($database, $pageImages, $allocationPlan->databasePageCount);
        $obsolete = array_fill_keys($rebalancePlan->obsoleteOverflowPageNumbers, true);
        $reused = [];
        foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
            if (isset($obsolete[$pageNumber])) {
                $reused[] = $pageNumber;
            }
        }

        return new self(
            $rebalancePlan,
            $freePlan,
            $allocationPlan,
            $overflowPageImages,
            $pageImages,
            $reused,
            SQLiteOverflowPage::chainLinksFromDatabase($postDatabase, $firstOverflowPage, strlen($replacementOverflowPayload)),
            $postDatabase,
        );
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowPageNumbers(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
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
            'action' => 'btree-freeblock-rebalance-cell-overflow-current-next',
            'parent_page' => $this->rebalancePlan->parentPageNumber,
            'current_page' => $this->rebalancePlan->leftPageNumber,
            'next_page' => $this->rebalancePlan->rightPageNumber,
            'divider_index' => $this->rebalancePlan->dividerIndex,
            'deleted_rowid' => $this->rebalancePlan->deletedRowId,
            'deleted_payload_bytes' => $this->rebalancePlan->deletedPayloadBytes,
            'obsolete_overflow_pages' => $this->rebalancePlan->obsoleteOverflowPageNumbers,
            'replacement_overflow_pages' => $this->replacementOverflowPageNumbers(),
            'reused_obsolete_overflow_pages' => $this->reusedObsoleteOverflowPages,
            'appended_page_numbers' => $this->allocationPlan->appendedPageNumbers,
            'after_rebalance_cells' => $rebalance['after_rebalance_cells'],
            'updated_parent_divider' => $rebalance['updated_parent_divider'],
            'moved_cell_count' => $rebalance['moved_cell_count'],
            'freelist_page_count_after_release' => $this->freePlan->freelistPageCount,
            'freelist_page_count_after_replacement' => $this->allocationPlan->freelistPageCount,
            'first_freelist_trunk_page_after_replacement' => $this->allocationPlan->firstFreelistTrunkPage,
            'replacement_chain_links' => $this->replacementChainLinks,
            'allocated_pointer_map_entries' => $this->allocationPlan->allocatedPointerMapEntries(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->allocationPlan->updatedPointerMapPages),
            'rebalance' => $rebalance,
            'freelist_release' => $this->freePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, ?int $pageCount = null): SQLiteDatabase
    {
        $pageCount ??= max($database->pageCount(), $database->header->databaseSizePages);
        foreach (array_keys($pageImages) as $pageNumber) {
            $pageCount = max($pageCount, $pageNumber);
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
