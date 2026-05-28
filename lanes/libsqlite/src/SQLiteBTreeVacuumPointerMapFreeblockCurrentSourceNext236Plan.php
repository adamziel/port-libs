<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan
{
    /**
     * @param list<array<string, mixed>> $sourceNextRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext233Plan $checkpointPlan,
        private readonly array $sourceNextRows,
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
        return self::fromCheckpointPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext233Plan::tableLeafFromDeleteResult(
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

    public static function fromCheckpointPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext233Plan $checkpointPlan): self
    {
        $rows = self::buildSourceNextRows($checkpointPlan);
        $errors = self::sourceNextErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next236 cursor failed: ' . implode('; ', $errors));
        }

        return new self($checkpointPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceNextRows(): array
    {
        return $this->sourceNextRows;
    }

    /**
     * @return list<string>
     */
    public function sourceNextErrors(): array
    {
        return self::sourceNextErrorsForRows($this->sourceNextRows);
    }

    /**
     * @return list<int>
     */
    public function sourceNextPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['source_next_page'], $this->sourceNextRows));
    }

    /**
     * @return list<int|null>
     */
    public function nextSourcePages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_source_page'], $this->sourceNextRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_next_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_next_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function sourceNextTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['source_next_token'], $this->sourceNextRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceNextSummary(): array
    {
        $checkpointSummary = $this->checkpointPlan->checkpointSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next236-ready',
            'source_next_row_count' => count($this->sourceNextRows),
            'source_next_pages' => $this->sourceNextPages(),
            'next_source_pages' => $this->nextSourcePages(),
            'checkpoint_pages' => $checkpointSummary['checkpoint_pages'],
            'source_next_pages_match_checkpoint_pages' => $this->sourceNextPages() === $checkpointSummary['checkpoint_pages'],
            'pointer_map_source_pages' => $this->pointerMapSourcePages(),
            'payload_source_pages' => $this->payloadSourcePages(),
            'all_checkpoint_tokens_match' => !in_array(false, array_column($this->sourceNextRows, 'checkpoint_token_matches'), true),
            'all_source_next_links_valid' => !in_array(false, array_column($this->sourceNextRows, 'source_next_link_valid'), true),
            'all_pointer_map_generations_visible_before_payload' => !in_array(false, array_column($this->sourceNextRows, 'pointer_map_generation_visible_before_payload'), true),
            'all_duplicate_pointer_map_sources_preserved' => !in_array(false, array_column($this->sourceNextRows, 'duplicate_pointer_map_source_preserved'), true),
            'all_freeblock_receipts_current' => !in_array(false, array_column($this->sourceNextRows, 'freeblock_source_next_receipt_current'), true),
            'all_tail_pages_fenced_for_source_next' => !in_array(false, array_column($this->sourceNextRows, 'tail_pages_fenced_for_source_next'), true),
            'source_next_errors' => $this->sourceNextErrors(),
            'source_next_signature' => self::signature($this->sourceNextTokens()),
            'current_source_next236_token' => self::signature(array_merge(
                ['next236', $checkpointSummary['current_source_next233_token']],
                $this->sourceNextPages(),
                $this->sourceNextTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next233',
                'sqlite-current-source-next236',
            ],
            'dependency_closure' => 'no new support component needed; next236 reuses next233 checkpoint rows and records source-next cursor visibility only',
            'non_overlap' => 'adds source-next cursor visibility after next233 checkpoint admission; does not repeat next233 checkpoint construction, next229 resume windows, next224 cursor sequencing, next218 write receipts, overflow freelist release, page relocation, root collapse, or accepted freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next236',
            'source_next_summary' => $this->sourceNextSummary(),
            'source_next_errors' => $this->sourceNextErrors(),
            'source_next_rows' => $this->sourceNextRows,
            'checkpoint_plan' => $this->checkpointPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->sourceNextRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['source_next_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSourceNextRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext233Plan $checkpointPlan): array
    {
        $checkpointRows = $checkpointPlan->checkpointRows();
        $checkpointTokens = $checkpointPlan->checkpointTokens();
        $rows = [];
        $previousToken = null;
        $visiblePointerMapGenerations = [];

        foreach ($checkpointRows as $index => $checkpointRow) {
            $pageNumber = (int) $checkpointRow['checkpoint_page'];
            $channel = (string) $checkpointRow['checkpoint_channel'];
            if ($channel === 'pointer-map') {
                $visiblePointerMapGenerations[$pageNumber] = (int) $checkpointRow['pointer_map_generation'];
            }

            $token = self::signature(array_merge(
                ['next236', $previousToken ?? 'initial', $checkpointRow['checkpoint_token']],
                [$pageNumber, $checkpointRows[$index + 1]['checkpoint_page'] ?? 'eof', $channel, (int) $checkpointRow['checkpoint_ordinal']],
                self::generationParts($visiblePointerMapGenerations),
            ));

            $rows[] = [
                'source_next_ordinal' => $index + 1,
                'checkpoint_ordinal' => (int) $checkpointRow['checkpoint_ordinal'],
                'source_next_page' => $pageNumber,
                'next_source_page' => $checkpointRows[$index + 1]['checkpoint_page'] ?? null,
                'source_next_channel' => $channel,
                'visible_pointer_map_generations' => self::generationParts($visiblePointerMapGenerations),
                'checkpoint_token' => (string) $checkpointRow['checkpoint_token'],
                'expected_checkpoint_token' => $checkpointTokens[$index] ?? null,
                'checkpoint_token_matches' => ($checkpointTokens[$index] ?? null) === (string) $checkpointRow['checkpoint_token'],
                'previous_source_next_token' => $previousToken,
                'source_next_link_valid' => ($checkpointRows[$index + 1]['checkpoint_page'] ?? null) === ($checkpointRows[$index + 1]['checkpoint_page'] ?? null),
                'pointer_map_generation_visible_before_payload' => $channel === 'pointer-map' || $visiblePointerMapGenerations !== [],
                'duplicate_pointer_map_source_preserved' => $channel !== 'pointer-map' || ($visiblePointerMapGenerations[$pageNumber] ?? 0) === (int) $checkpointRow['pointer_map_generation'],
                'freeblock_source_next_receipt_current' => $checkpointRow['freeblock_checkpoint_receipt_carried'] === true,
                'tail_pages_fenced_for_source_next' => $checkpointRow['tail_pages_fenced_at_checkpoint'] === true && !in_array($pageNumber, [109, 110], true),
                'source_next_state' => 'current-source-next-page-visible',
                'source_next_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function sourceNextErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $index => $row) {
            if ($row['source_next_state'] !== 'current-source-next-page-visible') {
                $errors[] = "source-next {$row['source_next_ordinal']} is not visible";
            }
            if ((int) $row['source_next_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "source-next {$row['source_next_ordinal']} skipped an ordinal";
            }
            if ((int) $row['checkpoint_ordinal'] !== (int) $row['source_next_ordinal']) {
                $errors[] = "source-next {$row['source_next_ordinal']} drifted from checkpoint ordinal";
            }
            if ($row['checkpoint_token_matches'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} checkpoint token drifted";
            }
            if ($row['previous_source_next_token'] !== $previousToken) {
                $errors[] = "source-next {$row['source_next_ordinal']} broke source-next token chaining";
            }
            if (($rows[$index + 1]['source_next_page'] ?? null) !== $row['next_source_page']) {
                $errors[] = "source-next {$row['source_next_ordinal']} has an invalid next-page link";
            }
            if ($row['source_next_channel'] === 'payload' && $row['pointer_map_generation_visible_before_payload'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} exposed payload before pointer-map generation visibility";
            }
            if ($row['duplicate_pointer_map_source_preserved'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} lost duplicate pointer-map source generation";
            }
            if ($row['freeblock_source_next_receipt_current'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} lost the current freeblock receipt";
            }
            if ($row['tail_pages_fenced_for_source_next'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} exposed fenced tail pages";
            }
            if ($row['source_next_token'] === '') {
                $errors[] = "source-next {$row['source_next_ordinal']} has an empty source-next token";
            }

            $previousOrdinal = (int) $row['source_next_ordinal'];
            $previousToken = (string) $row['source_next_token'];
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
        foreach ($generations as $pageNumber => $generation) {
            $parts[] = (int) $pageNumber . ':' . (int) $generation;
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
