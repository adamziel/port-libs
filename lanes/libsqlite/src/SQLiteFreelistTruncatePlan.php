<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFreelistTruncatePlan
{
    /**
     * @param list<int> $truncatedPageNumbers
     * @param array<int, string> $updatedFreelistPages
     */
    public function __construct(
        public readonly array $truncatedPageNumbers,
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
        $pageImages = [1 => $this->firstPage];
        foreach ($this->updatedFreelistPages as $pageNumber => $page) {
            $pageImages[$pageNumber] = $page;
        }
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @return array{truncated_page_numbers:list<int>,database_page_count:int,first_freelist_trunk_page:int,freelist_page_count:int,updated_freelist_page_numbers:list<int>}
     */
    public function toArray(): array
    {
        return [
            'truncated_page_numbers' => $this->truncatedPageNumbers,
            'database_page_count' => $this->databasePageCount,
            'first_freelist_trunk_page' => $this->firstFreelistTrunkPage,
            'freelist_page_count' => $this->freelistPageCount,
            'updated_freelist_page_numbers' => array_keys($this->updatedFreelistPages),
        ];
    }
}
