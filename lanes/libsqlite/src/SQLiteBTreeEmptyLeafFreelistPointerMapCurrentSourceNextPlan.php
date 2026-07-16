<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan
{
    /**
     * @param list<array{leaf_page:int,leaf_page_type:string,deleted_rowids:list<int>,deleted_record_values:list<list<mixed>>,obsolete_overflow_pages:list<int>}> $leafDeletes
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteDatabase $currentDatabase,
        public readonly SQLiteBTreeEmptyLeafBatchFreePlan $batchPlan,
        public readonly array $leafDeletes,
        public readonly array $rows,
    ) {
    }

    /**
     * @param list<array{leaf_page:int,leaf_page_type:string,delete_result:array<string,mixed>}> $leafDeletes
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $leafDeletes,
        bool $secureDelete = false,
        ?int $allocationLimit = null,
    ): self {
        $batchPlan = SQLiteBTreeEmptyLeafBatchFreePlan::fromDeleteResults($database, $leafDeletes, $secureDelete);
        $currentDatabase = self::databaseWithPageImages($database, $batchPlan->pageImages);
        $nextAllocationOrder = $currentDatabase->freelistAllocationOrder($allocationLimit);
        $nextAllocationPositions = array_flip($nextAllocationOrder);
        $freelistRoles = self::freelistRoles($currentDatabase);

        $rows = [];
        foreach ($batchPlan->freedPageNumbers as $position => $pageNumber) {
            $currentEntry = $database->pointerMapEntryForPage($pageNumber);
            $nextEntry = $currentDatabase->pointerMapEntryForPage($pageNumber);
            $leafDelete = self::leafDeleteForPage($batchPlan->leafDeletes, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'release_position' => $position,
                'source' => $leafDelete === null ? 'obsolete-overflow-page' : $leafDelete['leaf_page_type'] . '-empty-page',
                'leaf_page' => $leafDelete['leaf_page'] ?? null,
                'leaf_page_type' => $leafDelete['leaf_page_type'] ?? null,
                'deleted_rowids' => $leafDelete['deleted_rowids'] ?? [],
                'deleted_record_values' => $leafDelete['deleted_record_values'] ?? [],
                'current_type_name' => $currentEntry->typeName(),
                'current_parent_page_number' => $currentEntry->parentPageNumber,
                'next_type_name' => $nextEntry->typeName(),
                'next_parent_page_number' => $nextEntry->parentPageNumber,
                'freelist_role' => $freelistRoles[$pageNumber]['role'] ?? null,
                'freelist_position' => $freelistRoles[$pageNumber]['position'] ?? null,
                'next_allocation_position' => $nextAllocationPositions[$pageNumber] ?? null,
                'pointer_map_page' => $nextEntry->pointerMapPageNumber,
                'secure_deleted' => in_array($pageNumber, $batchPlan->freePlan->clearedPageNumbers, true),
                'materialized' => isset($batchPlan->pageImages[$pageNumber]),
            ];
        }

        return new self($database, $currentDatabase, $batchPlan, $batchPlan->leafDeletes, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function emptyLeafFreelistPointerMapRows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-empty-leaf-freelist-pointermap-current-source-next97',
            'leaf_delete_count' => count($this->leafDeletes),
            'leaf_deletes' => $this->leafDeletes,
            'freed_pages' => $this->batchPlan->freedPageNumbers,
            'current_freelist_pages' => $this->currentDatabase->freelistPageNumbers(),
            'current_freelist_allocation_order' => $this->currentDatabase->freelistAllocationOrder(),
            'current_first_freelist_trunk_page' => $this->currentDatabase->header->firstFreelistTrunkPage,
            'current_freelist_page_count' => $this->currentDatabase->header->freelistPageCount,
            'updated_page_numbers' => $this->batchPlan->updatedPageNumbers(),
            'updated_pointer_map_page_numbers' => array_keys($this->batchPlan->freePlan->updatedPointerMapPages),
            'secure_delete_cleared_pages' => $this->batchPlan->freePlan->clearedPageNumbers,
            'empty_leaf_freelist_pointermap_current_source_next97' => $this->rows,
        ];
    }

    /**
     * @param list<array{leaf_page:int,leaf_page_type:string,deleted_rowids:list<int>,deleted_record_values:list<list<mixed>>,obsolete_overflow_pages:list<int>}> $leafDeletes
     * @return array{leaf_page:int,leaf_page_type:string,deleted_rowids:list<int>,deleted_record_values:list<list<mixed>>,obsolete_overflow_pages:list<int>}|null
     */
    private static function leafDeleteForPage(array $leafDeletes, int $pageNumber): ?array
    {
        foreach ($leafDeletes as $leafDelete) {
            if ($leafDelete['leaf_page'] === $pageNumber) {
                return $leafDelete;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{role:string, position:int}>
     */
    private static function freelistRoles(SQLiteDatabase $database): array
    {
        $roles = [];
        $position = 0;
        foreach ($database->freelistTrunkPages() as $trunkPage) {
            $roles[$trunkPage->pageNumber] = ['role' => 'freelist-trunk', 'position' => $position++];
            foreach ($trunkPage->leafPageNumbers as $leafPageNumber) {
                $roles[$leafPageNumber] = ['role' => 'freelist-leaf', 'position' => $position++];
            }
        }

        return $roles;
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pageCount = $database->pageCount();
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite empty leaf current-source page numbers must be one-based integers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite empty leaf current-source page image length does not match page size');
            }
            $pageCount = max($pageCount, $pageNumber);
        }

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
