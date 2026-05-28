<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext194Plan
{
    /**
     * @param list<array<string, mixed>> $writerRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext190Plan $basePlan,
        private readonly array $writerRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext190Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext190Plan $basePlan): self
    {
        $rows = self::buildWriterRows($basePlan);
        $errors = self::writerErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next194 writer admission failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function writerRows(): array
    {
        return $this->writerRows;
    }

    /**
     * @return list<string>
     */
    public function writerErrors(): array
    {
        return self::writerErrorsForRows($this->writerRows);
    }

    /**
     * @return list<int>
     */
    public function admittedLeafFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reuse_channel'] === 'leaf-freeblock');
    }

    /**
     * @return list<int>
     */
    public function admittedOverflowFreelistPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reuse_channel'] === 'overflow-freelist');
    }

    /**
     * @return list<int>
     */
    public function fencedTailPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reuse_channel'] === 'tail-fence');
    }

    /**
     * @return array<string, mixed>
     */
    public function writerSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next194-ready',
            'admitted_leaf_freeblock_pages' => $this->admittedLeafFreeblockPages(),
            'admitted_overflow_freelist_pages' => $this->admittedOverflowFreelistPages(),
            'fenced_tail_pages' => $this->fencedTailPages(),
            'writer_error_count' => count($this->writerErrors()),
            'admitted_writer_pages' => array_values(array_merge(
                $this->admittedLeafFreeblockPages(),
                $this->admittedOverflowFreelistPages(),
            )),
            'fenced_tail_not_admitted' => !in_array(true, array_column($this->fencedTailRows(), 'next_writer_admitted'), true),
            'all_admitted_pages_reader_visible' => !in_array(false, array_column($this->admittedRows(), 'reader_visible_before_writer'), true),
            'all_admitted_pages_pointer_map_safe' => !in_array(false, array_column($this->admittedRows(), 'pointer_map_safe_for_writer'), true),
            'leaf_freeblock_before_overflow_freelist' => $this->leafFreeblockBeforeOverflowFreelist(),
            'writer_admission_token' => self::signature(array_column($this->writerRows, 'writer_admission_key')),
            'reader_lease_token' => $this->basePlan->leaseSummary()['lease_token'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next190',
                'sqlite-current-source-next194',
            ],
            'dependency_closure' => 'no new support component needed; next194 reuses next190 reader leases, secure-delete freeblock visibility, terminal overflow receipts, and tail-fence exclusion',
            'non_overlap' => 'adds next-writer freeblock/freelist admission after next190 reader leases; does not repeat next190 reader visibility, next187 publish barriers, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next194',
            'writer_summary' => $this->writerSummary(),
            'writer_errors' => $this->writerErrors(),
            'writer_rows' => $this->writerRows,
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
            array_filter($this->writerRows, $predicate),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function admittedRows(): array
    {
        return array_values(array_filter(
            $this->writerRows,
            static fn (array $row): bool => $row['next_writer_admitted'] === true,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fencedTailRows(): array
    {
        return array_values(array_filter(
            $this->writerRows,
            static fn (array $row): bool => $row['reuse_channel'] === 'tail-fence',
        ));
    }

    private function leafFreeblockBeforeOverflowFreelist(): bool
    {
        $firstLeaf = null;
        $firstOverflow = null;
        foreach ($this->writerRows as $row) {
            if ($row['reuse_channel'] === 'leaf-freeblock' && $firstLeaf === null) {
                $firstLeaf = (int) $row['writer_ordinal'];
            }
            if ($row['reuse_channel'] === 'overflow-freelist' && $firstOverflow === null) {
                $firstOverflow = (int) $row['writer_ordinal'];
            }
        }

        return $firstLeaf !== null && $firstOverflow !== null && $firstLeaf < $firstOverflow;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildWriterRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext190Plan $basePlan): array
    {
        $rows = [];
        $writerOrdinal = 0;
        foreach ($basePlan->leaseRows() as $row) {
            $readerVisible = $row['reader_state'] === 'reader-visible';
            $leafFreeblock = $readerVisible && $row['secure_delete_freeblock_visible'] === true;
            $overflowFreelist = $readerVisible && $row['overflow_reusable_by_next_writer'] === true;
            $tailFence = $row['reader_state'] === 'reader-fenced-tail';
            $admitted = $leafFreeblock || $overflowFreelist;
            if ($admitted) {
                $writerOrdinal++;
            }

            $reuseChannel = 'current-source-page';
            $receiptKind = 'reader-visible-page';
            if ($leafFreeblock) {
                $reuseChannel = 'leaf-freeblock';
                $receiptKind = 'secure-delete-freeblock';
            } elseif ($overflowFreelist) {
                $reuseChannel = 'overflow-freelist';
                $receiptKind = 'terminal-overflow-freelist';
            } elseif ($tailFence) {
                $reuseChannel = 'tail-fence';
                $receiptKind = 'truncated-tail-fence';
            }

            $pointerMapSafe = $readerVisible
                && $row['receipt_chain_complete'] === true
                && $row['reader_reuse_receipt_complete'] === true;

            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'reuse_channel' => $reuseChannel,
                'receipt_kind' => $receiptKind,
                'next_writer_admitted' => $admitted,
                'writer_ordinal' => $admitted ? $writerOrdinal : null,
                'reader_visible_before_writer' => $readerVisible,
                'pointer_map_safe_for_writer' => $pointerMapSafe,
                'secure_delete_freeblock_visible' => $row['secure_delete_freeblock_visible'],
                'overflow_reusable_by_next_writer' => $row['overflow_reusable_by_next_writer'],
                'tail_fence_required' => $row['tail_fence_required'],
                'tail_excluded_from_next_source' => $row['tail_excluded_from_next_source'],
                'tail_fence_visible_to_reader' => $row['tail_fence_visible_to_reader'],
                'reader_reuse_receipt_complete' => $row['reader_reuse_receipt_complete'],
                'source_replayable' => $row['source_replayable'],
                'final_materialized' => $row['final_materialized'],
                'writer_admission_key' => self::signature([
                    (int) $row['page_number'],
                    $reuseChannel,
                    $receiptKind,
                    $admitted ? $writerOrdinal : 'not-admitted',
                    $readerVisible ? 'reader-visible' : 'reader-fenced',
                    $pointerMapSafe ? 'pointer-map-safe' : 'pointer-map-unsafe',
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function writerErrorsForRows(array $rows): array
    {
        $errors = [];
        $expectedOrdinal = 0;
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['next_writer_admitted'] === true) {
                $expectedOrdinal++;
                if ($row['writer_ordinal'] !== $expectedOrdinal) {
                    $errors[] = "page {$pageNumber} has a non-contiguous next-writer ordinal";
                }
                if ($row['reader_visible_before_writer'] !== true) {
                    $errors[] = "page {$pageNumber} was admitted before reader visibility";
                }
                if ($row['pointer_map_safe_for_writer'] !== true) {
                    $errors[] = "page {$pageNumber} was admitted without a complete pointer-map reuse receipt";
                }
                if (!in_array($row['reuse_channel'], ['leaf-freeblock', 'overflow-freelist'], true)) {
                    $errors[] = "page {$pageNumber} has an invalid admitted reuse channel";
                }
            }

            if ($row['reuse_channel'] === 'tail-fence') {
                if ($row['next_writer_admitted'] !== false) {
                    $errors[] = "tail page {$pageNumber} was admitted to the next writer";
                }
                if ($row['tail_fence_required'] !== true || $row['tail_excluded_from_next_source'] !== true) {
                    $errors[] = "tail page {$pageNumber} is missing the truncation fence";
                }
                if ($row['tail_fence_visible_to_reader'] !== false) {
                    $errors[] = "tail page {$pageNumber} is reader-visible during writer admission";
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
