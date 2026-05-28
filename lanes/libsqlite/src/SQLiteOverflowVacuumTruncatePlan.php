<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOverflowVacuumTruncatePlan
{
    /**
     * @param array<int, string> $pageImages
     */
    private function __construct(
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteFreelistTruncatePlan $truncatePlan,
        public readonly SQLiteDatabase $currentDatabase,
        public readonly SQLiteDatabase $nextDatabase,
        public readonly array $pageImages,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        int $maxTruncatedPages,
        bool $secureDelete = false,
    ): self {
        if ($maxTruncatedPages < 1) {
            throw new \InvalidArgumentException('SQLite overflow vacuum truncate plan requires a positive truncation limit');
        }

        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, $deleteResults, $secureDelete);
        $releasedDatabase = self::databaseWithPageImages($database, $releasePlan->freePlan->pageImages());
        $truncatePlan = $releasedDatabase->planFreelistTailTruncation($maxTruncatedPages);

        $pageImages = [];
        foreach ($releasePlan->freePlan->pageImages() as $pageNumber => $page) {
            if ($pageNumber <= $truncatePlan->databasePageCount) {
                $pageImages[$pageNumber] = $page;
            }
        }
        foreach ($truncatePlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        $nextDatabase = self::databaseWithPageImages($database, $pageImages, $truncatePlan->databasePageCount);

        return new self($releasePlan, $truncatePlan, $releasedDatabase, $nextDatabase, $pageImages);
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
    public function truncatedPageNumbers(): array
    {
        return $this->truncatePlan->truncatedPageNumbers;
    }

    public function finalDatabasePageCount(): int
    {
        return $this->truncatePlan->databasePageCount;
    }

    public function finalFirstFreelistTrunkPage(): int
    {
        return $this->truncatePlan->firstFreelistTrunkPage;
    }

    public function finalFreelistPageCount(): int
    {
        return $this->truncatePlan->freelistPageCount;
    }

    public function materializedDatabase(): SQLiteDatabase
    {
        return $this->nextDatabase;
    }

    public function materializedBytes(): string
    {
        return $this->nextDatabase->toBytes();
    }

    /**
     * @return array<int, string>
     */
    public function materializedPageImages(): array
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $this->finalDatabasePageCount(); $pageNumber++) {
            $pages[$pageNumber] = $this->nextDatabase->page($pageNumber);
        }

        return $pages;
    }

    /**
     * @return array{database_page_count:int,byte_length:int,first_freelist_trunk_page:int,freelist_page_count:int,freelist_page_numbers:list<int>,updated_page_numbers:list<int>,omitted_truncated_page_numbers:list<int>}
     */
    public function materializedApplySummary(): array
    {
        return [
            'database_page_count' => $this->nextDatabase->pageCount(),
            'byte_length' => strlen($this->materializedBytes()),
            'first_freelist_trunk_page' => $this->nextDatabase->header->firstFreelistTrunkPage,
            'freelist_page_count' => $this->nextDatabase->header->freelistPageCount,
            'freelist_page_numbers' => $this->nextDatabase->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages),
            'omitted_truncated_page_numbers' => array_values(array_filter(
                $this->truncatedPageNumbers(),
                fn (int $pageNumber): bool => $pageNumber > $this->nextDatabase->pageCount(),
            )),
        ];
    }

    /**
     * @return list<int>
     */
    public function currentFreelistPageNumbers(): array
    {
        return $this->currentDatabase->freelistPageNumbers();
    }

    /**
     * @return list<int>
     */
    public function nextFreelistPageNumbers(): array
    {
        return $this->nextDatabase->freelistPageNumbers();
    }

    /**
     * @return list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}>
     */
    public function currentFreedPointerMapEntries(): array
    {
        return $this->releasePlan->freePlan->freedPointerMapEntries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pointerMapVacuumTransitions(): array
    {
        $currentEntries = [];
        foreach ($this->currentFreedPointerMapEntries() as $entry) {
            $currentEntries[(int) $entry['page_number']] = $entry;
        }

        $truncatedEntries = [];
        foreach ($this->truncatePlan->truncatedPointerMapEntries as $entry) {
            $truncatedEntries[(int) $entry['page_number']] = $entry;
        }

        $releasedPages = $this->releasedOverflowPages();
        sort($releasedPages);

        $transitions = [];
        foreach ($releasedPages as $pageNumber) {
            $current = $currentEntries[$pageNumber] ?? null;
            $truncated = $truncatedEntries[$pageNumber] ?? null;
            $next = null;
            $status = 'survives-as-free-page';
            if ($pageNumber > $this->finalDatabasePageCount()) {
                $status = 'truncated-from-database';
            } elseif ($this->nextDatabase->isAutoVacuum() && !$this->nextDatabase->isPointerMapPage($pageNumber)) {
                $next = $this->nextDatabase->pointerMapEntryForPage($pageNumber)->toArray();
            }

            $transitions[] = [
                'page_number' => $pageNumber,
                'status' => $status,
                'current' => $current,
                'next' => $next,
                'truncated' => $truncated,
                'current_type_name' => $current['type_name'] ?? null,
                'next_type_name' => $next['type_name'] ?? null,
                'truncated_type_name' => $truncated['type_name'] ?? null,
            ];
        }

        return $transitions;
    }

    /**
     * @return list<int>
     */
    public function survivingFreedPointerMapPages(): array
    {
        $pages = [];
        foreach ($this->pointerMapVacuumTransitions() as $transition) {
            if ($transition['status'] === 'survives-as-free-page') {
                $pages[] = (int) $transition['page_number'];
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     */
    public function truncatedFreedPointerMapPages(): array
    {
        $pages = [];
        foreach ($this->pointerMapVacuumTransitions() as $transition) {
            if ($transition['status'] === 'truncated-from-database') {
                $pages[] = (int) $transition['page_number'];
            }
        }

        return $pages;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'overflow-freelist-release-vacuum-truncate',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'current_database_page_count' => $this->currentDatabase->pageCount(),
            'current_first_freelist_trunk_page' => $this->currentDatabase->header->firstFreelistTrunkPage,
            'current_freelist_page_count' => $this->currentDatabase->header->freelistPageCount,
            'current_freelist_page_numbers' => $this->currentFreelistPageNumbers(),
            'current_freed_pointer_map_entries' => $this->currentFreedPointerMapEntries(),
            'final_database_page_count' => $this->finalDatabasePageCount(),
            'final_first_freelist_trunk_page' => $this->finalFirstFreelistTrunkPage(),
            'final_freelist_page_count' => $this->finalFreelistPageCount(),
            'next_freelist_page_numbers' => $this->nextFreelistPageNumbers(),
            'pointer_map_vacuum_transitions' => $this->pointerMapVacuumTransitions(),
            'surviving_freed_pointer_map_pages' => $this->survivingFreedPointerMapPages(),
            'truncated_freed_pointer_map_pages' => $this->truncatedFreedPointerMapPages(),
            'materialized_apply' => $this->materializedApplySummary(),
            'updated_page_numbers' => array_keys($this->pageImages),
            'release_plan' => $this->releasePlan->toArray(),
            'truncate_plan' => $this->truncatePlan->toArray(),
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, ?int $pageCountOverride = null): SQLiteDatabase
    {
        $pageCount = $pageCountOverride ?? $database->pageCount();
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite overflow vacuum page images must use one-based page numbers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite overflow vacuum page image length does not match page size');
            }
            if ($pageCountOverride === null) {
                $pageCount = max($pageCount, $pageNumber);
            }
        }

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
