<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeInteriorRedistributionPlan
{
    /**
     * @param list<int|string> $leftKeys
     * @param list<int|string> $rightKeys
     * @param list<int> $leftChildPageNumbers
     * @param list<int> $rightChildPageNumbers
     * @param list<int> $movedChildPageNumbers
     * @param array<int, array{type:int,parent_page_number:int}> $pointerMapUpdates
     * @param list<mixed> $oldDividerValues
     * @param list<mixed> $newDividerValues
     */
    private function __construct(
        public readonly string $pageType,
        public readonly int $leftPageNumber,
        public readonly int $rightPageNumber,
        public readonly int $parentPageNumber,
        public readonly int|string $oldDividerKey,
        public readonly int|string $newDividerKey,
        public readonly int $pageSize,
        public readonly int $usableSize,
        public readonly string $leftPage,
        public readonly string $rightPage,
        public readonly array $leftKeys,
        public readonly array $rightKeys,
        public readonly array $leftChildPageNumbers,
        public readonly array $rightChildPageNumbers,
        public readonly array $movedChildPageNumbers,
        public readonly int $beforeLeftCellCount,
        public readonly int $beforeRightCellCount,
        public readonly int $beforeLeftFreeSpaceBytes,
        public readonly int $beforeRightFreeSpaceBytes,
        public readonly int $afterLeftFreeSpaceBytes,
        public readonly int $afterRightFreeSpaceBytes,
        public readonly array $pointerMapUpdates,
        public readonly array $oldDividerValues = [],
        public readonly array $newDividerValues = [],
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
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution requires two table interior pages');
        }
        if ($leftHeader->rightMostPointer === null || $rightHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution requires right-most child pointers');
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

        $combinedKeys = array_merge($leftKeys, [$dividerKey], $rightKeys);
        $combinedChildren = array_merge($leftChildren, $rightChildren);
        if (count($combinedChildren) !== count($combinedKeys) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution found an invalid child/key shape');
        }
        if (count($combinedKeys) < 3) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution requires at least three separator keys');
        }

        $newLeftKeyCount = intdiv(count($combinedKeys), 2);
        if ($newLeftKeyCount < 1 || $newLeftKeyCount >= count($combinedKeys)) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution cannot split separator keys');
        }

        $newLeftKeys = array_slice($combinedKeys, 0, $newLeftKeyCount);
        $newDividerKey = $combinedKeys[$newLeftKeyCount];
        $newRightKeys = array_slice($combinedKeys, $newLeftKeyCount + 1);
        $newLeftChildren = array_slice($combinedChildren, 0, $newLeftKeyCount + 1);
        $newRightChildren = array_slice($combinedChildren, $newLeftKeyCount + 1);

        $newLeftPage = self::assembleTableInteriorPage($newLeftKeys, $newLeftChildren, $pageSize, $leftHeaderOffset, $leftPage, $usableSize);
        $newRightPage = self::assembleTableInteriorPage($newRightKeys, $newRightChildren, $pageSize, $rightHeaderOffset, $rightPage, $usableSize);
        $newLeftHeader = SQLiteBTreePageHeader::parsePage($newLeftPage, $pageSize, $leftHeaderOffset);
        $newRightHeader = SQLiteBTreePageHeader::parsePage($newRightPage, $pageSize, $rightHeaderOffset);

        $oldLeftChildren = array_flip($leftChildren);
        $movedChildPageNumbers = [];
        foreach ($newLeftChildren as $childPageNumber) {
            if (!isset($oldLeftChildren[$childPageNumber])) {
                $movedChildPageNumbers[] = $childPageNumber;
            }
        }

        $pointerMapUpdates = [];
        foreach ($newLeftChildren as $childPageNumber) {
            $pointerMapUpdates[$childPageNumber] = [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $leftPageNumber,
            ];
        }
        foreach ($newRightChildren as $childPageNumber) {
            $pointerMapUpdates[$childPageNumber] = [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $rightPageNumber,
            ];
        }
        ksort($pointerMapUpdates);

        return new self(
            'table-interior',
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            $dividerKey,
            $newDividerKey,
            $pageSize,
            $usableSize,
            $newLeftPage,
            $newRightPage,
            $newLeftKeys,
            $newRightKeys,
            $newLeftChildren,
            $newRightChildren,
            $movedChildPageNumbers,
            $leftHeader->cellCount,
            $rightHeader->cellCount,
            $leftHeader->freeSpaceBytes($leftPage, $usableSize),
            $rightHeader->freeSpaceBytes($rightPage, $usableSize),
            $newLeftHeader->freeSpaceBytes($newLeftPage, $usableSize),
            $newRightHeader->freeSpaceBytes($newRightPage, $usableSize),
            $pointerMapUpdates,
            [$dividerKey],
            [$newDividerKey],
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
    ): self {
        $usableSize ??= $pageSize;
        self::assertPageNumbers($leftPageNumber, $rightPageNumber, $parentPageNumber);
        self::assertPages($leftPage, $rightPage, $pageSize, $usableSize);
        if ($dividerPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree index interior redistribution requires a divider payload');
        }

        $leftHeader = SQLiteBTreePageHeader::parsePage($leftPage, $pageSize, $leftHeaderOffset);
        $rightHeader = SQLiteBTreePageHeader::parsePage($rightPage, $pageSize, $rightHeaderOffset);
        if ($leftHeader->pageType !== 'index-interior' || $rightHeader->pageType !== 'index-interior') {
            throw new \InvalidArgumentException('SQLite b-tree index interior redistribution requires two index interior pages');
        }
        if ($leftHeader->rightMostPointer === null || $rightHeader->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite b-tree index interior redistribution requires right-most child pointers');
        }

        $leftCells = SQLiteIndexCell::parsePageCells($leftPage, $leftHeader, $usableSize);
        $rightCells = SQLiteIndexCell::parsePageCells($rightPage, $rightHeader, $usableSize);
        $leftChildren = array_map(static fn (SQLiteIndexCell $cell): int => self::requireIndexChildPage($cell), $leftCells);
        $leftChildren[] = $leftHeader->rightMostPointer;
        $rightChildren = array_map(static fn (SQLiteIndexCell $cell): int => self::requireIndexChildPage($cell), $rightCells);
        $rightChildren[] = $rightHeader->rightMostPointer;

        $combinedPayloads = array_merge(
            array_map(static fn (SQLiteIndexCell $cell): string => $cell->payload, $leftCells),
            [$dividerPayload],
            array_map(static fn (SQLiteIndexCell $cell): string => $cell->payload, $rightCells),
        );
        $combinedChildren = array_merge($leftChildren, $rightChildren);
        if (count($combinedChildren) !== count($combinedPayloads) + 1) {
            throw new \InvalidArgumentException('SQLite b-tree index interior redistribution found an invalid child/key shape');
        }
        if (count($combinedPayloads) < 3) {
            throw new \InvalidArgumentException('SQLite b-tree index interior redistribution requires at least three separator payloads');
        }

        $newLeftPayloadCount = intdiv(count($combinedPayloads), 2);
        if ($newLeftPayloadCount < 1 || $newLeftPayloadCount >= count($combinedPayloads)) {
            throw new \InvalidArgumentException('SQLite b-tree index interior redistribution cannot split separator payloads');
        }

        $newLeftPayloads = array_slice($combinedPayloads, 0, $newLeftPayloadCount);
        $newDividerPayload = $combinedPayloads[$newLeftPayloadCount];
        $newRightPayloads = array_slice($combinedPayloads, $newLeftPayloadCount + 1);
        $newLeftChildren = array_slice($combinedChildren, 0, $newLeftPayloadCount + 1);
        $newRightChildren = array_slice($combinedChildren, $newLeftPayloadCount + 1);

        $newLeftPage = self::assembleIndexInteriorPage($newLeftPayloads, $newLeftChildren, $pageSize, $leftHeaderOffset, $leftPage, $usableSize);
        $newRightPage = self::assembleIndexInteriorPage($newRightPayloads, $newRightChildren, $pageSize, $rightHeaderOffset, $rightPage, $usableSize);
        $newLeftHeader = SQLiteBTreePageHeader::parsePage($newLeftPage, $pageSize, $leftHeaderOffset);
        $newRightHeader = SQLiteBTreePageHeader::parsePage($newRightPage, $pageSize, $rightHeaderOffset);

        $oldLeftChildren = array_flip($leftChildren);
        $movedChildPageNumbers = [];
        foreach ($newLeftChildren as $childPageNumber) {
            if (!isset($oldLeftChildren[$childPageNumber])) {
                $movedChildPageNumbers[] = $childPageNumber;
            }
        }

        $pointerMapUpdates = [];
        foreach ($newLeftChildren as $childPageNumber) {
            $pointerMapUpdates[$childPageNumber] = [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $leftPageNumber,
            ];
        }
        foreach ($newRightChildren as $childPageNumber) {
            $pointerMapUpdates[$childPageNumber] = [
                'type' => SQLitePointerMapEntry::BTREE_PAGE,
                'parent_page_number' => $rightPageNumber,
            ];
        }
        ksort($pointerMapUpdates);

        return new self(
            'index-interior',
            $leftPageNumber,
            $rightPageNumber,
            $parentPageNumber,
            strlen($dividerPayload),
            strlen($newDividerPayload),
            $pageSize,
            $usableSize,
            $newLeftPage,
            $newRightPage,
            $newLeftPayloads,
            $newRightPayloads,
            $newLeftChildren,
            $newRightChildren,
            $movedChildPageNumbers,
            $leftHeader->cellCount,
            $rightHeader->cellCount,
            $leftHeader->freeSpaceBytes($leftPage, $usableSize),
            $rightHeader->freeSpaceBytes($rightPage, $usableSize),
            $newLeftHeader->freeSpaceBytes($newLeftPage, $usableSize),
            $newRightHeader->freeSpaceBytes($newRightPage, $usableSize),
            $pointerMapUpdates,
            SQLiteRecord::parse($dividerPayload)->values,
            SQLiteRecord::parse($newDividerPayload)->values,
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
     * @return array{action:string,left_page:int,right_page:int,parent_page:int,before_cells:array{left:int,right:int},after_cells:array{left:int,right:int},moved_child_page_numbers:list<int>,old_divider_key:int,new_divider_key:int,before_free_space_bytes:array{left:int,right:int},after_free_space_bytes:array{left:int,right:int},delta_free_space_bytes:array{left:int,right:int}}
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
                'left' => count($this->leftKeys),
                'right' => count($this->rightKeys),
            ],
            'moved_child_page_numbers' => $this->movedChildPageNumbers,
            'old_divider_key' => $this->oldDividerKey,
            'new_divider_key' => $this->newDividerKey,
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
        ];
    }

    /**
     * @return array{page_type:string,left_page:int,right_page:int,parent_page:int,left_cell_count:int,right_cell_count:int,updated_page_numbers:list<int>,removed_page_numbers:list<int>,updated_parent_divider:array{action:string,old_separator_key:int,new_separator_key:int},pointer_map_update_pages:list<int>,actions:list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'page_type' => $this->pageType,
            'left_page' => $this->leftPageNumber,
            'right_page' => $this->rightPageNumber,
            'parent_page' => $this->parentPageNumber,
            'left_cell_count' => count($this->leftKeys),
            'right_cell_count' => count($this->rightKeys),
            'updated_page_numbers' => [$this->leftPageNumber, $this->rightPageNumber],
            'removed_page_numbers' => [],
            'updated_parent_divider' => [
                'action' => 'replace-parent-divider',
                'old_separator_key' => $this->oldDividerKey,
                'new_separator_key' => $this->newDividerKey,
                'old_separator_values' => $this->oldDividerValues,
                'new_separator_values' => $this->newDividerValues,
            ],
            'pointer_map_update_pages' => array_keys($this->pointerMapUpdates),
            'actions' => [$this->rebalanceAction()],
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
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution cannot assemble an invalid child/key shape');
        }
        $rightMostPointer = array_pop($children);
        if (!is_int($rightMostPointer)) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution lost the right-most child pointer');
        }

        $cells = [];
        foreach ($keys as $index => $key) {
            $leftChildPage = $children[$index] ?? null;
            if (!is_int($leftChildPage)) {
                throw new \InvalidArgumentException('SQLite b-tree table interior redistribution lost a left child pointer');
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
            throw new \InvalidArgumentException('SQLite b-tree index interior redistribution cannot assemble an invalid child/key shape');
        }
        $rightMostPointer = array_pop($children);
        if (!is_int($rightMostPointer)) {
            throw new \InvalidArgumentException('SQLite b-tree index interior redistribution lost the right-most child pointer');
        }

        $cells = [];
        foreach ($payloads as $index => $payload) {
            $leftChildPage = $children[$index] ?? null;
            if (!is_int($leftChildPage)) {
                throw new \InvalidArgumentException('SQLite b-tree index interior redistribution lost a left child pointer');
            }
            $cells[] = SQLiteIndexCell::encode($payload, $usableSize, null, $leftChildPage);
        }

        return SQLiteIndexInteriorPage::assemble($cells, $rightMostPointer, $pageSize, $headerOffset, $basePage, $usableSize);
    }

    private static function requireIndexChildPage(SQLiteIndexCell $cell): int
    {
        if ($cell->leftChildPage === null) {
            throw new \InvalidArgumentException('SQLite b-tree index interior redistribution requires index interior child pointers');
        }

        return $cell->leftChildPage;
    }

    private static function assertPageNumbers(int $leftPageNumber, int $rightPageNumber, int $parentPageNumber): void
    {
        if ($leftPageNumber < 1 || $rightPageNumber < 1 || $parentPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution page numbers must be positive');
        }
        if ($leftPageNumber === $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution requires distinct sibling pages');
        }
        if ($parentPageNumber === $leftPageNumber || $parentPageNumber === $rightPageNumber) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution parent must be distinct from siblings');
        }
    }

    private static function assertPages(string $leftPage, string $rightPage, int $pageSize, int $usableSize): void
    {
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution page size must be a power of two between 512 and 65536 bytes');
        }
        if ($usableSize < 480 || $usableSize > $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution usable size is outside the page');
        }
        if (strlen($leftPage) !== $pageSize || strlen($rightPage) !== $pageSize) {
            throw new \InvalidArgumentException('SQLite b-tree table interior redistribution pages must match the page size');
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
                throw new \InvalidArgumentException('SQLite b-tree table interior redistribution requires ordered separator keys');
            }
            $previous = $key;
        }
    }
}
