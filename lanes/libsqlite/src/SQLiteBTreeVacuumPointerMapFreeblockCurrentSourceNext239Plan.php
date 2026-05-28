<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext239Plan
{
    /**
     * @param list<array<string, mixed>> $drainRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan $sourceNextPlan,
        private readonly array $drainRows,
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
        return self::fromSourceNextPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan::tableLeafFromDeleteResult(
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

    public static function fromSourceNextPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan $sourceNextPlan): self
    {
        $rows = self::buildDrainRows($sourceNextPlan);
        $errors = self::drainErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next239 drain failed: ' . implode('; ', $errors));
        }

        return new self($sourceNextPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function drainRows(): array
    {
        return $this->drainRows;
    }

    /**
     * @return list<string>
     */
    public function drainErrors(): array
    {
        return self::drainErrorsForRows($this->drainRows);
    }

    /**
     * @return list<int>
     */
    public function drainPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['drain_page'], $this->drainRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapDrainPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['drain_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadDrainPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['drain_channel'] === 'payload');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapDrainPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_generation_drained'] === true);
    }

    /**
     * @return list<string>
     */
    public function drainTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['drain_token'], $this->drainRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function drainSummary(): array
    {
        $sourceNextSummary = $this->sourceNextPlan->sourceNextSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next239-ready',
            'drain_row_count' => count($this->drainRows),
            'drain_pages' => $this->drainPages(),
            'source_next_pages' => $sourceNextSummary['source_next_pages'],
            'drain_pages_match_source_next_pages' => $this->drainPages() === $sourceNextSummary['source_next_pages'],
            'pointer_map_drain_pages' => $this->pointerMapDrainPages(),
            'payload_drain_pages' => $this->payloadDrainPages(),
            'duplicate_pointer_map_drain_pages' => $this->duplicatePointerMapDrainPages(),
            'all_source_next_tokens_match' => !in_array(false, array_column($this->drainRows, 'source_next_token_matches'), true),
            'all_source_next_links_drained' => !in_array(false, array_column($this->drainRows, 'source_next_link_drained'), true),
            'all_pointer_maps_drained_before_payload' => !in_array(false, array_column($this->drainRows, 'pointer_maps_drained_before_payload'), true),
            'all_duplicate_pointer_map_generations_drained' => !in_array(false, array_column($this->drainRows, 'duplicate_pointer_map_generation_preserved'), true),
            'all_freeblock_receipts_drained' => !in_array(false, array_column($this->drainRows, 'freeblock_receipt_drained'), true),
            'all_tail_pages_remain_fenced_after_drain' => !in_array(false, array_column($this->drainRows, 'tail_pages_remain_fenced_after_drain'), true),
            'drain_errors' => $this->drainErrors(),
            'drain_signature' => self::signature($this->drainTokens()),
            'current_source_next239_token' => self::signature(array_merge(
                ['next239', $sourceNextSummary['current_source_next236_token']],
                $this->drainPages(),
                $this->drainTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next236',
                'sqlite-current-source-next239',
            ],
            'dependency_closure' => 'no new support component needed; next239 reuses next236 source-next cursor rows and adds final drain admission for pointer-map/freeblock reuse',
            'non_overlap' => 'adds ordered source-next drain admission after next236 cursor visibility; does not repeat next236 visibility, next233 checkpoints, next229 resume windows, overflow freelist release, page relocation, root collapse, or accepted freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next239',
            'drain_summary' => $this->drainSummary(),
            'drain_errors' => $this->drainErrors(),
            'drain_rows' => $this->drainRows,
            'source_next_plan' => $this->sourceNextPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->drainRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['drain_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildDrainRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan $sourceNextPlan): array
    {
        $sourceRows = $sourceNextPlan->sourceNextRows();
        $sourceTokens = $sourceNextPlan->sourceNextTokens();
        $rows = [];
        $previousDrainToken = null;
        $drainedPointerMapGenerations = [];
        $drainedFreeblockPages = [];

        foreach ($sourceRows as $index => $sourceRow) {
            $pageNumber = (int) $sourceRow['source_next_page'];
            $channel = (string) $sourceRow['source_next_channel'];
            if ($channel === 'pointer-map') {
                $drainedPointerMapGenerations[$pageNumber] = ($drainedPointerMapGenerations[$pageNumber] ?? 0) + 1;
            }
            if ($sourceRow['freeblock_source_next_receipt_current'] === true) {
                $drainedFreeblockPages[$pageNumber] = true;
            }

            $duplicateGeneration = $channel === 'pointer-map' && ($drainedPointerMapGenerations[$pageNumber] ?? 0) > 1;
            $token = self::signature(array_merge(
                ['next239', $previousDrainToken ?? 'initial', $sourceRow['source_next_token']],
                [$index + 1, $pageNumber, $channel, $duplicateGeneration, $sourceRow['next_source_page'] ?? 'eof'],
                self::generationParts($drainedPointerMapGenerations),
                self::sortedIntKeys($drainedFreeblockPages),
            ));

            $rows[] = [
                'drain_ordinal' => $index + 1,
                'source_next_ordinal' => (int) $sourceRow['source_next_ordinal'],
                'drain_page' => $pageNumber,
                'next_drain_page' => $sourceRows[$index + 1]['source_next_page'] ?? null,
                'drain_channel' => $channel,
                'source_next_token' => (string) $sourceRow['source_next_token'],
                'expected_source_next_token' => $sourceTokens[$index] ?? null,
                'source_next_token_matches' => ($sourceTokens[$index] ?? null) === (string) $sourceRow['source_next_token'],
                'previous_drain_token' => $previousDrainToken,
                'source_next_link_drained' => $sourceRow['next_source_page'] === ($sourceRows[$index + 1]['source_next_page'] ?? null),
                'drained_pointer_map_generations' => self::generationParts($drainedPointerMapGenerations),
                'drained_freeblock_pages' => self::sortedIntKeys($drainedFreeblockPages),
                'pointer_maps_drained_before_payload' => $channel === 'pointer-map' || $drainedPointerMapGenerations !== [],
                'duplicate_pointer_map_generation_drained' => $duplicateGeneration,
                'duplicate_pointer_map_generation_preserved' => $channel !== 'pointer-map' || ($drainedPointerMapGenerations[$pageNumber] ?? 0) >= 1,
                'freeblock_receipt_drained' => $sourceRow['freeblock_source_next_receipt_current'] === true && isset($drainedFreeblockPages[$pageNumber]),
                'tail_pages_remain_fenced_after_drain' => $sourceRow['tail_pages_fenced_for_source_next'] === true && !in_array($pageNumber, [109, 110], true),
                'drain_state' => 'current-source-next239-drained',
                'drain_token' => $token,
            ];

            $previousDrainToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function drainErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $index => $row) {
            if ($row['drain_state'] !== 'current-source-next239-drained') {
                $errors[] = "drain {$row['drain_ordinal']} is not drained";
            }
            if ((int) $row['drain_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "drain {$row['drain_ordinal']} skipped an ordinal";
            }
            if ((int) $row['source_next_ordinal'] !== (int) $row['drain_ordinal']) {
                $errors[] = "drain {$row['drain_ordinal']} drifted from source-next ordinal";
            }
            if ($row['source_next_token_matches'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} source-next token drifted";
            }
            if ($row['previous_drain_token'] !== $previousToken) {
                $errors[] = "drain {$row['drain_ordinal']} broke drain token chaining";
            }
            if (($rows[$index + 1]['drain_page'] ?? null) !== $row['next_drain_page']) {
                $errors[] = "drain {$row['drain_ordinal']} has an invalid next-drain link";
            }
            if ($row['drain_channel'] === 'payload' && $row['pointer_maps_drained_before_payload'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} exposed payload before pointer-map drain";
            }
            if ($row['duplicate_pointer_map_generation_preserved'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} lost duplicate pointer-map drain generation";
            }
            if ($row['freeblock_receipt_drained'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} lost the freeblock receipt";
            }
            if ($row['tail_pages_remain_fenced_after_drain'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} exposed fenced tail pages";
            }
            if ($row['drain_token'] === '') {
                $errors[] = "drain {$row['drain_ordinal']} has an empty drain token";
            }

            $previousOrdinal = (int) $row['drain_ordinal'];
            $previousToken = (string) $row['drain_token'];
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
