<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeInteriorFreelistRebalancePlan
{
    /**
     * @param list<int> $obsoleteChildPageNumbers
     * @param list<int> $obsoleteOverflowPageNumbers
     * @param list<array{offset:int,size:int,end_offset:int,next_offset:?int}> $interiorFreeblocks
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly int $interiorPageNumber,
        public readonly string $interiorPageType,
        public readonly int $rightMostPointer,
        public readonly int $interiorCellCount,
        public readonly int $interiorFreeblockBytes,
        public readonly int $interiorFreeSpaceBytes,
        public readonly array $interiorFreeblocks,
        public readonly array $obsoleteChildPageNumbers,
        public readonly array $obsoleteOverflowPageNumbers,
        public readonly SQLiteFreelistFreePlan $freePlan,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableInteriorFromDeleteResult(
        SQLiteDatabase $database,
        int $interiorPageNumber,
        array $deleteResult,
        bool $secureDelete = false,
    ): self {
        return self::fromDeleteResult($database, $interiorPageNumber, 'table-interior', $deleteResult, $secureDelete);
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function indexInteriorFromDeleteResult(
        SQLiteDatabase $database,
        int $interiorPageNumber,
        array $deleteResult,
        bool $secureDelete = false,
    ): self {
        return self::fromDeleteResult($database, $interiorPageNumber, 'index-interior', $deleteResult, $secureDelete);
    }

    /**
     * @return list<int>
     */
    public function releasedPageNumbers(): array
    {
        return array_merge($this->obsoleteChildPageNumbers, $this->obsoleteOverflowPageNumbers);
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
            'action' => 'btree-interior-freelist-rebalance',
            'interior_page' => $this->interiorPageNumber,
            'interior_page_type' => $this->interiorPageType,
            'right_most_pointer' => $this->rightMostPointer,
            'interior_cell_count' => $this->interiorCellCount,
            'interior_freeblock_bytes' => $this->interiorFreeblockBytes,
            'interior_free_space_bytes' => $this->interiorFreeSpaceBytes,
            'interior_freeblocks' => $this->interiorFreeblocks,
            'obsolete_child_pages' => $this->obsoleteChildPageNumbers,
            'obsolete_overflow_pages' => $this->obsoleteOverflowPageNumbers,
            'released_pages' => $this->releasedPageNumbers(),
            'freelist_page_count' => $this->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->freePlan->firstFreelistTrunkPage,
            'new_freelist_trunk_pages' => $this->freePlan->newTrunkPageNumbers,
            'freelist_leaf_pages' => $this->freePlan->leafPageNumbers,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->freePlan->clearedPageNumbers,
        ];
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    private static function fromDeleteResult(
        SQLiteDatabase $database,
        int $interiorPageNumber,
        string $expectedPageType,
        array $deleteResult,
        bool $secureDelete,
    ): self {
        if ($interiorPageNumber < 2 || $interiorPageNumber > $database->pageCount()) {
            throw new \InvalidArgumentException('SQLite interior freelist rebalance page is outside the database image');
        }

        $page = $deleteResult['page'] ?? null;
        if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
            throw new \InvalidArgumentException('SQLite interior freelist rebalance requires a deleted interior page image');
        }

        $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize);
        if ($header->pageType !== $expectedPageType) {
            throw new \InvalidArgumentException("SQLite interior freelist rebalance expected {$expectedPageType} page image");
        }
        if ($header->rightMostPointer === null) {
            throw new \InvalidArgumentException('SQLite interior freelist rebalance requires an interior right-most pointer');
        }
        if ($header->cellCount === 0) {
            throw new \InvalidArgumentException('SQLite interior freelist rebalance keeps non-empty interiors; empty interiors should use root-collapse or empty-page release planning');
        }

        $freeblocks = [];
        $freeblockBytes = 0;
        foreach ($header->freeblocks($page, $database->usablePageSize()) as $freeblock) {
            $freeblocks[] = $freeblock->toArray();
            $freeblockBytes += $freeblock->size;
        }
        if ($freeblocks === []) {
            throw new \InvalidArgumentException('SQLite interior freelist rebalance requires at least one reusable interior freeblock');
        }

        $obsoleteChildPages = self::normalizePageList($deleteResult, 'obsolete_child_page_numbers', 'child');
        $obsoleteOverflowPages = self::normalizeOptionalPageList($deleteResult, 'obsolete_overflow_page_numbers', 'overflow');
        if ($obsoleteChildPages === []) {
            throw new \InvalidArgumentException('SQLite interior freelist rebalance requires at least one obsolete child page');
        }

        $releasedPageNumbers = array_merge($obsoleteChildPages, $obsoleteOverflowPages);
        $seen = [];
        foreach ($releasedPageNumbers as $pageNumber) {
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite interior freelist rebalance page {$pageNumber} appears more than once");
            }
            $seen[$pageNumber] = true;
        }

        $freePlan = $database->planPageFreeList($releasedPageNumbers, $secureDelete);
        $pageImages = $freePlan->pageImages();
        $pageImages[$interiorPageNumber] = $page;
        ksort($pageImages);

        return new self(
            $interiorPageNumber,
            $expectedPageType,
            $header->rightMostPointer,
            $header->cellCount,
            $freeblockBytes,
            $header->freeSpaceBytes($page, $database->usablePageSize()),
            $freeblocks,
            $obsoleteChildPages,
            $obsoleteOverflowPages,
            $freePlan,
            $pageImages,
        );
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @return list<int>
     */
    private static function normalizePageList(array $deleteResult, string $key, string $label): array
    {
        $pageNumbers = $deleteResult[$key] ?? null;
        if (!is_array($pageNumbers)) {
            throw new \InvalidArgumentException("SQLite interior freelist rebalance requires obsolete {$label} page numbers");
        }

        return self::normalizePageNumbers($pageNumbers, $label);
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @return list<int>
     */
    private static function normalizeOptionalPageList(array $deleteResult, string $key, string $label): array
    {
        $pageNumbers = $deleteResult[$key] ?? [];
        if (!is_array($pageNumbers)) {
            throw new \InvalidArgumentException("SQLite interior freelist rebalance obsolete {$label} page numbers must be an array");
        }

        return self::normalizePageNumbers($pageNumbers, $label);
    }

    /**
     * @param array<mixed> $pageNumbers
     * @return list<int>
     */
    private static function normalizePageNumbers(array $pageNumbers, string $label): array
    {
        $normalized = [];
        $seen = [];
        foreach (array_values($pageNumbers) as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException("SQLite interior freelist rebalance {$label} page numbers must be integers");
            }
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite interior freelist rebalance {$label} page {$pageNumber} appears more than once");
            }
            $seen[$pageNumber] = true;
            $normalized[] = $pageNumber;
        }

        return $normalized;
    }
}
