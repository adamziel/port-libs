<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext176Plan
{
    /**
     * @param list<array<string, mixed>> $sourceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext173(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): self
    {
        $rows = self::buildSourceRows($basePlan);
        $errors = self::sourceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next176 source boundary failed: ' . implode('; ', $errors));
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
     * @return list<string>
     */
    public function sourceErrors(): array
    {
        return self::sourceErrorsForRows($this->sourceRows);
    }

    /**
     * @return list<int>
     */
    public function postDeleteLeafPages(): array
    {
        return $this->pagesBySource('post-delete-leaf-current-source');
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowPages(): array
    {
        return $this->pagesBySource('replacement-overflow-current-source');
    }

    /**
     * @return list<int>
     */
    public function rejectedTailPages(): array
    {
        return $this->pagesBySource('rejected-truncated-tail');
    }

    /**
     * @return list<int>
     */
    public function staleSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceRows, static fn (array $row): bool => $row['stale_source_bytes_visible'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function rewrittenNextPointerPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceRows, static fn (array $row): bool => $row['next_pointer_rewritten'] === true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceBoundarySummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next176-ready',
            'post_delete_leaf_pages' => $this->postDeleteLeafPages(),
            'replacement_overflow_pages' => $this->replacementOverflowPages(),
            'rejected_tail_pages' => $this->rejectedTailPages(),
            'rewritten_next_pointer_pages' => $this->rewrittenNextPointerPages(),
            'stale_source_pages' => $this->staleSourcePages(),
            'source_boundary_signature' => self::signature(array_map(
                static fn (array $row): string => $row['authoritative_source'] . ':' . $row['page_number'] . ':' . ($row['authoritative_next_page'] ?? 'null'),
                $this->sourceRows,
            )),
            'dependency_closure' => 'no new support component needed; next176 reuses native b-tree leaf/freeblock, overflow-chain, incremental-vacuum truncation, and auto-vacuum pointer-map helpers',
            'non_overlap' => 'adds the downstream current-source selection boundary after next173 transition rows; it does not repeat next173 transition auditing, next167 final leaf auditing, next166 write admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next176',
            'source_boundary_summary' => $this->sourceBoundarySummary(),
            'source_errors' => $this->sourceErrors(),
            'source_rows' => $this->sourceRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<int>
     */
    private function pagesBySource(string $source): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceRows, static fn (array $row): bool => $row['authoritative_source'] === $source),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSourceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $rows = [];
        foreach ($basePlan->transitionRows() as $row) {
            $status = (string) $row['status'];
            $authoritativeSource = match ($status) {
                'stable-leaf-freeblock' => 'post-delete-leaf-current-source',
                'replacement-overflow' => 'replacement-overflow-current-source',
                'truncated-tail-page' => 'rejected-truncated-tail',
                default => 'surviving-free-current-source',
            };
            $sourceNextPage = $row['source_next_page'];
            $finalNextPage = $row['final_next_page'];
            $finalPointerMapType = $row['final_pointer_map_type'];

            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'transition_status' => $status,
                'authoritative_source' => $authoritativeSource,
                'read_admitted' => $authoritativeSource !== 'rejected-truncated-tail',
                'source_materialized' => $row['source_materialized'],
                'final_materialized' => $row['final_materialized'],
                'source_next_page' => $sourceNextPage,
                'authoritative_next_page' => $authoritativeSource === 'rejected-truncated-tail' ? null : $finalNextPage,
                'next_pointer_rewritten' => $sourceNextPage !== $finalNextPage,
                'source_pointer_map_type' => $row['source_pointer_map_type'],
                'final_pointer_map_type' => $finalPointerMapType,
                'final_pointer_map_parent' => $row['final_pointer_map_parent'],
                'freeblocks_preserved_after_allocation' => $row['freeblocks_preserved_after_allocation'],
                'final_hash_matches_post_vacuum' => $row['final_hash_matches_post_vacuum'],
                'stale_source_bytes_visible' => self::staleBytesVisible($authoritativeSource, $row),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function staleBytesVisible(string $authoritativeSource, array $row): bool
    {
        if ($authoritativeSource === 'post-delete-leaf-current-source') {
            return $row['final_hash_matches_post_vacuum'] !== true
                || $row['freeblocks_preserved_after_allocation'] !== true;
        }

        if ($authoritativeSource === 'replacement-overflow-current-source') {
            return !in_array($row['final_pointer_map_type'], ['first-overflow-page', 'overflow-page'], true)
                || $row['final_materialized'] !== true;
        }

        return $authoritativeSource !== 'rejected-truncated-tail' && $row['final_pointer_map_type'] !== 'free-page';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function sourceErrorsForRows(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['authoritative_source'] === 'post-delete-leaf-current-source') {
                if ($row['read_admitted'] !== true || $row['stale_source_bytes_visible'] !== false) {
                    $errors[] = "leaf page {$pageNumber} did not use the post-delete current-source image";
                }
                continue;
            }
            if ($row['authoritative_source'] === 'replacement-overflow-current-source') {
                if ($row['read_admitted'] !== true || $row['stale_source_bytes_visible'] !== false) {
                    $errors[] = "replacement overflow page {$pageNumber} exposed stale source bytes";
                }
                if ($row['authoritative_next_page'] === null) {
                    $errors[] = "replacement overflow page {$pageNumber} has no authoritative next pointer";
                }
                continue;
            }
            if ($row['authoritative_source'] === 'rejected-truncated-tail') {
                if ($row['read_admitted'] !== false || $row['final_materialized'] !== false) {
                    $errors[] = "truncated tail page {$pageNumber} was admitted as current-source";
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $values
     */
    private static function signature(array $values): string
    {
        return hash('sha256', implode('|', $values));
    }
}
