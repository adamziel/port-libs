<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext187Plan
{
    /**
     * @param list<array<string, mixed>> $barrierRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $barrierRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext184(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): self
    {
        $rows = self::buildBarrierRows($basePlan);
        $errors = self::barrierErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next187 barrier failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function barrierRows(): array
    {
        return $this->barrierRows;
    }

    /**
     * @return list<string>
     */
    public function barrierErrors(): array
    {
        return self::barrierErrorsForRows($this->barrierRows);
    }

    /**
     * @return list<int>
     */
    public function nextSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publish_state'] === 'publish-current-source-page');
    }

    /**
     * @return list<int>
     */
    public function fencedTailPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publish_state'] === 'fence-truncated-tail-page');
    }

    /**
     * @return list<int>
     */
    public function scrubbedLeafPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freeblock_scrub_receipt_required'] === true);
    }

    /**
     * @return list<int>
     */
    public function terminalOverflowPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['terminal_overflow_receipt_required'] === true);
    }

    /**
     * @return array<string, mixed>
     */
    public function barrierSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next187-ready',
            'next_source_pages' => $this->nextSourcePages(),
            'fenced_tail_pages' => $this->fencedTailPages(),
            'scrubbed_leaf_pages' => $this->scrubbedLeafPages(),
            'terminal_overflow_pages' => $this->terminalOverflowPages(),
            'barrier_error_count' => count($this->barrierErrors()),
            'all_tail_pages_excluded_from_next_source' => !in_array(false, array_column($this->barrierRows, 'tail_excluded_from_next_source'), true),
            'all_materialized_pages_have_receipts' => !in_array(false, array_column($this->barrierRows, 'receipt_chain_complete'), true),
            'publish_token' => self::signature(array_column($this->barrierRows, 'publish_receipt_key')),
            'cursor_token' => $this->basePlan->cursorSummary()['cursor_token'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next184',
                'sqlite-current-source-next187',
            ],
            'dependency_closure' => 'no new support component needed; next187 reuses next184 current-source cursor rows, secure-delete freeblock receipts, overflow terminal next-pointer receipts, and pointer-map tail fences',
            'non_overlap' => 'adds final next-source publish barriers for scrubbed leaf pages, overflow terminal receipts, and excluded vacuum tail pages; does not repeat next184 cursor materialization, next183 commit receipts, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next187',
            'barrier_summary' => $this->barrierSummary(),
            'barrier_errors' => $this->barrierErrors(),
            'barrier_rows' => $this->barrierRows,
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
            array_filter($this->barrierRows, $predicate),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildBarrierRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $rows = [];
        $expectedNextOrdinal = 0;
        foreach ($basePlan->cursorRows() as $row) {
            $published = $row['cursor_state'] === 'materialized-current-source';
            if ($published) {
                $expectedNextOrdinal++;
            }

            $requiresFreeblockReceipt = $row['leaf_freeblock_scrub_required'] === true;
            $requiresTerminalReceipt = $row['overflow_terminal_page'] === true;
            $requiresTailFence = $row['cursor_state'] === 'excluded-truncated-tail';
            $receiptChainComplete = true;
            if ($published && $requiresFreeblockReceipt && $row['freeblock_receipt_carried'] !== true) {
                $receiptChainComplete = false;
            }
            if ($published && $requiresTerminalReceipt && ($row['final_next_page'] !== 0 || $row['next_pointer_receipt_carried'] !== true)) {
                $receiptChainComplete = false;
            }
            if ($requiresTailFence && $row['pointer_map_receipt_carried'] !== true) {
                $receiptChainComplete = false;
            }

            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'publish_state' => $published ? 'publish-current-source-page' : 'fence-truncated-tail-page',
                'next_source_ordinal' => $published ? $expectedNextOrdinal : null,
                'cursor_state' => $row['cursor_state'],
                'snapshot_kind' => $row['snapshot_kind'],
                'freeblock_scrub_receipt_required' => $requiresFreeblockReceipt,
                'freeblock_scrub_receipt_carried' => $row['freeblock_receipt_carried'],
                'terminal_overflow_receipt_required' => $requiresTerminalReceipt,
                'terminal_next_pointer_zero' => $requiresTerminalReceipt ? $row['final_next_page'] === 0 : null,
                'terminal_next_pointer_receipt_carried' => $row['next_pointer_receipt_carried'],
                'tail_fence_required' => $requiresTailFence,
                'tail_excluded_from_next_source' => $requiresTailFence ? $row['source_ordinal'] === null && $row['source_replayable'] === false : true,
                'pointer_map_tail_fence_receipt_carried' => $requiresTailFence ? $row['pointer_map_receipt_carried'] : null,
                'receipt_chain_complete' => $receiptChainComplete,
                'source_replayable' => $row['source_replayable'],
                'final_materialized' => $row['final_materialized'],
                'publish_receipt_key' => self::signature([
                    (int) $row['page_number'],
                    $published ? 'publish' : 'fence-tail',
                    $published ? $expectedNextOrdinal : 'tail',
                    $requiresFreeblockReceipt ? 'freeblock-receipt' : 'no-freeblock-receipt',
                    $requiresTerminalReceipt ? 'terminal-receipt' : 'no-terminal-receipt',
                    $requiresTailFence ? 'tail-fence' : 'no-tail-fence',
                    $receiptChainComplete ? 'complete' : 'incomplete',
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function barrierErrorsForRows(array $rows): array
    {
        $errors = [];
        $expectedOrdinal = 0;
        $sawTailFence = false;
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['publish_state'] === 'publish-current-source-page') {
                $expectedOrdinal++;
                if ($sawTailFence) {
                    $errors[] = "page {$pageNumber} was published after the truncated-tail fence";
                }
                if ($row['next_source_ordinal'] !== $expectedOrdinal) {
                    $errors[] = "page {$pageNumber} has a non-contiguous next-source ordinal";
                }
                if ($row['source_replayable'] !== true || $row['final_materialized'] !== true) {
                    $errors[] = "page {$pageNumber} is not materialized for the next source";
                }
            } elseif ($row['publish_state'] === 'fence-truncated-tail-page') {
                $sawTailFence = true;
                if ($row['tail_excluded_from_next_source'] !== true || $row['pointer_map_tail_fence_receipt_carried'] !== true) {
                    $errors[] = "tail page {$pageNumber} was not fenced out of the next source";
                }
            } else {
                $errors[] = "page {$pageNumber} has an unknown publish state";
            }

            if ($row['freeblock_scrub_receipt_required'] === true && $row['freeblock_scrub_receipt_carried'] !== true) {
                $errors[] = "leaf page {$pageNumber} is missing the freeblock scrub receipt before publish";
            }
            if ($row['terminal_overflow_receipt_required'] === true) {
                if ($row['terminal_next_pointer_zero'] !== true || $row['terminal_next_pointer_receipt_carried'] !== true) {
                    $errors[] = "overflow page {$pageNumber} is missing the terminal next-pointer publish receipt";
                }
            }
            if ($row['receipt_chain_complete'] !== true) {
                $errors[] = "page {$pageNumber} has an incomplete current-source receipt chain";
            }
        }

        return $errors;
    }

    /**
     * @param list<mixed> $items
     */
    private static function signature(array $items): string
    {
        return hash('sha256', implode('|', array_map(
            static fn (mixed $item): string => is_bool($item) ? ($item ? '1' : '0') : (string) $item,
            $items,
        )));
    }
}
