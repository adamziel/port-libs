<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Plan
{
    /**
     * @param list<array<string, mixed>> $checkpointRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $checkpointRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext186(
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
        $rows = self::buildCheckpointRows($basePlan);
        $errors = self::checkpointErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next189 checkpoint failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checkpointRows(): array
    {
        return $this->checkpointRows;
    }

    /**
     * @return list<string>
     */
    public function checkpointErrors(): array
    {
        return self::checkpointErrorsForRows($this->checkpointRows);
    }

    /**
     * @return list<int>
     */
    public function cumulativeHighWaterPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['current_source_high_water_page'], $this->checkpointRows));
    }

    /**
     * @return list<int>
     */
    public function newlyVisiblePages(): array
    {
        $pages = [];
        foreach ($this->checkpointRows as $row) {
            foreach ($row['newly_visible_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        $pages = array_keys($pages);
        sort($pages);

        return $pages;
    }

    /**
     * @return list<string>
     */
    public function checkpointTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['checkpoint_token'], $this->checkpointRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function checkpointSummary(): array
    {
        $cursorSummary = $this->basePlan->cursorSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next189-ready',
            'checkpoint_row_count' => count($this->checkpointRows),
            'visible_current_source_pages' => $cursorSummary['visible_current_source_pages'],
            'newly_visible_pages' => $this->newlyVisiblePages(),
            'cumulative_high_water_pages' => $this->cumulativeHighWaterPages(),
            'final_visible_page_count' => $this->finalVisiblePageCount(),
            'fenced_pages_visible' => $cursorSummary['fenced_pages_visible'],
            'all_pointer_maps_precede_payload' => !in_array(false, array_column($this->checkpointRows, 'pointer_map_visible_before_payload'), true),
            'all_resume_tokens_unique' => !in_array(false, array_column($this->checkpointRows, 'resume_token_unique'), true),
            'all_deleted_cells_hidden' => !in_array(false, array_column($this->checkpointRows, 'deleted_cell_hidden'), true),
            'all_fenced_pages_hidden' => !in_array(false, array_column($this->checkpointRows, 'fenced_pages_hidden'), true),
            'checkpoint_tokens' => $this->checkpointTokens(),
            'checkpoint_signature' => self::signature($this->checkpointTokens()),
            'current_source_restart_token' => self::signature(array_merge(
                ['next189', $cursorSummary['current_source_revision']],
                $this->cumulativeHighWaterPages(),
                $this->checkpointTokens(),
            )),
            'checkpoint_errors' => $this->checkpointErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next186',
                'sqlite-current-source-next189',
            ],
            'dependency_closure' => 'no new support component needed; next189 reuses next186 cursor visibility, resume tokens, committed page hashes, and auto-vacuum pointer-map/freeblock metadata',
            'non_overlap' => 'adds resumable current-source checkpoint fencing after next186 cursor visibility; does not repeat next186 visibility rows, next183 commit receipts, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next189',
            'checkpoint_summary' => $this->checkpointSummary(),
            'checkpoint_errors' => $this->checkpointErrors(),
            'checkpoint_rows' => $this->checkpointRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    private function finalVisiblePageCount(): int
    {
        $pages = $this->basePlan->visibleCurrentSourcePages();

        return $pages === [] ? 0 : max($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCheckpointRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $rows = [];
        $seenPages = [];
        $seenTokens = [];
        $previousResumeToken = null;
        $highWater = 0;

        foreach ($basePlan->cursorRows() as $row) {
            $visiblePages = array_values(array_map('intval', $row['visible_current_source_pages']));
            $newPages = [];
            foreach ($visiblePages as $pageNumber) {
                if (!isset($seenPages[$pageNumber])) {
                    $newPages[] = $pageNumber;
                    $seenPages[$pageNumber] = true;
                }
                $highWater = max($highWater, $pageNumber);
            }

            $resumeToken = (string) $row['resume_token'];
            $pointerMaps = array_values(array_map('intval', $row['visible_pointer_map_pages']));
            $payloadPages = array_values(array_unique(array_merge(
                array_map('intval', $row['visible_leaf_freeblock_pages']),
                array_map('intval', $row['visible_overflow_pages']),
            )));
            sort($payloadPages);

            $rows[] = [
                'batch_index' => (int) $row['batch_index'],
                'resume_token' => $resumeToken,
                'previous_resume_token' => $previousResumeToken,
                'resume_token_unique' => !isset($seenTokens[$resumeToken]),
                'visible_current_source_pages' => $visiblePages,
                'newly_visible_pages' => $newPages,
                'current_source_high_water_page' => $highWater,
                'visible_pointer_map_pages' => $pointerMaps,
                'visible_payload_pages' => $payloadPages,
                'pointer_map_visible_before_payload' => $payloadPages === [] || $pointerMaps !== [],
                'deleted_cell_hidden' => $row['deleted_cell_hidden'],
                'fenced_pages_hidden' => $row['fenced_pages_hidden'],
                'page_hash_count' => count($row['page_hashes']),
                'receipt_kinds' => $row['receipt_kinds'],
                'checkpoint_state' => 'current-source-resume-ready',
                'checkpoint_token' => self::signature(array_merge(
                    ['next189', (int) $row['batch_index'], $previousResumeToken ?? 'initial', $resumeToken, $highWater],
                    $newPages,
                    $pointerMaps,
                    $payloadPages,
                    $row['page_hashes'],
                    $row['receipt_kinds'],
                )),
            ];

            $seenTokens[$resumeToken] = true;
            $previousResumeToken = $resumeToken;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function checkpointErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousHighWater = 0;
        foreach ($rows as $row) {
            if ($row['checkpoint_state'] !== 'current-source-resume-ready') {
                $errors[] = "batch {$row['batch_index']} is not ready for current-source resume";
            }
            if ($row['resume_token_unique'] !== true) {
                $errors[] = "batch {$row['batch_index']} reused a current-source resume token";
            }
            if ($row['pointer_map_visible_before_payload'] !== true) {
                $errors[] = "batch {$row['batch_index']} exposes payload pages before pointer-map visibility";
            }
            if ($row['deleted_cell_hidden'] !== true) {
                $errors[] = "batch {$row['batch_index']} exposes the deleted cell during resume";
            }
            if ($row['fenced_pages_hidden'] !== true) {
                $errors[] = "batch {$row['batch_index']} exposes a fenced tail page during resume";
            }
            if ($row['page_hash_count'] < 1) {
                $errors[] = "batch {$row['batch_index']} has no page hashes for resume verification";
            }
            if ((int) $row['current_source_high_water_page'] < $previousHighWater) {
                $errors[] = "batch {$row['batch_index']} moved the current-source high-water page backwards";
            }
            if ($row['checkpoint_token'] === '') {
                $errors[] = "batch {$row['batch_index']} has an empty checkpoint token";
            }
            $previousHighWater = (int) $row['current_source_high_water_page'];
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
