<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext244Plan
{
    /**
     * @param list<array<string, mixed>> $publishRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext241Plan $sourcePlan,
        private readonly array $publishRows,
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
        return self::fromSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext241Plan::tableLeafFromDeleteResult(
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

    public static function fromSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext241Plan $sourcePlan): self
    {
        $rows = self::buildPublishRows($sourcePlan);
        $errors = self::publishErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next244 publish cursor failed: ' . implode('; ', $errors));
        }

        return new self($sourcePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publishRows(): array
    {
        return $this->publishRows;
    }

    /**
     * @return list<string>
     */
    public function publishErrors(): array
    {
        return self::publishErrorsForRows($this->publishRows);
    }

    /**
     * @return list<int>
     */
    public function publishPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['publish_page'], $this->publishRows));
    }

    /**
     * @return list<int|null>
     */
    public function nextPublishPages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_publish_page'], $this->publishRows));
    }

    /**
     * @return list<int>
     */
    public function publishablePayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['payload_publishable'] === true);
    }

    /**
     * @return list<int>
     */
    public function pointerMapFencePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publish_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapFencePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_publish'] === true);
    }

    /**
     * @return list<string>
     */
    public function publishTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['publish_token'], $this->publishRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function publishSummary(): array
    {
        $sourceSummary = $this->sourcePlan->sourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next244-ready',
            'publish_row_count' => count($this->publishRows),
            'publish_pages' => $this->publishPages(),
            'next_publish_pages' => $this->nextPublishPages(),
            'source_pages' => $sourceSummary['source_pages'],
            'publish_pages_match_source_pages' => $this->publishPages() === $sourceSummary['source_pages'],
            'pointer_map_fence_pages' => $this->pointerMapFencePages(),
            'publishable_payload_pages' => $this->publishablePayloadPages(),
            'duplicate_pointer_map_fence_pages' => $this->duplicatePointerMapFencePages(),
            'all_source_tokens_match' => !in_array(false, array_column($this->publishRows, 'source_token_matches'), true),
            'all_publish_links_current' => !in_array(false, array_column($this->publishRows, 'publish_link_current'), true),
            'all_payload_publish_after_pointer_map' => !in_array(false, array_column($this->publishRows, 'payload_publish_after_pointer_map'), true),
            'all_duplicate_pointer_maps_republished' => !in_array(false, array_column($this->publishRows, 'duplicate_pointer_map_republished'), true),
            'all_freeblock_receipts_published' => !in_array(false, array_column($this->publishRows, 'freeblock_receipt_published'), true),
            'all_tail_pages_excluded_from_publish' => !in_array(false, array_column($this->publishRows, 'tail_page_excluded_from_publish'), true),
            'publish_errors' => $this->publishErrors(),
            'publish_signature' => self::signature($this->publishTokens()),
            'current_source_next244_token' => self::signature(array_merge(
                ['next244', $sourceSummary['current_source_next241_token']],
                $this->publishPages(),
                $this->publishTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next241',
                'sqlite-current-source-next244',
            ],
            'dependency_closure' => 'no new support component needed; next244 reuses next241 current-source cursor rows and adds publish-order validation only',
            'non_overlap' => 'adds current-source publish-order validation after next241 cursor visibility; does not repeat next241 cursor rows, next238 freelist-link admission, next235 checkpoint admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next244',
            'publish_summary' => $this->publishSummary(),
            'publish_errors' => $this->publishErrors(),
            'publish_rows' => $this->publishRows,
            'source_plan' => $this->sourcePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->publishRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['publish_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildPublishRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext241Plan $sourcePlan): array
    {
        $sourceRows = $sourcePlan->sourceRows();
        $sourceTokens = $sourcePlan->sourceTokens();
        $rows = [];
        $previousPublishToken = null;
        $publishedPointerMaps = [];
        $publishedPayloads = [];

        foreach ($sourceRows as $index => $sourceRow) {
            $pageNumber = (int) $sourceRow['source_page'];
            $channel = (string) $sourceRow['source_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $publishedPointerMaps[$pageNumber] = ($publishedPointerMaps[$pageNumber] ?? 0) + 1;
            }

            $payloadPublishable = $channel === 'payload'
                && $publishedPointerMaps !== []
                && $sourceRow['payload_page_keeps_freeblock_receipt'] === true
                && $sourceRow['tail_page_remains_excluded'] === true;

            if ($payloadPublishable) {
                $publishedPayloads[$pageNumber] = true;
            }

            $duplicatePointerMap = $channel === 'pointer-map' && ($publishedPointerMaps[$pageNumber] ?? 0) > 1;
            $nextPublishPage = $sourceRows[$index + 1]['source_page'] ?? null;
            $token = self::signature(array_merge(
                ['next244', $previousPublishToken ?? 'initial', $sourceRow['source_token']],
                [$ordinal, $pageNumber, $nextPublishPage ?? 'eof', $channel, $payloadPublishable, $duplicatePointerMap],
                self::generationParts($publishedPointerMaps),
                self::sortedIntKeys($publishedPayloads),
            ));

            $rows[] = [
                'publish_ordinal' => $ordinal,
                'source_ordinal' => (int) $sourceRow['source_ordinal'],
                'publish_page' => $pageNumber,
                'next_publish_page' => $nextPublishPage,
                'publish_channel' => $channel,
                'source_token' => (string) $sourceRow['source_token'],
                'expected_source_token' => $sourceTokens[$index] ?? null,
                'source_token_matches' => ($sourceTokens[$index] ?? null) === (string) $sourceRow['source_token'],
                'previous_publish_token' => $previousPublishToken,
                'publish_link_current' => $nextPublishPage === ($sourceRows[$index + 1]['source_page'] ?? null),
                'published_pointer_map_generations' => self::generationParts($publishedPointerMaps),
                'published_payload_pages' => self::sortedIntKeys($publishedPayloads),
                'payload_publishable' => $payloadPublishable,
                'payload_publish_after_pointer_map' => $channel !== 'payload' || $payloadPublishable,
                'duplicate_pointer_map_publish' => $duplicatePointerMap,
                'duplicate_pointer_map_republished' => !$duplicatePointerMap || ($publishedPointerMaps[$pageNumber] ?? 0) > 1,
                'freeblock_receipt_published' => $channel !== 'payload' || $sourceRow['payload_page_keeps_freeblock_receipt'] === true,
                'tail_page_excluded_from_publish' => $sourceRow['tail_page_remains_excluded'] === true,
                'publish_state' => 'current-source-next244-publish-cursor-visible',
                'publish_token' => $token,
            ];

            $previousPublishToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function publishErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousOrdinal = 0;
        $previousToken = null;

        foreach ($rows as $row) {
            if ($row['publish_state'] !== 'current-source-next244-publish-cursor-visible') {
                $errors[] = "publish {$row['publish_ordinal']} is not visible";
            }
            if ((int) $row['publish_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "publish {$row['publish_ordinal']} skipped an ordinal";
            }
            if ((int) $row['source_ordinal'] !== (int) $row['publish_ordinal']) {
                $errors[] = "publish {$row['publish_ordinal']} drifted from source ordinal";
            }
            if ($row['source_token_matches'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} source token drifted";
            }
            if ($row['previous_publish_token'] !== $previousToken) {
                $errors[] = "publish {$row['publish_ordinal']} broke token chaining";
            }
            if ($row['publish_link_current'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} has stale next-page link";
            }
            if ($row['payload_publish_after_pointer_map'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} exposed payload before pointer-map publish";
            }
            if ($row['duplicate_pointer_map_republished'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} lost duplicate pointer-map generation";
            }
            if ($row['freeblock_receipt_published'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} lost freeblock receipt";
            }
            if ($row['tail_page_excluded_from_publish'] !== true) {
                $errors[] = "publish {$row['publish_ordinal']} exposed a fenced tail page";
            }
            if ($row['publish_token'] === '') {
                $errors[] = "publish {$row['publish_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['publish_ordinal'];
            $previousToken = (string) $row['publish_token'];
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
