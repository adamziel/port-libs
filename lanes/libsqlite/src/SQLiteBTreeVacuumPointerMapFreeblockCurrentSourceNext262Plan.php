<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext262Plan
{
    /**
     * @param list<array<string, mixed>> $replayRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan $handoffPlan,
        private readonly array $replayRows,
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
        return self::fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan::tableLeafFromDeleteResult(
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

    public static function fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan $handoffPlan): self
    {
        $rows = self::buildReplayRows($handoffPlan);
        $errors = self::replayErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next262 replay failed: ' . implode('; ', $errors));
        }

        return new self($handoffPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function replayRows(): array
    {
        return $this->replayRows;
    }

    /**
     * @return list<string>
     */
    public function replayErrors(): array
    {
        return self::replayErrorsForRows($this->replayRows);
    }

    /**
     * @return list<int>
     */
    public function replayPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['replay_page'], $this->replayRows));
    }

    /**
     * @return list<int>
     */
    public function barrierPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['replay_channel'] === 'pointer-map-replay-barrier');
    }

    /**
     * @return list<int>
     */
    public function consumablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['replay_channel'] === 'freeblock-consume-ready');
    }

    /**
     * @return list<int>
     */
    public function replayWriteOffsets(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['replay_write_offset'], $this->replayRows));
    }

    /**
     * @return list<int>
     */
    public function replayBarrierEpochs(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['replay_barrier_epoch'], $this->replayRows));
    }

    /**
     * @return list<string>
     */
    public function replayTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['replay_token'], $this->replayRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function replaySummary(): array
    {
        $handoffSummary = $this->handoffPlan->handoffSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next262-ready',
            'replay_row_count' => count($this->replayRows),
            'replay_pages' => $this->replayPages(),
            'handoff_pages' => $handoffSummary['handoff_pages'],
            'replay_pages_match_handoff' => $this->replayPages() === $handoffSummary['handoff_pages'],
            'barrier_pages' => $this->barrierPages(),
            'consumable_pages' => $this->consumablePages(),
            'replay_write_offsets' => $this->replayWriteOffsets(),
            'replay_barrier_epochs' => $this->replayBarrierEpochs(),
            'all_handoff_tokens_match' => !in_array(false, array_column($this->replayRows, 'handoff_token_matches'), true),
            'all_barriers_seen_before_consume' => !in_array(false, array_column($this->replayRows, 'barrier_seen_before_consume'), true),
            'all_stale_slots_remain_fenced' => !in_array(false, array_column($this->replayRows, 'stale_slot_remains_fenced'), true),
            'all_leaf_receipts_replayable' => !in_array(false, array_column($this->replayRows, 'leaf_receipt_replayable'), true),
            'all_replay_links_valid' => !in_array(false, array_column($this->replayRows, 'replay_link_valid'), true),
            'replay_errors' => $this->replayErrors(),
            'replay_signature' => self::signature($this->replayTokens()),
            'current_source_next262_token' => self::signature(array_merge(
                ['next262', $handoffSummary['current_source_next258_token']],
                $this->replayPages(),
                $this->replayWriteOffsets(),
                $this->replayTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next258',
                'sqlite-current-source-next262',
            ],
            'dependency_closure' => 'no new support component needed; next262 reuses next258 handoff rows and records the final replay barrier before next-source freeblock consumption',
            'non_overlap' => 'adds final replay-barrier ordering after next258 stale-slot fencing; does not repeat next258 handoff rows, next254 write slots, next249 allocation publication, accepted batch221 next258 behavior, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, PRAGMA, encoding, or suite-runner surfaces',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next262',
            'replay_summary' => $this->replaySummary(),
            'replay_errors' => $this->replayErrors(),
            'replay_rows' => $this->replayRows,
            'handoff_plan' => $this->handoffPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->replayRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['replay_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReplayRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan $handoffPlan): array
    {
        $handoffRows = $handoffPlan->handoffRows();
        $handoffTokens = $handoffPlan->handoffTokens();
        $rows = [];
        $previousToken = null;
        $barrierEpoch = 0;
        $lastBarrierPage = null;
        $lastConsumablePage = null;

        foreach ($handoffRows as $index => $handoffRow) {
            $pageNumber = (int) $handoffRow['handoff_page'];
            $isBarrier = $handoffRow['handoff_channel'] === 'pointer-map-fence';
            if ($isBarrier) {
                ++$barrierEpoch;
                $lastBarrierPage = $pageNumber;
            }

            $ordinal = $index + 1;
            $handoffToken = (string) $handoffRow['handoff_token'];
            $writeOffset = (int) $handoffRow['handoff_write_offset'];
            $channel = $isBarrier ? 'pointer-map-replay-barrier' : 'freeblock-consume-ready';
            $token = self::signature([
                'next262',
                $ordinal,
                $previousToken ?? 'initial',
                $handoffToken,
                $pageNumber,
                $channel,
                $barrierEpoch,
                $lastBarrierPage ?? 0,
                $lastConsumablePage ?? 0,
                $writeOffset,
            ]);

            $rows[] = [
                'replay_ordinal' => $ordinal,
                'handoff_ordinal' => (int) $handoffRow['handoff_ordinal'],
                'replay_page' => $pageNumber,
                'replay_channel' => $channel,
                'source_handoff_token' => $handoffToken,
                'expected_handoff_token' => $handoffTokens[$index] ?? null,
                'handoff_token_matches' => ($handoffTokens[$index] ?? null) === $handoffToken,
                'previous_replay_token' => $previousToken,
                'replay_barrier_epoch' => $barrierEpoch,
                'last_barrier_page' => $lastBarrierPage,
                'previous_consumable_page' => $lastConsumablePage,
                'replay_write_offset' => $writeOffset,
                'barrier_seen_before_consume' => $isBarrier || $barrierEpoch > 0,
                'stale_slot_remains_fenced' => $handoffRow['stale_freeblock_slot_fenced'] === true,
                'leaf_receipt_replayable' => $isBarrier || $handoffRow['leaf_receipt_preserved_for_next_source'] === true,
                'replay_link_valid' => $handoffRow['previous_handoff_token'] === ($handoffRows[$index - 1]['handoff_token'] ?? null),
                'replay_state' => 'current-source-next262-replay-barrier-ready',
                'replay_token' => $token,
            ];

            if (!$isBarrier) {
                $lastConsumablePage = $pageNumber;
            }
            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function replayErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousBarrierEpoch = 0;

        foreach ($rows as $row) {
            if ($row['replay_state'] !== 'current-source-next262-replay-barrier-ready') {
                $errors[] = "replay {$row['replay_ordinal']} is not ready";
            }
            if ((int) $row['replay_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "replay {$row['replay_ordinal']} skipped an ordinal";
            }
            if ((int) $row['handoff_ordinal'] !== (int) $row['replay_ordinal']) {
                $errors[] = "replay {$row['replay_ordinal']} drifted from handoff ordinal";
            }
            if ($row['handoff_token_matches'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} handoff token drifted";
            }
            if ($row['previous_replay_token'] !== $previousToken) {
                $errors[] = "replay {$row['replay_ordinal']} broke token chaining";
            }
            if ((int) $row['replay_barrier_epoch'] < $previousBarrierEpoch) {
                $errors[] = "replay {$row['replay_ordinal']} moved barrier epoch backward";
            }
            if ($row['barrier_seen_before_consume'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} consumed a page before a pointer-map barrier";
            }
            if ($row['stale_slot_remains_fenced'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} reopened a stale freeblock slot";
            }
            if ($row['leaf_receipt_replayable'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} lost the leaf receipt";
            }
            if ($row['replay_link_valid'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} broke handoff link continuity";
            }
            if ($row['replay_token'] === '') {
                $errors[] = "replay {$row['replay_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['replay_ordinal'];
            $previousBarrierEpoch = (int) $row['replay_barrier_epoch'];
            $previousToken = (string) $row['replay_token'];
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
