<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext195Plan
{
    /**
     * @param list<array<string, mixed>> $replayRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext191Plan $basePlan,
        private readonly array $replayRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext191Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext191Plan $basePlan): self
    {
        $rows = self::buildReplayRows($basePlan);
        $errors = self::replayErrorsForRows($rows, $basePlan);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next195 replay failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function replayRows(): array
    {
        return $this->replayRows;
    }

    /**
     * @return list<string>
     */
    public function replayErrors(): array
    {
        return self::replayErrorsForRows($this->replayRows, $this->basePlan);
    }

    /**
     * @return list<int>
     */
    public function replayablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['replay_state'] === 'replay-current-source-page');
    }

    /**
     * @return list<int>
     */
    public function omittedTailPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['replay_state'] === 'omit-truncated-tail');
    }

    /**
     * @return list<int>
     */
    public function pointerMapReplayPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['replay_role'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function overflowReplayPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['replay_role'] === 'replacement-overflow');
    }

    /**
     * @return array<string, mixed>
     */
    public function replaySummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next195-ready',
            'replayable_pages' => $this->replayablePages(),
            'omitted_tail_pages' => $this->omittedTailPages(),
            'pointer_map_replay_pages' => $this->pointerMapReplayPages(),
            'overflow_replay_pages' => $this->overflowReplayPages(),
            'replay_error_count' => count($this->replayErrors()),
            'byte_ranges_contiguous' => $this->byteRangesContiguous(),
            'pointer_map_replayed_before_overflow' => $this->pointerMapReplayedBeforeOverflow(),
            'freeblock_receipt_replayed' => $this->freeblockReceiptReplayed(),
            'tail_omission_matches_handoff' => $this->tailOmissionMatchesHandoff(),
            'published_page_count' => count($this->replayablePages()),
            'final_database_page_count' => $this->basePlan->handoffSummary()['final_database_page_count'],
            'replay_token' => self::signature(array_column($this->replayRows, 'current_source_replay_key')),
            'handoff_manifest_token' => $this->basePlan->handoffSummary()['manifest_token'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next191',
                'sqlite-current-source-next195',
            ],
            'dependency_closure' => 'no new support component needed; next195 reuses next191 current-source handoff rows, pointer-map ordering, secure-delete freeblock receipts, overflow replay hashes, and page-count tail fences',
            'non_overlap' => 'adds current-source replay cursor tickets after the next191 handoff manifest; does not repeat next191 manifest construction, next188 reader admission, next185 durability receipts, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted pointer-map page-move clusters',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next195',
            'replay_summary' => $this->replaySummary(),
            'replay_errors' => $this->replayErrors(),
            'replay_rows' => $this->replayRows,
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
            array_filter($this->replayRows, $predicate),
        ));
    }

    private function byteRangesContiguous(): bool
    {
        $expectedOffset = 0;
        foreach ($this->replayRows as $row) {
            if ($row['replay_state'] !== 'replay-current-source-page') {
                continue;
            }
            if ($row['byte_start'] !== $expectedOffset || $row['byte_end'] !== $expectedOffset + 512) {
                return false;
            }
            $expectedOffset += 512;
        }

        return $expectedOffset === count($this->replayablePages()) * 512;
    }

    private function pointerMapReplayedBeforeOverflow(): bool
    {
        $lastPointerMap = null;
        $firstOverflow = null;
        foreach ($this->replayRows as $row) {
            if ($row['replay_role'] === 'pointer-map') {
                $lastPointerMap = (int) $row['replay_ordinal'];
            }
            if ($row['replay_role'] === 'replacement-overflow' && $firstOverflow === null) {
                $firstOverflow = (int) $row['replay_ordinal'];
            }
        }

        return $lastPointerMap !== null && $firstOverflow !== null && $lastPointerMap < $firstOverflow;
    }

    private function freeblockReceiptReplayed(): bool
    {
        foreach ($this->replayRows as $row) {
            if ($row['replay_role'] === 'table-leaf-freeblock') {
                return $row['secure_delete_freeblock_replay'] === true
                    && $row['receipt_available_to_replay'] === true
                    && $row['replay_state'] === 'replay-current-source-page';
            }
        }

        return false;
    }

    private function tailOmissionMatchesHandoff(): bool
    {
        return $this->omittedTailPages() === $this->basePlan->fencedTailPages();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReplayRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext191Plan $basePlan): array
    {
        $rows = [];
        $byteOffset = 0;
        foreach ($basePlan->handoffRows() as $row) {
            $published = $row['handoff_state'] === 'publish-current-source-page';
            $ordinal = $published ? (int) $row['manifest_ordinal'] : null;
            $byteStart = $published ? $byteOffset : null;
            $byteEnd = $published ? $byteOffset + 512 : null;
            if ($published) {
                $byteOffset += 512;
            }

            $pageNumber = (int) $row['page_number'];
            $role = (string) $row['handoff_role'];
            $rows[] = [
                'replay_order' => (int) $row['manifest_order'],
                'replay_ordinal' => $ordinal,
                'page_number' => $pageNumber,
                'page_count_fence' => $row['page_count_fence'],
                'byte_start' => $byteStart,
                'byte_end' => $byteEnd,
                'replay_state' => $published ? 'replay-current-source-page' : 'omit-truncated-tail',
                'replay_action' => $published ? 'stream-page-from-current-source' : 'skip-page-beyond-current-source-eof',
                'replay_role' => $role,
                'receipt_available_to_replay' => $published && $row['reader_receipt_carried'] === true,
                'secure_delete_freeblock_replay' => $role === 'table-leaf-freeblock' && $row['secure_delete_freeblock_receipt'] === true,
                'pointer_map_replay_required' => $role === 'pointer-map',
                'overflow_replay_required' => $role === 'replacement-overflow',
                'tail_omission_required' => !$published,
                'source_hash_required' => $published,
                'source_hash_available' => $row['next_page_hash_available'],
                'resume_token_required' => $published,
                'resume_token_available' => $row['resume_token_available'],
                'pointer_map_type' => $row['pointer_map_type'],
                'pointer_map_parent' => $row['pointer_map_parent'],
                'current_source_replay_key' => self::signature([
                    $pageNumber,
                    $role,
                    $published ? $ordinal : 'tail',
                    $published ? $byteStart : 'omit',
                    $row['manifest_receipt_key'],
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function replayErrorsForRows(array $rows, SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext191Plan $basePlan): array
    {
        $errors = [];
        $expectedOrdinal = 0;
        $expectedByteStart = 0;
        $sawTail = false;
        $finalPageCount = (int) $basePlan->handoffSummary()['final_database_page_count'];

        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['page_count_fence'] !== $finalPageCount) {
                $errors[] = "page {$pageNumber} used a stale next195 page-count fence";
            }

            if ($row['replay_state'] === 'replay-current-source-page') {
                $expectedOrdinal++;
                if ($sawTail) {
                    $errors[] = "page {$pageNumber} was replayed after a truncated tail omission";
                }
                if ($row['replay_ordinal'] !== $expectedOrdinal) {
                    $errors[] = "page {$pageNumber} has a non-contiguous current-source replay ordinal";
                }
                if ($row['byte_start'] !== $expectedByteStart || $row['byte_end'] !== $expectedByteStart + 512) {
                    $errors[] = "page {$pageNumber} has a non-contiguous current-source replay byte range";
                }
                if ($pageNumber > $finalPageCount) {
                    $errors[] = "page {$pageNumber} was replayed beyond the final page-count fence";
                }
                if ($row['receipt_available_to_replay'] !== true || $row['source_hash_available'] !== true || $row['resume_token_available'] !== true) {
                    $errors[] = "page {$pageNumber} is missing replay receipt/hash/resume material";
                }
                $expectedByteStart += 512;
                continue;
            }

            if ($row['replay_state'] !== 'omit-truncated-tail') {
                $errors[] = "page {$pageNumber} has an unknown next195 replay state";
                continue;
            }

            $sawTail = true;
            if ($pageNumber <= $finalPageCount) {
                $errors[] = "page {$pageNumber} was omitted inside the final page-count fence";
            }
            if ($row['tail_omission_required'] !== true || $row['byte_start'] !== null || $row['byte_end'] !== null) {
                $errors[] = "tail page {$pageNumber} leaked replay byte-range material";
            }
            if ($row['source_hash_available'] !== false || $row['resume_token_available'] !== false) {
                $errors[] = "tail page {$pageNumber} leaked replay hash/resume material";
            }
        }

        if ($basePlan->handoffErrors() !== []) {
            $errors[] = 'next195 cannot replay a handoff with next191 errors';
        }

        return $errors;
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
