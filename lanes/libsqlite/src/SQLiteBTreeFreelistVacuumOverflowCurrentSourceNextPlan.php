<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $currentSourceRows
     * @param list<array<string, mixed>> $replacementRows
     */
    private function __construct(
        public readonly SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $basePlan,
        private readonly array $currentSourceRows,
        private readonly array $replacementRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $currentOverflowChains
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromCurrentSourceDeleteResults(
        SQLiteDatabase $database,
        array $currentOverflowChains,
        array $deleteResults,
        int $maxTruncatedPages,
        string $replacementPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = false,
    ): self {
        $currentSourceRows = self::buildCurrentSourceRows($database, $currentOverflowChains);
        $basePlan = SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan::overflowFreelistPointerMapFromDeleteResults(
            $database,
            $deleteResults,
            $maxTruncatedPages,
            $replacementPayload,
            $parentBtreePageNumber,
            $secureDelete,
        );

        return self::fromBasePlan($basePlan, $currentSourceRows);
    }

    /**
     * @param list<array<string, mixed>> $currentSourceRows
     */
    public static function fromBasePlan(
        SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $basePlan,
        array $currentSourceRows,
    ): self {
        if ($currentSourceRows === []) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum overflow next143 requires current-source overflow rows');
        }

        $currentByPage = [];
        foreach ($currentSourceRows as $row) {
            $pageNumber = $row['page_number'] ?? null;
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite b-tree freelist vacuum overflow next143 current-source row page must be an integer');
            }
            $currentByPage[$pageNumber] = $row;
        }

        $replacementRows = [];
        $allocatedPages = $basePlan->allocatedOverflowPages();
        foreach ($allocatedPages as $position => $pageNumber) {
            $current = $currentByPage[$pageNumber] ?? null;
            $entry = $basePlan->databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $replacementRows[] = [
                'page_number' => $pageNumber,
                'replacement_chain_position' => $position,
                'was_current_source_page' => $current !== null,
                'current_source' => $current['source'] ?? null,
                'current_chain_position' => $current['chain_position'] ?? null,
                'current_next_page' => $current['current_next_page'] ?? null,
                'current_pointer_map_type' => $current['current_pointer_map_type'] ?? null,
                'current_pointer_map_parent' => $current['current_pointer_map_parent'] ?? null,
                'vacuum_status' => self::vacuumStatusForPage($basePlan, $pageNumber),
                'replacement_next_page' => self::readUInt32($basePlan->databaseAfterAllocation->page($pageNumber), 0),
                'replacement_pointer_map_type' => $entry['type_name'],
                'replacement_pointer_map_parent' => $entry['parent_page_number'],
                'payload_prefix' => substr($basePlan->databaseAfterAllocation->page($pageNumber), 4, 16),
            ];
        }

        return new self($basePlan, $currentSourceRows, $replacementRows);
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
    public function replacementRows(): array
    {
        return $this->replacementRows;
    }

    /**
     * @return list<int>
     */
    public function reusedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->replacementRows, static fn (array $row): bool => $row['was_current_source_page'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedTruncatedCurrentSourcePages(): array
    {
        $allocated = array_fill_keys($this->basePlan->allocatedOverflowPages(), true);

        return array_values(array_filter(
            $this->basePlan->vacuumPlan->truncatedFreedPointerMapPages(),
            static fn (int $pageNumber): bool => !isset($allocated[$pageNumber]),
        ));
    }

    /**
     * @return list<int>
     */
    public function replacementChainNextPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['replacement_next_page'],
            $this->replacementRows,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freelist-vacuum-overflow-current-source-next143',
            'released_overflow_pages' => $this->basePlan->releasedOverflowPages(),
            'truncated_current_source_pages' => $this->basePlan->vacuumPlan->truncatedFreedPointerMapPages(),
            'surviving_current_source_pages' => $this->basePlan->vacuumPlan->survivingFreedPointerMapPages(),
            'allocated_overflow_pages' => $this->basePlan->allocatedOverflowPages(),
            'reused_current_source_pages' => $this->reusedCurrentSourcePages(),
            'rejected_truncated_current_source_pages' => $this->rejectedTruncatedCurrentSourcePages(),
            'replacement_chain_next_pages' => $this->replacementChainNextPages(),
            'final_database_page_count' => $this->basePlan->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->basePlan->pageImages()),
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'btree_freelist_vacuum_overflow_current_source_next143' => $this->replacementRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $chains
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteDatabase $database, array $chains): array
    {
        $rows = [];
        foreach (array_values($chains) as $chainIndex => $chain) {
            $firstPage = $chain['first_page'] ?? null;
            $payloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite b-tree freelist vacuum overflow next143 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree freelist vacuum overflow next143 chain is missing an overflow payload byte count');
            }

            $source = $chain['source'] ?? "current-source-overflow-chain-{$chainIndex}";
            foreach (SQLiteOverflowPage::chainLinksFromDatabase($database, $firstPage, $payloadBytes) as $position => $link) {
                $entry = $database->pointerMapEntryForPage($link['current_page'])->toArray();
                $rows[] = [
                    'source' => $source,
                    'chain_index' => $chainIndex,
                    'chain_position' => $position,
                    'page_number' => $link['current_page'],
                    'current_next_page' => $link['next_page'],
                    'current_payload_bytes' => $link['payload_bytes'],
                    'current_terminal' => $link['terminal'],
                    'current_pointer_map_type' => $entry['type_name'],
                    'current_pointer_map_parent' => $entry['parent_page_number'],
                ];
            }
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum overflow next143 requires at least one current-source overflow page');
        }

        return $rows;
    }

    private static function vacuumStatusForPage(
        SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $basePlan,
        int $pageNumber,
    ): ?string {
        foreach ($basePlan->transitionRows() as $row) {
            if (($row['page_number'] ?? null) === $pageNumber) {
                return is_string($row['vacuum_status'] ?? null) ? $row['vacuum_status'] : null;
            }
        }

        return null;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree freelist vacuum overflow next143 could not read uint32');
        }

        return $value[1];
    }
}
