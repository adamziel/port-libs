<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Plan
{
    /**
     * @param list<array<string, mixed>> $freelistRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext261Plan $vacuumPlan,
        private readonly array $freelistRows,
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
        return self::fromVacuumPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext261Plan::tableLeafFromDeleteResult(
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

    public static function fromVacuumPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext261Plan $vacuumPlan): self
    {
        $rows = self::buildFreelistRows($vacuumPlan);
        $errors = self::freelistErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next263 freelist splice failed: ' . implode('; ', $errors));
        }

        return new self($vacuumPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function freelistRows(): array
    {
        return $this->freelistRows;
    }

    /**
     * @return list<string>
     */
    public function freelistErrors(): array
    {
        return self::freelistErrorsForRows($this->freelistRows);
    }

    /**
     * @return list<int>
     */
    public function freelistPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['freelist_page'], $this->freelistRows));
    }

    /**
     * @return list<int>
     */
    public function trunkAnchorPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freelist_channel'] === 'freelist-trunk-anchor');
    }

    /**
     * @return list<int>
     */
    public function leafSlotPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freelist_channel'] === 'freelist-leaf-slot');
    }

    /**
     * @return array<int, list<int>>
     */
    public function leafSlotsByTrunk(): array
    {
        $pages = [];
        foreach ($this->freelistRows as $row) {
            if ($row['freelist_channel'] !== 'freelist-leaf-slot') {
                continue;
            }
            $trunkPage = (int) $row['active_trunk_page'];
            $pages[$trunkPage] ??= [];
            $pages[$trunkPage][] = (int) $row['freelist_page'];
        }
        ksort($pages);

        return $pages;
    }

    /**
     * @return list<int>
     */
    public function leafSlotOrdinals(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['trunk_slot_ordinal'],
            array_values(array_filter(
                $this->freelistRows,
                static fn (array $row): bool => $row['freelist_channel'] === 'freelist-leaf-slot',
            )),
        ));
    }

    /**
     * @return list<int>
     */
    public function freelistWriteOffsets(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['freelist_write_offset'],
            array_values(array_filter(
                $this->freelistRows,
                static fn (array $row): bool => $row['freelist_channel'] === 'freelist-leaf-slot',
            )),
        ));
    }

    /**
     * @return list<string>
     */
    public function freelistTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['freelist_token'], $this->freelistRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function freelistSummary(): array
    {
        $vacuumSummary = $this->vacuumPlan->vacuumSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next263-ready',
            'freelist_row_count' => count($this->freelistRows),
            'freelist_pages' => $this->freelistPages(),
            'trunk_anchor_pages' => $this->trunkAnchorPages(),
            'leaf_slot_pages' => $this->leafSlotPages(),
            'leaf_slots_by_trunk' => $this->leafSlotsByTrunk(),
            'leaf_slot_ordinals' => $this->leafSlotOrdinals(),
            'freelist_write_offsets' => $this->freelistWriteOffsets(),
            'vacuum_finalized_pages' => $vacuumSummary['finalized_reusable_pages'],
            'freelist_leaf_pages_match_vacuum' => $this->leafSlotPages() === $vacuumSummary['finalized_reusable_pages'],
            'all_vacuum_tokens_preserved' => !in_array(false, array_column($this->freelistRows, 'vacuum_token_preserved'), true),
            'all_trunks_seen_before_leaf_slots' => !in_array(false, array_column($this->freelistRows, 'trunk_seen_before_leaf_slot'), true),
            'all_leaf_slots_ordered' => !in_array(false, array_column($this->freelistRows, 'leaf_slot_ordered'), true),
            'all_offsets_match_vacuum_finalization' => !in_array(false, array_column($this->freelistRows, 'offset_matches_vacuum_finalization'), true),
            'all_tail_pages_rejected_from_freelist' => !in_array(false, array_column($this->freelistRows, 'tail_page_rejected_from_freelist'), true),
            'all_freelist_links_valid' => !in_array(false, array_column($this->freelistRows, 'freelist_link_valid'), true),
            'freelist_errors' => $this->freelistErrors(),
            'freelist_signature' => self::signature($this->freelistTokens()),
            'current_source_next263_token' => self::signature(array_merge(
                ['next263', $vacuumSummary['current_source_next261_token']],
                $this->trunkAnchorPages(),
                $this->leafSlotPages(),
                $this->leafSlotOrdinals(),
                $this->freelistWriteOffsets(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next261',
                'sqlite-current-source-next263',
            ],
            'dependency_closure' => 'no new support component needed; next263 reuses next261 vacuum finalization rows and seals reusable pages into pointer-map-scoped freelist splice receipts',
            'non_overlap' => 'adds freelist splice receipts after next261 pointer-map-scoped finalization; does not repeat next261 reusable-slot finalization, next259 source-next links, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next263',
            'freelist_summary' => $this->freelistSummary(),
            'freelist_errors' => $this->freelistErrors(),
            'freelist_rows' => $this->freelistRows,
            'vacuum_plan' => $this->vacuumPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->freelistRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['freelist_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildFreelistRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext261Plan $vacuumPlan): array
    {
        $rows = [];
        $previousToken = null;
        $activeTrunkPage = null;
        $slotOrdinalByTrunk = [];

        foreach ($vacuumPlan->vacuumRows() as $index => $vacuumRow) {
            $ordinal = $index + 1;
            $pageNumber = (int) $vacuumRow['vacuum_page'];
            $isTrunk = $vacuumRow['vacuum_channel'] === 'pointer-map-batch-fence';
            if ($isTrunk) {
                $activeTrunkPage = $pageNumber;
                $slotOrdinalByTrunk[$activeTrunkPage] ??= 0;
            }

            $slotOrdinal = 0;
            if (!$isTrunk && $activeTrunkPage !== null) {
                $slotOrdinalByTrunk[$activeTrunkPage] = ($slotOrdinalByTrunk[$activeTrunkPage] ?? 0) + 1;
                $slotOrdinal = $slotOrdinalByTrunk[$activeTrunkPage];
            }

            $channel = $isTrunk ? 'freelist-trunk-anchor' : 'freelist-leaf-slot';
            $writeOffset = (int) $vacuumRow['vacuum_write_offset'];
            $token = self::signature([
                'next263',
                $ordinal,
                $previousToken ?? 'initial',
                $vacuumRow['vacuum_token'],
                $pageNumber,
                $channel,
                $activeTrunkPage ?? 0,
                $slotOrdinal,
                $writeOffset,
            ]);

            $rows[] = [
                'freelist_ordinal' => $ordinal,
                'vacuum_ordinal' => (int) $vacuumRow['vacuum_ordinal'],
                'freelist_page' => $pageNumber,
                'freelist_channel' => $channel,
                'active_trunk_page' => $activeTrunkPage,
                'trunk_slot_ordinal' => $slotOrdinal,
                'freelist_write_offset' => $writeOffset,
                'source_vacuum_token' => (string) $vacuumRow['vacuum_token'],
                'previous_freelist_token' => $previousToken,
                'vacuum_token_preserved' => $vacuumRow['vacuum_token'] !== '',
                'trunk_seen_before_leaf_slot' => $isTrunk || $activeTrunkPage !== null,
                'leaf_slot_ordered' => $isTrunk || $slotOrdinal > 0,
                'offset_matches_vacuum_finalization' => $isTrunk || $writeOffset >= 8,
                'tail_page_rejected_from_freelist' => !in_array($pageNumber, [109, 110], true),
                'freelist_link_valid' => $vacuumRow['previous_vacuum_token'] === ($vacuumPlan->vacuumRows()[$index - 1]['vacuum_token'] ?? null),
                'freelist_state' => 'current-source-next263-freelist-splice-ready',
                'freelist_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function freelistErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['freelist_state'] !== 'current-source-next263-freelist-splice-ready') {
                $errors[] = "freelist row {$row['freelist_ordinal']} is not splice-ready";
            }
            if ((int) $row['freelist_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "freelist row {$row['freelist_ordinal']} skipped an ordinal";
            }
            if ((int) $row['vacuum_ordinal'] !== (int) $row['freelist_ordinal']) {
                $errors[] = "freelist row {$row['freelist_ordinal']} drifted from vacuum ordinal";
            }
            if ($row['previous_freelist_token'] !== $previousToken) {
                $errors[] = "freelist row {$row['freelist_ordinal']} broke token chaining";
            }
            if ($row['vacuum_token_preserved'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} lost its vacuum token";
            }
            if ($row['trunk_seen_before_leaf_slot'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} wrote a leaf slot before a trunk anchor";
            }
            if ($row['leaf_slot_ordered'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} has an unordered leaf slot";
            }
            if ($row['offset_matches_vacuum_finalization'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} has an unsafe freelist offset";
            }
            if ($row['tail_page_rejected_from_freelist'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} admitted a fenced tail page";
            }
            if ($row['freelist_link_valid'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} broke vacuum link continuity";
            }
            if ($row['freelist_token'] === '') {
                $errors[] = "freelist row {$row['freelist_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['freelist_ordinal'];
            $previousToken = (string) $row['freelist_token'];
        }

        if ($rows === []) {
            $errors[] = 'freelist splice plan is empty';
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
