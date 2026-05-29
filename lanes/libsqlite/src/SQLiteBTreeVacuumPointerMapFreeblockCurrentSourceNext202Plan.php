<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext202Plan
{
    /**
     * @param list<array<string, mixed>> $cursorRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextWriterVariant $basePlan,
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
        int $batchSize = 2,
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextWriterVariant::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextWriterVariant $basePlan): self
    {
        $rows = self::buildCursorRows($basePlan);
        $errors = self::cursorErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next202 cursor failed: ' . implode('; ', $errors));
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
    public function sourceWritablePages(): array
    {
        $pages = [];
        foreach ($this->cursorRows as $row) {
            foreach ($row['writer_visible_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        $keys = array_keys($pages);
        sort($keys);

        return array_values(array_map('intval', $keys));
    }

    /**
     * @return list<string>
     */
    public function resumeTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['resume_token'], $this->cursorRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function cursorSummary(): array
    {
        $handoffSummary = $this->basePlan->sourceNextSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next202-ready',
            'cursor_row_count' => count($this->cursorRows),
            'source_writable_pages' => $this->sourceWritablePages(),
            'base_next_writable_pages' => $handoffSummary['next_writable_pages'],
            'cursor_matches_base_writable_pages' => $this->sourceWritablePages() === $handoffSummary['next_writable_pages'],
            'newly_visible_page_batches' => array_values(array_map(static fn (array $row): array => $row['newly_visible_pages'], $this->cursorRows)),
            'pointer_map_guard_batches' => array_values(array_map(static fn (array $row): array => $row['pointer_map_guard_pages'], $this->cursorRows)),
            'payload_page_batches' => array_values(array_map(static fn (array $row): array => $row['payload_pages'], $this->cursorRows)),
            'all_monotonic' => !in_array(false, array_column($this->cursorRows, 'monotonic_writer_visibility'), true),
            'all_pointer_maps_before_payload' => !in_array(false, array_column($this->cursorRows, 'pointer_map_precedes_payload'), true),
            'all_freeblock_receipts_visible' => !in_array(false, array_column($this->cursorRows, 'leaf_freeblock_cursor_valid'), true),
            'all_fenced_tail_pages_guarded' => !in_array(false, array_column($this->cursorRows, 'fenced_tail_pages_guarded'), true),
            'resume_tokens' => $this->resumeTokens(),
            'resume_signature' => self::signature($this->resumeTokens()),
            'current_source_next202_token' => self::signature(array_merge(
                ['next202', $handoffSummary['next_writer_source_token']],
                $this->sourceWritablePages(),
                $this->resumeTokens(),
            )),
            'cursor_errors' => $this->cursorErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next196',
                'sqlite-current-source-next202',
            ],
            'dependency_closure' => 'no new support component needed; next202 reuses next196 source-next handoff rows, pointer-map carry-forward flags, leaf freeblock receipts, and fenced-tail guards',
            'non_overlap' => 'adds current-source cursor finalization after next196 writer handoff; does not repeat next196 handoff, next192 validation, next189 checkpoint rows, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next202',
            'cursor_summary' => $this->cursorSummary(),
            'cursor_errors' => $this->cursorErrors(),
            'cursor_rows' => $this->cursorRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCursorRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextWriterVariant $basePlan): array
    {
        $rows = [];
        $previousVisible = [];
        $previousResumeToken = null;

        foreach ($basePlan->handoffRows() as $row) {
            $visible = array_values(array_map('intval', $row['next_writable_pages']));
            $visibleSet = array_fill_keys($visible, true);
            $newlyVisible = [];
            foreach ($visible as $pageNumber) {
                if (!isset($previousVisible[$pageNumber])) {
                    $newlyVisible[] = $pageNumber;
                }
            }

            $pointerMapPages = array_values(array_map('intval', $row['visible_pointer_map_pages']));
            $payloadPages = array_values(array_map('intval', $row['visible_payload_pages']));
            $resumeToken = self::signature(array_merge(
                ['next202', (int) $row['batch_index'], $previousResumeToken ?? 'initial', (string) $row['source_next_token']],
                $visible,
                $newlyVisible,
                $pointerMapPages,
                $payloadPages,
            ));

            $rows[] = [
                'cursor_index' => count($rows),
                'batch_index' => (int) $row['batch_index'],
                'previous_resume_token' => $previousResumeToken,
                'source_next_token' => (string) $row['source_next_token'],
                'writer_visible_pages' => $visible,
                'newly_visible_pages' => $newlyVisible,
                'pointer_map_guard_pages' => $pointerMapPages,
                'payload_pages' => $payloadPages,
                'monotonic_writer_visibility' => count(array_diff(array_keys($previousVisible), $visible)) === 0,
                'pointer_map_precedes_payload' => $payloadPages === [] || $pointerMapPages !== [],
                'leaf_freeblock_cursor_valid' => $row['freeblock_receipt_carried_forward'] === true,
                'fenced_tail_pages_guarded' => $row['fenced_pages_blocked_from_next_writer'] === true,
                'handoff_token_valid' => $row['source_next_state'] === 'next-writer-source-ready' && $row['validation_token_matches'] === true,
                'resume_token' => $resumeToken,
                'cursor_state' => 'current-source-cursor-ready',
            ];

            $previousVisible = $visibleSet;
            $previousResumeToken = $resumeToken;
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
        $previousToken = null;

        foreach ($rows as $row) {
            if ($row['cursor_state'] !== 'current-source-cursor-ready') {
                $errors[] = "cursor {$row['cursor_index']} is not ready";
            }
            if ($row['handoff_token_valid'] !== true) {
                $errors[] = "cursor {$row['cursor_index']} inherited an invalid handoff token";
            }
            if ($row['monotonic_writer_visibility'] !== true) {
                $errors[] = "cursor {$row['cursor_index']} lost a previously visible writer page";
            }
            if ($row['pointer_map_precedes_payload'] !== true) {
                $errors[] = "cursor {$row['cursor_index']} exposes payload pages without pointer-map guards";
            }
            if ($row['leaf_freeblock_cursor_valid'] !== true) {
                $errors[] = "cursor {$row['cursor_index']} lost the leaf freeblock receipt";
            }
            if ($row['fenced_tail_pages_guarded'] !== true) {
                $errors[] = "cursor {$row['cursor_index']} exposes fenced tail pages";
            }
            if ($row['previous_resume_token'] !== $previousToken) {
                $errors[] = "cursor {$row['cursor_index']} broke resume-token chaining";
            }
            if ($row['newly_visible_pages'] === []) {
                $errors[] = "cursor {$row['cursor_index']} did not advance visible source pages";
            }
            if ($row['resume_token'] === '') {
                $errors[] = "cursor {$row['cursor_index']} has an empty resume token";
            }

            $previousToken = (string) $row['resume_token'];
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
