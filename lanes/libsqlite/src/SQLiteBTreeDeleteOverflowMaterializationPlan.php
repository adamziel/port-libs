<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeDeleteOverflowMaterializationPlan
{
    /**
     * @param array<int, string> $pageImages
     * @param list<array<string, mixed>> $pageTransitions
     * @param list<array<string, mixed>> $pointerMapTransitions
     */
    private function __construct(
        public readonly string $sourceAction,
        public readonly SQLiteDatabase $currentDatabase,
        public readonly SQLiteDatabase $nextDatabase,
        public readonly array $pageImages,
        public readonly array $pageTransitions,
        public readonly array $pointerMapTransitions,
    ) {
    }

    public static function fromEmptyLeafBatchPlan(
        SQLiteDatabase $database,
        SQLiteBTreeEmptyLeafBatchFreePlan $plan,
    ): self {
        return self::fromPageImages($database, $plan->pageImages, 'btree-empty-leaf-batch-free');
    }

    public static function fromEmptyLeafFreePlan(
        SQLiteDatabase $database,
        SQLiteBTreeEmptyLeafFreePlan $plan,
    ): self {
        return self::fromPageImages($database, $plan->pageImages, $plan->leafPageType . '-empty-leaf-free');
    }

    public static function fromFreeblockFreelistRebalancePlan(
        SQLiteDatabase $database,
        SQLiteBTreeFreeblockFreelistRebalancePlan $plan,
    ): self {
        return self::fromPageImages($database, $plan->pageImages, 'btree-freeblock-freelist-rebalance');
    }

    public static function fromOverflowFreeblockCurrentNextPlan(
        SQLiteDatabase $database,
        SQLiteBTreeOverflowFreeblockCurrentNextPlan $plan,
    ): self {
        return self::fromPageImages($database, $plan->pageImages, 'btree-overflow-freeblock-current-next');
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
            'action' => 'btree-delete-overflow-materialization-current-next',
            'source_action' => $this->sourceAction,
            'current_page_count' => $this->currentDatabase->pageCount(),
            'next_page_count' => $this->nextDatabase->pageCount(),
            'current_first_freelist_trunk_page' => $this->currentDatabase->header->firstFreelistTrunkPage,
            'next_first_freelist_trunk_page' => $this->nextDatabase->header->firstFreelistTrunkPage,
            'current_freelist_page_count' => $this->currentDatabase->header->freelistPageCount,
            'next_freelist_page_count' => $this->nextDatabase->header->freelistPageCount,
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'page_transitions' => $this->pageTransitions,
            'pointer_map_transitions' => $this->pointerMapTransitions,
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function fromPageImages(SQLiteDatabase $database, array $pageImages, string $sourceAction): self
    {
        if ($pageImages === []) {
            throw new \InvalidArgumentException('SQLite B-tree delete materialization requires page images');
        }

        $normalizedImages = [];
        $pageCount = $database->pageCount();
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite B-tree delete materialization page numbers must be one-based integers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite B-tree delete materialization page image length does not match page size');
            }

            $normalizedImages[$pageNumber] = $page;
            $pageCount = max($pageCount, $pageNumber);
        }
        ksort($normalizedImages);

        $nextDatabase = self::databaseWithPageImages($database, $normalizedImages, $pageCount);
        $pageTransitions = self::pageTransitions($database, $nextDatabase, $normalizedImages);
        $pointerMapTransitions = self::pointerMapTransitions($database, $nextDatabase, $pageTransitions);

        return new self($sourceAction, $database, $nextDatabase, $normalizedImages, $pageTransitions, $pointerMapTransitions);
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, int $pageCount): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber]
                ?? ($pageNumber <= $database->pageCount()
                    ? $database->page($pageNumber)
                    : str_repeat("\0", $database->header->pageSize));
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    /**
     * @param array<int, string> $pageImages
     * @return list<array<string, mixed>>
     */
    private static function pageTransitions(SQLiteDatabase $current, SQLiteDatabase $next, array $pageImages): array
    {
        $transitions = [];
        foreach ($pageImages as $pageNumber => $nextPage) {
            $currentPage = $pageNumber <= $current->pageCount()
                ? $current->page($pageNumber)
                : str_repeat("\0", $current->header->pageSize);

            $transitions[] = [
                'page_number' => $pageNumber,
                'current_sha1' => sha1($currentPage),
                'next_sha1' => sha1($nextPage),
                'changed' => $currentPage !== $nextPage,
                'current_zeroed' => $currentPage === str_repeat("\0", $current->header->pageSize),
                'next_zeroed' => $nextPage === str_repeat("\0", $next->header->pageSize),
            ];
        }

        return $transitions;
    }

    /**
     * @param list<array<string, mixed>> $pageTransitions
     * @return list<array<string, mixed>>
     */
    private static function pointerMapTransitions(SQLiteDatabase $current, SQLiteDatabase $next, array $pageTransitions): array
    {
        if (!$current->isAutoVacuum()) {
            return [];
        }

        $transitions = [];
        foreach ($pageTransitions as $transition) {
            $pageNumber = $transition['page_number'];
            if (!is_int($pageNumber) || $pageNumber < 2 || $next->isPointerMapPage($pageNumber)) {
                continue;
            }

            $currentEntry = $pageNumber <= $current->pageCount() && !$current->isPointerMapPage($pageNumber)
                ? $current->pointerMapEntryForPage($pageNumber)
                : null;
            $nextEntry = $pageNumber <= $next->pageCount() && !$next->isPointerMapPage($pageNumber)
                ? $next->pointerMapEntryForPage($pageNumber)
                : null;

            if ($currentEntry === null && $nextEntry === null) {
                continue;
            }
            if (
                $currentEntry !== null
                && $nextEntry !== null
                && $currentEntry->type === $nextEntry->type
                && $currentEntry->parentPageNumber === $nextEntry->parentPageNumber
            ) {
                continue;
            }

            $transitions[] = [
                'page_number' => $pageNumber,
                'current_type_name' => $currentEntry?->typeName(),
                'current_parent_page_number' => $currentEntry?->parentPageNumber,
                'next_type_name' => $nextEntry?->typeName(),
                'next_parent_page_number' => $nextEntry?->parentPageNumber,
            ];
        }

        return $transitions;
    }
}
