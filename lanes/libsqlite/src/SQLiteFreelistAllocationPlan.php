<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFreelistAllocationPlan
{
    /**
     * @param list<int> $allocatedPageNumbers
     * @param list<int> $appendedPageNumbers
     * @param array<int, string> $updatedFreelistPages
     */
    public function __construct(
        public readonly array $allocatedPageNumbers,
        public readonly array $appendedPageNumbers,
        public readonly string $firstPage,
        public readonly array $updatedFreelistPages,
        public readonly int $databasePageCount,
        public readonly int $firstFreelistTrunkPage,
        public readonly int $freelistPageCount,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        return [1 => $this->firstPage] + $this->updatedFreelistPages;
    }

    /**
     * @return array{allocated_page_numbers:list<int>,appended_page_numbers:list<int>,database_page_count:int,first_freelist_trunk_page:int,freelist_page_count:int,updated_freelist_page_numbers:list<int>}
     */
    public function toArray(): array
    {
        return [
            'allocated_page_numbers' => $this->allocatedPageNumbers,
            'appended_page_numbers' => $this->appendedPageNumbers,
            'database_page_count' => $this->databasePageCount,
            'first_freelist_trunk_page' => $this->firstFreelistTrunkPage,
            'freelist_page_count' => $this->freelistPageCount,
            'updated_freelist_page_numbers' => array_keys($this->updatedFreelistPages),
        ];
    }
}
