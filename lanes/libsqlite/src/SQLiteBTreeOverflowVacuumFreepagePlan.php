<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowVacuumFreepagePlan
{
    /**
     * @param array<int, string> $currentPageImages
     * @param list<int> $currentFreelistPages
     * @param list<int> $nextAllocationOrder
     * @param list<array{source:string,pages:list<int>,count:int}> $sources
     */
    private function __construct(
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $currentDatabase,
        public readonly array $currentPageImages,
        public readonly array $currentFreelistPages,
        public readonly array $nextAllocationOrder,
        public readonly array $sources,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        bool $secureDelete = false,
        ?int $nextAllocationLimit = null,
    ): self {
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, $deleteResults, $secureDelete);

        return self::fromReleasePlan($database, $releasePlan, $nextAllocationLimit);
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $chains
     */
    public static function fromOverflowChains(
        SQLiteDatabase $database,
        array $chains,
        bool $secureDelete = false,
        ?int $nextAllocationLimit = null,
    ): self {
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromOverflowChains($database, $chains, $secureDelete);

        return self::fromReleasePlan($database, $releasePlan, $nextAllocationLimit);
    }

    public function currentFirstFreelistTrunkPage(): int
    {
        return $this->currentDatabase->header->firstFreelistTrunkPage;
    }

    public function currentFreelistPageCount(): int
    {
        return $this->currentDatabase->header->freelistPageCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-vacuum-freepage-current-next',
            'sources' => $this->sources,
            'released_overflow_pages' => $this->releasePlan->releasedOverflowPages,
            'current_first_freelist_trunk_page' => $this->currentFirstFreelistTrunkPage(),
            'current_freelist_page_count' => $this->currentFreelistPageCount(),
            'current_freelist_pages' => $this->currentFreelistPages,
            'next_allocation_order' => $this->nextAllocationOrder,
            'current_page_numbers' => array_keys($this->currentPageImages),
            'updated_pointer_map_page_numbers' => array_keys($this->releasePlan->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->releasePlan->freePlan->clearedPageNumbers,
        ];
    }

    private static function fromReleasePlan(
        SQLiteDatabase $database,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        ?int $nextAllocationLimit,
    ): self {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[$pageNumber] = $database->page($pageNumber);
        }
        foreach ($releasePlan->freePlan->pageImages() as $pageNumber => $page) {
            $pages[$pageNumber] = $page;
        }
        ksort($pages);

        $currentDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
        $currentPageImages = $releasePlan->freePlan->pageImages();

        return new self(
            $releasePlan,
            $currentDatabase,
            $currentPageImages,
            $currentDatabase->freelistPageNumbers(),
            $currentDatabase->freelistAllocationOrder($nextAllocationLimit),
            $releasePlan->sources,
        );
    }
}
