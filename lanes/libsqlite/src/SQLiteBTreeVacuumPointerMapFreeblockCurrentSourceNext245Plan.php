<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Plan
{
    /**
     * @param list<array<string, mixed>> $cursorRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan $currentSourcePlan,
        private readonly array $cursorRows,
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
        return self::fromCurrentSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan::tableLeafFromDeleteResult(
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

    public static function fromCurrentSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan $currentSourcePlan): self
    {
        $rows = self::buildCursorRows($currentSourcePlan);
        $errors = self::cursorErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next245 handoff failed: ' . implode('; ', $errors));
        }

        return new self($currentSourcePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cursorRows(): array
    {
        return $this->cursorRows;
    }

    /**
     * @return list<string>
     */
    public function cursorErrors(): array
    {
        return self::cursorErrorsForRows($this->cursorRows);
    }

    /**
     * @return list<int>
     */
    public function admittedPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['admitted_page'], $this->cursorRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapBarrierPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['admission_kind'] === 'pointer-map-barrier');
    }

    /**
     * @return list<int>
     */
    public function reusableFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['admission_kind'] === 'reusable-freeblock');
    }

    /**
     * @return list<int>
     */
    public function cursorEpochs(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['pointer_map_epoch'], $this->cursorRows));
    }

    /**
     * @return list<string>
     */
    public function cursorTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['cursor_token'], $this->cursorRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function cursorSummary(): array
    {
        $currentSourceSummary = $this->currentSourcePlan->currentSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next245-ready',
            'cursor_row_count' => count($this->cursorRows),
            'admitted_pages' => $this->admittedPages(),
            'current_source_pages' => $currentSourceSummary['current_source_pages'],
            'admitted_pages_match_current_source' => $this->admittedPages() === $currentSourceSummary['current_source_pages'],
            'pointer_map_barrier_pages' => $this->pointerMapBarrierPages(),
            'reusable_freeblock_pages' => $this->reusableFreeblockPages(),
            'cursor_epochs' => $this->cursorEpochs(),
            'all_current_source_tokens_match' => !in_array(false, array_column($this->cursorRows, 'current_source_token_matches'), true),
            'all_pointer_map_epochs_open_before_reuse' => !in_array(false, array_column($this->cursorRows, 'pointer_map_epoch_open_before_reuse'), true),
            'all_reusable_pages_have_leaf_receipts' => !in_array(false, array_column($this->cursorRows, 'leaf_receipt_visible_before_admission'), true),
            'all_trunk_candidates_preserved' => !in_array(false, array_column($this->cursorRows, 'trunk_candidate_preserved'), true),
            'all_cursor_links_valid' => !in_array(false, array_column($this->cursorRows, 'cursor_link_valid'), true),
            'cursor_errors' => $this->cursorErrors(),
            'cursor_signature' => self::signature($this->cursorTokens()),
            'current_source_next245_token' => self::signature(array_merge(
                ['next245', $currentSourceSummary['current_source_next242_token']],
                $this->admittedPages(),
                $this->cursorEpochs(),
                $this->cursorTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next242',
                'sqlite-current-source-next245',
            ],
            'dependency_closure' => 'no new support component needed; next245 reuses next242 current-source rows and verifies cursor admission ordering only',
            'non_overlap' => 'adds source-cursor admission ordering over next242 current-source rows; does not repeat next242 visibility, next238 freelist admission, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or VFS/WAL behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next245',
            'cursor_summary' => $this->cursorSummary(),
            'cursor_errors' => $this->cursorErrors(),
            'cursor_rows' => $this->cursorRows,
            'current_source_plan' => $this->currentSourcePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->cursorRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['admitted_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCursorRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan $currentSourcePlan): array
    {
        $sourceRows = $currentSourcePlan->currentSourceRows();
        $sourceTokens = $currentSourcePlan->currentSourceTokens();
        $rows = [];
        $previousToken = null;
        $pointerMapEpoch = 0;
        $stableTrunk = null;

        foreach ($sourceRows as $index => $sourceRow) {
            $pageNumber = (int) $sourceRow['current_source_page'];
            $kind = (string) $sourceRow['source_channel'];
            $isPointerMap = $kind === 'pointer-map-barrier';
            $isReusable = $kind === 'reusable-freeblock';
            if ($isPointerMap) {
                ++$pointerMapEpoch;
            }
            if ($isReusable && $stableTrunk === null) {
                $stableTrunk = (int) $sourceRow['stable_trunk_candidate_page'];
            }

            $ordinal = $index + 1;
            $sourceToken = (string) $sourceRow['current_source_token'];
            $token = self::signature([
                'next245',
                $ordinal,
                $previousToken ?? 'initial',
                $sourceToken,
                $pageNumber,
                $kind,
                $pointerMapEpoch,
                $stableTrunk ?? 0,
            ]);

            $rows[] = [
                'cursor_ordinal' => $ordinal,
                'current_source_ordinal' => (int) $sourceRow['current_source_ordinal'],
                'admitted_page' => $pageNumber,
                'admission_kind' => $kind,
                'pointer_map_epoch' => $pointerMapEpoch,
                'source_current_token' => $sourceToken,
                'expected_current_token' => $sourceTokens[$index] ?? null,
                'current_source_token_matches' => ($sourceTokens[$index] ?? null) === $sourceToken,
                'previous_cursor_token' => $previousToken,
                'pointer_map_epoch_open_before_reuse' => !$isReusable || $pointerMapEpoch > 0,
                'leaf_receipt_visible_before_admission' => !$isReusable || $sourceRow['freeblock_source_has_leaf_receipt'] === true,
                'trunk_candidate_page' => $stableTrunk,
                'trunk_candidate_preserved' => !$isReusable || $stableTrunk === $sourceRow['stable_trunk_candidate_page'],
                'tail_page_still_fenced' => $sourceRow['tail_page_excluded_from_current_source'] === true,
                'cursor_link_valid' => $sourceRow['previous_current_source_token'] === ($sourceRows[$index - 1]['current_source_token'] ?? null),
                'cursor_state' => 'current-source-next245-source-cursor-admitted',
                'cursor_token' => $token,
            ];
            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function cursorErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['cursor_state'] !== 'current-source-next245-source-cursor-admitted') {
                $errors[] = "cursor {$row['cursor_ordinal']} is not admitted";
            }
            if ((int) $row['cursor_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "cursor {$row['cursor_ordinal']} skipped an ordinal";
            }
            if ((int) $row['current_source_ordinal'] !== (int) $row['cursor_ordinal']) {
                $errors[] = "cursor {$row['cursor_ordinal']} drifted from current-source ordinal";
            }
            if ($row['current_source_token_matches'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} current-source token drifted";
            }
            if ($row['previous_cursor_token'] !== $previousToken) {
                $errors[] = "cursor {$row['cursor_ordinal']} broke token chaining";
            }
            if ($row['pointer_map_epoch_open_before_reuse'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} reused a freeblock before a pointer-map epoch";
            }
            if ($row['leaf_receipt_visible_before_admission'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['trunk_candidate_preserved'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} changed the trunk candidate";
            }
            if ($row['tail_page_still_fenced'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} admitted a fenced tail page";
            }
            if ($row['cursor_link_valid'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} broke current-source link continuity";
            }
            if ($row['cursor_token'] === '') {
                $errors[] = "cursor {$row['cursor_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['cursor_ordinal'];
            $previousToken = (string) $row['cursor_token'];
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
