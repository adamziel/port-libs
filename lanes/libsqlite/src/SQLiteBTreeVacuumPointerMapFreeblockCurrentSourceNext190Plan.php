<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext190Plan
{
    /**
     * @param list<array<string, mixed>> $leaseRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Plan $basePlan,
        private readonly array $leaseRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Plan $basePlan): self
    {
        $rows = self::buildLeaseRows($basePlan);
        $errors = self::leaseErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next190 lease failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leaseRows(): array
    {
        return $this->leaseRows;
    }

    /**
     * @return list<string>
     */
    public function leaseErrors(): array
    {
        return self::leaseErrorsForRows($this->leaseRows);
    }

    /**
     * @return list<int>
     */
    public function readerVisiblePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reader_state'] === 'reader-visible');
    }

    /**
     * @return list<int>
     */
    public function readerFencedPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reader_state'] === 'reader-fenced-tail');
    }

    /**
     * @return list<int>
     */
    public function reusableOverflowPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['overflow_reusable_by_next_writer'] === true);
    }

    /**
     * @return list<int>
     */
    public function scrubbedFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['secure_delete_freeblock_visible'] === true);
    }

    /**
     * @return array<string, mixed>
     */
    public function leaseSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next190-ready',
            'reader_visible_pages' => $this->readerVisiblePages(),
            'reader_fenced_pages' => $this->readerFencedPages(),
            'reusable_overflow_pages' => $this->reusableOverflowPages(),
            'scrubbed_freeblock_pages' => $this->scrubbedFreeblockPages(),
            'lease_error_count' => count($this->leaseErrors()),
            'reader_page_count' => count($this->readerVisiblePages()),
            'tail_fence_count' => count($this->readerFencedPages()),
            'reader_ordinals_contiguous' => $this->readerOrdinalsContiguous(),
            'tail_fence_after_reader_pages' => $this->tailFenceAfterReaderPages(),
            'all_reader_pages_reusable_or_scrubbed' => !in_array(false, array_column($this->leaseRows, 'reader_reuse_receipt_complete'), true),
            'lease_token' => self::signature(array_column($this->leaseRows, 'reader_lease_key')),
            'publish_token' => $this->basePlan->barrierSummary()['publish_token'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next187',
                'sqlite-current-source-next190',
            ],
            'dependency_closure' => 'no new support component needed; next190 reuses next187 publish barriers, secure-delete freeblock receipts, overflow terminal receipts, and truncated-tail pointer-map fences',
            'non_overlap' => 'adds reader lease admission for the already published current-source pages; does not repeat next187 publish barriers, next184 cursor materialization, next183 commit receipts, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next190',
            'lease_summary' => $this->leaseSummary(),
            'lease_errors' => $this->leaseErrors(),
            'lease_rows' => $this->leaseRows,
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
            array_filter($this->leaseRows, $predicate),
        ));
    }

    private function readerOrdinalsContiguous(): bool
    {
        $expected = 0;
        foreach ($this->leaseRows as $row) {
            if ($row['reader_state'] !== 'reader-visible') {
                continue;
            }

            $expected++;
            if ($row['reader_ordinal'] !== $expected) {
                return false;
            }
        }

        return $expected > 0;
    }

    private function tailFenceAfterReaderPages(): bool
    {
        $sawTail = false;
        foreach ($this->leaseRows as $row) {
            if ($row['reader_state'] === 'reader-fenced-tail') {
                $sawTail = true;
                continue;
            }
            if ($sawTail && $row['reader_state'] === 'reader-visible') {
                return false;
            }
        }

        return $sawTail;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildLeaseRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Plan $basePlan): array
    {
        $rows = [];
        $readerOrdinal = 0;
        foreach ($basePlan->barrierRows() as $row) {
            $visible = $row['publish_state'] === 'publish-current-source-page';
            if ($visible) {
                $readerOrdinal++;
            }

            $secureFreeblockVisible = $visible
                && $row['freeblock_scrub_receipt_required'] === true
                && $row['freeblock_scrub_receipt_carried'] === true;
            $overflowReusable = $visible
                && $row['terminal_overflow_receipt_required'] === true
                && $row['terminal_next_pointer_zero'] === true
                && $row['terminal_next_pointer_receipt_carried'] === true;
            $tailFence = !$visible && $row['tail_fence_required'] === true;
            $reuseReceiptComplete = true;
            if ($visible && $row['freeblock_scrub_receipt_required'] === true && !$secureFreeblockVisible) {
                $reuseReceiptComplete = false;
            }
            if ($visible && $row['terminal_overflow_receipt_required'] === true && !$overflowReusable) {
                $reuseReceiptComplete = false;
            }
            if ($tailFence && $row['tail_excluded_from_next_source'] !== true) {
                $reuseReceiptComplete = false;
            }

            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'reader_state' => $visible ? 'reader-visible' : 'reader-fenced-tail',
                'reader_ordinal' => $visible ? $readerOrdinal : null,
                'publish_state' => $row['publish_state'],
                'cursor_state' => $row['cursor_state'],
                'secure_delete_freeblock_visible' => $secureFreeblockVisible,
                'overflow_reusable_by_next_writer' => $overflowReusable,
                'tail_fence_visible_to_reader' => false,
                'tail_fence_required' => $tailFence,
                'tail_excluded_from_next_source' => $row['tail_excluded_from_next_source'],
                'receipt_chain_complete' => $row['receipt_chain_complete'],
                'reader_reuse_receipt_complete' => $reuseReceiptComplete && $row['receipt_chain_complete'] === true,
                'source_replayable' => $row['source_replayable'],
                'final_materialized' => $row['final_materialized'],
                'reader_lease_key' => self::signature([
                    (int) $row['page_number'],
                    $visible ? 'reader-visible' : 'reader-fenced-tail',
                    $visible ? $readerOrdinal : 'tail',
                    $secureFreeblockVisible ? 'secure-freeblock' : 'no-secure-freeblock',
                    $overflowReusable ? 'overflow-reusable' : 'no-overflow-reuse',
                    $tailFence ? 'tail-fenced' : 'no-tail-fence',
                    $reuseReceiptComplete ? 'reuse-complete' : 'reuse-incomplete',
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function leaseErrorsForRows(array $rows): array
    {
        $errors = [];
        $expectedOrdinal = 0;
        $sawTailFence = false;
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['reader_state'] === 'reader-visible') {
                $expectedOrdinal++;
                if ($sawTailFence) {
                    $errors[] = "page {$pageNumber} became reader-visible after a tail fence";
                }
                if ($row['reader_ordinal'] !== $expectedOrdinal) {
                    $errors[] = "page {$pageNumber} has a non-contiguous reader ordinal";
                }
                if ($row['source_replayable'] !== true || $row['final_materialized'] !== true) {
                    $errors[] = "page {$pageNumber} is not materialized for reader visibility";
                }
            } elseif ($row['reader_state'] === 'reader-fenced-tail') {
                $sawTailFence = true;
                if ($row['tail_fence_required'] !== true || $row['tail_excluded_from_next_source'] !== true) {
                    $errors[] = "tail page {$pageNumber} is not excluded from the next reader lease";
                }
                if ($row['tail_fence_visible_to_reader'] !== false) {
                    $errors[] = "tail page {$pageNumber} is visible to the next reader";
                }
            } else {
                $errors[] = "page {$pageNumber} has an unknown reader lease state";
            }

            if ($row['receipt_chain_complete'] !== true || $row['reader_reuse_receipt_complete'] !== true) {
                $errors[] = "page {$pageNumber} has an incomplete reader reuse receipt";
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
