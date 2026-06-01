<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFreelistFreePlan
{
    /**
     * @param list<int> $freedPageNumbers
     * @param list<int> $leafPageNumbers
     * @param list<int> $newTrunkPageNumbers
     * @param array<int, string> $updatedFreelistPages
     * @param array<int, string> $updatedPointerMapPages
     * @param list<int> $clearedPageNumbers
     * @param array<int, string> $clearedPageImages
     */
    public function __construct(
        public readonly array $freedPageNumbers,
        public readonly array $leafPageNumbers,
        public readonly array $newTrunkPageNumbers,
        public readonly string $firstPage,
        public readonly array $updatedFreelistPages,
        public readonly int $databasePageCount,
        public readonly int $firstFreelistTrunkPage,
        public readonly int $freelistPageCount,
        public readonly array $updatedPointerMapPages = [],
        public readonly array $clearedPageNumbers = [],
        public readonly array $clearedPageImages = [],
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $pageImages = [1 => $this->firstPage];
        foreach ([$this->clearedPageImages, $this->updatedFreelistPages, $this->updatedPointerMapPages] as $images) {
            foreach ($images as $pageNumber => $page) {
                $pageImages[$pageNumber] = $page;
            }
        }
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @return array{freed_page_numbers:list<int>,leaf_page_numbers:list<int>,new_trunk_page_numbers:list<int>,database_page_count:int,first_freelist_trunk_page:int,freelist_page_count:int,updated_freelist_page_numbers:list<int>,cleared_page_numbers?:list<int>}
     */
    public function toArray(): array
    {
        $summary = [
            'freed_page_numbers' => $this->freedPageNumbers,
            'leaf_page_numbers' => $this->leafPageNumbers,
            'new_trunk_page_numbers' => $this->newTrunkPageNumbers,
            'database_page_count' => $this->databasePageCount,
            'first_freelist_trunk_page' => $this->firstFreelistTrunkPage,
            'freelist_page_count' => $this->freelistPageCount,
            'updated_freelist_page_numbers' => array_keys($this->updatedFreelistPages),
        ];
        if ($this->clearedPageNumbers !== []) {
            $summary['cleared_page_numbers'] = $this->clearedPageNumbers;
        }

        return $summary;
    }
}
