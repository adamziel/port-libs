<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext261Plan
{
    /**
     * @param list<array<string, mixed>> $vacuumRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan $handoffPlan,
        private readonly array $vacuumRows,
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
        return self::fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan::tableLeafFromDeleteResult(
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

    public static function fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan $handoffPlan): self
    {
        $rows = self::buildVacuumRows($handoffPlan);
        $errors = self::vacuumErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next261 finalization failed: ' . implode('; ', $errors));
        }

        return new self($handoffPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vacuumRows(): array
    {
        return $this->vacuumRows;
    }

    /**
     * @return list<string>
     */
    public function vacuumErrors(): array
    {
        return self::vacuumErrorsForRows($this->vacuumRows);
    }

    /**
     * @return list<int>
     */
    public function finalizedReusablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['vacuum_channel'] === 'finalized-reusable-freeblock');
    }

    /**
     * @return array<int, list<int>>
     */
    public function reusablePagesByPointerMap(): array
    {
        $pages = [];
        foreach ($this->vacuumRows as $row) {
            if ($row['vacuum_channel'] !== 'finalized-reusable-freeblock') {
                continue;
            }
            $pointerMapPage = (int) $row['active_pointer_map_page'];
            $pages[$pointerMapPage] ??= [];
            $pages[$pointerMapPage][] = (int) $row['vacuum_page'];
        }
        ksort($pages);

        return $pages;
    }

    /**
     * @return list<int>
     */
    public function fencePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['vacuum_channel'] === 'pointer-map-batch-fence');
    }

    /**
     * @return list<int>
     */
    public function finalizedWriteOffsets(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['vacuum_write_offset'],
            array_values(array_filter(
                $this->vacuumRows,
                static fn (array $row): bool => $row['vacuum_channel'] === 'finalized-reusable-freeblock',
            )),
        ));
    }

    /**
     * @return list<string>
     */
    public function vacuumTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['vacuum_token'], $this->vacuumRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function vacuumSummary(): array
    {
        $handoffSummary = $this->handoffPlan->handoffSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next261-ready',
            'vacuum_row_count' => count($this->vacuumRows),
            'fence_pages' => $this->fencePages(),
            'finalized_reusable_pages' => $this->finalizedReusablePages(),
            'reusable_pages_by_pointer_map' => $this->reusablePagesByPointerMap(),
            'finalized_write_offsets' => $this->finalizedWriteOffsets(),
            'handoff_pages' => $handoffSummary['handoff_pages'],
            'handoff_signature' => $handoffSummary['handoff_signature'],
            'all_handoff_tokens_preserved' => !in_array(false, array_column($this->vacuumRows, 'handoff_token_preserved'), true),
            'all_pointer_map_batches_fenced' => !in_array(false, array_column($this->vacuumRows, 'pointer_map_batch_fenced'), true),
            'all_reusable_slots_finalized' => !in_array(false, array_column($this->vacuumRows, 'reusable_slot_finalized'), true),
            'all_offsets_current_source_safe' => !in_array(false, array_column($this->vacuumRows, 'offset_current_source_safe'), true),
            'all_vacuum_links_valid' => !in_array(false, array_column($this->vacuumRows, 'vacuum_link_valid'), true),
            'vacuum_errors' => $this->vacuumErrors(),
            'vacuum_signature' => self::signature($this->vacuumTokens()),
            'current_source_next261_token' => self::signature(array_merge(
                ['next261', $handoffSummary['current_source_next258_token']],
                $this->fencePages(),
                $this->finalizedReusablePages(),
                $this->finalizedWriteOffsets(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next258',
                'sqlite-current-source-next261',
            ],
            'dependency_closure' => 'no new support component needed; next261 reuses next258 current-source handoff rows and finalizes pointer-map-scoped reusable freeblock batches',
            'non_overlap' => 'adds pointer-map-scoped vacuum finalization over next258 handoff rows; does not repeat next258 stale-slot fencing, next254 write-slot publication, next249 allocation rows, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next261',
            'vacuum_summary' => $this->vacuumSummary(),
            'vacuum_errors' => $this->vacuumErrors(),
            'vacuum_rows' => $this->vacuumRows,
            'handoff_plan' => $this->handoffPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->vacuumRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['vacuum_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildVacuumRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan $handoffPlan): array
    {
        $rows = [];
        $previousToken = null;
        $activePointerMapPage = null;
        $batchOrdinalByPointerMap = [];

        foreach ($handoffPlan->handoffRows() as $index => $handoffRow) {
            $pageNumber = (int) $handoffRow['handoff_page'];
            $isFence = $handoffRow['handoff_channel'] === 'pointer-map-fence';
            if ($isFence) {
                $activePointerMapPage = $pageNumber;
                $batchOrdinalByPointerMap[$activePointerMapPage] ??= 0;
            }

            $isReusable = $handoffRow['handoff_channel'] === 'next-source-reusable-page';
            if ($isReusable && $activePointerMapPage !== null) {
                $batchOrdinalByPointerMap[$activePointerMapPage] = ($batchOrdinalByPointerMap[$activePointerMapPage] ?? 0) + 1;
            }

            $channel = $isFence ? 'pointer-map-batch-fence' : 'finalized-reusable-freeblock';
            $offset = (int) $handoffRow['handoff_write_offset'];
            $vacuumToken = self::signature([
                'next261',
                $index + 1,
                $previousToken ?? 'initial',
                $handoffRow['handoff_token'],
                $pageNumber,
                $channel,
                $activePointerMapPage ?? 0,
                $batchOrdinalByPointerMap[$activePointerMapPage ?? 0] ?? 0,
                $offset,
            ]);

            $rows[] = [
                'vacuum_ordinal' => $index + 1,
                'handoff_ordinal' => (int) $handoffRow['handoff_ordinal'],
                'vacuum_page' => $pageNumber,
                'vacuum_channel' => $channel,
                'active_pointer_map_page' => $activePointerMapPage,
                'pointer_map_batch_ordinal' => $isReusable ? ($batchOrdinalByPointerMap[$activePointerMapPage ?? 0] ?? 0) : 0,
                'source_handoff_token' => (string) $handoffRow['handoff_token'],
                'previous_vacuum_token' => $previousToken,
                'vacuum_write_offset' => $offset,
                'handoff_token_preserved' => $handoffRow['handoff_token'] !== '',
                'pointer_map_batch_fenced' => $isFence || $activePointerMapPage !== null,
                'reusable_slot_finalized' => !$isReusable || ($handoffRow['next_reuse_has_current_slot'] === true && $handoffRow['stale_freeblock_slot_fenced'] === true),
                'offset_current_source_safe' => $isFence || ($offset >= 8 && $offset < 512),
                'vacuum_link_valid' => $handoffRow['previous_handoff_token'] === ($handoffPlan->handoffRows()[$index - 1]['handoff_token'] ?? null),
                'vacuum_state' => 'current-source-next261-vacuum-freeblock-finalized',
                'vacuum_token' => $vacuumToken,
            ];

            $previousToken = $vacuumToken;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function vacuumErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['vacuum_state'] !== 'current-source-next261-vacuum-freeblock-finalized') {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} is not finalized";
            }
            if ((int) $row['vacuum_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} skipped an ordinal";
            }
            if ((int) $row['handoff_ordinal'] !== (int) $row['vacuum_ordinal']) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} drifted from handoff ordinal";
            }
            if ($row['previous_vacuum_token'] !== $previousToken) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} broke token chaining";
            }
            if ($row['handoff_token_preserved'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} lost its handoff token";
            }
            if ($row['pointer_map_batch_fenced'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} lacks a pointer-map fence";
            }
            if ($row['reusable_slot_finalized'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} left a reusable slot unfinished";
            }
            if ($row['offset_current_source_safe'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} has an unsafe current-source offset";
            }
            if ($row['vacuum_link_valid'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} broke handoff link continuity";
            }
            if ($row['vacuum_token'] === '') {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['vacuum_ordinal'];
            $previousToken = (string) $row['vacuum_token'];
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
