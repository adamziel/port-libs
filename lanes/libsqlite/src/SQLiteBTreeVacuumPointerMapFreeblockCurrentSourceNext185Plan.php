<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext185Plan
{
    /**
     * @param list<array<string, mixed>> $receiptRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext182Plan $basePlan,
        private readonly array $receiptRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext182Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext182Plan $basePlan): self
    {
        $rows = self::buildReceiptRows($basePlan);
        $errors = self::receiptErrorsForRows($rows, $basePlan);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next185 receipt failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function receiptRows(): array
    {
        return $this->receiptRows;
    }

    /**
     * @return list<string>
     */
    public function receiptErrors(): array
    {
        return self::receiptErrorsForRows($this->receiptRows, $this->basePlan);
    }

    /**
     * @return list<int>
     */
    public function durableReplayPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['durable_after_apply'] === true);
    }

    /**
     * @return list<int>
     */
    public function fencedTailPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['receipt_state'] === 'tail-truncated');
    }

    /**
     * @return list<int>
     */
    public function pointerMapReceiptPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['receipt_kind'] === 'pointer-map-apply-receipt');
    }

    /**
     * @return list<int>
     */
    public function overflowReceiptPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['receipt_kind'] === 'overflow-apply-receipt');
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next185-ready',
            'durable_replay_pages' => $this->durableReplayPages(),
            'pointer_map_receipt_pages' => $this->pointerMapReceiptPages(),
            'overflow_receipt_pages' => $this->overflowReceiptPages(),
            'fenced_tail_pages' => $this->fencedTailPages(),
            'final_database_page_count' => $this->finalDatabasePageCount(),
            'truncation_receipt_after_replay' => $this->truncationReceiptAfterReplay(),
            'pointer_map_receipt_before_overflow' => $this->pointerMapReceiptBeforeOverflow(),
            'receipt_signature' => self::signature(array_map(
                static fn (array $row): string => $row['apply_order'] . ':' . $row['page_number'] . ':' . $row['receipt_state'] . ':' . $row['receipt_kind'],
                $this->receiptRows,
            )),
            'current_source_receipt_token' => self::signature(array_map(
                static fn (array $row): string => $row['page_number'] . ':' . ($row['durable_after_apply'] ? 'durable' : 'fenced') . ':' . ($row['next_page_hash'] ?? 'null'),
                $this->receiptRows,
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next182',
                'sqlite-current-source-next185',
            ],
            'dependency_closure' => 'no new support component needed; next185 reuses next182 ordered apply rows, replay hashes, pointer-map dependency receipts, and fenced-tail truncation pages',
            'non_overlap' => 'adds the post-apply durability receipt and final page-count fence after next182 apply scheduling; does not repeat next182 scheduling, next177 replay batches, next176 source-boundary checks, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next185',
            'receipt_summary' => $this->receiptSummary(),
            'receipt_errors' => $this->receiptErrors(),
            'receipt_rows' => $this->receiptRows,
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
            array_filter($this->receiptRows, $predicate),
        ));
    }

    private function finalDatabasePageCount(): int
    {
        $durable = $this->durableReplayPages();

        return $durable === [] ? 0 : max($durable);
    }

    private function truncationReceiptAfterReplay(): bool
    {
        $lastReplay = -1;
        $firstTruncate = null;
        foreach ($this->receiptRows as $row) {
            if ($row['receipt_state'] === 'page-applied') {
                $lastReplay = max($lastReplay, (int) $row['apply_order']);
            }
            if ($row['receipt_state'] === 'tail-truncated' && $firstTruncate === null) {
                $firstTruncate = (int) $row['apply_order'];
            }
        }

        return $firstTruncate !== null && $firstTruncate > $lastReplay;
    }

    private function pointerMapReceiptBeforeOverflow(): bool
    {
        $lastPointerMap = null;
        $firstOverflow = null;
        foreach ($this->receiptRows as $row) {
            if ($row['receipt_kind'] === 'pointer-map-apply-receipt') {
                $lastPointerMap = (int) $row['apply_order'];
            }
            if ($row['receipt_kind'] === 'overflow-apply-receipt' && $firstOverflow === null) {
                $firstOverflow = (int) $row['apply_order'];
            }
        }

        return $lastPointerMap !== null && $firstOverflow !== null && $lastPointerMap < $firstOverflow;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReceiptRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext182Plan $basePlan): array
    {
        $rows = [];
        $finalPageCount = self::finalPageCountFromApplyRows($basePlan->applyRows());
        foreach ($basePlan->applyRows() as $row) {
            $isReplay = $row['operation'] === 'replay-page';
            $rows[] = [
                'apply_order' => (int) $row['apply_order'],
                'page_number' => (int) $row['page_number'],
                'source_operation' => $row['operation'],
                'page_role' => $row['page_role'],
                'receipt_state' => $isReplay ? 'page-applied' : 'tail-truncated',
                'receipt_kind' => self::receiptKind($row),
                'durable_after_apply' => $isReplay,
                'visible_to_next_reader' => $isReplay,
                'excluded_from_next_reader' => !$isReplay,
                'next_page_hash' => $row['next_page_hash'],
                'resume_token' => $row['resume_token'],
                'pointer_map_type' => $row['pointer_map_type'],
                'pointer_map_parent' => $row['pointer_map_parent'],
                'dependency_replayed_in_schedule' => $row['dependency_replayed_in_schedule'],
                'tail_truncation_allowed_after_this_row' => $row['tail_truncation_allowed_after_this_row'],
                'final_database_page_count_after_receipt' => $isReplay ? null : $finalPageCount,
                'receipt_token' => self::signature([
                    (int) $row['apply_order'],
                    (int) $row['page_number'],
                    $row['operation'],
                    $row['page_role'],
                    $row['next_page_hash'] ?? 'truncated',
                    $row['resume_token'] ?? 'truncated',
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function finalPageCountFromApplyRows(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            if ($row['operation'] === 'replay-page') {
                $max = max($max, (int) $row['page_number']);
            }
        }

        return $max;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function receiptKind(array $row): string
    {
        if ($row['operation'] === 'truncate-fenced-tail') {
            return 'tail-truncation-receipt';
        }

        return match ($row['page_role']) {
            'database-header' => 'database-header-apply-receipt',
            'table-leaf-freeblock' => 'leaf-freeblock-apply-receipt',
            'pointer-map' => 'pointer-map-apply-receipt',
            'replacement-overflow' => 'overflow-apply-receipt',
            default => 'page-apply-receipt',
        };
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function receiptErrorsForRows(array $rows, SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext182Plan $basePlan): array
    {
        $errors = [];
        $durablePages = [];
        $fencedPages = [];
        $seenTailTruncation = false;
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['receipt_state'] === 'tail-truncated') {
                $seenTailTruncation = true;
                $fencedPages[] = $pageNumber;
                if ($row['durable_after_apply'] !== false || $row['excluded_from_next_reader'] !== true) {
                    $errors[] = "truncated tail page {$pageNumber} is visible after next185";
                }
                if (!in_array($pageNumber, $basePlan->truncateAfterReplayPages(), true)) {
                    $errors[] = "tail page {$pageNumber} is not scheduled for next182 truncation";
                }
                continue;
            }

            if ($seenTailTruncation) {
                $errors[] = "page {$pageNumber} applied after tail truncation receipt";
            }
            if ($row['durable_after_apply'] !== true || $row['visible_to_next_reader'] !== true) {
                $errors[] = "applied page {$pageNumber} is not durable for the next reader";
            }
            if (!in_array($pageNumber, $basePlan->orderedReplayPages(), true)) {
                $errors[] = "page {$pageNumber} is not scheduled for next182 replay";
            }
            if ($row['next_page_hash'] === null || strlen((string) $row['next_page_hash']) !== 64) {
                $errors[] = "page {$pageNumber} is missing a durable next-page hash";
            }
            if ($row['resume_token'] === null || strlen((string) $row['resume_token']) !== 64) {
                $errors[] = "page {$pageNumber} is missing a durable resume token";
            }
            if ($row['dependency_replayed_in_schedule'] !== true) {
                $errors[] = "page {$pageNumber} was receipted before its pointer-map dependency";
            }
            $durablePages[] = $pageNumber;
        }

        if ($durablePages !== $basePlan->orderedReplayPages()) {
            $errors[] = 'durable receipt pages do not match next182 replay order';
        }
        if ($fencedPages !== $basePlan->truncateAfterReplayPages()) {
            $errors[] = 'tail truncation receipts do not match next182 fenced pages';
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
