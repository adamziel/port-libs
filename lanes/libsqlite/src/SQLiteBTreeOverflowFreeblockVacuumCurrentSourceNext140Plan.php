<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNext140Plan
{
    /**
     * @param list<array<string, mixed>> $currentSourceRows
     * @param list<array<string, mixed>> $vacuumRows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $basePlan,
        private readonly array $currentSourceRows,
        private readonly array $vacuumRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $currentOverflowChains
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromCurrentSourceDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentOverflowChains,
        array $deleteResult,
        int $maxTruncatedPages,
        bool $secureDelete = false,
    ): self {
        $currentSourceRows = self::buildCurrentSourceRows($database, $currentOverflowChains);
        $basePlan = SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $secureDelete,
        );

        return self::fromBasePlan($basePlan, $currentSourceRows);
    }

    /**
     * @param list<array<string, mixed>> $currentSourceRows
     */
    public static function fromBasePlan(
        SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $basePlan,
        array $currentSourceRows,
    ): self {
        if ($currentSourceRows === []) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 requires current-source overflow rows');
        }

        $currentByPage = [];
        foreach ($currentSourceRows as $row) {
            $pageNumber = $row['page_number'] ?? null;
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 current-source row page must be an integer');
            }
            $currentByPage[$pageNumber] = $row;
        }

        $vacuumRows = [];
        foreach ($basePlan->rows as $row) {
            $pageNumber = (int) $row['page_number'];
            $current = $currentByPage[$pageNumber] ?? null;
            $vacuumRows[] = [
                'page_number' => $pageNumber,
                'source' => $current['source'] ?? null,
                'chain_position' => $current['chain_position'] ?? null,
                'current_next_page' => $current['current_next_page'] ?? null,
                'current_terminal' => $current['current_terminal'] ?? null,
                'current_payload_bytes' => $current['current_payload_bytes'] ?? null,
                'current_pointer_map_type' => $current['current_pointer_map_type'] ?? $row['current_pointer_map_type'],
                'current_pointer_map_parent' => $current['current_pointer_map_parent'] ?? $row['current_pointer_map_parent'],
                'leaf_freeblock_status' => $basePlan->materializedApplySummary()['freeblock_integrity_status'],
                'freelist_role' => $row['freelist_role'],
                'vacuum_status' => $row['vacuum_status'],
                'next_pointer_map_type' => $row['next_pointer_map_type'],
                'next_pointer_map_parent' => $row['next_pointer_map_parent'],
                'materialized_after_vacuum' => $row['vacuum_status'] !== 'truncated',
                'truncated_after_vacuum' => $row['vacuum_status'] === 'truncated',
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
    public function survivingCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->vacuumRows, static fn (array $row): bool => $row['vacuum_status'] !== 'truncated'),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->vacuumRows, static fn (array $row): bool => $row['vacuum_status'] === 'truncated'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freeblock-vacuum-current-source-next140',
            'leaf_page' => $this->basePlan->toArray()['leaf_page'],
            'leaf_page_type' => $this->basePlan->toArray()['leaf_page_type'],
            'released_overflow_pages' => $this->basePlan->toArray()['released_overflow_pages'],
            'surviving_current_source_pages' => $this->survivingCurrentSourcePages(),
            'truncated_current_source_pages' => $this->truncatedCurrentSourcePages(),
            'final_database_page_count' => $this->basePlan->toArray()['final_database_page_count'],
            'final_first_freelist_trunk_page' => $this->basePlan->toArray()['final_first_freelist_trunk_page'],
            'final_freelist_page_count' => $this->basePlan->toArray()['final_freelist_page_count'],
            'final_freelist_page_numbers' => $this->basePlan->toArray()['final_freelist_page_numbers'],
            'updated_page_numbers' => $this->basePlan->toArray()['updated_page_numbers'],
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'btree_overflow_freeblock_vacuum_current_source_next140' => $this->vacuumRows,
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
                throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 chain is missing an overflow payload byte count');
            }

            $source = $chain['source'] ?? "current-overflow-chain-{$chainIndex}";
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
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 requires at least one current-source overflow page');
        }

        return $rows;
    }
}
