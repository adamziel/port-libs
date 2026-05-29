<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeInteriorMergePlan
{
    /**
     * @param list<int> $mergedKeys
     * @param list<int> $mergedChildPageNumbers
     * @param array<int, array{type:int,parent_page_number:int}> $pointerMapUpdates
     */
    private function __construct(
        public readonly string $pageType,
        public readonly int $leftPageNumber,
        public readonly int $rightPageNumber,
        public readonly int $parentPageNumber,
        public readonly int $dividerKey,
        public readonly int $pageSize,
        public readonly int $usableSize,
        public readonly string $mergedPage,
        public readonly array $mergedKeys,
        public readonly array $mergedChildPageNumbers,
        public readonly int $beforeLeftCellCount,
        public readonly int $beforeRightCellCount,
        public readonly int $beforeLeftFreeSpaceBytes,
        public readonly int $beforeRightFreeSpaceBytes,
        public readonly int $afterFreeSpaceBytes,
        public readonly array $pointerMapUpdates,
    ) {
    }

    public static function tableInterior(
        string $leftPage,
        string $rightPage,
        int $leftPageNumber,
        int $rightPageNumber,
        int $parentPageNumber,
        int $dividerKey,
        int $pageSize = 512,
        ?int $usableSize = null,
        int $leftHeaderOffset = 0,
        int $rightHeaderOffset = 0,
    ): self {
        $usableSize ??= $pageSize;
        self::assertPageNumbers($leftPageNumber, $rightPageNumber, $parentPageNumber);
        self::assertPages($leftPage, $rightPage, $pageSize, $usableSize);

        $leftHeader = SQLiteBTreePageHeader::parsePage($leftPage, $pageSize, $leftHeaderOffset);
        $rightHeader = SQLiteBTreePageHeader::parsePage($rightPage, $pageSize, $rightHeaderOffset);
        if ($leftHeader->pageType !== 'table-interior' || $rightHeader->pageType !== 'table-interior') {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge requires two table interior pages');
        }
        if ($leftHeader->rightMostPointer === null || $rightHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge requires right-most child pointers');
        }

        $leftCells = SQLiteTableInteriorCell::parsePageCells($leftPage, $leftHeader);
        $rightCells = SQLiteTableInteriorCell::parsePageCells($rightPage, $rightHeader);
        $leftKeys = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $leftCells);
        $rightKeys = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $rightCells);
        self::assertStrictlyIncreasing(array_merge($leftKeys, [$dividerKey], $rightKeys));

        $leftChildren = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $leftCells);
        $leftChildren[] = $leftHeader->rightMostPointer;
        $rightChildren = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $rightCells);
        $rightChildren[] = $rightHeader->rightMostPointer;

        $mergedKeys = array_merge($leftKeys, [$dividerKey], $rightKeys);
        $mergedChildren = array_merge($leftChildren, $rightChildren);
        if (count($mergedChildren) !== count($mergedKeys) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge found an invalid child/key shape');
        }

        $mergedPage = self::assembleTableInteriorPage($mergedKeys, $mergedChildren, $pageSize, $leftHeaderOffset, $leftPage, $usableSize);
        $mergedHeader = SQLiteBTreePageHeader::parsePage($mergedPage, $pageSize, $leftHeaderOffset);

        $pointerMapUpdates = [];
        foreach ($mergedChildren as $childPageNumber) {
            $pointerMapUpdates[$childPageNumber] = [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $leftPageNumber,
            ];
        }
        ksort($pointerMapUpdates);

        return new self(
            'table-interior',
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $dividerKey,
            $pageSize,
            $usableSize,
            $mergedPage,
            $mergedKeys,
            $mergedChildren,
            $leftHeader->cellCount,
            $rightHeader->cellCount,
            $leftHeader->freeSpaceBytes($leftPage, $usableSize),
            $rightHeader->freeSpaceBytes($rightPage, $usableSize),
            $mergedHeader->freeSpaceBytes($mergedPage, $usableSize),
            $pointerMapUpdates,
        );
    }

    public static function indexInterior(
        string $leftPage,
        string $rightPage,
        int $leftPageNumber,
        int $rightPageNumber,
        int $parentPageNumber,
        string $dividerPayload,
        int $pageSize = 512,
        ?int $usableSize = null,
        int $leftHeaderOffset = 0,
        int $rightHeaderOffset = 0,
        ?callable $overflowReader = null,
        ?callable $overflowPageNumbers = null,
    ): self {
        $usableSize ??= $pageSize;
        self::assertPageNumbers($leftPageNumber, $rightPageNumber, $parentPageNumber);
        self::assertPages($leftPage, $rightPage, $pageSize, $usableSize);
        if ($dividerPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree index interior merge requires a divider payload');
        }

        $leftHeader = SQLiteBTreePageHeader::parsePage($leftPage, $pageSize, $leftHeaderOffset);
        $rightHeader = SQLiteBTreePageHeader::parsePage($rightPage, $pageSize, $rightHeaderOffset);
        if ($leftHeader->pageType !== 'index-interior' || $rightHeader->pageType !== 'index-interior') {
            throw new \InvalidArgumentException('SQLite b-tree index interior merge requires two index interior pages');
        }
        if ($leftHeader->rightMostPointer === null || $rightHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree index interior merge requires right-most child pointers');
        }

        $leftCells = SQLiteIndexCell::parsePageCells($leftPage, $leftHeader, $usableSize, $overflowReader);
        $rightCells = SQLiteIndexCell::parsePageCells($rightPage, $rightHeader, $usableSize, $overflowReader);
        $leftChildren = array_map(static fn (SQLiteIndexCell $cell): int => self::requireChildPage($cell), $leftCells);
        $leftChildren[] = $leftHeader->rightMostPointer;
        $rightChildren = array_map(static fn (SQLiteIndexCell $cell): int => self::requireChildPage($cell), $rightCells);
        $rightChildren[] = $rightHeader->rightMostPointer;

        $mergedEntries = array_merge(
            array_map(static fn (SQLiteIndexCell $cell): array => [
                'payload' => $cell->payload,
                'first_overflow_page' => $cell->firstOverflowPage,
            ], $leftCells),
            [[
                'payload' => $dividerPayload,
                'first_overflow_page' => null,
            ]],
            array_map(static fn (SQLiteIndexCell $cell): array => [
                'payload' => $cell->payload,
                'first_overflow_page' => $cell->firstOverflowPage,
            ], $rightCells),
        );
        $mergedChildren = array_merge($leftChildren, $rightChildren);
        if (count($mergedChildren) !== count($mergedEntries) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree index interior merge found an invalid child/key shape');
        }

        $mergedPage = self::assembleIndexInteriorEntries($mergedEntries, $mergedChildren, $pageSize, $leftHeaderOffset, $leftPage, $usableSize);
        $mergedHeader = SQLiteBTreePageHeader::parsePage($mergedPage, $pageSize, $leftHeaderOffset);

        $pointerMapUpdates = [];
        foreach ($mergedChildren as $childPageNumber) {
            $pointerMapUpdates[$childPageNumber] = [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $leftPageNumber,
            ];
        }
        foreach ($mergedEntries as $entry) {
            $firstOverflowPage = $entry['first_overflow_page'];
            if ($firstOverflowPage === null) {
                continue;
            }
            if ($overflowPageNumbers === null) {
                throw new \InvalidArgumentException('SQLite b-tree index interior merge overflow pointer-map updates require overflow page numbers');
            }
            $overflowPayloadBytes = strlen($entry['payload']) - SQLiteIndexCell::localPayloadLength(strlen($entry['payload']), $usableSize);
            $chainPages = $overflowPageNumbers($firstOverflowPage, $overflowPayloadBytes);
            if (!is_array($chainPages) || $chainPages === [] || $chainPages[0] !== $firstOverflowPage) {
                throw new \InvalidArgumentException('SQLite b-tree index interior merge overflow page numbers must start at the first overflow page');
            }
            $previousPage = $leftPageNumber;
            foreach ($chainPages as $chainIndex => $overflowPageNumber) {
                if (!is_int($overflowPageNumber) || $overflowPageNumber < 2) {
                    throw new \InvalidArgumentException('SQLite b-tree index interior merge overflow page numbers must be valid page numbers');
                }
                $pointerMapUpdates[$overflowPageNumber] = [
                    'type' => $chainIndex === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
                    'parent_page_number' => $previousPage,
                ];
                $previousPage = $overflowPageNumber;
            }
        }
        ksort($pointerMapUpdates);

        return new self(
            'index-interior',
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            count($leftCells),
            $pageSize,
            $usableSize,
            $mergedPage,
            array_map(static fn (array $entry): int => strlen($entry['payload']), $mergedEntries),
            $mergedChildren,
            $leftHeader->cellCount,
            $rightHeader->cellCount,
            $leftHeader->freeSpaceBytes($leftPage, $usableSize),
            $rightHeader->freeSpaceBytes($rightPage, $usableSize),
            $mergedHeader->freeSpaceBytes($mergedPage, $usableSize),
            $pointerMapUpdates,
        );
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        return [$this->leftPageNumber => $this->mergedPage];
    }

    /**
     * @return array{action:string,page:int,before_cells:int,after_cells:int,merged_from_pages:list<int>,obsolete_page:int,parent_page:int,divider_key:int,before_free_space_bytes:array{left:int,right:int},after_free_space_bytes:int,delta_free_space_bytes:int}
     */
    public function mergeAction(): array
    {
        return [
            'action' => $this->pageType . '-sibling-merge',
            'page' => $this->leftPageNumber,
            'before_cells' => $this->beforeLeftCellCount,
            'after_cells' => count($this->mergedKeys),
            'merged_from_pages' => [$this->leftPageNumber, $this->rightPageNumber],
            'obsolete_page' => $this->rightPageNumber,
            'parent_page' => $this->parentPageNumber,
            'divider_key' => $this->dividerKey,
            'before_free_space_bytes' => [
                'left' => $this->beforeLeftFreeSpaceBytes,
                'right' => $this->beforeRightFreeSpaceBytes,
            ],
            'after_free_space_bytes' => $this->afterFreeSpaceBytes,
            'delta_free_space_bytes' => $this->afterFreeSpaceBytes - $this->beforeLeftFreeSpaceBytes,
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
            'reason' => 'right_interior_sibling_merged_into_left',
        ];
    }

    /**
     * @return array{page_type:string,merged_page:int,obsolete_page:int,parent_page:int,merged_cell_count:int,merged_child_page_numbers:list<int>,removed_parent_divider:array{action:string,old_separator_key:int},pointer_map_update_pages:list<int>,updated_page_numbers:list<int>,removed_page_numbers:list<int>,actions:list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'page_type' => $this->pageType,
            'merged_page' => $this->leftPageNumber,
            'obsolete_page' => $this->rightPageNumber,
            'parent_page' => $this->parentPageNumber,
            'merged_cell_count' => count($this->mergedKeys),
            'merged_child_page_numbers' => $this->mergedChildPageNumbers,
            'removed_parent_divider' => [
                'action' => 'remove-parent-divider',
                'old_separator_key' => $this->dividerKey,
            ],
            'pointer_map_update_pages' => array_keys($this->pointerMapUpdates),
            'updated_page_numbers' => [$this->leftPageNumber],
            'removed_page_numbers' => [$this->rightPageNumber],
            'actions' => [$this->mergeAction(), $this->freePageAction()],
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
            throw new \InvalidArgumentException('SQLite b-tree table interior merge cannot assemble an invalid child/key shape');
        }
        $rightMostPointer = array_pop($children);
        if (!is_int($rightMostPointer)) {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge lost the right-most child pointer');
        }

        $cells = [];
        foreach ($keys as $index => $key) {
            $leftChildPage = $children[$index] ?? null;
            if (!is_int($leftChildPage)) {
                throw new \InvalidArgumentException('SQLite b-tree table interior merge lost a left child pointer');
            }
            $cells[] = SQLiteTableInteriorCell::encode($leftChildPage, $key);
        }

        return SQLiteTableInteriorPage::assemble($cells, $rightMostPointer, $pageSize, $headerOffset, $basePage, $usableSize);
    }

    /**
     * @param list<string> $payloads
     * @param list<int> $children
     */
    private static function assembleIndexInteriorPage(
        array $payloads,
        array $children,
        int $pageSize,
        int $headerOffset,
        string $basePage,
        int $usableSize,
    ): string {
        if (count($children) !== count($payloads) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree index interior merge cannot assemble an invalid child/key shape');
        }
        $rightMostPointer = array_pop($children);
        if (!is_int($rightMostPointer)) {
            throw new \InvalidArgumentException('SQLite b-tree index interior merge lost the right-most child pointer');
        }

        $cells = [];
        foreach ($payloads as $index => $payload) {
            $leftChildPage = $children[$index] ?? null;
            if (!is_int($leftChildPage)) {
                throw new \InvalidArgumentException('SQLite b-tree index interior merge lost a left child pointer');
            }
            $cells[] = SQLiteIndexCell::encode($payload, $usableSize, null, $leftChildPage);
        }

        return SQLiteIndexInteriorPage::assemble($cells, $rightMostPointer, $pageSize, $headerOffset, $basePage, $usableSize);
    }

    /**
     * @param list<array{payload:string,first_overflow_page:?int}> $entries
     * @param list<int> $children
     */
    private static function assembleIndexInteriorEntries(
        array $entries,
        array $children,
        int $pageSize,
        int $headerOffset,
        string $basePage,
        int $usableSize,
    ): string {
        if (count($children) !== count($entries) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree index interior merge cannot assemble an invalid child/key shape');
        }
        $rightMostPointer = array_pop($children);
        if (!is_int($rightMostPointer)) {
            throw new \InvalidArgumentException('SQLite b-tree index interior merge lost the right-most child pointer');
        }

        $cells = [];
        foreach ($entries as $index => $entry) {
            $leftChildPage = $children[$index] ?? null;
            if (!is_int($leftChildPage)) {
                throw new \InvalidArgumentException('SQLite b-tree index interior merge lost a left child pointer');
            }
            $cells[] = SQLiteIndexCell::encode($entry['payload'], $usableSize, $entry['first_overflow_page'], $leftChildPage);
        }

        return SQLiteIndexInteriorPage::assemble($cells, $rightMostPointer, $pageSize, $headerOffset, $basePage, $usableSize);
    }

    private static function requireChildPage(SQLiteIndexCell $cell): int
    {
        if ($cell->leftChildPage === null) {
            throw new \InvalidArgumentException('SQLite b-tree index interior merge requires index interior child pointers');
        }

        return $cell->leftChildPage;
    }

    private static function assertPageNumbers(int $leftPageNumber, int $rightPageNumber, int $parentPageNumber): void
    {
        if ($leftPageNumber < 1 || $rightPageNumber < 1 || $parentPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge page numbers must be positive');
        }
        if ($leftPageNumber === $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge requires distinct sibling pages');
        }
        if ($parentPageNumber === $leftPageNumber || $parentPageNumber === $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge parent must be distinct from siblings');
        }
    }

    private static function assertPages(string $leftPage, string $rightPage, int $pageSize, int $usableSize): void
    {
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge page size must be a power of two between 512 and 65536 bytes');
        }
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge usable size is outside the page');
        }
        if (strlen($leftPage) !== $pageSize || strlen($rightPage) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree table interior merge pages must match the page size');
        }
    }

    /**
     * @param list<int> $keys
     */
    private static function assertStrictlyIncreasing(array $keys): void
    {
        $previous = null;
        foreach ($keys as $key) {
            if ($previous !== null && $key <= $previous) {
                throw new \InvalidArgumentException('SQLite b-tree table interior merge keys must be strictly increasing across siblings');
            }
            $previous = $key;
        }
    }
}
