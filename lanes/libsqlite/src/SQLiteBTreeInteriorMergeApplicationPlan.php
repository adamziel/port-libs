<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeInteriorMergeApplicationPlan
{
    /**
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly SQLiteBTreeInteriorMergePlan $mergePlan,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $pageImages,
        public readonly ?int $parentDividerCellIndex = null,
        public readonly ?string $parentPageAfter = null,
    ) {
    }

    public static function apply(
        SQLiteDatabase $database,
        SQLiteBTreeInteriorMergePlan $mergePlan,
        bool $secureDelete = false,
    ): self {
        if ($database->header->pageSize !== $mergePlan->pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree interior merge application page size does not match database');
        }
        if ($database->usablePageSize() !== $mergePlan->usableSize) {
            throw new \InvalidArgumentException('SQLite b-tree interior merge application usable size does not match database');
        }

        $database->page($mergePlan->leftPageNumber);
        $database->page($mergePlan->rightPageNumber);

        $freePlan = $database->planPageFree($mergePlan->rightPageNumber, $secureDelete);
        $pageImages = $freePlan->pageImages();

        foreach ($mergePlan->pointerMapUpdates as $pageNumber => $update) {
            $pointerMapPage = $database->pointerMapPageFor($pageNumber);
            if ($pointerMapPage === null || $pointerMapPage === $pageNumber) {
                throw new \InvalidArgumentException("SQLite page {$pageNumber} does not have a pointer-map entry");
            }
            $page = $pageImages[$pointerMapPage] ?? $database->page($pointerMapPage);
            $pageImages[$pointerMapPage] = substr_replace(
                $page,
                chr($update['type']) . pack('N', $update['parent_page_number']),
                $database->pointerMapOffsetFor($pageNumber),
                5,
            );
        }
        $pageImages[$mergePlan->leftPageNumber] = $mergePlan->mergedPage;
        ksort($pageImages);

        return new self($mergePlan, $freePlan, $pageImages);
    }

    public static function tableCurrentAndNext(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $currentPageNumber,
        bool $secureDelete = false,
    ): self {
        if ($parentPageNumber < 1 || $currentPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior merge page numbers must be positive');
        }

        $pageSize = $database->header->pageSize;
        $usableSize = $database->usablePageSize();
        $parentHeaderOffset = $parentPageNumber === 1 ? 100 : 0;
        $parentPage = $database->page($parentPageNumber);
        $parentHeader = SQLiteBTreePageHeader::parsePage($parentPage, $pageSize, $parentHeaderOffset);
        if ($parentHeader->pageType !== 'table-interior') {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior merge requires a table interior parent');
        }
        if ($parentHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior merge requires a parent right-most child pointer');
        }

        $parentCells = SQLiteTableInteriorCell::parsePageCells($parentPage, $parentHeader);
        $parentKeys = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $parentCells);
        $parentChildren = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $parentCells);
        $parentChildren[] = $parentHeader->rightMostPointer;

        $currentChildIndex = array_search($currentPageNumber, $parentChildren, true);
        if (!is_int($currentChildIndex)) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior merge current child is not referenced by parent');
        }
        if ($currentChildIndex >= count($parentChildren) - 1) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior merge requires a next sibling to the right');
        }

        $nextPageNumber = $parentChildren[$currentChildIndex + 1];
        $dividerKey = $parentKeys[$currentChildIndex] ?? null;
        if (!is_int($nextPageNumber) || !is_int($dividerKey)) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior merge could not resolve the parent divider');
        }

        $mergePlan = SQLiteBTreeInteriorMergePlan::tableInterior(
            $database->page($currentPageNumber),
            $database->page($nextPageNumber),
            $currentPageNumber,
            $nextPageNumber,
            $parentPageNumber,
            $dividerKey,
            $pageSize,
            $usableSize,
            $currentPageNumber === 1 ? 100 : 0,
            $nextPageNumber === 1 ? 100 : 0,
        );
        $application = self::apply($database, $mergePlan, $secureDelete);

        $updatedParentKeys = $parentKeys;
        array_splice($updatedParentKeys, $currentChildIndex, 1);
        $updatedParentChildren = $parentChildren;
        array_splice($updatedParentChildren, $currentChildIndex + 1, 1);
        if (count($updatedParentChildren) !== count($updatedParentKeys) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior merge produced an invalid parent shape');
        }
        $parentPageAfter = self::assembleTableParentPage(
            $updatedParentKeys,
            $updatedParentChildren,
            $pageSize,
            $parentHeaderOffset,
            $parentPage,
            $usableSize,
        );

        $pageImages = $application->pageImages;
        $pageImages[$parentPageNumber] = $parentPageAfter;
        ksort($pageImages);

        return new self($mergePlan, $application->freePlan, $pageImages, $currentChildIndex, $parentPageAfter);
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return array{action:string,merged_page:int,obsolete_page:int,parent_page:int,merged_child_page_numbers:list<int>,freed_pages:list<int>,freelist_page_count:int,first_freelist_trunk_page:int,updated_page_numbers:list<int>,updated_pointer_map_page_numbers:list<int>,secure_delete_cleared_pages:list<int>}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->mergePlan->pageType . '-sibling-merge-apply',
            'merged_page' => $this->mergePlan->leftPageNumber,
            'obsolete_page' => $this->mergePlan->rightPageNumber,
            'parent_page' => $this->mergePlan->parentPageNumber,
            'merged_child_page_numbers' => $this->mergePlan->mergedChildPageNumbers,
            'freed_pages' => $this->freePlan->freedPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
            'parent_divider_cell_index' => $this->parentDividerCellIndex,
            'parent_page_updated' => $this->parentPageAfter !== null,
        ];
    }

    /**
     * @param list<int> $keys
     * @param list<int> $children
     */
    private static function assembleTableParentPage(
        array $keys,
        array $children,
        int $pageSize,
        int $headerOffset,
        string $basePage,
        int $usableSize,
    ): string {
        if (count($children) !== count($keys) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior merge cannot assemble an invalid parent shape');
        }

        $rightMostPointer = array_pop($children);
        if (!is_int($rightMostPointer)) {
            throw new \InvalidArgumentException('SQLite b-tree current/next interior merge lost the parent right-most child pointer');
        }

        $cells = [];
        foreach ($keys as $index => $key) {
            $leftChildPage = $children[$index] ?? null;
            if (!is_int($leftChildPage)) {
                throw new \InvalidArgumentException('SQLite b-tree current/next interior merge lost a parent left child pointer');
            }
            $cells[] = SQLiteTableInteriorCell::encode($leftChildPage, $key);
        }

        return SQLiteTableInteriorPage::assemble($cells, $rightMostPointer, $pageSize, $headerOffset, $basePage, $usableSize);
    }
}
