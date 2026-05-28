<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext230Plan
{
    /**
     * @param list<array<string, mixed>> $finalRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext227Plan $basePlan,
        private readonly array $finalRows,
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
        $rows = self::buildFinalRows($basePlan);
        $errors = self::finalErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next230 finalization failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function finalRows(): array
    {
        return $this->finalRows;
    }

    /**
     * @return list<string>
     */
    public function finalErrors(): array
    {
        return self::finalErrorsForRows($this->finalRows);
    }

    /**
     * @return list<int>
     */
    public function finalPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['page_number'], $this->finalRows));
    }

    /**
     * @return list<int>
     */
    public function uniqueFinalPages(): array
    {
        return self::sortedIntKeys(array_fill_keys($this->finalPages(), true));
    }

    /**
     * @return list<int>
     */
    public function pointerMapFinalPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['final_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadFinalPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['final_channel'] === 'payload');
    }

    /**
     * @return list<int>
     */
    public function duplicateRewriteFinalPages(): array
    {
        $pages = [];
        foreach ($this->finalRows as $row) {
            if ($row['duplicate_rewrite_finalized'] === true) {
                $pages[(int) $row['page_number']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<string>
     */
    public function finalTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['final_token'], $this->finalRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function finalSummary(): array
    {
        $sealSummary = $this->basePlan->sealSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next230-ready',
            'final_row_count' => count($this->finalRows),
            'final_pages' => $this->finalPages(),
            'unique_final_pages' => $this->uniqueFinalPages(),
            'pointer_map_final_pages' => $this->pointerMapFinalPages(),
            'payload_final_pages' => $this->payloadFinalPages(),
            'duplicate_rewrite_final_pages' => $this->duplicateRewriteFinalPages(),
            'final_pages_match_seal_pages' => $this->finalPages() === $sealSummary['seal_pages'],
            'unique_final_pages_match_seal_pages' => $this->uniqueFinalPages() === $sealSummary['unique_seal_pages'],
            'pointer_map_final_matches_seals' => $this->pointerMapFinalPages() === $sealSummary['pointer_map_seal_pages'],
            'payload_final_matches_seals' => $this->payloadFinalPages() === $sealSummary['payload_seal_pages'],
            'duplicate_rewrites_match_seals' => $this->duplicateRewriteFinalPages() === $sealSummary['duplicate_rewrite_seal_pages'],
            'all_seal_tokens_match' => !in_array(false, array_column($this->finalRows, 'seal_token_matches'), true),
            'all_pointer_maps_finalized_before_payload' => $this->pointerMapsBeforePayloadFinal(),
            'all_payload_rows_depend_on_pointer_maps' => !in_array(false, array_column($this->finalRows, 'payload_depends_on_pointer_maps'), true),
            'all_tail_pages_excluded_from_final' => !in_array(false, array_column($this->finalRows, 'tail_page_excluded_from_final'), true),
            'all_freeblock_receipts_finalized' => !in_array(false, array_column($this->finalRows, 'freeblock_receipt_finalized'), true),
            'all_leaf_freeblock_receipts_finalized' => in_array(true, array_column($this->finalRows, 'leaf_freeblock_receipt_finalized'), true),
            'all_final_offsets_contiguous' => !in_array(false, array_column($this->finalRows, 'final_offset_contiguous'), true),
            'final_tokens' => $this->finalTokens(),
            'final_signature' => self::signature($this->finalTokens()),
            'current_source_next230_token' => self::signature(array_merge(
                ['next230', $sealSummary['current_source_next227_token']],
                $this->finalPages(),
                $this->finalTokens(),
            )),
            'final_errors' => $this->finalErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next227',
                'sqlite-current-source-next230',
            ],
            'dependency_closure' => 'no new support component needed; next230 reuses next227 publication seals, duplicate pointer-map rewrite receipts, leaf freeblock receipts, and fenced-tail guards',
            'non_overlap' => 'adds final current-source application ordering after next227 publication seals; does not repeat next227 sealing, next219 readback, next217 page-write materialization, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next230',
            'final_summary' => $this->finalSummary(),
            'final_errors' => $this->finalErrors(),
            'final_rows' => $this->finalRows,
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
            array_filter($this->finalRows, $predicate),
        ));
    }

    private function pointerMapsBeforePayloadFinal(): bool
    {
        $lastPointer = null;
        $firstPayload = null;
        foreach ($this->finalRows as $row) {
            if ($row['final_channel'] === 'pointer-map') {
                $lastPointer = (int) $row['final_ordinal'];
            }
            if ($row['final_channel'] === 'payload' && $firstPayload === null) {
                $firstPayload = (int) $row['final_ordinal'];
            }
        }

        return $lastPointer !== null && $firstPayload !== null && $lastPointer < $firstPayload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildFinalRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext227Plan $basePlan): array
    {
        $sealRows = $basePlan->sealRows();
        $sealTokens = $basePlan->sealTokens();
        $sealSummary = $basePlan->sealSummary();
        $rows = [];
        $previousFinalToken = null;
        $finalizedPages = [];
        $finalizedPointerMaps = [];

        foreach ($sealRows as $index => $sealRow) {
            $pageNumber = (int) $sealRow['page_number'];
            $finalizedPages[$pageNumber] = true;
            $finalOrdinal = $index + 1;
            $channel = (string) $sealRow['seal_channel'];
            if ($channel === 'pointer-map') {
                $finalizedPointerMaps[$pageNumber] = true;
            }

            $sealToken = (string) $sealRow['seal_token'];
            $token = self::signature(array_merge(
                ['next230', $finalOrdinal, $previousFinalToken ?? 'initial', $sealToken],
                [$pageNumber, (int) $sealRow['byte_offset'], $channel],
                self::sortedIntKeys($finalizedPages),
                self::sortedIntKeys($finalizedPointerMaps),
            ));

            $rows[] = [
                'final_ordinal' => $finalOrdinal,
                'source_seal_ordinal' => (int) $sealRow['seal_ordinal'],
                'page_number' => $pageNumber,
                'final_channel' => $channel,
                'byte_offset' => (int) $sealRow['byte_offset'],
                'byte_length' => (int) $sealRow['byte_length'],
                'finalized_visible_pages' => self::sortedIntKeys($finalizedPages),
                'finalized_pointer_map_pages' => self::sortedIntKeys($finalizedPointerMaps),
                'source_seal_token' => $sealToken,
                'expected_seal_token' => $sealTokens[$index] ?? null,
                'seal_token_matches' => ($sealTokens[$index] ?? null) === $sealToken,
                'previous_final_token' => $previousFinalToken,
                'final_chain_valid' => $previousFinalToken === null || is_string($previousFinalToken),
                'duplicate_rewrite_finalized' => $sealRow['duplicate_rewrite_sealed'] === true,
                'payload_depends_on_pointer_maps' => $channel === 'pointer-map' || $finalizedPointerMaps === array_fill_keys($sealSummary['pointer_map_seal_pages'], true),
                'tail_page_excluded_from_final' => $sealRow['tail_page_excluded_from_seal'] === true && !in_array($pageNumber, [109, 110], true),
                'freeblock_receipt_finalized' => $sealRow['freeblock_receipt_sealed'] === true,
                'leaf_freeblock_receipt_finalized' => $sealRow['leaf_freeblock_receipt_sealed'] === true,
                'overflow_payload_finalized' => $sealRow['overflow_payload_sealed'] === true,
                'final_offset_contiguous' => ((int) $sealRow['byte_offset']) % 512 === 0 && (int) $sealRow['byte_length'] === 512,
                'final_state' => 'current-source-page-finalized',
                'final_token' => $token,
            ];

            $previousFinalToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function finalErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $seenPayload = false;
        $pointerMapsComplete = false;

        foreach ($rows as $row) {
            if ($row['final_state'] !== 'current-source-page-finalized') {
                $errors[] = "final row {$row['final_ordinal']} is not ready";
            }
            if ((int) $row['final_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "final row {$row['final_ordinal']} skipped a final ordinal";
            }
            if ((int) $row['source_seal_ordinal'] !== (int) $row['final_ordinal']) {
                $errors[] = "final row {$row['final_ordinal']} drifted from its source seal ordinal";
            }
            if ($row['seal_token_matches'] !== true) {
                $errors[] = "final row {$row['final_ordinal']} source seal token drifted";
            }
            if ($row['previous_final_token'] !== $previousToken) {
                $errors[] = "final row {$row['final_ordinal']} broke final token chaining";
            }
            if ($row['final_channel'] === 'pointer-map' && $seenPayload) {
                $errors[] = "final row {$row['final_ordinal']} placed pointer-map finalization after payload finalization";
            }
            if ($row['final_channel'] === 'payload') {
                $seenPayload = true;
                if ($pointerMapsComplete !== true || $row['payload_depends_on_pointer_maps'] !== true) {
                    $errors[] = "final row {$row['final_ordinal']} finalized payload before pointer-map dependencies";
                }
            }
            if ($row['final_channel'] === 'pointer-map') {
                $pointerMapsComplete = true;
            }
            if ($row['tail_page_excluded_from_final'] !== true) {
                $errors[] = "final row {$row['final_ordinal']} exposed a fenced tail page";
            }
            if ($row['freeblock_receipt_finalized'] !== true) {
                $errors[] = "final row {$row['final_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['final_offset_contiguous'] !== true) {
                $errors[] = "final row {$row['final_ordinal']} has an invalid page byte range";
            }
            if ($row['final_token'] === '') {
                $errors[] = "final row {$row['final_ordinal']} has an empty final token";
            }

            $previousOrdinal = (int) $row['final_ordinal'];
            $previousToken = (string) $row['final_token'];
        }

        if ($rows === []) {
            $errors[] = 'finalization plan is empty';
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
