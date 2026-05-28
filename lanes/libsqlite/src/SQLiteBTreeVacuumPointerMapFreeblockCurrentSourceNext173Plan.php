<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext173Plan
{
    /**
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext167Plan $basePlan,
        private readonly array $transitionRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext167Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext167Plan $basePlan): self
    {
        $rows = self::buildTransitionRows($basePlan);
        $errors = self::transitionErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next173 transition audit failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transitionRows(): array
    {
        return $this->transitionRows;
    }

    /**
     * @return list<string>
     */
    public function transitionErrors(): array
    {
        return self::transitionErrorsForRows($this->transitionRows);
    }

    /**
     * @return list<int>
     */
    public function stableLeafPages(): array
    {
        return $this->pagesByStatus('stable-leaf-freeblock');
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowPages(): array
    {
        return $this->pagesByStatus('replacement-overflow');
    }

    /**
     * @return list<int>
     */
    public function rewrittenCurrentSourceNextPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->transitionRows, static fn (array $row): bool => $row['source_next_page'] !== $row['final_next_page']),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedTailPages(): array
    {
        return $this->pagesByStatus('truncated-tail-page');
    }

    /**
     * @return array<string, mixed>
     */
    public function transitionSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next173-ready',
            'stable_leaf_pages' => $this->stableLeafPages(),
            'replacement_overflow_pages' => $this->replacementOverflowPages(),
            'rewritten_current_source_next_pages' => $this->rewrittenCurrentSourceNextPages(),
            'truncated_tail_pages' => $this->truncatedTailPages(),
            'transition_signature' => self::signature(array_map(
                static fn (array $row): string => $row['status'] . ':' . $row['page_number'] . ':' . ($row['final_next_page'] ?? 'null'),
                $this->transitionRows,
            )),
            'dependency_closure' => 'no new support component needed; next173 reuses native b-tree leaf parsing, overflow-chain materialization, incremental-vacuum truncation, and auto-vacuum pointer-map helpers',
            'non_overlap' => 'adds current-source transition rows that reject stale overflow next-pointers after vacuum/rewrite; does not repeat next167 final leaf audit, next166 write admission, next164 chain continuity, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next173',
            'transition_summary' => $this->transitionSummary(),
            'transition_errors' => $this->transitionErrors(),
            'transition_rows' => $this->transitionRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<int>
     */
    private function pagesByStatus(string $status): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->transitionRows, static fn (array $row): bool => $row['status'] === $status),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext167Plan $basePlan): array
    {
        $rows = [];
        foreach ($basePlan->leafRows() as $row) {
            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'status' => 'stable-leaf-freeblock',
                'source_materialized' => true,
                'post_vacuum_materialized' => true,
                'final_materialized' => true,
                'source_next_page' => null,
                'post_vacuum_next_page' => null,
                'final_next_page' => null,
                'source_pointer_map_type' => $row['source_pointer_map_type'],
                'post_vacuum_pointer_map_type' => $row['post_vacuum_pointer_map_type'],
                'final_pointer_map_type' => $row['final_pointer_map_type'],
                'source_pointer_map_parent' => $row['source_pointer_map_parent'],
                'post_vacuum_pointer_map_parent' => $row['post_vacuum_pointer_map_parent'],
                'final_pointer_map_parent' => $row['final_pointer_map_parent'],
                'source_hash' => $row['source_hash'],
                'post_vacuum_hash' => $row['post_vacuum_hash'],
                'final_hash' => $row['final_hash'],
                'final_hash_matches_post_vacuum' => $row['final_hash_matches_post_vacuum'],
                'freeblocks_preserved_after_allocation' => $row['post_vacuum_freeblocks'] === $row['final_freeblocks'],
            ];
        }

        $releasedByPage = [];
        foreach ($basePlan->releasedPageRows() as $row) {
            $releasedByPage[(int) $row['page_number']] = $row;
        }

        foreach ($basePlan->basePlan->chainRows() as $row) {
            $pageNumber = (int) $row['page_number'];
            $released = $releasedByPage[$pageNumber] ?? [];
            $allocated = $row['allocated_for_replacement'] === true;
            $rows[] = [
                'page_number' => $pageNumber,
                'status' => $allocated ? 'replacement-overflow' : (string) $row['status'],
                'source_materialized' => $row['source_materialized'],
                'post_vacuum_materialized' => $row['post_vacuum_materialized'],
                'final_materialized' => $row['final_materialized'],
                'source_next_page' => $row['source_next_page'],
                'post_vacuum_next_page' => $row['post_vacuum_next_page'],
                'final_next_page' => $row['final_next_page'],
                'source_pointer_map_type' => $released['source_pointer_map_type'] ?? null,
                'post_vacuum_pointer_map_type' => $released['post_vacuum_pointer_map_type'] ?? null,
                'final_pointer_map_type' => $released['final_pointer_map_type'] ?? null,
                'source_pointer_map_parent' => $released['source_pointer_map_parent'] ?? null,
                'post_vacuum_pointer_map_parent' => $released['post_vacuum_pointer_map_parent'] ?? null,
                'final_pointer_map_parent' => $released['final_pointer_map_parent'] ?? null,
                'source_hash' => null,
                'post_vacuum_hash' => null,
                'final_hash' => $released['final_page_hash'] ?? $row['final_page_hash'],
                'final_hash_matches_post_vacuum' => null,
                'freeblocks_preserved_after_allocation' => null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ((int) $a['page_number']) <=> ((int) $b['page_number']));

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function transitionErrorsForRows(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if ($row['status'] === 'stable-leaf-freeblock') {
                if ($row['final_hash_matches_post_vacuum'] !== true) {
                    $errors[] = "leaf page {$row['page_number']} changed after replacement allocation";
                }
                if ($row['freeblocks_preserved_after_allocation'] !== true) {
                    $errors[] = "leaf page {$row['page_number']} freeblocks changed after replacement allocation";
                }
                continue;
            }

            if ($row['status'] === 'replacement-overflow') {
                if ($row['final_materialized'] !== true) {
                    $errors[] = "replacement overflow page {$row['page_number']} is not materialized";
                }
                if (!in_array($row['final_pointer_map_type'], ['first-overflow-page', 'overflow-page'], true)) {
                    $errors[] = "replacement overflow page {$row['page_number']} has invalid pointer-map type";
                }
                continue;
            }

            if ($row['status'] === 'truncated-tail-page' && $row['final_materialized'] !== false) {
                $errors[] = "truncated tail page {$row['page_number']} survived in final image";
            }
        }

        return $errors;
    }

    /**
     * @param list<string|int> $items
     */
    private static function signature(array $items): string
    {
        return hash('sha256', implode('|', array_map(static fn (string|int $item): string => (string) $item, $items)));
    }
}
