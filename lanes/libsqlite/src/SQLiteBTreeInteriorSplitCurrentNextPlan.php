<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeInteriorSplitCurrentNextPlan
{
    /**
     * @param list<int> $leftKeys
     * @param list<int> $rightKeys
     * @param list<int> $leftChildPageNumbers
     * @param list<int> $rightChildPageNumbers
     * @param array<int, array{type:int,parent_page_number:int}> $pointerMapUpdates
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $parentPageNumber,
        public readonly int $currentPageNumber,
        public readonly int $nextPageNumber,
        public readonly int $insertedDividerKey,
        public readonly int $parentDividerCellIndex,
        public readonly int $pageSize,
        public readonly int $usableSize,
        public readonly array $leftKeys,
        public readonly array $rightKeys,
        public readonly array $leftChildPageNumbers,
        public readonly array $rightChildPageNumbers,
        public readonly array $pointerMapUpdates,
        public readonly array $pageImages,
        public readonly int $beforeCurrentCellCount,
        public readonly int $afterCurrentCellCount,
        public readonly int $afterNextCellCount,
    ) {
    }

    public static function tableCurrentIntoNext(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $currentPageNumber,
        int $nextPageNumber,
    ): self {
        if ($parentPageNumber < 1 || $currentPageNumber < 1 || $nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree table interior split page numbers must be positive');
        }
        if ($parentPageNumber === $currentPageNumber || $parentPageNumber === $nextPageNumber || $currentPageNumber === $nextPageNumber) {
            throw new \InvalidArgumentException('SQLite b-tree table interior split requires distinct parent, current, and next pages');
        }
        if ($nextPageNumber <= $database->pageCount() && trim($database->page($nextPageNumber), "\0") !== '') {
            throw new \InvalidArgumentException('SQLite b-tree table interior split next page must be empty or newly allocated');
        }

        $pageSize = $database->header->pageSize;
        $usableSize = $database->usablePageSize();
        $parentHeaderOffset = $parentPageNumber === 1 ? 100 : 0;
        $currentHeaderOffset = $currentPageNumber === 1 ? 100 : 0;
        $parentPage = $database->page($parentPageNumber);
        $currentPage = $database->page($currentPageNumber);
        $parentHeader = SQLiteBTreePageHeader::parsePage($parentPage, $pageSize, $parentHeaderOffset);
        $currentHeader = SQLiteBTreePageHeader::parsePage($currentPage, $pageSize, $currentHeaderOffset);
        if ($parentHeader->pageType !== 'table-interior' || $currentHeader->pageType !== 'table-interior') {
            throw new \InvalidArgumentException('SQLite b-tree table interior split requires table interior parent and current pages');
        }
        if ($parentHeader->rightMostPointer === null || $currentHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree table interior split requires right-most child pointers');
        }

        $parentCells = SQLiteTableInteriorCell::parsePageCells($parentPage, $parentHeader);
        $parentKeys = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $parentCells);
        $parentChildren = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $parentCells);
        $parentChildren[] = $parentHeader->rightMostPointer;
        $currentIndex = array_search($currentPageNumber, $parentChildren, true);
        if (!is_int($currentIndex)) {
            throw new \InvalidArgumentException('SQLite b-tree table interior split current page is not referenced by parent');
        }
        if (in_array($nextPageNumber, $parentChildren, true)) {
            throw new \InvalidArgumentException('SQLite b-tree table interior split next page is already referenced by parent');
        }

        $currentCells = SQLiteTableInteriorCell::parsePageCells($currentPage, $currentHeader);
        $keys = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $currentCells);
        $children = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $currentCells);
        $children[] = $currentHeader->rightMostPointer;
        if (count($keys) < 3) {
            throw new \InvalidArgumentException('SQLite b-tree table interior split requires at least three current cells');
        }
        self::assertStrictlyIncreasing($keys);

        $dividerIndex = intdiv(count($keys), 2);
        $dividerKey = $keys[$dividerIndex];
        $leftKeys = array_slice($keys, 0, $dividerIndex);
        $rightKeys = array_slice($keys, $dividerIndex + 1);
        $leftChildren = array_slice($children, 0, $dividerIndex + 1);
        $rightChildren = array_slice($children, $dividerIndex + 1);
        if ($leftKeys === [] || $rightKeys === [] || count($leftChildren) !== count($leftKeys) + 1 || count($rightChildren) !== count($rightKeys) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree table interior split produced an invalid child/key shape');
        }

        $leftPage = self::assembleTableInteriorPage($leftKeys, $leftChildren, $pageSize, $currentHeaderOffset, $currentPage, $usableSize);
        $rightPage = self::assembleTableInteriorPage($rightKeys, $rightChildren, $pageSize, 0, str_repeat("\0", $pageSize), $usableSize);

        $updatedParentKeys = $parentKeys;
        array_splice($updatedParentKeys, $currentIndex, 0, [$dividerKey]);
        $updatedParentChildren = $parentChildren;
        array_splice($updatedParentChildren, $currentIndex + 1, 0, [$nextPageNumber]);
        $parentAfter = self::assembleTableInteriorPage($updatedParentKeys, $updatedParentChildren, $pageSize, $parentHeaderOffset, $parentPage, $usableSize);

        $pointerMapUpdates = [
            $nextPageNumber => [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $parentPageNumber,
            ],
        ];
        foreach ($rightChildren as $childPageNumber) {
            $pointerMapUpdates[$childPageNumber] = [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $nextPageNumber,
            ];
        }
        foreach ($leftChildren as $childPageNumber) {
            $pointerMapUpdates[$childPageNumber] = [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $currentPageNumber,
            ];
        }
        ksort($pointerMapUpdates);

        $pageImages = [
            $parentPageNumber => $parentAfter,
            $currentPageNumber => $leftPage,
            $nextPageNumber => $rightPage,
        ];
        foreach ($database->planPointerMapUpdates($pointerMapUpdates, max($database->pageCount(), $nextPageNumber)) as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        return new self(
            $parentPageNumber,
            $currentPageNumber,
            $nextPageNumber,
            $dividerKey,
            $currentIndex,
            $pageSize,
            $usableSize,
            $leftKeys,
            $rightKeys,
            $leftChildren,
            $rightChildren,
            $pointerMapUpdates,
            $pageImages,
            $currentHeader->cellCount,
            count($leftKeys),
            count($rightKeys),
        );
    }

    /**
     * @return array{action:string,parent_page:int,current_page:int,next_page:int,inserted_divider_key:int,parent_divider_cell_index:int,left_cell_count:int,right_cell_count:int,left_child_page_numbers:list<int>,right_child_page_numbers:list<int>,pointer_map_update_pages:list<int>,updated_page_numbers:list<int>}
     */
    public function toArray(): array
    {
        return [
            'action' => 'table-interior-current-next-split',
            'parent_page' => $this->parentPageNumber,
            'current_page' => $this->currentPageNumber,
            'next_page' => $this->nextPageNumber,
            'inserted_divider_key' => $this->insertedDividerKey,
            'parent_divider_cell_index' => $this->parentDividerCellIndex,
            'left_cell_count' => $this->afterCurrentCellCount,
            'right_cell_count' => $this->afterNextCellCount,
            'left_child_page_numbers' => $this->leftChildPageNumbers,
            'right_child_page_numbers' => $this->rightChildPageNumbers,
            'pointer_map_update_pages' => array_keys($this->pointerMapUpdates),
            'updated_page_numbers' => array_keys($this->pageImages),
        ];
    }

    /**
     * @param list<int> $keys
     * @param list<int> $children
     */
    private static function assembleTableInteriorPage(
        array $keys,
        array $children,
        int $pageSize,
        int $headerOffset,
        string $basePage,
        int $usableSize,
    ): string {
        if (count($children) !== count($keys) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree table interior split cannot assemble an invalid child/key shape');
        }

        $rightMostPointer = array_pop($children);
        if (!is_int($rightMostPointer)) {
            throw new \InvalidArgumentException('SQLite b-tree table interior split lost a right-most child pointer');
        }

        $cells = [];
        foreach ($keys as $index => $key) {
            $leftChildPage = $children[$index] ?? null;
            if (!is_int($leftChildPage)) {
                throw new \InvalidArgumentException('SQLite b-tree table interior split lost a left child pointer');
            }
            $cells[] = SQLiteTableInteriorCell::encode($leftChildPage, $key);
        }

        return SQLiteTableInteriorPage::assemble($cells, $rightMostPointer, $pageSize, $headerOffset, $basePage, $usableSize);
    }

    /**
     * @param list<int> $keys
     */
    private static function assertStrictlyIncreasing(array $keys): void
    {
        $previous = null;
        foreach ($keys as $key) {
            if ($previous !== null && $key <= $previous) {
                throw new \InvalidArgumentException('SQLite b-tree table interior split keys must be strictly increasing');
            }
            $previous = $key;
        }
    }
}
