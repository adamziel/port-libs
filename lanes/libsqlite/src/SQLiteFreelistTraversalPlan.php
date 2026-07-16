<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFreelistTraversalPlan
{
    /**
     * @param list<SQLiteFreelistTrunkPage> $trunkPages
     * @param list<int> $trunkPageNumbers
     * @param list<int> $leafPageNumbers
     * @param list<int> $pageNumbers
     * @param list<int> $allocationOrder
     * @param list<int> $cyclePath
     * @param list<string> $errors
     */
    public function __construct(
        public readonly int $expectedPageCount,
        public readonly ?int $firstTrunkPage,
        public readonly array $trunkPages,
        public readonly array $trunkPageNumbers,
        public readonly array $leafPageNumbers,
        public readonly array $pageNumbers,
        public readonly array $allocationOrder,
        public readonly ?int $cycleAtPage,
        public readonly array $cyclePath,
        public readonly int $actualPageCount,
        public readonly array $errors,
    ) {
    }

    public function hasCycle(): bool
    {
        return $this->cycleAtPage !== null;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array{
     *   expected_page_count:int,
     *   first_trunk_page:?int,
     *   trunk_page_numbers:list<int>,
     *   leaf_page_numbers:list<int>,
     *   page_numbers:list<int>,
     *   allocation_order:list<int>,
     *   cycle_at_page:?int,
     *   cycle_path:list<int>,
     *   actual_page_count:int,
     *   valid:bool,
     *   errors:list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'expected_page_count' => $this->expectedPageCount,
            'first_trunk_page' => $this->firstTrunkPage,
            'trunk_page_numbers' => $this->trunkPageNumbers,
            'leaf_page_numbers' => $this->leafPageNumbers,
            'page_numbers' => $this->pageNumbers,
            'allocation_order' => $this->allocationOrder,
            'cycle_at_page' => $this->cycleAtPage,
            'cycle_path' => $this->cyclePath,
            'actual_page_count' => $this->actualPageCount,
            'valid' => $this->isValid(),
            'errors' => $this->errors,
        ];
    }
}
