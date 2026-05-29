<?php
declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan
{
    private function __construct(private readonly object $inner)
    {
    }

    public static function __callStatic(string $name, array $args): self
    {
        $args = self::unwrapArgs($args);
        if (method_exists(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextExtendedVariantPlan::class, $name)) {
            return new self(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextExtendedVariantPlan::{$name}(...$args));
        }
        if (method_exists(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextBaseVariantPlan::class, $name)) {
            return new self(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextBaseVariantPlan::{$name}(...$args));
        }

        throw new \BadMethodCallException(sprintf('Unknown %s factory method %s', self::class, $name));
    }

    public static function next127TableLeafFromDeleteResult(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextBaseVariantPlan::tableLeafFromDeleteResult(...$args));
    }

    public static function next127IndexLeafFromDeleteResult(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextBaseVariantPlan::indexLeafFromDeleteResult(...$args));
    }

    public static function next127FromDeletePlan(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextBaseVariantPlan::fromDeletePlan(...$args));
    }

    public static function next144TableLeafFromDeleteResult(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextExtendedVariantPlan::tableLeafFromDeleteResult(...$args));
    }

    public static function next144IndexLeafFromDeleteResult(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextExtendedVariantPlan::indexLeafFromDeleteResult(...$args));
    }

    public static function next144FromBasePlan(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextExtendedVariantPlan::fromBasePlan(...$args));
    }

    /**
     * @param list<mixed> $args
     * @return list<mixed>
     */
    private static function unwrapArgs(array $args): array
    {
        return array_map(static fn (mixed $arg): mixed => $arg instanceof self ? $arg->inner : $arg, $args);
    }

    public function __call(string $name, array $args): mixed
    {
        return $this->inner->{$name}(...$args);
    }

    public function __get(string $name): mixed
    {
        return $this->inner->{$name};
    }

    public function __isset(string $name): bool
    {
        return isset($this->inner->{$name});
    }

    public function toArray(): array
    {
        return $this->inner->toArray();
    }
}

final class SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextBaseVariantPlan
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

final class SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextExtendedVariantPlan
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan $basePlan,
        public readonly array $rows,
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
        return self::fromBasePlan(SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $secureDelete,
        ));
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
        return self::fromBasePlan(SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan::indexLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $secureDelete,
            $overflowReader,
        ));
    }

    public static function fromBasePlan(SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan $basePlan): self
    {
        $sourceDatabase = $basePlan->basePlan->sourceDatabase;
        $nextDatabase = $basePlan->basePlan->nextDatabase;
        $leafPage = $basePlan->basePlan->deletePlan->leafPageNumber;
        $leafImage = $basePlan->basePlan->deletePlan->leafPageImage;
        $leafHeader = SQLiteBTreePageHeader::parsePage($leafImage, $sourceDatabase->header->pageSize);
        $rows = [[
            'kind' => 'deleted-leaf-freeblock',
            'page_number' => $leafPage,
            'source_page_hash' => hash('sha256', $sourceDatabase->page($leafPage)),
            'next_page_hash' => hash('sha256', $leafImage),
            'source_pointer_map_page' => self::pointerMapPageFor($sourceDatabase, $leafPage),
            'source_pointer_map_type' => self::pointerMapType($sourceDatabase, $leafPage),
            'source_pointer_map_parent' => self::pointerMapParent($sourceDatabase, $leafPage),
            'next_pointer_map_page' => self::pointerMapPageFor($nextDatabase, $leafPage),
            'next_pointer_map_type' => self::pointerMapType($nextDatabase, $leafPage),
            'next_pointer_map_parent' => self::pointerMapParent($nextDatabase, $leafPage),
            'freeblock_count' => count($leafHeader->freeblocks($leafImage)),
            'freeblock_bytes' => $basePlan->basePlan->deletePlan->freeblockBytesAfter,
            'freeblock_status' => $leafHeader->freeblockIntegrityReport($leafImage)['status'],
            'vacuum_status' => 'materialized-leaf-page',
            'freelist_role' => null,
            'materialized' => true,
        ]];

        foreach ($basePlan->rows as $row) {
            $pageNumber = (int) $row['page_number'];
            $materialized = $pageNumber <= $nextDatabase->pageCount();
            $rows[] = [
                'kind' => 'released-overflow-page',
                'page_number' => $pageNumber,
                'chain_position' => $row['chain_position'],
                'source_overflow_next_page' => self::readUInt32($sourceDatabase->page($pageNumber), 0),
                'next_overflow_next_page' => $materialized ? self::readUInt32($nextDatabase->page($pageNumber), 0) : null,
                'source_page_hash' => hash('sha256', $sourceDatabase->page($pageNumber)),
                'next_page_hash' => $materialized ? hash('sha256', $nextDatabase->page($pageNumber)) : null,
                'source_pointer_map_page' => self::pointerMapPageFor($sourceDatabase, $pageNumber),
                'source_pointer_map_type' => $row['current_pointer_map_type'],
                'source_pointer_map_parent' => $row['current_pointer_map_parent'],
                'next_pointer_map_page' => $materialized ? self::pointerMapPageFor($nextDatabase, $pageNumber) : null,
                'next_pointer_map_type' => $row['next_pointer_map_type'],
                'next_pointer_map_parent' => $row['next_pointer_map_parent'],
                'vacuum_status' => $row['vacuum_status'],
                'freelist_role' => $row['freelist_role'],
                'materialized' => $materialized,
            ];
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materializedRows(): array
    {
        return array_values(array_filter($this->rows, static fn (array $row): bool => $row['materialized']));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function truncatedRows(): array
    {
        return array_values(array_filter($this->rows, static fn (array $row): bool => !$row['materialized']));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-pointermap-vacuum-freeblock-current-source-next144',
            'leaf_page' => $this->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->releasedOverflowPages(),
            'surviving_released_overflow_pages' => $this->basePlan->survivingReleasedOverflowPages(),
            'truncated_released_overflow_pages' => $this->basePlan->truncatedReleasedOverflowPages(),
            'materialized_page_numbers' => array_column($this->materializedRows(), 'page_number'),
            'truncated_page_numbers' => array_column($this->truncatedRows(), 'page_number'),
            'final_database_page_count' => $this->basePlan->basePlan->nextDatabase->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->basePlan->nextDatabase->freelistPageNumbers(),
            'updated_page_numbers' => $this->basePlan->basePlan->updatedPageNumbers(),
            'rows' => $this->rows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    private static function pointerMapPageFor(SQLiteDatabase $database, int $pageNumber): ?int
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapPageFor($pageNumber);
    }

    private static function pointerMapType(SQLiteDatabase $database, int $pageNumber): ?string
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->typeName();
    }

    private static function pointerMapParent(SQLiteDatabase $database, int $pageNumber): ?int
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->parentPageNumber;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map vacuum freeblock next144 could not read uint32');
        }

        return $value[1];
    }
}
