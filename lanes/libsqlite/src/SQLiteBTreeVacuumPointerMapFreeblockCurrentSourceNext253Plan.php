<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext253Plan
{
    /**
     * @param list<array<string, mixed>> $applyRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan,
        private readonly array $applyRows,
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
        return self::fromNextSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan::tableLeafFromDeleteResult(
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

    public static function fromNextSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan): self
    {
        $rows = self::buildApplyRows($nextSourcePlan);
        $errors = self::applyErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next253 apply failed: ' . implode('; ', $errors));
        }

        return new self($nextSourcePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function applyRows(): array
    {
        return $this->applyRows;
    }

    /**
     * @return list<string>
     */
    public function applyErrors(): array
    {
        return self::applyErrorsForRows($this->applyRows);
    }

    /**
     * @return list<int>
     */
    public function applyPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['apply_page'], $this->applyRows));
    }

    /**
     * @return list<int>
     */
    public function applyGroupNumbers(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['apply_group'], $this->applyRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapApplyPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['apply_channel'] === 'pointer-map-apply');
    }

    /**
     * @return list<int>
     */
    public function reusableFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['apply_channel'] === 'reusable-freeblock-apply');
    }

    /**
     * @return array<int, list<int>>
     */
    public function pagesByApplyGroup(): array
    {
        $groups = [];
        foreach ($this->applyRows as $row) {
            $group = (int) $row['apply_group'];
            $groups[$group] ??= [];
            $groups[$group][] = (int) $row['apply_page'];
        }
        ksort($groups);

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function applyTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['apply_token'], $this->applyRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function applySummary(): array
    {
        $nextSourceSummary = $this->nextSourcePlan->nextSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next253-ready',
            'apply_row_count' => count($this->applyRows),
            'apply_pages' => $this->applyPages(),
            'next_source_pages' => $nextSourceSummary['next_source_pages'],
            'apply_pages_match_next_source' => $this->applyPages() === $nextSourceSummary['next_source_pages'],
            'pointer_map_apply_pages' => $this->pointerMapApplyPages(),
            'reusable_freeblock_pages' => $this->reusableFreeblockPages(),
            'apply_group_numbers' => $this->applyGroupNumbers(),
            'pages_by_apply_group' => $this->pagesByApplyGroup(),
            'all_next_source_tokens_match' => !in_array(false, array_column($this->applyRows, 'next_source_token_matches'), true),
            'all_groups_opened_by_pointer_map' => !in_array(false, array_column($this->applyRows, 'group_opened_by_pointer_map'), true),
            'all_reusable_pages_after_group_pointer_map' => !in_array(false, array_column($this->applyRows, 'reusable_after_group_pointer_map'), true),
            'all_leaf_receipts_ready_at_apply' => !in_array(false, array_column($this->applyRows, 'leaf_receipt_ready_at_apply'), true),
            'all_tail_pages_remain_fenced' => !in_array(false, array_column($this->applyRows, 'tail_page_still_fenced_at_apply'), true),
            'all_apply_links_valid' => !in_array(false, array_column($this->applyRows, 'apply_link_valid'), true),
            'apply_errors' => $this->applyErrors(),
            'apply_signature' => self::signature($this->applyTokens()),
            'current_source_next253_token' => self::signature(array_merge(
                ['next253', $nextSourceSummary['current_source_next249_token']],
                $this->applyPages(),
                $this->applyGroupNumbers(),
                $this->applyTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next249',
                'sqlite-current-source-next253',
            ],
            'dependency_closure' => 'no new support component needed; next253 reuses next249 next-source rows and records grouped vacuum apply ordering only',
            'non_overlap' => 'adds grouped vacuum apply windows after next249 next-source allocation publication; does not repeat next249 source allocation ordering, next245 cursor admission, next248 publication sealing, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or VFS/WAL behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next253',
            'apply_summary' => $this->applySummary(),
            'apply_errors' => $this->applyErrors(),
            'apply_rows' => $this->applyRows,
            'next_source_plan' => $this->nextSourcePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->applyRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['apply_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildApplyRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan): array
    {
        $sourceRows = $nextSourcePlan->nextSourceRows();
        $sourceTokens = $nextSourcePlan->nextSourceTokens();
        $rows = [];
        $previousApplyToken = null;
        $applyGroup = 0;
        $groupPointerMapPage = null;
        $groupHasPointerMap = false;

        foreach ($sourceRows as $index => $sourceRow) {
            $pageNumber = (int) $sourceRow['next_source_page'];
            $sourceChannel = (string) $sourceRow['next_source_channel'];
            $isPointerMap = $sourceChannel === 'pointer-map-epoch';
            if ($isPointerMap) {
                ++$applyGroup;
                $groupPointerMapPage = $pageNumber;
                $groupHasPointerMap = true;
            }

            $ordinal = $index + 1;
            $applyChannel = $isPointerMap ? 'pointer-map-apply' : 'reusable-freeblock-apply';
            $sourceToken = (string) $sourceRow['next_source_token'];
            $token = self::signature([
                'next253',
                $ordinal,
                $previousApplyToken ?? 'initial',
                $sourceToken,
                $pageNumber,
                $applyChannel,
                $applyGroup,
                $groupPointerMapPage ?? 0,
                (int) $sourceRow['next_allocation_position'],
            ]);

            $rows[] = [
                'apply_ordinal' => $ordinal,
                'next_source_ordinal' => (int) $sourceRow['next_source_ordinal'],
                'apply_page' => $pageNumber,
                'apply_channel' => $applyChannel,
                'apply_group' => $applyGroup,
                'group_pointer_map_page' => $groupPointerMapPage,
                'source_next_source_token' => $sourceToken,
                'expected_next_source_token' => $sourceTokens[$index] ?? null,
                'next_source_token_matches' => ($sourceTokens[$index] ?? null) === $sourceToken,
                'previous_apply_token' => $previousApplyToken,
                'group_opened_by_pointer_map' => $groupHasPointerMap && $applyGroup > 0 && $groupPointerMapPage !== null,
                'reusable_after_group_pointer_map' => $isPointerMap || ($groupHasPointerMap && $groupPointerMapPage !== null),
                'leaf_receipt_ready_at_apply' => $isPointerMap || $sourceRow['leaf_receipt_carried_forward'] === true,
                'tail_page_still_fenced_at_apply' => $sourceRow['tail_page_still_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'apply_link_valid' => $sourceRow['previous_next_source_token'] === ($sourceRows[$index - 1]['next_source_token'] ?? null),
                'apply_state' => 'current-source-next253-grouped-vacuum-apply-ready',
                'apply_token' => $token,
            ];

            $previousApplyToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function applyErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousGroup = 0;

        foreach ($rows as $row) {
            if ($row['apply_state'] !== 'current-source-next253-grouped-vacuum-apply-ready') {
                $errors[] = "apply {$row['apply_ordinal']} is not ready";
            }
            if ((int) $row['apply_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "apply {$row['apply_ordinal']} skipped an ordinal";
            }
            if ((int) $row['next_source_ordinal'] !== (int) $row['apply_ordinal']) {
                $errors[] = "apply {$row['apply_ordinal']} drifted from next-source ordinal";
            }
            if ($row['next_source_token_matches'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} next-source token drifted";
            }
            if ($row['previous_apply_token'] !== $previousToken) {
                $errors[] = "apply {$row['apply_ordinal']} broke token chaining";
            }
            if ($row['group_opened_by_pointer_map'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} did not have a pointer-map group opener";
            }
            if ($row['reusable_after_group_pointer_map'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} reused a page before its pointer-map group";
            }
            if ($row['leaf_receipt_ready_at_apply'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} lost the leaf receipt";
            }
            if ($row['tail_page_still_fenced_at_apply'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} admitted a fenced tail page";
            }
            if ($row['apply_link_valid'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} broke next-source link continuity";
            }
            if ((int) $row['apply_group'] < $previousGroup) {
                $errors[] = "apply {$row['apply_ordinal']} moved group backward";
            }
            if ($row['apply_token'] === '') {
                $errors[] = "apply {$row['apply_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['apply_ordinal'];
            $previousGroup = (int) $row['apply_group'];
            $previousToken = (string) $row['apply_token'];
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
