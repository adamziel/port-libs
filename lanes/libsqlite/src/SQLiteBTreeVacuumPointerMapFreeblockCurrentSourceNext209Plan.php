<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext209Plan
{
    /**
     * @param list<array<string, mixed>> $sourceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan $basePlan,
        private readonly array $sourceRows,
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
        $rows = self::buildSourceRows($basePlan);
        $errors = self::sourceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next209 writer source failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceRows(): array
    {
        return $this->sourceRows;
    }

    /**
     * @return list<string>
     */
    public function sourceErrors(): array
    {
        return self::sourceErrorsForRows($this->sourceRows);
    }

    /**
     * @return list<int>
     */
    public function writerSourcePages(): array
    {
        $pages = [];
        foreach ($this->sourceRows as $row) {
            foreach ($row['writer_source_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function writerPointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function writerPayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function sourceTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['writer_source_token'], $this->sourceRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function writerSourceSummary(): array
    {
        $sealedSummary = $this->basePlan->sealedCurrentSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next209-ready',
            'source_row_count' => count($this->sourceRows),
            'writer_source_pages' => $this->writerSourcePages(),
            'writer_pointer_map_pages' => $this->writerPointerMapPages(),
            'writer_payload_pages' => $this->writerPayloadPages(),
            'sealed_pages' => $sealedSummary['sealed_pages'],
            'source_matches_sealed_pages' => $this->writerSourcePages() === $sealedSummary['sealed_pages'],
            'source_tokens' => $this->sourceTokens(),
            'source_signature' => self::signature($this->sourceTokens()),
            'next_writer_current_source_token' => self::signature(array_merge(
                ['next209', $sealedSummary['next_writer_freeblock_source_token']],
                $this->writerSourcePages(),
                $this->sourceTokens(),
            )),
            'all_seal_tokens_match' => !in_array(false, array_column($this->sourceRows, 'seal_token_matches'), true),
            'all_pointer_map_sources_before_payload' => $this->pointerMapsBeforePayloadSources(),
            'all_leaf_freeblock_sources_ready' => !in_array(false, array_column($this->sourceRows, 'leaf_freeblock_source_ready'), true),
            'all_tail_pages_remain_fenced' => !in_array(false, array_column($this->sourceRows, 'tail_pages_remain_fenced'), true),
            'all_writer_source_chains_valid' => !in_array(false, array_column($this->sourceRows, 'writer_source_chain_valid'), true),
            'source_errors' => $this->sourceErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next206',
                'sqlite-current-source-next209',
            ],
            'dependency_closure' => 'no new support component needed; next209 reuses next206 sealed current-source rows, leaf freeblock receipts, pointer-map pages, and fenced-tail metadata',
            'non_overlap' => 'adds writer-source latch admission after next206 sealed current-source rows; does not repeat next206 sealing, next203 cursor batching, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted freelist/pointer-map reuse slices',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next209',
            'writer_source_summary' => $this->writerSourceSummary(),
            'source_errors' => $this->sourceErrors(),
            'source_rows' => $this->sourceRows,
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
        foreach ($this->sourceRows as $row) {
            if (!$predicate($row)) {
                continue;
            }
            foreach ($row['writer_source_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    private function pointerMapsBeforePayloadSources(): bool
    {
        $byCursor = [];
        foreach ($this->sourceRows as $row) {
            $cursor = (int) $row['cursor_index'];
            $byCursor[$cursor] ??= ['pointer' => null, 'payload' => null];
            if ($row['source_channel'] === 'pointer-map') {
                $byCursor[$cursor]['pointer'] = (int) $row['source_ordinal'];
            }
            if ($row['source_channel'] === 'payload') {
                $byCursor[$cursor]['payload'] = (int) $row['source_ordinal'];
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
    private static function buildSourceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Plan $basePlan): array
    {
        $sealRows = $basePlan->sealRows();
        $sealTokens = $basePlan->sealTokens();
        $rows = [];
        $previousToken = null;
        $visiblePages = [];

        foreach ($sealRows as $index => $row) {
            $pages = array_values(array_map('intval', $row['sealed_pages']));
            foreach ($pages as $pageNumber) {
                $visiblePages[$pageNumber] = true;
            }

            $sealToken = (string) $row['seal_token'];
            $token = self::signature(array_merge(
                ['next209', (int) $row['seal_ordinal'], $previousToken ?? 'initial', $sealToken],
                $pages,
                self::sortedIntKeys($visiblePages),
                [(int) $row['high_water_page']],
            ));

            $rows[] = [
                'source_ordinal' => (int) $row['seal_ordinal'],
                'seal_index' => $index,
                'cursor_index' => (int) $row['cursor_index'],
                'batch_index' => (int) $row['batch_index'],
                'source_channel' => (string) $row['seal_channel'],
                'writer_source_pages' => $pages,
                'writer_visible_pages' => self::sortedIntKeys($visiblePages),
                'seal_token' => $sealToken,
                'expected_seal_token' => $sealTokens[$index] ?? null,
                'seal_token_matches' => ($sealTokens[$index] ?? null) === $sealToken,
                'previous_writer_source_token' => $previousToken,
                'leaf_freeblock_source_ready' => $row['leaf_freeblock_sealed'] === true,
                'tail_pages_remain_fenced' => $row['tail_pages_fenced'] === true && !array_intersect([109, 110], $pages),
                'writer_source_chain_valid' => $row['previous_seal_token'] === null || is_string($row['previous_seal_token']),
                'high_water_page' => (int) $row['high_water_page'],
                'writer_source_state' => 'current-source-writer-ready',
                'writer_source_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function sourceErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousVisible = [];

        foreach ($rows as $row) {
            if ($row['writer_source_state'] !== 'current-source-writer-ready') {
                $errors[] = "source {$row['source_ordinal']} is not writer-ready";
            }
            if ((int) $row['source_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "source {$row['source_ordinal']} skipped a writer source ordinal";
            }
            if ($row['seal_token_matches'] !== true) {
                $errors[] = "source {$row['source_ordinal']} seal token drifted";
            }
            if ($row['previous_writer_source_token'] !== $previousToken) {
                $errors[] = "source {$row['source_ordinal']} broke writer source chaining";
            }
            if ($row['leaf_freeblock_source_ready'] !== true) {
                $errors[] = "source {$row['source_ordinal']} lost leaf freeblock source readiness";
            }
            if ($row['tail_pages_remain_fenced'] !== true) {
                $errors[] = "source {$row['source_ordinal']} exposed fenced tail pages";
            }
            if ($row['writer_source_chain_valid'] !== true) {
                $errors[] = "source {$row['source_ordinal']} has an invalid seal chain";
            }
            if (count(array_diff(array_keys($previousVisible), $row['writer_visible_pages'])) !== 0) {
                $errors[] = "source {$row['source_ordinal']} lost a visible source page";
            }
            if ($row['writer_source_token'] === '') {
                $errors[] = "source {$row['source_ordinal']} has an empty writer source token";
            }

            $previousOrdinal = (int) $row['source_ordinal'];
            $previousToken = (string) $row['writer_source_token'];
            $previousVisible = array_fill_keys(array_map('intval', $row['writer_visible_pages']), true);
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
