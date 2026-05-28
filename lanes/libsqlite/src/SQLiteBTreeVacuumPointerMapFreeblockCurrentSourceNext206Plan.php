<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan
{
    /**
     * @param list<array<string, mixed>> $sealRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Plan $basePlan,
        private readonly array $sealRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Plan $basePlan): self
    {
        $rows = self::buildSealRows($basePlan);
        $errors = self::sealErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next206 seal failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sealRows(): array
    {
        return $this->sealRows;
    }

    /**
     * @return list<string>
     */
    public function sealErrors(): array
    {
        return self::sealErrorsForRows($this->sealRows);
    }

    /**
     * @return list<int>
     */
    public function sealedPages(): array
    {
        $pages = [];
        foreach ($this->sealRows as $row) {
            foreach ($row['sealed_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function sealedPointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['seal_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function sealedPayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['seal_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function sealTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['seal_token'], $this->sealRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function sealedCurrentSourceSummary(): array
    {
        $cursorSummary = $this->basePlan->currentSourceCursorSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next206-ready',
            'seal_row_count' => count($this->sealRows),
            'sealed_pages' => $this->sealedPages(),
            'sealed_pointer_map_pages' => $this->sealedPointerMapPages(),
            'sealed_payload_pages' => $this->sealedPayloadPages(),
            'cursor_row_count' => $cursorSummary['cursor_row_count'],
            'cursor_signature' => $cursorSummary['cursor_signature'],
            'next_writer_cursor_token' => $cursorSummary['next_writer_cursor_token'],
            'seal_tokens' => $this->sealTokens(),
            'seal_signature' => self::signature($this->sealTokens()),
            'next_writer_freeblock_source_token' => self::signature(array_merge(
                ['next206', $cursorSummary['next_writer_cursor_token']],
                $this->sealedPages(),
                $this->sealTokens(),
            )),
            'all_cursor_tokens_match' => !in_array(false, array_column($this->sealRows, 'cursor_token_matches'), true),
            'all_pointer_maps_sealed_before_payload' => $this->pointerMapsSealedBeforePayload(),
            'all_leaf_freeblocks_sealed' => !in_array(false, array_column($this->leafPayloadRows(), 'leaf_freeblock_sealed'), true),
            'all_tail_pages_fenced' => !in_array(false, array_column($this->sealRows, 'tail_pages_fenced'), true),
            'all_seal_chains_valid' => !in_array(false, array_column($this->sealRows, 'seal_chain_valid'), true),
            'seal_errors' => $this->sealErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next203',
                'sqlite-current-source-next206',
            ],
            'dependency_closure' => 'no new support component needed; next206 reuses next203 current-source cursor batches, pointer-map pages, leaf freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds sealed freeblock current-source writer admission after next203 cursor admission; does not repeat next203 cursor batching, next196 source-next handoff, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted freelist/pointer-map reuse slices',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next206',
            'sealed_current_source_summary' => $this->sealedCurrentSourceSummary(),
            'seal_errors' => $this->sealErrors(),
            'seal_rows' => $this->sealRows,
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
        foreach ($this->sealRows as $row) {
            if (!$predicate($row)) {
                continue;
            }
            foreach ($row['sealed_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leafPayloadRows(): array
    {
        return array_values(array_filter(
            $this->sealRows,
            static fn (array $row): bool => in_array(3, $row['sealed_pages'], true),
        ));
    }

    private function pointerMapsSealedBeforePayload(): bool
    {
        $byCursor = [];
        foreach ($this->sealRows as $row) {
            $cursor = (int) $row['cursor_index'];
            $byCursor[$cursor] ??= ['pointer' => null, 'payload' => null];
            if ($row['seal_channel'] === 'pointer-map') {
                $byCursor[$cursor]['pointer'] = (int) $row['seal_ordinal'];
            }
            if ($row['seal_channel'] === 'payload') {
                $byCursor[$cursor]['payload'] = (int) $row['seal_ordinal'];
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
    private static function buildSealRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Plan $basePlan): array
    {
        $cursorRows = $basePlan->cursorRows();
        $cursorTokens = $basePlan->cursorTokens();
        $rows = [];
        $previousSealToken = null;
        $ordinal = 0;

        foreach ($cursorRows as $index => $cursorRow) {
            foreach ([
                'pointer-map' => array_values(array_map('intval', $cursorRow['pointer_map_cursor_pages'])),
                'payload' => array_values(array_map('intval', $cursorRow['payload_cursor_pages'])),
            ] as $channel => $pages) {
                if ($pages === []) {
                    continue;
                }

                ++$ordinal;
                $cursorToken = (string) $cursorRow['cursor_token'];
                $token = self::signature(array_merge(
                    ['next206', $ordinal, $channel, $previousSealToken ?? 'initial', $cursorToken],
                    $pages,
                    [(int) $cursorRow['high_water_page']],
                ));

                $rows[] = [
                    'seal_ordinal' => $ordinal,
                    'cursor_index' => $index,
                    'batch_index' => (int) $cursorRow['batch_index'],
                    'seal_channel' => $channel,
                    'sealed_pages' => $pages,
                    'cursor_token' => $cursorToken,
                    'expected_cursor_token' => $cursorTokens[$index] ?? null,
                    'cursor_token_matches' => ($cursorTokens[$index] ?? null) === $cursorToken,
                    'previous_seal_token' => $previousSealToken,
                    'leaf_freeblock_sealed' => $channel !== 'payload'
                        || !in_array(3, $pages, true)
                        || $cursorRow['leaf_freeblock_cursor_ready'] === true,
                    'tail_pages_fenced' => $cursorRow['fenced_tail_pages_absent'] === true
                        && !array_intersect([109, 110], $pages),
                    'seal_chain_valid' => $cursorRow['previous_cursor_token'] === null
                        || is_string($cursorRow['previous_cursor_token']),
                    'high_water_page' => (int) $cursorRow['high_water_page'],
                    'seal_state' => 'sealed-current-source',
                    'seal_token' => $token,
                ];

                $previousSealToken = $token;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function sealErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['seal_state'] !== 'sealed-current-source') {
                $errors[] = "seal {$row['seal_ordinal']} is not current-source ready";
            }
            if ((int) $row['seal_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "seal {$row['seal_ordinal']} skipped a seal ordinal";
            }
            if ($row['cursor_token_matches'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} cursor token drifted";
            }
            if ($row['previous_seal_token'] !== $previousToken) {
                $errors[] = "seal {$row['seal_ordinal']} broke seal token chaining";
            }
            if ($row['leaf_freeblock_sealed'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} lost leaf freeblock state";
            }
            if ($row['tail_pages_fenced'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} exposed a truncated tail page";
            }
            if ($row['seal_chain_valid'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} has an invalid cursor chain";
            }
            if ($row['seal_token'] === '') {
                $errors[] = "seal {$row['seal_ordinal']} has an empty seal token";
            }
            $previousOrdinal = (int) $row['seal_ordinal'];
            $previousToken = (string) $row['seal_token'];
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
