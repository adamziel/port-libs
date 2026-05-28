<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan
{
    /**
     * @param list<array<string, mixed>> $nextSourceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Plan $cursorPlan,
        private readonly array $nextSourceRows,
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
        return self::fromCursorPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Plan::tableLeafFromDeleteResult(
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

    public static function fromCursorPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Plan $cursorPlan): self
    {
        $rows = self::buildNextSourceRows($cursorPlan);
        $errors = self::nextSourceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next249 handoff failed: ' . implode('; ', $errors));
        }

        return new self($cursorPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nextSourceRows(): array
    {
        return $this->nextSourceRows;
    }

    /**
     * @return list<string>
     */
    public function nextSourceErrors(): array
    {
        return self::nextSourceErrorsForRows($this->nextSourceRows);
    }

    /**
     * @return list<int>
     */
    public function nextSourcePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['next_source_page'], $this->nextSourceRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapEpochPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['next_source_channel'] === 'pointer-map-epoch');
    }

    /**
     * @return list<int>
     */
    public function reusableAllocationPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['next_source_channel'] === 'reusable-allocation');
    }

    /**
     * @return list<int>
     */
    public function nextAllocationPositions(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['next_allocation_position'], $this->nextSourceRows));
    }

    /**
     * @return list<string>
     */
    public function nextSourceTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['next_source_token'], $this->nextSourceRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function nextSourceSummary(): array
    {
        $cursorSummary = $this->cursorPlan->cursorSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next249-ready',
            'next_source_row_count' => count($this->nextSourceRows),
            'next_source_pages' => $this->nextSourcePages(),
            'cursor_pages' => $cursorSummary['admitted_pages'],
            'next_source_pages_match_cursor' => $this->nextSourcePages() === $cursorSummary['admitted_pages'],
            'pointer_map_epoch_pages' => $this->pointerMapEpochPages(),
            'reusable_allocation_pages' => $this->reusableAllocationPages(),
            'next_allocation_positions' => $this->nextAllocationPositions(),
            'all_cursor_tokens_match' => !in_array(false, array_column($this->nextSourceRows, 'cursor_token_matches'), true),
            'all_pointer_map_epochs_ready' => !in_array(false, array_column($this->nextSourceRows, 'pointer_map_epoch_ready_for_next_source'), true),
            'all_reusable_pages_after_epoch' => !in_array(false, array_column($this->nextSourceRows, 'reusable_page_after_pointer_map_epoch'), true),
            'all_leaf_receipts_carried_forward' => !in_array(false, array_column($this->nextSourceRows, 'leaf_receipt_carried_forward'), true),
            'all_trunk_candidates_carried_forward' => !in_array(false, array_column($this->nextSourceRows, 'trunk_candidate_carried_forward'), true),
            'all_next_source_links_valid' => !in_array(false, array_column($this->nextSourceRows, 'next_source_link_valid'), true),
            'next_source_errors' => $this->nextSourceErrors(),
            'next_source_signature' => self::signature($this->nextSourceTokens()),
            'current_source_next249_token' => self::signature(array_merge(
                ['next249', $cursorSummary['current_source_next245_token']],
                $this->nextSourcePages(),
                $this->nextAllocationPositions(),
                $this->nextSourceTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next245',
                'sqlite-current-source-next249',
            ],
            'dependency_closure' => 'no new support component needed; next249 reuses next245 admitted cursor rows and records next-source allocation ordering only',
            'non_overlap' => 'adds next-source allocation publication after next245 cursor admission; does not repeat next245 cursor ordering, next242 current-source visibility, next238 freelist admission, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or VFS/WAL behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next249',
            'next_source_summary' => $this->nextSourceSummary(),
            'next_source_errors' => $this->nextSourceErrors(),
            'next_source_rows' => $this->nextSourceRows,
            'cursor_plan' => $this->cursorPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->nextSourceRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['next_source_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildNextSourceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Plan $cursorPlan): array
    {
        $cursorRows = $cursorPlan->cursorRows();
        $cursorTokens = $cursorPlan->cursorTokens();
        $rows = [];
        $previousToken = null;
        $openEpoch = 0;
        $allocationPosition = 0;
        $stableTrunk = null;

        foreach ($cursorRows as $index => $cursorRow) {
            $pageNumber = (int) $cursorRow['admitted_page'];
            $admissionKind = (string) $cursorRow['admission_kind'];
            $isPointerMap = $admissionKind === 'pointer-map-barrier';
            $isReusable = $admissionKind === 'reusable-freeblock';
            if ($isPointerMap) {
                $openEpoch = (int) $cursorRow['pointer_map_epoch'];
            }
            if ($isReusable) {
                ++$allocationPosition;
                $stableTrunk ??= (int) $cursorRow['trunk_candidate_page'];
            }

            $ordinal = $index + 1;
            $cursorToken = (string) $cursorRow['cursor_token'];
            $channel = $isPointerMap ? 'pointer-map-epoch' : 'reusable-allocation';
            $token = self::signature([
                'next249',
                $ordinal,
                $previousToken ?? 'initial',
                $cursorToken,
                $pageNumber,
                $channel,
                $openEpoch,
                $allocationPosition,
                $stableTrunk ?? 0,
            ]);

            $rows[] = [
                'next_source_ordinal' => $ordinal,
                'cursor_ordinal' => (int) $cursorRow['cursor_ordinal'],
                'next_source_page' => $pageNumber,
                'next_source_channel' => $channel,
                'source_cursor_token' => $cursorToken,
                'expected_cursor_token' => $cursorTokens[$index] ?? null,
                'cursor_token_matches' => ($cursorTokens[$index] ?? null) === $cursorToken,
                'previous_next_source_token' => $previousToken,
                'pointer_map_epoch' => $openEpoch,
                'next_allocation_position' => $allocationPosition,
                'pointer_map_epoch_ready_for_next_source' => $isPointerMap || $openEpoch > 0,
                'reusable_page_after_pointer_map_epoch' => !$isReusable || $openEpoch > 0,
                'leaf_receipt_carried_forward' => !$isReusable || $cursorRow['leaf_receipt_visible_before_admission'] === true,
                'trunk_candidate_page' => $stableTrunk,
                'trunk_candidate_carried_forward' => !$isReusable || $stableTrunk === $cursorRow['trunk_candidate_page'],
                'tail_page_still_fenced' => $cursorRow['tail_page_still_fenced'] === true,
                'next_source_link_valid' => $cursorRow['previous_cursor_token'] === ($cursorRows[$index - 1]['cursor_token'] ?? null),
                'next_source_state' => 'current-source-next249-next-source-allocation-published',
                'next_source_token' => $token,
            ];
            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function nextSourceErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousAllocationPosition = 0;

        foreach ($rows as $row) {
            if ($row['next_source_state'] !== 'current-source-next249-next-source-allocation-published') {
                $errors[] = "next-source {$row['next_source_ordinal']} is not published";
            }
            if ((int) $row['next_source_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "next-source {$row['next_source_ordinal']} skipped an ordinal";
            }
            if ((int) $row['cursor_ordinal'] !== (int) $row['next_source_ordinal']) {
                $errors[] = "next-source {$row['next_source_ordinal']} drifted from cursor ordinal";
            }
            if ($row['cursor_token_matches'] !== true) {
                $errors[] = "next-source {$row['next_source_ordinal']} cursor token drifted";
            }
            if ($row['previous_next_source_token'] !== $previousToken) {
                $errors[] = "next-source {$row['next_source_ordinal']} broke token chaining";
            }
            if ($row['pointer_map_epoch_ready_for_next_source'] !== true) {
                $errors[] = "next-source {$row['next_source_ordinal']} published before pointer-map epoch";
            }
            if ($row['reusable_page_after_pointer_map_epoch'] !== true) {
                $errors[] = "next-source {$row['next_source_ordinal']} reused a page before pointer-map epoch";
            }
            if ($row['leaf_receipt_carried_forward'] !== true) {
                $errors[] = "next-source {$row['next_source_ordinal']} lost the leaf receipt";
            }
            if ($row['trunk_candidate_carried_forward'] !== true) {
                $errors[] = "next-source {$row['next_source_ordinal']} changed the trunk candidate";
            }
            if ($row['tail_page_still_fenced'] !== true) {
                $errors[] = "next-source {$row['next_source_ordinal']} admitted a fenced tail page";
            }
            if ($row['next_source_link_valid'] !== true) {
                $errors[] = "next-source {$row['next_source_ordinal']} broke cursor link continuity";
            }
            if ((int) $row['next_allocation_position'] < $previousAllocationPosition) {
                $errors[] = "next-source {$row['next_source_ordinal']} moved allocation position backward";
            }
            if ($row['next_source_token'] === '') {
                $errors[] = "next-source {$row['next_source_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['next_source_ordinal'];
            $previousAllocationPosition = (int) $row['next_allocation_position'];
            $previousToken = (string) $row['next_source_token'];
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
