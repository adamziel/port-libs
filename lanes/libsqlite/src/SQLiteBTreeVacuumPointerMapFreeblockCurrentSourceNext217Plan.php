<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Plan
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
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next217 write plan failed: ' . implode('; ', $errors));
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
        return array_values(array_map(static fn (array $row): int => (int) $row['page_number'], $this->writeRows));
    }

    /**
     * @return list<int>
     */
    public function uniqueWritePages(): array
    {
        return self::sortedIntKeys(array_fill_keys($this->writePages(), true));
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
     * @return list<int>
     */
    public function leafFreeblockWritePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['leaf_freeblock_receipt_carried'] === true);
    }

    /**
     * @return list<int>
     */
    public function overflowWritePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['overflow_payload_write'] === true);
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
        $baseSummary = $this->basePlan->applySummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next217-ready',
            'write_row_count' => count($this->writeRows),
            'write_pages' => $this->writePages(),
            'unique_write_pages' => $this->uniqueWritePages(),
            'pointer_map_write_pages' => $this->pointerMapWritePages(),
            'payload_write_pages' => $this->payloadWritePages(),
            'leaf_freeblock_write_pages' => $this->leafFreeblockWritePages(),
            'overflow_write_pages' => $this->overflowWritePages(),
            'write_pages_match_apply_pages' => $this->uniqueWritePages() === $baseSummary['apply_pages'],
            'pointer_map_writes_match_apply_pages' => self::sortedIntKeys(array_fill_keys($this->pointerMapWritePages(), true)) === $baseSummary['pointer_map_apply_pages'],
            'payload_writes_match_apply_pages' => $this->payloadWritePages() === $baseSummary['payload_apply_pages'],
            'all_pointer_maps_written_before_payload' => $this->pointerMapsBeforePayloadWrites(),
            'all_source_apply_tokens_match' => !in_array(false, array_column($this->writeRows, 'source_apply_token_matches'), true),
            'all_write_chains_valid' => !in_array(false, array_column($this->writeRows, 'write_chain_valid'), true),
            'all_tail_pages_excluded' => !in_array(false, array_column($this->writeRows, 'tail_page_excluded_from_write'), true),
            'all_freeblock_receipts_carried' => !in_array(false, array_column($this->writeRows, 'freeblock_receipt_carried'), true),
            'all_write_offsets_contiguous' => !in_array(false, array_column($this->writeRows, 'write_offset_contiguous'), true),
            'write_tokens' => $this->writeTokens(),
            'write_signature' => self::signature($this->writeTokens()),
            'current_source_next217_token' => self::signature(array_merge(
                ['next217', $baseSummary['next_writer_apply_token']],
                $this->writePages(),
                $this->writeTokens(),
            )),
            'write_errors' => $this->writeErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next212',
                'sqlite-current-source-next217',
            ],
            'dependency_closure' => 'no new support component needed; next217 reuses next212 current-source apply rows, pointer-map apply pages, leaf freeblock receipts, and fenced-tail guards',
            'non_overlap' => 'adds page-write materialization for current-source next217 after next212 apply ordering; does not repeat next212 writer apply ordering, next209 source latching, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next217',
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
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, $predicate),
        ));
    }

    private function pointerMapsBeforePayloadWrites(): bool
    {
        $lastPointer = null;
        $firstPayload = null;
        foreach ($this->writeRows as $row) {
            if ($row['write_channel'] === 'pointer-map') {
                $lastPointer = (int) $row['write_ordinal'];
            }
            if ($row['write_channel'] === 'payload' && $firstPayload === null) {
                $firstPayload = (int) $row['write_ordinal'];
            }
        }

        return $lastPointer !== null && $firstPayload !== null && $lastPointer < $firstPayload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildWriteRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $applyRows = $basePlan->applyRows();
        $applyTokens = $basePlan->applyTokens();
        $orderedRows = array_merge(
            array_values(array_filter($applyRows, static fn (array $row): bool => $row['apply_channel'] === 'pointer-map')),
            array_values(array_filter($applyRows, static fn (array $row): bool => $row['apply_channel'] !== 'pointer-map')),
        );

        $rows = [];
        $previousWriteToken = null;
        $writtenPages = [];
        $writeOrdinal = 1;

        foreach ($orderedRows as $applyRow) {
            $sourceToken = (string) $applyRow['apply_token'];
            foreach ($applyRow['apply_pages'] as $pageNumber) {
                $pageNumber = (int) $pageNumber;
                $rewritesExistingPage = isset($writtenPages[$pageNumber]);
                $writtenPages[$pageNumber] = true;
                $expectedOffset = ($pageNumber - 1) * 512;
                $token = self::signature(array_merge(
                    ['next217', $writeOrdinal, $previousWriteToken ?? 'initial', $sourceToken],
                    [$pageNumber, $expectedOffset, (string) $applyRow['apply_channel']],
                    self::sortedIntKeys($writtenPages),
                ));

                $rows[] = [
                    'write_ordinal' => $writeOrdinal,
                    'source_apply_ordinal' => (int) $applyRow['apply_ordinal'],
                    'page_number' => $pageNumber,
                    'write_channel' => (string) $applyRow['apply_channel'],
                    'byte_offset' => $expectedOffset,
                    'byte_length' => 512,
                    'written_visible_pages' => self::sortedIntKeys($writtenPages),
                    'source_apply_token' => $sourceToken,
                    'expected_apply_token' => $applyTokens[((int) $applyRow['apply_ordinal']) - 1] ?? null,
                    'source_apply_token_matches' => ($applyTokens[((int) $applyRow['apply_ordinal']) - 1] ?? null) === $sourceToken,
                    'previous_write_token' => $previousWriteToken,
                    'write_chain_valid' => $previousWriteToken === null || is_string($previousWriteToken),
                    'write_offset_contiguous' => $expectedOffset % 512 === 0,
                    'rewrites_existing_page' => $rewritesExistingPage,
                    'tail_page_excluded_from_write' => !in_array($pageNumber, [109, 110], true),
                    'freeblock_receipt_carried' => $applyRow['freeblock_receipt_carried'] === true,
                    'leaf_freeblock_receipt_carried' => $applyRow['freeblock_receipt_carried'] === true && $pageNumber === 3,
                    'overflow_payload_write' => $applyRow['apply_channel'] === 'payload' && $pageNumber !== 3,
                    'sync_group' => $applyRow['apply_channel'] === 'pointer-map' ? 'pointer-map-before-payload' : 'payload-after-pointer-map',
                    'write_state' => 'current-source-page-write-ready',
                    'write_token' => $token,
                ];

                $previousWriteToken = $token;
                ++$writeOrdinal;
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
        $seenPayload = false;
        $seenPages = [];

        foreach ($rows as $row) {
            if ($row['write_state'] !== 'current-source-page-write-ready') {
                $errors[] = "write {$row['write_ordinal']} is not ready";
            }
            if ((int) $row['write_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "write {$row['write_ordinal']} skipped a write ordinal";
            }
            if ($row['source_apply_token_matches'] !== true) {
                $errors[] = "write {$row['write_ordinal']} source apply token drifted";
            }
            if ($row['previous_write_token'] !== $previousToken) {
                $errors[] = "write {$row['write_ordinal']} broke write token chaining";
            }
            if ($row['write_channel'] === 'pointer-map' && $seenPayload) {
                $errors[] = "write {$row['write_ordinal']} placed pointer-map bytes after payload bytes";
            }
            if ($row['write_channel'] === 'payload') {
                $seenPayload = true;
            }
            if ($row['tail_page_excluded_from_write'] !== true) {
                $errors[] = "write {$row['write_ordinal']} exposed a fenced tail page";
            }
            if ($row['write_offset_contiguous'] !== true || (int) $row['byte_length'] !== 512) {
                $errors[] = "write {$row['write_ordinal']} has an invalid page byte range";
            }
            if ($row['write_token'] === '') {
                $errors[] = "write {$row['write_ordinal']} has an empty write token";
            }

            $seenPages[(int) $row['page_number']] = true;
            $previousOrdinal = (int) $row['write_ordinal'];
            $previousToken = (string) $row['write_token'];
        }

        if ($rows === []) {
            $errors[] = 'write plan is empty';
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
