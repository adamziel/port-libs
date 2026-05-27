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

        return new self($releasePlan, $truncatePlan, $pageImages);
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'overflow-freelist-release-vacuum-truncate',
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'final_database_page_count' => $this->finalDatabasePageCount(),
            'final_first_freelist_trunk_page' => $this->finalFirstFreelistTrunkPage(),
            'final_freelist_page_count' => $this->finalFreelistPageCount(),
            'updated_page_numbers' => array_keys($this->pageImages),
            'release_plan' => $this->releasePlan->toArray(),
            'truncate_plan' => $this->truncatePlan->toArray(),
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pageCount = $database->pageCount();
        foreach ($pageImages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite overflow vacuum page images must use one-based page numbers');
            }
            if (!is_string($page) || strlen($page) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite overflow vacuum page image length does not match page size');
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
