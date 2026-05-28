<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext191Plan
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext188Plan $basePlan,
        private readonly array $handoffRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext188Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext188Plan $basePlan): self
    {
        $rows = self::buildHandoffRows($basePlan);
        $errors = self::handoffErrorsForRows($rows, $basePlan);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next191 handoff failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handoffRows(): array
    {
        return $this->handoffRows;
    }

    /**
     * @return list<string>
     */
    public function handoffErrors(): array
    {
        return self::handoffErrorsForRows($this->handoffRows, $this->basePlan);
    }

    /**
     * @return list<int>
     */
    public function manifestPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_state'] === 'publish-current-source-page');
    }

    /**
     * @return list<int>
     */
    public function pointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_role'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function overflowPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_role'] === 'replacement-overflow');
    }

    /**
     * @return list<int>
     */
    public function fencedTailPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_state'] === 'exclude-truncated-tail');
    }

    /**
     * @return array<string, mixed>
     */
    public function handoffSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next191-ready',
            'manifest_pages' => $this->manifestPages(),
            'pointer_map_pages' => $this->pointerMapPages(),
            'overflow_pages' => $this->overflowPages(),
            'fenced_tail_pages' => $this->fencedTailPages(),
            'final_database_page_count' => $this->finalDatabasePageCount(),
            'database_header_first' => $this->databaseHeaderFirst(),
            'pointer_map_before_overflow' => $this->pointerMapBeforeOverflow(),
            'leaf_freeblock_receipt_preserved' => $this->leafFreeblockReceiptPreserved(),
            'tail_pages_fenced_from_manifest' => $this->tailPagesFencedFromManifest(),
            'handoff_error_count' => count($this->handoffErrors()),
            'manifest_token' => self::signature(array_column($this->handoffRows, 'manifest_receipt_key')),
            'next_reader_epoch_token' => $this->basePlan->readerSummary()['reader_epoch_token'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next188',
                'sqlite-current-source-next191',
            ],
            'dependency_closure' => 'no new support component needed; next191 reuses next188 current-source reader admission, durable receipt hashes, secure-delete freeblock visibility, pointer-map ordering, and page-count tail fences',
            'non_overlap' => 'adds a current-source handoff manifest for the next B-tree reader after next188 reader admission; does not repeat next188 admission, next185 durability receipts, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, or accepted pointer-map page-move clusters',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next191',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
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
            array_filter($this->handoffRows, $predicate),
        ));
    }

    private function finalDatabasePageCount(): int
    {
        return (int) $this->basePlan->readerSummary()['final_database_page_count'];
    }

    private function databaseHeaderFirst(): bool
    {
        return ($this->handoffRows[0]['handoff_role'] ?? null) === 'database-header'
            && ($this->handoffRows[0]['handoff_state'] ?? null) === 'publish-current-source-page';
    }

    private function pointerMapBeforeOverflow(): bool
    {
        $lastPointerMap = null;
        $firstOverflow = null;
        foreach ($this->handoffRows as $row) {
            if ($row['handoff_role'] === 'pointer-map') {
                $lastPointerMap = (int) $row['manifest_order'];
            }
            if ($row['handoff_role'] === 'replacement-overflow' && $firstOverflow === null) {
                $firstOverflow = (int) $row['manifest_order'];
            }
        }

        return $lastPointerMap !== null && $firstOverflow !== null && $lastPointerMap < $firstOverflow;
    }

    private function leafFreeblockReceiptPreserved(): bool
    {
        foreach ($this->handoffRows as $row) {
            if ($row['handoff_role'] === 'table-leaf-freeblock') {
                return $row['secure_delete_freeblock_receipt'] === true
                    && $row['reader_receipt_carried'] === true
                    && $row['handoff_state'] === 'publish-current-source-page';
            }
        }

        return false;
    }

    private function tailPagesFencedFromManifest(): bool
    {
        return $this->fencedTailPages() === $this->basePlan->nextSourceRejectedPages();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext188Plan $basePlan): array
    {
        $rows = [];
        $manifestOrdinal = 0;
        foreach ($basePlan->readerRows() as $row) {
            $published = $row['reader_admission'] === 'readable';
            if ($published) {
                $manifestOrdinal++;
            }

            $role = (string) $row['source_page_role'];
            $state = $published ? 'publish-current-source-page' : 'exclude-truncated-tail';
            $pageNumber = (int) $row['page_number'];
            $finalPageCount = (int) $row['page_count_seen_by_reader'];

            $rows[] = [
                'manifest_order' => (int) $row['reader_order'],
                'manifest_ordinal' => $published ? $manifestOrdinal : null,
                'page_number' => $pageNumber,
                'page_count_fence' => $finalPageCount,
                'handoff_state' => $state,
                'handoff_action' => $published ? 'copy-page-into-current-source-manifest' : 'keep-page-out-of-current-source-manifest',
                'handoff_role' => $role,
                'reader_admission' => $row['reader_admission'],
                'reader_receipt_carried' => $row['receipt_carried_to_reader'],
                'secure_delete_freeblock_receipt' => $role === 'table-leaf-freeblock' ? $row['source_receipt_kind'] === 'leaf-freeblock-apply-receipt' : false,
                'pointer_map_receipt_required' => $role === 'pointer-map',
                'overflow_receipt_required' => $role === 'replacement-overflow',
                'tail_fence_required' => !$published,
                'pointer_map_type' => $row['pointer_map_type'],
                'pointer_map_parent' => $row['pointer_map_parent'],
                'next_page_hash_available' => $row['next_page_hash'] !== null,
                'resume_token_available' => $row['resume_token'] !== null,
                'visible_to_next_reader' => $row['visible_to_next_reader'],
                'excluded_from_next_reader' => $row['excluded_from_next_reader'],
                'manifest_receipt_key' => self::signature([
                    $pageNumber,
                    $state,
                    $role,
                    $published ? $manifestOrdinal : 'tail',
                    $finalPageCount,
                    $row['reader_token'],
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function handoffErrorsForRows(array $rows, SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext188Plan $basePlan): array
    {
        $errors = [];
        $expectedOrdinal = 0;
        $sawTailFence = false;
        $finalPageCount = (int) $basePlan->readerSummary()['final_database_page_count'];
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['page_count_fence'] !== $finalPageCount) {
                $errors[] = "page {$pageNumber} used a stale next191 page-count fence";
            }

            if ($row['handoff_state'] === 'publish-current-source-page') {
                $expectedOrdinal++;
                if ($sawTailFence) {
                    $errors[] = "page {$pageNumber} was published after a truncated-tail fence";
                }
                if ($row['manifest_ordinal'] !== $expectedOrdinal) {
                    $errors[] = "page {$pageNumber} has a non-contiguous current-source manifest ordinal";
                }
                if ($pageNumber > $finalPageCount) {
                    $errors[] = "page {$pageNumber} was published beyond the final page-count fence";
                }
                if ($row['reader_receipt_carried'] !== true || $row['visible_to_next_reader'] !== true) {
                    $errors[] = "page {$pageNumber} did not carry a readable next188 receipt into the handoff";
                }
                if ($row['next_page_hash_available'] !== true || $row['resume_token_available'] !== true) {
                    $errors[] = "page {$pageNumber} is missing current-source hash/resume material";
                }
            } elseif ($row['handoff_state'] === 'exclude-truncated-tail') {
                $sawTailFence = true;
                if ($pageNumber <= $finalPageCount) {
                    $errors[] = "page {$pageNumber} was fenced inside the final page count";
                }
                if ($row['tail_fence_required'] !== true || $row['excluded_from_next_reader'] !== true) {
                    $errors[] = "tail page {$pageNumber} was not excluded from the current-source handoff";
                }
                if ($row['next_page_hash_available'] !== false || $row['resume_token_available'] !== false) {
                    $errors[] = "tail page {$pageNumber} leaked hash/resume material into the handoff";
                }
            } else {
                $errors[] = "page {$pageNumber} has an unknown next191 handoff state";
            }

            if ($row['handoff_role'] === 'table-leaf-freeblock' && $row['secure_delete_freeblock_receipt'] !== true) {
                $errors[] = "leaf page {$pageNumber} is missing a secure-delete freeblock receipt";
            }
        }

        if ($basePlan->readerSummary()['pointer_map_before_overflow'] !== true) {
            $errors[] = 'next191 cannot publish a handoff when next188 pointer-map ordering failed';
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
