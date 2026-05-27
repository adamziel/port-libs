<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePageMovePlan
{
    /**
     * @param array<int, string> $pageImages
     * @param array{kind:string,page:int,before:int,after:int} $parentPointerUpdate
     * @param list<int> $updatedPointerMapPageNumbers
     */
    private function __construct(
        public readonly int $sourcePageNumber,
        public readonly int $targetPageNumber,
        public readonly int $parentPageNumber,
        public readonly int $databasePageCount,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly array $pageImages,
        public readonly array $parentPointerUpdate,
        public readonly array $updatedPointerMapPageNumbers,
    ) {
    }

    public static function moveLastTableLeafIntoFreelistSlot(
        SQLiteDatabase $database,
        int $sourcePageNumber,
        int $parentPageNumber,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree page moves require an auto-vacuum database');
        }
        if ($sourcePageNumber !== max($database->pageCount(), $database->header->databaseSizePages)) {
            throw new \InvalidArgumentException('SQLite b-tree page move source must be the last database page');
        }
        if ($sourcePageNumber < 3 || $parentPageNumber < 3) {
            throw new \InvalidArgumentException('SQLite b-tree page move source and parent must be b-tree pages');
        }
        if ($database->isPointerMapPage($sourcePageNumber) || $database->isPointerMapPage($parentPageNumber)) {
            throw new \InvalidArgumentException('SQLite b-tree page move cannot move pointer-map pages');
        }

        $sourcePage = $database->page($sourcePageNumber);
        $sourceHeader = SQLiteBTreePageHeader::parsePage($sourcePage, $database->header->pageSize);
        if ($sourceHeader->pageType !== 'table-leaf') {
            throw new \InvalidArgumentException('SQLite b-tree page move source must be a table leaf page');
        }

        $allocationPlan = $database->planPageAllocation(1, false);
        $targetPageNumber = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if (!is_int($targetPageNumber)) {
            throw new \InvalidArgumentException('SQLite b-tree page move could not allocate a target freelist page');
        }
        if ($targetPageNumber >= $sourcePageNumber) {
            throw new \InvalidArgumentException('SQLite b-tree page move target must be before the source page');
        }
        if ($database->isPointerMapPage($targetPageNumber)) {
            throw new \InvalidArgumentException('SQLite b-tree page move target cannot be a pointer-map page');
        }

        [$parentPage, $parentPointerUpdate] = self::parentPageWithMovedTableChild(
            $database,
            $parentPageNumber,
            $sourcePageNumber,
            $targetPageNumber,
        );

        $databasePageCount = $sourcePageNumber - 1;
        $firstPage = substr_replace($allocationPlan->firstPage, self::uint32Bytes($databasePageCount), 28, 4);
        $pageImages = $allocationPlan->pageImages();
        $pageImages[1] = $firstPage;
        $pageImages[$parentPageNumber] = $parentPage;
        $pageImages[$targetPageNumber] = $sourcePage;
        $pointerMapUpdates = [
            $targetPageNumber => [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $parentPageNumber,
            ],
        ];
        $overflowReader = static fn (int $firstOverflowPage, int $byteCount): string => self::readOverflowPayload($database, $firstOverflowPage, $byteCount);
        foreach (SQLiteTableLeafCell::parsePageCells($sourcePage, $sourceHeader, $database->usablePageSize(), $overflowReader) as $cell) {
            if ($cell->firstOverflowPage !== null) {
                $pointerMapUpdates[$cell->firstOverflowPage] = [
                    'type' => SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE,
                    'parent_page_number' => $targetPageNumber,
                ];
            }
        }

        $pointerMapPageImages = $database->planPointerMapUpdates($pointerMapUpdates, $databasePageCount);
        foreach ($pointerMapPageImages as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        return new self(
            $sourcePageNumber,
            $targetPageNumber,
            $parentPageNumber,
            $databasePageCount,
            $allocationPlan,
            $pageImages,
            $parentPointerUpdate,
            array_keys($pointerMapPageImages),
        );
    }

    public static function moveLastIndexLeafIntoFreelistSlot(
        SQLiteDatabase $database,
        int $sourcePageNumber,
        int $parentPageNumber,
    ): self {
        if (!$database->isAutoVacuum()) {
            throw new \InvalidArgumentException('SQLite b-tree page moves require an auto-vacuum database');
        }
        if ($sourcePageNumber !== max($database->pageCount(), $database->header->databaseSizePages)) {
            throw new \InvalidArgumentException('SQLite b-tree page move source must be the last database page');
        }
        if ($sourcePageNumber < 3 || $parentPageNumber < 3) {
            throw new \InvalidArgumentException('SQLite b-tree page move source and parent must be b-tree pages');
        }
        if ($database->isPointerMapPage($sourcePageNumber) || $database->isPointerMapPage($parentPageNumber)) {
            throw new \InvalidArgumentException('SQLite b-tree page move cannot move pointer-map pages');
        }

        $sourcePage = $database->page($sourcePageNumber);
        $sourceHeader = SQLiteBTreePageHeader::parsePage($sourcePage, $database->header->pageSize);
        if ($sourceHeader->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite b-tree page move source must be an index leaf page');
        }

        $allocationPlan = $database->planPageAllocation(1, false);
        $targetPageNumber = $allocationPlan->allocatedPageNumbers[0] ?? null;
        if (!is_int($targetPageNumber)) {
            throw new \InvalidArgumentException('SQLite b-tree page move could not allocate a target freelist page');
        }
        if ($targetPageNumber >= $sourcePageNumber) {
            throw new \InvalidArgumentException('SQLite b-tree page move target must be before the source page');
        }
        if ($database->isPointerMapPage($targetPageNumber)) {
            throw new \InvalidArgumentException('SQLite b-tree page move target cannot be a pointer-map page');
        }

        [$parentPage, $parentPointerUpdate] = self::parentPageWithMovedIndexChild(
            $database,
            $parentPageNumber,
            $sourcePageNumber,
            $targetPageNumber,
        );

        $databasePageCount = $sourcePageNumber - 1;
        $firstPage = substr_replace($allocationPlan->firstPage, self::uint32Bytes($databasePageCount), 28, 4);
        $pageImages = $allocationPlan->pageImages();
        $pageImages[1] = $firstPage;
        $pageImages[$parentPageNumber] = $parentPage;
        $pageImages[$targetPageNumber] = $sourcePage;

        $pointerMapUpdates = [
            $targetPageNumber => [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $parentPageNumber,
            ],
        ];
        $overflowReader = static fn (int $firstOverflowPage, int $byteCount): string => self::readOverflowPayload($database, $firstOverflowPage, $byteCount);
        foreach (SQLiteIndexCell::parsePageCells($sourcePage, $sourceHeader, $database->usablePageSize(), $overflowReader) as $cell) {
            if ($cell->firstOverflowPage !== null) {
                $pointerMapUpdates[$cell->firstOverflowPage] = [
                    'type' => SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE,
                    'parent_page_number' => $targetPageNumber,
                ];
            }
        }

        $pointerMapPageImages = $database->planPointerMapUpdates($pointerMapUpdates, $databasePageCount);
        foreach ($pointerMapPageImages as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        return new self(
            $sourcePageNumber,
            $targetPageNumber,
            $parentPageNumber,
            $databasePageCount,
            $allocationPlan,
            $pageImages,
            $parentPointerUpdate,
            array_keys($pointerMapPageImages),
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
     * @return array{action:string,source_page:int,target_page:int,parent_page:int,database_page_count:int,allocated_page_numbers:list<int>,freelist_page_count:int,first_freelist_trunk_page:int,parent_pointer_update:array{kind:string,page:int,before:int,after:int},updated_page_numbers:list<int>,updated_freelist_page_numbers:list<int>,updated_pointer_map_page_numbers:list<int>}
     */
    public function toArray(): array
    {
        $movedHeader = SQLiteBTreePageHeader::parsePage($this->pageImages[$this->targetPageNumber], strlen($this->pageImages[$this->targetPageNumber]));

        return [
            'action' => "auto-vacuum-{$movedHeader->pageType}-page-move",
            'source_page' => $this->sourcePageNumber,
            'target_page' => $this->targetPageNumber,
            'parent_page' => $this->parentPageNumber,
            'database_page_count' => $this->databasePageCount,
            'allocated_page_numbers' => $this->allocationPlan->allocatedPageNumbers,
            'freelist_page_count' => $this->allocationPlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->allocationPlan->firstFreelistTrunkPage,
            'parent_pointer_update' => $this->parentPointerUpdate,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_freelist_page_numbers' => array_keys($this->allocationPlan->updatedFreelistPages),
            'updated_pointer_map_page_numbers' => $this->updatedPointerMapPageNumbers,
        ];
    }

    /**
     * @return array{0:string,1:array{kind:string,page:int,before:int,after:int}}
     */
    private static function parentPageWithMovedTableChild(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $sourcePageNumber,
        int $targetPageNumber,
    ): array {
        $parentPage = $database->page($parentPageNumber);
        $header = SQLiteBTreePageHeader::parsePage($parentPage, $database->header->pageSize);
        if ($header->pageType !== 'table-interior' || $header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree page move parent must be a table interior page');
        }

        $cells = [];
        $updated = null;
        foreach (SQLiteTableInteriorCell::parsePageCells($parentPage, $header) as $cell) {
            $leftChild = $cell->leftChildPage;
            if ($leftChild === $sourcePageNumber) {
                $leftChild = $targetPageNumber;
                $updated = [
                    'kind' => 'left-child',
                    'page' => $parentPageNumber,
                    'before' => $sourcePageNumber,
                    'after' => $targetPageNumber,
                ];
            }
            $cells[] = SQLiteTableInteriorCell::encode($leftChild, $cell->key);
        }

        $rightMostPointer = $header->rightMostPointer;
        if ($rightMostPointer === $sourcePageNumber) {
            $rightMostPointer = $targetPageNumber;
            $updated = [
                'kind' => 'right-most',
                'page' => $parentPageNumber,
                'before' => $sourcePageNumber,
                'after' => $targetPageNumber,
            ];
        }
        if ($updated === null) {
            throw new \InvalidArgumentException('SQLite b-tree page move parent does not reference the source page');
        }

        return [SQLiteTableInteriorPage::assemble($cells, $rightMostPointer, $database->header->pageSize), $updated];
    }

    /**
     * @return array{0:string,1:array{kind:string,page:int,before:int,after:int}}
     */
    private static function parentPageWithMovedIndexChild(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $sourcePageNumber,
        int $targetPageNumber,
    ): array {
        $parentPage = $database->page($parentPageNumber);
        $header = SQLiteBTreePageHeader::parsePage($parentPage, $database->header->pageSize);
        if ($header->pageType !== 'index-interior' || $header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree page move parent must be an index interior page');
        }

        $overflowReader = static fn (int $firstOverflowPage, int $byteCount): string => self::readOverflowPayload($database, $firstOverflowPage, $byteCount);
        $cells = [];
        $updated = null;
        foreach (SQLiteIndexCell::parsePageCells($parentPage, $header, $database->usablePageSize(), $overflowReader) as $cell) {
            $leftChild = $cell->leftChildPage;
            if ($leftChild === null) {
                throw new \InvalidArgumentException('SQLite index interior parent cell is missing its child page');
            }
            if ($leftChild === $sourcePageNumber) {
                $leftChild = $targetPageNumber;
                $updated = [
                    'kind' => 'left-child',
                    'page' => $parentPageNumber,
                    'before' => $sourcePageNumber,
                    'after' => $targetPageNumber,
                ];
            }
            $cells[] = SQLiteIndexCell::encode($cell->payload, $database->usablePageSize(), $cell->firstOverflowPage, $leftChild);
        }

        $rightMostPointer = $header->rightMostPointer;
        if ($rightMostPointer === $sourcePageNumber) {
            $rightMostPointer = $targetPageNumber;
            $updated = [
                'kind' => 'right-most',
                'page' => $parentPageNumber,
                'before' => $sourcePageNumber,
                'after' => $targetPageNumber,
            ];
        }
        if ($updated === null) {
            throw new \InvalidArgumentException('SQLite b-tree page move parent does not reference the source page');
        }

        return [SQLiteIndexInteriorPage::assemble($cells, $rightMostPointer, $database->header->pageSize), $updated];
    }

    private static function uint32Bytes(int $value): string
    {
        if ($value < 0 || $value > 0xffffffff) {
            throw new \InvalidArgumentException('SQLite uint32 value is outside the supported range');
        }

        return pack('N', $value);
    }

    private static function readOverflowPayload(SQLiteDatabase $database, int $firstOverflowPage, int $byteCount): string
    {
        if ($firstOverflowPage < 2) {
            throw new \InvalidArgumentException('SQLite overflow chain starts before page 2');
        }
        if ($byteCount < 0) {
            throw new \InvalidArgumentException('SQLite overflow byte count cannot be negative');
        }

        $payload = '';
        $pageNumber = $firstOverflowPage;
        $seen = [];
        while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite overflow chain loops at page {$pageNumber}");
            }
            $seen[$pageNumber] = true;

            $page = $database->page($pageNumber);
            $nextPage = unpack('N', substr($page, 0, 4))[1];
            $payload .= substr($page, 4, min($database->usablePageSize() - 4, $byteCount - strlen($payload)));
            $pageNumber = $nextPage;
        }

        if (strlen($payload) !== $byteCount) {
            throw new \InvalidArgumentException('SQLite overflow chain ended before the requested payload bytes were read');
        }

        return $payload;
    }
}
