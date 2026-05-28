<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext159Plan
{
    /**
     * @param list<array<string, mixed>> $chainRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Plan $basePlan,
        private readonly array $chainRows,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Plan $basePlan): self
    {
        if (count($basePlan->allocatedOverflowPages()) < 2) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next159 requires a multi-page replacement overflow chain');
        }

        return new self($basePlan, self::buildChainRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function chainRows(): array
    {
        return $this->chainRows;
    }

    /**
     * @return list<int>
     */
    public function reusedSurvivingChainPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['reused_surviving_released_page'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedTruncatedChainPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['rejected_after_truncate'] === true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next159',
            'leaf_page' => $this->basePlan->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->basePlan->allocatedOverflowPages(),
            'reused_surviving_chain_pages' => $this->reusedSurvivingChainPages(),
            'rejected_truncated_chain_pages' => $this->rejectedTruncatedChainPages(),
            'final_database_page_count' => $this->basePlan->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'chain_rows' => $this->chainRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildChainRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Plan $basePlan): array
    {
        $sourceDatabase = $basePlan->basePlan->basePlan->basePlan->sourceDatabase;
        $postVacuumDatabase = $basePlan->basePlan->basePlan->basePlan->nextDatabase;
        $finalDatabase = $basePlan->databaseAfterAllocation;
        $allocated = array_fill_keys($basePlan->allocatedOverflowPages(), true);
        $surviving = array_fill_keys($basePlan->basePlan->basePlan->survivingReleasedOverflowPages(), true);
        $rows = [];

        foreach ($basePlan->rows as $row) {
            if ($row['kind'] !== 'released-overflow-page') {
                continue;
            }

            $pageNumber = (int) $row['page_number'];
            $isAllocated = isset($allocated[$pageNumber]);
            $isFinalMaterialized = $pageNumber <= $finalDatabase->pageCount();
            $sourceEntry = self::pointerMapEntry($sourceDatabase, $pageNumber);
            $postVacuumEntry = self::pointerMapEntry($postVacuumDatabase, $pageNumber);
            $finalEntry = self::pointerMapEntry($finalDatabase, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'source_overflow_next_page' => self::readUInt32($sourceDatabase->page($pageNumber), 0),
                'post_vacuum_overflow_next_page' => $pageNumber <= $postVacuumDatabase->pageCount()
                    ? self::readUInt32($postVacuumDatabase->page($pageNumber), 0)
                    : null,
                'final_overflow_next_page' => $isFinalMaterialized ? self::readUInt32($finalDatabase->page($pageNumber), 0) : null,
                'source_pointer_map_type' => $sourceEntry['type_name'] ?? null,
                'source_pointer_map_parent' => $sourceEntry['parent_page_number'] ?? null,
                'post_vacuum_pointer_map_type' => $postVacuumEntry['type_name'] ?? null,
                'post_vacuum_pointer_map_parent' => $postVacuumEntry['parent_page_number'] ?? null,
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
                'reused_surviving_released_page' => $isAllocated && isset($surviving[$pageNumber]),
                'allocated_for_replacement' => $isAllocated,
                'rejected_after_truncate' => !$isAllocated && !$isFinalMaterialized,
                'final_materialized' => $isFinalMaterialized,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next159 could not read uint32');
        }

        return $value[1];
    }
}
