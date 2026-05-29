<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext169Plan
{
    /**
     * @param list<array<string, mixed>> $writeGateRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $writeGateRows,
    ) {
    }

    /**
     * @param array<string, mixed> $deleteResult
     * @param array<int, string> $observedCurrentPages
     */
    public static function tableLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
        array $observedCurrentPages = [],
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext165(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ), $observedCurrentPages);
    }

    /**
     * @param array<int, string> $observedCurrentPages
     */
    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan, array $observedCurrentPages = []): self
    {
        $rows = self::buildWriteGateRows($basePlan, $observedCurrentPages);
        if (self::admittedPagesFromRows($rows) === []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next169 has no admitted writable pages');
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function writeGateRows(): array
    {
        return $this->writeGateRows;
    }

    /**
     * @return list<int>
     */
    public function admittedCurrentSourcePages(): array
    {
        return self::admittedPagesFromRows($this->writeGateRows);
    }

    /**
     * @return list<int>
     */
    public function staleCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeGateRows, static fn (array $row): bool => $row['gate_status'] === 'rejected-stale-current-source'),
        ));
    }

    /**
     * @return list<int>
     */
    public function vacuumRejectedPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeGateRows, static fn (array $row): bool => $row['gate_status'] === 'rejected-after-vacuum-truncate'),
        ));
    }

    /**
     * @return list<int>
     */
    public function changedAdmittedPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeGateRows, static fn (array $row): bool => $row['gate_status'] === 'admitted-current-source-match' && $row['page_changed']),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSourceGate(): array
    {
        $base = $this->basePlan->toArray();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next169-ready',
            'leaf_page' => $base['leaf_page'],
            'replacement_overflow_pages' => $base['replacement_overflow_pages'],
            'replacement_overflow_next_pages' => $base['replacement_overflow_next_pages'],
            'replacement_pointer_map_parents' => $base['replacement_pointer_map_parents'],
            'admitted_current_source_pages' => $this->admittedCurrentSourcePages(),
            'changed_admitted_pages' => $this->changedAdmittedPages(),
            'stale_current_source_pages' => $this->staleCurrentSourcePages(),
            'vacuum_rejected_pages' => $this->vacuumRejectedPages(),
            'write_gate_signature' => self::signature(array_column($this->writeGateRows, 'gate_status')),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next165',
                'sqlite-current-source-next169',
            ],
            'dependency_closure' => 'no new support component needed; next169 reuses native b-tree delete, vacuum, freelist allocation, pointer-map, and page-hash primitives',
            'non_overlap' => 'adds current-source hash admission before applying vacuum/freeblock replacement pages; does not repeat next165 source/next row imaging, next163 fence rows, overflow freelist release, bulk freeblocks, page relocation, root collapse, or freelist trunk reuse',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next169',
            'current_source_gate' => $this->currentSourceGate(),
            'write_gate_rows' => $this->writeGateRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param array<int, string> $observedCurrentPages
     * @return list<array<string, mixed>>
     */
    private static function buildWriteGateRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan, array $observedCurrentPages): array
    {
        $rows = [];
        foreach ($basePlan->sourceNextRows() as $row) {
            $pageNumber = (int) $row['page_number'];
            $expectedHash = $row['current_page_hash'];
            $observedPage = $observedCurrentPages[$pageNumber] ?? null;
            $observedHash = $observedPage === null ? $expectedHash : hash('sha256', $observedPage);
            $sourceMatches = $expectedHash !== null && $observedHash === $expectedHash;
            $writeAllowed = (bool) $row['write_allowed'];
            $gateStatus = !$writeAllowed
                ? 'rejected-after-vacuum-truncate'
                : ($sourceMatches ? 'admitted-current-source-match' : 'rejected-stale-current-source');

            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => $row['write_kind'],
                'write_allowed_by_vacuum' => $writeAllowed,
                'expected_current_page_hash' => $expectedHash,
                'observed_current_page_hash' => $observedHash,
                'next_page_hash' => $row['next_page_hash'],
                'current_source_matches' => $sourceMatches,
                'page_changed' => (bool) $row['page_changed'],
                'gate_status' => $gateStatus,
                'write_admitted' => $gateStatus === 'admitted-current-source-match',
                'pointer_map_changed' => (bool) $row['pointer_map_changed'],
                'current_pointer_map_type' => $row['current_pointer_map_type'],
                'next_pointer_map_type' => $row['next_pointer_map_type'],
                'current_pointer_map_parent' => $row['current_pointer_map_parent'],
                'next_pointer_map_parent' => $row['next_pointer_map_parent'],
                'current_overflow_next_page' => $row['current_overflow_next_page'],
                'next_overflow_next_page' => $row['next_overflow_next_page'],
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private static function admittedPagesFromRows(array $rows): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => $row['gate_status'] === 'admitted-current-source-match'),
        ));
    }

    /**
     * @param list<mixed> $values
     */
    private static function signature(array $values): string
    {
        return hash('sha256', implode('|', array_map(static fn (mixed $value): string => (string) $value, $values)));
    }
}
