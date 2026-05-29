<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $allocatedPageImages
     * @param list<array<string, mixed>> $reuseRows
     */
    private function __construct(
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteDatabase $databaseAfterRelease,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterReuse,
        private readonly array $allocatedPageImages,
        public readonly array $reuseRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $chains
     * @param array<int, string> $allocatedPageImages
     */
    public static function fromOverflowChains(
        SQLiteDatabase $database,
        array $chains,
        int $allocationCount,
        ?int $parentPageNumber,
        array $allocatedPageImages = [],
        bool $secureDelete = false,
    ): self {
        if ($allocationCount < 1) {
            throw new \InvalidArgumentException('SQLite overflow freelist vacuum reuse allocation count must be positive');
        }

        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromOverflowChains($database, $chains, $secureDelete);
        $databaseAfterRelease = $database->applyPageFreePlan($releasePlan->freePlan);
        $allocationPlan = $databaseAfterRelease->planBtreePageAllocation($allocationCount, $parentPageNumber, false);
        $databaseAfterReuse = $databaseAfterRelease->applyPageAllocationPlan($allocationPlan, $allocatedPageImages);

        return new self(
            $releasePlan,
            $databaseAfterRelease,
            $allocationPlan,
            $databaseAfterReuse,
            $allocatedPageImages,
            self::reuseRows($database, $databaseAfterRelease, $releasePlan, $allocationPlan, $databaseAfterReuse, $allocatedPageImages),
        );
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPages(): array
    {
        return $this->releasePlan->releasedOverflowPages;
    }

    /**
     * @return list<int>
     */
    public function reusedPageNumbers(): array
    {
        return array_values(array_intersect(
            $this->releasePlan->releasedOverflowPages,
            $this->allocationPlan->allocatedPageNumbers,
        ));
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->releasePlan->freePlan->pageImages();
        foreach ($this->allocationPlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        foreach ($this->allocationPlan->allocatedPageNumbers as $pageNumber) {
            $images[$pageNumber] = $this->databaseAfterReuse->page($pageNumber);
        }
        ksort($images);

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freelist-vacuum-reuse-current-source-next121',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'reused_page_numbers' => $this->reusedPageNumbers(),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'final_first_freelist_trunk_page' => $this->databaseAfterReuse->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterReuse->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterReuse->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'btree_overflow_freelist_vacuum_reuse_current_source_next121' => $this->reuseRows,
        ];
    }

    /**
     * @param array<int, string> $allocatedPageImages
     * @return list<array<string, mixed>>
     */
    private static function reuseRows(
        SQLiteDatabase $database,
        SQLiteDatabase $databaseAfterRelease,
        SQLiteOverflowFreelistReleasePlan $releasePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterReuse,
        array $allocatedPageImages,
    ): array {
        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $rows = [];

        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            if (!isset($released[$pageNumber])) {
                continue;
            }

            $beforeEntry = $database->pointerMapEntryForPage($pageNumber)->toArray();
            $freeEntry = $databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $reuseEntry = $databaseAfterReuse->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationPlan->allocationSteps()[$position] ?? [];

            $rows[] = [
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'release_source' => self::releaseSourceForPage($releasePlan, $pageNumber),
                'allocation_source' => $step['source'] ?? null,
                'freelist_trunk_page' => $step['trunk_page'] ?? null,
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'free_pointer_map_type' => $freeEntry['type_name'],
                'free_pointer_map_parent' => $freeEntry['parent_page_number'],
                'reuse_pointer_map_type' => $reuseEntry['type_name'],
                'reuse_pointer_map_parent' => $reuseEntry['parent_page_number'],
                'materialized_with_supplied_image' => isset($allocatedPageImages[$pageNumber]),
                'next_page_type_byte' => ord($databaseAfterReuse->page($pageNumber)[0]),
            ];
        }

        return $rows;
    }

    private static function releaseSourceForPage(SQLiteOverflowFreelistReleasePlan $releasePlan, int $pageNumber): ?string
    {
        foreach ($releasePlan->sources as $source) {
            if (in_array($pageNumber, $source['pages'], true)) {
                return $source['source'];
            }
        }

        return null;
    }
}
