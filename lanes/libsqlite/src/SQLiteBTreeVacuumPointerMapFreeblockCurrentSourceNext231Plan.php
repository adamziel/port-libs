<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext231Plan
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext227Plan $basePlan,
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
        return self::fromSealPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext227Plan::tableLeafFromDeleteResult(
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

    public static function fromSealPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext227Plan $basePlan): self
    {
        $rows = self::buildHandoffRows($basePlan);
        $errors = self::handoffErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next231 handoff failed: ' . implode('; ', $errors));
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
    public function handoffPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['page_number'], $this->handoffRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapHandoffPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadHandoffPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'payload');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapHandoffPages(): array
    {
        $pages = [];
        foreach ($this->handoffRows as $row) {
            if ($row['duplicate_pointer_map_rewrite_handoff'] === true) {
                $pages[(int) $row['page_number']] = true;
            }
        }

        return self::sortedIntKeys($pages);
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
        $sealSummary = $this->basePlan->sealSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next231-ready',
            'handoff_row_count' => count($this->handoffRows),
            'handoff_pages' => $this->handoffPages(),
            'pointer_map_handoff_pages' => $this->pointerMapHandoffPages(),
            'payload_handoff_pages' => $this->payloadHandoffPages(),
            'duplicate_pointer_map_handoff_pages' => $this->duplicatePointerMapHandoffPages(),
            'handoff_pages_match_seal_pages' => $this->handoffPages() === $sealSummary['seal_pages'],
            'pointer_map_handoffs_match_seals' => $this->pointerMapHandoffPages() === $sealSummary['pointer_map_seal_pages'],
            'payload_handoffs_match_seals' => $this->payloadHandoffPages() === $sealSummary['payload_seal_pages'],
            'duplicate_pointer_map_handoffs_match_seals' => $this->duplicatePointerMapHandoffPages() === $sealSummary['duplicate_rewrite_seal_pages'],
            'all_seal_tokens_match' => !in_array(false, array_column($this->handoffRows, 'seal_token_matches'), true),
            'all_current_source_tokens_match' => !in_array(false, array_column($this->handoffRows, 'current_source_token_matches'), true),
            'all_pointer_maps_admitted_before_payload' => $this->pointerMapsBeforePayloadHandoffs(),
            'all_tail_pages_fenced' => !in_array(false, array_column($this->handoffRows, 'tail_page_fenced'), true),
            'all_freeblock_receipts_handed_off' => !in_array(false, array_column($this->handoffRows, 'freeblock_receipt_handoff'), true),
            'all_leaf_freeblock_receipts_handed_off' => in_array(true, array_column($this->handoffRows, 'leaf_freeblock_receipt_handoff'), true),
            'all_handoff_offsets_contiguous' => !in_array(false, array_column($this->handoffRows, 'handoff_offset_contiguous'), true),
            'handoff_tokens' => $this->handoffTokens(),
            'handoff_signature' => self::signature($this->handoffTokens()),
            'current_source_next231_token' => self::signature(array_merge(
                ['next231', $sealSummary['current_source_next227_token']],
                $this->handoffPages(),
                $this->handoffTokens(),
            )),
            'handoff_errors' => $this->handoffErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next227',
                'sqlite-current-source-next231',
            ],
            'dependency_closure' => 'no new support component needed; next231 reuses next227 publication seals, duplicate pointer-map rewrite receipts, leaf freeblock receipts, and fenced-tail guards',
            'non_overlap' => 'adds next-writer current-source handoff admission after next227 publication sealing; does not repeat next227 sealing, next219 readback, next217 page-write materialization, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next231',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->handoffRows, $predicate),
        ));
    }

    private function pointerMapsBeforePayloadHandoffs(): bool
    {
        $lastPointer = null;
        $firstPayload = null;
        foreach ($this->handoffRows as $row) {
            if ($row['handoff_channel'] === 'pointer-map') {
                $lastPointer = (int) $row['handoff_ordinal'];
            }
            if ($row['handoff_channel'] === 'payload' && $firstPayload === null) {
                $firstPayload = (int) $row['handoff_ordinal'];
            }
        }

        return $lastPointer !== null && $firstPayload !== null && $lastPointer < $firstPayload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext227Plan $basePlan): array
    {
        $sealRows = $basePlan->sealRows();
        $sealTokens = $basePlan->sealTokens();
        $sealSummary = $basePlan->sealSummary();
        $rows = [];
        $previousHandoffToken = null;
        $admittedPages = [];

        foreach ($sealRows as $index => $sealRow) {
            $pageNumber = (int) $sealRow['page_number'];
            $admittedPages[$pageNumber] = true;
            $handoffOrdinal = $index + 1;
            $sealToken = (string) $sealRow['seal_token'];
            $token = self::signature(array_merge(
                ['next231', $handoffOrdinal, $previousHandoffToken ?? 'initial', $sealToken],
                [$pageNumber, (int) $sealRow['byte_offset'], (string) $sealRow['seal_channel']],
                self::sortedIntKeys($admittedPages),
            ));

            $rows[] = [
                'handoff_ordinal' => $handoffOrdinal,
                'source_seal_ordinal' => (int) $sealRow['seal_ordinal'],
                'page_number' => $pageNumber,
                'handoff_channel' => (string) $sealRow['seal_channel'],
                'byte_offset' => (int) $sealRow['byte_offset'],
                'byte_length' => (int) $sealRow['byte_length'],
                'admitted_visible_pages' => self::sortedIntKeys($admittedPages),
                'source_seal_token' => $sealToken,
                'expected_seal_token' => $sealTokens[$index] ?? null,
                'seal_token_matches' => ($sealTokens[$index] ?? null) === $sealToken,
                'current_source_token' => $sealSummary['current_source_next227_token'],
                'expected_current_source_token' => $sealSummary['current_source_next227_token'],
                'current_source_token_matches' => $sealSummary['current_source_next227_token'] !== '',
                'previous_handoff_token' => $previousHandoffToken,
                'handoff_chain_valid' => $previousHandoffToken === null || is_string($previousHandoffToken),
                'duplicate_pointer_map_rewrite_handoff' => $sealRow['duplicate_rewrite_sealed'] === true,
                'tail_page_fenced' => $sealRow['tail_page_excluded_from_seal'] === true && !in_array($pageNumber, [109, 110], true),
                'freeblock_receipt_handoff' => $sealRow['freeblock_receipt_sealed'] === true,
                'leaf_freeblock_receipt_handoff' => $sealRow['leaf_freeblock_receipt_sealed'] === true,
                'overflow_payload_handoff' => $sealRow['overflow_payload_sealed'] === true,
                'handoff_offset_contiguous' => ((int) $sealRow['byte_offset']) % 512 === 0 && (int) $sealRow['byte_length'] === 512,
                'handoff_state' => 'current-source-next-writer-admitted',
                'handoff_token' => $token,
            ];

            $previousHandoffToken = $token;
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
        $previousToken = null;
        $previousOrdinal = 0;
        $seenPayload = false;

        foreach ($rows as $row) {
            if ($row['handoff_state'] !== 'current-source-next-writer-admitted') {
                $errors[] = "handoff {$row['handoff_ordinal']} is not ready";
            }
            if ((int) $row['handoff_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "handoff {$row['handoff_ordinal']} skipped a handoff ordinal";
            }
            if ((int) $row['source_seal_ordinal'] !== (int) $row['handoff_ordinal']) {
                $errors[] = "handoff {$row['handoff_ordinal']} drifted from its source seal ordinal";
            }
            if ($row['seal_token_matches'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} source seal token drifted";
            }
            if ($row['current_source_token_matches'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} current-source token drifted";
            }
            if ($row['previous_handoff_token'] !== $previousToken) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke handoff token chaining";
            }
            if ($row['handoff_channel'] === 'pointer-map' && $seenPayload) {
                $errors[] = "handoff {$row['handoff_ordinal']} placed pointer-map after payload";
            }
            if ($row['handoff_channel'] === 'payload') {
                $seenPayload = true;
            }
            if ($row['tail_page_fenced'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} exposed a fenced tail page";
            }
            if ($row['freeblock_receipt_handoff'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['handoff_offset_contiguous'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} has an invalid page byte range";
            }
            if ($row['handoff_token'] === '') {
                $errors[] = "handoff {$row['handoff_ordinal']} has an empty handoff token";
            }

            $previousOrdinal = (int) $row['handoff_ordinal'];
            $previousToken = (string) $row['handoff_token'];
        }

        if ($rows === []) {
            $errors[] = 'handoff plan is empty';
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
