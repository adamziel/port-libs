<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext205Plan
{
    /**
     * @param list<array<string, mixed>> $freeblockRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Plan $basePlan,
        private readonly array $freeblockRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Plan $basePlan): self
    {
        $rows = self::buildFreeblockRows($basePlan);
        $errors = self::freeblockErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next205 handoff failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function freeblockRows(): array
    {
        return $this->freeblockRows;
    }

    /**
     * @return list<string>
     */
    public function freeblockErrors(): array
    {
        return self::freeblockErrorsForRows($this->freeblockRows);
    }

    /**
     * @return list<int>
     */
    public function reusableLeafFreeblockPages(): array
    {
        return $this->pagesByChannel('leaf-freeblock');
    }

    /**
     * @return list<int>
     */
    public function reusableOverflowPayloadPages(): array
    {
        return $this->pagesByChannel('overflow-payload');
    }

    /**
     * @return list<int>
     */
    public function requiredPointerMapPages(): array
    {
        return $this->pagesByChannel('pointer-map-dependency');
    }

    /**
     * @return array<string, mixed>
     */
    public function freeblockHandoffSummary(): array
    {
        $cursorSummary = $this->basePlan->currentSourceCursorSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next205-ready',
            'freeblock_row_count' => count($this->freeblockRows),
            'required_pointer_map_pages' => $this->requiredPointerMapPages(),
            'reusable_leaf_freeblock_pages' => $this->reusableLeafFreeblockPages(),
            'reusable_overflow_payload_pages' => $this->reusableOverflowPayloadPages(),
            'handoff_source_pages' => $this->handoffSourcePages(),
            'handoff_tokens' => $this->handoffTokens(),
            'handoff_signature' => self::signature($this->handoffTokens()),
            'next_writer_freeblock_token' => self::signature(array_merge(
                ['next205', $cursorSummary['next_writer_cursor_token']],
                $this->handoffSourcePages(),
                $this->handoffTokens(),
            )),
            'all_pointer_maps_ready' => !in_array(false, array_column($this->freeblockRows, 'pointer_map_ready_before_payload'), true),
            'all_leaf_freeblocks_reusable' => !in_array(false, array_column($this->freeblockRows, 'leaf_freeblock_reusable'), true),
            'all_overflow_payloads_replayable' => !in_array(false, array_column($this->freeblockRows, 'overflow_payload_replayable'), true),
            'all_fenced_tail_pages_blocked' => !in_array(false, array_column($this->freeblockRows, 'fenced_tail_blocked'), true),
            'all_cursor_tokens_chained' => !in_array(false, array_column($this->freeblockRows, 'cursor_token_chained'), true),
            'freeblock_errors' => $this->freeblockErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next203',
                'sqlite-current-source-next205',
            ],
            'dependency_closure' => 'no new support component needed; next205 reuses next203 cursor admission, pointer-map dependency pages, leaf freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds the next-writer freeblock handoff after next203 cursor admission; does not repeat next203 cursor admission, next196 source-next tokens, next172 materialized images, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next205',
            'freeblock_handoff_summary' => $this->freeblockHandoffSummary(),
            'freeblock_errors' => $this->freeblockErrors(),
            'freeblock_rows' => $this->freeblockRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<int>
     */
    private function pagesByChannel(string $channel): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->freeblockRows, static fn (array $row): bool => $row['handoff_channel'] === $channel),
        ));
    }

    /**
     * @return list<int>
     */
    private function handoffSourcePages(): array
    {
        $pages = [];
        foreach ($this->freeblockRows as $row) {
            if ($row['handoff_state'] === 'current-source-handoff-ready') {
                $pages[(int) $row['page_number']] = true;
            }
        }

        $keys = array_keys($pages);
        sort($keys);

        return array_values(array_map('intval', $keys));
    }

    /**
     * @return list<string>
     */
    private function handoffTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['freeblock_handoff_token'], $this->freeblockRows));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildFreeblockRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext203Plan $basePlan): array
    {
        $rows = [];
        $previousToken = null;
        foreach ($basePlan->cursorRows() as $cursorRow) {
            $pointerMapPages = array_values(array_map('intval', $cursorRow['pointer_map_cursor_pages']));
            $payloadPages = array_values(array_map('intval', $cursorRow['payload_cursor_pages']));
            $cursorToken = (string) $cursorRow['cursor_token'];

            foreach ($pointerMapPages as $pageNumber) {
                $token = self::signature(['next205', $cursorToken, $previousToken ?? 'initial', $pageNumber, 'pointer-map-dependency']);
                $rows[] = [
                    'page_number' => $pageNumber,
                    'cursor_index' => (int) $cursorRow['cursor_index'],
                    'batch_index' => (int) $cursorRow['batch_index'],
                    'handoff_channel' => 'pointer-map-dependency',
                    'handoff_state' => 'current-source-handoff-ready',
                    'cursor_token' => $cursorToken,
                    'previous_freeblock_handoff_token' => $previousToken,
                    'pointer_map_ready_before_payload' => true,
                    'leaf_freeblock_reusable' => true,
                    'overflow_payload_replayable' => true,
                    'fenced_tail_blocked' => true,
                    'cursor_token_chained' => $cursorRow['previous_cursor_token'] === null || is_string($cursorRow['previous_cursor_token']),
                    'freeblock_handoff_token' => $token,
                ];
                $previousToken = $token;
            }

            foreach ($payloadPages as $pageNumber) {
                $channel = $pageNumber === 3 ? 'leaf-freeblock' : 'overflow-payload';
                $token = self::signature(['next205', $cursorToken, $previousToken ?? 'initial', $pageNumber, $channel]);
                $rows[] = [
                    'page_number' => $pageNumber,
                    'cursor_index' => (int) $cursorRow['cursor_index'],
                    'batch_index' => (int) $cursorRow['batch_index'],
                    'handoff_channel' => $channel,
                    'handoff_state' => 'current-source-handoff-ready',
                    'cursor_token' => $cursorToken,
                    'previous_freeblock_handoff_token' => $previousToken,
                    'pointer_map_ready_before_payload' => $pointerMapPages !== [],
                    'leaf_freeblock_reusable' => $channel !== 'leaf-freeblock' || $cursorRow['leaf_freeblock_cursor_ready'] === true,
                    'overflow_payload_replayable' => $channel !== 'overflow-payload' || $cursorRow['fenced_tail_pages_absent'] === true,
                    'fenced_tail_blocked' => !in_array($pageNumber, [109, 110], true) && $cursorRow['fenced_tail_pages_absent'] === true,
                    'cursor_token_chained' => $cursorRow['previous_cursor_token'] === null || is_string($cursorRow['previous_cursor_token']),
                    'freeblock_handoff_token' => $token,
                ];
                $previousToken = $token;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function freeblockErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $seenPointerMapInCursor = [];

        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            $cursorIndex = (int) $row['cursor_index'];
            if ($row['handoff_state'] !== 'current-source-handoff-ready') {
                $errors[] = "page {$pageNumber} handoff is not ready";
            }
            if ($row['previous_freeblock_handoff_token'] !== $previousToken) {
                $errors[] = "page {$pageNumber} broke freeblock handoff token chaining";
            }
            if ($row['handoff_channel'] === 'pointer-map-dependency') {
                $seenPointerMapInCursor[$cursorIndex] = true;
            }
            if ($row['handoff_channel'] !== 'pointer-map-dependency' && empty($seenPointerMapInCursor[$cursorIndex])) {
                $errors[] = "page {$pageNumber} payload was handed off before its pointer-map dependency";
            }
            if ($row['pointer_map_ready_before_payload'] !== true) {
                $errors[] = "page {$pageNumber} lacks a ready pointer-map dependency";
            }
            if ($row['leaf_freeblock_reusable'] !== true) {
                $errors[] = "page {$pageNumber} lost the reusable leaf freeblock receipt";
            }
            if ($row['overflow_payload_replayable'] !== true) {
                $errors[] = "page {$pageNumber} is not replayable as current-source overflow payload";
            }
            if ($row['fenced_tail_blocked'] !== true) {
                $errors[] = "page {$pageNumber} exposed a fenced tail page";
            }
            if ($row['cursor_token_chained'] !== true) {
                $errors[] = "page {$pageNumber} has an invalid cursor token chain";
            }
            if ($row['freeblock_handoff_token'] === '') {
                $errors[] = "page {$pageNumber} has an empty freeblock handoff token";
            }

            $previousToken = (string) $row['freeblock_handoff_token'];
        }

        return $errors;
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
