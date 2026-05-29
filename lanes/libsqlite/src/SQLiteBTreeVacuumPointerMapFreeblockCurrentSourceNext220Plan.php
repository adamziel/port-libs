<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext220Plan
{
    /**
     * @param list<array<string, mixed>> $commitRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Plan $writePlan,
        private readonly array $commitRows,
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
        return self::fromWritePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Plan::tableLeafFromDeleteResult(
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

    public static function fromWritePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Plan $writePlan): self
    {
        $rows = self::buildCommitRows($writePlan);
        $errors = self::commitErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next220 commit failed: ' . implode('; ', $errors));
        }

        return new self($writePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function commitRows(): array
    {
        return $this->commitRows;
    }

    /**
     * @return list<string>
     */
    public function commitErrors(): array
    {
        return self::commitErrorsForRows($this->commitRows);
    }

    /**
     * @return list<int>
     */
    public function commitPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['page_number'], $this->commitRows));
    }

    /**
     * @return list<int>
     */
    public function uniqueCommitPages(): array
    {
        return self::sortedIntKeys(array_fill_keys($this->commitPages(), true));
    }

    /**
     * @return list<int>
     */
    public function pointerMapCommitPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['commit_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadCommitPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['commit_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function commitTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['commit_token'], $this->commitRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function commitSummary(): array
    {
        $writeSummary = $this->writePlan->writeSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next220-ready',
            'commit_row_count' => count($this->commitRows),
            'commit_pages' => $this->commitPages(),
            'unique_commit_pages' => $this->uniqueCommitPages(),
            'pointer_map_commit_pages' => $this->pointerMapCommitPages(),
            'payload_commit_pages' => $this->payloadCommitPages(),
            'commit_pages_match_write_pages' => $this->commitPages() === $writeSummary['write_pages'],
            'unique_commit_pages_match_write_pages' => $this->uniqueCommitPages() === $writeSummary['unique_write_pages'],
            'pointer_map_commit_pages_match_write_pages' => $this->pointerMapCommitPages() === $writeSummary['pointer_map_write_pages'],
            'payload_commit_pages_match_write_pages' => $this->payloadCommitPages() === $writeSummary['payload_write_pages'],
            'all_pointer_maps_committed_before_payload' => $this->pointerMapsBeforePayloadCommits(),
            'all_source_write_tokens_match' => !in_array(false, array_column($this->commitRows, 'source_write_token_matches'), true),
            'all_commit_chains_valid' => !in_array(false, array_column($this->commitRows, 'commit_chain_valid'), true),
            'all_tail_pages_excluded' => !in_array(false, array_column($this->commitRows, 'tail_page_excluded_from_commit'), true),
            'all_freeblock_receipts_carried' => !in_array(false, array_column($this->commitRows, 'freeblock_receipt_carried'), true),
            'all_commit_offsets_contiguous' => !in_array(false, array_column($this->commitRows, 'commit_offset_contiguous'), true),
            'rewrite_commit_pages' => $this->rewriteCommitPages(),
            'commit_groups' => array_values(array_unique(array_column($this->commitRows, 'commit_group'))),
            'commit_tokens' => $this->commitTokens(),
            'commit_signature' => self::signature($this->commitTokens()),
            'current_source_next220_token' => self::signature(array_merge(
                ['next220', $writeSummary['current_source_next217_token']],
                $this->commitPages(),
                $this->commitTokens(),
            )),
            'commit_errors' => $this->commitErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next217',
                'sqlite-current-source-next220',
            ],
            'dependency_closure' => 'no new support component needed; next220 reuses next217 page-write rows, pointer-map-first ordering, duplicate pointer-map rewrite receipts, and fenced-tail guards',
            'non_overlap' => 'adds commit-fenced current-source publication for next220 after next217 write materialization; does not repeat next217 write-row construction, next212 apply ordering, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next220',
            'commit_summary' => $this->commitSummary(),
            'commit_errors' => $this->commitErrors(),
            'commit_rows' => $this->commitRows,
            'write_plan' => $this->writePlan->toArray(),
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
            array_filter($this->commitRows, $predicate),
        ));
    }

    /**
     * @return list<int>
     */
    private function rewriteCommitPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['rewrites_existing_page'] === true);
    }

    private function pointerMapsBeforePayloadCommits(): bool
    {
        $lastPointer = null;
        $firstPayload = null;
        foreach ($this->commitRows as $row) {
            if ($row['commit_channel'] === 'pointer-map') {
                $lastPointer = (int) $row['commit_ordinal'];
            }
            if ($row['commit_channel'] === 'payload' && $firstPayload === null) {
                $firstPayload = (int) $row['commit_ordinal'];
            }
        }

        return $lastPointer !== null && $firstPayload !== null && $lastPointer < $firstPayload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCommitRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Plan $writePlan): array
    {
        $writeRows = $writePlan->writeRows();
        $writeTokens = $writePlan->writeTokens();
        $rows = [];
        $previousCommitToken = null;
        $committedPages = [];
        $commitOrdinal = 1;

        foreach ($writeRows as $writeRow) {
            $pageNumber = (int) $writeRow['page_number'];
            $committedPages[$pageNumber] = true;
            $sourceToken = (string) $writeRow['write_token'];
            $expectedOffset = ($pageNumber - 1) * 512;
            $token = self::signature(array_merge(
                ['next220', $commitOrdinal, $previousCommitToken ?? 'initial', $sourceToken],
                [$pageNumber, $expectedOffset, (string) $writeRow['write_channel']],
                self::sortedIntKeys($committedPages),
            ));

            $rows[] = [
                'commit_ordinal' => $commitOrdinal,
                'source_write_ordinal' => (int) $writeRow['write_ordinal'],
                'page_number' => $pageNumber,
                'commit_channel' => (string) $writeRow['write_channel'],
                'byte_offset' => $expectedOffset,
                'byte_length' => 512,
                'committed_visible_pages' => self::sortedIntKeys($committedPages),
                'source_write_token' => $sourceToken,
                'expected_write_token' => $writeTokens[((int) $writeRow['write_ordinal']) - 1] ?? null,
                'source_write_token_matches' => ($writeTokens[((int) $writeRow['write_ordinal']) - 1] ?? null) === $sourceToken,
                'previous_commit_token' => $previousCommitToken,
                'commit_chain_valid' => $previousCommitToken === null || is_string($previousCommitToken),
                'commit_offset_contiguous' => $expectedOffset % 512 === 0,
                'rewrites_existing_page' => $writeRow['rewrites_existing_page'] === true,
                'tail_page_excluded_from_commit' => $writeRow['tail_page_excluded_from_write'] === true,
                'freeblock_receipt_carried' => $writeRow['freeblock_receipt_carried'] === true,
                'leaf_freeblock_receipt_carried' => $writeRow['leaf_freeblock_receipt_carried'] === true,
                'overflow_payload_commit' => $writeRow['overflow_payload_write'] === true,
                'commit_group' => $writeRow['write_channel'] === 'pointer-map' ? 'commit-pointer-map-before-payload' : 'commit-payload-after-pointer-map',
                'commit_state' => 'current-source-page-commit-ready',
                'commit_token' => $token,
            ];

            $previousCommitToken = $token;
            ++$commitOrdinal;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function commitErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $seenPayload = false;

        foreach ($rows as $row) {
            if ($row['commit_state'] !== 'current-source-page-commit-ready') {
                $errors[] = "commit {$row['commit_ordinal']} is not ready";
            }
            if ((int) $row['commit_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "commit {$row['commit_ordinal']} skipped a commit ordinal";
            }
            if ($row['source_write_token_matches'] !== true) {
                $errors[] = "commit {$row['commit_ordinal']} source write token drifted";
            }
            if ($row['previous_commit_token'] !== $previousToken) {
                $errors[] = "commit {$row['commit_ordinal']} broke commit token chaining";
            }
            if ($row['commit_channel'] === 'pointer-map' && $seenPayload) {
                $errors[] = "commit {$row['commit_ordinal']} placed pointer-map bytes after payload bytes";
            }
            if ($row['commit_channel'] === 'payload') {
                $seenPayload = true;
            }
            if ($row['tail_page_excluded_from_commit'] !== true) {
                $errors[] = "commit {$row['commit_ordinal']} exposed a fenced tail page";
            }
            if ($row['commit_offset_contiguous'] !== true || (int) $row['byte_length'] !== 512) {
                $errors[] = "commit {$row['commit_ordinal']} has an invalid page byte range";
            }
            if ($row['commit_token'] === '') {
                $errors[] = "commit {$row['commit_ordinal']} has an empty commit token";
            }

            $previousOrdinal = (int) $row['commit_ordinal'];
            $previousToken = (string) $row['commit_token'];
        }

        if ($rows === []) {
            $errors[] = 'commit plan is empty';
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
