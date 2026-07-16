<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFreelistAllocationPlan
{
    /**
     * @param list<int> $allocatedPageNumbers
     * @param list<int> $appendedPageNumbers
     * @param array<int, string> $updatedFreelistPages
     * @param array<int, string> $updatedPointerMapPages
     * @param list<array<string, int|string|null>> $allocationSteps
     * @param list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}> $allocatedPointerMapEntries
     */
    public function __construct(
        public readonly array $allocatedPageNumbers,
        public readonly array $appendedPageNumbers,
        public readonly string $firstPage,
        public readonly array $updatedFreelistPages,
        public readonly int $databasePageCount,
        public readonly int $firstFreelistTrunkPage,
        public readonly int $freelistPageCount,
        public readonly array $updatedPointerMapPages = [],
        public readonly array $allocationSteps = [],
        public readonly array $allocatedPointerMapEntries = [],
    ) {
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    public function allocationSteps(): array
    {
        return $this->allocationSteps;
    }

    /**
     * @return list<array{page_number:int,pointer_map_page:int,offset:int,type:int,type_name:string,parent_page_number:int}>
     */
    public function allocatedPointerMapEntries(): array
    {
        return $this->allocatedPointerMapEntries;
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $pageImages = [1 => $this->firstPage];
        foreach ([$this->updatedFreelistPages, $this->updatedPointerMapPages] as $images) {
            foreach ($images as $pageNumber => $page) {
                $pageImages[$pageNumber] = $page;
            }
        }
        ksort($pageImages);

        return $pageImages;
    }

    /**
     * @return array{allocated_page_numbers:list<int>,appended_page_numbers:list<int>,database_page_count:int,first_freelist_trunk_page:int,freelist_page_count:int,updated_freelist_page_numbers:list<int>,updated_pointer_map_page_numbers?:list<int>}
     */
    public function toArray(): array
    {
        $summary = [
            'allocated_page_numbers' => $this->allocatedPageNumbers,
            'appended_page_numbers' => $this->appendedPageNumbers,
            'database_page_count' => $this->databasePageCount,
            'first_freelist_trunk_page' => $this->firstFreelistTrunkPage,
            'freelist_page_count' => $this->freelistPageCount,
            'updated_freelist_page_numbers' => array_keys($this->updatedFreelistPages),
        ];
        if ($this->updatedPointerMapPages !== []) {
            $summary['updated_pointer_map_page_numbers'] = array_keys($this->updatedPointerMapPages);
        }
        if ($this->allocatedPointerMapEntries !== []) {
            $summary['allocated_pointer_map_entries'] = $this->allocatedPointerMapEntries;
        }

        return $summary;
    }
}
