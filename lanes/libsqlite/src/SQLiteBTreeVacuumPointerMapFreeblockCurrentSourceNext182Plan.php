<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext182Plan
{
    /**
     * @param list<array<string, mixed>> $applyRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan $basePlan,
        private readonly array $applyRows,
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
        int $batchSize = 2,
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
            $batchSize,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan $basePlan): self
    {
        $rows = self::buildApplyRows($basePlan);
        $errors = self::applyErrorsForRows($rows, $basePlan);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next182 apply schedule failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function applyRows(): array
    {
        return $this->applyRows;
    }

    /**
     * @return list<string>
     */
    public function applyErrors(): array
    {
        return self::applyErrorsForRows($this->applyRows, $this->basePlan);
    }

    /**
     * @return list<int>
     */
    public function orderedReplayPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->applyRows, static fn (array $row): bool => $row['operation'] === 'replay-page'),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncateAfterReplayPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->applyRows, static fn (array $row): bool => $row['operation'] === 'truncate-fenced-tail'),
        ));
    }

    /**
     * @return list<int>
     */
    public function replayPointerMapPages(): array
    {
        $pages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->applyRows, static fn (array $row): bool => $row['operation'] === 'replay-page' && $row['page_role'] === 'pointer-map'),
        ));
        sort($pages);

        return $pages;
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->applyRows, static fn (array $row): bool => $row['operation'] === 'replay-page' && $row['page_role'] === 'replacement-overflow'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function applySummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next182-ready',
            'ordered_replay_pages' => $this->orderedReplayPages(),
            'replay_pointer_map_pages' => $this->replayPointerMapPages(),
            'replacement_overflow_pages' => $this->replacementOverflowPages(),
            'truncate_after_replay_pages' => $this->truncateAfterReplayPages(),
            'fenced_pages' => $this->basePlan->fencedPages(),
            'dependency_pages' => $this->basePlan->pointerMapDependencyPages(),
            'apply_signature' => self::signature(array_map(
                static fn (array $row): string => $row['operation'] . ':' . $row['page_number'] . ':' . $row['page_role'],
                $this->applyRows,
            )),
            'truncate_after_replay' => $this->truncateAfterReplayPages() === $this->basePlan->fencedPages(),
            'pointer_map_replayed_before_overflow' => $this->pointerMapReplayedBeforeOverflow(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next177',
                'sqlite-current-source-next182',
            ],
            'dependency_closure' => 'no new support component needed; next182 reuses native page-image hashes, pointer-map dependency batches, and current-source tail fences from the B-tree vacuum/freeblock path',
            'non_overlap' => 'adds the ordered apply/truncate schedule after next177 replay batches; does not repeat next177 batch construction, next176 source boundary checks, next173 transition auditing, next166 write admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next182',
            'apply_summary' => $this->applySummary(),
            'apply_errors' => $this->applyErrors(),
            'apply_rows' => $this->applyRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    private function pointerMapReplayedBeforeOverflow(): bool
    {
        $firstOverflowOrder = null;
        $lastPointerMapOrder = null;
        foreach ($this->applyRows as $row) {
            if ($row['operation'] !== 'replay-page') {
                continue;
            }
            if ($row['page_role'] === 'pointer-map') {
                $lastPointerMapOrder = (int) $row['apply_order'];
            }
            if ($row['page_role'] === 'replacement-overflow' && $firstOverflowOrder === null) {
                $firstOverflowOrder = (int) $row['apply_order'];
            }
        }

        return $lastPointerMapOrder !== null && $firstOverflowOrder !== null && $lastPointerMapOrder < $firstOverflowOrder;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildApplyRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan $basePlan): array
    {
        $rows = [];
        $order = 0;
        $pointerMapDependencies = $basePlan->pointerMapDependencyPages();
        $replayPages = $basePlan->replayPages();

        foreach ($basePlan->batchRows() as $batch) {
            foreach ($batch['page_numbers'] as $position => $pageNumber) {
                $pageNumber = (int) $pageNumber;
                $role = self::pageRole($pageNumber, $batch, (int) $position, $pointerMapDependencies);
                $rows[] = [
                    'operation' => 'replay-page',
                    'apply_order' => $order++,
                    'batch_index' => (int) $batch['batch_index'],
                    'position_in_batch' => (int) $position,
                    'page_number' => $pageNumber,
                    'page_role' => $role,
                    'next_page_hash' => $batch['next_page_hashes'][$position],
                    'resume_token' => $batch['resume_tokens'][$position],
                    'pointer_map_dependency_pages' => $batch['pointer_map_dependency_pages'],
                    'pointer_map_type' => $batch['pointer_map_types'][$position],
                    'pointer_map_parent' => $batch['pointer_map_parents'][$position],
                    'dependency_replayed_in_schedule' => self::dependenciesAvailable($pageNumber, $role, $batch['pointer_map_dependency_pages'], $replayPages),
                    'tail_truncation_allowed_after_this_row' => false,
                ];
            }
        }

        if ($rows !== []) {
            $rows[array_key_last($rows)]['tail_truncation_allowed_after_this_row'] = true;
        }

        foreach ($basePlan->fencedPages() as $pageNumber) {
            $rows[] = [
                'operation' => 'truncate-fenced-tail',
                'apply_order' => $order++,
                'batch_index' => null,
                'position_in_batch' => null,
                'page_number' => (int) $pageNumber,
                'page_role' => 'truncated-tail',
                'next_page_hash' => null,
                'resume_token' => null,
                'pointer_map_dependency_pages' => [],
                'pointer_map_type' => null,
                'pointer_map_parent' => null,
                'dependency_replayed_in_schedule' => true,
                'tail_truncation_allowed_after_this_row' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $batch
     * @param list<int> $pointerMapDependencies
     */
    private static function pageRole(int $pageNumber, array $batch, int $position, array $pointerMapDependencies): string
    {
        if ($pageNumber === 1) {
            return 'database-header';
        }
        if (in_array($pageNumber, $pointerMapDependencies, true)) {
            return 'pointer-map';
        }
        if (in_array($batch['pointer_map_types'][$position], ['first-overflow-page', 'overflow-page'], true)) {
            return 'replacement-overflow';
        }

        return 'table-leaf-freeblock';
    }

    /**
     * @param list<int> $dependencyPages
     * @param list<int> $replayPages
     */
    private static function dependenciesAvailable(int $pageNumber, string $role, array $dependencyPages, array $replayPages): bool
    {
        if ($role !== 'replacement-overflow') {
            return true;
        }

        foreach ($dependencyPages as $dependencyPage) {
            if ((int) $dependencyPage === $pageNumber) {
                continue;
            }
            if (!in_array((int) $dependencyPage, $replayPages, true) && (int) $dependencyPage !== 2) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function applyErrorsForRows(array $rows, SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan $basePlan): array
    {
        $errors = [];
        $seenTruncate = false;
        $replayedPages = [];
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['operation'] === 'truncate-fenced-tail') {
                $seenTruncate = true;
                if (!in_array($pageNumber, $basePlan->fencedPages(), true)) {
                    $errors[] = "page {$pageNumber} is not a fenced tail page";
                }
                continue;
            }

            if ($seenTruncate) {
                $errors[] = "page {$pageNumber} replayed after tail truncation";
            }
            if (!in_array($pageNumber, $basePlan->replayPages(), true)) {
                $errors[] = "page {$pageNumber} is not in the next-source replay set";
            }
            if ($row['next_page_hash'] === null || strlen((string) $row['next_page_hash']) !== 64) {
                $errors[] = "page {$pageNumber} is missing a next-source hash";
            }
            if ($row['resume_token'] === null || strlen((string) $row['resume_token']) !== 64) {
                $errors[] = "page {$pageNumber} is missing a replay resume token";
            }
            if ($row['dependency_replayed_in_schedule'] !== true) {
                $errors[] = "page {$pageNumber} has an unsatisfied pointer-map dependency";
            }
            $replayedPages[] = $pageNumber;
        }

        if ($replayedPages !== $basePlan->replayPages()) {
            $errors[] = 'replay page order does not match next177 batches';
        }

        return $errors;
    }

    /**
     * @param list<mixed> $values
     */
    private static function signature(array $values): string
    {
        return hash('sha256', implode('|', array_map(
            static fn (mixed $value): string => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            $values,
        )));
    }
}
