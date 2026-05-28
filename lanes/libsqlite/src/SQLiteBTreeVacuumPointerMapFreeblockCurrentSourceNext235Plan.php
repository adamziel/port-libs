<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext235Plan
{
    /**
     * @param list<array<string, mixed>> $checkpointRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Plan $handoffPlan,
        private readonly array $checkpointRows,
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
        return self::fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Plan::tableLeafFromDeleteResult(
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

    public static function fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Plan $handoffPlan): self
    {
        $rows = self::buildCheckpointRows($handoffPlan);
        $errors = self::checkpointErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next235 checkpoint failed: ' . implode('; ', $errors));
        }

        return new self($handoffPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checkpointRows(): array
    {
        return $this->checkpointRows;
    }

    /**
     * @return list<string>
     */
    public function checkpointErrors(): array
    {
        return self::checkpointErrorsForRows($this->checkpointRows);
    }

    /**
     * @return list<int>
     */
    public function checkpointPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['checkpoint_page'], $this->checkpointRows));
    }

    /**
     * @return list<int>
     */
    public function reusablePayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['payload_reusable_after_checkpoint'] === true);
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapCheckpointPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_checkpoint'] === true);
    }

    /**
     * @return list<string>
     */
    public function checkpointTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['checkpoint_token'], $this->checkpointRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function checkpointSummary(): array
    {
        $handoffSummary = $this->handoffPlan->handoffSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next235-ready',
            'checkpoint_row_count' => count($this->checkpointRows),
            'checkpoint_pages' => $this->checkpointPages(),
            'handoff_pages' => $handoffSummary['handoff_pages'],
            'checkpoint_pages_match_handoff_pages' => $this->checkpointPages() === $handoffSummary['handoff_pages'],
            'duplicate_pointer_map_checkpoint_pages' => $this->duplicatePointerMapCheckpointPages(),
            'reusable_payload_pages' => $this->reusablePayloadPages(),
            'all_handoff_tokens_match' => !in_array(false, array_column($this->checkpointRows, 'handoff_token_matches'), true),
            'all_current_source_links_closed' => !in_array(false, array_column($this->checkpointRows, 'current_source_link_closed'), true),
            'all_pointer_map_generations_preserved' => !in_array(false, array_column($this->checkpointRows, 'pointer_map_generation_preserved'), true),
            'all_payload_reuse_waits_for_pointer_map' => !in_array(false, array_column($this->checkpointRows, 'payload_reuse_waits_for_pointer_map'), true),
            'all_freeblock_receipts_visible' => !in_array(false, array_column($this->checkpointRows, 'freeblock_receipt_visible_at_checkpoint'), true),
            'all_tail_pages_remain_fenced' => !in_array(false, array_column($this->checkpointRows, 'tail_pages_remain_fenced'), true),
            'checkpoint_errors' => $this->checkpointErrors(),
            'checkpoint_signature' => self::signature($this->checkpointTokens()),
            'current_source_next235_token' => self::signature(array_merge(
                ['next235', $handoffSummary['current_source_next232_token']],
                $this->checkpointPages(),
                $this->checkpointTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next232',
                'sqlite-current-source-next235',
            ],
            'dependency_closure' => 'no new support component needed; next235 reuses next232 handoff rows and adds reusable-payload checkpoint admission only',
            'non_overlap' => 'adds post-handoff current-source checkpoints for duplicate pointer-map rewrites and payload reuse admission; does not repeat next232 handoff admission, next229 resume construction, next224 cursor sequencing, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next235',
            'checkpoint_summary' => $this->checkpointSummary(),
            'checkpoint_errors' => $this->checkpointErrors(),
            'checkpoint_rows' => $this->checkpointRows,
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
        foreach ($this->checkpointRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['checkpoint_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCheckpointRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Plan $handoffPlan): array
    {
        $handoffRows = $handoffPlan->handoffRows();
        $handoffTokens = $handoffPlan->handoffTokens();
        $rows = [];
        $previousCheckpointToken = null;
        $visiblePointerMaps = [];
        $pointerMapGenerations = [];
        $visibleFreeblockPages = [];

        foreach ($handoffRows as $index => $handoffRow) {
            $pageNumber = (int) $handoffRow['handoff_page'];
            $channel = (string) $handoffRow['handoff_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $visiblePointerMaps[$pageNumber] = true;
                $pointerMapGenerations[$pageNumber] = ($pointerMapGenerations[$pageNumber] ?? 0) + 1;
            }
            if ($handoffRow['freeblock_handoff_receipt_carried'] === true) {
                $visibleFreeblockPages[$pageNumber] = true;
            }

            $pointerMapVisible = $visiblePointerMaps !== [];
            $payloadReusable = $channel === 'payload' && $pointerMapVisible && $handoffRow['payload_handoff_admitted_after_pointer_map'] === true;
            $duplicatePointerMap = $channel === 'pointer-map' && ($pointerMapGenerations[$pageNumber] ?? 0) > 1;
            $token = self::signature(array_merge(
                ['next235', $previousCheckpointToken ?? 'initial', $handoffRow['handoff_token']],
                [$ordinal, $pageNumber, $channel, $payloadReusable, $duplicatePointerMap],
                self::sortedIntKeys($visiblePointerMaps),
                self::sortedIntKeys($visibleFreeblockPages),
            ));

            $rows[] = [
                'checkpoint_ordinal' => $ordinal,
                'handoff_ordinal' => (int) $handoffRow['handoff_ordinal'],
                'checkpoint_page' => $pageNumber,
                'checkpoint_channel' => $channel,
                'source_handoff_token' => (string) $handoffRow['handoff_token'],
                'expected_handoff_token' => $handoffTokens[$index] ?? null,
                'handoff_token_matches' => ($handoffTokens[$index] ?? null) === (string) $handoffRow['handoff_token'],
                'previous_checkpoint_token' => $previousCheckpointToken,
                'current_source_link_closed' => $handoffRow['next_handoff_page'] === ($handoffRows[$index + 1]['handoff_page'] ?? null),
                'visible_pointer_map_pages' => self::sortedIntKeys($visiblePointerMaps),
                'visible_freeblock_receipt_pages' => self::sortedIntKeys($visibleFreeblockPages),
                'pointer_map_generation' => $pointerMapGenerations[$pageNumber] ?? 0,
                'duplicate_pointer_map_checkpoint' => $duplicatePointerMap,
                'pointer_map_generation_preserved' => $channel !== 'pointer-map' || ($pointerMapGenerations[$pageNumber] ?? 0) >= 1,
                'payload_reusable_after_checkpoint' => $payloadReusable,
                'payload_reuse_waits_for_pointer_map' => $channel !== 'payload' || ($payloadReusable && $pointerMapVisible),
                'freeblock_receipt_visible_at_checkpoint' => $handoffRow['freeblock_handoff_receipt_carried'] === true && $visibleFreeblockPages !== [],
                'tail_pages_remain_fenced' => $handoffRow['tail_pages_fenced_for_handoff'] === true && !in_array($pageNumber, [109, 110], true),
                'checkpoint_state' => 'current-source-next235-reusable-payload-checkpointed',
                'checkpoint_token' => $token,
            ];

            $previousCheckpointToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function checkpointErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $index => $row) {
            if ($row['checkpoint_state'] !== 'current-source-next235-reusable-payload-checkpointed') {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} is not checkpointed";
            }
            if ((int) $row['checkpoint_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} skipped an ordinal";
            }
            if ((int) $row['handoff_ordinal'] !== (int) $row['checkpoint_ordinal']) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} drifted from handoff ordinal";
            }
            if ($row['handoff_token_matches'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} handoff token drifted";
            }
            if ($row['previous_checkpoint_token'] !== $previousToken) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} broke token chaining";
            }
            if ($row['current_source_link_closed'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} has an open current-source link";
            }
            if ($row['pointer_map_generation_preserved'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} lost pointer-map generation state";
            }
            if ($row['payload_reuse_waits_for_pointer_map'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} made payload reusable before pointer-map";
            }
            if ($row['freeblock_receipt_visible_at_checkpoint'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} lost freeblock receipt visibility";
            }
            if ($row['tail_pages_remain_fenced'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} exposed a fenced tail page";
            }
            if ($row['checkpoint_token'] === '') {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} has an empty token";
            }
            if ($index === count($rows) - 1 && $row['current_source_link_closed'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} did not close at eof";
            }

            $previousOrdinal = (int) $row['checkpoint_ordinal'];
            $previousToken = (string) $row['checkpoint_token'];
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
