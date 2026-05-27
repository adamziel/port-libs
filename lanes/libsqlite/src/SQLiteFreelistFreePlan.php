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
     * @param list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}> $freedPointerMapEntries
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
        public readonly array $freedPointerMapEntries = [],
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
     * @return list<int>
     */
    public function existingTrunkPageNumbers(): array
    {
        $newTrunks = array_fill_keys($this->newTrunkPageNumbers, true);
        $existingTrunks = [];
        foreach (array_keys($this->updatedFreelistPages) as $pageNumber) {
            if (!isset($newTrunks[$pageNumber])) {
                $existingTrunks[] = $pageNumber;
            }
        }

        return $existingTrunks;
    }

    /**
     * @return array{freed_page_numbers:list<int>,leaf_page_numbers:list<int>,new_trunk_page_numbers:list<int>,database_page_count:int,first_freelist_trunk_page:int,freelist_page_count:int,updated_freelist_page_numbers:list<int>,existing_trunk_page_numbers?:list<int>,updated_pointer_map_page_numbers?:list<int>,cleared_page_numbers?:list<int>,freed_pointer_map_entries?:list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}>}
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
        $existingTrunks = $this->existingTrunkPageNumbers();
        if ($existingTrunks !== [] && $this->updatedPointerMapPages !== []) {
            $summary['existing_trunk_page_numbers'] = $existingTrunks;
        }
        if ($this->clearedPageNumbers !== []) {
            $summary['cleared_page_numbers'] = $this->clearedPageNumbers;
        }
        if ($this->updatedPointerMapPages !== []) {
            $summary['updated_pointer_map_page_numbers'] = array_keys($this->updatedPointerMapPages);
        }
        if ($this->freedPointerMapEntries !== []) {
            $summary['freed_pointer_map_entries'] = $this->freedPointerMapEntries;
        }

        return $summary;
    }
}
