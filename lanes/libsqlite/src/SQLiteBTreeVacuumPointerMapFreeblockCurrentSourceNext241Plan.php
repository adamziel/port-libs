<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext241Plan
{
    /**
     * @param list<array<string, mixed>> $sourceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $freelistPlan,
        private readonly array $sourceRows,
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
        return self::fromFreelistPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext238(
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

    public static function fromFreelistPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $freelistPlan): self
    {
        $rows = self::buildSourceRows($freelistPlan);
        $errors = self::sourceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next241 source cursor failed: ' . implode('; ', $errors));
        }

        return new self($freelistPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceRows(): array
    {
        return $this->sourceRows;
    }

    /**
     * @return list<string>
     */
    public function sourceErrors(): array
    {
        return self::sourceErrorsForRows($this->sourceRows);
    }

    /**
     * @return list<int>
     */
    public function sourcePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['source_page'], $this->sourceRows));
    }

    /**
     * @return list<int|null>
     */
    public function nextSourcePages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_source_page'], $this->sourceRows));
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_replay'] === true);
    }

    /**
     * @return list<int>
     */
    public function reusablePayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'payload');
    }

    /**
     * @return list<int>
     */
    public function pointerMapBarrierPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'pointer-map');
    }

    /**
     * @return list<string>
     */
    public function sourceTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['source_token'], $this->sourceRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function sourceSummary(): array
    {
        $freelistSummary = $this->freelistPlan->freelistSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next241-ready',
            'source_row_count' => count($this->sourceRows),
            'source_pages' => $this->sourcePages(),
            'next_source_pages' => $this->nextSourcePages(),
            'freelist_pages' => $freelistSummary['freelist_pages'],
            'source_pages_match_freelist_pages' => $this->sourcePages() === $freelistSummary['freelist_pages'],
            'pointer_map_barrier_pages' => $this->pointerMapBarrierPages(),
            'reusable_payload_pages' => $this->reusablePayloadPages(),
            'duplicate_pointer_map_pages' => $this->duplicatePointerMapPages(),
            'all_freelist_tokens_match' => !in_array(false, array_column($this->sourceRows, 'freelist_token_matches'), true),
            'all_source_links_current' => !in_array(false, array_column($this->sourceRows, 'source_link_current'), true),
            'all_pointer_map_barriers_replayed_before_payload' => !in_array(false, array_column($this->sourceRows, 'pointer_map_barrier_replayed_before_payload'), true),
            'all_payload_pages_keep_freeblock_receipts' => !in_array(false, array_column($this->sourceRows, 'payload_page_keeps_freeblock_receipt'), true),
            'all_duplicate_pointer_maps_keep_generation' => !in_array(false, array_column($this->sourceRows, 'duplicate_pointer_map_keeps_generation'), true),
            'all_tail_pages_remain_excluded' => !in_array(false, array_column($this->sourceRows, 'tail_page_remains_excluded'), true),
            'source_errors' => $this->sourceErrors(),
            'source_signature' => self::signature($this->sourceTokens()),
            'current_source_next241_token' => self::signature(array_merge(
                ['next241', $freelistSummary['current_source_next238_token']],
                $this->sourcePages(),
                $this->sourceTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next238',
                'sqlite-current-source-next241',
            ],
            'dependency_closure' => 'no new support component needed; next241 reuses next238 freelist-link rows and adds current-source cursor validation only',
            'non_overlap' => 'adds current-source freelist cursor validation after next238 freelist admission; does not repeat next238 freelist-link admission, next235 checkpoint admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next241',
            'source_summary' => $this->sourceSummary(),
            'source_errors' => $this->sourceErrors(),
            'source_rows' => $this->sourceRows,
            'freelist_plan' => $this->freelistPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->sourceRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['source_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSourceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $freelistPlan): array
    {
        $freelistRows = $freelistPlan->freelistRows();
        $freelistTokens = $freelistPlan->freelistTokens();
        $rows = [];
        $previousToken = null;
        $seenPointerMaps = [];
        $pointerMapGenerations = [];

        foreach ($freelistRows as $index => $freelistRow) {
            $pageNumber = (int) $freelistRow['freelist_page'];
            $channel = (string) $freelistRow['freelist_channel'];
            if ($channel === 'pointer-map') {
                $seenPointerMaps[$pageNumber] = true;
                $pointerMapGenerations[$pageNumber] = ($pointerMapGenerations[$pageNumber] ?? 0) + 1;
            }

            $nextSourcePage = $freelistRows[$index + 1]['freelist_page'] ?? null;
            $duplicatePointerMap = $channel === 'pointer-map' && ($pointerMapGenerations[$pageNumber] ?? 0) > 1;
            $token = self::signature(array_merge(
                ['next241', $previousToken ?? 'initial', $freelistRow['freelist_token']],
                [$index + 1, $pageNumber, $nextSourcePage ?? 'eof', $channel, $duplicatePointerMap],
                self::generationParts($pointerMapGenerations),
                self::sortedIntKeys($seenPointerMaps),
            ));

            $rows[] = [
                'source_ordinal' => $index + 1,
                'freelist_ordinal' => (int) $freelistRow['freelist_ordinal'],
                'source_page' => $pageNumber,
                'next_source_page' => $nextSourcePage,
                'source_channel' => $channel,
                'source_freelist_token' => (string) $freelistRow['freelist_token'],
                'expected_freelist_token' => $freelistTokens[$index] ?? null,
                'freelist_token_matches' => ($freelistTokens[$index] ?? null) === (string) $freelistRow['freelist_token'],
                'previous_source_token' => $previousToken,
                'visible_pointer_map_pages' => self::sortedIntKeys($seenPointerMaps),
                'pointer_map_generations' => self::generationParts($pointerMapGenerations),
                'duplicate_pointer_map_replay' => $duplicatePointerMap,
                'source_link_current' => $nextSourcePage === ($freelistRows[$index + 1]['freelist_page'] ?? null),
                'pointer_map_barrier_replayed_before_payload' => $channel !== 'payload' || $seenPointerMaps !== [],
                'payload_page_keeps_freeblock_receipt' => $channel !== 'payload' || $freelistRow['freeblock_receipt_admitted_to_freelist'] === true,
                'duplicate_pointer_map_keeps_generation' => !$duplicatePointerMap || ($pointerMapGenerations[$pageNumber] ?? 0) > 1,
                'tail_page_remains_excluded' => !in_array($pageNumber, [109, 110], true) && $freelistRow['tail_page_blocked_from_freelist'] === true,
                'source_state' => 'current-source-next241-freelist-cursor-visible',
                'source_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function sourceErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousOrdinal = 0;
        $previousToken = null;

        foreach ($rows as $row) {
            if ($row['source_state'] !== 'current-source-next241-freelist-cursor-visible') {
                $errors[] = "source {$row['source_ordinal']} is not visible";
            }
            if ((int) $row['source_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "source {$row['source_ordinal']} skipped an ordinal";
            }
            if ((int) $row['freelist_ordinal'] !== (int) $row['source_ordinal']) {
                $errors[] = "source {$row['source_ordinal']} drifted from freelist ordinal";
            }
            if ($row['freelist_token_matches'] !== true) {
                $errors[] = "source {$row['source_ordinal']} freelist token drifted";
            }
            if ($row['previous_source_token'] !== $previousToken) {
                $errors[] = "source {$row['source_ordinal']} broke token chaining";
            }
            if ($row['source_link_current'] !== true) {
                $errors[] = "source {$row['source_ordinal']} has stale next-page link";
            }
            if ($row['pointer_map_barrier_replayed_before_payload'] !== true) {
                $errors[] = "source {$row['source_ordinal']} exposed payload before pointer-map barrier";
            }
            if ($row['payload_page_keeps_freeblock_receipt'] !== true) {
                $errors[] = "source {$row['source_ordinal']} lost freeblock receipt";
            }
            if ($row['duplicate_pointer_map_keeps_generation'] !== true) {
                $errors[] = "source {$row['source_ordinal']} lost duplicate pointer-map generation";
            }
            if ($row['tail_page_remains_excluded'] !== true) {
                $errors[] = "source {$row['source_ordinal']} exposed a fenced tail page";
            }
            if ($row['source_token'] === '') {
                $errors[] = "source {$row['source_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['source_ordinal'];
            $previousToken = (string) $row['source_token'];
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

        return array_values(array_map(
            static fn (int $page, int $generation): string => $page . ':' . $generation,
            array_keys($generations),
            array_values($generations),
        ));
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
