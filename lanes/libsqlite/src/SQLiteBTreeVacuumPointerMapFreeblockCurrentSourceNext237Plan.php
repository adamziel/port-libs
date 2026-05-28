<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext237Plan
{
    /**
     * @param list<array<string, mixed>> $reuseRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Plan $cursorPlan,
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
        return self::fromCursorPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Plan::tableLeafFromDeleteResult(
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

    public static function fromCursorPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Plan $cursorPlan): self
    {
        $rows = self::buildReuseRows($cursorPlan);
        $errors = self::reuseErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next237 reuse barrier failed: ' . implode('; ', $errors));
        }

        return new self($cursorPlan, $rows);
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
    public function reusablePayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reuse_channel'] === 'payload-reuse');
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
    public function freeblockBarrierPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reuse_channel'] === 'freeblock-barrier');
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
        $cursorSummary = $this->cursorPlan->cursorSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next237-ready',
            'reuse_row_count' => count($this->reuseRows),
            'reuse_pages' => $this->reusePages(),
            'cursor_pages' => $cursorSummary['cursor_pages'],
            'reuse_pages_match_cursor_pages' => $this->reusePages() === $cursorSummary['cursor_pages'],
            'pointer_map_barrier_pages' => $this->pointerMapBarrierPages(),
            'freeblock_barrier_pages' => $this->freeblockBarrierPages(),
            'reusable_payload_pages' => $this->reusablePayloadPages(),
            'all_cursor_tokens_match' => !in_array(false, array_column($this->reuseRows, 'cursor_token_matches'), true),
            'all_reuse_links_valid' => !in_array(false, array_column($this->reuseRows, 'reuse_link_valid'), true),
            'all_payload_reuse_waits_for_freeblock' => !in_array(false, array_column($this->reuseRows, 'payload_reuse_waits_for_freeblock'), true),
            'all_payload_reuse_has_pointer_maps' => !in_array(false, array_column($this->reuseRows, 'payload_reuse_has_pointer_maps'), true),
            'all_freeblock_barriers_have_receipts' => !in_array(false, array_column($this->reuseRows, 'freeblock_barrier_has_leaf_receipt'), true),
            'all_tail_pages_stay_fenced' => !in_array(false, array_column($this->reuseRows, 'tail_page_stays_fenced'), true),
            'all_reuse_offsets_contiguous' => !in_array(false, array_column($this->reuseRows, 'reuse_offset_contiguous'), true),
            'reuse_tokens' => $this->reuseTokens(),
            'reuse_signature' => self::signature($this->reuseTokens()),
            'current_source_next237_token' => self::signature(array_merge(
                ['next237', $cursorSummary['current_source_next234_token']],
                $this->reusePages(),
                $this->reuseTokens(),
            )),
            'reuse_errors' => $this->reuseErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next234',
                'sqlite-current-source-next237',
            ],
            'dependency_closure' => 'no new support component needed; next237 reuses next234 cursor rows, pointer-map visibility, freeblock receipts, and fenced-tail guards',
            'non_overlap' => 'adds a reuse barrier after next234 current-source cursor admission; does not repeat next234 cursor construction, next231 handoff rows, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next237',
            'reuse_summary' => $this->reuseSummary(),
            'reuse_errors' => $this->reuseErrors(),
            'reuse_rows' => $this->reuseRows,
            'cursor_plan' => $this->cursorPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['reuse_page'],
            array_filter($this->reuseRows, $predicate),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReuseRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Plan $cursorPlan): array
    {
        $cursorRows = $cursorPlan->cursorRows();
        $cursorTokens = $cursorPlan->cursorTokens();
        $rows = [];
        $previousToken = null;
        $visiblePointerMaps = [];
        $freeblockBarrierOpen = false;

        foreach ($cursorRows as $index => $cursorRow) {
            $pageNumber = (int) $cursorRow['cursor_page'];
            $cursorChannel = (string) $cursorRow['cursor_channel'];
            if ($cursorChannel === 'pointer-map') {
                $channel = 'pointer-map-barrier';
                $visiblePointerMaps[$pageNumber] = true;
            } elseif ($cursorChannel === 'freeblock-source') {
                $channel = 'freeblock-barrier';
                $freeblockBarrierOpen = true;
            } else {
                $channel = 'payload-reuse';
            }

            $ordinal = $index + 1;
            $cursorToken = (string) $cursorRow['cursor_token'];
            $token = self::signature(array_merge(
                ['next237', $ordinal, $previousToken ?? 'initial', $cursorToken],
                [$pageNumber, $channel, (int) $cursorRow['byte_offset'], (int) $cursorRow['byte_length']],
                self::sortedIntKeys($visiblePointerMaps),
                [$freeblockBarrierOpen ? 'freeblock-barrier-open' : 'freeblock-barrier-pending'],
            ));

            $rows[] = [
                'reuse_ordinal' => $ordinal,
                'cursor_ordinal' => (int) $cursorRow['cursor_ordinal'],
                'reuse_page' => $pageNumber,
                'reuse_channel' => $channel,
                'byte_offset' => (int) $cursorRow['byte_offset'],
                'byte_length' => (int) $cursorRow['byte_length'],
                'visible_pointer_map_pages' => self::sortedIntKeys($visiblePointerMaps),
                'source_cursor_token' => $cursorToken,
                'expected_cursor_token' => $cursorTokens[$index] ?? null,
                'cursor_token_matches' => ($cursorTokens[$index] ?? null) === $cursorToken,
                'previous_reuse_token' => $previousToken,
                'reuse_link_valid' => $cursorRow['previous_cursor_token'] === ($cursorRows[$index - 1]['cursor_token'] ?? null),
                'freeblock_barrier_has_leaf_receipt' => $channel !== 'freeblock-barrier' || $cursorRow['freeblock_row_has_leaf_receipt'] === true,
                'payload_reuse_waits_for_freeblock' => $channel !== 'payload-reuse' || $freeblockBarrierOpen,
                'payload_reuse_has_pointer_maps' => $channel !== 'payload-reuse' || $visiblePointerMaps !== [],
                'tail_page_stays_fenced' => $cursorRow['tail_page_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'reuse_offset_contiguous' => ((int) $cursorRow['byte_offset']) % 512 === 0 && (int) $cursorRow['byte_length'] === 512,
                'reuse_state' => 'current-source-reuse-barrier-admitted',
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
        $freeblockSeen = false;
        $pointerMapSeen = false;

        foreach ($rows as $row) {
            if ($row['reuse_state'] !== 'current-source-reuse-barrier-admitted') {
                $errors[] = "reuse {$row['reuse_ordinal']} is not admitted";
            }
            if ((int) $row['reuse_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "reuse {$row['reuse_ordinal']} skipped a reuse ordinal";
            }
            if ((int) $row['cursor_ordinal'] !== (int) $row['reuse_ordinal']) {
                $errors[] = "reuse {$row['reuse_ordinal']} drifted from cursor ordinal";
            }
            if ($row['cursor_token_matches'] !== true) {
                $errors[] = "reuse {$row['reuse_ordinal']} cursor token drifted";
            }
            if ($row['previous_reuse_token'] !== $previousToken) {
                $errors[] = "reuse {$row['reuse_ordinal']} broke reuse token chaining";
            }
            if ($row['reuse_link_valid'] !== true) {
                $errors[] = "reuse {$row['reuse_ordinal']} broke source cursor linkage";
            }
            if ($row['reuse_channel'] === 'pointer-map-barrier') {
                if ($freeblockSeen) {
                    $errors[] = "reuse {$row['reuse_ordinal']} placed pointer-map after freeblock barrier";
                }
                $pointerMapSeen = true;
            }
            if ($row['reuse_channel'] === 'freeblock-barrier') {
                $freeblockSeen = true;
                if ($pointerMapSeen !== true || $row['freeblock_barrier_has_leaf_receipt'] !== true) {
                    $errors[] = "reuse {$row['reuse_ordinal']} opened freeblock barrier before pointer-map or receipt";
                }
            }
            if ($row['reuse_channel'] === 'payload-reuse') {
                if ($row['payload_reuse_waits_for_freeblock'] !== true) {
                    $errors[] = "reuse {$row['reuse_ordinal']} reused payload before freeblock barrier";
                }
                if ($row['payload_reuse_has_pointer_maps'] !== true) {
                    $errors[] = "reuse {$row['reuse_ordinal']} reused payload without pointer-map visibility";
                }
            }
            if ($row['tail_page_stays_fenced'] !== true) {
                $errors[] = "reuse {$row['reuse_ordinal']} exposed a fenced tail page";
            }
            if ($row['reuse_offset_contiguous'] !== true) {
                $errors[] = "reuse {$row['reuse_ordinal']} has an invalid page byte range";
            }
            if ($row['reuse_token'] === '') {
                $errors[] = "reuse {$row['reuse_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['reuse_ordinal'];
            $previousToken = (string) $row['reuse_token'];
        }

        if ($rows === []) {
            $errors[] = 'reuse barrier plan is empty';
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
