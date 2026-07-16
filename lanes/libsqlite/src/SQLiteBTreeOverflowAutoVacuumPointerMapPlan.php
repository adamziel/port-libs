<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowAutoVacuumPointerMapPlan
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param array<int, string> $pageImages
     * @param list<array{current_page:int,next_page:int,payload_bytes:int,terminal:bool}> $chainLinks
     * @param list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}> $pointerMapEntries
     */
    private function __construct(
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly array $overflowPageImages,
        public readonly array $pageImages,
        public readonly array $chainLinks,
        public readonly array $pointerMapEntries,
        public readonly SQLiteDatabase $database,
    ) {
    }

    public static function allocateCurrentNextChain(
        SQLiteDatabase $database,
        int $parentBtreePageNumber,
        string $overflowPayload,
        bool $allowAppend = false,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite overflow current/next pointer-map planning requires an auto-vacuum database');
        }
        if ($overflowPayload === '') {
            throw new \InvalidArgumentException('SQLite overflow current/next pointer-map planning requires overflow payload bytes');
        }

        $requiredPageCount = SQLiteOverflowPage::requiredPageCount(
            strlen($overflowPayload),
            $database->header->pageSize,
            $database->usablePageSize(),
        );
        $allocationPlan = $database->planOverflowPageAllocation($requiredPageCount, $parentBtreePageNumber, $allowAppend);
        if (!$allowAppend && $allocationPlan->appendedPageNumbers !== []) {
            throw new \InvalidArgumentException('SQLite overflow current/next pointer-map planning expected freelist pages, not appended pages');
        }

        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $overflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $database->header->pageSize,
            $database->usablePageSize(),
        );

        $pageImages = $allocationPlan->pageImages();
        foreach ($overflowPageImages as $pageNumber => $pageImage) {
            $pageImages[$pageNumber] = $pageImage;
        }
        ksort($pageImages);

        $postDatabase = SQLiteDatabase::fromBytes(self::databaseBytesWithPageImages(
            $database,
            $pageImages,
            $allocationPlan->databasePageCount,
        ));
        $firstOverflowPage = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if ($firstOverflowPage === null) {
            throw new \InvalidArgumentException('SQLite overflow current/next pointer-map planning did not allocate an overflow page');
        }

        $chainLinks = SQLiteOverflowPage::chainLinksFromDatabase(
            $postDatabase,
            $firstOverflowPage,
            strlen($overflowPayload),
        );

        $pointerMapEntries = [];
        foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
            $pointerMapEntries[] = $postDatabase->pointerMapEntryForPage($pageNumber)->toArray();
        }

        return new self($allocationPlan, $overflowPageImages, $pageImages, $chainLinks, $pointerMapEntries, $postDatabase);
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return list<int>
     */
    public function updatedPointerMapPageNumbers(): array
    {
        return array_keys($this->allocationPlan->updatedPointerMapPages);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-autovacuum-pointermap-current-next',
            'allocated_overflow_pages' => $this->allocationPlan->allocatedPageNumbers,
            'allocation_steps' => $this->allocationPlan->allocationSteps(),
            'chain_links' => $this->chainLinks,
            'pointer_map_entries' => $this->pointerMapEntries,
            'updated_pointer_map_page_numbers' => $this->updatedPointerMapPageNumbers(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'first_freelist_trunk_page' => $this->allocationPlan->firstFreelistTrunkPage,
            'freelist_page_count' => $this->allocationPlan->freelistPageCount,
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseBytesWithPageImages(SQLiteDatabase $database, array $pageImages, int $pageCount): string
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            if (isset($pageImages[$pageNumber])) {
                $pages[] = $pageImages[$pageNumber];
                continue;
            }

            $pages[] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $database->header->pageSize);
        }

        return implode('', $pages);
    }
}
