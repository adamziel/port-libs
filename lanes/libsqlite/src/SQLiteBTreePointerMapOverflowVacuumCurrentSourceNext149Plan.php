<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapOverflowVacuumCurrentSourceNext149Plan
{
    /**
     * @param list<array<string, mixed>> $currentSourceRows
     * @param list<array<string, mixed>> $vacuumRows
     */
    private function __construct(
        public readonly SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $basePlan,
        private readonly array $currentSourceRows,
        private readonly array $vacuumRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $currentOverflowChains
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromCurrentSourceOverflowChains(
        SQLiteDatabase $database,
        array $currentOverflowChains,
        array $deleteResults,
        int $maxTruncatedPages,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self {
        $basePlan = SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan::fromCurrentSourceOverflowChains(
            $database,
            $currentOverflowChains,
            $deleteResults,
            $maxTruncatedPages,
            $replacementPayload,
            $parentBtreePageNumber,
            $secureDelete,
        );

        return self::fromBasePlan($basePlan);
    }

    public static function fromBasePlan(SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $basePlan): self
    {
        $currentSourceRows = $basePlan->currentSourceRows();
        if ($currentSourceRows === []) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow vacuum next149 requires current-source overflow rows');
        }

        $allocated = array_flip($basePlan->allocatedOverflowPages());
        $truncated = array_flip($basePlan->vacuumPlan->truncatedFreedPointerMapPages());
        $surviving = array_flip($basePlan->vacuumPlan->survivingFreedPointerMapPages());
        $finalPageCount = $basePlan->databaseAfterAllocation->pageCount();

        $vacuumRows = [];
        foreach ($currentSourceRows as $row) {
            $pageNumber = $row['page_number'] ?? null;
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite b-tree pointer-map overflow vacuum next149 current-source page must be an integer');
            }

            $wasTruncated = isset($truncated[$pageNumber]);
            $wasAllocated = isset($allocated[$pageNumber]);
            $finalEntry = $wasTruncated && $pageNumber > $finalPageCount
                ? null
                : $basePlan->databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();

            $vacuumRows[] = [
                'source' => $row['source'] ?? null,
                'page_number' => $pageNumber,
                'current_chain_position' => $row['chain_position'] ?? null,
                'current_next_page' => $row['current_next_page'] ?? null,
                'current_pointer_map_type' => $row['current_pointer_map_type'] ?? null,
                'current_pointer_map_parent' => $row['current_pointer_map_parent'] ?? null,
                'survived_vacuum_as_free_page' => isset($surviving[$pageNumber]),
                'truncated_by_vacuum' => $wasTruncated,
                'reused_as_replacement_overflow' => $wasAllocated,
                'rejected_for_reuse_after_truncate' => $wasTruncated && !$wasAllocated,
                'final_page_count' => $finalPageCount,
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
            ];
        }

        return new self($basePlan, $currentSourceRows, $vacuumRows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currentSourceRows(): array
    {
        return $this->currentSourceRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vacuumRows(): array
    {
        return $this->vacuumRows;
    }

    /**
     * @return list<int>
     */
    public function truncatedCurrentSourcePagesRejectedForReuse(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->vacuumRows, static fn (array $row): bool => $row['rejected_for_reuse_after_truncate'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function currentSourcePagesReusedAfterVacuum(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->vacuumRows, static fn (array $row): bool => $row['reused_as_replacement_overflow'] === true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-pointermap-overflow-vacuum-current-source-next149',
            'released_overflow_pages' => $this->basePlan->releasedOverflowPages(),
            'truncated_page_numbers' => $this->basePlan->truncatedPageNumbers(),
            'allocated_overflow_pages' => $this->basePlan->allocatedOverflowPages(),
            'current_source_pages_reused_after_vacuum' => $this->currentSourcePagesReusedAfterVacuum(),
            'truncated_current_source_pages_rejected_for_reuse' => $this->truncatedCurrentSourcePagesRejectedForReuse(),
            'final_database_page_count' => $this->basePlan->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'btree_pointermap_overflow_vacuum_current_source_next149' => $this->vacuumRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }
}
