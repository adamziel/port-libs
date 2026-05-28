<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext93Plan
{
    /**
     * @param array<int, string> $pageImages
     * @param list<array<string, mixed>> $leafFreeblockTransitions
     */
    private function __construct(
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteDatabase $coalescedDatabase,
        public readonly SQLiteDatabase $nextDatabase,
        public readonly array $pageImages,
        public readonly array $leafFreeblockTransitions,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDatabaseDeleteResults(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResults,
        int $maxTruncatedPages,
        bool $secureDelete = false,
        bool $clearCoalescedFragments = true,
    ): self {
        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage(
            $database,
            $leafPageNumber,
            $clearCoalescedFragments,
        );
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $vacuumPlan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
            $coalescedDatabase,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
        );

        $pageImages = $coalescePlan->pageImages();
        foreach ($vacuumPlan->pageImages as $pageNumber => $pageImage) {
            $pageImages[$pageNumber] = $pageImage;
        }
        foreach (array_keys($pageImages) as $pageNumber) {
            if ($pageNumber > $vacuumPlan->finalDatabasePageCount()) {
                unset($pageImages[$pageNumber]);
            }
        }
        ksort($pageImages);

        $nextDatabase = self::databaseWithPageImages(
            $database,
            $pageImages,
            $vacuumPlan->finalDatabasePageCount(),
        );

        return new self(
            $coalescePlan,
            $vacuumPlan,
            $database,
            $coalescedDatabase,
            $nextDatabase,
            $pageImages,
            self::leafFreeblockTransitions($coalescePlan),
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
     * @return list<int>
     */
    public function truncatedPageNumbers(): array
    {
        return $this->vacuumPlan->truncatedPageNumbers();
    }

    /**
     * @return list<int>
     */
    public function survivingFreelistPageNumbers(): array
    {
        return $this->nextDatabase->freelistPageNumbers();
    }

    /**
     * @return array<string, mixed>
     */
    public function materializedApplySummary(): array
    {
        return [
            'database_page_count' => $this->nextDatabase->pageCount(),
            'byte_length' => strlen($this->nextDatabase->toBytes()),
            'first_freelist_trunk_page' => $this->nextDatabase->header->firstFreelistTrunkPage,
            'freelist_page_count' => $this->nextDatabase->header->freelistPageCount,
            'freelist_page_numbers' => $this->survivingFreelistPageNumbers(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freeblock-pointermap-vacuum-current-source-next93',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'leaf_page_type' => $this->coalescePlan->pageType,
            'fragmented_bytes_before' => $this->coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->coalescePlan->fragmentedBytesAfter,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'leaf_freeblock_transitions' => $this->leafFreeblockTransitions,
            'released_overflow_pages' => $this->vacuumPlan->releasedOverflowPages(),
            'current_freelist_page_numbers' => $this->vacuumPlan->currentFreelistPageNumbers(),
            'surviving_freelist_page_numbers' => $this->survivingFreelistPageNumbers(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'pointer_map_vacuum_transitions' => $this->vacuumPlan->pointerMapVacuumTransitions(),
            'materialized_apply' => $this->materializedApplySummary(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'coalesce' => $this->coalescePlan->toArray(),
            'vacuum' => $this->vacuumPlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function leafFreeblockTransitions(SQLiteBTreeFreeblockCoalescePlan $plan): array
    {
        return [
            [
                'phase' => 'current',
                'freeblock_count' => count($plan->beforeFreeblocks),
                'freeblocks' => $plan->beforeFreeblocks,
                'fragmented_bytes' => $plan->fragmentedBytesBefore,
            ],
            [
                'phase' => 'next',
                'freeblock_count' => count($plan->afterFreeblocks),
                'freeblocks' => $plan->afterFreeblocks,
                'fragmented_bytes' => $plan->fragmentedBytesAfter,
            ],
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, ?int $pageCount = null): SQLiteDatabase
    {
        $pageCount ??= max($database->pageCount(), $database->header->databaseSizePages);
        foreach ($pageImages as $pageNumber => $pageImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite freeblock pointer-map vacuum page images must use one-based page numbers');
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite freeblock pointer-map vacuum page image length does not match page size');
            }
            $pageCount = max($pageCount, $pageNumber);
        }

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? (
                $pageNumber <= $database->pageCount()
                    ? $database->page($pageNumber)
                    : str_repeat("\0", $database->header->pageSize)
            );
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
