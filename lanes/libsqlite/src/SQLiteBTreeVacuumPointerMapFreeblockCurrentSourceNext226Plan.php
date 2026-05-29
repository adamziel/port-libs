<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext226Plan
{
    /**
     * @param list<array<string, mixed>> $publishRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $publishRows,
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
        $rows = self::buildPublishRows($basePlan);
        $errors = self::publishErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next226 publish fence failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publishRows(): array
    {
        return $this->publishRows;
    }

    /**
     * @return list<string>
     */
    public function publishErrors(): array
    {
        return self::publishErrorsForRows($this->publishRows);
    }

    /**
     * @return list<int>
     */
    public function publishPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['page_number'], $this->publishRows));
    }

    /**
     * @return list<int>
     */
    public function uniquePublishPages(): array
    {
        return self::sortedIntKeys(array_fill_keys($this->publishPages(), true));
    }

    /**
     * @return list<int>
     */
    public function pointerMapPublishPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publish_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadPublishPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publish_channel'] === 'payload');
    }

    /**
     * @return list<int>
     */
    public function duplicateRewritePublishPages(): array
    {
        $pages = [];
        foreach ($this->publishRows as $row) {
            if ($row['duplicate_rewrite_published'] === true) {
                $pages[(int) $row['page_number']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<string>
     */
    public function publishTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['publish_token'], $this->publishRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function publishSummary(): array
    {
        $readSummary = $this->basePlan->readSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next226-ready',
            'publish_row_count' => count($this->publishRows),
            'publish_pages' => $this->publishPages(),
            'unique_publish_pages' => $this->uniquePublishPages(),
            'pointer_map_publish_pages' => $this->pointerMapPublishPages(),
            'payload_publish_pages' => $this->payloadPublishPages(),
            'publish_pages_match_read_pages' => $this->publishPages() === $readSummary['read_pages'],
            'unique_publish_pages_match_unique_read_pages' => $this->uniquePublishPages() === $readSummary['unique_read_pages'],
            'pointer_map_publish_matches_readback' => $this->pointerMapPublishPages() === $readSummary['pointer_map_read_pages'],
            'payload_publish_matches_readback' => $this->payloadPublishPages() === $readSummary['payload_read_pages'],
            'duplicate_rewrite_pages' => $this->duplicateRewritePublishPages(),
            'duplicate_rewrite_pages_match_readback' => $this->duplicateRewritePublishPages() === $readSummary['duplicate_rewrite_pages'],
            'all_read_tokens_match' => !in_array(false, array_column($this->publishRows, 'read_token_matches'), true),
            'all_current_source_tokens_match' => !in_array(false, array_column($this->publishRows, 'current_source_token_matches'), true),
            'all_pointer_maps_published_before_payload' => $this->pointerMapsBeforePayloadPublish(),
            'all_tail_pages_excluded_from_publish' => !in_array(false, array_column($this->publishRows, 'tail_page_excluded_from_publish'), true),
            'all_freeblock_receipts_confirmed' => !in_array(false, array_column($this->publishRows, 'freeblock_receipt_confirmed'), true),
            'all_publish_offsets_contiguous' => !in_array(false, array_column($this->publishRows, 'publish_offset_contiguous'), true),
            'all_publish_chains_valid' => !in_array(false, array_column($this->publishRows, 'publish_chain_valid'), true),
            'publish_tokens' => $this->publishTokens(),
            'publish_signature' => self::signature($this->publishTokens()),
            'current_source_next226_token' => self::signature(array_merge(
                ['next226', $readSummary['current_source_next219_token']],
                $this->publishPages(),
                $this->publishTokens(),
            )),
            'publish_errors' => $this->publishErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next219',
                'sqlite-current-source-next226',
            ],
            'dependency_closure' => 'no new support component needed; next226 reuses next219 readback rows, read tokens, duplicate pointer-map rewrite receipts, and fenced-tail guards',
            'non_overlap' => 'adds final current-source publish fencing after next219 readback verification; does not repeat next219 readback, next217 page writes, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next226',
            'publish_summary' => $this->publishSummary(),
            'publish_errors' => $this->publishErrors(),
            'publish_rows' => $this->publishRows,
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
            array_filter($this->publishRows, $predicate),
        ));
    }

    private function pointerMapsBeforePayloadPublish(): bool
    {
        $lastPointer = null;
        $firstPayload = null;
        foreach ($this->publishRows as $row) {
            if ($row['publish_channel'] === 'pointer-map') {
                $lastPointer = (int) $row['publish_ordinal'];
            }
            if ($row['publish_channel'] === 'payload' && $firstPayload === null) {
                $firstPayload = (int) $row['publish_ordinal'];
            }
        }

        return $lastPointer !== null && $firstPayload !== null && $lastPointer < $firstPayload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildPublishRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $readRows = $basePlan->readRows();
        $readTokens = $basePlan->readTokens();
        $readSummary = $basePlan->readSummary();
        $rows = [];
        $previousPublishToken = null;
        $publishedPages = [];

        foreach ($readRows as $index => $readRow) {
            $pageNumber = (int) $readRow['page_number'];
            $publishedPages[$pageNumber] = true;
            $publishOrdinal = $index + 1;
            $readToken = (string) $readRow['read_token'];
            $byteOffset = (int) $readRow['byte_offset'];
            $token = self::signature(array_merge(
                ['next226', $publishOrdinal, $previousPublishToken ?? 'initial', $readToken],
                [$pageNumber, $byteOffset, (string) $readRow['read_channel']],
                self::sortedIntKeys($publishedPages),
            ));

            $rows[] = [
                'publish_ordinal' => $publishOrdinal,
                'source_read_ordinal' => (int) $readRow['read_ordinal'],
                'page_number' => $pageNumber,
                'publish_channel' => (string) $readRow['read_channel'],
                'byte_offset' => $byteOffset,
                'byte_length' => (int) $readRow['byte_length'],
                'published_visible_pages' => self::sortedIntKeys($publishedPages),
                'source_read_token' => $readToken,
                'expected_read_token' => $readTokens[$index] ?? null,
                'read_token_matches' => ($readTokens[$index] ?? null) === $readToken,
                'current_source_token' => $readSummary['current_source_next219_token'],
                'expected_current_source_token' => $readSummary['current_source_next219_token'],
                'current_source_token_matches' => $readSummary['current_source_next219_token'] !== '',
                'previous_publish_token' => $previousPublishToken,
                'publish_chain_valid' => $previousPublishToken === null || is_string($previousPublishToken),
                'duplicate_rewrite_published' => $readRow['duplicate_rewrite_read'] === true,
                'tail_page_excluded_from_publish' => $readRow['tail_page_excluded_from_read'] === true && !in_array($pageNumber, [109, 110], true),
                'freeblock_receipt_confirmed' => $readRow['freeblock_receipt_confirmed'] === true,
                'leaf_freeblock_receipt_confirmed' => $readRow['leaf_freeblock_receipt_confirmed'] === true,
                'overflow_payload_published' => $readRow['overflow_payload_read'] === true,
                'publish_offset_contiguous' => $byteOffset % 512 === 0 && (int) $readRow['byte_length'] === 512,
                'publish_state' => 'current-source-page-publish-fenced',
                'publish_token' => $token,
            ];

            $previousPublishToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function publishErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $seenPayload = false;

        foreach ($rows as $row) {
            if ($row['publish_state'] !== 'current-source-page-publish-fenced') {
                $errors[] = "publish {$row['publish_ordinal']} is not fenced";
            }
            if ((int) $row['publish_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "publish {$row['publish_ordinal']} skipped a publish ordinal";
            }
            if ((int) $row['source_read_ordinal'] !== (int) $row['publish_ordinal']) {
                $errors[] = "publish {$row['publish_ordinal']} drifted from its source read ordinal";
            }
            if ($row['read_token_matches'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} source read token drifted";
            }
            if ($row['current_source_token_matches'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} current-source token drifted";
            }
            if ($row['previous_publish_token'] !== $previousToken) {
                $errors[] = "publish {$row['publish_ordinal']} broke publish token chaining";
            }
            if ($row['publish_channel'] === 'pointer-map' && $seenPayload) {
                $errors[] = "publish {$row['publish_ordinal']} placed pointer-map publication after payload publication";
            }
            if ($row['publish_channel'] === 'payload') {
                $seenPayload = true;
            }
            if ($row['tail_page_excluded_from_publish'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} exposed a fenced tail page";
            }
            if ($row['publish_offset_contiguous'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} has an invalid page byte range";
            }
            if ($row['publish_token'] === '') {
                $errors[] = "publish {$row['publish_ordinal']} has an empty publish token";
            }

            $previousOrdinal = (int) $row['publish_ordinal'];
            $previousToken = (string) $row['publish_token'];
        }

        if ($rows === []) {
            $errors[] = 'publish plan is empty';
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
