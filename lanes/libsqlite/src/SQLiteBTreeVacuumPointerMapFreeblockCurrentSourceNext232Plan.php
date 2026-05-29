<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Plan
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $resumePlan,
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
        return self::fromResumePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext229(
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

    public static function fromResumePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $resumePlan): self
    {
        $rows = self::buildHandoffRows($resumePlan);
        $errors = self::handoffErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next232 handoff gate failed: ' . implode('; ', $errors));
        }

        return new self($resumePlan, $rows);
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
        return self::handoffErrorsForRows($this->handoffRows);
    }

    /**
     * @return list<int>
     */
    public function handoffPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['handoff_page'], $this->handoffRows));
    }

    /**
     * @return list<int|null>
     */
    public function nextHandoffPages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_handoff_page'], $this->handoffRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapHandoffPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadHandoffPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function handoffTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['handoff_token'], $this->handoffRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function handoffSummary(): array
    {
        $resumeSummary = $this->resumePlan->resumeSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next232-ready',
            'handoff_row_count' => count($this->handoffRows),
            'handoff_pages' => $this->handoffPages(),
            'next_handoff_pages' => $this->nextHandoffPages(),
            'pointer_map_handoff_pages' => $this->pointerMapHandoffPages(),
            'payload_handoff_pages' => $this->payloadHandoffPages(),
            'resume_pages' => $resumeSummary['resume_pages'],
            'handoff_pages_match_resume_pages' => $this->handoffPages() === $resumeSummary['resume_pages'],
            'all_resume_tokens_match' => !in_array(false, array_column($this->handoffRows, 'resume_token_matches'), true),
            'all_handoff_links_valid' => !in_array(false, array_column($this->handoffRows, 'handoff_link_valid'), true),
            'all_pointer_map_handoffs_visible' => !in_array(false, array_column($this->handoffRows, 'pointer_map_handoff_visible'), true),
            'all_payload_handoffs_admitted_after_pointer_map' => !in_array(false, array_column($this->handoffRows, 'payload_handoff_admitted_after_pointer_map'), true),
            'all_freeblock_handoff_receipts_carried' => !in_array(false, array_column($this->handoffRows, 'freeblock_handoff_receipt_carried'), true),
            'all_tail_pages_fenced_for_handoff' => !in_array(false, array_column($this->handoffRows, 'tail_pages_fenced_for_handoff'), true),
            'all_handoff_windows_monotonic' => !in_array(false, array_column($this->handoffRows, 'handoff_window_monotonic'), true),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_signature' => self::signature($this->handoffTokens()),
            'current_source_next232_token' => self::signature(array_merge(
                ['next232', $resumeSummary['current_source_next229_token']],
                $this->handoffPages(),
                $this->handoffTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next229',
                'sqlite-current-source-next232',
            ],
            'dependency_closure' => 'no new support component needed; next232 reuses next229 resume rows and adds next-writer handoff admission only',
            'non_overlap' => 'adds next-writer handoff admission after next229 resume-window receipts; does not repeat next229 resume construction, next224 cursor sequencing, next218 write receipts, overflow freelist release, page relocation, root collapse, or accepted freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next232',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
            'resume_plan' => $this->resumePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->handoffRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['handoff_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $resumePlan): array
    {
        $resumeRows = $resumePlan->resumeRows();
        $resumeTokens = $resumePlan->resumeTokens();
        $rows = [];
        $previousHandoffToken = null;
        $admittedPages = [];
        $admittedPointerMaps = [];

        foreach ($resumeRows as $index => $resumeRow) {
            $pageNumber = (int) $resumeRow['resume_page'];
            $channel = (string) $resumeRow['resume_channel'];
            $admittedPages[$pageNumber] = true;
            if ($channel === 'pointer-map') {
                $admittedPointerMaps[$pageNumber] = true;
            }

            $pointerVisible = $channel === 'pointer-map' || $admittedPointerMaps !== [];
            $payloadAdmitted = $channel !== 'payload' || $pointerVisible;
            $token = self::signature(array_merge(
                ['next232', $previousHandoffToken ?? 'initial', $resumeRow['resume_token']],
                [$pageNumber, $resumeRow['next_resume_page'] ?? 'eof', (int) $resumeRow['resume_ordinal']],
                self::sortedIntKeys($admittedPages),
                self::sortedIntKeys($admittedPointerMaps),
            ));

            $rows[] = [
                'handoff_ordinal' => $index + 1,
                'resume_ordinal' => (int) $resumeRow['resume_ordinal'],
                'cursor_index' => (int) $resumeRow['cursor_index'],
                'batch_index' => (int) $resumeRow['batch_index'],
                'handoff_channel' => $channel,
                'handoff_page' => $pageNumber,
                'next_handoff_page' => $resumeRow['next_resume_page'],
                'handoff_admitted_pages' => self::sortedIntKeys($admittedPages),
                'handoff_admitted_pointer_map_pages' => self::sortedIntKeys($admittedPointerMaps),
                'resume_token' => (string) $resumeRow['resume_token'],
                'expected_resume_token' => $resumeTokens[$index] ?? null,
                'resume_token_matches' => ($resumeTokens[$index] ?? null) === (string) $resumeRow['resume_token'],
                'previous_handoff_token' => $previousHandoffToken,
                'handoff_link_valid' => $resumeRow['next_resume_page'] === ($resumeRows[$index + 1]['resume_page'] ?? null),
                'pointer_map_handoff_visible' => $pointerVisible,
                'payload_handoff_admitted_after_pointer_map' => $payloadAdmitted,
                'freeblock_handoff_receipt_carried' => $resumeRow['freeblock_resume_receipt_carried'] === true,
                'tail_pages_fenced_for_handoff' => $resumeRow['tail_pages_fenced_at_resume'] === true && !in_array($pageNumber, [109, 110], true),
                'handoff_window_monotonic' => count($admittedPages) >= $index,
                'handoff_state' => 'current-source-next-writer-handoff-admitted',
                'handoff_token' => $token,
            ];

            $previousHandoffToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function handoffErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousAdmitted = [];

        foreach ($rows as $index => $row) {
            if ($row['handoff_state'] !== 'current-source-next-writer-handoff-admitted') {
                $errors[] = "handoff {$row['handoff_ordinal']} is not admitted";
            }
            if ((int) $row['handoff_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "handoff {$row['handoff_ordinal']} skipped a handoff ordinal";
            }
            if ((int) $row['resume_ordinal'] !== (int) $row['handoff_ordinal']) {
                $errors[] = "handoff {$row['handoff_ordinal']} drifted from resume ordinal";
            }
            if ($row['resume_token_matches'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} resume token drifted";
            }
            if ($row['previous_handoff_token'] !== $previousToken) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke handoff token chaining";
            }
            if ($row['handoff_link_valid'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} has an invalid next handoff link";
            }
            if ($row['pointer_map_handoff_visible'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} has no visible pointer-map admission";
            }
            if ($row['payload_handoff_admitted_after_pointer_map'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} admitted payload before pointer-map";
            }
            if ($row['freeblock_handoff_receipt_carried'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_fenced_for_handoff'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} exposed fenced tail pages";
            }
            if ($row['handoff_window_monotonic'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} has a non-monotonic admission window";
            }
            if ($row['handoff_admitted_pages'] !== self::sortedIntKeys(array_fill_keys(array_merge($previousAdmitted, [(int) $row['handoff_page']]), true))) {
                $errors[] = "handoff {$row['handoff_ordinal']} lost an admitted page";
            }
            if ($row['handoff_token'] === '') {
                $errors[] = "handoff {$row['handoff_ordinal']} has an empty handoff token";
            }
            if ($index === count($rows) - 1 && $row['next_handoff_page'] !== null) {
                $errors[] = "handoff {$row['handoff_ordinal']} did not terminate at eof";
            }

            $previousOrdinal = (int) $row['handoff_ordinal'];
            $previousToken = (string) $row['handoff_token'];
            $previousAdmitted = array_values(array_map('intval', $row['handoff_admitted_pages']));
        }

        return $errors;
    }

    /**
     * @param array<int, bool> $values
     * @return list<int>
     */
    private static function sortedIntKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return array_values(array_map('intval', $keys));
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
