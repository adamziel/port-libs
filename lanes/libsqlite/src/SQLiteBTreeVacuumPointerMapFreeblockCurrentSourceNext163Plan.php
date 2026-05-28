<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext163Plan
{
    /**
     * @param list<array<string, mixed>> $fenceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Plan $basePlan,
        private readonly array $fenceRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Plan $basePlan): self
    {
        if ($basePlan->replacementOverflowPages() === []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock current-source next163 requires replacement overflow pages');
        }
        if ($basePlan->leafFreeblockPages() === []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock current-source next163 requires a deleted leaf freeblock');
        }

        foreach ($basePlan->chainRows as $row) {
            if (($row['pointer_map_matches_chain'] ?? false) !== true || ($row['next_pointer_matches_chain'] ?? false) !== true) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next163 replacement chain is inconsistent');
            }
            if (($row['truncated_current_source_page_reused'] ?? false) === true) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next163 cannot reuse a truncated current-source page');
            }
        }

        return new self($basePlan, self::buildFenceRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fenceRows(): array
    {
        return $this->fenceRows;
    }

    /**
     * @return list<int>
     */
    public function admittedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->fenceRows, static fn (array $row): bool => $row['current_source_admitted'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->fenceRows, static fn (array $row): bool => $row['current_source_rejected'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function replacementChainPages(): array
    {
        return $this->basePlan->replacementOverflowPages();
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSourceFence(): array
    {
        $base156 = $this->basePlan->basePlan;
        $released = $base156->basePlan->basePlan->basePlan->releasedOverflowPages();
        $surviving = $base156->basePlan->basePlan->survivingReleasedOverflowPages();
        $truncated = $base156->basePlan->basePlan->truncatedReleasedOverflowPages();
        $leafPage = $base156->basePlan->basePlan->basePlan->deletePlan->leafPageNumber;
        $leafImage = $base156->basePlan->basePlan->basePlan->deletePlan->leafPageImage;

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next163-ready',
            'leaf_page' => $leafPage,
            'leaf_freeblock_pages' => $this->basePlan->leafFreeblockPages(),
            'released_overflow_pages' => $released,
            'surviving_released_overflow_pages' => $surviving,
            'truncated_released_overflow_pages' => $truncated,
            'replacement_chain_pages' => $this->replacementChainPages(),
            'admitted_current_source_pages' => $this->admittedCurrentSourcePages(),
            'rejected_current_source_pages' => $this->rejectedCurrentSourcePages(),
            'source_chain_signature' => self::signature($released),
            'surviving_chain_signature' => self::signature($surviving),
            'truncated_chain_signature' => self::signature($truncated),
            'replacement_chain_signature' => self::signature($this->replacementChainPages()),
            'leaf_freeblock_hash' => hash('sha256', $leafImage),
            'final_database_page_count' => $base156->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $base156->databaseAfterAllocation->freelistPageNumbers(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next160',
                'sqlite-current-source-next163',
            ],
            'dependency_closure' => 'no new support component needed; next163 reuses native b-tree vacuum, freelist allocation, overflow encoding, and pointer-map page image application',
            'non_overlap' => 'adds current-source admission fencing for replacement overflow chains after vacuum truncation; does not repeat next160 chain pointer validation, next159 row imaging, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next163',
            'current_source_fence' => $this->currentSourceFence(),
            'fence_rows' => $this->fenceRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildFenceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Plan $basePlan): array
    {
        $base156 = $basePlan->basePlan;
        $sourceDatabase = $base156->basePlan->basePlan->basePlan->sourceDatabase;
        $finalDatabase = $base156->databaseAfterAllocation;
        $allocatedByPage = [];
        foreach ($basePlan->chainRows as $row) {
            $allocatedByPage[(int) $row['page_number']] = $row;
        }

        $rows = [];
        foreach ($base156->basePlan->basePlan->basePlan->releasedOverflowPages() as $position => $pageNumber) {
            $allocatedRow = $allocatedByPage[$pageNumber] ?? null;
            $sourceEntry = self::pointerMapEntry($sourceDatabase, $pageNumber);
            $finalEntry = $pageNumber <= $finalDatabase->pageCount()
                ? self::pointerMapEntry($finalDatabase, $pageNumber)
                : null;
            $sourceNext = self::readUInt32($sourceDatabase->page($pageNumber), 0);
            $finalNext = $pageNumber <= $finalDatabase->pageCount()
                ? self::readUInt32($finalDatabase->page($pageNumber), 0)
                : null;

            $rows[] = [
                'source_chain_position' => $position,
                'page_number' => $pageNumber,
                'source_overflow_next_page' => $sourceNext,
                'final_overflow_next_page' => $finalNext,
                'source_pointer_map_type' => $sourceEntry['type_name'] ?? null,
                'source_pointer_map_parent' => $sourceEntry['parent_page_number'] ?? null,
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
                'replacement_chain_position' => $allocatedRow['chain_position'] ?? null,
                'replacement_expected_next_page' => $allocatedRow['expected_next_page'] ?? null,
                'replacement_expected_parent' => $allocatedRow['expected_pointer_map_parent'] ?? null,
                'current_source_admitted' => $allocatedRow !== null,
                'current_source_rejected' => $allocatedRow === null,
                'admission_status' => $allocatedRow === null
                    ? 'rejected-after-vacuum-truncate'
                    : 'admitted-as-replacement-overflow-page',
                'source_page_hash' => hash('sha256', $sourceDatabase->page($pageNumber)),
                'final_page_hash' => $pageNumber <= $finalDatabase->pageCount()
                    ? hash('sha256', $finalDatabase->page($pageNumber))
                    : null,
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

    /**
     * @param list<int> $pageNumbers
     */
    private static function signature(array $pageNumbers): string
    {
        return hash('sha256', implode(',', $pageNumbers));
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next163 could not read uint32');
        }

        return $value[1];
    }
}
