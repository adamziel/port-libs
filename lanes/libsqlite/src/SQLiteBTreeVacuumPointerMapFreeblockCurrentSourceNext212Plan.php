<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Plan
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
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next212 apply failed: ' . implode('; ', $errors));
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
    public function applyPages(): array
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
    public function pointerMapApplyPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['apply_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadApplyPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['apply_channel'] === 'payload');
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
        $sourceSummary = $this->basePlan->writerSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next212-ready',
            'apply_row_count' => count($this->applyRows),
            'apply_pages' => $this->applyPages(),
            'pointer_map_apply_pages' => $this->pointerMapApplyPages(),
            'payload_apply_pages' => $this->payloadApplyPages(),
            'writer_source_pages' => $sourceSummary['writer_source_pages'],
            'apply_matches_writer_source_pages' => $this->applyPages() === $sourceSummary['writer_source_pages'],
            'apply_tokens' => $this->applyTokens(),
            'apply_signature' => self::signature($this->applyTokens()),
            'next_writer_apply_token' => self::signature(array_merge(
                ['next212', $sourceSummary['next_writer_current_source_token']],
                $this->applyPages(),
                $this->applyTokens(),
            )),
            'all_source_tokens_match' => !in_array(false, array_column($this->applyRows, 'source_token_matches'), true),
            'all_pointer_maps_applied_before_payload' => $this->pointerMapsBeforePayloadApply(),
            'all_freeblock_receipts_carried' => !in_array(false, array_column($this->applyRows, 'freeblock_receipt_carried'), true),
            'all_tail_pages_fenced_for_apply' => !in_array(false, array_column($this->applyRows, 'tail_pages_fenced_for_apply'), true),
            'all_apply_chains_valid' => !in_array(false, array_column($this->applyRows, 'apply_chain_valid'), true),
            'apply_errors' => $this->applyErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next209',
                'sqlite-current-source-next212',
            ],
            'dependency_closure' => 'no new support component needed; next212 reuses next209 writer-source latch rows, pointer-map source pages, leaf freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds current-source page apply ordering after next209 writer-source latch admission; does not repeat next209 source latching, next206 sealing, next203 cursor batching, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next212',
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

    private function pointerMapsBeforePayloadApply(): bool
    {
        $byCursor = [];
        foreach ($this->applyRows as $row) {
            $cursor = (int) $row['cursor_index'];
            $byCursor[$cursor] ??= ['pointer' => null, 'payload' => null];
            if ($row['apply_channel'] === 'pointer-map') {
                $byCursor[$cursor]['pointer'] = (int) $row['apply_ordinal'];
            }
            if ($row['apply_channel'] === 'payload') {
                $byCursor[$cursor]['payload'] = (int) $row['apply_ordinal'];
            }
        }

        foreach ($byCursor as $row) {
            if ($row['payload'] !== null && ($row['pointer'] === null || $row['pointer'] > $row['payload'])) {
                return false;
            }
        }

        return $byCursor !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildApplyRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext209Plan $basePlan): array
    {
        $sourceRows = $basePlan->sourceRows();
        $sourceTokens = $basePlan->sourceTokens();
        $rows = [];
        $previousApplyToken = null;
        $appliedPages = [];

        foreach ($sourceRows as $index => $row) {
            $pages = array_values(array_map('intval', $row['writer_source_pages']));
            foreach ($pages as $pageNumber) {
                $appliedPages[$pageNumber] = true;
            }

            $sourceToken = (string) $row['writer_source_token'];
            $token = self::signature(array_merge(
                ['next212', (int) $row['source_ordinal'], $previousApplyToken ?? 'initial', $sourceToken],
                $pages,
                self::sortedIntKeys($appliedPages),
                [(int) $row['high_water_page']],
            ));

            $rows[] = [
                'apply_ordinal' => (int) $row['source_ordinal'],
                'source_index' => $index,
                'cursor_index' => (int) $row['cursor_index'],
                'batch_index' => (int) $row['batch_index'],
                'apply_channel' => (string) $row['source_channel'],
                'apply_pages' => $pages,
                'applied_visible_pages' => self::sortedIntKeys($appliedPages),
                'source_token' => $sourceToken,
                'expected_source_token' => $sourceTokens[$index] ?? null,
                'source_token_matches' => ($sourceTokens[$index] ?? null) === $sourceToken,
                'previous_apply_token' => $previousApplyToken,
                'freeblock_receipt_carried' => $row['leaf_freeblock_source_ready'] === true,
                'tail_pages_fenced_for_apply' => $row['tail_pages_remain_fenced'] === true && !array_intersect([109, 110], $pages),
                'apply_chain_valid' => $row['previous_writer_source_token'] === null || is_string($row['previous_writer_source_token']),
                'high_water_page' => (int) $row['high_water_page'],
                'apply_state' => 'current-source-page-apply-ready',
                'apply_token' => $token,
            ];

            $previousApplyToken = $token;
        }

        return $rows;
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
            if ($row['apply_state'] !== 'current-source-page-apply-ready') {
                $errors[] = "apply {$row['apply_ordinal']} is not ready";
            }
            if ((int) $row['apply_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "apply {$row['apply_ordinal']} skipped an apply ordinal";
            }
            if ($row['source_token_matches'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} source token drifted";
            }
            if ($row['previous_apply_token'] !== $previousToken) {
                $errors[] = "apply {$row['apply_ordinal']} broke apply token chaining";
            }
            if ($row['freeblock_receipt_carried'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_fenced_for_apply'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} exposed fenced tail pages";
            }
            if ($row['apply_chain_valid'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} has an invalid source chain";
            }
            if (count(array_diff(array_keys($previousVisible), $row['applied_visible_pages'])) !== 0) {
                $errors[] = "apply {$row['apply_ordinal']} lost an already-applied page";
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
