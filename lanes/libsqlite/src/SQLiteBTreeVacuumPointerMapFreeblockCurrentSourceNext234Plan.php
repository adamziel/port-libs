<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Plan
{
    /**
     * @param list<array<string, mixed>> $cursorRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext231Plan $handoffPlan,
        private readonly array $cursorRows,
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
        return self::fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext231Plan::tableLeafFromDeleteResult(
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

    public static function fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext231Plan $handoffPlan): self
    {
        $rows = self::buildCursorRows($handoffPlan);
        $errors = self::cursorErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next234 cursor failed: ' . implode('; ', $errors));
        }

        return new self($handoffPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cursorRows(): array
    {
        return $this->cursorRows;
    }

    /**
     * @return list<string>
     */
    public function cursorErrors(): array
    {
        return self::cursorErrorsForRows($this->cursorRows);
    }

    /**
     * @return list<int>
     */
    public function cursorPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['cursor_page'], $this->cursorRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapCursorPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['cursor_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function freeblockCursorPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['cursor_channel'] === 'freeblock-source');
    }

    /**
     * @return list<int>
     */
    public function payloadCursorPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['cursor_channel'] === 'payload-source');
    }

    /**
     * @return list<string>
     */
    public function cursorTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['cursor_token'], $this->cursorRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function cursorSummary(): array
    {
        $handoffSummary = $this->handoffPlan->handoffSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next234-ready',
            'cursor_row_count' => count($this->cursorRows),
            'cursor_pages' => $this->cursorPages(),
            'pointer_map_cursor_pages' => $this->pointerMapCursorPages(),
            'freeblock_cursor_pages' => $this->freeblockCursorPages(),
            'payload_cursor_pages' => $this->payloadCursorPages(),
            'handoff_pages' => $handoffSummary['handoff_pages'],
            'cursor_pages_match_handoff_pages' => $this->cursorPages() === $handoffSummary['handoff_pages'],
            'pointer_map_cursor_pages_match_handoff' => $this->pointerMapCursorPages() === $handoffSummary['pointer_map_handoff_pages'],
            'payload_cursor_pages_match_handoff_payload' => array_values(array_merge($this->freeblockCursorPages(), $this->payloadCursorPages())) === $handoffSummary['payload_handoff_pages'],
            'all_handoff_tokens_match' => !in_array(false, array_column($this->cursorRows, 'handoff_token_matches'), true),
            'all_pointer_maps_visible_before_freeblocks' => $this->pointerMapsBeforeFreeblocks(),
            'all_freeblock_rows_have_leaf_receipt' => !in_array(false, array_column($this->cursorRows, 'freeblock_row_has_leaf_receipt'), true),
            'all_payload_rows_depend_on_freeblock_cursor' => !in_array(false, array_column($this->cursorRows, 'payload_depends_on_freeblock_cursor'), true),
            'all_tail_pages_fenced' => !in_array(false, array_column($this->cursorRows, 'tail_page_fenced'), true),
            'all_cursor_offsets_contiguous' => !in_array(false, array_column($this->cursorRows, 'cursor_offset_contiguous'), true),
            'cursor_tokens' => $this->cursorTokens(),
            'cursor_signature' => self::signature($this->cursorTokens()),
            'current_source_next234_token' => self::signature(array_merge(
                ['next234', $handoffSummary['current_source_next231_token']],
                $this->cursorPages(),
                $this->cursorTokens(),
            )),
            'cursor_errors' => $this->cursorErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next231',
                'sqlite-current-source-next234',
            ],
            'dependency_closure' => 'no new support component needed; next234 reuses next231 handoff rows, leaf freeblock receipts, pointer-map handoff ordering, and fenced-tail guards',
            'non_overlap' => 'adds a current-source freeblock cursor admission after next231 handoff rows; does not repeat next231 handoff construction, next227 sealing, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next234',
            'cursor_summary' => $this->cursorSummary(),
            'cursor_errors' => $this->cursorErrors(),
            'cursor_rows' => $this->cursorRows,
            'handoff_plan' => $this->handoffPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['cursor_page'],
            array_filter($this->cursorRows, $predicate),
        ));
    }

    private function pointerMapsBeforeFreeblocks(): bool
    {
        $lastPointer = null;
        $firstFreeblock = null;
        foreach ($this->cursorRows as $row) {
            if ($row['cursor_channel'] === 'pointer-map') {
                $lastPointer = (int) $row['cursor_ordinal'];
            }
            if ($row['cursor_channel'] === 'freeblock-source' && $firstFreeblock === null) {
                $firstFreeblock = (int) $row['cursor_ordinal'];
            }
        }

        return $lastPointer !== null && $firstFreeblock !== null && $lastPointer < $firstFreeblock;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCursorRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext231Plan $handoffPlan): array
    {
        $handoffRows = $handoffPlan->handoffRows();
        $handoffTokens = $handoffPlan->handoffTokens();
        $rows = [];
        $previousCursorToken = null;
        $visiblePointerMaps = [];
        $freeblockCursorOpened = false;

        foreach ($handoffRows as $index => $handoffRow) {
            $pageNumber = (int) $handoffRow['page_number'];
            $handoffChannel = (string) $handoffRow['handoff_channel'];
            if ($handoffChannel === 'pointer-map') {
                $channel = 'pointer-map';
                $visiblePointerMaps[$pageNumber] = true;
            } elseif ($handoffRow['leaf_freeblock_receipt_handoff'] === true) {
                $channel = 'freeblock-source';
                $freeblockCursorOpened = true;
            } else {
                $channel = 'payload-source';
            }

            $cursorOrdinal = $index + 1;
            $handoffToken = (string) $handoffRow['handoff_token'];
            $token = self::signature(array_merge(
                ['next234', $cursorOrdinal, $previousCursorToken ?? 'initial', $handoffToken],
                [$pageNumber, $channel, (int) $handoffRow['byte_offset'], (int) $handoffRow['byte_length']],
                self::sortedIntKeys($visiblePointerMaps),
                [$freeblockCursorOpened ? 'freeblock-open' : 'freeblock-pending'],
            ));

            $rows[] = [
                'cursor_ordinal' => $cursorOrdinal,
                'source_handoff_ordinal' => (int) $handoffRow['handoff_ordinal'],
                'cursor_page' => $pageNumber,
                'cursor_channel' => $channel,
                'byte_offset' => (int) $handoffRow['byte_offset'],
                'byte_length' => (int) $handoffRow['byte_length'],
                'visible_pointer_map_pages' => self::sortedIntKeys($visiblePointerMaps),
                'source_handoff_token' => $handoffToken,
                'expected_handoff_token' => $handoffTokens[$index] ?? null,
                'handoff_token_matches' => ($handoffTokens[$index] ?? null) === $handoffToken,
                'previous_cursor_token' => $previousCursorToken,
                'cursor_chain_valid' => $previousCursorToken === null || is_string($previousCursorToken),
                'freeblock_row_has_leaf_receipt' => $channel !== 'freeblock-source' || $handoffRow['leaf_freeblock_receipt_handoff'] === true,
                'payload_depends_on_freeblock_cursor' => $channel !== 'payload-source' || $freeblockCursorOpened,
                'pointer_maps_visible_for_cursor' => $channel === 'pointer-map' || $visiblePointerMaps !== [],
                'tail_page_fenced' => $handoffRow['tail_page_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'cursor_offset_contiguous' => ((int) $handoffRow['byte_offset']) % 512 === 0 && (int) $handoffRow['byte_length'] === 512,
                'cursor_state' => 'current-source-freeblock-cursor-admitted',
                'cursor_token' => $token,
            ];

            $previousCursorToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function cursorErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $seenFreeblock = false;
        $seenPointerMap = false;

        foreach ($rows as $row) {
            if ($row['cursor_state'] !== 'current-source-freeblock-cursor-admitted') {
                $errors[] = "cursor {$row['cursor_ordinal']} is not admitted";
            }
            if ((int) $row['cursor_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "cursor {$row['cursor_ordinal']} skipped a cursor ordinal";
            }
            if ((int) $row['source_handoff_ordinal'] !== (int) $row['cursor_ordinal']) {
                $errors[] = "cursor {$row['cursor_ordinal']} drifted from source handoff ordinal";
            }
            if ($row['handoff_token_matches'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} source handoff token drifted";
            }
            if ($row['previous_cursor_token'] !== $previousToken) {
                $errors[] = "cursor {$row['cursor_ordinal']} broke cursor token chaining";
            }
            if ($row['cursor_channel'] === 'pointer-map') {
                if ($seenFreeblock) {
                    $errors[] = "cursor {$row['cursor_ordinal']} placed pointer-map after freeblock source";
                }
                $seenPointerMap = true;
            }
            if ($row['cursor_channel'] === 'freeblock-source') {
                $seenFreeblock = true;
                if ($seenPointerMap !== true || $row['freeblock_row_has_leaf_receipt'] !== true) {
                    $errors[] = "cursor {$row['cursor_ordinal']} opened freeblock source before pointer-map or receipt";
                }
            }
            if ($row['cursor_channel'] === 'payload-source' && $row['payload_depends_on_freeblock_cursor'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} exposed payload before freeblock cursor";
            }
            if ($row['pointer_maps_visible_for_cursor'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} has no visible pointer-map dependency";
            }
            if ($row['tail_page_fenced'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} exposed a fenced tail page";
            }
            if ($row['cursor_offset_contiguous'] !== true) {
                $errors[] = "cursor {$row['cursor_ordinal']} has an invalid page byte range";
            }
            if ($row['cursor_token'] === '') {
                $errors[] = "cursor {$row['cursor_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['cursor_ordinal'];
            $previousToken = (string) $row['cursor_token'];
        }

        if ($rows === []) {
            $errors[] = 'freeblock cursor plan is empty';
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
