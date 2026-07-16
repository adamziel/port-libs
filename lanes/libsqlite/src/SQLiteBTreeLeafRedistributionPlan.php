<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeLeafRedistributionPlan
{
    /**
     * @param list<string> $leftCells
     * @param list<string> $rightCells
     * @param list<int> $leftKeys
     * @param list<int> $rightKeys
     * @param array<string, mixed> $divider
     */
    private function __construct(
        public readonly string $pageType,
        public readonly int $leftPageNumber,
        public readonly int $rightPageNumber,
        public readonly int $parentPageNumber,
        public readonly int $pageSize,
        public readonly int $usableSize,
        public readonly string $leftPage,
        public readonly string $rightPage,
        public readonly array $leftCells,
        public readonly array $rightCells,
        public readonly array $leftKeys,
        public readonly array $rightKeys,
        public readonly array $divider,
        public readonly int $beforeLeftCellCount,
        public readonly int $beforeRightCellCount,
        public readonly int $beforeLeftFreeSpaceBytes,
        public readonly int $beforeRightFreeSpaceBytes,
        public readonly int $afterLeftFreeSpaceBytes,
        public readonly int $afterRightFreeSpaceBytes,
    ) {
    }

    public static function tableLeaf(
        string $leftPage,
        string $rightPage,
        int $leftPageNumber,
        int $rightPageNumber,
        int $parentPageNumber,
        int $pageSize = 512,
        ?int $usableSize = null,
        int $headerOffset = 0,
    ): self {
        $usableSize ??= $pageSize;
        self::assertPageNumbers($leftPageNumber, $rightPageNumber, $parentPageNumber);
        self::assertPages($leftPage, $rightPage, $pageSize, $usableSize);

        $leftHeader = SQLiteBTreePageHeader::parsePage($leftPage, $pageSize, $headerOffset);
        $rightHeader = SQLiteBTreePageHeader::parsePage($rightPage, $pageSize, $headerOffset);
        if ($leftHeader->pageType !== 'table-leaf' || $rightHeader->pageType !== 'table-leaf') {
            throw new \InvalidArgumentException('SQLite b-tree table leaf redistribution requires two table leaf pages');
        }

        $leftParsedCells = SQLiteTableLeafCell::parsePageCells($leftPage, $leftHeader, $usableSize);
        $rightParsedCells = SQLiteTableLeafCell::parsePageCells($rightPage, $rightHeader, $usableSize);
        $allCells = array_merge($leftParsedCells, $rightParsedCells);
        $allKeys = array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $allCells);
        self::assertStrictlyIncreasing($allKeys, 'SQLite b-tree table leaf redistribution requires ordered rowids');

        [$leftSlice, $rightSlice] = self::splitCells($allCells);
        $newLeftCells = array_map(static fn (SQLiteTableLeafCell $cell): string => SQLiteTableLeafCell::encode($cell->rowId, $cell->payload, $usableSize), $leftSlice);
        $newRightCells = array_map(static fn (SQLiteTableLeafCell $cell): string => SQLiteTableLeafCell::encode($cell->rowId, $cell->payload, $usableSize), $rightSlice);
        $newLeftPage = SQLiteTableLeafPage::assemble($newLeftCells, $pageSize, $headerOffset, null, $usableSize);
        $newRightPage = SQLiteTableLeafPage::assemble($newRightCells, $pageSize, $headerOffset, null, $usableSize);
        $newLeftHeader = SQLiteBTreePageHeader::parsePage($newLeftPage, $pageSize, $headerOffset);
        $newRightHeader = SQLiteBTreePageHeader::parsePage($newRightPage, $pageSize, $headerOffset);
        $rightKeys = array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $rightSlice);

        return new self(
            'table-leaf',
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $pageSize,
            $usableSize,
            $newLeftPage,
            $newRightPage,
            $newLeftCells,
            $newRightCells,
            array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $leftSlice),
            $rightKeys,
            [
                'action' => 'replace-parent-divider',
                'old_separator_key' => $rightParsedCells[0]->rowId ?? null,
                'new_separator_key' => $rightKeys[0] ?? null,
            ],
            count($leftParsedCells),
            count($rightParsedCells),
            $leftHeader->freeSpaceBytes($leftPage),
            $rightHeader->freeSpaceBytes($rightPage),
            $newLeftHeader->freeSpaceBytes($newLeftPage),
            $newRightHeader->freeSpaceBytes($newRightPage),
        );
    }

    public static function indexLeaf(
        string $leftPage,
        string $rightPage,
        int $leftPageNumber,
        int $rightPageNumber,
        int $parentPageNumber,
        int $pageSize = 512,
        ?int $usableSize = null,
        int $headerOffset = 0,
        int $textEncoding = 1,
    ): self {
        $usableSize ??= $pageSize;
        self::assertPageNumbers($leftPageNumber, $rightPageNumber, $parentPageNumber);
        self::assertPages($leftPage, $rightPage, $pageSize, $usableSize);

        $leftHeader = SQLiteBTreePageHeader::parsePage($leftPage, $pageSize, $headerOffset);
        $rightHeader = SQLiteBTreePageHeader::parsePage($rightPage, $pageSize, $headerOffset);
        if ($leftHeader->pageType !== 'index-leaf' || $rightHeader->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite b-tree index leaf redistribution requires two index leaf pages');
        }

        $leftParsedCells = SQLiteIndexCell::parsePageCells($leftPage, $leftHeader, $usableSize);
        $rightParsedCells = SQLiteIndexCell::parsePageCells($rightPage, $rightHeader, $usableSize);
        $records = array_merge(
            array_map(static fn (SQLiteIndexCell $cell): SQLiteRecord => $cell->record($textEncoding), $leftParsedCells),
            array_map(static fn (SQLiteIndexCell $cell): SQLiteRecord => $cell->record($textEncoding), $rightParsedCells),
        );
        self::assertLexicographicOrder(array_map(static fn (SQLiteRecord $record): array => $record->values, $records));

        [$leftRecords, $rightRecords] = self::splitCells($records);
        $newLeftCells = array_map(static fn (SQLiteRecord $record): string => SQLiteIndexCell::encode(SQLiteRecord::encode($record->values, $textEncoding), $usableSize), $leftRecords);
        $newRightCells = array_map(static fn (SQLiteRecord $record): string => SQLiteIndexCell::encode(SQLiteRecord::encode($record->values, $textEncoding), $usableSize), $rightRecords);
        $newLeftPage = SQLiteIndexLeafPage::assemble($newLeftCells, $pageSize, $headerOffset, null, $usableSize);
        $newRightPage = SQLiteIndexLeafPage::assemble($newRightCells, $pageSize, $headerOffset, null, $usableSize);
        $newLeftHeader = SQLiteBTreePageHeader::parsePage($newLeftPage, $pageSize, $headerOffset);
        $newRightHeader = SQLiteBTreePageHeader::parsePage($newRightPage, $pageSize, $headerOffset);
        $rightValues = array_map(static fn (SQLiteRecord $record): array => $record->values, $rightRecords);

        return new self(
            'index-leaf',
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $pageSize,
            $usableSize,
            $newLeftPage,
            $newRightPage,
            $newLeftCells,
            $newRightCells,
            array_keys($leftRecords),
            array_keys($rightRecords),
            [
                'action' => 'replace-parent-divider',
                'old_separator_record' => $rightParsedCells[0]->record($textEncoding)->values ?? null,
                'new_separator_record' => $rightValues[0] ?? null,
            ],
            count($leftParsedCells),
            count($rightParsedCells),
            $leftHeader->freeSpaceBytes($leftPage),
            $rightHeader->freeSpaceBytes($rightPage),
            $newLeftHeader->freeSpaceBytes($newLeftPage),
            $newRightHeader->freeSpaceBytes($newRightPage),
        );
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        return [
            $this->leftPageNumber => $this->leftPage,
            $this->rightPageNumber => $this->rightPage,
        ];
    }

    /**
     * @return array{action:string,left_page:int,right_page:int,parent_page:int,before_cells:array{left:int,right:int},after_cells:array{left:int,right:int},moved_cell_count:int,before_free_space_bytes:array{left:int,right:int},after_free_space_bytes:array{left:int,right:int},delta_free_space_bytes:array{left:int,right:int},divider:array<string, mixed>}
     */
    public function rebalanceAction(): array
    {
        return [
            'action' => $this->pageType . '-sibling-redistribute',
            'left_page' => $this->leftPageNumber,
            'right_page' => $this->rightPageNumber,
            'parent_page' => $this->parentPageNumber,
            'before_cells' => [
                'left' => $this->beforeLeftCellCount,
                'right' => $this->beforeRightCellCount,
            ],
            'after_cells' => [
                'left' => count($this->leftCells),
                'right' => count($this->rightCells),
            ],
            'moved_cell_count' => $this->movedFromRightCount(),
            'before_free_space_bytes' => [
                'left' => $this->beforeLeftFreeSpaceBytes,
                'right' => $this->beforeRightFreeSpaceBytes,
            ],
            'after_free_space_bytes' => [
                'left' => $this->afterLeftFreeSpaceBytes,
                'right' => $this->afterRightFreeSpaceBytes,
            ],
            'delta_free_space_bytes' => [
                'left' => $this->afterLeftFreeSpaceBytes - $this->beforeLeftFreeSpaceBytes,
                'right' => $this->afterRightFreeSpaceBytes - $this->beforeRightFreeSpaceBytes,
            ],
            'divider' => $this->divider,
        ];
    }

    /**
     * @return array{page_type:string,left_page:int,right_page:int,parent_page:int,left_cell_count:int,right_cell_count:int,updated_page_numbers:list<int>,removed_page_numbers:list<int>,updated_parent_divider:array<string, mixed>,actions:list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'page_type' => $this->pageType,
            'left_page' => $this->leftPageNumber,
            'right_page' => $this->rightPageNumber,
            'parent_page' => $this->parentPageNumber,
            'left_cell_count' => count($this->leftCells),
            'right_cell_count' => count($this->rightCells),
            'updated_page_numbers' => [$this->leftPageNumber, $this->rightPageNumber],
            'removed_page_numbers' => [],
            'updated_parent_divider' => $this->divider,
            'actions' => [$this->rebalanceAction()],
        ];
    }

    private function movedFromRightCount(): int
    {
        return max(0, count($this->leftCells) - $this->beforeLeftCellCount);
    }

    /**
     * @template T
     * @param list<T> $cells
     * @return array{0:list<T>,1:list<T>}
     */
    private static function splitCells(array $cells): array
    {
        if (count($cells) < 3) {
            throw new \InvalidArgumentException('SQLite b-tree leaf redistribution requires at least three cells');
        }
        $leftCount = intdiv(count($cells) + 1, 2);

        return [
            array_slice($cells, 0, $leftCount),
            array_slice($cells, $leftCount),
        ];
    }

    private static function assertPageNumbers(int $leftPageNumber, int $rightPageNumber, int $parentPageNumber): void
    {
        if ($leftPageNumber < 1 || $rightPageNumber < 1 || $parentPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree leaf redistribution page numbers must be positive');
        }
        if ($leftPageNumber === $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite b-tree leaf redistribution requires distinct sibling pages');
        }
    }

    private static function assertPages(string $leftPage, string $rightPage, int $pageSize, int $usableSize): void
    {
        if (strlen($leftPage) !== $pageSize || strlen($rightPage) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree leaf redistribution page length does not match page size');
        }
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree leaf redistribution usable size is outside the page');
        }
    }

    /**
     * @param list<int> $keys
     */
    private static function assertStrictlyIncreasing(array $keys, string $message): void
    {
        for ($index = 1, $count = count($keys); $index < $count; $index++) {
            if ($keys[$index - 1] >= $keys[$index]) {
                throw new \InvalidArgumentException($message);
            }
        }
    }

    /**
     * @param list<list<mixed>> $records
     */
    private static function assertLexicographicOrder(array $records): void
    {
        for ($index = 1, $count = count($records); $index < $count; $index++) {
            if (self::compareRecordValues($records[$index - 1], $records[$index]) >= 0) {
                throw new \InvalidArgumentException('SQLite b-tree index leaf redistribution requires ordered records');
            }
        }
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function compareRecordValues(array $left, array $right): int
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
