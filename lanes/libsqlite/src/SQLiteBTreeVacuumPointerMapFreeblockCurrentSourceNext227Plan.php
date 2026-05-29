<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext227Plan
{
    /**
     * @param list<array<string, mixed>> $sealRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext219(
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
        $rows = self::buildSealRows($basePlan);
        $errors = self::sealErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next227 seal failed: ' . implode('; ', $errors));
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
    public function sealPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['page_number'], $this->sealRows));
    }

    /**
     * @return list<int>
     */
    public function uniqueSealPages(): array
    {
        return self::sortedIntKeys(array_fill_keys($this->sealPages(), true));
    }

    /**
     * @return list<int>
     */
    public function pointerMapSealPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['seal_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadSealPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['seal_channel'] === 'payload');
    }

    /**
     * @return list<int>
     */
    public function duplicateRewriteSealPages(): array
    {
        $pages = [];
        foreach ($this->sealRows as $row) {
            if ($row['duplicate_rewrite_sealed'] === true) {
                $pages[(int) $row['page_number']] = true;
            }
        }

        return self::sortedIntKeys($pages);
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
    public function sealSummary(): array
    {
        $readSummary = $this->basePlan->readSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next227-ready',
            'seal_row_count' => count($this->sealRows),
            'seal_pages' => $this->sealPages(),
            'unique_seal_pages' => $this->uniqueSealPages(),
            'pointer_map_seal_pages' => $this->pointerMapSealPages(),
            'payload_seal_pages' => $this->payloadSealPages(),
            'duplicate_rewrite_seal_pages' => $this->duplicateRewriteSealPages(),
            'seal_pages_match_read_pages' => $this->sealPages() === $readSummary['read_pages'],
            'unique_seal_pages_match_read_pages' => $this->uniqueSealPages() === $readSummary['unique_read_pages'],
            'pointer_map_seals_match_reads' => $this->pointerMapSealPages() === $readSummary['pointer_map_read_pages'],
            'payload_seals_match_reads' => $this->payloadSealPages() === $readSummary['payload_read_pages'],
            'duplicate_rewrites_match_reads' => $this->duplicateRewriteSealPages() === $readSummary['duplicate_rewrite_pages'],
            'all_read_tokens_match' => !in_array(false, array_column($this->sealRows, 'read_token_matches'), true),
            'all_current_source_tokens_match' => !in_array(false, array_column($this->sealRows, 'current_source_token_matches'), true),
            'all_pointer_maps_sealed_before_payload' => $this->pointerMapsBeforePayloadSeals(),
            'all_tail_pages_excluded_from_seal' => !in_array(false, array_column($this->sealRows, 'tail_page_excluded_from_seal'), true),
            'all_freeblock_receipts_sealed' => !in_array(false, array_column($this->sealRows, 'freeblock_receipt_sealed'), true),
            'all_leaf_freeblock_receipts_sealed' => in_array(true, array_column($this->sealRows, 'leaf_freeblock_receipt_sealed'), true),
            'all_seal_offsets_contiguous' => !in_array(false, array_column($this->sealRows, 'seal_offset_contiguous'), true),
            'seal_tokens' => $this->sealTokens(),
            'seal_signature' => self::signature($this->sealTokens()),
            'current_source_next227_token' => self::signature(array_merge(
                ['next227', $readSummary['current_source_next219_token']],
                $this->sealPages(),
                $this->sealTokens(),
            )),
            'seal_errors' => $this->sealErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next219',
                'sqlite-current-source-next227',
            ],
            'dependency_closure' => 'no new support component needed; next227 reuses next219 readback rows, duplicate pointer-map rewrite receipts, leaf freeblock receipts, and fenced-tail guards',
            'non_overlap' => 'adds durable publication sealing after next219 readback; does not repeat next219 readback, next217 page-write materialization, next212 apply ordering, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next227',
            'seal_summary' => $this->sealSummary(),
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
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sealRows, $predicate),
        ));
    }

    private function pointerMapsBeforePayloadSeals(): bool
    {
        $lastPointer = null;
        $firstPayload = null;
        foreach ($this->sealRows as $row) {
            if ($row['seal_channel'] === 'pointer-map') {
                $lastPointer = (int) $row['seal_ordinal'];
            }
            if ($row['seal_channel'] === 'payload' && $firstPayload === null) {
                $firstPayload = (int) $row['seal_ordinal'];
            }
        }

        return $lastPointer !== null && $firstPayload !== null && $lastPointer < $firstPayload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSealRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $readRows = $basePlan->readRows();
        $readTokens = $basePlan->readTokens();
        $readSummary = $basePlan->readSummary();
        $rows = [];
        $previousSealToken = null;
        $sealedPages = [];

        foreach ($readRows as $index => $readRow) {
            $pageNumber = (int) $readRow['page_number'];
            $sealedPages[$pageNumber] = true;
            $sealOrdinal = $index + 1;
            $readToken = (string) $readRow['read_token'];
            $token = self::signature(array_merge(
                ['next227', $sealOrdinal, $previousSealToken ?? 'initial', $readToken],
                [$pageNumber, (int) $readRow['byte_offset'], (string) $readRow['read_channel']],
                self::sortedIntKeys($sealedPages),
            ));

            $rows[] = [
                'seal_ordinal' => $sealOrdinal,
                'source_read_ordinal' => (int) $readRow['read_ordinal'],
                'page_number' => $pageNumber,
                'seal_channel' => (string) $readRow['read_channel'],
                'byte_offset' => (int) $readRow['byte_offset'],
                'byte_length' => (int) $readRow['byte_length'],
                'sealed_visible_pages' => self::sortedIntKeys($sealedPages),
                'source_read_token' => $readToken,
                'expected_read_token' => $readTokens[$index] ?? null,
                'read_token_matches' => ($readTokens[$index] ?? null) === $readToken,
                'current_source_token' => $readSummary['current_source_next219_token'],
                'expected_current_source_token' => $readSummary['current_source_next219_token'],
                'current_source_token_matches' => $readSummary['current_source_next219_token'] !== '',
                'previous_seal_token' => $previousSealToken,
                'seal_chain_valid' => $previousSealToken === null || is_string($previousSealToken),
                'duplicate_rewrite_sealed' => $readRow['duplicate_rewrite_read'] === true,
                'tail_page_excluded_from_seal' => !in_array($pageNumber, [109, 110], true),
                'freeblock_receipt_sealed' => $readRow['freeblock_receipt_confirmed'] === true,
                'leaf_freeblock_receipt_sealed' => $readRow['leaf_freeblock_receipt_confirmed'] === true,
                'overflow_payload_sealed' => $readRow['overflow_payload_read'] === true,
                'seal_offset_contiguous' => ((int) $readRow['byte_offset']) % 512 === 0 && (int) $readRow['byte_length'] === 512,
                'seal_state' => 'current-source-page-publication-sealed',
                'seal_token' => $token,
            ];

            $previousSealToken = $token;
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
        $seenPayload = false;

        foreach ($rows as $row) {
            if ($row['seal_state'] !== 'current-source-page-publication-sealed') {
                $errors[] = "seal {$row['seal_ordinal']} is not ready";
            }
            if ((int) $row['seal_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "seal {$row['seal_ordinal']} skipped a seal ordinal";
            }
            if ((int) $row['source_read_ordinal'] !== (int) $row['seal_ordinal']) {
                $errors[] = "seal {$row['seal_ordinal']} drifted from its source read ordinal";
            }
            if ($row['read_token_matches'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} source read token drifted";
            }
            if ($row['current_source_token_matches'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} current-source token drifted";
            }
            if ($row['previous_seal_token'] !== $previousToken) {
                $errors[] = "seal {$row['seal_ordinal']} broke seal token chaining";
            }
            if ($row['seal_channel'] === 'pointer-map' && $seenPayload) {
                $errors[] = "seal {$row['seal_ordinal']} placed pointer-map seal after payload seal";
            }
            if ($row['seal_channel'] === 'payload') {
                $seenPayload = true;
            }
            if ($row['tail_page_excluded_from_seal'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} exposed a fenced tail page";
            }
            if ($row['freeblock_receipt_sealed'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['seal_offset_contiguous'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} has an invalid page byte range";
            }
            if ($row['seal_token'] === '') {
                $errors[] = "seal {$row['seal_ordinal']} has an empty seal token";
            }

            $previousOrdinal = (int) $row['seal_ordinal'];
            $previousToken = (string) $row['seal_token'];
        }

        if ($rows === []) {
            $errors[] = 'seal plan is empty';
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
