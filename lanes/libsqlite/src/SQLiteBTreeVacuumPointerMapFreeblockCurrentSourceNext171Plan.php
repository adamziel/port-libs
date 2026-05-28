<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext171Plan
{
    /**
     * @param list<array<string, mixed>> $sourceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext167Plan $basePlan,
        private readonly array $sourceRows,
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
        $rows = self::buildSourceRows($basePlan);
        $errors = self::sourceTransitionErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next171 transition is inconsistent: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceRows(): array
    {
        return $this->sourceRows;
    }

    /**
     * @return list<int>
     */
    public function stableLeafPages(): array
    {
        return $this->pagesByStatus('stable-leaf-freeblock-page');
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowPages(): array
    {
        return $this->pagesByStatus('replacement-overflow-current-source-page');
    }

    /**
     * @return list<int>
     */
    public function rejectedTruncatedPages(): array
    {
        return $this->pagesByStatus('rejected-truncated-current-source-page');
    }

    /**
     * @return list<int>
     */
    public function survivingFreePages(): array
    {
        return $this->pagesByStatus('surviving-free-current-source-page');
    }

    /**
     * @return list<string>
     */
    public function sourceTransitionErrors(): array
    {
        return self::sourceTransitionErrorsForRows($this->sourceRows);
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceTransitionSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next171-ready',
            'stable_leaf_pages' => $this->stableLeafPages(),
            'replacement_overflow_pages' => $this->replacementOverflowPages(),
            'surviving_free_pages' => $this->survivingFreePages(),
            'rejected_truncated_pages' => $this->rejectedTruncatedPages(),
            'base_replacement_pointer_map_pages' => $this->basePlan->replacementPointerMapPagesAfterVacuum(),
            'base_free_pointer_map_pages' => $this->basePlan->freePointerMapPagesAfterVacuum(),
            'base_changed_current_source_next_pages' => $this->basePlan->currentSourceAudit()['changed_current_source_next_pages'],
            'base_reused_truncated_current_source_pages' => $this->basePlan->currentSourceAudit()['reused_truncated_current_source_pages'],
            'transition_signature' => self::signature(array_map(
                static fn (array $row): string => $row['page_number'] . ':' . $row['transition_status'],
                $this->sourceRows,
            )),
            'dependency_closure' => 'no new support component needed; next171 reuses native b-tree leaf/freeblock images, overflow-chain allocation, pointer-map rows, and incremental-vacuum truncation helpers',
            'non_overlap' => 'adds current-source transition classification after next167 final-image auditing; does not repeat next167 leaf hash/freeblock checks, next164 chain continuity, overflow freelist release, root collapse, page move, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next171',
            'source_transition_summary' => $this->sourceTransitionSummary(),
            'source_transition_errors' => $this->sourceTransitionErrors(),
            'source_rows' => $this->sourceRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param list<string> $labels
     */
    private static function signature(array $labels): string
    {
        return hash('sha256', implode('|', $labels));
    }

    /**
     * @return list<int>
     */
    private function pagesByStatus(string $status): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceRows, static fn (array $row): bool => $row['transition_status'] === $status),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSourceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext167Plan $basePlan): array
    {
        $rows = [];
        foreach ($basePlan->leafRows() as $leafRow) {
            $rows[] = [
                'page_number' => (int) $leafRow['page_number'],
                'transition_status' => 'stable-leaf-freeblock-page',
                'source_pointer_map_type' => $leafRow['source_pointer_map_type'],
                'post_vacuum_pointer_map_type' => $leafRow['post_vacuum_pointer_map_type'],
                'final_pointer_map_type' => $leafRow['final_pointer_map_type'],
                'source_pointer_map_parent' => $leafRow['source_pointer_map_parent'],
                'final_pointer_map_parent' => $leafRow['final_pointer_map_parent'],
                'source_hash' => $leafRow['source_hash'],
                'post_vacuum_hash' => $leafRow['post_vacuum_hash'],
                'final_hash' => $leafRow['final_hash'],
                'hash_changed_from_source' => $leafRow['source_hash'] !== $leafRow['final_hash'],
                'hash_matches_post_vacuum' => $leafRow['final_hash_matches_post_vacuum'],
                'final_materialized' => true,
                'allocated_for_replacement' => false,
            ];
        }

        foreach ($basePlan->releasedPageRows() as $releasedRow) {
            $allocated = $releasedRow['allocated_for_replacement'] === true;
            $materialized = $releasedRow['final_materialized'] === true;
            $transitionStatus = $allocated
                ? 'replacement-overflow-current-source-page'
                : ($materialized ? 'surviving-free-current-source-page' : 'rejected-truncated-current-source-page');

            $rows[] = [
                'page_number' => (int) $releasedRow['page_number'],
                'transition_status' => $transitionStatus,
                'source_pointer_map_type' => $releasedRow['source_pointer_map_type'],
                'post_vacuum_pointer_map_type' => $releasedRow['post_vacuum_pointer_map_type'],
                'final_pointer_map_type' => $releasedRow['final_pointer_map_type'],
                'source_pointer_map_parent' => $releasedRow['source_pointer_map_parent'],
                'final_pointer_map_parent' => $releasedRow['final_pointer_map_parent'],
                'source_hash' => null,
                'post_vacuum_hash' => null,
                'final_hash' => $releasedRow['final_page_hash'],
                'hash_changed_from_source' => null,
                'hash_matches_post_vacuum' => null,
                'final_materialized' => $materialized,
                'allocated_for_replacement' => $allocated,
                'final_next_page' => $releasedRow['final_next_page'],
                'final_status' => $releasedRow['final_status'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ((int) $a['page_number']) <=> ((int) $b['page_number']));

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function sourceTransitionErrorsForRows(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if ($row['transition_status'] === 'stable-leaf-freeblock-page' && $row['hash_matches_post_vacuum'] !== true) {
                $errors[] = "leaf page {$row['page_number']} does not match the post-vacuum image";
            }
            if ($row['transition_status'] === 'replacement-overflow-current-source-page' && $row['allocated_for_replacement'] !== true) {
                $errors[] = "replacement page {$row['page_number']} was not marked allocated";
            }
            if ($row['transition_status'] === 'replacement-overflow-current-source-page' && $row['final_materialized'] !== true) {
                $errors[] = "replacement page {$row['page_number']} is not materialized";
            }
            if ($row['transition_status'] === 'replacement-overflow-current-source-page' && !in_array($row['final_pointer_map_type'], ['first-overflow-page', 'overflow-page'], true)) {
                $errors[] = "replacement page {$row['page_number']} has pointer-map type {$row['final_pointer_map_type']}";
            }
            if ($row['transition_status'] === 'surviving-free-current-source-page' && $row['final_pointer_map_type'] !== 'free-page') {
                $errors[] = "surviving free page {$row['page_number']} has pointer-map type {$row['final_pointer_map_type']}";
            }
            if ($row['transition_status'] === 'rejected-truncated-current-source-page' && $row['final_materialized'] === true) {
                $errors[] = "truncated current-source page {$row['page_number']} remained materialized";
            }
            if ($row['transition_status'] === 'rejected-truncated-current-source-page' && $row['final_pointer_map_type'] !== null) {
                $errors[] = "truncated current-source page {$row['page_number']} retained pointer-map type {$row['final_pointer_map_type']}";
            }
        }

        return $errors;
    }
}
