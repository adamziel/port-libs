<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext200Plan
{
    /**
     * @param list<array<string, mixed>> $commitRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextWriterLeaseVariant $basePlan,
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
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextWriterLeaseVariant::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextWriterLeaseVariant $basePlan): self
    {
        $rows = self::buildCommitRows($basePlan);
        $errors = self::commitErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next200 commit failed: ' . implode('; ', $errors));
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
    public function committedCurrentSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['commit_state'] === 'committed-current-source');
    }

    /**
     * @return list<int>
     */
    public function committedLeafFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['commit_channel'] === 'leaf-freeblock');
    }

    /**
     * @return list<int>
     */
    public function committedOverflowFreelistPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['commit_channel'] === 'overflow-freelist');
    }

    /**
     * @return list<int>
     */
    public function excludedTailPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['commit_state'] === 'excluded-truncated-tail');
    }

    /**
     * @return array<string, mixed>
     */
    public function commitSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next200-ready',
            'committed_current_source_pages' => $this->committedCurrentSourcePages(),
            'committed_leaf_freeblock_pages' => $this->committedLeafFreeblockPages(),
            'committed_overflow_freelist_pages' => $this->committedOverflowFreelistPages(),
            'excluded_tail_pages' => $this->excludedTailPages(),
            'commit_error_count' => count($this->commitErrors()),
            'all_committed_pages_pointer_map_safe' => !in_array(false, array_column($this->committedRows(), 'pointer_map_safe_for_commit'), true),
            'all_committed_pages_reader_visible' => !in_array(false, array_column($this->committedRows(), 'reader_visible_before_commit'), true),
            'tail_pages_not_committed' => !in_array(true, array_column($this->tailRows(), 'commit_admitted'), true),
            'leaf_freeblock_commits_before_overflow_freelist' => $this->leafFreeblockCommitsBeforeOverflowFreelist(),
            'commit_sequence_token' => self::signature(array_column($this->commitRows, 'commit_key')),
            'writer_admission_token' => $this->basePlan->writerSummary()['writer_admission_token'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next194',
                'sqlite-current-source-next200',
            ],
            'dependency_closure' => 'no new support component needed; next200 reuses next194 writer admission, pointer-map-safe reuse receipts, and truncated-tail fences',
            'non_overlap' => 'adds the post-writer current-source commit boundary after next194 admission; does not repeat next194 writer admission, next190 reader leases, next187 publish barriers, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next200',
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
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->commitRows, $predicate),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function committedRows(): array
    {
        return array_values(array_filter(
            $this->commitRows,
            static fn (array $row): bool => $row['commit_admitted'] === true,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tailRows(): array
    {
        return array_values(array_filter(
            $this->commitRows,
            static fn (array $row): bool => $row['commit_state'] === 'excluded-truncated-tail',
        ));
    }

    private function leafFreeblockCommitsBeforeOverflowFreelist(): bool
    {
        $firstLeaf = null;
        $firstOverflow = null;
        foreach ($this->commitRows as $row) {
            if ($row['commit_channel'] === 'leaf-freeblock' && $firstLeaf === null) {
                $firstLeaf = (int) $row['commit_ordinal'];
            }
            if ($row['commit_channel'] === 'overflow-freelist' && $firstOverflow === null) {
                $firstOverflow = (int) $row['commit_ordinal'];
            }
        }

        return $firstLeaf !== null && $firstOverflow !== null && $firstLeaf < $firstOverflow;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCommitRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextWriterLeaseVariant $basePlan): array
    {
        $rows = [];
        foreach ($basePlan->writerRows() as $row) {
            $admitted = $row['next_writer_admitted'] === true;
            $tail = $row['reuse_channel'] === 'tail-fence';
            $state = $admitted ? 'committed-current-source' : ($tail ? 'excluded-truncated-tail' : 'preserved-reader-source');
            $commitOrdinal = $admitted ? (int) $row['writer_ordinal'] : null;
            $pointerMapSafe = $admitted
                && $row['pointer_map_safe_for_writer'] === true
                && $row['reader_reuse_receipt_complete'] === true;

            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'commit_state' => $state,
                'commit_channel' => $row['reuse_channel'],
                'commit_admitted' => $admitted,
                'commit_ordinal' => $commitOrdinal,
                'reader_visible_before_commit' => $row['reader_visible_before_writer'],
                'pointer_map_safe_for_commit' => $pointerMapSafe || !$admitted,
                'writer_admission_key' => $row['writer_admission_key'],
                'writer_ordinal' => $row['writer_ordinal'],
                'tail_excluded_from_next_source' => $row['tail_excluded_from_next_source'],
                'tail_fence_required' => $row['tail_fence_required'],
                'source_replayable' => $row['source_replayable'],
                'final_materialized' => $row['final_materialized'],
                'commit_key' => self::signature([
                    'next200',
                    (int) $row['page_number'],
                    $state,
                    $row['reuse_channel'],
                    $commitOrdinal ?? 'not-committed',
                    $row['writer_admission_key'],
                ]),
            ];
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
        $expectedOrdinal = 0;
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['commit_admitted'] === true) {
                $expectedOrdinal++;
                if ($row['commit_ordinal'] !== $expectedOrdinal) {
                    $errors[] = "page {$pageNumber} has a non-contiguous commit ordinal";
                }
                if ($row['reader_visible_before_commit'] !== true) {
                    $errors[] = "page {$pageNumber} was committed before reader visibility";
                }
                if ($row['pointer_map_safe_for_commit'] !== true) {
                    $errors[] = "page {$pageNumber} was committed without pointer-map-safe receipts";
                }
                if (!in_array($row['commit_channel'], ['leaf-freeblock', 'overflow-freelist'], true)) {
                    $errors[] = "page {$pageNumber} has an invalid commit channel";
                }
            }

            if ($row['commit_state'] === 'excluded-truncated-tail') {
                if ($row['commit_admitted'] !== false) {
                    $errors[] = "tail page {$pageNumber} was committed";
                }
                if ($row['tail_fence_required'] !== true || $row['tail_excluded_from_next_source'] !== true) {
                    $errors[] = "tail page {$pageNumber} is missing its current-source fence";
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<mixed> $items
     */
    private static function signature(array $items): string
    {
        return hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));
    }
}
