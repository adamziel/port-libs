<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext225Plan
{
    /**
     * @param list<array<string, mixed>> $readRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $readRows,
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
        $rows = self::buildReadRows($basePlan);
        $errors = self::readErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next225 readback failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readRows(): array
    {
        return $this->readRows;
    }

    /**
     * @return list<string>
     */
    public function readErrors(): array
    {
        return self::readErrorsForRows($this->readRows);
    }

    /**
     * @return list<int>
     */
    public function readPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['page_number'], $this->readRows));
    }

    /**
     * @return list<int>
     */
    public function uniqueReadPages(): array
    {
        return self::sortedIntKeys(array_fill_keys($this->readPages(), true));
    }

    /**
     * @return list<int>
     */
    public function pointerMapReadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['read_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadReadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['read_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function readTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['read_token'], $this->readRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function readSummary(): array
    {
        $baseSummary = $this->basePlan->readSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next225-ready',
            'read_row_count' => count($this->readRows),
            'read_pages' => $this->readPages(),
            'unique_read_pages' => $this->uniqueReadPages(),
            'pointer_map_read_pages' => $this->pointerMapReadPages(),
            'payload_read_pages' => $this->payloadReadPages(),
            'read_pages_match_write_pages' => $this->readPages() === $baseSummary['read_pages'],
            'unique_read_pages_match_unique_write_pages' => $this->uniqueReadPages() === $baseSummary['unique_read_pages'],
            'pointer_map_reads_match_writes' => $this->pointerMapReadPages() === $baseSummary['pointer_map_read_pages'],
            'payload_reads_match_writes' => $this->payloadReadPages() === $baseSummary['payload_read_pages'],
            'all_write_tokens_match' => !in_array(false, array_column($this->readRows, 'write_token_matches'), true),
            'all_current_source_tokens_match' => !in_array(false, array_column($this->readRows, 'current_source_token_matches'), true),
            'all_pointer_maps_read_before_payload' => $this->pointerMapsBeforePayloadReads(),
            'all_duplicate_rewrites_preserved' => $this->duplicateRewritePages() === $this->duplicateRewriteReadPages(),
            'all_tail_pages_excluded_from_read' => !in_array(false, array_column($this->readRows, 'tail_page_excluded_from_read'), true),
            'all_read_offsets_contiguous' => !in_array(false, array_column($this->readRows, 'read_offset_contiguous'), true),
            'duplicate_rewrite_pages' => $this->duplicateRewriteReadPages(),
            'read_tokens' => $this->readTokens(),
            'read_signature' => self::signature($this->readTokens()),
            'current_source_next225_token' => self::signature(array_merge(
                ['next225', $baseSummary['current_source_next219_token']],
                $this->readPages(),
                $this->readTokens(),
            )),
            'read_errors' => $this->readErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next219',
                'sqlite-current-source-next225',
            ],
            'dependency_closure' => 'no new support component needed; next225 reuses next219 current-source readback rows, token chains, pointer-map-before-payload ordering, and fenced-tail guards',
            'non_overlap' => 'adds source-next publication admission after next219 readback; does not repeat next219 readback verification, next217 page-write materialization, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next225',
            'read_summary' => $this->readSummary(),
            'read_errors' => $this->readErrors(),
            'read_rows' => $this->readRows,
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
            array_filter($this->readRows, $predicate),
        ));
    }

    /**
     * @return list<int>
     */
    private function duplicateRewritePages(): array
    {
        $pages = [];
        foreach ($this->basePlan->readRows() as $row) {
            if ($row['duplicate_rewrite_read'] === true) {
                $pages[(int) $row['page_number']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    private function duplicateRewriteReadPages(): array
    {
        $pages = [];
        foreach ($this->readRows as $row) {
            if ($row['duplicate_rewrite_read'] === true) {
                $pages[(int) $row['page_number']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    private function pointerMapsBeforePayloadReads(): bool
    {
        $lastPointer = null;
        $firstPayload = null;
        foreach ($this->readRows as $row) {
            if ($row['read_channel'] === 'pointer-map') {
                $lastPointer = (int) $row['read_ordinal'];
            }
            if ($row['read_channel'] === 'payload' && $firstPayload === null) {
                $firstPayload = (int) $row['read_ordinal'];
            }
        }

        return $lastPointer !== null && $firstPayload !== null && $lastPointer < $firstPayload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReadRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $sourceRows = $basePlan->readRows();
        $sourceTokens = $basePlan->readTokens();
        $sourceSummary = $basePlan->readSummary();
        $rows = [];
        $previousReadToken = null;
        $readPages = [];

        foreach ($sourceRows as $index => $sourceRow) {
            $pageNumber = (int) $sourceRow['page_number'];
            $readPages[$pageNumber] = true;
            $readOrdinal = $index + 1;
            $sourceToken = (string) $sourceRow['read_token'];
            $token = self::signature(array_merge(
                ['next225', $readOrdinal, $previousReadToken ?? 'initial', $sourceToken],
                [$pageNumber, (int) $sourceRow['byte_offset'], (string) $sourceRow['read_channel']],
                self::sortedIntKeys($readPages),
            ));

            $rows[] = [
                'read_ordinal' => $readOrdinal,
                'source_write_ordinal' => (int) $sourceRow['read_ordinal'],
                'page_number' => $pageNumber,
                'read_channel' => (string) $sourceRow['read_channel'],
                'byte_offset' => (int) $sourceRow['byte_offset'],
                'byte_length' => (int) $sourceRow['byte_length'],
                'read_visible_pages' => self::sortedIntKeys($readPages),
                'source_write_token' => $sourceToken,
                'expected_write_token' => $sourceTokens[$index] ?? null,
                'write_token_matches' => ($sourceTokens[$index] ?? null) === $sourceToken,
                'current_source_token' => $sourceSummary['current_source_next219_token'],
                'expected_current_source_token' => $sourceSummary['current_source_next219_token'],
                'current_source_token_matches' => $sourceSummary['current_source_next219_token'] !== '',
                'previous_read_token' => $previousReadToken,
                'read_chain_valid' => $previousReadToken === null || is_string($previousReadToken),
                'duplicate_rewrite_read' => $sourceRow['duplicate_rewrite_read'] === true,
                'tail_page_excluded_from_read' => !in_array($pageNumber, [109, 110], true),
                'freeblock_receipt_confirmed' => $sourceRow['freeblock_receipt_confirmed'] === true,
                'leaf_freeblock_receipt_confirmed' => $sourceRow['leaf_freeblock_receipt_confirmed'] === true,
                'overflow_payload_read' => $sourceRow['overflow_payload_read'] === true,
                'read_offset_contiguous' => ((int) $sourceRow['byte_offset']) % 512 === 0 && (int) $sourceRow['byte_length'] === 512,
                'read_state' => 'current-source-next225-publication-ready',
                'read_token' => $token,
            ];

            $previousReadToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function readErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $seenPayload = false;

        foreach ($rows as $row) {
            if ($row['read_state'] !== 'current-source-next225-publication-ready') {
                $errors[] = "read {$row['read_ordinal']} is not ready";
            }
            if ((int) $row['read_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "read {$row['read_ordinal']} skipped a read ordinal";
            }
            if ((int) $row['source_write_ordinal'] !== (int) $row['read_ordinal']) {
                $errors[] = "read {$row['read_ordinal']} drifted from its source write ordinal";
            }
            if ($row['write_token_matches'] !== true) {
                $errors[] = "read {$row['read_ordinal']} source write token drifted";
            }
            if ($row['current_source_token_matches'] !== true) {
                $errors[] = "read {$row['read_ordinal']} current-source token drifted";
            }
            if ($row['previous_read_token'] !== $previousToken) {
                $errors[] = "read {$row['read_ordinal']} broke read token chaining";
            }
            if ($row['read_channel'] === 'pointer-map' && $seenPayload) {
                $errors[] = "read {$row['read_ordinal']} placed pointer-map readback after payload readback";
            }
            if ($row['read_channel'] === 'payload') {
                $seenPayload = true;
            }
            if ($row['tail_page_excluded_from_read'] !== true) {
                $errors[] = "read {$row['read_ordinal']} exposed a fenced tail page";
            }
            if ($row['read_offset_contiguous'] !== true) {
                $errors[] = "read {$row['read_ordinal']} has an invalid page byte range";
            }
            if ($row['read_token'] === '') {
                $errors[] = "read {$row['read_ordinal']} has an empty read token";
            }

            $previousOrdinal = (int) $row['read_ordinal'];
            $previousToken = (string) $row['read_token'];
        }

        if ($rows === []) {
            $errors[] = 'readback plan is empty';
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
