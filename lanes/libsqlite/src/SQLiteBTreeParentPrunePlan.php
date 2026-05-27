<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeParentPrunePlan
{
    /**
     * @param list<int> $deletedRowIds
     * @param list<list<mixed>> $deletedRecordValues
     * @param list<int> $obsoleteOverflowPageNumbers
     * @param list<int> $freedPageNumbers
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $parentPageNumber,
        public readonly int $obsoleteChildPageNumber,
        public readonly string $parentPageType,
        public readonly string $childPageType,
        public readonly int $parentBeforeCellCount,
        public readonly int $parentAfterCellCount,
        public readonly ?int $rightMostPointerBefore,
        public readonly int $rightMostPointerAfter,
        public readonly array $deletedRowIds,
        public readonly array $deletedRecordValues,
        public readonly array $obsoleteOverflowPageNumbers,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $freedPageNumbers,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableChild(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $obsoleteChildPageNumber,
        array $deleteResult,
        bool $secureDelete = false,
    ): self {
        $rowIds = $deleteResult['rowids'] ?? null;
        if (!is_array($rowIds)) {
            $rowId = $deleteResult['rowid'] ?? null;
            $rowIds = is_int($rowId) ? [$rowId] : null;
        }
        if (!is_array($rowIds)) {
            throw new \InvalidArgumentException('SQLite parent prune table child requires deleted rowids');
        }

        return self::fromDeleteResult($database, $parentPageNumber, $obsoleteChildPageNumber, $deleteResult, 'table-leaf', $rowIds, [], $secureDelete);
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function indexChild(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $obsoleteChildPageNumber,
        array $deleteResult,
        bool $secureDelete = false,
    ): self {
        $recordValues = $deleteResult['record_values'] ?? null;
        if (!is_array($recordValues)) {
            throw new \InvalidArgumentException('SQLite parent prune index child requires deleted record values');
        }
        if ($recordValues !== [] && !is_array($recordValues[0] ?? null)) {
            $recordValues = [$recordValues];
        }

        return self::fromDeleteResult($database, $parentPageNumber, $obsoleteChildPageNumber, $deleteResult, 'index-leaf', [], $recordValues, $secureDelete);
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return array{action:string,parent_page:int,obsolete_child_page:int,parent_page_type:string,child_page_type:string,parent_before_cells:int,parent_after_cells:int,right_most_pointer_before:?int,right_most_pointer_after:int,deleted_rowids:list<int>,deleted_record_values:list<list<mixed>>,obsolete_overflow_pages:list<int>,freed_pages:list<int>,freelist_page_count:int,first_freelist_trunk_page:int,updated_page_numbers:list<int>,updated_pointer_map_page_numbers:list<int>,secure_delete_cleared_pages:list<int>}
     */
    public function toArray(): array
    {
        return [
            'action' => 'parent-prune-empty-child',
            'parent_page' => $this->parentPageNumber,
            'obsolete_child_page' => $this->obsoleteChildPageNumber,
            'parent_page_type' => $this->parentPageType,
            'child_page_type' => $this->childPageType,
            'parent_before_cells' => $this->parentBeforeCellCount,
            'parent_after_cells' => $this->parentAfterCellCount,
            'right_most_pointer_before' => $this->rightMostPointerBefore,
            'right_most_pointer_after' => $this->rightMostPointerAfter,
            'deleted_rowids' => $this->deletedRowIds,
            'deleted_record_values' => $this->deletedRecordValues,
            'obsolete_overflow_pages' => $this->obsoleteOverflowPageNumbers,
            'freed_pages' => $this->freedPageNumbers,
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
        ];
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @param list<int> $deletedRowIds
     * @param list<list<mixed>> $deletedRecordValues
     */
    private static function fromDeleteResult(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $obsoleteChildPageNumber,
        array $deleteResult,
        string $expectedChildType,
        array $deletedRowIds,
        array $deletedRecordValues,
        bool $secureDelete,
    ): self {
        if ($parentPageNumber < 2 || $parentPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite parent prune parent page is outside the database image');
        }
        if ($obsoleteChildPageNumber < 2 || $obsoleteChildPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite parent prune child page is outside the database image');
        }
        if ($parentPageNumber === $obsoleteChildPageNumber) {
            throw new \InvalidArgumentException('SQLite parent prune cannot remove the parent page');
        }

        $pageSize = $database->header->pageSize;
        $usableSize = $database->usablePageSize();
        $parentPage = $database->page($parentPageNumber);
        $parentHeader = SQLiteBTreePageHeader::parsePage($parentPage, $pageSize);
        if (!in_array($parentHeader->pageType, ['table-interior', 'index-interior'], true)) {
            throw new \InvalidArgumentException('SQLite parent prune requires an interior parent page');
        }
        if ($parentHeader->cellCount < 1 || $parentHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite parent prune requires an interior parent with at least two children');
        }

        $deletedPage = $deleteResult['page'] ?? null;
        if (!is_string($deletedPage) || strlen($deletedPage) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite parent prune requires a deleted child page image');
        }
        $deletedHeader = SQLiteBTreePageHeader::parsePage($deletedPage, $pageSize);
        if ($deletedHeader->pageType !== $expectedChildType) {
            throw new \InvalidArgumentException("SQLite parent prune requires a deleted {$expectedChildType} page image");
        }
        if ($deletedHeader->cellCount !== 0) {
            throw new \InvalidArgumentException('SQLite parent prune requires the deleted child page to be empty');
        }

        [$rebuiltParentPage, $rightMostAfter, $cellCountAfter] = self::prunedParentPage(
            $parentPage,
            $parentHeader,
            $obsoleteChildPageNumber,
            $pageSize,
            $usableSize,
        );

        $obsoleteOverflowPageNumbers = self::obsoleteOverflowPageNumbers($deleteResult);
        $freedPageNumbers = array_values(array_unique(array_merge([$obsoleteChildPageNumber], $obsoleteOverflowPageNumbers)));
        $freePlan = $database->planPageFreeList($freedPageNumbers, $secureDelete);
        $pageImages = $freePlan->pageImages();
        $pageImages[$parentPageNumber] = $rebuiltParentPage;
        ksort($pageImages);

        return new self(
            $parentPageNumber,
            $obsoleteChildPageNumber,
            $parentHeader->pageType,
            $expectedChildType,
            $parentHeader->cellCount,
            $cellCountAfter,
            $parentHeader->rightMostPointer,
            $rightMostAfter,
            array_values($deletedRowIds),
            array_values($deletedRecordValues),
            $obsoleteOverflowPageNumbers,
            $freePlan,
            $freedPageNumbers,
            $pageImages,
        );
    }

    /**
     * @return array{0:string,1:int,2:int}
     */
    private static function prunedParentPage(
        string $parentPage,
        SQLiteBTreePageHeader $parentHeader,
        int $obsoleteChildPageNumber,
        int $pageSize,
        int $usableSize,
    ): array {
        if ($parentHeader->pageType === 'table-interior') {
            $cells = SQLiteTableInteriorCell::parsePageCells($parentPage, $parentHeader);
            $rawCells = [];
            foreach ($cells as $cell) {
                $rawCells[] = substr($parentPage, $cell->offset, $cell->bytesRead);
            }

            [$newCells, $rightMostAfter] = self::pruneRawCells(
                $rawCells,
                array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $cells),
                $parentHeader->rightMostPointer,
                $obsoleteChildPageNumber,
            );

            return [
                SQLiteTableInteriorPage::assemble($newCells, $rightMostAfter, $pageSize, 0, $parentPage, $usableSize),
                $rightMostAfter,
                count($newCells),
            ];
        }

        $cells = SQLiteIndexCell::parsePageCells($parentPage, $parentHeader, $usableSize);
        $rawCells = [];
        $leftChildren = [];
        foreach ($cells as $cell) {
            if ($cell->leftChildPage === null) {
                throw new \InvalidArgumentException('SQLite parent prune found an index interior cell without a child pointer');
            }
            $leftChildren[] = $cell->leftChildPage;
            $rawCells[] = substr($parentPage, $cell->offset, $cell->bytesRead);
        }

        [$newCells, $rightMostAfter] = self::pruneRawCells(
            $rawCells,
            $leftChildren,
            $parentHeader->rightMostPointer,
            $obsoleteChildPageNumber,
        );

        return [
            SQLiteIndexInteriorPage::assemble($newCells, $rightMostAfter, $pageSize, 0, $parentPage, $usableSize),
            $rightMostAfter,
            count($newCells),
        ];
    }

    /**
     * @param list<string> $rawCells
     * @param list<int> $leftChildren
     * @return array{0:list<string>,1:int}
     */
    private static function pruneRawCells(array $rawCells, array $leftChildren, ?int $rightMostPointer, int $obsoleteChildPageNumber): array
    {
        if ($rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite parent prune requires a right-most child pointer');
        }
        $index = array_search($obsoleteChildPageNumber, $leftChildren, true);
        if ($index !== false) {
            array_splice($rawCells, $index, 1);
            if ($rawCells === []) {
                throw new \InvalidArgumentException('SQLite parent prune would leave an empty interior parent; use root collapse or sibling merge');
            }

            return [array_values($rawCells), $rightMostPointer];
        }

        if ($rightMostPointer === $obsoleteChildPageNumber) {
            if ($rawCells === []) {
                throw new \InvalidArgumentException('SQLite parent prune cannot remove the only child pointer');
            }
            $rightMostAfter = array_pop($leftChildren);
            array_pop($rawCells);
            if ($rightMostAfter === null || $rawCells === []) {
                throw new \InvalidArgumentException('SQLite parent prune would leave an empty interior parent; use root collapse or sibling merge');
            }

            return [array_values($rawCells), $rightMostAfter];
        }

        throw new \InvalidArgumentException('SQLite parent prune obsolete child is not referenced by the parent');
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @return list<int>
     */
    private static function obsoleteOverflowPageNumbers(array $deleteResult): array
    {
        $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
        if (!is_array($pages)) {
            throw new \InvalidArgumentException('SQLite parent prune requires obsolete overflow page numbers');
        }

        $normalized = [];
        foreach (array_values($pages) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite parent prune overflow page numbers must be integers');
            }
            $normalized[] = $pageNumber;
        }

        return array_values(array_unique($normalized));
    }
}
