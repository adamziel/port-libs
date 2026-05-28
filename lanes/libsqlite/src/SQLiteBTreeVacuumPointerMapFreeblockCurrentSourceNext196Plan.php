<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext196Plan
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext192Plan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext192Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext192Plan $basePlan): self
    {
        $rows = self::buildHandoffRows($basePlan);
        $errors = self::handoffErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next196 handoff failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
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
    public function nextWritablePages(): array
    {
        $pages = [];
        foreach ($this->handoffRows as $row) {
            foreach ($row['next_writable_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        $pages = array_keys($pages);
        sort($pages);

        return array_values(array_map('intval', $pages));
    }

    /**
     * @return list<string>
     */
    public function sourceNextTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['source_next_token'], $this->handoffRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceNextSummary(): array
    {
        $validationSummary = $this->basePlan->validationSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next196-ready',
            'handoff_row_count' => count($this->handoffRows),
            'next_writable_pages' => $this->nextWritablePages(),
            'reader_pages' => $validationSummary['admitted_reader_pages'],
            'validation_signature' => $validationSummary['validation_signature'],
            'source_next_tokens' => $this->sourceNextTokens(),
            'source_next_signature' => self::signature($this->sourceNextTokens()),
            'next_writer_source_token' => self::signature(array_merge(
                ['next196', $validationSummary['current_source_reader_token']],
                $this->nextWritablePages(),
                $this->sourceNextTokens(),
            )),
            'all_validation_tokens_match' => !in_array(false, array_column($this->handoffRows, 'validation_token_matches'), true),
            'all_pointer_maps_carried_forward' => !in_array(false, array_column($this->handoffRows, 'pointer_map_carried_forward'), true),
            'all_freeblock_receipts_carried_forward' => !in_array(false, array_column($this->handoffRows, 'freeblock_receipt_carried_forward'), true),
            'all_handoff_chains_valid' => !in_array(false, array_column($this->handoffRows, 'handoff_chain_valid'), true),
            'all_fenced_pages_blocked_from_next_writer' => !in_array(false, array_column($this->handoffRows, 'fenced_pages_blocked_from_next_writer'), true),
            'handoff_errors' => $this->handoffErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next192',
                'sqlite-current-source-next196',
            ],
            'dependency_closure' => 'no new support component needed; next196 reuses next192 reader validation, checkpoint tokens, pointer-map ordering, leaf freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds source-next writer handoff after next192 reader validation; does not repeat next192 validation, next189 checkpoint rows, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next196',
            'source_next_summary' => $this->sourceNextSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext192Plan $basePlan): array
    {
        $validationRows = $basePlan->validationRows();
        $validationTokens = $basePlan->validationTokens();
        $rows = [];
        $previousSourceNextToken = null;
        $nextWritablePages = [];

        foreach ($validationRows as $index => $row) {
            $validatedPages = array_values(array_map('intval', $row['validated_pages']));
            foreach ($validatedPages as $pageNumber) {
                $nextWritablePages[$pageNumber] = true;
            }

            $pointerMapPages = array_values(array_map('intval', $row['visible_pointer_map_pages']));
            $payloadPages = array_values(array_map('intval', $row['visible_payload_pages']));
            $token = self::signature(array_merge(
                ['next196', (int) $row['batch_index'], $previousSourceNextToken ?? 'initial', (string) $row['validation_token']],
                self::sortedIntKeys($nextWritablePages),
                $pointerMapPages,
                $payloadPages,
                [(int) $row['high_water_page'], (int) $row['page_hash_count']],
            ));

            $rows[] = [
                'batch_index' => (int) $row['batch_index'],
                'validation_token' => (string) $row['validation_token'],
                'expected_validation_token' => $validationTokens[$index] ?? null,
                'validation_token_matches' => ($validationTokens[$index] ?? null) === (string) $row['validation_token'],
                'previous_source_next_token' => $previousSourceNextToken,
                'validated_pages' => $validatedPages,
                'next_writable_pages' => self::sortedIntKeys($nextWritablePages),
                'visible_pointer_map_pages' => $pointerMapPages,
                'visible_payload_pages' => $payloadPages,
                'pointer_map_carried_forward' => $payloadPages === [] || $pointerMapPages !== [],
                'freeblock_receipt_carried_forward' => $row['leaf_freeblock_validated'] === true && $row['deleted_cell_hidden'] === true,
                'handoff_chain_valid' => $row['previous_validation_token'] === null || is_string($row['previous_validation_token']),
                'fenced_pages_blocked_from_next_writer' => $row['fenced_pages_excluded'] === true,
                'high_water_page' => (int) $row['high_water_page'],
                'source_next_state' => 'next-writer-source-ready',
                'source_next_token' => $token,
            ];

            $previousSourceNextToken = $token;
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
        $previousHighWater = 0;
        $previousToken = null;

        foreach ($rows as $row) {
            if ($row['source_next_state'] !== 'next-writer-source-ready') {
                $errors[] = "batch {$row['batch_index']} is not ready for the next writer";
            }
            if ($row['validation_token_matches'] !== true) {
                $errors[] = "batch {$row['batch_index']} validation token drifted before source-next handoff";
            }
            if ($row['pointer_map_carried_forward'] !== true) {
                $errors[] = "batch {$row['batch_index']} lost pointer-map pages before payload handoff";
            }
            if ($row['freeblock_receipt_carried_forward'] !== true) {
                $errors[] = "batch {$row['batch_index']} lost the leaf freeblock receipt";
            }
            if ($row['handoff_chain_valid'] !== true) {
                $errors[] = "batch {$row['batch_index']} has an invalid validation chain";
            }
            if ($row['fenced_pages_blocked_from_next_writer'] !== true) {
                $errors[] = "batch {$row['batch_index']} exposes fenced pages to the next writer";
            }
            if ((int) $row['high_water_page'] < $previousHighWater) {
                $errors[] = "batch {$row['batch_index']} moved high-water backwards";
            }
            if ($row['previous_source_next_token'] !== $previousToken) {
                $errors[] = "batch {$row['batch_index']} broke source-next token chaining";
            }
            if ($row['source_next_token'] === '') {
                $errors[] = "batch {$row['batch_index']} has an empty source-next token";
            }
            $previousHighWater = (int) $row['high_water_page'];
            $previousToken = (string) $row['source_next_token'];
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
