<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext257Plan
{
    /**
     * @param list<array<string, mixed>> $advanceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext253Plan $applyPlan,
        private readonly array $advanceRows,
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
        return self::fromApplyPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext253Plan::tableLeafFromDeleteResult(
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

    public static function fromApplyPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext253Plan $applyPlan): self
    {
        $rows = self::buildAdvanceRows($applyPlan);
        $errors = self::advanceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next257 advance failed: ' . implode('; ', $errors));
        }

        return new self($applyPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function advanceRows(): array
    {
        return $this->advanceRows;
    }

    /**
     * @return list<string>
     */
    public function advanceErrors(): array
    {
        return self::advanceErrorsForRows($this->advanceRows);
    }

    /**
     * @return list<int>
     */
    public function advancedPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['advanced_page'], $this->advanceRows));
    }

    /**
     * @return list<int>
     */
    public function committedFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['advance_channel'] === 'freeblock-source-advance');
    }

    /**
     * @return list<int>
     */
    public function committedPointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['advance_channel'] === 'pointer-map-source-advance');
    }

    /**
     * @return array<int, list<int>>
     */
    public function committedPagesByGroup(): array
    {
        $groups = [];
        foreach ($this->advanceRows as $row) {
            $group = (int) $row['advance_group'];
            $groups[$group] ??= [];
            $groups[$group][] = (int) $row['advanced_page'];
        }
        ksort($groups);

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function advanceTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['advance_token'], $this->advanceRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function advanceSummary(): array
    {
        $applySummary = $this->applyPlan->applySummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next257-ready',
            'advance_row_count' => count($this->advanceRows),
            'advanced_pages' => $this->advancedPages(),
            'apply_pages' => $applySummary['apply_pages'],
            'advanced_pages_match_apply' => $this->advancedPages() === $applySummary['apply_pages'],
            'committed_pointer_map_pages' => $this->committedPointerMapPages(),
            'committed_freeblock_pages' => $this->committedFreeblockPages(),
            'committed_pages_by_group' => $this->committedPagesByGroup(),
            'all_apply_tokens_match' => !in_array(false, array_column($this->advanceRows, 'apply_token_matches'), true),
            'all_groups_have_pointer_map_opener' => !in_array(false, array_column($this->advanceRows, 'group_has_pointer_map_opener'), true),
            'all_freeblocks_wait_for_group_pointer_map' => !in_array(false, array_column($this->advanceRows, 'freeblock_waited_for_pointer_map'), true),
            'all_leaf_receipts_committed' => !in_array(false, array_column($this->advanceRows, 'leaf_receipt_committed'), true),
            'all_tail_pages_fenced_until_after_advance' => !in_array(false, array_column($this->advanceRows, 'tail_page_fenced_until_after_advance'), true),
            'all_source_epochs_monotonic' => !in_array(false, array_column($this->advanceRows, 'source_epoch_monotonic'), true),
            'all_advance_links_valid' => !in_array(false, array_column($this->advanceRows, 'advance_link_valid'), true),
            'advance_errors' => $this->advanceErrors(),
            'advance_signature' => self::signature($this->advanceTokens()),
            'current_source_next257_token' => self::signature(array_merge(
                ['next257', $applySummary['current_source_next253_token']],
                $this->advancedPages(),
                array_column($this->advanceRows, 'source_epoch'),
                $this->advanceTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next253',
                'sqlite-current-source-next257',
            ],
            'dependency_closure' => 'no new support component needed; next257 reuses next253 grouped apply rows and records the current-source advance fence after each pointer-map/freeblock group is durable',
            'non_overlap' => 'adds current-source advance fencing after next253 grouped apply rows; does not repeat next253 grouped apply ordering, next249 next-source allocation publication, next248 seal construction, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or WAL/VFS behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next257',
            'advance_summary' => $this->advanceSummary(),
            'advance_errors' => $this->advanceErrors(),
            'advance_rows' => $this->advanceRows,
            'apply_plan' => $this->applyPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->advanceRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['advanced_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildAdvanceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext253Plan $applyPlan): array
    {
        $applyRows = $applyPlan->applyRows();
        $applyTokens = $applyPlan->applyTokens();
        $rows = [];
        $previousAdvanceToken = null;
        $previousEpoch = 0;
        $groupPointerMapPages = [];

        foreach ($applyRows as $index => $applyRow) {
            $ordinal = $index + 1;
            $pageNumber = (int) $applyRow['apply_page'];
            $group = (int) $applyRow['apply_group'];
            $isPointerMap = $applyRow['apply_channel'] === 'pointer-map-apply';
            $advanceChannel = $isPointerMap ? 'pointer-map-source-advance' : 'freeblock-source-advance';

            if ($isPointerMap) {
                $groupPointerMapPages[$group] = $pageNumber;
            }

            $sourceEpoch = $previousEpoch + ($isPointerMap ? 2 : 1);
            $token = self::signature([
                'next257',
                $ordinal,
                $previousAdvanceToken ?? 'initial',
                $applyRow['apply_token'],
                $pageNumber,
                $advanceChannel,
                $group,
                $groupPointerMapPages[$group] ?? 0,
                $sourceEpoch,
            ]);

            $rows[] = [
                'advance_ordinal' => $ordinal,
                'apply_ordinal' => (int) $applyRow['apply_ordinal'],
                'advanced_page' => $pageNumber,
                'advance_channel' => $advanceChannel,
                'advance_group' => $group,
                'group_pointer_map_page' => $groupPointerMapPages[$group] ?? null,
                'source_apply_token' => (string) $applyRow['apply_token'],
                'expected_apply_token' => $applyTokens[$index] ?? null,
                'apply_token_matches' => ($applyTokens[$index] ?? null) === (string) $applyRow['apply_token'],
                'previous_advance_token' => $previousAdvanceToken,
                'source_epoch' => $sourceEpoch,
                'previous_source_epoch' => $previousEpoch,
                'group_has_pointer_map_opener' => isset($groupPointerMapPages[$group]),
                'freeblock_waited_for_pointer_map' => $isPointerMap || isset($groupPointerMapPages[$group]),
                'leaf_receipt_committed' => $isPointerMap || $applyRow['leaf_receipt_ready_at_apply'] === true,
                'tail_page_fenced_until_after_advance' => $applyRow['tail_page_still_fenced_at_apply'] === true,
                'source_epoch_monotonic' => $sourceEpoch > $previousEpoch,
                'advance_link_valid' => $applyRow['previous_apply_token'] === ($applyRows[$index - 1]['apply_token'] ?? null),
                'advance_state' => 'current-source-next257-advance-ready',
                'advance_token' => $token,
            ];

            $previousEpoch = $sourceEpoch;
            $previousAdvanceToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function advanceErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousEpoch = 0;

        foreach ($rows as $row) {
            if ($row['advance_state'] !== 'current-source-next257-advance-ready') {
                $errors[] = "advance {$row['advance_ordinal']} is not ready";
            }
            if ((int) $row['advance_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "advance {$row['advance_ordinal']} skipped an ordinal";
            }
            if ((int) $row['apply_ordinal'] !== (int) $row['advance_ordinal']) {
                $errors[] = "advance {$row['advance_ordinal']} drifted from apply ordinal";
            }
            if ($row['apply_token_matches'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} apply token drifted";
            }
            if ($row['previous_advance_token'] !== $previousToken) {
                $errors[] = "advance {$row['advance_ordinal']} broke token chaining";
            }
            if ($row['group_has_pointer_map_opener'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} lacks a pointer-map opener";
            }
            if ($row['freeblock_waited_for_pointer_map'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} exposed a freeblock before pointer-map advance";
            }
            if ($row['leaf_receipt_committed'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} lost the leaf receipt";
            }
            if ($row['tail_page_fenced_until_after_advance'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} admitted a fenced tail page too early";
            }
            if ($row['source_epoch_monotonic'] !== true || (int) $row['source_epoch'] <= $previousEpoch) {
                $errors[] = "advance {$row['advance_ordinal']} did not advance the source epoch";
            }
            if ($row['advance_link_valid'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} broke apply link continuity";
            }
            if ($row['advance_token'] === '') {
                $errors[] = "advance {$row['advance_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['advance_ordinal'];
            $previousEpoch = (int) $row['source_epoch'];
            $previousToken = (string) $row['advance_token'];
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
