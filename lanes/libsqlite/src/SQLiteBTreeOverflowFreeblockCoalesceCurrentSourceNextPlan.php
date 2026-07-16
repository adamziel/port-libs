<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreeblockCoalesceCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $pageImages
     * @param list<int> $updatedPageNumbers
     */
    private function __construct(
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly array $pageImages,
        public readonly array $updatedPageNumbers,
        public readonly SQLiteDatabase $database,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDatabaseDeleteResults(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResults,
        bool $secureDelete = false,
        bool $clearCoalescedFragments = true,
    ): self {
        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage(
            $database,
            $leafPageNumber,
            $clearCoalescedFragments,
        );
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults(
            $coalescedDatabase,
            $deleteResults,
            $secureDelete,
        );

        $pageImages = $coalescePlan->pageImages();
        foreach ($releasePlan->freePlan->pageImages() as $pageNumber => $pageImage) {
            $pageImages[$pageNumber] = $pageImage;
        }
        ksort($pageImages);

        return new self(
            $coalescePlan,
            $releasePlan,
            $pageImages,
            array_keys($pageImages),
            self::databaseWithPageImages($database, $pageImages, $releasePlan->freePlan->databasePageCount),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freeblock-coalesce-current-source',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'fragmented_bytes_before' => $this->coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->coalescePlan->fragmentedBytesAfter,
            'freeblock_count_before' => count($this->coalescePlan->beforeFreeblocks),
            'freeblock_count_after' => count($this->coalescePlan->afterFreeblocks),
            'released_sources' => $this->releasePlan->sources,
            'released_overflow_pages' => $this->releasePlan->releasedOverflowPages,
            'freelist_page_count' => $this->releasePlan->freePlan->freelistPageCount,
            'first_freelist_trunk_page' => $this->releasePlan->freePlan->firstFreelistTrunkPage,
            'cleared_page_numbers' => $this->releasePlan->freePlan->clearedPageNumbers,
            'updated_page_numbers' => $this->updatedPageNumbers,
            'updated_pointer_map_page_numbers' => array_keys($this->releasePlan->freePlan->updatedPointerMapPages),
            'coalesce' => $this->coalescePlan->toArray(),
            'release' => $this->releasePlan->toArray(),
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, ?int $pageCount = null): SQLiteDatabase
    {
        $pageCount ??= max($database->pageCount(), $database->header->databaseSizePages);
        foreach (array_keys($pageImages) as $pageNumber) {
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
