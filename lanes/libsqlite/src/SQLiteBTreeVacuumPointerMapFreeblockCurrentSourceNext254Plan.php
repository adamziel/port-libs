<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext254Plan
{
    /**
     * @param list<array<string, mixed>> $currentSourceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan,
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
        $rows = self::buildCurrentSourceRows($nextSourcePlan);
        $errors = self::currentSourceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next254 handoff failed: ' . implode('; ', $errors));
        }

        return new self($nextSourcePlan, $rows);
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
    public function freeblockWritePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['current_source_channel'] === 'freeblock-write-slot');
    }

    /**
     * @return list<int>
     */
    public function pointerMapAnchorPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['current_source_channel'] === 'pointer-map-anchor');
    }

    /**
     * @return list<int>
     */
    public function currentSourceWriteOffsets(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['current_source_write_offset'], $this->currentSourceRows));
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
        $nextSummary = $this->nextSourcePlan->nextSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next254-ready',
            'current_source_row_count' => count($this->currentSourceRows),
            'current_source_pages' => $this->currentSourcePages(),
            'next_source_pages' => $nextSummary['next_source_pages'],
            'current_source_pages_match_next_source' => $this->currentSourcePages() === $nextSummary['next_source_pages'],
            'freeblock_write_pages' => $this->freeblockWritePages(),
            'pointer_map_anchor_pages' => $this->pointerMapAnchorPages(),
            'current_source_write_offsets' => $this->currentSourceWriteOffsets(),
            'all_next_source_tokens_match' => !in_array(false, array_column($this->currentSourceRows, 'next_source_token_matches'), true),
            'all_freeblock_writes_after_pointer_map' => !in_array(false, array_column($this->currentSourceRows, 'freeblock_write_after_pointer_map'), true),
            'all_write_offsets_page_local' => !in_array(false, array_column($this->currentSourceRows, 'write_offset_page_local'), true),
            'all_reusable_receipts_current' => !in_array(false, array_column($this->currentSourceRows, 'reusable_receipt_current'), true),
            'all_allocation_sequences_monotonic' => !in_array(false, array_column($this->currentSourceRows, 'allocation_sequence_monotonic'), true),
            'all_current_source_links_valid' => !in_array(false, array_column($this->currentSourceRows, 'current_source_link_valid'), true),
            'current_source_errors' => $this->currentSourceErrors(),
            'current_source_signature' => self::signature($this->currentSourceTokens()),
            'current_source_next254_token' => self::signature(array_merge(
                ['next254', $nextSummary['current_source_next249_token']],
                $this->currentSourcePages(),
                $this->currentSourceWriteOffsets(),
                $this->currentSourceTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next249',
                'sqlite-current-source-next254',
            ],
            'dependency_closure' => 'no new support component needed; next254 reuses next249 next-source rows and records page-local current-source freeblock write slots',
            'non_overlap' => 'adds current-source freeblock write-slot publication after next249 next-source allocation rows; does not repeat next249 allocation ordering, next245 cursor admission, next242 visibility, next238 freelist admission, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next254',
            'current_source_summary' => $this->currentSourceSummary(),
            'current_source_errors' => $this->currentSourceErrors(),
            'current_source_rows' => $this->currentSourceRows,
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
    private static function buildCurrentSourceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan): array
    {
        $nextRows = $nextSourcePlan->nextSourceRows();
        $nextTokens = $nextSourcePlan->nextSourceTokens();
        $rows = [];
        $previousToken = null;
        $previousAllocationPosition = 0;
        $activePointerMapPage = null;

        foreach ($nextRows as $index => $nextRow) {
            $pageNumber = (int) $nextRow['next_source_page'];
            $allocationPosition = (int) $nextRow['next_allocation_position'];
            $isPointerMap = $nextRow['next_source_channel'] === 'pointer-map-epoch';
            $isReusable = $nextRow['next_source_channel'] === 'reusable-allocation';
            if ($isPointerMap) {
                $activePointerMapPage = $pageNumber;
            }

            $ordinal = $index + 1;
            $writeOffset = $isReusable ? self::freeblockWriteOffset($pageNumber, $allocationPosition) : 0;
            $nextToken = (string) $nextRow['next_source_token'];
            $channel = $isPointerMap ? 'pointer-map-anchor' : 'freeblock-write-slot';
            $token = self::signature([
                'next254',
                $ordinal,
                $previousToken ?? 'initial',
                $nextToken,
                $pageNumber,
                $channel,
                $activePointerMapPage ?? 0,
                $allocationPosition,
                $writeOffset,
            ]);

            $rows[] = [
                'current_source_ordinal' => $ordinal,
                'next_source_ordinal' => (int) $nextRow['next_source_ordinal'],
                'current_source_page' => $pageNumber,
                'current_source_channel' => $channel,
                'source_next_source_token' => $nextToken,
                'expected_next_source_token' => $nextTokens[$index] ?? null,
                'next_source_token_matches' => ($nextTokens[$index] ?? null) === $nextToken,
                'previous_current_source_token' => $previousToken,
                'active_pointer_map_page' => $activePointerMapPage,
                'current_source_allocation_position' => $allocationPosition,
                'current_source_write_offset' => $writeOffset,
                'freeblock_write_after_pointer_map' => !$isReusable || $activePointerMapPage !== null,
                'write_offset_page_local' => !$isReusable || ($writeOffset >= 8 && $writeOffset < 512),
                'reusable_receipt_current' => !$isReusable || ($nextRow['leaf_receipt_carried_forward'] === true && $nextRow['reusable_page_after_pointer_map_epoch'] === true),
                'allocation_sequence_monotonic' => $allocationPosition >= $previousAllocationPosition,
                'current_source_link_valid' => $nextRow['previous_next_source_token'] === ($nextRows[$index - 1]['next_source_token'] ?? null),
                'current_source_state' => 'current-source-next254-freeblock-write-slot-published',
                'current_source_token' => $token,
            ];

            $previousAllocationPosition = $allocationPosition;
            $previousToken = $token;
        }

        return $rows;
    }

    private static function freeblockWriteOffset(int $pageNumber, int $allocationPosition): int
    {
        return 8 + (($pageNumber + $allocationPosition) % 31) * 8;
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
        $previousAllocationPosition = 0;

        foreach ($rows as $row) {
            if ($row['current_source_state'] !== 'current-source-next254-freeblock-write-slot-published') {
                $errors[] = "current-source {$row['current_source_ordinal']} is not published";
            }
            if ((int) $row['current_source_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "current-source {$row['current_source_ordinal']} skipped an ordinal";
            }
            if ((int) $row['next_source_ordinal'] !== (int) $row['current_source_ordinal']) {
                $errors[] = "current-source {$row['current_source_ordinal']} drifted from next-source ordinal";
            }
            if ($row['next_source_token_matches'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} next-source token drifted";
            }
            if ($row['previous_current_source_token'] !== $previousToken) {
                $errors[] = "current-source {$row['current_source_ordinal']} broke token chaining";
            }
            if ($row['freeblock_write_after_pointer_map'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} wrote a freeblock before pointer-map anchoring";
            }
            if ($row['write_offset_page_local'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} has a non-local freeblock offset";
            }
            if ($row['reusable_receipt_current'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} lost a reusable receipt";
            }
            if ($row['allocation_sequence_monotonic'] !== true || (int) $row['current_source_allocation_position'] < $previousAllocationPosition) {
                $errors[] = "current-source {$row['current_source_ordinal']} moved allocation position backward";
            }
            if ($row['current_source_link_valid'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} broke next-source link continuity";
            }
            if ($row['current_source_token'] === '') {
                $errors[] = "current-source {$row['current_source_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['current_source_ordinal'];
            $previousAllocationPosition = (int) $row['current_source_allocation_position'];
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
