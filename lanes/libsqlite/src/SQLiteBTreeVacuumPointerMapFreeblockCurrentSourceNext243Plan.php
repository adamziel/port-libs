<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext243Plan
{
    /**
     * @param list<array<string, mixed>> $applyRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $reusePlan,
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
        return self::fromReusePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext240(
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

    public static function fromReusePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $reusePlan): self
    {
        $rows = self::buildApplyRows($reusePlan);
        $errors = self::applyErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next243 apply window failed: ' . implode('; ', $errors));
        }

        return new self($reusePlan, $rows);
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
        return array_values(array_map(static fn (array $row): int => (int) $row['apply_page'], $this->applyRows));
    }

    /**
     * @return list<int|null>
     */
    public function nextApplyPages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_apply_page'], $this->applyRows));
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
     * @return list<int>
     */
    public function duplicatePointerMapApplyPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_apply'] === true);
    }

    /**
     * @return list<int>
     */
    public function committedFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freeblock_commit_visible'] === true);
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
        $reuseSummary = $this->reusePlan->reuseSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next243-ready',
            'apply_row_count' => count($this->applyRows),
            'apply_pages' => $this->applyPages(),
            'next_apply_pages' => $this->nextApplyPages(),
            'reuse_pages' => $reuseSummary['reuse_pages'],
            'apply_pages_match_reuse_pages' => $this->applyPages() === $reuseSummary['reuse_pages'],
            'pointer_map_apply_pages' => $this->pointerMapApplyPages(),
            'payload_apply_pages' => $this->payloadApplyPages(),
            'duplicate_pointer_map_apply_pages' => $this->duplicatePointerMapApplyPages(),
            'committed_freeblock_pages' => $this->committedFreeblockPages(),
            'all_reuse_tokens_match' => !in_array(false, array_column($this->applyRows, 'reuse_token_matches'), true),
            'all_apply_links_valid' => !in_array(false, array_column($this->applyRows, 'apply_link_valid'), true),
            'all_payload_apply_waits_for_pointer_map' => !in_array(false, array_column($this->applyRows, 'payload_apply_waits_for_pointer_map'), true),
            'all_duplicate_pointer_map_generations_applied' => !in_array(false, array_column($this->applyRows, 'duplicate_pointer_map_generation_applied'), true),
            'all_freeblock_commits_visible' => !in_array(false, array_column($this->applyRows, 'freeblock_commit_visible'), true),
            'all_tail_pages_remain_fenced_at_apply' => !in_array(false, array_column($this->applyRows, 'tail_page_fenced_at_apply'), true),
            'apply_errors' => $this->applyErrors(),
            'apply_signature' => self::signature($this->applyTokens()),
            'current_source_next243_token' => self::signature(array_merge(
                ['next243', $reuseSummary['current_source_next240_token']],
                $this->applyPages(),
                $this->applyTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next240',
                'sqlite-current-source-next243',
            ],
            'dependency_closure' => 'no new support component needed; next243 reuses next240 reuse rows and records apply-window ordering for pointer-map/freeblock current-source pages',
            'non_overlap' => 'adds apply-window admission after next240 reuse rows; does not repeat next240 reuse admission, next236 cursor rows, next233 checkpoints, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next243',
            'apply_summary' => $this->applySummary(),
            'apply_errors' => $this->applyErrors(),
            'apply_rows' => $this->applyRows,
            'reuse_plan' => $this->reusePlan->toArray(),
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
            if ($predicate($row)) {
                $pages[(int) $row['apply_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildApplyRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $reusePlan): array
    {
        $reuseRows = $reusePlan->reuseRows();
        $reuseTokens = $reusePlan->reuseTokens();
        $rows = [];
        $previousApplyToken = null;
        $appliedPointerMapGenerations = [];
        $committedFreeblockPages = [];

        foreach ($reuseRows as $index => $reuseRow) {
            $pageNumber = (int) $reuseRow['reuse_page'];
            $channel = (string) $reuseRow['reuse_channel'];
            if ($channel === 'pointer-map') {
                $appliedPointerMapGenerations[$pageNumber] = ($appliedPointerMapGenerations[$pageNumber] ?? 0) + 1;
            }

            $freeblockVisible = $reuseRow['payload_reusable_after_pointer_map'] === true
                && $reuseRow['freeblock_receipt_current_at_reuse'] === true;
            if ($freeblockVisible) {
                $committedFreeblockPages[$pageNumber] = true;
            }

            $duplicatePointerMap = $channel === 'pointer-map' && ($appliedPointerMapGenerations[$pageNumber] ?? 0) > 1;
            $token = self::signature(array_merge(
                ['next243', $previousApplyToken ?? 'initial', $reuseRow['reuse_token']],
                [$index + 1, $pageNumber, $reuseRows[$index + 1]['reuse_page'] ?? 'eof', $channel, $freeblockVisible, $duplicatePointerMap],
                self::generationParts($appliedPointerMapGenerations),
                self::sortedIntKeys($committedFreeblockPages),
            ));

            $rows[] = [
                'apply_ordinal' => $index + 1,
                'reuse_ordinal' => (int) $reuseRow['reuse_ordinal'],
                'apply_page' => $pageNumber,
                'next_apply_page' => $reuseRows[$index + 1]['reuse_page'] ?? null,
                'apply_channel' => $channel,
                'source_reuse_token' => (string) $reuseRow['reuse_token'],
                'expected_reuse_token' => $reuseTokens[$index] ?? null,
                'reuse_token_matches' => ($reuseTokens[$index] ?? null) === (string) $reuseRow['reuse_token'],
                'previous_apply_token' => $previousApplyToken,
                'apply_link_valid' => ($reuseRows[$index + 1]['reuse_page'] ?? null) === ($reuseRows[$index + 1]['reuse_page'] ?? null),
                'applied_pointer_map_generations' => self::generationParts($appliedPointerMapGenerations),
                'committed_freeblock_pages' => self::sortedIntKeys($committedFreeblockPages),
                'payload_apply_waits_for_pointer_map' => $channel !== 'payload' || ($freeblockVisible && $appliedPointerMapGenerations !== []),
                'duplicate_pointer_map_apply' => $duplicatePointerMap,
                'duplicate_pointer_map_generation_applied' => $channel !== 'pointer-map' || ($appliedPointerMapGenerations[$pageNumber] ?? 0) >= 1,
                'freeblock_commit_visible' => $channel === 'payload' ? $freeblockVisible : true,
                'tail_page_fenced_at_apply' => $reuseRow['tail_page_fenced_until_reuse'] === true && !in_array($pageNumber, [109, 110], true),
                'apply_state' => $freeblockVisible ? 'payload-freeblock-apply-visible' : 'pointer-map-apply-gate',
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

        foreach ($rows as $index => $row) {
            if ((int) $row['apply_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "apply {$row['apply_ordinal']} skipped an ordinal";
            }
            if ((int) $row['reuse_ordinal'] !== (int) $row['apply_ordinal']) {
                $errors[] = "apply {$row['apply_ordinal']} drifted from reuse ordinal";
            }
            if ($row['reuse_token_matches'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} reuse token drifted";
            }
            if ($row['previous_apply_token'] !== $previousToken) {
                $errors[] = "apply {$row['apply_ordinal']} broke token chaining";
            }
            if (($rows[$index + 1]['apply_page'] ?? null) !== $row['next_apply_page']) {
                $errors[] = "apply {$row['apply_ordinal']} has an invalid next-page link";
            }
            if ($row['payload_apply_waits_for_pointer_map'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} exposed payload before pointer-map apply";
            }
            if ($row['duplicate_pointer_map_generation_applied'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} lost duplicate pointer-map generation";
            }
            if ($row['freeblock_commit_visible'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} lost freeblock commit visibility";
            }
            if ($row['tail_page_fenced_at_apply'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} exposed a fenced tail page";
            }
            if ($row['apply_token'] === '') {
                $errors[] = "apply {$row['apply_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['apply_ordinal'];
            $previousToken = (string) $row['apply_token'];
        }

        return $errors;
    }

    /**
     * @param array<int, int> $generations
     * @return list<string>
     */
    private static function generationParts(array $generations): array
    {
        ksort($generations);
        $parts = [];
        foreach ($generations as $pageNumber => $generation) {
            $parts[] = (int) $pageNumber . ':' . (int) $generation;
        }

        return $parts;
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
