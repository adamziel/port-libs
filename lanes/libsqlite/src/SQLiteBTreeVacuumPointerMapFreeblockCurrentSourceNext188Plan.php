<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext188Plan
{
    /**
     * @param list<array<string, mixed>> $readerRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $readerRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext185(
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
        $rows = self::buildReaderRows($basePlan);
        $errors = self::readerErrorsForRows($rows, $basePlan);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next188 reader admission failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readerRows(): array
    {
        return $this->readerRows;
    }

    /**
     * @return list<string>
     */
    public function readerErrors(): array
    {
        return self::readerErrorsForRows($this->readerRows, $this->basePlan);
    }

    /**
     * @return list<int>
     */
    public function nextSourceReadablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reader_admission'] === 'readable');
    }

    /**
     * @return list<int>
     */
    public function nextSourceRejectedPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reader_admission'] === 'beyond-eof');
    }

    /**
     * @return list<int>
     */
    public function receiptCarriedPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['receipt_carried_to_reader'] === true);
    }

    /**
     * @return array<string, mixed>
     */
    public function readerSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next188-ready',
            'next_source_readable_pages' => $this->nextSourceReadablePages(),
            'next_source_rejected_pages' => $this->nextSourceRejectedPages(),
            'receipt_carried_pages' => $this->receiptCarriedPages(),
            'final_database_page_count' => $this->finalDatabasePageCount(),
            'freeblock_leaf_visible_to_reader' => $this->freeblockLeafVisibleToReader(),
            'pointer_map_before_overflow' => $this->basePlan->receiptSummary()['pointer_map_receipt_before_overflow'],
            'tail_pages_rejected_after_page_count_fence' => $this->tailPagesRejectedAfterPageCountFence(),
            'reader_epoch_token' => self::signature(array_map(
                static fn (array $row): string => $row['reader_order'] . ':' . $row['page_number'] . ':' . $row['reader_admission'] . ':' . $row['page_count_seen_by_reader'],
                $this->readerRows,
            )),
            'receipt_carry_token' => self::signature(array_map(
                static fn (array $row): string => $row['page_number'] . ':' . $row['source_receipt_kind'] . ':' . ($row['receipt_carried_to_reader'] ? 'carried' : 'fenced'),
                $this->readerRows,
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next185',
                'sqlite-current-source-next188',
            ],
            'dependency_closure' => 'no new support component needed; next188 reuses next185 durable receipt rows, final page-count fences, secure-delete freeblock receipts, overflow receipt hashes, and pointer-map ordering',
            'non_overlap' => 'adds next-source reader admission after next185 durability receipts; does not repeat next185 receipt publication, next182 apply scheduling, next177 replay batches, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next188',
            'reader_summary' => $this->readerSummary(),
            'reader_errors' => $this->readerErrors(),
            'reader_rows' => $this->readerRows,
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
            array_filter($this->readerRows, $predicate),
        ));
    }

    private function finalDatabasePageCount(): int
    {
        return (int) $this->basePlan->receiptSummary()['final_database_page_count'];
    }

    private function freeblockLeafVisibleToReader(): bool
    {
        foreach ($this->readerRows as $row) {
            if ($row['source_receipt_kind'] === 'leaf-freeblock-apply-receipt') {
                return $row['reader_admission'] === 'readable' && $row['receipt_carried_to_reader'] === true;
            }
        }

        return false;
    }

    private function tailPagesRejectedAfterPageCountFence(): bool
    {
        $finalPageCount = $this->finalDatabasePageCount();
        foreach ($this->readerRows as $row) {
            if ($row['page_number'] > $finalPageCount && $row['reader_admission'] !== 'beyond-eof') {
                return false;
            }
        }

        return $this->nextSourceRejectedPages() === $this->basePlan->fencedTailPages();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReaderRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $rows = [];
        $finalPageCount = (int) $basePlan->receiptSummary()['final_database_page_count'];
        foreach ($basePlan->receiptRows() as $order => $row) {
            $pageNumber = (int) $row['page_number'];
            $readable = $row['durable_after_apply'] === true && $pageNumber <= $finalPageCount;
            $rows[] = [
                'reader_order' => (int) $order,
                'page_number' => $pageNumber,
                'page_count_seen_by_reader' => $finalPageCount,
                'source_receipt_state' => $row['receipt_state'],
                'source_receipt_kind' => $row['receipt_kind'],
                'source_page_role' => $row['page_role'],
                'reader_admission' => $readable ? 'readable' : 'beyond-eof',
                'reader_action' => $readable ? 'read-current-source-page' : 'reject-tail-page',
                'receipt_carried_to_reader' => $readable,
                'visible_to_next_reader' => $readable,
                'excluded_from_next_reader' => !$readable,
                'next_page_hash' => $readable ? $row['next_page_hash'] : null,
                'resume_token' => $readable ? $row['resume_token'] : null,
                'pointer_map_type' => $readable ? $row['pointer_map_type'] : null,
                'pointer_map_parent' => $readable ? $row['pointer_map_parent'] : null,
                'reader_token' => self::signature([
                    $order,
                    $pageNumber,
                    $finalPageCount,
                    $readable ? 'readable' : 'beyond-eof',
                    $row['receipt_kind'],
                    $row['next_page_hash'] ?? 'truncated',
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function readerErrorsForRows(array $rows, SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $errors = [];
        $finalPageCount = (int) $basePlan->receiptSummary()['final_database_page_count'];
        $readablePages = [];
        $rejectedPages = [];
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['page_count_seen_by_reader'] !== $finalPageCount) {
                $errors[] = "page {$pageNumber} used a stale next188 page-count fence";
            }
            if ($row['reader_admission'] === 'readable') {
                if ($pageNumber > $finalPageCount) {
                    $errors[] = "page {$pageNumber} is readable past the next188 page-count fence";
                }
                if ($row['receipt_carried_to_reader'] !== true || $row['visible_to_next_reader'] !== true) {
                    $errors[] = "page {$pageNumber} did not carry its next185 receipt to the next reader";
                }
                if ($row['next_page_hash'] === null || strlen((string) $row['next_page_hash']) !== 64) {
                    $errors[] = "page {$pageNumber} is missing a readable next-page hash";
                }
                if ($row['resume_token'] === null || strlen((string) $row['resume_token']) !== 64) {
                    $errors[] = "page {$pageNumber} is missing a readable resume token";
                }
                $readablePages[] = $pageNumber;
                continue;
            }

            if ($pageNumber <= $finalPageCount) {
                $errors[] = "page {$pageNumber} was rejected inside the next188 page-count fence";
            }
            if ($row['receipt_carried_to_reader'] !== false || $row['excluded_from_next_reader'] !== true) {
                $errors[] = "tail page {$pageNumber} was not fenced out of the next reader";
            }
            if ($row['next_page_hash'] !== null || $row['resume_token'] !== null) {
                $errors[] = "tail page {$pageNumber} leaked replay hashes after truncation";
            }
            $rejectedPages[] = $pageNumber;
        }

        if ($readablePages !== $basePlan->durableReplayPages()) {
            $errors[] = 'next188 readable pages do not match next185 durable replay pages';
        }
        if ($rejectedPages !== $basePlan->fencedTailPages()) {
            $errors[] = 'next188 rejected pages do not match next185 fenced tail pages';
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
