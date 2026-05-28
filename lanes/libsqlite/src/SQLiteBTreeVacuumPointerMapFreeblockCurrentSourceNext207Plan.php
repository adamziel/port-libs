<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext207Plan
{
    /**
     * @param list<array<string, mixed>> $windowRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan $basePlan,
        private readonly array $windowRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan $basePlan): self
    {
        $rows = self::buildWindowRows($basePlan);
        $errors = self::windowErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next207 writer window failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function writerWindowRows(): array
    {
        return $this->windowRows;
    }

    /**
     * @return list<string>
     */
    public function writerWindowErrors(): array
    {
        return self::windowErrorsForRows($this->windowRows);
    }

    /**
     * @return list<int>
     */
    public function admittedWriterPages(): array
    {
        $pages = [];
        foreach ($this->windowRows as $row) {
            foreach ($row['writer_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function admittedPointerMapWriterPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['writer_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function admittedPayloadWriterPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['writer_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function writerWindowTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['writer_window_token'], $this->windowRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function writerWindowSummary(): array
    {
        $sealedSummary = $this->basePlan->sealedCurrentSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next207-ready',
            'writer_window_row_count' => count($this->windowRows),
            'admitted_writer_pages' => $this->admittedWriterPages(),
            'admitted_pointer_map_writer_pages' => $this->admittedPointerMapWriterPages(),
            'admitted_payload_writer_pages' => $this->admittedPayloadWriterPages(),
            'sealed_pages' => $sealedSummary['sealed_pages'],
            'seal_signature' => $sealedSummary['seal_signature'],
            'writer_window_tokens' => $this->writerWindowTokens(),
            'writer_window_signature' => self::signature($this->writerWindowTokens()),
            'next_writer_current_source_token' => self::signature(array_merge(
                ['next207', $sealedSummary['next_writer_freeblock_source_token']],
                $this->admittedWriterPages(),
                $this->writerWindowTokens(),
            )),
            'all_seal_tokens_match' => !in_array(false, array_column($this->windowRows, 'seal_token_matches'), true),
            'all_pointer_maps_admitted_before_payload' => $this->pointerMapsAdmittedBeforePayload(),
            'all_leaf_freeblocks_admitted' => !in_array(false, array_column($this->leafPayloadRows(), 'leaf_freeblock_admitted'), true),
            'all_tail_pages_fenced' => !in_array(false, array_column($this->windowRows, 'tail_pages_fenced'), true),
            'all_window_chains_valid' => !in_array(false, array_column($this->windowRows, 'writer_chain_valid'), true),
            'writer_window_errors' => $this->writerWindowErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next206',
                'sqlite-current-source-next207',
            ],
            'dependency_closure' => 'no new support component needed; next207 reuses next206 sealed pointer-map and payload/freeblock rows to admit a deterministic writer window',
            'non_overlap' => 'adds final current-source writer-window admission after next206 sealing; does not repeat next206 seal rows, next203 cursor batching, next196 source-next handoff, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted freelist/pointer-map reuse slices',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next207',
            'writer_window_summary' => $this->writerWindowSummary(),
            'writer_window_errors' => $this->writerWindowErrors(),
            'writer_window_rows' => $this->windowRows,
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
        foreach ($this->windowRows as $row) {
            if (!$predicate($row)) {
                continue;
            }
            foreach ($row['writer_pages'] as $pageNumber) {
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
            $this->windowRows,
            static fn (array $row): bool => in_array(3, $row['writer_pages'], true),
        ));
    }

    private function pointerMapsAdmittedBeforePayload(): bool
    {
        $byCursor = [];
        foreach ($this->windowRows as $row) {
            $cursor = (int) $row['cursor_index'];
            $byCursor[$cursor] ??= ['pointer' => null, 'payload' => null];
            if ($row['writer_channel'] === 'pointer-map') {
                $byCursor[$cursor]['pointer'] = (int) $row['writer_ordinal'];
            }
            if ($row['writer_channel'] === 'payload') {
                $byCursor[$cursor]['payload'] = (int) $row['writer_ordinal'];
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
    private static function buildWindowRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan $basePlan): array
    {
        $sealRows = $basePlan->sealRows();
        $sealTokens = $basePlan->sealTokens();
        $rows = [];
        $previousWindowToken = null;
        $ordinal = 0;
        $admittedPages = [];

        foreach ($sealRows as $index => $sealRow) {
            ++$ordinal;
            $pages = array_values(array_map('intval', $sealRow['sealed_pages']));
            foreach ($pages as $pageNumber) {
                $admittedPages[$pageNumber] = true;
            }

            $sealToken = (string) $sealRow['seal_token'];
            $token = self::signature(array_merge(
                ['next207', $ordinal, $previousWindowToken ?? 'initial', $sealToken],
                $pages,
                self::sortedIntKeys($admittedPages),
                [(int) $sealRow['high_water_page']],
            ));

            $rows[] = [
                'writer_ordinal' => $ordinal,
                'seal_ordinal' => (int) $sealRow['seal_ordinal'],
                'cursor_index' => (int) $sealRow['cursor_index'],
                'batch_index' => (int) $sealRow['batch_index'],
                'writer_channel' => (string) $sealRow['seal_channel'],
                'writer_pages' => $pages,
                'admitted_pages_after_window' => self::sortedIntKeys($admittedPages),
                'seal_token' => $sealToken,
                'expected_seal_token' => $sealTokens[$index] ?? null,
                'seal_token_matches' => ($sealTokens[$index] ?? null) === $sealToken,
                'previous_writer_window_token' => $previousWindowToken,
                'leaf_freeblock_admitted' => $sealRow['leaf_freeblock_sealed'] === true,
                'tail_pages_fenced' => $sealRow['tail_pages_fenced'] === true
                    && !array_intersect([109, 110], $pages),
                'writer_chain_valid' => $sealRow['previous_seal_token'] === null
                    || is_string($sealRow['previous_seal_token']),
                'high_water_page' => (int) $sealRow['high_water_page'],
                'writer_window_state' => 'current-source-writer-window-ready',
                'writer_window_token' => $token,
            ];

            $previousWindowToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function windowErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $seenPages = [];

        foreach ($rows as $row) {
            if ($row['writer_window_state'] !== 'current-source-writer-window-ready') {
                $errors[] = "writer window {$row['writer_ordinal']} is not current-source ready";
            }
            if ((int) $row['writer_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "writer window {$row['writer_ordinal']} skipped a writer ordinal";
            }
            if ((int) $row['seal_ordinal'] !== (int) $row['writer_ordinal']) {
                $errors[] = "writer window {$row['writer_ordinal']} drifted from its seal ordinal";
            }
            if ($row['seal_token_matches'] !== true) {
                $errors[] = "writer window {$row['writer_ordinal']} seal token drifted";
            }
            if ($row['previous_writer_window_token'] !== $previousToken) {
                $errors[] = "writer window {$row['writer_ordinal']} broke token chaining";
            }
            foreach ($row['writer_pages'] as $pageNumber) {
                $seenPages[(int) $pageNumber] = true;
            }
            if ($row['admitted_pages_after_window'] !== self::sortedIntKeys($seenPages)) {
                $errors[] = "writer window {$row['writer_ordinal']} has a stale admitted page set";
            }
            if ($row['leaf_freeblock_admitted'] !== true) {
                $errors[] = "writer window {$row['writer_ordinal']} lost leaf freeblock state";
            }
            if ($row['tail_pages_fenced'] !== true) {
                $errors[] = "writer window {$row['writer_ordinal']} exposed a truncated tail page";
            }
            if ($row['writer_chain_valid'] !== true) {
                $errors[] = "writer window {$row['writer_ordinal']} has an invalid seal chain";
            }
            if ($row['writer_window_token'] === '') {
                $errors[] = "writer window {$row['writer_ordinal']} has an empty token";
            }
            $previousOrdinal = (int) $row['writer_ordinal'];
            $previousToken = (string) $row['writer_window_token'];
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
