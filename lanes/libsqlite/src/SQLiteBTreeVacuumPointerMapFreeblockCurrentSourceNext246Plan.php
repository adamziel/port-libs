<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext246Plan
{
    /**
     * @param list<array<string, mixed>> $reuseRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan $currentSourcePlan,
        private readonly array $reuseRows,
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
        return self::fromCurrentSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan::tableLeafFromDeleteResult(
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

    public static function fromCurrentSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan $currentSourcePlan): self
    {
        $rows = self::buildReuseRows($currentSourcePlan);
        $errors = self::reuseErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next246 reuse cursor failed: ' . implode('; ', $errors));
        }

        return new self($currentSourcePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reuseRows(): array
    {
        return $this->reuseRows;
    }

    /**
     * @return list<string>
     */
    public function reuseErrors(): array
    {
        return self::reuseErrorsForRows($this->reuseRows);
    }

    /**
     * @return list<int>
     */
    public function reusePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['reuse_page'], $this->reuseRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapBarrierPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reuse_channel'] === 'pointer-map-barrier');
    }

    /**
     * @return list<int>
     */
    public function allocatedFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reuse_channel'] === 'reusable-freeblock');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_generation'] === true);
    }

    /**
     * @return list<string>
     */
    public function reuseTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['reuse_token'], $this->reuseRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function reuseSummary(): array
    {
        $sourceSummary = $this->currentSourcePlan->currentSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next246-ready',
            'reuse_row_count' => count($this->reuseRows),
            'reuse_pages' => $this->reusePages(),
            'current_source_pages' => $sourceSummary['current_source_pages'],
            'reuse_pages_match_current_source_pages' => $this->reusePages() === $sourceSummary['current_source_pages'],
            'pointer_map_barrier_pages' => $this->pointerMapBarrierPages(),
            'allocated_freeblock_pages' => $this->allocatedFreeblockPages(),
            'duplicate_pointer_map_pages' => $this->duplicatePointerMapPages(),
            'all_current_source_tokens_match' => !in_array(false, array_column($this->reuseRows, 'current_source_token_matches'), true),
            'all_pointer_map_generations_current' => !in_array(false, array_column($this->reuseRows, 'pointer_map_generation_current'), true),
            'all_freeblock_reuse_waits_for_pointer_map' => !in_array(false, array_column($this->reuseRows, 'freeblock_reuse_waits_for_pointer_map'), true),
            'all_leaf_receipts_current_at_reuse' => !in_array(false, array_column($this->reuseRows, 'leaf_receipt_current_at_reuse'), true),
            'all_trunk_lease_stable' => !in_array(false, array_column($this->reuseRows, 'trunk_lease_stable'), true),
            'all_tail_pages_remain_excluded' => !in_array(false, array_column($this->reuseRows, 'tail_pages_remain_excluded'), true),
            'all_reuse_links_valid' => !in_array(false, array_column($this->reuseRows, 'reuse_link_valid'), true),
            'reuse_errors' => $this->reuseErrors(),
            'reuse_signature' => self::signature($this->reuseTokens()),
            'current_source_next246_token' => self::signature(array_merge(
                ['next246', $sourceSummary['current_source_next242_token']],
                $this->reusePages(),
                $this->reuseTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next242',
                'sqlite-current-source-next246',
            ],
            'dependency_closure' => 'no new support component needed; next246 reuses next242 current-source rows and validates vacuum reuse cursor publication',
            'non_overlap' => 'adds a reuse-cursor publication fence after next242 current-source handoff; does not repeat next242 handoff visibility, next238 freelist admission, overflow freelist release, page relocation, root collapse, index-interior merge, or VFS/WAL behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next246',
            'reuse_summary' => $this->reuseSummary(),
            'reuse_errors' => $this->reuseErrors(),
            'reuse_rows' => $this->reuseRows,
            'current_source_plan' => $this->currentSourcePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->reuseRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['reuse_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReuseRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan $currentSourcePlan): array
    {
        $sourceRows = $currentSourcePlan->currentSourceRows();
        $sourceTokens = $currentSourcePlan->currentSourceTokens();
        $rows = [];
        $previousToken = null;
        $pointerMapGenerations = [];
        $allocatedFreeblocks = [];
        $trunkLeasePage = null;

        foreach ($sourceRows as $index => $sourceRow) {
            $pageNumber = (int) $sourceRow['current_source_page'];
            $channel = (string) $sourceRow['source_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map-barrier') {
                $pointerMapGenerations[$pageNumber] = ($pointerMapGenerations[$pageNumber] ?? 0) + 1;
            }

            $pointerMapCurrent = $pointerMapGenerations !== [];
            $isFreeblock = $channel === 'reusable-freeblock';
            if ($isFreeblock) {
                $allocatedFreeblocks[$pageNumber] = true;
                $trunkLeasePage ??= $pageNumber;
            }

            $duplicatePointerMap = $channel === 'pointer-map-barrier' && ($pointerMapGenerations[$pageNumber] ?? 0) > 1;
            $token = self::signature(array_merge(
                ['next246', $previousToken ?? 'initial', $sourceRow['current_source_token']],
                [$ordinal, $pageNumber, $channel, $trunkLeasePage ?? 0, $duplicatePointerMap],
                self::generationParts($pointerMapGenerations),
                self::sortedIntKeys($allocatedFreeblocks),
            ));

            $rows[] = [
                'reuse_ordinal' => $ordinal,
                'current_source_ordinal' => (int) $sourceRow['current_source_ordinal'],
                'reuse_page' => $pageNumber,
                'reuse_channel' => $channel,
                'source_current_source_token' => (string) $sourceRow['current_source_token'],
                'expected_current_source_token' => $sourceTokens[$index] ?? null,
                'current_source_token_matches' => ($sourceTokens[$index] ?? null) === (string) $sourceRow['current_source_token'],
                'previous_reuse_token' => $previousToken,
                'reuse_link_valid' => $sourceRow['previous_current_source_token'] === ($sourceRows[$index - 1]['current_source_token'] ?? null),
                'pointer_map_generations' => self::generationParts($pointerMapGenerations),
                'pointer_map_generation_current' => $pointerMapCurrent,
                'duplicate_pointer_map_generation' => $duplicatePointerMap,
                'allocated_freeblock_pages' => self::sortedIntKeys($allocatedFreeblocks),
                'freeblock_reuse_waits_for_pointer_map' => !$isFreeblock || ($pointerMapCurrent && $sourceRow['pointer_map_barrier_visible_before_source'] === true),
                'leaf_receipt_current_at_reuse' => !$isFreeblock || $sourceRow['freeblock_source_has_leaf_receipt'] === true,
                'trunk_lease_page' => $trunkLeasePage,
                'trunk_lease_stable' => !$isFreeblock || $trunkLeasePage === min(self::sortedIntKeys($allocatedFreeblocks)),
                'tail_pages_remain_excluded' => $sourceRow['tail_page_excluded_from_current_source'] === true && !in_array($pageNumber, [109, 110], true),
                'reuse_state' => 'current-source-next246-vacuum-reuse-cursor-published',
                'reuse_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function reuseErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['reuse_state'] !== 'current-source-next246-vacuum-reuse-cursor-published') {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} is not published";
            }
            if ((int) $row['reuse_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} skipped an ordinal";
            }
            if ((int) $row['current_source_ordinal'] !== (int) $row['reuse_ordinal']) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} drifted from current-source ordinal";
            }
            if ($row['current_source_token_matches'] !== true) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} current-source token drifted";
            }
            if ($row['previous_reuse_token'] !== $previousToken) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} broke token chaining";
            }
            if ($row['reuse_link_valid'] !== true) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} broke current-source link continuity";
            }
            if ($row['pointer_map_generation_current'] !== true) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} published before pointer-map generation";
            }
            if ($row['freeblock_reuse_waits_for_pointer_map'] !== true) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} reused a freeblock before pointer-map visibility";
            }
            if ($row['leaf_receipt_current_at_reuse'] !== true) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} lost the leaf receipt";
            }
            if ($row['trunk_lease_stable'] !== true) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} changed the trunk lease";
            }
            if ($row['tail_pages_remain_excluded'] !== true) {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} exposed a fenced tail page";
            }
            if ($row['reuse_token'] === '') {
                $errors[] = "reuse cursor {$row['reuse_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['reuse_ordinal'];
            $previousToken = (string) $row['reuse_token'];
        }

        return $errors;
    }

    /**
     * @param array<int, int> $generations
     * @return list<string>
     */
    private static function generationParts(array $generations): array
    {
        ksort($generations);
        $parts = [];
        foreach ($generations as $page => $generation) {
            $parts[] = $page . ':' . $generation;
        }

        return $parts;
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
