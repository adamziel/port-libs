<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNext127Plan
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $deletePlan,
        public readonly SQLiteFreelistTruncatePlan $truncatePlan,
        public readonly SQLiteDatabase $nextDatabase,
        public readonly array $rows,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        bool $secureDelete = false,
    ): self {
        return self::fromDeletePlan(
            $database,
            SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult(
                $database,
                $leafPageNumber,
                $deleteResult,
                $secureDelete,
            ),
            $maxTruncatedPages,
        );
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function indexLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): self {
        return self::fromDeletePlan(
            $database,
            SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult(
                $database,
                $leafPageNumber,
                $deleteResult,
                $secureDelete,
                $overflowReader,
            ),
            $maxTruncatedPages,
        );
    }

    public static function fromDeletePlan(
        SQLiteDatabase $database,
        SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $deletePlan,
        int $maxTruncatedPages,
    ): self {
        if ($maxTruncatedPages < 1) {
            throw new \InvalidArgumentException('SQLite pointer-map vacuum freeblock next127 requires a positive truncation limit');
        }

        $afterDelete = self::databaseWithPageImages($database, $deletePlan->pageImages);
        $truncatePlan = $afterDelete->planFreelistTailTruncation($maxTruncatedPages);
        $pageImages = $deletePlan->pageImages;
        foreach ($truncatePlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        foreach (array_keys($pageImages) as $pageNumber) {
            if ($pageNumber > $truncatePlan->databasePageCount) {
                unset($pageImages[$pageNumber]);
            }
        }
        ksort($pageImages);

        $nextDatabase = self::databaseWithPageImages($database, $pageImages, $truncatePlan->databasePageCount);

        return new self(
            $database,
            $deletePlan,
            $truncatePlan,
            $nextDatabase,
            self::rows($database, $deletePlan, $truncatePlan, $nextDatabase),
            $pageImages,
        );
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPages(): array
    {
        return $this->deletePlan->obsoleteOverflowPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function truncatedReleasedOverflowPages(): array
    {
        return array_values(array_filter(
            $this->releasedOverflowPages(),
            fn (int $pageNumber): bool => $pageNumber > $this->truncatePlan->databasePageCount,
        ));
    }

    /**
     * @return list<int>
     */
    public function survivingReleasedOverflowPages(): array
    {
        return array_values(array_filter(
            $this->releasedOverflowPages(),
            fn (int $pageNumber): bool => $pageNumber <= $this->truncatePlan->databasePageCount,
        ));
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return array{database_page_count:int,byte_length:int,first_freelist_trunk_page:int,freelist_page_count:int,freelist_page_numbers:list<int>,updated_page_numbers:list<int>,omitted_truncated_page_numbers:list<int>}
     */
    public function materializedApplySummary(): array
    {
        return [
            'database_page_count' => $this->nextDatabase->pageCount(),
            'byte_length' => strlen($this->nextDatabase->toBytes()),
            'first_freelist_trunk_page' => $this->nextDatabase->header->firstFreelistTrunkPage,
            'freelist_page_count' => $this->nextDatabase->header->freelistPageCount,
            'freelist_page_numbers' => $this->nextDatabase->freelistPageNumbers(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'omitted_truncated_page_numbers' => array_values(array_filter(
                $this->truncatePlan->truncatedPageNumbers,
                fn (int $pageNumber): bool => $pageNumber > $this->nextDatabase->pageCount(),
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-pointermap-vacuum-freeblock-current-source-next127',
            'leaf_page' => $this->deletePlan->leafPageNumber,
            'leaf_page_type' => $this->deletePlan->leafPageType,
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'surviving_released_overflow_pages' => $this->survivingReleasedOverflowPages(),
            'truncated_released_overflow_pages' => $this->truncatedReleasedOverflowPages(),
            'final_database_page_count' => $this->truncatePlan->databasePageCount,
            'final_first_freelist_trunk_page' => $this->truncatePlan->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->truncatePlan->freelistPageCount,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'freeblock_bytes_before' => $this->deletePlan->freeblockBytesBefore,
            'freeblock_bytes_after' => $this->deletePlan->freeblockBytesAfter,
            'cell_content_start_delta' => $this->deletePlan->cellContentStartDelta,
            'current_source_page_hash' => $this->deletePlan->currentSourcePageHash,
            'next_leaf_page_hash' => $this->deletePlan->nextLeafPageHash,
            'rows' => $this->rows,
            'materialized_apply' => $this->materializedApplySummary(),
            'delete_plan' => $this->deletePlan->toArray(),
            'truncate_plan' => $this->truncatePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(
        SQLiteDatabase $database,
        SQLiteBTreeDeleteRebalanceFreeblockApplyPlan $deletePlan,
        SQLiteFreelistTruncatePlan $truncatePlan,
        SQLiteDatabase $nextDatabase,
    ): array {
        $truncatedEntries = [];
        foreach ($truncatePlan->truncatedPointerMapEntries as $entry) {
            $truncatedEntries[(int) $entry['page_number']] = $entry;
        }

        $freedEntries = [];
        foreach ($deletePlan->freePlan->freedPointerMapEntries as $entry) {
            $freedEntries[(int) $entry['page_number']] = $entry;
        }

        $rows = [];
        foreach ($deletePlan->obsoleteOverflowPageNumbers as $position => $pageNumber) {
            $truncated = $pageNumber > $truncatePlan->databasePageCount;
            $currentEntry = $database->isAutoVacuum() && !$database->isPointerMapPage($pageNumber)
                ? $database->pointerMapEntryForPage($pageNumber)->toArray()
                : null;
            $nextEntry = null;
            if (!$truncated && $nextDatabase->isAutoVacuum() && !$nextDatabase->isPointerMapPage($pageNumber)) {
                $nextEntry = $nextDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }

            $rows[] = [
                'leaf_page' => $deletePlan->leafPageNumber,
                'leaf_page_type' => $deletePlan->leafPageType,
                'chain_position' => $position,
                'page_number' => $pageNumber,
                'current_overflow_next_page' => self::readUInt32($database->page($pageNumber), 0),
                'current_pointer_map_type' => $currentEntry['type_name'] ?? null,
                'current_pointer_map_parent' => $currentEntry['parent_page_number'] ?? null,
                'freed_pointer_map_type' => $freedEntries[$pageNumber]['type_name'] ?? null,
                'freed_pointer_map_parent' => $freedEntries[$pageNumber]['parent_page_number'] ?? null,
                'next_pointer_map_type' => $nextEntry['type_name'] ?? null,
                'truncated_pointer_map_type' => $truncatedEntries[$pageNumber]['type_name'] ?? null,
                'vacuum_status' => $truncated ? 'truncated-from-database' : 'survives-as-free-page',
                'freeblock_bytes_before' => $deletePlan->freeblockBytesBefore,
                'freeblock_bytes_after' => $deletePlan->freeblockBytesAfter,
                'next_leaf_hash' => $deletePlan->nextLeafPageHash,
                'materialized' => !$truncated,
                'truncated' => $truncated,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, ?int $pageCountOverride = null): SQLiteDatabase
    {
        $pageCount = $pageCountOverride ?? $database->pageCount();
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite pointer-map vacuum freeblock next127 could not read uint32');
        }

        return $value[1];
    }
}
