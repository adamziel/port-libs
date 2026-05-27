<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeTableLeafBalanceApplyPlan
{
    /**
     * @param list<array{rowid:int,payload:string}> $leftEntries
     * @param list<array{rowid:int,payload:string}> $rightEntries
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $parentPageNumber,
        public readonly int $leftPageNumber,
        public readonly int $rightPageNumber,
        public readonly int $dividerIndex,
        public readonly int $beforeLeftCellCount,
        public readonly int $beforeRightCellCount,
        public readonly array $leftEntries,
        public readonly array $rightEntries,
        public readonly int $newDividerKey,
        public readonly array $pageImages,
    ) {
    }

    public static function apply(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $leftPageNumber,
        int $rightPageNumber,
        int $dividerIndex,
    ): self {
        if ($parentPageNumber < 1 || $leftPageNumber < 1 || $rightPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite table leaf balance page numbers must be positive');
        }
        if ($leftPageNumber === $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite table leaf balance requires distinct sibling pages');
        }

        $pageSize = $database->header->pageSize;
        $usableSize = $database->usablePageSize();
        $parentPage = $database->page($parentPageNumber);
        $leftPage = $database->page($leftPageNumber);
        $rightPage = $database->page($rightPageNumber);
        $parentHeaderOffset = $parentPageNumber === 1 ? 100 : 0;
        $leftHeaderOffset = $leftPageNumber === 1 ? 100 : 0;
        $rightHeaderOffset = $rightPageNumber === 1 ? 100 : 0;

        $parentHeader = SQLiteBTreePageHeader::parsePage($parentPage, $pageSize, $parentHeaderOffset);
        $leftHeader = SQLiteBTreePageHeader::parsePage($leftPage, $pageSize, $leftHeaderOffset);
        $rightHeader = SQLiteBTreePageHeader::parsePage($rightPage, $pageSize, $rightHeaderOffset);
        if ($parentHeader->pageType !== 'table-interior' || $parentHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite table leaf balance requires a table-interior parent page');
        }
        if ($leftHeader->pageType !== 'table-leaf' || $rightHeader->pageType !== 'table-leaf') {
            throw new \InvalidArgumentException('SQLite table leaf balance requires two table-leaf sibling pages');
        }

        $parentCells = SQLiteTableInteriorCell::parsePageCells($parentPage, $parentHeader);
        $childPages = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $parentCells);
        $childPages[] = $parentHeader->rightMostPointer;
        if (!isset($childPages[$dividerIndex], $childPages[$dividerIndex + 1], $parentCells[$dividerIndex])) {
            throw new \InvalidArgumentException('SQLite table leaf balance divider index is outside the parent child list');
        }
        if ($childPages[$dividerIndex] !== $leftPageNumber || $childPages[$dividerIndex + 1] !== $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite table leaf balance sibling pages must flank the selected parent divider');
        }

        $leftEntries = self::leafEntries($leftPage, $leftHeader, $usableSize);
        $rightEntries = self::leafEntries($rightPage, $rightHeader, $usableSize);
        $combined = array_merge($leftEntries, $rightEntries);
        self::assertOrdered($combined);

        $leftCount = intdiv(count($combined) + 1, 2);
        if ($leftCount < 1 || $leftCount >= count($combined)) {
            throw new \InvalidArgumentException('SQLite table leaf balance requires enough cells on both sides');
        }

        $newLeftEntries = array_slice($combined, 0, $leftCount);
        $newRightEntries = array_slice($combined, $leftCount);
        $newDividerKey = $newLeftEntries[count($newLeftEntries) - 1]['rowid'];

        $parentRewrites = [];
        foreach ($parentCells as $index => $cell) {
            $parentRewrites[] = SQLiteTableInteriorCell::encode(
                $cell->leftChildPage,
                $index === $dividerIndex ? $newDividerKey : $cell->key,
            );
        }

        $newLeftPage = SQLiteTableLeafPage::assemble(
            array_map(static fn (array $entry): string => SQLiteTableLeafCell::encode($entry['rowid'], $entry['payload'], $usableSize), $newLeftEntries),
            $pageSize,
            $leftHeaderOffset,
            $leftPage,
            $usableSize,
        );
        $newRightPage = SQLiteTableLeafPage::assemble(
            array_map(static fn (array $entry): string => SQLiteTableLeafCell::encode($entry['rowid'], $entry['payload'], $usableSize), $newRightEntries),
            $pageSize,
            $rightHeaderOffset,
            $rightPage,
            $usableSize,
        );
        $newParentPage = SQLiteTableInteriorPage::assemble(
            $parentRewrites,
            $parentHeader->rightMostPointer,
            $pageSize,
            $parentHeaderOffset,
            $parentPage,
            $usableSize,
        );

        $pageImages = [
            $parentPageNumber => $newParentPage,
            $leftPageNumber => $newLeftPage,
            $rightPageNumber => $newRightPage,
        ];
        ksort($pageImages);

        return new self(
            $parentPageNumber,
            $leftPageNumber,
            $rightPageNumber,
            $dividerIndex,
            $leftHeader->cellCount,
            $rightHeader->cellCount,
            $newLeftEntries,
            $newRightEntries,
            $newDividerKey,
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'table-leaf-balance-apply',
            'parent_page' => $this->parentPageNumber,
            'left_page' => $this->leftPageNumber,
            'right_page' => $this->rightPageNumber,
            'divider_index' => $this->dividerIndex,
            'before_cells' => [
                'left' => $this->beforeLeftCellCount,
                'right' => $this->beforeRightCellCount,
            ],
            'after_cells' => [
                'left' => count($this->leftEntries),
                'right' => count($this->rightEntries),
            ],
            'moved_cell_count' => max(0, count($this->leftEntries) - $this->beforeLeftCellCount),
            'updated_parent_divider' => [
                'left_child' => $this->leftPageNumber,
                'key' => $this->newDividerKey,
            ],
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'removed_page_numbers' => [],
        ];
    }

    /**
     * @return list<array{rowid:int,payload:string}>
     */
    private static function leafEntries(string $page, SQLiteBTreePageHeader $header, int $usableSize): array
    {
        return array_map(
            static fn (SQLiteTableLeafCell $cell): array => [
                'rowid' => $cell->rowId,
                'payload' => $cell->payload,
            ],
            SQLiteTableLeafCell::parsePageCells($page, $header, $usableSize, static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount)),
        );
    }

    /**
     * @param list<array{rowid:int,payload:string}> $entries
     */
    private static function assertOrdered(array $entries): void
    {
        for ($index = 1, $count = count($entries); $index < $count; $index++) {
            if ($entries[$index - 1]['rowid'] >= $entries[$index]['rowid']) {
                throw new \InvalidArgumentException('SQLite table leaf balance requires ordered sibling rowids');
            }
        }
    }
}
