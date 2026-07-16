<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeIndexLeafBalanceApplyPlan
{
    /**
     * @param list<array{values:list<mixed>,payload:string}> $leftEntries
     * @param list<array{values:list<mixed>,payload:string}> $rightEntries
     * @param array{values:list<mixed>,payload:string,leftChild:int} $dividerEntry
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
        public readonly array $dividerEntry,
        public readonly array $pageImages,
    ) {
    }

    public static function apply(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $leftPageNumber,
        int $rightPageNumber,
        int $dividerIndex,
        int $textEncoding = 1,
    ): self {
        if ($parentPageNumber < 1 || $leftPageNumber < 1 || $rightPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite index leaf balance page numbers must be positive');
        }
        if ($leftPageNumber === $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite index leaf balance requires distinct sibling pages');
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
        if ($parentHeader->pageType !== 'index-interior' || $parentHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite index leaf balance requires an index-interior parent page');
        }
        if ($leftHeader->pageType !== 'index-leaf' || $rightHeader->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite index leaf balance requires two index-leaf sibling pages');
        }

        $parentCells = SQLiteIndexCell::parsePageCells($parentPage, $parentHeader, $usableSize);
        $childPages = [];
        $parentEntries = [];
        foreach ($parentCells as $cell) {
            if ($cell->leftChildPage === null) {
                throw new \InvalidArgumentException('SQLite index leaf balance found a parent divider without a left child');
            }
            $childPages[] = $cell->leftChildPage;
            $parentEntries[] = [
                'values' => $cell->record($textEncoding)->values,
                'payload' => $cell->payload,
                'leftChild' => $cell->leftChildPage,
            ];
        }
        $childPages[] = $parentHeader->rightMostPointer;
        if (!isset($childPages[$dividerIndex], $childPages[$dividerIndex + 1], $parentEntries[$dividerIndex])) {
            throw new \InvalidArgumentException('SQLite index leaf balance divider index is outside the parent child list');
        }
        if ($childPages[$dividerIndex] !== $leftPageNumber || $childPages[$dividerIndex + 1] !== $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite index leaf balance sibling pages must flank the selected parent divider');
        }

        $leftEntries = self::leafEntries($leftPage, $leftHeader, $usableSize, $textEncoding);
        $rightEntries = self::leafEntries($rightPage, $rightHeader, $usableSize, $textEncoding);
        $combined = array_merge(
            $leftEntries,
            [[
                'values' => $parentEntries[$dividerIndex]['values'],
                'payload' => $parentEntries[$dividerIndex]['payload'],
            ]],
            $rightEntries,
        );
        self::assertOrdered($combined);

        $newDividerIndex = intdiv(count($combined), 2);
        if ($newDividerIndex < 1 || $newDividerIndex >= count($combined) - 1) {
            throw new \InvalidArgumentException('SQLite index leaf balance requires enough cells on both sides of the divider');
        }

        $newLeftEntries = array_slice($combined, 0, $newDividerIndex);
        $newDividerEntry = $combined[$newDividerIndex];
        $newRightEntries = array_slice($combined, $newDividerIndex + 1);
        $parentEntries[$dividerIndex] = [
            'values' => $newDividerEntry['values'],
            'payload' => $newDividerEntry['payload'],
            'leftChild' => $leftPageNumber,
        ];

        $newLeftPage = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => SQLiteIndexCell::encode($entry['payload'], $usableSize), $newLeftEntries),
            $pageSize,
            $leftHeaderOffset,
            $leftPage,
            $usableSize,
        );
        $newRightPage = SQLiteIndexLeafPage::assemble(
            array_map(static fn (array $entry): string => SQLiteIndexCell::encode($entry['payload'], $usableSize), $newRightEntries),
            $pageSize,
            $rightHeaderOffset,
            $rightPage,
            $usableSize,
        );
        $newParentPage = SQLiteIndexInteriorPage::assemble(
            array_map(
                static fn (array $entry): string => SQLiteIndexCell::encode($entry['payload'], $usableSize, leftChildPage: $entry['leftChild']),
                $parentEntries,
            ),
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
            [
                'values' => $newDividerEntry['values'],
                'payload' => $newDividerEntry['payload'],
                'leftChild' => $leftPageNumber,
            ],
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
            'action' => 'index-leaf-balance-apply',
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
                'left_child' => $this->dividerEntry['leftChild'],
                'record_values' => $this->dividerEntry['values'],
            ],
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'removed_page_numbers' => [],
        ];
    }

    /**
     * @return list<array{values:list<mixed>,payload:string}>
     */
    private static function leafEntries(string $page, SQLiteBTreePageHeader $header, int $usableSize, int $textEncoding): array
    {
        return array_map(
            static fn (SQLiteIndexCell $cell): array => [
                'values' => $cell->record($textEncoding)->values,
                'payload' => $cell->payload,
            ],
            SQLiteIndexCell::parsePageCells($page, $header, $usableSize),
        );
    }

    /**
     * @param list<array{values:list<mixed>,payload:string}> $entries
     */
    private static function assertOrdered(array $entries): void
    {
        for ($index = 1, $count = count($entries); $index < $count; $index++) {
            if (self::compareValues($entries[$index - 1]['values'], $entries[$index]['values']) >= 0) {
                throw new \InvalidArgumentException('SQLite index leaf balance requires ordered sibling/divider records');
            }
        }
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function compareValues(array $left, array $right): int
    {
        $count = min(count($left), count($right));
        for ($index = 0; $index < $count; $index++) {
            $comparison = self::compareValue($left[$index], $right[$index]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return count($left) <=> count($right);
    }

    private static function compareValue(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if (is_int($left) && is_int($right)) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }
}
