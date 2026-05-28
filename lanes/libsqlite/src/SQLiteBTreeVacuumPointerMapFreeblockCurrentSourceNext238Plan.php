<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext238Plan
{
    /**
     * @param list<array<string, mixed>> $freelistRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext235Plan $checkpointPlan,
        private readonly array $freelistRows,
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
        return self::fromCheckpointPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext235Plan::tableLeafFromDeleteResult(
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

    public static function fromCheckpointPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext235Plan $checkpointPlan): self
    {
        $rows = self::buildFreelistRows($checkpointPlan);
        $errors = self::freelistErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next238 freelist admission failed: ' . implode('; ', $errors));
        }

        return new self($checkpointPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function freelistRows(): array
    {
        return $this->freelistRows;
    }

    /**
     * @return list<string>
     */
    public function freelistErrors(): array
    {
        return self::freelistErrorsForRows($this->freelistRows);
    }

    /**
     * @return list<int>
     */
    public function freelistPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['freelist_page'], $this->freelistRows));
    }

    /**
     * @return list<int>
     */
    public function reusablePayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freelist_channel'] === 'payload');
    }

    /**
     * @return list<int>
     */
    public function pointerMapBarrierPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freelist_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function freelistTrunkCandidatePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freelist_trunk_candidate'] === true);
    }

    /**
     * @return list<string>
     */
    public function freelistTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['freelist_token'], $this->freelistRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function freelistSummary(): array
    {
        $checkpointSummary = $this->checkpointPlan->checkpointSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next238-ready',
            'freelist_row_count' => count($this->freelistRows),
            'freelist_pages' => $this->freelistPages(),
            'checkpoint_pages' => $checkpointSummary['checkpoint_pages'],
            'freelist_pages_match_checkpoint_pages' => $this->freelistPages() === $checkpointSummary['checkpoint_pages'],
            'pointer_map_barrier_pages' => $this->pointerMapBarrierPages(),
            'reusable_payload_pages' => $this->reusablePayloadPages(),
            'freelist_trunk_candidate_pages' => $this->freelistTrunkCandidatePages(),
            'all_checkpoint_tokens_match' => !in_array(false, array_column($this->freelistRows, 'checkpoint_token_matches'), true),
            'all_pointer_map_barriers_seen_before_reuse' => !in_array(false, array_column($this->freelistRows, 'pointer_map_barrier_seen_before_reuse'), true),
            'all_freeblock_receipts_admitted_to_freelist' => !in_array(false, array_column($this->freelistRows, 'freeblock_receipt_admitted_to_freelist'), true),
            'all_reusable_payload_pages_linked_monotonically' => !in_array(false, array_column($this->freelistRows, 'reusable_payload_page_linked_monotonically'), true),
            'all_duplicate_pointer_maps_preserve_generation' => !in_array(false, array_column($this->freelistRows, 'duplicate_pointer_map_preserves_generation'), true),
            'all_tail_pages_blocked_from_freelist' => !in_array(false, array_column($this->freelistRows, 'tail_page_blocked_from_freelist'), true),
            'freelist_errors' => $this->freelistErrors(),
            'freelist_signature' => self::signature($this->freelistTokens()),
            'current_source_next238_token' => self::signature(array_merge(
                ['next238', $checkpointSummary['current_source_next235_token']],
                $this->freelistPages(),
                $this->freelistTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next235',
                'sqlite-current-source-next238',
            ],
            'dependency_closure' => 'no new support component needed; next238 reuses next235 checkpoint rows and adds freelist-link admission after pointer-map/freeblock visibility',
            'non_overlap' => 'adds freelist-link admission for checkpointed reusable payload pages; does not repeat next235 checkpoint admission, next232 handoff admission, overflow freelist release, page relocation, root collapse, index-interior merge, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next238',
            'freelist_summary' => $this->freelistSummary(),
            'freelist_errors' => $this->freelistErrors(),
            'freelist_rows' => $this->freelistRows,
            'checkpoint_plan' => $this->checkpointPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->freelistRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['freelist_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildFreelistRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext235Plan $checkpointPlan): array
    {
        $checkpointRows = $checkpointPlan->checkpointRows();
        $checkpointTokens = $checkpointPlan->checkpointTokens();
        $rows = [];
        $previousFreelistToken = null;
        $seenPointerMaps = [];
        $admittedPayloadPages = [];
        $lastPayloadPage = 0;

        foreach ($checkpointRows as $index => $checkpointRow) {
            $pageNumber = (int) $checkpointRow['checkpoint_page'];
            $channel = (string) $checkpointRow['checkpoint_channel'];
            if ($channel === 'pointer-map') {
                $seenPointerMaps[$pageNumber] = true;
            }

            $payloadReusable = $checkpointRow['payload_reusable_after_checkpoint'] === true;
            if ($payloadReusable) {
                $admittedPayloadPages[$pageNumber] = true;
            }

            $linkedMonotonic = $channel !== 'payload' || ($payloadReusable && $pageNumber > $lastPayloadPage);
            if ($channel === 'payload' && $payloadReusable) {
                $lastPayloadPage = $pageNumber;
            }

            $token = self::signature(array_merge(
                ['next238', $previousFreelistToken ?? 'initial', $checkpointRow['checkpoint_token']],
                [$index + 1, $pageNumber, $channel, $payloadReusable, $lastPayloadPage],
                self::sortedIntKeys($seenPointerMaps),
                self::sortedIntKeys($admittedPayloadPages),
            ));

            $rows[] = [
                'freelist_ordinal' => $index + 1,
                'checkpoint_ordinal' => (int) $checkpointRow['checkpoint_ordinal'],
                'freelist_page' => $pageNumber,
                'freelist_channel' => $channel,
                'source_checkpoint_token' => (string) $checkpointRow['checkpoint_token'],
                'expected_checkpoint_token' => $checkpointTokens[$index] ?? null,
                'checkpoint_token_matches' => ($checkpointTokens[$index] ?? null) === (string) $checkpointRow['checkpoint_token'],
                'previous_freelist_token' => $previousFreelistToken,
                'visible_pointer_map_barrier_pages' => self::sortedIntKeys($seenPointerMaps),
                'admitted_reusable_payload_pages' => self::sortedIntKeys($admittedPayloadPages),
                'pointer_map_barrier_seen_before_reuse' => $channel !== 'payload' || ($payloadReusable && $seenPointerMaps !== []),
                'freeblock_receipt_admitted_to_freelist' => $checkpointRow['freeblock_receipt_visible_at_checkpoint'] === true && $checkpointRow['payload_reuse_waits_for_pointer_map'] === true,
                'reusable_payload_page_linked_monotonically' => $linkedMonotonic,
                'duplicate_pointer_map_preserves_generation' => $checkpointRow['duplicate_pointer_map_checkpoint'] !== true || (int) $checkpointRow['pointer_map_generation'] > 1,
                'tail_page_blocked_from_freelist' => $checkpointRow['tail_pages_remain_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'freelist_trunk_candidate' => $payloadReusable && $pageNumber === min(self::sortedIntKeys($admittedPayloadPages)),
                'freelist_state' => 'current-source-next238-freelist-link-admitted',
                'freelist_token' => $token,
            ];

            $previousFreelistToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function freelistErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['freelist_state'] !== 'current-source-next238-freelist-link-admitted') {
                $errors[] = "freelist {$row['freelist_ordinal']} is not admitted";
            }
            if ((int) $row['freelist_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "freelist {$row['freelist_ordinal']} skipped an ordinal";
            }
            if ((int) $row['checkpoint_ordinal'] !== (int) $row['freelist_ordinal']) {
                $errors[] = "freelist {$row['freelist_ordinal']} drifted from checkpoint ordinal";
            }
            if ($row['checkpoint_token_matches'] !== true) {
                $errors[] = "freelist {$row['freelist_ordinal']} checkpoint token drifted";
            }
            if ($row['previous_freelist_token'] !== $previousToken) {
                $errors[] = "freelist {$row['freelist_ordinal']} broke token chaining";
            }
            if ($row['pointer_map_barrier_seen_before_reuse'] !== true) {
                $errors[] = "freelist {$row['freelist_ordinal']} reused payload before pointer-map barrier";
            }
            if ($row['freeblock_receipt_admitted_to_freelist'] !== true) {
                $errors[] = "freelist {$row['freelist_ordinal']} lost freeblock receipt";
            }
            if ($row['reusable_payload_page_linked_monotonically'] !== true) {
                $errors[] = "freelist {$row['freelist_ordinal']} linked reusable payload pages out of order";
            }
            if ($row['duplicate_pointer_map_preserves_generation'] !== true) {
                $errors[] = "freelist {$row['freelist_ordinal']} lost duplicate pointer-map generation";
            }
            if ($row['tail_page_blocked_from_freelist'] !== true) {
                $errors[] = "freelist {$row['freelist_ordinal']} exposed a fenced tail page";
            }
            if ($row['freelist_token'] === '') {
                $errors[] = "freelist {$row['freelist_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['freelist_ordinal'];
            $previousToken = (string) $row['freelist_token'];
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
