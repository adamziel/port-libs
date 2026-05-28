<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext228Plan
{
    /**
     * @param list<array<string, mixed>> $drainRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext224Plan $basePlan,
        private readonly array $drainRows,
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

    public static function fromSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext224Plan $basePlan): self
    {
        $rows = self::buildDrainRows($basePlan);
        $errors = self::drainErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next228 drain failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function drainRows(): array
    {
        return $this->drainRows;
    }

    /**
     * @return list<string>
     */
    public function drainErrors(): array
    {
        return self::drainErrorsForRows($this->drainRows);
    }

    /**
     * @return list<int>
     */
    public function drainedPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['drained_page'], $this->drainRows));
    }

    /**
     * @return list<int|null>
     */
    public function resumeAfterPages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['resume_after_page'], $this->drainRows));
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapPages(): array
    {
        $pages = [];
        foreach ($this->drainRows as $row) {
            if ($row['pointer_map_revisit'] === true) {
                $pages[(int) $row['drained_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<string>
     */
    public function drainTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['drain_token'], $this->drainRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function drainSummary(): array
    {
        $sourceSummary = $this->basePlan->sourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next228-ready',
            'drain_row_count' => count($this->drainRows),
            'drained_pages' => $this->drainedPages(),
            'resume_after_pages' => $this->resumeAfterPages(),
            'duplicate_pointer_map_pages' => $this->duplicatePointerMapPages(),
            'source_pages' => $sourceSummary['current_source_pages'],
            'drain_pages_match_source_pages' => $this->drainedPages() === $sourceSummary['current_source_pages'],
            'all_resume_links_match_source_next' => !in_array(false, array_column($this->drainRows, 'resume_link_matches_source_next'), true),
            'all_pointer_map_revisits_ordered' => !in_array(false, array_column($this->drainRows, 'pointer_map_revisit_ordered'), true),
            'all_freeblock_receipts_drained' => !in_array(false, array_column($this->drainRows, 'freeblock_receipt_drained'), true),
            'all_tail_pages_fenced_at_drain' => !in_array(false, array_column($this->drainRows, 'tail_pages_fenced_at_drain'), true),
            'eof_drain_token' => $this->drainRows[count($this->drainRows) - 1]['drain_token'] ?? null,
            'drain_errors' => $this->drainErrors(),
            'drain_signature' => self::signature($this->drainTokens()),
            'current_source_next228_token' => self::signature(array_merge(
                ['next228', $sourceSummary['current_source_next224_token']],
                $this->drainedPages(),
                $this->resumeAfterPages(),
                $this->drainTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next224',
                'sqlite-current-source-next228',
            ],
            'dependency_closure' => 'no new support component needed; next228 reuses next224 current-source next-page cursor receipts and adds drain/finalization metadata only',
            'non_overlap' => 'adds current-source drain finalization after next224 cursor sequencing; does not repeat next224 next-page links, next218 write receipt construction, next212 apply ordering, overflow freelist release, page relocation, root collapse, or accepted freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next228',
            'drain_summary' => $this->drainSummary(),
            'drain_errors' => $this->drainErrors(),
            'drain_rows' => $this->drainRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildDrainRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext224Plan $basePlan): array
    {
        $sourceRows = $basePlan->sourceRows();
        $rows = [];
        $previousDrainToken = null;
        $seenPointerMaps = [];
        $sourceTokens = $basePlan->sourceTokens();

        foreach ($sourceRows as $index => $sourceRow) {
            $page = (int) $sourceRow['current_source_page'];
            $resumeAfter = $sourceRow['next_source_page'];
            $channel = (string) $sourceRow['source_channel'];
            $pointerMapRevisit = $channel === 'pointer-map' && isset($seenPointerMaps[$page]);
            if ($channel === 'pointer-map') {
                $seenPointerMaps[$page] = true;
            }

            $token = self::signature(array_merge(
                ['next228', $previousDrainToken ?? 'initial', $sourceRow['source_token']],
                [$page, $resumeAfter ?? 'eof', $channel, $pointerMapRevisit],
                self::sortedIntKeys($seenPointerMaps),
            ));

            $rows[] = [
                'drain_ordinal' => $index + 1,
                'source_ordinal' => (int) $sourceRow['source_ordinal'],
                'source_channel' => $channel,
                'drained_page' => $page,
                'resume_after_page' => $resumeAfter,
                'source_token' => (string) $sourceRow['source_token'],
                'expected_source_token' => $sourceTokens[$index] ?? null,
                'source_token_matches' => ($sourceTokens[$index] ?? null) === (string) $sourceRow['source_token'],
                'previous_drain_token' => $previousDrainToken,
                'resume_link_matches_source_next' => $resumeAfter === $sourceRow['next_source_page'],
                'pointer_map_revisit' => $pointerMapRevisit,
                'pointer_map_revisit_ordered' => !$pointerMapRevisit || $seenPointerMaps[$page] === true,
                'visible_pointer_map_pages' => $sourceRow['visible_pointer_map_pages'],
                'freeblock_receipt_drained' => $sourceRow['freeblock_receipt_carried'] === true,
                'tail_pages_fenced_at_drain' => $sourceRow['tail_pages_fenced_for_source'] === true && !in_array($page, [109, 110], true),
                'drain_state' => 'current-source-drained-for-next-writer',
                'drain_token' => $token,
            ];

            $previousDrainToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function drainErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $index => $row) {
            if ($row['drain_state'] !== 'current-source-drained-for-next-writer') {
                $errors[] = "drain {$row['drain_ordinal']} is not finalized";
            }
            if ((int) $row['drain_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "drain {$row['drain_ordinal']} skipped a drain ordinal";
            }
            if ((int) $row['source_ordinal'] !== (int) $row['drain_ordinal']) {
                $errors[] = "drain {$row['drain_ordinal']} drifted from source ordinal";
            }
            if ($row['source_token_matches'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} source token drifted";
            }
            if ($row['previous_drain_token'] !== $previousToken) {
                $errors[] = "drain {$row['drain_ordinal']} broke drain token chaining";
            }
            if ($row['resume_link_matches_source_next'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} has an invalid resume link";
            }
            if ($row['pointer_map_revisit_ordered'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} revisited a pointer-map page before first visibility";
            }
            if ($row['freeblock_receipt_drained'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_fenced_at_drain'] !== true) {
                $errors[] = "drain {$row['drain_ordinal']} exposed fenced tail pages";
            }
            if ($row['drain_token'] === '') {
                $errors[] = "drain {$row['drain_ordinal']} has an empty drain token";
            }
            if ($index === count($rows) - 1 && $row['resume_after_page'] !== null) {
                $errors[] = "drain {$row['drain_ordinal']} did not terminate at eof";
            }

            $previousOrdinal = (int) $row['drain_ordinal'];
            $previousToken = (string) $row['drain_token'];
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
