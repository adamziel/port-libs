<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext258Plan
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext254Plan $currentSourcePlan,
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
        return self::fromCurrentSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext254Plan::tableLeafFromDeleteResult(
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

    public static function fromCurrentSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext254Plan $currentSourcePlan): self
    {
        $rows = self::buildHandoffRows($currentSourcePlan);
        $errors = self::handoffErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next258 handoff failed: ' . implode('; ', $errors));
        }

        return new self($currentSourcePlan, $rows);
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
    public function nextReusablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'next-source-reusable-page');
    }

    /**
     * @return list<int>
     */
    public function pointerMapFencePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'pointer-map-fence');
    }

    /**
     * @return list<int>
     */
    public function staleSlotFencedPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['stale_freeblock_slot_fenced'] === true);
    }

    /**
     * @return list<int>
     */
    public function handoffWriteOffsets(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['handoff_write_offset'], $this->handoffRows));
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
        $currentSummary = $this->currentSourcePlan->currentSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next258-ready',
            'handoff_row_count' => count($this->handoffRows),
            'handoff_pages' => $this->handoffPages(),
            'current_source_pages' => $currentSummary['current_source_pages'],
            'handoff_pages_match_current_source' => $this->handoffPages() === $currentSummary['current_source_pages'],
            'next_reusable_pages' => $this->nextReusablePages(),
            'pointer_map_fence_pages' => $this->pointerMapFencePages(),
            'stale_slot_fenced_pages' => $this->staleSlotFencedPages(),
            'handoff_write_offsets' => $this->handoffWriteOffsets(),
            'all_current_source_tokens_match' => !in_array(false, array_column($this->handoffRows, 'current_source_token_matches'), true),
            'all_pointer_map_fences_before_reuse' => !in_array(false, array_column($this->handoffRows, 'pointer_map_fence_before_reuse'), true),
            'all_next_reuse_has_current_slot' => !in_array(false, array_column($this->handoffRows, 'next_reuse_has_current_slot'), true),
            'all_stale_slots_fenced_before_next_reuse' => !in_array(false, array_column($this->handoffRows, 'stale_freeblock_slot_fenced'), true),
            'all_leaf_receipts_preserved' => !in_array(false, array_column($this->handoffRows, 'leaf_receipt_preserved_for_next_source'), true),
            'all_handoff_links_valid' => !in_array(false, array_column($this->handoffRows, 'handoff_link_valid'), true),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_signature' => self::signature($this->handoffTokens()),
            'current_source_next258_token' => self::signature(array_merge(
                ['next258', $currentSummary['current_source_next254_token']],
                $this->handoffPages(),
                $this->handoffWriteOffsets(),
                $this->handoffTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next254',
                'sqlite-current-source-next258',
            ],
            'dependency_closure' => 'no new support component needed; next258 reuses next254 page-local current-source write slots and adds the next-source stale-slot fence',
            'non_overlap' => 'adds next-source reusable-page handoff and stale-slot fencing after next254 freeblock write-slot publication; does not repeat next254 slot offsets, next249 allocation rows, next245 cursor admission, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next258',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
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
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext254Plan $currentSourcePlan): array
    {
        $currentRows = $currentSourcePlan->currentSourceRows();
        $currentTokens = $currentSourcePlan->currentSourceTokens();
        $rows = [];
        $previousToken = null;
        $activePointerMapPage = null;
        $lastReusableSlotPage = null;

        foreach ($currentRows as $index => $currentRow) {
            $pageNumber = (int) $currentRow['current_source_page'];
            $isPointerMap = $currentRow['current_source_channel'] === 'pointer-map-anchor';
            $isReusable = $currentRow['current_source_channel'] === 'freeblock-write-slot';
            if ($isPointerMap) {
                $activePointerMapPage = $pageNumber;
            }

            $ordinal = $index + 1;
            $currentToken = (string) $currentRow['current_source_token'];
            $writeOffset = (int) $currentRow['current_source_write_offset'];
            $channel = $isPointerMap ? 'pointer-map-fence' : 'next-source-reusable-page';
            $staleSlotFenced = $isPointerMap || ($lastReusableSlotPage === null || $activePointerMapPage !== null);
            $token = self::signature([
                'next258',
                $ordinal,
                $previousToken ?? 'initial',
                $currentToken,
                $pageNumber,
                $channel,
                $activePointerMapPage ?? 0,
                $lastReusableSlotPage ?? 0,
                $writeOffset,
            ]);

            $rows[] = [
                'handoff_ordinal' => $ordinal,
                'current_source_ordinal' => (int) $currentRow['current_source_ordinal'],
                'handoff_page' => $pageNumber,
                'handoff_channel' => $channel,
                'source_current_source_token' => $currentToken,
                'expected_current_source_token' => $currentTokens[$index] ?? null,
                'current_source_token_matches' => ($currentTokens[$index] ?? null) === $currentToken,
                'previous_handoff_token' => $previousToken,
                'active_pointer_map_page' => $activePointerMapPage,
                'previous_reusable_slot_page' => $lastReusableSlotPage,
                'handoff_write_offset' => $writeOffset,
                'pointer_map_fence_before_reuse' => $isPointerMap || $activePointerMapPage !== null,
                'next_reuse_has_current_slot' => !$isReusable || ($writeOffset >= 8 && $writeOffset < 512),
                'stale_freeblock_slot_fenced' => $staleSlotFenced,
                'leaf_receipt_preserved_for_next_source' => !$isReusable || $currentRow['reusable_receipt_current'] === true,
                'handoff_link_valid' => $currentRow['previous_current_source_token'] === ($currentRows[$index - 1]['current_source_token'] ?? null),
                'handoff_state' => 'current-source-next258-next-source-reuse-handoff-ready',
                'handoff_token' => $token,
            ];

            if ($isReusable) {
                $lastReusableSlotPage = $pageNumber;
            }
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

        foreach ($rows as $row) {
            if ($row['handoff_state'] !== 'current-source-next258-next-source-reuse-handoff-ready') {
                $errors[] = "handoff {$row['handoff_ordinal']} is not ready";
            }
            if ((int) $row['handoff_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "handoff {$row['handoff_ordinal']} skipped an ordinal";
            }
            if ((int) $row['current_source_ordinal'] !== (int) $row['handoff_ordinal']) {
                $errors[] = "handoff {$row['handoff_ordinal']} drifted from current-source ordinal";
            }
            if ($row['current_source_token_matches'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} current-source token drifted";
            }
            if ($row['previous_handoff_token'] !== $previousToken) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke token chaining";
            }
            if ($row['pointer_map_fence_before_reuse'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} reused a page before pointer-map fencing";
            }
            if ($row['next_reuse_has_current_slot'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} is missing the current freeblock slot";
            }
            if ($row['stale_freeblock_slot_fenced'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} left a stale freeblock slot visible";
            }
            if ($row['leaf_receipt_preserved_for_next_source'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lost the leaf receipt";
            }
            if ($row['handoff_link_valid'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke current-source link continuity";
            }
            if ($row['handoff_token'] === '') {
                $errors[] = "handoff {$row['handoff_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['handoff_ordinal'];
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
