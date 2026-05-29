<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan
{
    /**
     * @param list<array<string, mixed>> $currentSourceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $freelistPlan,
        private readonly array $currentSourceRows,
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
        return self::fromFreelistPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext238(
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

    public static function fromFreelistPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $freelistPlan): self
    {
        $rows = self::buildCurrentSourceRows($freelistPlan);
        $errors = self::currentSourceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next242 handoff failed: ' . implode('; ', $errors));
        }

        return new self($freelistPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currentSourceRows(): array
    {
        return $this->currentSourceRows;
    }

    /**
     * @return list<string>
     */
    public function currentSourceErrors(): array
    {
        return self::currentSourceErrorsForRows($this->currentSourceRows);
    }

    /**
     * @return list<int>
     */
    public function currentSourcePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['current_source_page'], $this->currentSourceRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'pointer-map-barrier');
    }

    /**
     * @return list<int>
     */
    public function reusableFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'reusable-freeblock');
    }

    /**
     * @return list<int>
     */
    public function trunkCandidatePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['trunk_candidate_visible'] === true);
    }

    /**
     * @return list<string>
     */
    public function currentSourceTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['current_source_token'], $this->currentSourceRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSourceSummary(): array
    {
        $freelistSummary = $this->freelistPlan->freelistSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next242-ready',
            'current_source_row_count' => count($this->currentSourceRows),
            'current_source_pages' => $this->currentSourcePages(),
            'freelist_pages' => $freelistSummary['freelist_pages'],
            'current_source_pages_match_freelist_pages' => $this->currentSourcePages() === $freelistSummary['freelist_pages'],
            'pointer_map_source_pages' => $this->pointerMapSourcePages(),
            'reusable_freeblock_pages' => $this->reusableFreeblockPages(),
            'trunk_candidate_pages' => $this->trunkCandidatePages(),
            'all_freelist_tokens_match' => !in_array(false, array_column($this->currentSourceRows, 'freelist_token_matches'), true),
            'all_pointer_map_barriers_visible' => !in_array(false, array_column($this->currentSourceRows, 'pointer_map_barrier_visible_before_source'), true),
            'all_freeblock_sources_have_receipts' => !in_array(false, array_column($this->currentSourceRows, 'freeblock_source_has_leaf_receipt'), true),
            'all_trunk_candidates_stable' => !in_array(false, array_column($this->currentSourceRows, 'trunk_candidate_stable'), true),
            'all_reusable_pages_monotonic' => !in_array(false, array_column($this->currentSourceRows, 'reusable_source_monotonic'), true),
            'all_tail_pages_excluded' => !in_array(false, array_column($this->currentSourceRows, 'tail_page_excluded_from_current_source'), true),
            'all_current_source_links_valid' => !in_array(false, array_column($this->currentSourceRows, 'current_source_link_valid'), true),
            'current_source_errors' => $this->currentSourceErrors(),
            'current_source_signature' => self::signature($this->currentSourceTokens()),
            'current_source_next242_token' => self::signature(array_merge(
                ['next242', $freelistSummary['current_source_next238_token']],
                $this->currentSourcePages(),
                $this->currentSourceTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next238',
                'sqlite-current-source-next242',
            ],
            'dependency_closure' => 'no new support component needed; next242 reuses next238 freelist rows and records current-source freeblock handoff visibility only',
            'non_overlap' => 'adds current-source freeblock handoff visibility after next238 freelist-link admission; does not repeat next238 freelist admission, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, index-interior merge, or rollback/VFS writer behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next242',
            'current_source_summary' => $this->currentSourceSummary(),
            'current_source_errors' => $this->currentSourceErrors(),
            'current_source_rows' => $this->currentSourceRows,
            'freelist_plan' => $this->freelistPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->currentSourceRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['current_source_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $freelistPlan): array
    {
        $freelistRows = $freelistPlan->freelistRows();
        $freelistTokens = $freelistPlan->freelistTokens();
        $rows = [];
        $previousToken = null;
        $visiblePointerMaps = [];
        $reusablePages = [];
        $stableTrunk = null;
        $lastReusablePage = 0;

        foreach ($freelistRows as $index => $freelistRow) {
            $pageNumber = (int) $freelistRow['freelist_page'];
            $freelistChannel = (string) $freelistRow['freelist_channel'];
            $isPointerMap = $freelistChannel === 'pointer-map';
            $isReusable = $freelistChannel === 'payload';

            if ($isPointerMap) {
                $visiblePointerMaps[$pageNumber] = true;
            }
            if ($isReusable) {
                $reusablePages[$pageNumber] = true;
                $stableTrunk ??= $pageNumber;
            }

            $channel = $isPointerMap ? 'pointer-map-barrier' : 'reusable-freeblock';
            $ordinal = $index + 1;
            $freelistToken = (string) $freelistRow['freelist_token'];
            $token = self::signature(array_merge(
                ['next242', $ordinal, $previousToken ?? 'initial', $freelistToken],
                [$pageNumber, $channel, $stableTrunk ?? 0, $lastReusablePage],
                self::sortedIntKeys($visiblePointerMaps),
                self::sortedIntKeys($reusablePages),
            ));

            $rows[] = [
                'current_source_ordinal' => $ordinal,
                'freelist_ordinal' => (int) $freelistRow['freelist_ordinal'],
                'current_source_page' => $pageNumber,
                'source_channel' => $channel,
                'source_freelist_token' => $freelistToken,
                'expected_freelist_token' => $freelistTokens[$index] ?? null,
                'freelist_token_matches' => ($freelistTokens[$index] ?? null) === $freelistToken,
                'previous_current_source_token' => $previousToken,
                'visible_pointer_map_pages' => self::sortedIntKeys($visiblePointerMaps),
                'visible_reusable_pages' => self::sortedIntKeys($reusablePages),
                'stable_trunk_candidate_page' => $stableTrunk,
                'trunk_candidate_visible' => $stableTrunk !== null && $pageNumber === $stableTrunk,
                'pointer_map_barrier_visible_before_source' => $isPointerMap || $visiblePointerMaps !== [],
                'freeblock_source_has_leaf_receipt' => !$isReusable || $freelistRow['freeblock_receipt_admitted_to_freelist'] === true,
                'trunk_candidate_stable' => !$isReusable || $stableTrunk === min(self::sortedIntKeys($reusablePages)),
                'reusable_source_monotonic' => !$isReusable || $pageNumber > $lastReusablePage,
                'tail_page_excluded_from_current_source' => !in_array($pageNumber, [109, 110], true) && $freelistRow['tail_page_blocked_from_freelist'] === true,
                'current_source_link_valid' => $freelistRow['previous_freelist_token'] === ($freelistRows[$index - 1]['freelist_token'] ?? null),
                'current_source_state' => 'current-source-next242-freeblock-handoff-visible',
                'current_source_token' => $token,
            ];

            if ($isReusable) {
                $lastReusablePage = $pageNumber;
            }
            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function currentSourceErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['current_source_state'] !== 'current-source-next242-freeblock-handoff-visible') {
                $errors[] = "current-source {$row['current_source_ordinal']} is not visible";
            }
            if ((int) $row['current_source_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "current-source {$row['current_source_ordinal']} skipped an ordinal";
            }
            if ((int) $row['freelist_ordinal'] !== (int) $row['current_source_ordinal']) {
                $errors[] = "current-source {$row['current_source_ordinal']} drifted from freelist ordinal";
            }
            if ($row['freelist_token_matches'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} freelist token drifted";
            }
            if ($row['previous_current_source_token'] !== $previousToken) {
                $errors[] = "current-source {$row['current_source_ordinal']} broke token chaining";
            }
            if ($row['pointer_map_barrier_visible_before_source'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} exposed a freeblock before pointer-map barriers";
            }
            if ($row['freeblock_source_has_leaf_receipt'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['trunk_candidate_stable'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} changed the freelist trunk candidate";
            }
            if ($row['reusable_source_monotonic'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} linked reusable pages out of order";
            }
            if ($row['tail_page_excluded_from_current_source'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} exposed a fenced tail page";
            }
            if ($row['current_source_link_valid'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} broke freelist link continuity";
            }
            if ($row['current_source_token'] === '') {
                $errors[] = "current-source {$row['current_source_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['current_source_ordinal'];
            $previousToken = (string) $row['current_source_token'];
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
