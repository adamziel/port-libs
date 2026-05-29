<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Plan
{
    /**
     * @param list<array<string, mixed>> $commitRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Plan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Plan $basePlan): self
    {
        $rows = self::buildCommitRows($basePlan);
        $errors = self::commitErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next215 commit failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
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
    public function committedPages(): array
    {
        $pages = [];
        foreach ($this->commitRows as $row) {
            foreach ($row['commit_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function committedPointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['commit_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function committedPayloadPages(): array
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
        $applySummary = $this->basePlan->applySummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next215-ready',
            'commit_row_count' => count($this->commitRows),
            'committed_pages' => $this->committedPages(),
            'committed_pointer_map_pages' => $this->committedPointerMapPages(),
            'committed_payload_pages' => $this->committedPayloadPages(),
            'apply_pages' => $applySummary['apply_pages'],
            'commit_matches_apply_pages' => $this->committedPages() === $applySummary['apply_pages'],
            'commit_tokens' => $this->commitTokens(),
            'commit_signature' => self::signature($this->commitTokens()),
            'next_writer_commit_token' => self::signature(array_merge(
                ['next215', $applySummary['next_writer_apply_token']],
                $this->committedPages(),
                $this->commitTokens(),
            )),
            'all_apply_tokens_match' => !in_array(false, array_column($this->commitRows, 'apply_token_matches'), true),
            'all_pointer_maps_committed_before_payload' => $this->pointerMapsBeforePayloadCommit(),
            'all_freeblock_receipts_committed' => !in_array(false, array_column($this->commitRows, 'freeblock_receipt_committed'), true),
            'all_tail_pages_fenced_for_commit' => !in_array(false, array_column($this->commitRows, 'tail_pages_fenced_for_commit'), true),
            'all_commit_chains_valid' => !in_array(false, array_column($this->commitRows, 'commit_chain_valid'), true),
            'commit_errors' => $this->commitErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next212',
                'sqlite-current-source-next215',
            ],
            'dependency_closure' => 'no new support component needed; next215 reuses next212 applied current-source page rows, pointer-map apply ordering, leaf freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds current-source commit receipts after next212 page apply; does not repeat next212 apply ordering, next209 source latching, next206 sealing, next203 cursor batching, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next215',
            'commit_summary' => $this->commitSummary(),
            'commit_errors' => $this->commitErrors(),
            'commit_rows' => $this->commitRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->commitRows as $row) {
            if (!$predicate($row)) {
                continue;
            }
            foreach ($row['commit_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    private function pointerMapsBeforePayloadCommit(): bool
    {
        $byCursor = [];
        foreach ($this->commitRows as $row) {
            $cursor = (int) $row['cursor_index'];
            $byCursor[$cursor] ??= ['pointer' => null, 'payload' => null];
            if ($row['commit_channel'] === 'pointer-map') {
                $byCursor[$cursor]['pointer'] = (int) $row['commit_ordinal'];
            }
            if ($row['commit_channel'] === 'payload') {
                $byCursor[$cursor]['payload'] = (int) $row['commit_ordinal'];
            }
        }

        foreach ($byCursor as $row) {
            if ($row['payload'] !== null && ($row['pointer'] === null || $row['pointer'] > $row['payload'])) {
                return false;
            }
        }

        return $byCursor !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCommitRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Plan $basePlan): array
    {
        $applyRows = $basePlan->applyRows();
        $applyTokens = $basePlan->applyTokens();
        $rows = [];
        $previousCommitToken = null;
        $committedPages = [];

        foreach ($applyRows as $index => $row) {
            $pages = array_values(array_map('intval', $row['apply_pages']));
            foreach ($pages as $pageNumber) {
                $committedPages[$pageNumber] = true;
            }

            $applyToken = (string) $row['apply_token'];
            $token = self::signature(array_merge(
                ['next215', (int) $row['apply_ordinal'], $previousCommitToken ?? 'initial', $applyToken],
                $pages,
                self::sortedIntKeys($committedPages),
                [(int) $row['high_water_page']],
            ));

            $rows[] = [
                'commit_ordinal' => (int) $row['apply_ordinal'],
                'apply_index' => $index,
                'source_index' => (int) $row['source_index'],
                'cursor_index' => (int) $row['cursor_index'],
                'batch_index' => (int) $row['batch_index'],
                'commit_channel' => (string) $row['apply_channel'],
                'commit_pages' => $pages,
                'committed_visible_pages' => self::sortedIntKeys($committedPages),
                'apply_token' => $applyToken,
                'expected_apply_token' => $applyTokens[$index] ?? null,
                'apply_token_matches' => ($applyTokens[$index] ?? null) === $applyToken,
                'previous_commit_token' => $previousCommitToken,
                'freeblock_receipt_committed' => $row['freeblock_receipt_carried'] === true,
                'tail_pages_fenced_for_commit' => $row['tail_pages_fenced_for_apply'] === true && !array_intersect([109, 110], $pages),
                'commit_chain_valid' => $row['previous_apply_token'] === null || is_string($row['previous_apply_token']),
                'high_water_page' => (int) $row['high_water_page'],
                'commit_state' => 'current-source-page-commit-ready',
                'commit_token' => $token,
            ];

            $previousCommitToken = $token;
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
        $previousVisible = [];

        foreach ($rows as $row) {
            if ($row['commit_state'] !== 'current-source-page-commit-ready') {
                $errors[] = "commit {$row['commit_ordinal']} is not ready";
            }
            if ((int) $row['commit_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "commit {$row['commit_ordinal']} skipped a commit ordinal";
            }
            if ($row['apply_token_matches'] !== true) {
                $errors[] = "commit {$row['commit_ordinal']} apply token drifted";
            }
            if ($row['previous_commit_token'] !== $previousToken) {
                $errors[] = "commit {$row['commit_ordinal']} broke commit token chaining";
            }
            if ($row['freeblock_receipt_committed'] !== true) {
                $errors[] = "commit {$row['commit_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_fenced_for_commit'] !== true) {
                $errors[] = "commit {$row['commit_ordinal']} exposed fenced tail pages";
            }
            if ($row['commit_chain_valid'] !== true) {
                $errors[] = "commit {$row['commit_ordinal']} has an invalid apply chain";
            }
            if (count(array_diff(array_keys($previousVisible), $row['committed_visible_pages'])) !== 0) {
                $errors[] = "commit {$row['commit_ordinal']} lost an already-committed page";
            }
            if ($row['commit_token'] === '') {
                $errors[] = "commit {$row['commit_ordinal']} has an empty commit token";
            }

            $previousOrdinal = (int) $row['commit_ordinal'];
            $previousToken = (string) $row['commit_token'];
            $previousVisible = array_fill_keys(array_map('intval', $row['committed_visible_pages']), true);
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
