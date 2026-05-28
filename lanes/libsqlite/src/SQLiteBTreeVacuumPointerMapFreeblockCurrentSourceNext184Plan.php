<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext184Plan
{
    /**
     * @param list<array<string, mixed>> $cursorRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext181Plan $basePlan,
        private readonly array $cursorRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext181Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext181Plan $basePlan): self
    {
        $rows = self::buildCursorRows($basePlan);
        $errors = self::cursorErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next184 cursor failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cursorRows(): array
    {
        return $this->cursorRows;
    }

    /**
     * @return list<string>
     */
    public function cursorErrors(): array
    {
        return self::cursorErrorsForRows($this->cursorRows);
    }

    /**
     * @return list<int>
     */
    public function materializedSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['cursor_state'] === 'materialized-current-source');
    }

    /**
     * @return list<int>
     */
    public function excludedTruncatedPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['cursor_state'] === 'excluded-truncated-tail');
    }

    /**
     * @return list<int>
     */
    public function freeblockScrubPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['leaf_freeblock_scrub_required'] === true);
    }

    /**
     * @return list<int>
     */
    public function overflowTerminalPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['overflow_terminal_page'] === true);
    }

    /**
     * @return array<string, mixed>
     */
    public function cursorSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next184-ready',
            'materialized_source_pages' => $this->materializedSourcePages(),
            'excluded_truncated_pages' => $this->excludedTruncatedPages(),
            'freeblock_scrub_pages' => $this->freeblockScrubPages(),
            'overflow_terminal_pages' => $this->overflowTerminalPages(),
            'cursor_error_count' => count($this->cursorErrors()),
            'cursor_token' => self::signature(array_map(
                static fn (array $row): string => $row['page_number'] . ':' . $row['cursor_state'] . ':' . ($row['source_ordinal'] ?? 'null') . ':' . ($row['final_next_page'] ?? 'null'),
                $this->cursorRows,
            )),
            'scrub_token' => self::signature(array_column($this->cursorRows, 'scrub_receipt_key')),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next181',
                'sqlite-current-source-next184',
            ],
            'dependency_closure' => 'no new support component needed; next184 reuses next181 snapshot rows, secure-delete leaf freeblock receipts, overflow terminal next-pointer receipts, and auto-vacuum pointer-map metadata',
            'non_overlap' => 'adds current-source cursor materialization and scrub admission after next181 snapshots; does not repeat next181 snapshot admission, next178 publication receipts, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next184',
            'cursor_summary' => $this->cursorSummary(),
            'cursor_errors' => $this->cursorErrors(),
            'cursor_rows' => $this->cursorRows,
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
            array_filter($this->cursorRows, $predicate),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCursorRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext181Plan $basePlan): array
    {
        $rows = [];
        $ordinal = 0;
        foreach ($basePlan->snapshotRows() as $row) {
            $admitted = $row['next_reader_admitted'] === true;
            if ($admitted) {
                $ordinal++;
            }

            $leafScrub = $row['snapshot_kind'] === 'leaf-freeblock-current-source';
            $overflowTerminal = $row['snapshot_kind'] === 'overflow-tail-current-source';
            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'source_ordinal' => $admitted ? $ordinal : null,
                'cursor_state' => $admitted ? 'materialized-current-source' : 'excluded-truncated-tail',
                'snapshot_kind' => $row['snapshot_kind'],
                'quarantine_reason' => $row['quarantine_reason'],
                'leaf_freeblock_scrub_required' => $leafScrub,
                'freeblock_receipt_carried' => $row['freeblock_receipt_carried'],
                'overflow_terminal_page' => $overflowTerminal,
                'next_pointer_receipt_carried' => $row['next_pointer_receipt_carried'],
                'pointer_map_receipt_carried' => $row['pointer_map_receipt_carried'],
                'final_materialized' => $row['final_materialized'],
                'final_next_page' => $row['final_next_page'],
                'final_pointer_map_type' => $row['final_pointer_map_type'],
                'final_pointer_map_parent' => $row['final_pointer_map_parent'],
                'source_replayable' => $admitted && $row['final_materialized'] === true,
                'truncated_tail_fenced' => !$admitted && $row['quarantine_reason'] === 'truncated-tail-fenced-from-next-reader',
                'scrub_receipt_key' => self::signature([
                    (int) $row['page_number'],
                    $admitted ? '1' : '0',
                    $row['snapshot_kind'],
                    $leafScrub ? ($row['freeblock_receipt_carried'] ? 'freeblock-ok' : 'freeblock-missing') : 'no-freeblock',
                    $overflowTerminal ? ($row['next_pointer_receipt_carried'] ? 'terminal-next-ok' : 'terminal-next-missing') : 'no-terminal-next',
                    $row['pointer_map_receipt_carried'] ? 'pointer-map-ok' : 'no-pointer-map',
                ]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function cursorErrorsForRows(array $rows): array
    {
        $errors = [];
        $expectedOrdinal = 0;
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['cursor_state'] === 'materialized-current-source') {
                $expectedOrdinal++;
                if ($row['source_ordinal'] !== $expectedOrdinal) {
                    $errors[] = "page {$pageNumber} has a non-contiguous current-source ordinal";
                }
                if ($row['source_replayable'] !== true || $row['final_materialized'] !== true) {
                    $errors[] = "page {$pageNumber} is not replayable from the current source";
                }
            } elseif ($row['cursor_state'] === 'excluded-truncated-tail') {
                if ($row['source_ordinal'] !== null || $row['truncated_tail_fenced'] !== true) {
                    $errors[] = "truncated page {$pageNumber} was not fenced from the current-source cursor";
                }
            } else {
                $errors[] = "page {$pageNumber} has an unknown current-source cursor state";
            }

            if ($row['leaf_freeblock_scrub_required'] === true && $row['freeblock_receipt_carried'] !== true) {
                $errors[] = "leaf page {$pageNumber} is missing its secure-delete freeblock scrub receipt";
            }
            if ($row['overflow_terminal_page'] === true) {
                if ($row['final_next_page'] !== 0 || $row['next_pointer_receipt_carried'] !== true) {
                    $errors[] = "overflow terminal page {$pageNumber} did not carry the final zero next-pointer receipt";
                }
            }
            if ($row['cursor_state'] === 'excluded-truncated-tail' && $row['pointer_map_receipt_carried'] !== true) {
                $errors[] = "excluded truncated page {$pageNumber} did not carry a pointer-map fence receipt";
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
