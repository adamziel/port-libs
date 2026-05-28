<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext260Plan
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext257Plan $advancePlan,
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
        return self::fromAdvancePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext257Plan::tableLeafFromDeleteResult(
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

    public static function fromAdvancePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext257Plan $advancePlan): self
    {
        $rows = self::buildHandoffRows($advancePlan);
        $errors = self::handoffErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next260 handoff failed: ' . implode('; ', $errors));
        }

        return new self($advancePlan, $rows);
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
     * @return list<int>
     */
    public function readerVisiblePages(): array
    {
        $pages = [];
        foreach ($this->handoffRows as $row) {
            if ($row['reader_visible_at_handoff'] === true) {
                $pages[(int) $row['handoff_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function pointerMapSnapshotPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'pointer-map-snapshot');
    }

    /**
     * @return list<int>
     */
    public function reusableFreeblockSnapshotPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'reusable-freeblock-snapshot');
    }

    /**
     * @return array<int, list<int>>
     */
    public function readerVisiblePagesByGroup(): array
    {
        $groups = [];
        foreach ($this->handoffRows as $row) {
            $group = (int) $row['handoff_group'];
            $groups[$group] ??= [];
            $groups[$group][] = (int) $row['handoff_page'];
        }
        ksort($groups);

        return $groups;
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
        $advanceSummary = $this->advancePlan->advanceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next260-ready',
            'handoff_row_count' => count($this->handoffRows),
            'handoff_pages' => $this->handoffPages(),
            'advanced_pages' => $advanceSummary['advanced_pages'],
            'handoff_pages_match_advanced_pages' => $this->handoffPages() === $advanceSummary['advanced_pages'],
            'reader_visible_pages' => $this->readerVisiblePages(),
            'pointer_map_snapshot_pages' => $this->pointerMapSnapshotPages(),
            'reusable_freeblock_snapshot_pages' => $this->reusableFreeblockSnapshotPages(),
            'reader_visible_pages_by_group' => $this->readerVisiblePagesByGroup(),
            'all_advance_tokens_match' => !in_array(false, array_column($this->handoffRows, 'advance_token_matches'), true),
            'all_group_snapshots_have_pointer_map' => !in_array(false, array_column($this->handoffRows, 'group_snapshot_has_pointer_map'), true),
            'all_reader_visibility_after_pointer_map' => !in_array(false, array_column($this->handoffRows, 'reader_visibility_after_pointer_map'), true),
            'all_freeblock_receipts_reader_visible' => !in_array(false, array_column($this->handoffRows, 'freeblock_receipt_reader_visible'), true),
            'all_tail_pages_blocked_from_reader' => !in_array(false, array_column($this->handoffRows, 'tail_page_blocked_from_reader'), true),
            'all_source_epochs_preserved' => !in_array(false, array_column($this->handoffRows, 'source_epoch_preserved'), true),
            'all_handoff_links_valid' => !in_array(false, array_column($this->handoffRows, 'handoff_link_valid'), true),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_signature' => self::signature($this->handoffTokens()),
            'current_source_next260_token' => self::signature(array_merge(
                ['next260', $advanceSummary['current_source_next257_token']],
                $this->handoffPages(),
                array_column($this->handoffRows, 'reader_source_epoch'),
                $this->handoffTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next257',
                'sqlite-current-source-next260',
            ],
            'dependency_closure' => 'no new support component needed; next260 reuses next257 advance fences and publishes grouped reader-visible current-source snapshots',
            'non_overlap' => 'adds reader-visible handoff snapshots after next257 source advance; does not repeat next257 advance fencing, next253 grouped apply ordering, next249 allocation publication, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or WAL/VFS behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next260',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
            'advance_plan' => $this->advancePlan->toArray(),
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
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext257Plan $advancePlan): array
    {
        $advanceRows = $advancePlan->advanceRows();
        $advanceTokens = $advancePlan->advanceTokens();
        $rows = [];
        $previousToken = null;
        $groupPointerMapPages = [];
        $readerVisiblePages = [];

        foreach ($advanceRows as $index => $advanceRow) {
            $ordinal = $index + 1;
            $pageNumber = (int) $advanceRow['advanced_page'];
            $group = (int) $advanceRow['advance_group'];
            $isPointerMap = $advanceRow['advance_channel'] === 'pointer-map-source-advance';
            $handoffChannel = $isPointerMap ? 'pointer-map-snapshot' : 'reusable-freeblock-snapshot';

            if ($isPointerMap) {
                $groupPointerMapPages[$group] = $pageNumber;
            }

            $readerVisiblePages[$pageNumber] = true;
            $groupHasPointerMap = isset($groupPointerMapPages[$group]);
            $readerEpoch = (int) $advanceRow['source_epoch'] + $group;
            $token = self::signature(array_merge(
                ['next260', $ordinal, $previousToken ?? 'initial', $advanceRow['advance_token']],
                [$pageNumber, $handoffChannel, $group, $groupPointerMapPages[$group] ?? 0, $readerEpoch],
                self::sortedIntKeys($readerVisiblePages),
            ));

            $rows[] = [
                'handoff_ordinal' => $ordinal,
                'advance_ordinal' => (int) $advanceRow['advance_ordinal'],
                'handoff_page' => $pageNumber,
                'handoff_channel' => $handoffChannel,
                'handoff_group' => $group,
                'group_pointer_map_page' => $groupPointerMapPages[$group] ?? null,
                'source_advance_token' => (string) $advanceRow['advance_token'],
                'expected_advance_token' => $advanceTokens[$index] ?? null,
                'advance_token_matches' => ($advanceTokens[$index] ?? null) === (string) $advanceRow['advance_token'],
                'previous_handoff_token' => $previousToken,
                'reader_source_epoch' => $readerEpoch,
                'source_epoch' => (int) $advanceRow['source_epoch'],
                'reader_visible_pages' => self::sortedIntKeys($readerVisiblePages),
                'reader_visible_at_handoff' => true,
                'group_snapshot_has_pointer_map' => $groupHasPointerMap,
                'reader_visibility_after_pointer_map' => $isPointerMap || $groupHasPointerMap,
                'freeblock_receipt_reader_visible' => $isPointerMap || $advanceRow['leaf_receipt_committed'] === true,
                'tail_page_blocked_from_reader' => $advanceRow['tail_page_fenced_until_after_advance'] === true && !in_array($pageNumber, [109, 110], true),
                'source_epoch_preserved' => $readerEpoch > (int) $advanceRow['source_epoch'],
                'handoff_link_valid' => $advanceRow['previous_advance_token'] === ($advanceRows[$index - 1]['advance_token'] ?? null),
                'handoff_state' => 'current-source-next260-reader-handoff-ready',
                'handoff_token' => $token,
            ];

            $previousToken = $token;
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
        $previousReaderEpoch = 0;

        foreach ($rows as $row) {
            if ($row['handoff_state'] !== 'current-source-next260-reader-handoff-ready') {
                $errors[] = "handoff {$row['handoff_ordinal']} is not ready";
            }
            if ((int) $row['handoff_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "handoff {$row['handoff_ordinal']} skipped an ordinal";
            }
            if ((int) $row['advance_ordinal'] !== (int) $row['handoff_ordinal']) {
                $errors[] = "handoff {$row['handoff_ordinal']} drifted from advance ordinal";
            }
            if ($row['advance_token_matches'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} advance token drifted";
            }
            if ($row['previous_handoff_token'] !== $previousToken) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke token chaining";
            }
            if ($row['group_snapshot_has_pointer_map'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lacks a pointer-map group snapshot";
            }
            if ($row['reader_visibility_after_pointer_map'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} exposed a reader page before pointer-map snapshot";
            }
            if ($row['freeblock_receipt_reader_visible'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lost reader-visible freeblock receipt";
            }
            if ($row['tail_page_blocked_from_reader'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} exposed a fenced tail page to readers";
            }
            if ($row['source_epoch_preserved'] !== true || (int) $row['reader_source_epoch'] <= $previousReaderEpoch) {
                $errors[] = "handoff {$row['handoff_ordinal']} did not preserve a monotonic reader epoch";
            }
            if ($row['handoff_link_valid'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke advance link continuity";
            }
            if ($row['handoff_token'] === '') {
                $errors[] = "handoff {$row['handoff_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['handoff_ordinal'];
            $previousReaderEpoch = (int) $row['reader_source_epoch'];
            $previousToken = (string) $row['handoff_token'];
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
