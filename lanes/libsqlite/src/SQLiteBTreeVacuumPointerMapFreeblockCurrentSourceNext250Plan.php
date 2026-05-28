<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext250Plan
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext247Plan $checkpointPlan,
        private readonly array $handoffRows,
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
        return self::fromCheckpointPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext247Plan::tableLeafFromDeleteResult(
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

    public static function fromCheckpointPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext247Plan $checkpointPlan): self
    {
        $rows = self::buildHandoffRows($checkpointPlan);
        $errors = self::handoffErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next250 handoff failed: ' . implode('; ', $errors));
        }

        return new self($checkpointPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handoffRows(): array
    {
        return $this->handoffRows;
    }

    /**
     * @return list<string>
     */
    public function handoffErrors(): array
    {
        return self::handoffErrorsForRows($this->handoffRows);
    }

    /**
     * @return list<int>
     */
    public function handoffPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['handoff_page'], $this->handoffRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapBarrierPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'pointer-map-barrier');
    }

    /**
     * @return list<int>
     */
    public function freeblockSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'freeblock-source');
    }

    /**
     * @return list<int>
     */
    public function payloadSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'payload-source');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapBarrierPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_barrier'] === true);
    }

    /**
     * @return list<string>
     */
    public function handoffTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['handoff_token'], $this->handoffRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function handoffSummary(): array
    {
        $checkpointSummary = $this->checkpointPlan->checkpointSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next250-ready',
            'handoff_row_count' => count($this->handoffRows),
            'handoff_pages' => $this->handoffPages(),
            'checkpoint_pages' => $checkpointSummary['checkpoint_pages'],
            'handoff_pages_match_checkpoint_pages' => $this->handoffPages() === $checkpointSummary['checkpoint_pages'],
            'pointer_map_barrier_pages' => $this->pointerMapBarrierPages(),
            'freeblock_source_pages' => $this->freeblockSourcePages(),
            'payload_source_pages' => $this->payloadSourcePages(),
            'duplicate_pointer_map_barrier_pages' => $this->duplicatePointerMapBarrierPages(),
            'all_checkpoint_tokens_match' => !in_array(false, array_column($this->handoffRows, 'checkpoint_token_matches'), true),
            'all_handoff_links_current' => !in_array(false, array_column($this->handoffRows, 'handoff_link_current'), true),
            'all_pointer_map_barriers_before_sources' => !in_array(false, array_column($this->handoffRows, 'pointer_map_barrier_before_source'), true),
            'all_freeblock_sources_open_before_payload' => !in_array(false, array_column($this->handoffRows, 'freeblock_source_open_before_payload'), true),
            'all_payload_sources_checkpoint_ready' => !in_array(false, array_column($this->handoffRows, 'payload_source_checkpoint_ready'), true),
            'all_duplicate_pointer_maps_keep_generation' => !in_array(false, array_column($this->handoffRows, 'duplicate_pointer_map_keeps_generation'), true),
            'all_tail_pages_excluded_from_handoff' => !in_array(false, array_column($this->handoffRows, 'tail_page_excluded_from_handoff'), true),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_signature' => self::signature($this->handoffTokens()),
            'current_source_next250_token' => self::signature(array_merge(
                ['next250', $checkpointSummary['current_source_next247_token']],
                $this->handoffPages(),
                $this->handoffTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next247',
                'sqlite-current-source-next250',
            ],
            'dependency_closure' => 'no new support component needed; next250 reuses next247 checkpoint rows and validates the next current-source freeblock/payload handoff barriers',
            'non_overlap' => 'adds current-source next250 handoff barrier validation after next247 checkpoint admission; does not repeat next247 checkpoint construction, next244 publish ordering, next241 source cursor rows, next238 freelist-link admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next250',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
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
        foreach ($this->handoffRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['handoff_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext247Plan $checkpointPlan): array
    {
        $checkpointRows = $checkpointPlan->checkpointRows();
        $checkpointTokens = $checkpointPlan->checkpointTokens();
        $rows = [];
        $previousToken = null;
        $pointerMapGenerations = [];
        $barrierPages = [];
        $freeblockOpened = false;
        $payloadPages = [];

        foreach ($checkpointRows as $index => $checkpointRow) {
            $pageNumber = (int) $checkpointRow['checkpoint_page'];
            $checkpointChannel = (string) $checkpointRow['checkpoint_channel'];
            $ordinal = $index + 1;

            if ($checkpointChannel === 'pointer-map') {
                $handoffChannel = 'pointer-map-barrier';
                $pointerMapGenerations[$pageNumber] = ($pointerMapGenerations[$pageNumber] ?? 0) + 1;
                $barrierPages[$pageNumber] = true;
            } elseif ($checkpointRow['freeblock_receipt_checkpointed'] === true && $freeblockOpened === false) {
                $handoffChannel = 'freeblock-source';
                $freeblockOpened = true;
            } else {
                $handoffChannel = 'payload-source';
            }

            $isPayloadSource = $handoffChannel === 'payload-source';
            if ($isPayloadSource && $checkpointRow['payload_checkpoint_ready'] === true) {
                $payloadPages[$pageNumber] = true;
            }

            $duplicatePointerMap = $handoffChannel === 'pointer-map-barrier' && ($pointerMapGenerations[$pageNumber] ?? 0) > 1;
            $nextHandoffPage = $checkpointRows[$index + 1]['checkpoint_page'] ?? null;
            $token = self::signature(array_merge(
                ['next250', $previousToken ?? 'initial', $checkpointRow['checkpoint_token']],
                [$ordinal, $pageNumber, $nextHandoffPage ?? 'eof', $handoffChannel, $freeblockOpened, $duplicatePointerMap],
                self::generationParts($pointerMapGenerations),
                self::sortedIntKeys($barrierPages),
                self::sortedIntKeys($payloadPages),
            ));

            $rows[] = [
                'handoff_ordinal' => $ordinal,
                'checkpoint_ordinal' => (int) $checkpointRow['checkpoint_ordinal'],
                'handoff_page' => $pageNumber,
                'next_handoff_page' => $nextHandoffPage,
                'checkpoint_channel' => $checkpointChannel,
                'handoff_channel' => $handoffChannel,
                'checkpoint_token' => (string) $checkpointRow['checkpoint_token'],
                'expected_checkpoint_token' => $checkpointTokens[$index] ?? null,
                'checkpoint_token_matches' => ($checkpointTokens[$index] ?? null) === (string) $checkpointRow['checkpoint_token'],
                'previous_handoff_token' => $previousToken,
                'handoff_link_current' => $nextHandoffPage === ($checkpointRows[$index + 1]['checkpoint_page'] ?? null),
                'pointer_map_generations' => self::generationParts($pointerMapGenerations),
                'pointer_map_barrier_pages' => self::sortedIntKeys($barrierPages),
                'payload_source_pages' => self::sortedIntKeys($payloadPages),
                'freeblock_source_open' => $freeblockOpened,
                'pointer_map_barrier_before_source' => $handoffChannel === 'pointer-map-barrier' || $barrierPages !== [],
                'freeblock_source_open_before_payload' => !$isPayloadSource || $freeblockOpened,
                'payload_source_checkpoint_ready' => !$isPayloadSource || $checkpointRow['payload_checkpoint_ready'] === true,
                'duplicate_pointer_map_barrier' => $duplicatePointerMap,
                'duplicate_pointer_map_keeps_generation' => !$duplicatePointerMap || ($pointerMapGenerations[$pageNumber] ?? 0) > 1,
                'tail_page_excluded_from_handoff' => $checkpointRow['tail_page_excluded_from_checkpoint'] === true,
                'handoff_state' => 'current-source-next250-freeblock-handoff-admitted',
                'handoff_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function handoffErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousOrdinal = 0;
        $previousToken = null;

        foreach ($rows as $row) {
            if ($row['handoff_state'] !== 'current-source-next250-freeblock-handoff-admitted') {
                $errors[] = "handoff {$row['handoff_ordinal']} is not admitted";
            }
            if ((int) $row['handoff_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "handoff {$row['handoff_ordinal']} skipped an ordinal";
            }
            if ((int) $row['checkpoint_ordinal'] !== (int) $row['handoff_ordinal']) {
                $errors[] = "handoff {$row['handoff_ordinal']} drifted from checkpoint ordinal";
            }
            if ($row['checkpoint_token_matches'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} checkpoint token drifted";
            }
            if ($row['previous_handoff_token'] !== $previousToken) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke token chaining";
            }
            if ($row['handoff_link_current'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} has stale next-page link";
            }
            if ($row['pointer_map_barrier_before_source'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} opened source before pointer-map barrier";
            }
            if ($row['freeblock_source_open_before_payload'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} opened payload before freeblock source";
            }
            if ($row['payload_source_checkpoint_ready'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} exposed payload without checkpoint readiness";
            }
            if ($row['duplicate_pointer_map_keeps_generation'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lost duplicate pointer-map generation";
            }
            if ($row['tail_page_excluded_from_handoff'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} exposed a fenced tail page";
            }
            if ($row['handoff_token'] === '') {
                $errors[] = "handoff {$row['handoff_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['handoff_ordinal'];
            $previousToken = (string) $row['handoff_token'];
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

        return array_values(array_map(
            static fn (int $page, int $generation): string => $page . ':' . $generation,
            array_keys($generations),
            array_values($generations),
        ));
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
