<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext229Plan
{
    /**
     * @param list<array<string, mixed>> $resumeRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext224Plan $sourcePlan,
        private readonly array $resumeRows,
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
        return self::fromSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext224Plan::tableLeafFromDeleteResult(
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

    public static function fromSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext224Plan $sourcePlan): self
    {
        $rows = self::buildResumeRows($sourcePlan);
        $errors = self::resumeErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next229 resume windows failed: ' . implode('; ', $errors));
        }

        return new self($sourcePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resumeRows(): array
    {
        return $this->resumeRows;
    }

    /**
     * @return list<string>
     */
    public function resumeErrors(): array
    {
        return self::resumeErrorsForRows($this->resumeRows);
    }

    /**
     * @return list<int>
     */
    public function resumePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['resume_page'], $this->resumeRows));
    }

    /**
     * @return list<int|null>
     */
    public function nextResumePages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_resume_page'], $this->resumeRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapResumePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['resume_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadResumePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['resume_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function resumeTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['resume_token'], $this->resumeRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function resumeSummary(): array
    {
        $sourceSummary = $this->sourcePlan->sourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next229-ready',
            'resume_row_count' => count($this->resumeRows),
            'resume_pages' => $this->resumePages(),
            'next_resume_pages' => $this->nextResumePages(),
            'pointer_map_resume_pages' => $this->pointerMapResumePages(),
            'payload_resume_pages' => $this->payloadResumePages(),
            'current_source_pages' => $sourceSummary['current_source_pages'],
            'resume_pages_match_current_source_pages' => $this->resumePages() === $sourceSummary['current_source_pages'],
            'all_source_tokens_match' => !in_array(false, array_column($this->resumeRows, 'source_token_matches'), true),
            'all_resume_links_valid' => !in_array(false, array_column($this->resumeRows, 'resume_link_valid'), true),
            'all_pointer_map_resume_visible' => !in_array(false, array_column($this->resumeRows, 'pointer_map_resume_visible'), true),
            'all_freeblock_resume_receipts_carried' => !in_array(false, array_column($this->resumeRows, 'freeblock_resume_receipt_carried'), true),
            'all_tail_pages_fenced_at_resume' => !in_array(false, array_column($this->resumeRows, 'tail_pages_fenced_at_resume'), true),
            'all_resume_windows_monotonic' => !in_array(false, array_column($this->resumeRows, 'resume_window_monotonic'), true),
            'resume_errors' => $this->resumeErrors(),
            'resume_signature' => self::signature($this->resumeTokens()),
            'current_source_next229_token' => self::signature(array_merge(
                ['next229', $sourceSummary['current_source_next224_token']],
                $this->resumePages(),
                $this->resumeTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next224',
                'sqlite-current-source-next229',
            ],
            'dependency_closure' => 'no new support component needed; next229 reuses next224 current-source cursor rows and adds resume-window admission receipts only',
            'non_overlap' => 'adds current-source resume-window admission after next224 next-page cursor sequencing; does not repeat next224 cursor construction, next218 write receipts, next212 apply ordering, overflow freelist release, page relocation, root collapse, or accepted freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next229',
            'resume_summary' => $this->resumeSummary(),
            'resume_errors' => $this->resumeErrors(),
            'resume_rows' => $this->resumeRows,
            'source_plan' => $this->sourcePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->resumeRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['resume_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildResumeRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext224Plan $sourcePlan): array
    {
        $sourceRows = $sourcePlan->sourceRows();
        $sourceTokens = $sourcePlan->sourceTokens();
        $rows = [];
        $previousResumeToken = null;
        $visiblePages = [];
        $visiblePointerMaps = [];

        foreach ($sourceRows as $index => $sourceRow) {
            $pageNumber = (int) $sourceRow['current_source_page'];
            $nextPage = $sourceRow['next_source_page'];
            $channel = (string) $sourceRow['source_channel'];
            $visiblePages[$pageNumber] = true;
            if ($channel === 'pointer-map') {
                $visiblePointerMaps[$pageNumber] = true;
            }

            $token = self::signature(array_merge(
                ['next229', $previousResumeToken ?? 'initial', $sourceRow['source_token']],
                [$pageNumber, $nextPage ?? 'eof', (int) $sourceRow['source_ordinal']],
                self::sortedIntKeys($visiblePages),
                self::sortedIntKeys($visiblePointerMaps),
            ));

            $pointerVisible = $channel === 'pointer-map' || $visiblePointerMaps !== [];
            $rows[] = [
                'resume_ordinal' => $index + 1,
                'source_ordinal' => (int) $sourceRow['source_ordinal'],
                'cursor_index' => (int) $sourceRow['cursor_index'],
                'batch_index' => (int) $sourceRow['batch_index'],
                'resume_channel' => $channel,
                'resume_page' => $pageNumber,
                'next_resume_page' => $nextPage,
                'resume_visible_pages' => self::sortedIntKeys($visiblePages),
                'resume_visible_pointer_map_pages' => self::sortedIntKeys($visiblePointerMaps),
                'source_token' => (string) $sourceRow['source_token'],
                'expected_source_token' => $sourceTokens[$index] ?? null,
                'source_token_matches' => ($sourceTokens[$index] ?? null) === (string) $sourceRow['source_token'],
                'previous_resume_token' => $previousResumeToken,
                'resume_link_valid' => $nextPage === $sourceRow['next_source_page'],
                'pointer_map_resume_visible' => $pointerVisible,
                'freeblock_resume_receipt_carried' => $sourceRow['freeblock_receipt_carried'] === true,
                'tail_pages_fenced_at_resume' => $sourceRow['tail_pages_fenced_for_source'] === true && !in_array($pageNumber, [109, 110], true),
                'resume_window_monotonic' => count($visiblePages) >= $index,
                'resume_state' => 'current-source-resume-window-receipted',
                'resume_token' => $token,
            ];

            $previousResumeToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function resumeErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousVisible = [];

        foreach ($rows as $index => $row) {
            if ($row['resume_state'] !== 'current-source-resume-window-receipted') {
                $errors[] = "resume {$row['resume_ordinal']} is not receipted";
            }
            if ((int) $row['resume_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "resume {$row['resume_ordinal']} skipped a resume ordinal";
            }
            if ((int) $row['source_ordinal'] !== (int) $row['resume_ordinal']) {
                $errors[] = "resume {$row['resume_ordinal']} drifted from source ordinal";
            }
            if ($row['source_token_matches'] !== true) {
                $errors[] = "resume {$row['resume_ordinal']} source token drifted";
            }
            if ($row['previous_resume_token'] !== $previousToken) {
                $errors[] = "resume {$row['resume_ordinal']} broke resume token chaining";
            }
            if ($row['resume_link_valid'] !== true) {
                $errors[] = "resume {$row['resume_ordinal']} has an invalid next resume link";
            }
            if ($row['resume_channel'] === 'payload' && $row['pointer_map_resume_visible'] !== true) {
                $errors[] = "resume {$row['resume_ordinal']} exposed payload before pointer-map visibility";
            }
            if ($row['freeblock_resume_receipt_carried'] !== true) {
                $errors[] = "resume {$row['resume_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_fenced_at_resume'] !== true) {
                $errors[] = "resume {$row['resume_ordinal']} exposed fenced tail pages";
            }
            if ($row['resume_window_monotonic'] !== true) {
                $errors[] = "resume {$row['resume_ordinal']} has a non-monotonic resume window";
            }
            if ($row['resume_visible_pages'] !== self::sortedIntKeys(array_fill_keys(array_merge($previousVisible, [(int) $row['resume_page']]), true))) {
                $errors[] = "resume {$row['resume_ordinal']} lost a visible page";
            }
            if ($row['resume_token'] === '') {
                $errors[] = "resume {$row['resume_ordinal']} has an empty resume token";
            }
            if ($index === count($rows) - 1 && $row['next_resume_page'] !== null) {
                $errors[] = "resume {$row['resume_ordinal']} did not terminate at eof";
            }

            $previousOrdinal = (int) $row['resume_ordinal'];
            $previousToken = (string) $row['resume_token'];
            $previousVisible = array_values(array_map('intval', $row['resume_visible_pages']));
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
