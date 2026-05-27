<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteOverflowFreelistReusePlan
{
    /**
     * @param array<int, string> $pageImages
     * @param list<int> $reusedReleasedPageNumbers
     */
    private function __construct(
        public readonly SQLiteOverflowFreelistReleasePlan $releasePlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly array $pageImages,
        public readonly array $reusedReleasedPageNumbers,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
        SQLiteDatabase $database,
        array $deleteResults,
        int $replacementOverflowPageCount,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
        bool $allowAppend = true,
    ): self {
        if ($replacementOverflowPageCount < 1) {
            throw new \InvalidArgumentException('SQLite overflow freelist reuse replacement page count must be positive');
        }

        $releasePlan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, $deleteResults, $secureDelete);
        $releasedDatabase = self::databaseWithPageImages($database, $releasePlan->freePlan->pageImages());
        $allocationPlan = $releasedDatabase->planOverflowPageAllocation(
            $replacementOverflowPageCount,
            $parentBtreePageNumber,
            $allowAppend,
        );

        $pageImages = $releasePlan->freePlan->pageImages();
        foreach ($allocationPlan->pageImages() as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        foreach ($allocationPlan->appendedPageNumbers as $pageNumber) {
            $pageImages[$pageNumber] ??= str_repeat("\0", $database->header->pageSize);
        }
        ksort($pageImages);

        $released = array_fill_keys($releasePlan->releasedOverflowPages, true);
        $reused = [];
        foreach ($allocationPlan->allocatedPageNumbers as $pageNumber) {
            if (isset($released[$pageNumber])) {
                $reused[] = $pageNumber;
            }
        }

        return new self($releasePlan, $allocationPlan, $pageImages, $reused);
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowPageNumbers(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function appendedPageNumbers(): array
    {
        return $this->allocationPlan->appendedPageNumbers;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'overflow-freelist-reuse',
            'released_overflow_pages' => $this->releasePlan->releasedOverflowPages,
            'replacement_overflow_pages' => $this->replacementOverflowPageNumbers(),
            'reused_released_pages' => $this->reusedReleasedPageNumbers,
            'appended_page_numbers' => $this->appendedPageNumbers(),
            'release' => $this->releasePlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
            'updated_page_numbers' => array_keys($this->pageImages),
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        $pageCount = max($database->pageCount(), $database->header->databaseSizePages);
        foreach (array_keys($pageImages) as $pageNumber) {
            $pageCount = max($pageCount, $pageNumber);
        }

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[$pageNumber] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
