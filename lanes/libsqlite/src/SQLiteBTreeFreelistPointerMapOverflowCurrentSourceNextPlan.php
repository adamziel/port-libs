<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNextPlan
{
    private function __construct(
        public readonly SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan $basePlan,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $releasedChains
     */
    public static function fromOverflowChains(
        SQLiteDatabase $database,
        array $releasedChains,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
    ): self {
        $basePlan = SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan::fromOverflowChains(
            $database,
            $releasedChains,
            $replacementPayload,
            $parentBtreePageNumber,
            $secureDelete,
        );

        if ($basePlan->allocationPlan->firstFreelistTrunkPage !== 0) {
            throw new \InvalidArgumentException('SQLite b-tree freelist pointer-map overflow next136 requires replacement allocation to consume the freelist trunk');
        }
        $trunkPages = array_filter(
            $basePlan->allocatedOverflowPages(),
            static fn (int $pageNumber): bool => in_array($pageNumber, $basePlan->releasePlan->freePlan->newTrunkPageNumbers, true)
                || in_array($pageNumber, $basePlan->releasePlan->freePlan->existingTrunkPageNumbers(), true),
        );
        if ($trunkPages === []) {
            throw new \InvalidArgumentException('SQLite b-tree freelist pointer-map overflow next136 requires an allocated freelist trunk page');
        }

        return new self($basePlan);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trunkOverflowRows(): array
    {
        $releasePlan = $this->basePlan->releasePlan;
        $allocationSteps = $this->basePlan->allocationPlan->allocationSteps();
        $allocated = $this->basePlan->allocatedOverflowPages();
        $rows = [];
        $releaseTrunks = array_fill_keys($releasePlan->freePlan->newTrunkPageNumbers, 'new-trunk');
        foreach ($releasePlan->freePlan->existingTrunkPageNumbers() as $pageNumber) {
            $releaseTrunks[$pageNumber] = 'existing-trunk';
        }

        foreach ($allocated as $position => $pageNumber) {
            if (!isset($releaseTrunks[$pageNumber])) {
                continue;
            }

            $before = $this->basePlan->databaseAfterRelease->page($pageNumber);
            $after = $this->basePlan->databaseAfterAllocation->page($pageNumber);
            $beforeEntry = $this->basePlan->databaseAfterRelease->pointerMapEntryForPage($pageNumber)->toArray();
            $afterEntry = $this->basePlan->databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $step = $allocationSteps[$position] ?? [];

            $rows[] = [
                'page_number' => $pageNumber,
                'release_trunk_role' => $releaseTrunks[$pageNumber],
                'allocation_source' => $step['source'] ?? null,
                'replacement_chain_position' => $position,
                'before_freelist_next_trunk_page' => self::readUInt32($before, 0),
                'before_freelist_leaf_count' => self::readUInt32($before, 4),
                'after_overflow_next_page' => self::readUInt32($after, 0),
                'after_stale_leaf_count_bytes' => self::readUInt32($after, 4),
                'before_pointer_map_type' => $beforeEntry['type_name'],
                'before_pointer_map_parent' => $beforeEntry['parent_page_number'],
                'after_pointer_map_type' => $afterEntry['type_name'],
                'after_pointer_map_parent' => $afterEntry['parent_page_number'],
                'payload_prefix' => substr($after, 4, 16),
                'freelist_empty_after_allocation' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers() === [],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function chainRows(): array
    {
        return $this->basePlan->reuseRows();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freelist-pointermap-overflow-current-source-next136',
            'released_overflow_pages' => $this->basePlan->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->basePlan->allocatedOverflowPages(),
            'reused_released_overflow_pages' => $this->basePlan->reusedReleasedOverflowPages(),
            'final_first_freelist_trunk_page' => $this->basePlan->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->basePlan->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'trunk_overflow_rows' => $this->trunkOverflowRows(),
            'replacement_chain_rows' => $this->chainRows(),
            'base' => $this->basePlan->toArray(),
        ];
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \RuntimeException('Unable to read SQLite next136 uint32 field');
        }

        return (int) $value[1];
    }
}
