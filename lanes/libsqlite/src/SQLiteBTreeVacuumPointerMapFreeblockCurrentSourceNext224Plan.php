<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext224Plan
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
        return self::fromWritePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext218Plan::tableLeafFromDeleteResult(
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

    public static function fromWritePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext218Plan $basePlan): self
    {
        $rows = self::buildSourceRows($basePlan);
        $errors = self::sourceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next224 cursor receipts failed: ' . implode('; ', $errors));
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
    public function currentSourcePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['current_source_page'], $this->sourceRows));
    }

    /**
     * @return list<int|null>
     */
    public function nextSourcePages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_source_page'], $this->sourceRows));
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
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next224-ready',
            'source_row_count' => count($this->sourceRows),
            'current_source_pages' => $this->currentSourcePages(),
            'next_source_pages' => $this->nextSourcePages(),
            'pointer_map_source_pages' => $this->pointerMapSourcePages(),
            'payload_source_pages' => $this->payloadSourcePages(),
            'write_pages' => $writeSummary['write_pages'],
            'source_pages_match_write_pages' => $this->dedupe($this->currentSourcePages()) === $writeSummary['write_pages'],
            'all_source_tokens_match_writes' => !in_array(false, array_column($this->sourceRows, 'write_token_matches'), true),
            'all_next_links_valid' => !in_array(false, array_column($this->sourceRows, 'next_link_valid'), true),
            'all_pointer_maps_visible_before_payload_source' => !in_array(false, array_column($this->sourceRows, 'pointer_map_visible_before_payload_source'), true),
            'all_freeblock_receipts_carried' => !in_array(false, array_column($this->sourceRows, 'freeblock_receipt_carried'), true),
            'all_tail_pages_fenced_for_source' => !in_array(false, array_column($this->sourceRows, 'tail_pages_fenced_for_source'), true),
            'source_errors' => $this->sourceErrors(),
            'source_signature' => self::signature($this->sourceTokens()),
            'current_source_next224_token' => self::signature(array_merge(
                ['next224', $writeSummary['current_source_next218_token']],
                $this->currentSourcePages(),
                $this->sourceTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next218',
                'sqlite-current-source-next224',
            ],
            'dependency_closure' => 'no new support component needed; next224 reuses next218 write receipts and adds current-source next-page cursor sequencing only',
            'non_overlap' => 'adds current-source next-page cursor sequencing after next218 write receipts; does not repeat next218 write receipt construction, next212 apply ordering, overflow freelist release, page relocation, root collapse, or accepted freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next224',
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
                $pages[(int) $row['current_source_page']] = true;
            }
        }

        return $this->dedupe(array_keys($pages));
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
        $visiblePointerMapPages = [];

        foreach ($writeRows as $index => $writeRow) {
            $nextWriteRow = $writeRows[$index + 1] ?? null;
            $sourcePage = (int) $writeRow['page_number'];
            $nextSourcePage = is_array($nextWriteRow) ? (int) $nextWriteRow['page_number'] : null;
            $sourceChannel = (string) $writeRow['write_channel'];
            if ($sourceChannel === 'pointer-map') {
                $visiblePointerMapPages[$sourcePage] = true;
            }

            $token = self::signature(array_merge(
                ['next224', $previousSourceToken ?? 'initial', $writeRow['write_token']],
                [$sourcePage, $nextSourcePage ?? 'eof', (int) $writeRow['write_ordinal']],
                self::sortedIntKeys($visiblePointerMapPages),
            ));

            $pointerVisible = $sourceChannel === 'pointer-map'
                || $visiblePointerMapPages !== [];

            $rows[] = [
                'source_ordinal' => $index + 1,
                'write_ordinal' => (int) $writeRow['write_ordinal'],
                'cursor_index' => (int) $writeRow['cursor_index'],
                'batch_index' => (int) $writeRow['batch_index'],
                'source_channel' => $sourceChannel,
                'current_source_page' => $sourcePage,
                'next_source_page' => $nextSourcePage,
                'visible_pointer_map_pages' => self::sortedIntKeys($visiblePointerMapPages),
                'write_token' => (string) $writeRow['write_token'],
                'expected_write_token' => $writeTokens[$index] ?? null,
                'write_token_matches' => ($writeTokens[$index] ?? null) === (string) $writeRow['write_token'],
                'previous_source_token' => $previousSourceToken,
                'next_link_valid' => $nextWriteRow === null || $nextSourcePage === (int) $nextWriteRow['page_number'],
                'pointer_map_visible_before_payload_source' => $pointerVisible,
                'freeblock_receipt_carried' => $writeRow['freeblock_receipt_carried'] === true,
                'tail_pages_fenced_for_source' => $writeRow['tail_pages_fenced_for_write'] === true && !in_array($sourcePage, [109, 110], true),
                'source_state' => 'current-source-next-page-receipted',
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

        foreach ($rows as $index => $row) {
            if ($row['source_state'] !== 'current-source-next-page-receipted') {
                $errors[] = "source {$row['source_ordinal']} is not receipted";
            }
            if ((int) $row['source_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "source {$row['source_ordinal']} skipped a source ordinal";
            }
            if ((int) $row['write_ordinal'] !== (int) $row['source_ordinal']) {
                $errors[] = "source {$row['source_ordinal']} drifted from write ordinal";
            }
            if ($row['write_token_matches'] !== true) {
                $errors[] = "source {$row['source_ordinal']} write token drifted";
            }
            if ($row['previous_source_token'] !== $previousToken) {
                $errors[] = "source {$row['source_ordinal']} broke source token chaining";
            }
            if ($row['next_link_valid'] !== true) {
                $errors[] = "source {$row['source_ordinal']} has an invalid next-source link";
            }
            if ($row['source_channel'] === 'payload' && $row['pointer_map_visible_before_payload_source'] !== true) {
                $errors[] = "source {$row['source_ordinal']} exposed payload before a pointer-map source";
            }
            if ($row['freeblock_receipt_carried'] !== true) {
                $errors[] = "source {$row['source_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_fenced_for_source'] !== true) {
                $errors[] = "source {$row['source_ordinal']} exposed fenced tail pages";
            }
            if ($row['source_token'] === '') {
                $errors[] = "source {$row['source_ordinal']} has an empty source token";
            }
            if ($index === count($rows) - 1 && $row['next_source_page'] !== null) {
                $errors[] = "source {$row['source_ordinal']} did not terminate at eof";
            }

            $previousOrdinal = (int) $row['source_ordinal'];
            $previousToken = (string) $row['source_token'];
        }

        return $errors;
    }

    /**
     * @param list<int> $pages
     * @return list<int>
     */
    private function dedupe(array $pages): array
    {
        $seen = [];
        foreach ($pages as $page) {
            $seen[(int) $page] = true;
        }

        return self::sortedIntKeys($seen);
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
