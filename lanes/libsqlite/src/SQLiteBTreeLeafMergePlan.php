<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeLeafMergePlan
{
    /**
     * @param list<string> $mergedCells
     * @param list<int> $leftKeys
     * @param list<int> $rightKeys
     * @param list<int> $mergedKeys
     * @param array<string, mixed> $divider
     */
    private function __construct(
        public readonly string $pageType,
        public readonly int $leftPageNumber,
        public readonly int $rightPageNumber,
        public readonly int $parentPageNumber,
        public readonly int $pageSize,
        public readonly int $usableSize,
        public readonly string $mergedPage,
        public readonly array $mergedCells,
        public readonly array $leftKeys,
        public readonly array $rightKeys,
        public readonly array $mergedKeys,
        public readonly array $divider,
        public readonly int $beforeLeftFreeSpaceBytes,
        public readonly int $beforeRightFreeSpaceBytes,
        public readonly int $afterFreeSpaceBytes,
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
            throw new \InvalidArgumentException('SQLite b-tree table leaf merge requires two table leaf pages');
        }

        $leftCells = SQLiteTableLeafCell::parsePageCells($leftPage, $leftHeader, $usableSize);
        $rightCells = SQLiteTableLeafCell::parsePageCells($rightPage, $rightHeader, $usableSize);
        $mergedCells = array_merge(
            array_map(static fn (SQLiteTableLeafCell $cell): string => SQLiteTableLeafCell::encode($cell->rowId, $cell->payload, $usableSize), $leftCells),
            array_map(static fn (SQLiteTableLeafCell $cell): string => SQLiteTableLeafCell::encode($cell->rowId, $cell->payload, $usableSize), $rightCells),
        );
        $mergedKeys = array_merge(
            array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $leftCells),
            array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $rightCells),
        );
        self::assertStrictlyIncreasing($mergedKeys, 'SQLite b-tree table leaf merge requires ordered rowids');

        $mergedPage = SQLiteTableLeafPage::assemble($mergedCells, $pageSize, $headerOffset, null, $usableSize);
        $mergedHeader = SQLiteBTreePageHeader::parsePage($mergedPage, $pageSize, $headerOffset);

        return new self(
            'table-leaf',
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $pageSize,
            $usableSize,
            $mergedPage,
            $mergedCells,
            array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $leftCells),
            array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $rightCells),
            $mergedKeys,
            [
                'action' => 'remove-parent-divider',
                'old_separator_key' => $rightCells[0]->rowId ?? null,
                'new_separator_key' => $mergedKeys[0] ?? null,
            ],
            $leftHeader->freeSpaceBytes($leftPage),
            $rightHeader->freeSpaceBytes($rightPage),
            $mergedHeader->freeSpaceBytes($mergedPage),
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
            throw new \InvalidArgumentException('SQLite b-tree index leaf merge requires two index leaf pages');
        }

        $leftCells = SQLiteIndexCell::parsePageCells($leftPage, $leftHeader, $usableSize);
        $rightCells = SQLiteIndexCell::parsePageCells($rightPage, $rightHeader, $usableSize);
        $records = array_merge(
            array_map(static fn (SQLiteIndexCell $cell): SQLiteRecord => $cell->record($textEncoding), $leftCells),
            array_map(static fn (SQLiteIndexCell $cell): SQLiteRecord => $cell->record($textEncoding), $rightCells),
        );
        $mergedValues = array_map(static fn (SQLiteRecord $record): array => $record->values, $records);
        self::assertLexicographicOrder($mergedValues);

        $mergedCells = array_map(
            static fn (SQLiteRecord $record): string => SQLiteIndexCell::encode(SQLiteRecord::encode($record->values, $textEncoding), $usableSize),
            $records,
        );
        $mergedPage = SQLiteIndexLeafPage::assemble($mergedCells, $pageSize, $headerOffset, null, $usableSize);
        $mergedHeader = SQLiteBTreePageHeader::parsePage($mergedPage, $pageSize, $headerOffset);

        return new self(
            'index-leaf',
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $pageSize,
            $usableSize,
            $mergedPage,
            $mergedCells,
            array_keys($leftCells),
            array_keys($rightCells),
            array_keys($records),
            [
                'action' => 'remove-parent-divider',
                'old_separator_record' => $rightCells[0]->record($textEncoding)->values ?? null,
                'new_separator_record' => $mergedValues[0] ?? null,
            ],
            $leftHeader->freeSpaceBytes($leftPage),
            $rightHeader->freeSpaceBytes($rightPage),
            $mergedHeader->freeSpaceBytes($mergedPage),
        );
    }

    /**
     * @return array{action:string,page:int,before_cells:int,after_cells:int,before_free_space_bytes:int,after_free_space_bytes:int,delta_free_space_bytes:int,merged_from_pages:list<int>,obsolete_page:int,parent_page:int,divider:array<string, mixed>}
     */
    public function mergeAction(): array
    {
        return [
            'action' => $this->pageType . '-sibling-merge',
            'page' => $this->leftPageNumber,
            'before_cells' => count($this->leftKeys),
            'after_cells' => count($this->mergedCells),
            'before_free_space_bytes' => $this->beforeLeftFreeSpaceBytes,
            'after_free_space_bytes' => $this->afterFreeSpaceBytes,
            'delta_free_space_bytes' => $this->afterFreeSpaceBytes - $this->beforeLeftFreeSpaceBytes,
            'merged_from_pages' => [$this->leftPageNumber, $this->rightPageNumber],
            'obsolete_page' => $this->rightPageNumber,
            'parent_page' => $this->parentPageNumber,
            'divider' => $this->divider,
        ];
    }

    /**
     * @return array{action:string,page:int,parent_page:int,reason:string}
     */
    public function freePageAction(): array
    {
        return [
            'action' => 'free-page',
            'page' => $this->rightPageNumber,
            'parent_page' => $this->parentPageNumber,
            'reason' => 'right_sibling_merged_into_left',
        ];
    }

    /**
     * @return array{page_type:string,left_page:int,right_page:int,parent_page:int,merged_cell_count:int,obsolete_page:int,removed_divider:array<string, mixed>,before_free_space_bytes:array{left:int,right:int},after_free_space_bytes:int,actions:list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'page_type' => $this->pageType,
            'left_page' => $this->leftPageNumber,
            'right_page' => $this->rightPageNumber,
            'parent_page' => $this->parentPageNumber,
            'merged_cell_count' => count($this->mergedCells),
            'obsolete_page' => $this->rightPageNumber,
            'removed_divider' => $this->divider,
            'before_free_space_bytes' => [
                'left' => $this->beforeLeftFreeSpaceBytes,
                'right' => $this->beforeRightFreeSpaceBytes,
            ],
            'after_free_space_bytes' => $this->afterFreeSpaceBytes,
            'actions' => [$this->mergeAction(), $this->freePageAction()],
        ];
    }

    private static function assertPageNumbers(int $leftPageNumber, int $rightPageNumber, int $parentPageNumber): void
    {
        if ($leftPageNumber < 1 || $rightPageNumber < 1 || $parentPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree leaf merge page numbers must be positive');
        }
        if ($leftPageNumber === $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite b-tree leaf merge requires distinct sibling pages');
        }
    }

    private static function assertPages(string $leftPage, string $rightPage, int $pageSize, int $usableSize): void
    {
        if (strlen($leftPage) !== $pageSize || strlen($rightPage) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree leaf merge page length does not match page size');
        }
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree leaf merge usable size is outside the page');
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
                throw new \InvalidArgumentException('SQLite b-tree index leaf merge requires ordered records');
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
