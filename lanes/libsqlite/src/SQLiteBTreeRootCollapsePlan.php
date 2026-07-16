<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeRootCollapsePlan
{
    /**
     * @param list<int> $childPageNumbers
     * @param array<int, array{type:int,parent_page_number:int}> $pointerMapUpdates
     * @param list<int> $updatedPointerMapPageNumbers
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $rootPageNumber,
        public readonly int $obsoleteChildPageNumber,
        public readonly string $rootBeforeType,
        public readonly string $rootAfterType,
        public readonly int $rootBeforeCellCount,
        public readonly int $rootAfterCellCount,
        public readonly array $childPageNumbers,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $pointerMapUpdates,
        public readonly array $updatedPointerMapPageNumbers,
        public readonly array $pageImages,
    ) {
    }

    public static function collapseOnlyChild(
        SQLiteDatabase $database,
        int $rootPageNumber,
        bool $secureDelete = false,
    ): self {
        if ($rootPageNumber < 1 || $rootPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite b-tree root collapse root page is outside the database image');
        }

        $pageSize = $database->header->pageSize;
        $usableSize = $database->usablePageSize();
        $rootHeaderOffset = $rootPageNumber === 1 ? 100 : 0;
        $rootPage = $database->page($rootPageNumber);
        $rootHeader = SQLiteBTreePageHeader::parsePage($rootPage, $pageSize, $rootHeaderOffset);
        if (!in_array($rootHeader->pageType, ['table-interior', 'index-interior'], true)) {
            throw new \InvalidArgumentException('SQLite b-tree root collapse requires an interior root page');
        }
        if ($rootHeader->cellCount !== 0 || $rootHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree root collapse requires an empty interior root with one right-most child');
        }

        $childPageNumber = $rootHeader->rightMostPointer;
        if ($childPageNumber === $rootPageNumber || $childPageNumber < 2 || $childPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite b-tree root collapse child page is outside the database image');
        }
        $childPage = $database->page($childPageNumber);
        $childHeader = SQLiteBTreePageHeader::parsePage($childPage, $pageSize);
        if (!in_array($childHeader->pageType, ['table-leaf', 'table-interior', 'index-leaf', 'index-interior'], true)) {
            throw new \InvalidArgumentException('SQLite b-tree root collapse child page is not a b-tree page');
        }

        $collapsedRootPage = self::copyBtreePageToRoot(
            $database,
            $childPage,
            $childHeader,
            $rootPage,
            $rootHeaderOffset,
            $pageSize,
            $usableSize,
        );
        $collapsedHeader = SQLiteBTreePageHeader::parsePage($collapsedRootPage, $pageSize, $rootHeaderOffset);
        $childPageNumbers = self::childPointers($database, $collapsedRootPage, $collapsedHeader, $usableSize);
        $firstOverflowPageNumbers = self::firstOverflowPointers(
            $database,
            $collapsedRootPage,
            $collapsedHeader,
            $usableSize,
        );

        $freePlan = $database->planPageFree($childPageNumber, $secureDelete);
        $pageImages = $freePlan->pageImages();
        $pageImages[$rootPageNumber] = $collapsedRootPage;

        $pointerMapUpdates = [];
        foreach ($childPageNumbers as $grandchildPageNumber) {
            $pointerMapUpdates[$grandchildPageNumber] = [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $rootPageNumber,
            ];
        }
        foreach ($firstOverflowPageNumbers as $firstOverflowPageNumber) {
            if ($database->pointerMapPageFor($firstOverflowPageNumber) === null) {
                continue;
            }
            $pointerMapUpdates[$firstOverflowPageNumber] = [
                'type' => SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE,
                'parent_page_number' => $rootPageNumber,
            ];
        }
        if ($pointerMapUpdates !== []) {
            foreach ($pointerMapUpdates as $pageNumber => $update) {
                $pointerMapPage = $database->pointerMapPageFor($pageNumber);
                if ($pointerMapPage === null || $pointerMapPage === $pageNumber) {
                    throw new \InvalidArgumentException("SQLite page {$pageNumber} does not have a pointer-map entry");
                }
                if ($pageNumber > $freePlan->databasePageCount) {
                    throw new \InvalidArgumentException("SQLite pointer-map update page {$pageNumber} is outside the database image");
                }
                $page = $pageImages[$pointerMapPage] ?? $database->page($pointerMapPage);
                $pageImages[$pointerMapPage] = substr_replace(
                    $page,
                    chr($update['type']) . pack('N', $update['parent_page_number']),
                    $database->pointerMapOffsetFor($pageNumber),
                    5,
                );
            }
        }
        ksort($pointerMapUpdates);
        ksort($pageImages);
        $updatedPointerMapPageNumbers = array_keys($freePlan->updatedPointerMapPages);
        foreach (array_keys($pointerMapUpdates) as $pageNumber) {
            $pointerMapPage = $database->pointerMapPageFor($pageNumber);
            if ($pointerMapPage !== null && $pointerMapPage !== $pageNumber) {
                $updatedPointerMapPageNumbers[] = $pointerMapPage;
            }
        }
        $updatedPointerMapPageNumbers = array_values(array_unique($updatedPointerMapPageNumbers));
        sort($updatedPointerMapPageNumbers);

        return new self(
            $rootPageNumber,
            $childPageNumber,
            $rootHeader->pageType,
            $collapsedHeader->pageType,
            $rootHeader->cellCount,
            $collapsedHeader->cellCount,
            $childPageNumbers,
            $freePlan,
            $pointerMapUpdates,
            $updatedPointerMapPageNumbers,
            $pageImages,
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
     * @return array{action:string,root_page:int,obsolete_child_page:int,root_before_type:string,root_after_type:string,root_before_cells:int,root_after_cells:int,child_page_numbers:list<int>,freed_pages:list<int>,freelist_page_count:int,first_freelist_trunk_page:int,updated_page_numbers:list<int>,updated_pointer_map_page_numbers:list<int>,secure_delete_cleared_pages:list<int>}
     */
    public function toArray(): array
    {
        return [
            'action' => 'root-collapse-apply',
            'root_page' => $this->rootPageNumber,
            'obsolete_child_page' => $this->obsoleteChildPageNumber,
            'root_before_type' => $this->rootBeforeType,
            'root_after_type' => $this->rootAfterType,
            'root_before_cells' => $this->rootBeforeCellCount,
            'root_after_cells' => $this->rootAfterCellCount,
            'child_page_numbers' => $this->childPageNumbers,
            'freed_pages' => $this->freePlan->freedPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => $this->updatedPointerMapPageNumbers,
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
        ];
    }

    private static function copyBtreePageToRoot(
        SQLiteDatabase $database,
        string $sourcePage,
        SQLiteBTreePageHeader $sourceHeader,
        string $rootBasePage,
        int $rootHeaderOffset,
        int $pageSize,
        int $usableSize,
    ): string {
        $cells = self::rawCells($database, $sourcePage, $sourceHeader, $usableSize);

        return match ($sourceHeader->pageType) {
            'table-leaf' => SQLiteTableLeafPage::assemble($cells, $pageSize, $rootHeaderOffset, $rootBasePage, $usableSize),
            'index-leaf' => SQLiteIndexLeafPage::assemble($cells, $pageSize, $rootHeaderOffset, $rootBasePage, $usableSize),
            'table-interior' => SQLiteTableInteriorPage::assemble(
                $cells,
                self::requireRightMostPointer($sourceHeader),
                $pageSize,
                $rootHeaderOffset,
                $rootBasePage,
                $usableSize,
            ),
            'index-interior' => SQLiteIndexInteriorPage::assemble(
                $cells,
                self::requireRightMostPointer($sourceHeader),
                $pageSize,
                $rootHeaderOffset,
                $rootBasePage,
                $usableSize,
            ),
            default => throw new \InvalidArgumentException('SQLite b-tree root collapse cannot copy this page type'),
        };
    }

    /**
     * @return list<string>
     */
    private static function rawCells(SQLiteDatabase $database, string $page, SQLiteBTreePageHeader $header, int $usableSize): array
    {
        $overflowReader = static fn (int $firstOverflowPage, int $byteCount): string => $database->readOverflowPayloadForBtreePlan($firstOverflowPage, $byteCount);

        return match ($header->pageType) {
            'table-leaf' => array_map(
                static fn (SQLiteTableLeafCell $cell): string => substr($page, $cell->offset, $cell->bytesRead),
                SQLiteTableLeafCell::parsePageCells($page, $header, $usableSize, $overflowReader),
            ),
            'table-interior' => array_map(
                static fn (SQLiteTableInteriorCell $cell): string => substr($page, $cell->offset, $cell->bytesRead),
                SQLiteTableInteriorCell::parsePageCells($page, $header),
            ),
            'index-leaf', 'index-interior' => array_map(
                static fn (SQLiteIndexCell $cell): string => substr($page, $cell->offset, $cell->bytesRead),
                SQLiteIndexCell::parsePageCells($page, $header, $usableSize, $overflowReader),
            ),
            default => throw new \InvalidArgumentException('SQLite b-tree root collapse cannot read cells for this page type'),
        };
    }

    /**
     * @return list<int>
     */
    private static function childPointers(SQLiteDatabase $database, string $page, SQLiteBTreePageHeader $header, int $usableSize): array
    {
        if ($header->pageType === 'table-interior') {
            $children = array_map(
                static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage,
                SQLiteTableInteriorCell::parsePageCells($page, $header),
            );
            $children[] = self::requireRightMostPointer($header);

            return $children;
        }

        if ($header->pageType === 'index-interior') {
            $overflowReader = static fn (int $firstOverflowPage, int $byteCount): string => $database->readOverflowPayloadForBtreePlan($firstOverflowPage, $byteCount);
            $children = [];
            foreach (SQLiteIndexCell::parsePageCells($page, $header, $usableSize, $overflowReader) as $cell) {
                if ($cell->leftChildPage === null) {
                    throw new \InvalidArgumentException('SQLite b-tree root collapse found an index interior cell without a child pointer');
                }
                $children[] = $cell->leftChildPage;
            }
            $children[] = self::requireRightMostPointer($header);

            return $children;
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private static function firstOverflowPointers(
        SQLiteDatabase $database,
        string $page,
        SQLiteBTreePageHeader $header,
        int $usableSize,
    ): array {
        $overflowReader = static fn (int $firstOverflowPage, int $byteCount): string => $database->readOverflowPayloadForBtreePlan($firstOverflowPage, $byteCount);
        $firstOverflowPages = [];

        if ($header->pageType === 'table-leaf') {
            foreach (SQLiteTableLeafCell::parsePageCells($page, $header, $usableSize, $overflowReader) as $cell) {
                if ($cell->firstOverflowPage !== null) {
                    $firstOverflowPages[] = $cell->firstOverflowPage;
                }
            }
        } elseif ($header->pageType === 'index-leaf' || $header->pageType === 'index-interior') {
            foreach (SQLiteIndexCell::parsePageCells($page, $header, $usableSize, $overflowReader) as $cell) {
                if ($cell->firstOverflowPage !== null) {
                    $firstOverflowPages[] = $cell->firstOverflowPage;
                }
            }
        }

        return array_values(array_unique($firstOverflowPages));
    }

    private static function requireRightMostPointer(SQLiteBTreePageHeader $header): int
    {
        if ($header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree root collapse requires a right-most pointer');
        }

        return $header->rightMostPointer;
    }
}
