<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreeblockCurrentNextPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param array<int, string> $pageImages
     * @param list<int> $reusedReleasedPageNumbers
     * @param list<array{current_page:int,next_page:int,payload_bytes:int,terminal:bool}> $chainLinks
     */
    private function __construct(
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly array $overflowPageImages,
        public readonly array $pageImages,
        public readonly array $reusedReleasedPageNumbers,
        public readonly array $chainLinks,
        public readonly SQLiteDatabase $database,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function replaceFromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
        bool $allowAppend = true,
    ): self {
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite overflow freeblock current/next replacement requires overflow payload bytes');
        }

        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, $deleteResults, $secureDelete);
        $releasedDatabase = self::databaseWithPageImages($database, $releasePlan->freePlan->pageImages());
        $replacementPageCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $releasedDatabase->planOverflowPageAllocation(
            $replacementPageCount,
            $parentBtreePageNumber,
            $allowAppend,
        );

        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );

        $pageImages = $releasePlan->freePlan->pageImages();
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        foreach ($overflowPageImages as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        $postDatabase = self::databaseWithPageImages($database, $pageImages, $allocationPlan->databasePageCount);
        $firstOverflowPage = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if ($firstOverflowPage === null) {
            throw new \InvalidArgumentException('SQLite overflow freeblock current/next replacement did not allocate an overflow page');
        }

        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $reused = [];
        foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
            if (isset($released[$pageNumber])) {
                $reused[] = $pageNumber;
            }
        }

        return new self(
            $releasePlan,
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
        return [
            'action' => 'btree-overflow-freeblock-current-next',
            'released_overflow_pages' => $this->releasePlan->releasedOverflowPages,
            'replacement_overflow_pages' => $this->replacementOverflowPageNumbers(),
            'reused_released_pages' => $this->reusedReleasedPageNumbers,
            'appended_page_numbers' => $this->allocationPlan->appendedPageNumbers,
            'chain_links' => $this->chainLinks,
            'allocated_pointer_map_entries' => $this->allocationPlan->allocatedPointerMapEntries(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->allocationPlan->updatedPointerMapPages),
            'release' => $this->releasePlan->toArray(),
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
