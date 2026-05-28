<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext219Plan
{
    /**
     * @param list<array<string, mixed>> $readRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Plan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Plan $basePlan): self
    {
        $rows = self::buildReadRows($basePlan);
        $errors = self::readErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next219 readback failed: ' . implode('; ', $errors));
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
        $writeSummary = $this->basePlan->writeSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next219-ready',
            'read_row_count' => count($this->readRows),
            'read_pages' => $this->readPages(),
            'unique_read_pages' => $this->uniqueReadPages(),
            'pointer_map_read_pages' => $this->pointerMapReadPages(),
            'payload_read_pages' => $this->payloadReadPages(),
            'read_pages_match_write_pages' => $this->readPages() === $writeSummary['write_pages'],
            'unique_read_pages_match_unique_write_pages' => $this->uniqueReadPages() === $writeSummary['unique_write_pages'],
            'pointer_map_reads_match_writes' => $this->pointerMapReadPages() === $writeSummary['pointer_map_write_pages'],
            'payload_reads_match_writes' => $this->payloadReadPages() === $writeSummary['payload_write_pages'],
            'all_write_tokens_match' => !in_array(false, array_column($this->readRows, 'write_token_matches'), true),
            'all_current_source_tokens_match' => !in_array(false, array_column($this->readRows, 'current_source_token_matches'), true),
            'all_pointer_maps_read_before_payload' => $this->pointerMapsBeforePayloadReads(),
            'all_duplicate_rewrites_preserved' => $this->duplicateRewritePages() === $this->duplicateRewriteReadPages(),
            'all_tail_pages_excluded_from_read' => !in_array(false, array_column($this->readRows, 'tail_page_excluded_from_read'), true),
            'all_read_offsets_contiguous' => !in_array(false, array_column($this->readRows, 'read_offset_contiguous'), true),
            'duplicate_rewrite_pages' => $this->duplicateRewriteReadPages(),
            'read_tokens' => $this->readTokens(),
            'read_signature' => self::signature($this->readTokens()),
            'current_source_next219_token' => self::signature(array_merge(
                ['next219', $writeSummary['current_source_next217_token']],
                $this->readPages(),
                $this->readTokens(),
            )),
            'read_errors' => $this->readErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next217',
                'sqlite-current-source-next219',
            ],
            'dependency_closure' => 'no new support component needed; next219 reuses next217 current-source write rows, write tokens, pointer-map-before-payload ordering, and fenced-tail guards',
            'non_overlap' => 'adds post-write current-source readback verification after next217 page writes; does not repeat next217 page-write materialization, next212 apply ordering, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next219',
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
        foreach ($this->basePlan->writeRows() as $row) {
            if ($row['rewrites_existing_page'] === true) {
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
    private static function buildReadRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Plan $basePlan): array
    {
        $writeRows = $basePlan->writeRows();
        $writeTokens = $basePlan->writeTokens();
        $writeSummary = $basePlan->writeSummary();
        $rows = [];
        $previousReadToken = null;
        $readPages = [];

        foreach ($writeRows as $index => $writeRow) {
            $pageNumber = (int) $writeRow['page_number'];
            $readPages[$pageNumber] = true;
            $readOrdinal = $index + 1;
            $writeToken = (string) $writeRow['write_token'];
            $token = self::signature(array_merge(
                ['next219', $readOrdinal, $previousReadToken ?? 'initial', $writeToken],
                [$pageNumber, (int) $writeRow['byte_offset'], (string) $writeRow['write_channel']],
                self::sortedIntKeys($readPages),
            ));

            $rows[] = [
                'read_ordinal' => $readOrdinal,
                'source_write_ordinal' => (int) $writeRow['write_ordinal'],
                'page_number' => $pageNumber,
                'read_channel' => (string) $writeRow['write_channel'],
                'byte_offset' => (int) $writeRow['byte_offset'],
                'byte_length' => (int) $writeRow['byte_length'],
                'read_visible_pages' => self::sortedIntKeys($readPages),
                'source_write_token' => $writeToken,
                'expected_write_token' => $writeTokens[$index] ?? null,
                'write_token_matches' => ($writeTokens[$index] ?? null) === $writeToken,
                'current_source_token' => $writeSummary['current_source_next217_token'],
                'expected_current_source_token' => $writeSummary['current_source_next217_token'],
                'current_source_token_matches' => $writeSummary['current_source_next217_token'] !== '',
                'previous_read_token' => $previousReadToken,
                'read_chain_valid' => $previousReadToken === null || is_string($previousReadToken),
                'duplicate_rewrite_read' => $writeRow['rewrites_existing_page'] === true,
                'tail_page_excluded_from_read' => !in_array($pageNumber, [109, 110], true),
                'freeblock_receipt_confirmed' => $writeRow['freeblock_receipt_carried'] === true,
                'leaf_freeblock_receipt_confirmed' => $writeRow['leaf_freeblock_receipt_carried'] === true,
                'overflow_payload_read' => $writeRow['overflow_payload_write'] === true,
                'read_offset_contiguous' => ((int) $writeRow['byte_offset']) % 512 === 0 && (int) $writeRow['byte_length'] === 512,
                'read_state' => 'current-source-page-readback-ready',
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
            if ($row['read_state'] !== 'current-source-page-readback-ready') {
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
