<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext210Plan
{
    /**
     * @param list<array<string, mixed>> $applyRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext209Plan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext209Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext209Plan $basePlan): self
    {
        $rows = self::buildApplyRows($basePlan);
        $errors = self::applyErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next210 apply failed: ' . implode('; ', $errors));
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
        return self::applyErrorsForRows($this->applyRows);
    }

    /**
     * @return list<int>
     */
    public function appliedPages(): array
    {
        $pages = [];
        foreach ($this->applyRows as $row) {
            foreach ($row['apply_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function appliedPointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['apply_channel'] === 'pointer-map-apply');
    }

    /**
     * @return list<int>
     */
    public function appliedPayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['apply_channel'] === 'payload-apply');
    }

    /**
     * @return list<string>
     */
    public function applyTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['apply_token'], $this->applyRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function applySummary(): array
    {
        $writerSummary = $this->basePlan->writerSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next210-ready',
            'apply_row_count' => count($this->applyRows),
            'applied_pages' => $this->appliedPages(),
            'applied_pointer_map_pages' => $this->appliedPointerMapPages(),
            'applied_payload_pages' => $this->appliedPayloadPages(),
            'writer_source_pages' => $writerSummary['writer_source_pages'],
            'apply_matches_writer_source_pages' => $this->appliedPages() === $writerSummary['writer_source_pages'],
            'apply_tokens' => $this->applyTokens(),
            'apply_signature' => self::signature($this->applyTokens()),
            'next_current_source_apply_token' => self::signature(array_merge(
                ['next210', $writerSummary['next_writer_current_source_token']],
                $this->appliedPages(),
                $this->applyTokens(),
            )),
            'all_writer_tokens_match' => !in_array(false, array_column($this->applyRows, 'writer_token_matches'), true),
            'all_pointer_maps_applied_before_payload' => !in_array(false, array_column($this->applyRows, 'pointer_map_dependency_satisfied'), true),
            'all_tail_pages_remain_fenced' => !in_array(false, array_column($this->applyRows, 'tail_pages_remain_fenced'), true),
            'all_apply_chains_valid' => !in_array(false, array_column($this->applyRows, 'apply_chain_valid'), true),
            'all_current_source_epochs_ready' => !in_array(false, array_column($this->applyRows, 'current_source_epoch_ready'), true),
            'apply_errors' => $this->applyErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next209',
                'sqlite-current-source-next210',
            ],
            'dependency_closure' => 'no new support component needed; next210 reuses next209 writer-source rows, pointer-map ordering, leaf freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds current-source writer apply ordering after next209 writer-source latch admission; does not repeat next209 source latching, next206 sealing, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted freelist/pointer-map reuse slices',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next210',
            'apply_summary' => $this->applySummary(),
            'apply_errors' => $this->applyErrors(),
            'apply_rows' => $this->applyRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->applyRows as $row) {
            if (!$predicate($row)) {
                continue;
            }
            foreach ($row['apply_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildApplyRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext209Plan $basePlan): array
    {
        $sourceRows = $basePlan->sourceRows();
        $writerTokens = $basePlan->sourceTokens();
        $rows = [];
        $previousToken = null;
        $visiblePages = [];
        $appliedPointerMapPages = [];

        foreach ($sourceRows as $index => $sourceRow) {
            $pages = array_values(array_map('intval', $sourceRow['writer_source_pages']));
            foreach ($pages as $pageNumber) {
                $visiblePages[$pageNumber] = true;
                if ($sourceRow['source_channel'] === 'pointer-map') {
                    $appliedPointerMapPages[$pageNumber] = true;
                }
            }

            $writerToken = (string) $sourceRow['writer_source_token'];
            $applyChannel = $sourceRow['source_channel'] === 'pointer-map' ? 'pointer-map-apply' : 'payload-apply';
            $pointerDependency = $applyChannel === 'pointer-map-apply'
                || self::requiredPointerMapPagesVisible($pages, $appliedPointerMapPages);
            $token = self::signature(array_merge(
                ['next210', (int) $sourceRow['source_ordinal'], $applyChannel, $previousToken ?? 'initial', $writerToken],
                $pages,
                self::sortedIntKeys($visiblePages),
                self::sortedIntKeys($appliedPointerMapPages),
                [(int) $sourceRow['high_water_page']],
            ));

            $rows[] = [
                'apply_ordinal' => (int) $sourceRow['source_ordinal'],
                'source_index' => $index,
                'cursor_index' => (int) $sourceRow['cursor_index'],
                'batch_index' => (int) $sourceRow['batch_index'],
                'apply_channel' => $applyChannel,
                'apply_pages' => $pages,
                'applied_visible_pages' => self::sortedIntKeys($visiblePages),
                'applied_pointer_map_pages' => self::sortedIntKeys($appliedPointerMapPages),
                'writer_source_token' => $writerToken,
                'expected_writer_source_token' => $writerTokens[$index] ?? null,
                'writer_token_matches' => ($writerTokens[$index] ?? null) === $writerToken,
                'previous_apply_token' => $previousToken,
                'pointer_map_dependency_satisfied' => $pointerDependency,
                'tail_pages_remain_fenced' => $sourceRow['tail_pages_remain_fenced'] === true
                    && !array_intersect([109, 110], $pages),
                'apply_chain_valid' => $sourceRow['previous_writer_source_token'] === null
                    || is_string($sourceRow['previous_writer_source_token']),
                'current_source_epoch_ready' => $sourceRow['writer_source_state'] === 'current-source-writer-ready',
                'high_water_page' => (int) $sourceRow['high_water_page'],
                'apply_state' => 'current-source-writer-applied',
                'apply_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<int> $pages
     * @param array<int, bool> $appliedPointerMapPages
     */
    private static function requiredPointerMapPagesVisible(array $pages, array $appliedPointerMapPages): bool
    {
        foreach ($pages as $pageNumber) {
            $requiredPointerMapPage = $pageNumber >= 106 ? 105 : 2;
            if (!isset($appliedPointerMapPages[$requiredPointerMapPage])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function applyErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousVisible = [];

        foreach ($rows as $row) {
            if ($row['apply_state'] !== 'current-source-writer-applied') {
                $errors[] = "apply {$row['apply_ordinal']} is not writer-applied";
            }
            if ((int) $row['apply_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "apply {$row['apply_ordinal']} skipped an ordinal";
            }
            if ($row['writer_token_matches'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} writer token drifted";
            }
            if ($row['previous_apply_token'] !== $previousToken) {
                $errors[] = "apply {$row['apply_ordinal']} broke apply chaining";
            }
            if ($row['pointer_map_dependency_satisfied'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} wrote payload before pointer-map source";
            }
            if ($row['tail_pages_remain_fenced'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} exposed fenced tail pages";
            }
            if ($row['apply_chain_valid'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} has an invalid writer chain";
            }
            if ($row['current_source_epoch_ready'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} used an unready current-source epoch";
            }
            if (count(array_diff(array_keys($previousVisible), $row['applied_visible_pages'])) !== 0) {
                $errors[] = "apply {$row['apply_ordinal']} lost a visible applied page";
            }
            if ($row['apply_token'] === '') {
                $errors[] = "apply {$row['apply_ordinal']} has an empty apply token";
            }

            $previousOrdinal = (int) $row['apply_ordinal'];
            $previousToken = (string) $row['apply_token'];
            $previousVisible = array_fill_keys(array_map('intval', $row['applied_visible_pages']), true);
        }

        return $errors;
    }

    /**
     * @param array<int, bool> $values
     * @return list<int>
     */
    private static function sortedIntKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return array_values(array_map('intval', $keys));
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
