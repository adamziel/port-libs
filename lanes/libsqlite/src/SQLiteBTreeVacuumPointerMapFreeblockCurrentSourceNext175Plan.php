<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext175Plan
{
    /**
     * @param list<array<string, mixed>> $admissionRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext173Plan $basePlan,
        private readonly array $admissionRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext173Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext173Plan $basePlan): self
    {
        $rows = self::buildAdmissionRows($basePlan);
        $errors = self::admissionErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next175 admission failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function admissionRows(): array
    {
        return $this->admissionRows;
    }

    /**
     * @return list<string>
     */
    public function admissionErrors(): array
    {
        return self::admissionErrorsForRows($this->admissionRows);
    }

    /**
     * @return list<int>
     */
    public function admittedCurrentSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['admission'] === 'admit-final-page');
    }

    /**
     * @return list<int>
     */
    public function rejectedCurrentSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['admission'] === 'reject-truncated-current-source-page');
    }

    /**
     * @return list<int>
     */
    public function pointerMapRewritePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['pointer_map_rewrite_required'] === true);
    }

    /**
     * @return list<int>
     */
    public function staleNextPointerFencePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['stale_next_pointer_fenced'] === true);
    }

    /**
     * @return array<string, mixed>
     */
    public function admissionSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next175-ready',
            'admitted_current_source_pages' => $this->admittedCurrentSourcePages(),
            'rejected_current_source_pages' => $this->rejectedCurrentSourcePages(),
            'pointer_map_rewrite_pages' => $this->pointerMapRewritePages(),
            'stale_next_pointer_fence_pages' => $this->staleNextPointerFencePages(),
            'transition_signature' => $this->basePlan->transitionSummary()['transition_signature'],
            'admission_signature' => self::signature(array_map(
                static fn (array $row): string => $row['page_number'] . ':' . $row['admission'] . ':' . ($row['final_next_page'] ?? 'null'),
                $this->admissionRows,
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next173',
                'sqlite-current-source-next175',
            ],
            'dependency_closure' => 'no new support component needed; next175 reuses native current-source transition rows, b-tree freeblock page images, overflow next-pointer decoding, and auto-vacuum pointer-map metadata',
            'non_overlap' => 'adds final current-source admission fencing for stale overflow next-pointers and truncated-tail pages; does not repeat next173 transition rows, next167 final leaf audit, next166 write admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next175',
            'admission_summary' => $this->admissionSummary(),
            'admission_errors' => $this->admissionErrors(),
            'admission_rows' => $this->admissionRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->admissionRows, $predicate),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildAdmissionRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext173Plan $basePlan): array
    {
        $truncatedTailPages = array_fill_keys($basePlan->truncatedTailPages(), true);
        $rows = [];
        foreach ($basePlan->transitionRows() as $row) {
            $finalNext = $row['final_next_page'];
            $sourceNext = $row['source_next_page'];
            $pointsAtRejectedTail = is_int($finalNext) && isset($truncatedTailPages[$finalNext]);
            $pointerMapRewriteRequired = $row['source_pointer_map_type'] !== $row['final_pointer_map_type']
                || $row['source_pointer_map_parent'] !== $row['final_pointer_map_parent'];
            $admission = $row['final_materialized'] === true ? 'admit-final-page' : 'reject-truncated-current-source-page';

            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'status' => $row['status'],
                'admission' => $admission,
                'source_materialized' => $row['source_materialized'],
                'final_materialized' => $row['final_materialized'],
                'source_next_page' => $sourceNext,
                'final_next_page' => $finalNext,
                'source_pointer_map_type' => $row['source_pointer_map_type'],
                'final_pointer_map_type' => $row['final_pointer_map_type'],
                'source_pointer_map_parent' => $row['source_pointer_map_parent'],
                'final_pointer_map_parent' => $row['final_pointer_map_parent'],
                'pointer_map_rewrite_required' => $pointerMapRewriteRequired,
                'next_pointer_rewritten' => $sourceNext !== $finalNext,
                'stale_next_pointer_fenced' => $sourceNext !== $finalNext || $admission === 'reject-truncated-current-source-page',
                'final_next_points_at_rejected_tail' => $pointsAtRejectedTail,
                'stable_leaf_hash_preserved' => $row['status'] === 'stable-leaf-freeblock' ? $row['final_hash_matches_post_vacuum'] : null,
                'stable_leaf_freeblocks_preserved' => $row['status'] === 'stable-leaf-freeblock' ? $row['freeblocks_preserved_after_allocation'] : null,
                'current_source_admission_key' => self::signature([
                    (int) $row['page_number'],
                    (string) $row['status'],
                    $sourceNext ?? 'null',
                    $finalNext ?? 'null',
                    $row['final_pointer_map_type'] ?? 'null',
                    $row['final_pointer_map_parent'] ?? 'null',
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function admissionErrorsForRows(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if ($row['status'] === 'stable-leaf-freeblock') {
                if ($row['stable_leaf_hash_preserved'] !== true) {
                    $errors[] = "stable leaf page {$row['page_number']} hash changed";
                }
                if ($row['stable_leaf_freeblocks_preserved'] !== true) {
                    $errors[] = "stable leaf page {$row['page_number']} freeblocks changed";
                }
            }

            if ($row['status'] === 'replacement-overflow') {
                if ($row['admission'] !== 'admit-final-page') {
                    $errors[] = "replacement overflow page {$row['page_number']} was not admitted";
                }
                if (!in_array($row['final_pointer_map_type'], ['first-overflow-page', 'overflow-page'], true)) {
                    $errors[] = "replacement overflow page {$row['page_number']} pointer-map type is not overflow";
                }
                if ($row['final_next_points_at_rejected_tail'] === true) {
                    $errors[] = "replacement overflow page {$row['page_number']} points at rejected truncated tail";
                }
            }

            if ($row['status'] === 'truncated-tail-page') {
                if ($row['admission'] !== 'reject-truncated-current-source-page') {
                    $errors[] = "truncated tail page {$row['page_number']} was admitted";
                }
                if ($row['final_materialized'] !== false) {
                    $errors[] = "truncated tail page {$row['page_number']} survived final materialization";
                }
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
