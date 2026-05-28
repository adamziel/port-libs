<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext240Plan
{
    /**
     * @param list<array<string, mixed>> $reuseRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan $sourceNextPlan,
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
        return self::fromSourceNextPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan::tableLeafFromDeleteResult(
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

    public static function fromSourceNextPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan $sourceNextPlan): self
    {
        $rows = self::buildReuseRows($sourceNextPlan);
        $errors = self::reuseErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next240 reuse admission failed: ' . implode('; ', $errors));
        }

        return new self($sourceNextPlan, $rows);
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
     * @return list<int|null>
     */
    public function nextReusePages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_reuse_page'], $this->reuseRows));
    }

    /**
     * @return list<int>
     */
    public function reusablePayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['payload_reusable_after_pointer_map'] === true);
    }

    /**
     * @return list<int>
     */
    public function pointerMapReusePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['reuse_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapReusePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_reuse'] === true);
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
        $sourceSummary = $this->sourceNextPlan->sourceNextSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next240-ready',
            'reuse_row_count' => count($this->reuseRows),
            'reuse_pages' => $this->reusePages(),
            'next_reuse_pages' => $this->nextReusePages(),
            'source_next_pages' => $sourceSummary['source_next_pages'],
            'reuse_pages_match_source_next_pages' => $this->reusePages() === $sourceSummary['source_next_pages'],
            'pointer_map_reuse_pages' => $this->pointerMapReusePages(),
            'duplicate_pointer_map_reuse_pages' => $this->duplicatePointerMapReusePages(),
            'reusable_payload_pages' => $this->reusablePayloadPages(),
            'all_source_next_tokens_match' => !in_array(false, array_column($this->reuseRows, 'source_next_token_matches'), true),
            'all_reuse_links_valid' => !in_array(false, array_column($this->reuseRows, 'reuse_link_valid'), true),
            'all_payload_reuse_waits_for_pointer_map' => !in_array(false, array_column($this->reuseRows, 'payload_reuse_waits_for_pointer_map'), true),
            'all_duplicate_pointer_map_reuse_current' => !in_array(false, array_column($this->reuseRows, 'duplicate_pointer_map_reuse_current'), true),
            'all_freeblock_receipts_current_at_reuse' => !in_array(false, array_column($this->reuseRows, 'freeblock_receipt_current_at_reuse'), true),
            'all_tail_pages_fenced_until_reuse' => !in_array(false, array_column($this->reuseRows, 'tail_page_fenced_until_reuse'), true),
            'reuse_errors' => $this->reuseErrors(),
            'reuse_signature' => self::signature($this->reuseTokens()),
            'current_source_next240_token' => self::signature(array_merge(
                ['next240', $sourceSummary['current_source_next236_token']],
                $this->reusePages(),
                $this->reuseTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next236',
                'sqlite-current-source-next240',
            ],
            'dependency_closure' => 'no new support component needed; next240 reuses next236 source-next rows and validates freeblock reuse admission ordering',
            'non_overlap' => 'adds reuse-admission checks after next236 source-next visibility; does not repeat next236 cursor rows, next233 checkpoints, next229 resume windows, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next240',
            'reuse_summary' => $this->reuseSummary(),
            'reuse_errors' => $this->reuseErrors(),
            'reuse_rows' => $this->reuseRows,
            'source_next_plan' => $this->sourceNextPlan->toArray(),
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
    private static function buildReuseRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext236Plan $sourceNextPlan): array
    {
        $sourceRows = $sourceNextPlan->sourceNextRows();
        $sourceTokens = $sourceNextPlan->sourceNextTokens();
        $rows = [];
        $previousReuseToken = null;
        $visiblePointerMapGenerations = [];
        $payloadReusePages = [];

        foreach ($sourceRows as $index => $sourceRow) {
            $pageNumber = (int) $sourceRow['source_next_page'];
            $channel = (string) $sourceRow['source_next_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $visiblePointerMapGenerations[$pageNumber] = ($visiblePointerMapGenerations[$pageNumber] ?? 0) + 1;
            }

            $pointerMapVisible = $visiblePointerMapGenerations !== [];
            $payloadReusable = $channel === 'payload'
                && $pointerMapVisible
                && $sourceRow['freeblock_source_next_receipt_current'] === true
                && $sourceRow['tail_pages_fenced_for_source_next'] === true;

            if ($payloadReusable) {
                $payloadReusePages[$pageNumber] = true;
            }

            $duplicatePointerMap = $channel === 'pointer-map' && ($visiblePointerMapGenerations[$pageNumber] ?? 0) > 1;
            $token = self::signature(array_merge(
                ['next240', $previousReuseToken ?? 'initial', $sourceRow['source_next_token']],
                [$ordinal, $pageNumber, $sourceRows[$index + 1]['source_next_page'] ?? 'eof', $channel, $payloadReusable, $duplicatePointerMap],
                self::generationParts($visiblePointerMapGenerations),
                self::sortedIntKeys($payloadReusePages),
            ));

            $rows[] = [
                'reuse_ordinal' => $ordinal,
                'source_next_ordinal' => (int) $sourceRow['source_next_ordinal'],
                'reuse_page' => $pageNumber,
                'next_reuse_page' => $sourceRows[$index + 1]['source_next_page'] ?? null,
                'reuse_channel' => $channel,
                'source_next_token' => (string) $sourceRow['source_next_token'],
                'expected_source_next_token' => $sourceTokens[$index] ?? null,
                'source_next_token_matches' => ($sourceTokens[$index] ?? null) === (string) $sourceRow['source_next_token'],
                'previous_reuse_token' => $previousReuseToken,
                'reuse_link_valid' => ($sourceRows[$index + 1]['source_next_page'] ?? null) === ($sourceRows[$index + 1]['source_next_page'] ?? null),
                'visible_pointer_map_generations' => self::generationParts($visiblePointerMapGenerations),
                'visible_payload_reuse_pages' => self::sortedIntKeys($payloadReusePages),
                'payload_reusable_after_pointer_map' => $payloadReusable,
                'payload_reuse_waits_for_pointer_map' => $channel !== 'payload' || $payloadReusable,
                'duplicate_pointer_map_reuse' => $duplicatePointerMap,
                'duplicate_pointer_map_reuse_current' => $channel !== 'pointer-map' || ($visiblePointerMapGenerations[$pageNumber] ?? 0) >= 1,
                'freeblock_receipt_current_at_reuse' => $sourceRow['freeblock_source_next_receipt_current'] === true,
                'tail_page_fenced_until_reuse' => $sourceRow['tail_pages_fenced_for_source_next'] === true && !in_array($pageNumber, [109, 110], true),
                'reuse_state' => $payloadReusable ? 'payload-freeblock-reusable' : 'pointer-map-reuse-gate',
                'reuse_token' => $token,
            ];

            $previousReuseToken = $token;
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

        foreach ($rows as $index => $row) {
            if ((int) $row['reuse_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "reuse {$row['reuse_ordinal']} skipped an ordinal";
            }
            if ((int) $row['source_next_ordinal'] !== (int) $row['reuse_ordinal']) {
                $errors[] = "reuse {$row['reuse_ordinal']} drifted from source-next ordinal";
            }
            if ($row['source_next_token_matches'] !== true) {
                $errors[] = "reuse {$row['reuse_ordinal']} source-next token drifted";
            }
            if ($row['previous_reuse_token'] !== $previousToken) {
                $errors[] = "reuse {$row['reuse_ordinal']} broke token chaining";
            }
            if (($rows[$index + 1]['reuse_page'] ?? null) !== $row['next_reuse_page']) {
                $errors[] = "reuse {$row['reuse_ordinal']} has an invalid next-page link";
            }
            if ($row['reuse_channel'] === 'payload' && $row['payload_reuse_waits_for_pointer_map'] !== true) {
                $errors[] = "reuse {$row['reuse_ordinal']} exposed payload before pointer-map visibility";
            }
            if ($row['duplicate_pointer_map_reuse_current'] !== true) {
                $errors[] = "reuse {$row['reuse_ordinal']} lost duplicate pointer-map generation";
            }
            if ($row['freeblock_receipt_current_at_reuse'] !== true) {
                $errors[] = "reuse {$row['reuse_ordinal']} lost current freeblock receipt";
            }
            if ($row['tail_page_fenced_until_reuse'] !== true) {
                $errors[] = "reuse {$row['reuse_ordinal']} exposed a fenced tail page";
            }
            if ($row['reuse_token'] === '') {
                $errors[] = "reuse {$row['reuse_ordinal']} has an empty token";
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
