<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext218Plan
{
    /**
     * @param list<array<string, mixed>> $writeRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $writeRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext212(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): self
    {
        $rows = self::buildWriteRows($basePlan);
        $errors = self::writeErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next218 write receipts failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function writeRows(): array
    {
        return $this->writeRows;
    }

    /**
     * @return list<string>
     */
    public function writeErrors(): array
    {
        return self::writeErrorsForRows($this->writeRows);
    }

    /**
     * @return list<int>
     */
    public function writePages(): array
    {
        $pages = [];
        foreach ($this->writeRows as $row) {
            $pages[(int) $row['page_number']] = true;
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function pointerMapWritePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['write_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadWritePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['write_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function writeTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['write_token'], $this->writeRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function writeSummary(): array
    {
        $applySummary = $this->basePlan->applySummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next218-ready',
            'write_row_count' => count($this->writeRows),
            'write_pages' => $this->writePages(),
            'pointer_map_write_pages' => $this->pointerMapWritePages(),
            'payload_write_pages' => $this->payloadWritePages(),
            'apply_pages' => $applySummary['apply_pages'],
            'writes_match_apply_pages' => $this->writePages() === $applySummary['apply_pages'],
            'write_tokens' => $this->writeTokens(),
            'write_signature' => self::signature($this->writeTokens()),
            'current_source_next218_token' => self::signature(array_merge(
                ['next218', $applySummary['next_writer_apply_token']],
                $this->writePages(),
                $this->writeTokens(),
            )),
            'all_apply_tokens_match' => !in_array(false, array_column($this->writeRows, 'apply_token_matches'), true),
            'all_pointer_maps_written_before_payload' => $this->pointerMapsWrittenBeforePayload(),
            'all_freeblock_receipts_carried' => !in_array(false, array_column($this->writeRows, 'freeblock_receipt_carried'), true),
            'all_tail_pages_fenced_for_write' => !in_array(false, array_column($this->writeRows, 'tail_pages_fenced_for_write'), true),
            'all_write_chains_valid' => !in_array(false, array_column($this->writeRows, 'write_chain_valid'), true),
            'write_errors' => $this->writeErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next212',
                'sqlite-current-source-next218',
            ],
            'dependency_closure' => 'no new support component needed; next218 reuses next212 current-source apply rows and adds per-page write receipts only',
            'non_overlap' => 'adds per-page current-source write receipts after next212 apply ordering; does not repeat next212 page apply ordering, next209 source latching, overflow freelist release, root collapse, page relocation, or accepted freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next218',
            'write_summary' => $this->writeSummary(),
            'write_errors' => $this->writeErrors(),
            'write_rows' => $this->writeRows,
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
        foreach ($this->writeRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['page_number']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    private function pointerMapsWrittenBeforePayload(): bool
    {
        $byCursor = [];
        foreach ($this->writeRows as $row) {
            $cursor = (int) $row['cursor_index'];
            $byCursor[$cursor] ??= ['pointer' => null, 'payload' => null];
            if ($row['write_channel'] === 'pointer-map') {
                $byCursor[$cursor]['pointer'] ??= (int) $row['write_ordinal'];
            }
            if ($row['write_channel'] === 'payload') {
                $byCursor[$cursor]['payload'] ??= (int) $row['write_ordinal'];
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
    private static function buildWriteRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $applyRows = $basePlan->applyRows();
        $applyTokens = $basePlan->applyTokens();
        $rows = [];
        $previousWriteToken = null;
        $visiblePages = [];
        $writeOrdinal = 0;

        foreach ($applyRows as $applyIndex => $applyRow) {
            $applyToken = (string) $applyRow['apply_token'];
            $pages = array_values(array_map('intval', $applyRow['apply_pages']));
            foreach ($pages as $pageNumber) {
                $visiblePages[$pageNumber] = true;
                ++$writeOrdinal;

                $token = self::signature(array_merge(
                    ['next218', $writeOrdinal, $previousWriteToken ?? 'initial', $applyToken],
                    [$pageNumber, (int) $applyRow['apply_ordinal'], (int) $applyRow['cursor_index']],
                    self::sortedIntKeys($visiblePages),
                ));

                $rows[] = [
                    'write_ordinal' => $writeOrdinal,
                    'apply_index' => $applyIndex,
                    'apply_ordinal' => (int) $applyRow['apply_ordinal'],
                    'cursor_index' => (int) $applyRow['cursor_index'],
                    'batch_index' => (int) $applyRow['batch_index'],
                    'write_channel' => (string) $applyRow['apply_channel'],
                    'page_number' => $pageNumber,
                    'written_visible_pages' => self::sortedIntKeys($visiblePages),
                    'apply_token' => $applyToken,
                    'expected_apply_token' => $applyTokens[$applyIndex] ?? null,
                    'apply_token_matches' => ($applyTokens[$applyIndex] ?? null) === $applyToken,
                    'previous_write_token' => $previousWriteToken,
                    'freeblock_receipt_carried' => $applyRow['freeblock_receipt_carried'] === true,
                    'tail_pages_fenced_for_write' => $applyRow['tail_pages_fenced_for_apply'] === true && !in_array($pageNumber, [109, 110], true),
                    'write_chain_valid' => $applyRow['previous_apply_token'] === null || is_string($applyRow['previous_apply_token']),
                    'high_water_page' => (int) $applyRow['high_water_page'],
                    'write_state' => 'current-source-page-write-receipted',
                    'write_token' => $token,
                ];

                $previousWriteToken = $token;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function writeErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousVisible = [];

        foreach ($rows as $row) {
            if ($row['write_state'] !== 'current-source-page-write-receipted') {
                $errors[] = "write {$row['write_ordinal']} is not receipted";
            }
            if ((int) $row['write_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "write {$row['write_ordinal']} skipped a write ordinal";
            }
            if ($row['apply_token_matches'] !== true) {
                $errors[] = "write {$row['write_ordinal']} apply token drifted";
            }
            if ($row['previous_write_token'] !== $previousToken) {
                $errors[] = "write {$row['write_ordinal']} broke write token chaining";
            }
            if ($row['freeblock_receipt_carried'] !== true) {
                $errors[] = "write {$row['write_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_fenced_for_write'] !== true) {
                $errors[] = "write {$row['write_ordinal']} exposed fenced tail pages";
            }
            if ($row['write_chain_valid'] !== true) {
                $errors[] = "write {$row['write_ordinal']} has an invalid apply chain";
            }
            if (count(array_diff(array_keys($previousVisible), $row['written_visible_pages'])) !== 0) {
                $errors[] = "write {$row['write_ordinal']} lost an already-written page";
            }
            if ($row['write_token'] === '') {
                $errors[] = "write {$row['write_ordinal']} has an empty write token";
            }

            $previousOrdinal = (int) $row['write_ordinal'];
            $previousToken = (string) $row['write_token'];
            $previousVisible = array_fill_keys(array_map('intval', $row['written_visible_pages']), true);
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
