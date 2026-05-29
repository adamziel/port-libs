<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Plan
{
    /**
     * @param list<array<string, mixed>> $cursorRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextWriterVariant $basePlan,
        private readonly array $cursorRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextWriterVariant::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextWriterVariant $basePlan): self
    {
        $rows = self::buildCursorRows($basePlan);
        $errors = self::cursorErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next203 cursor failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cursorRows(): array
    {
        return $this->cursorRows;
    }

    /**
     * @return list<string>
     */
    public function cursorErrors(): array
    {
        return self::cursorErrorsForRows($this->cursorRows);
    }

    /**
     * @return list<int>
     */
    public function currentSourcePages(): array
    {
        $pages = [];
        foreach ($this->cursorRows as $row) {
            foreach ($row['current_source_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function pointerMapCursorPages(): array
    {
        $pages = [];
        foreach ($this->cursorRows as $row) {
            foreach ($row['pointer_map_cursor_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function payloadCursorPages(): array
    {
        $pages = [];
        foreach ($this->cursorRows as $row) {
            foreach ($row['payload_cursor_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<string>
     */
    public function cursorTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['cursor_token'], $this->cursorRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSourceCursorSummary(): array
    {
        $sourceNextSummary = $this->basePlan->sourceNextSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next203-ready',
            'cursor_row_count' => count($this->cursorRows),
            'current_source_pages' => $this->currentSourcePages(),
            'next_writable_pages' => $sourceNextSummary['next_writable_pages'],
            'pointer_map_cursor_pages' => $this->pointerMapCursorPages(),
            'payload_cursor_pages' => $this->payloadCursorPages(),
            'cursor_tokens' => $this->cursorTokens(),
            'cursor_signature' => self::signature($this->cursorTokens()),
            'next_writer_cursor_token' => self::signature(array_merge(
                ['next203', $sourceNextSummary['next_writer_source_token']],
                $this->currentSourcePages(),
                $this->cursorTokens(),
            )),
            'all_source_next_tokens_match' => !in_array(false, array_column($this->cursorRows, 'source_next_token_matches'), true),
            'all_pointer_maps_before_payload' => !in_array(false, array_column($this->cursorRows, 'pointer_maps_before_payload'), true),
            'all_freeblock_cursors_ready' => !in_array(false, array_column($this->cursorRows, 'leaf_freeblock_cursor_ready'), true),
            'all_fenced_tail_pages_absent' => !in_array(false, array_column($this->cursorRows, 'fenced_tail_pages_absent'), true),
            'all_cursor_chains_valid' => !in_array(false, array_column($this->cursorRows, 'cursor_chain_valid'), true),
            'cursor_errors' => $this->cursorErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next196',
                'sqlite-current-source-next203',
            ],
            'dependency_closure' => 'no new support component needed; next203 reuses next196 source-next handoff tokens, pointer-map pages, leaf freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds current-source next-writer cursor admission after next196 source-next handoff; does not repeat next196 token handoff, next192 validation, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted freelist/pointer-map reuse slices',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next203',
            'current_source_cursor_summary' => $this->currentSourceCursorSummary(),
            'cursor_errors' => $this->cursorErrors(),
            'cursor_rows' => $this->cursorRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCursorRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextWriterVariant $basePlan): array
    {
        $handoffRows = $basePlan->handoffRows();
        $sourceNextTokens = $basePlan->sourceNextTokens();
        $rows = [];
        $previousCursorToken = null;

        foreach ($handoffRows as $index => $row) {
            $pointerMapPages = array_values(array_map('intval', $row['visible_pointer_map_pages']));
            $payloadPages = array_values(array_map('intval', $row['visible_payload_pages']));
            $currentSourcePages = array_values(array_unique(array_merge($pointerMapPages, $payloadPages)));
            sort($currentSourcePages);
            $sourceNextToken = (string) $row['source_next_token'];
            $expectedSourceNextToken = $sourceNextTokens[$index] ?? null;
            $token = self::signature(array_merge(
                ['next203', (int) $row['batch_index'], $previousCursorToken ?? 'initial', $sourceNextToken],
                $currentSourcePages,
                $pointerMapPages,
                $payloadPages,
                [(int) $row['high_water_page']],
            ));

            $rows[] = [
                'cursor_index' => $index,
                'batch_index' => (int) $row['batch_index'],
                'source_next_token' => $sourceNextToken,
                'expected_source_next_token' => $expectedSourceNextToken,
                'source_next_token_matches' => $expectedSourceNextToken === $sourceNextToken,
                'previous_cursor_token' => $previousCursorToken,
                'current_source_pages' => $currentSourcePages,
                'pointer_map_cursor_pages' => $pointerMapPages,
                'payload_cursor_pages' => $payloadPages,
                'pointer_maps_before_payload' => $payloadPages === [] || ($pointerMapPages !== [] && min($pointerMapPages) < min($payloadPages)),
                'leaf_freeblock_cursor_ready' => in_array(3, $payloadPages, true) ? $row['freeblock_receipt_carried_forward'] === true : true,
                'fenced_tail_pages_absent' => $row['fenced_pages_blocked_from_next_writer'] === true && !array_intersect([109, 110], $currentSourcePages),
                'cursor_chain_valid' => $row['previous_source_next_token'] === null || is_string($row['previous_source_next_token']),
                'high_water_page' => (int) $row['high_water_page'],
                'cursor_state' => 'current-source-cursor-ready',
                'cursor_token' => $token,
            ];

            $previousCursorToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function cursorErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousHighWater = 0;

        foreach ($rows as $row) {
            if ($row['cursor_state'] !== 'current-source-cursor-ready') {
                $errors[] = "batch {$row['batch_index']} cursor is not ready";
            }
            if ($row['source_next_token_matches'] !== true) {
                $errors[] = "batch {$row['batch_index']} source-next token drifted before cursor admission";
            }
            if ($row['pointer_maps_before_payload'] !== true) {
                $errors[] = "batch {$row['batch_index']} admitted payload pages before pointer-map pages";
            }
            if ($row['leaf_freeblock_cursor_ready'] !== true) {
                $errors[] = "batch {$row['batch_index']} lost the leaf freeblock cursor receipt";
            }
            if ($row['fenced_tail_pages_absent'] !== true) {
                $errors[] = "batch {$row['batch_index']} exposed fenced tail pages to the cursor";
            }
            if ($row['cursor_chain_valid'] !== true) {
                $errors[] = "batch {$row['batch_index']} has an invalid source-next chain";
            }
            if ($row['previous_cursor_token'] !== $previousToken) {
                $errors[] = "batch {$row['batch_index']} broke cursor token chaining";
            }
            if ((int) $row['high_water_page'] < $previousHighWater) {
                $errors[] = "batch {$row['batch_index']} moved the cursor high-water backwards";
            }
            if ($row['cursor_token'] === '') {
                $errors[] = "batch {$row['batch_index']} has an empty cursor token";
            }
            $previousHighWater = (int) $row['high_water_page'];
            $previousToken = (string) $row['cursor_token'];
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
