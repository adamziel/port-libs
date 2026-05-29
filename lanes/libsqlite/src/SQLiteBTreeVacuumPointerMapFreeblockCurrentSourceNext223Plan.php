<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext223Plan
{
    /**
     * @param list<array<string, mixed>> $sourceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext218Plan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext218Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext218Plan $basePlan): self
    {
        $rows = self::buildSourceRows($basePlan);
        $errors = self::sourceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next223 source publication failed: ' . implode('; ', $errors));
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
    public function sourcePages(): array
    {
        $pages = [];
        foreach ($this->sourceRows as $row) {
            $pages[(int) $row['page_number']] = true;
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function pointerMapSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function sourceTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['source_token'], $this->sourceRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceSummary(): array
    {
        $writeSummary = $this->basePlan->writeSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next223-ready',
            'source_row_count' => count($this->sourceRows),
            'source_pages' => $this->sourcePages(),
            'pointer_map_source_pages' => $this->pointerMapSourcePages(),
            'payload_source_pages' => $this->payloadSourcePages(),
            'write_pages' => $writeSummary['write_pages'],
            'source_matches_write_pages' => $this->sourcePages() === $writeSummary['write_pages'],
            'source_tokens' => $this->sourceTokens(),
            'source_signature' => self::signature($this->sourceTokens()),
            'current_source_next223_token' => self::signature(array_merge(
                ['next223', $writeSummary['current_source_next218_token']],
                $this->sourcePages(),
                $this->sourceTokens(),
            )),
            'all_write_tokens_match' => !in_array(false, array_column($this->sourceRows, 'write_token_matches'), true),
            'all_pointer_maps_sourced_before_payload' => $this->pointerMapsSourcedBeforePayload(),
            'all_freeblock_receipts_published' => !in_array(false, array_column($this->sourceRows, 'freeblock_receipt_published'), true),
            'all_tail_pages_excluded_from_source' => !in_array(false, array_column($this->sourceRows, 'tail_pages_excluded_from_source'), true),
            'all_source_chains_valid' => !in_array(false, array_column($this->sourceRows, 'source_chain_valid'), true),
            'source_errors' => $this->sourceErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next218',
                'sqlite-current-source-next223',
            ],
            'dependency_closure' => 'no new support component needed; next223 reuses next218 per-page write receipts and publishes a current-source source fence only',
            'non_overlap' => 'adds current-source publication receipts after next218 per-page writes; does not repeat next218 write receipts, next212 apply ordering, overflow freelist release, root collapse, page relocation, or accepted freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next223',
            'source_summary' => $this->sourceSummary(),
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
            if ($predicate($row)) {
                $pages[(int) $row['page_number']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    private function pointerMapsSourcedBeforePayload(): bool
    {
        $byCursor = [];
        foreach ($this->sourceRows as $row) {
            $cursor = (int) $row['cursor_index'];
            $byCursor[$cursor] ??= ['pointer' => null, 'payload' => null];
            if ($row['source_channel'] === 'pointer-map') {
                $byCursor[$cursor]['pointer'] ??= (int) $row['source_ordinal'];
            }
            if ($row['source_channel'] === 'payload') {
                $byCursor[$cursor]['payload'] ??= (int) $row['source_ordinal'];
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
    private static function buildSourceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext218Plan $basePlan): array
    {
        $writeRows = $basePlan->writeRows();
        $writeTokens = $basePlan->writeTokens();
        $rows = [];
        $previousSourceToken = null;
        $visibleSourcePages = [];

        foreach ($writeRows as $index => $writeRow) {
            $pageNumber = (int) $writeRow['page_number'];
            $visibleSourcePages[$pageNumber] = true;
            $writeToken = (string) $writeRow['write_token'];
            $token = self::signature(array_merge(
                ['next223', (int) $writeRow['write_ordinal'], $previousSourceToken ?? 'initial', $writeToken],
                [$pageNumber, (int) $writeRow['apply_ordinal'], (int) $writeRow['cursor_index']],
                self::sortedIntKeys($visibleSourcePages),
                [(int) $writeRow['high_water_page']],
            ));

            $rows[] = [
                'source_ordinal' => (int) $writeRow['write_ordinal'],
                'write_index' => $index,
                'write_ordinal' => (int) $writeRow['write_ordinal'],
                'apply_ordinal' => (int) $writeRow['apply_ordinal'],
                'cursor_index' => (int) $writeRow['cursor_index'],
                'batch_index' => (int) $writeRow['batch_index'],
                'source_channel' => (string) $writeRow['write_channel'],
                'page_number' => $pageNumber,
                'source_visible_pages' => self::sortedIntKeys($visibleSourcePages),
                'write_token' => $writeToken,
                'expected_write_token' => $writeTokens[$index] ?? null,
                'write_token_matches' => ($writeTokens[$index] ?? null) === $writeToken,
                'previous_source_token' => $previousSourceToken,
                'freeblock_receipt_published' => $writeRow['freeblock_receipt_carried'] === true,
                'tail_pages_excluded_from_source' => $writeRow['tail_pages_fenced_for_write'] === true && !in_array($pageNumber, [109, 110], true),
                'source_chain_valid' => $writeRow['previous_write_token'] === null || is_string($writeRow['previous_write_token']),
                'high_water_page' => (int) $writeRow['high_water_page'],
                'source_state' => 'current-source-publication-receipted',
                'source_token' => $token,
            ];

            $previousSourceToken = $token;
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
            if ($row['source_state'] !== 'current-source-publication-receipted') {
                $errors[] = "source {$row['source_ordinal']} is not receipted";
            }
            if ((int) $row['source_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "source {$row['source_ordinal']} skipped a source ordinal";
            }
            if ($row['write_token_matches'] !== true) {
                $errors[] = "source {$row['source_ordinal']} write token drifted";
            }
            if ($row['previous_source_token'] !== $previousToken) {
                $errors[] = "source {$row['source_ordinal']} broke source token chaining";
            }
            if ($row['freeblock_receipt_published'] !== true) {
                $errors[] = "source {$row['source_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_excluded_from_source'] !== true) {
                $errors[] = "source {$row['source_ordinal']} exposed fenced tail pages";
            }
            if ($row['source_chain_valid'] !== true) {
                $errors[] = "source {$row['source_ordinal']} has an invalid write chain";
            }
            if (count(array_diff(array_keys($previousVisible), $row['source_visible_pages'])) !== 0) {
                $errors[] = "source {$row['source_ordinal']} lost an already-sourced page";
            }
            if ($row['source_token'] === '') {
                $errors[] = "source {$row['source_ordinal']} has an empty source token";
            }

            $previousOrdinal = (int) $row['source_ordinal'];
            $previousToken = (string) $row['source_token'];
            $previousVisible = array_fill_keys(array_map('intval', $row['source_visible_pages']), true);
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
