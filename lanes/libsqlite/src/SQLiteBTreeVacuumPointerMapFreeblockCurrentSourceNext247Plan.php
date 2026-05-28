<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext247Plan
{
    /**
     * @param list<array<string, mixed>> $checkpointRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext244Plan $publishPlan,
        private readonly array $checkpointRows,
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
        return self::fromPublishPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext244Plan::tableLeafFromDeleteResult(
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

    public static function fromPublishPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext244Plan $publishPlan): self
    {
        $rows = self::buildCheckpointRows($publishPlan);
        $errors = self::checkpointErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next247 checkpoint failed: ' . implode('; ', $errors));
        }

        return new self($publishPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checkpointRows(): array
    {
        return $this->checkpointRows;
    }

    /**
     * @return list<string>
     */
    public function checkpointErrors(): array
    {
        return self::checkpointErrorsForRows($this->checkpointRows);
    }

    /**
     * @return list<int>
     */
    public function checkpointPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['checkpoint_page'], $this->checkpointRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapCheckpointPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['checkpoint_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadCheckpointPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['checkpoint_channel'] === 'payload');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapCheckpointPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_checkpoint'] === true);
    }

    /**
     * @return list<string>
     */
    public function checkpointTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['checkpoint_token'], $this->checkpointRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function checkpointSummary(): array
    {
        $publishSummary = $this->publishPlan->publishSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next247-ready',
            'checkpoint_row_count' => count($this->checkpointRows),
            'checkpoint_pages' => $this->checkpointPages(),
            'publish_pages' => $publishSummary['publish_pages'],
            'checkpoint_pages_match_publish_pages' => $this->checkpointPages() === $publishSummary['publish_pages'],
            'pointer_map_checkpoint_pages' => $this->pointerMapCheckpointPages(),
            'payload_checkpoint_pages' => $this->payloadCheckpointPages(),
            'duplicate_pointer_map_checkpoint_pages' => $this->duplicatePointerMapCheckpointPages(),
            'all_publish_tokens_match' => !in_array(false, array_column($this->checkpointRows, 'publish_token_matches'), true),
            'all_checkpoint_links_current' => !in_array(false, array_column($this->checkpointRows, 'checkpoint_link_current'), true),
            'all_payload_checkpoint_after_pointer_map' => !in_array(false, array_column($this->checkpointRows, 'payload_checkpoint_after_pointer_map'), true),
            'all_freeblock_receipts_checkpointed' => !in_array(false, array_column($this->checkpointRows, 'freeblock_receipt_checkpointed'), true),
            'all_tail_pages_excluded_from_checkpoint' => !in_array(false, array_column($this->checkpointRows, 'tail_page_excluded_from_checkpoint'), true),
            'checkpoint_errors' => $this->checkpointErrors(),
            'checkpoint_signature' => self::signature($this->checkpointTokens()),
            'current_source_next247_token' => self::signature(array_merge(
                ['next247', $publishSummary['current_source_next244_token']],
                $this->checkpointPages(),
                $this->checkpointTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next244',
                'sqlite-current-source-next247',
            ],
            'dependency_closure' => 'no new support component needed; next247 reuses next244 publish cursor rows and adds checkpoint admission for pointer-map/freeblock visibility',
            'non_overlap' => 'adds current-source checkpoint admission after next244 publish visibility; does not repeat next244 publish cursor construction, next241 source cursor rows, next238 freelist-link admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next247',
            'checkpoint_summary' => $this->checkpointSummary(),
            'checkpoint_errors' => $this->checkpointErrors(),
            'checkpoint_rows' => $this->checkpointRows,
            'publish_plan' => $this->publishPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->checkpointRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['checkpoint_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCheckpointRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext244Plan $publishPlan): array
    {
        $publishRows = $publishPlan->publishRows();
        $publishTokens = $publishPlan->publishTokens();
        $rows = [];
        $previousToken = null;
        $checkpointedPointerMaps = [];
        $checkpointedPayloads = [];

        foreach ($publishRows as $index => $publishRow) {
            $pageNumber = (int) $publishRow['publish_page'];
            $channel = (string) $publishRow['publish_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $checkpointedPointerMaps[$pageNumber] = ($checkpointedPointerMaps[$pageNumber] ?? 0) + 1;
            }

            $payloadReady = $channel === 'payload'
                && $checkpointedPointerMaps !== []
                && $publishRow['payload_publish_after_pointer_map'] === true
                && $publishRow['freeblock_receipt_published'] === true
                && $publishRow['tail_page_excluded_from_publish'] === true;

            if ($payloadReady) {
                $checkpointedPayloads[$pageNumber] = true;
            }

            $duplicatePointerMap = $channel === 'pointer-map' && ($checkpointedPointerMaps[$pageNumber] ?? 0) > 1;
            $nextCheckpointPage = $publishRows[$index + 1]['publish_page'] ?? null;
            $token = self::signature(array_merge(
                ['next247', $previousToken ?? 'initial', $publishRow['publish_token']],
                [$ordinal, $pageNumber, $nextCheckpointPage ?? 'eof', $channel, $payloadReady, $duplicatePointerMap],
                self::generationParts($checkpointedPointerMaps),
                self::sortedIntKeys($checkpointedPayloads),
            ));

            $rows[] = [
                'checkpoint_ordinal' => $ordinal,
                'publish_ordinal' => (int) $publishRow['publish_ordinal'],
                'checkpoint_page' => $pageNumber,
                'next_checkpoint_page' => $nextCheckpointPage,
                'checkpoint_channel' => $channel,
                'publish_token' => (string) $publishRow['publish_token'],
                'expected_publish_token' => $publishTokens[$index] ?? null,
                'publish_token_matches' => ($publishTokens[$index] ?? null) === (string) $publishRow['publish_token'],
                'previous_checkpoint_token' => $previousToken,
                'checkpoint_link_current' => $nextCheckpointPage === ($publishRows[$index + 1]['publish_page'] ?? null),
                'checkpointed_pointer_map_generations' => self::generationParts($checkpointedPointerMaps),
                'checkpointed_payload_pages' => self::sortedIntKeys($checkpointedPayloads),
                'payload_checkpoint_ready' => $payloadReady,
                'payload_checkpoint_after_pointer_map' => $channel !== 'payload' || $payloadReady,
                'duplicate_pointer_map_checkpoint' => $duplicatePointerMap,
                'duplicate_pointer_map_checkpointed' => !$duplicatePointerMap || ($checkpointedPointerMaps[$pageNumber] ?? 0) > 1,
                'freeblock_receipt_checkpointed' => $channel !== 'payload' || $publishRow['freeblock_receipt_published'] === true,
                'tail_page_excluded_from_checkpoint' => $publishRow['tail_page_excluded_from_publish'] === true,
                'checkpoint_state' => 'current-source-next247-checkpoint-admitted',
                'checkpoint_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function checkpointErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousOrdinal = 0;
        $previousToken = null;

        foreach ($rows as $row) {
            if ($row['checkpoint_state'] !== 'current-source-next247-checkpoint-admitted') {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} is not admitted";
            }
            if ((int) $row['checkpoint_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} skipped an ordinal";
            }
            if ((int) $row['publish_ordinal'] !== (int) $row['checkpoint_ordinal']) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} drifted from publish ordinal";
            }
            if ($row['publish_token_matches'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} publish token drifted";
            }
            if ($row['previous_checkpoint_token'] !== $previousToken) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} broke token chaining";
            }
            if ($row['checkpoint_link_current'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} has stale next-page link";
            }
            if ($row['payload_checkpoint_after_pointer_map'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} exposed payload before pointer-map checkpoint";
            }
            if ($row['duplicate_pointer_map_checkpointed'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} lost duplicate pointer-map generation";
            }
            if ($row['freeblock_receipt_checkpointed'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} lost freeblock receipt";
            }
            if ($row['tail_page_excluded_from_checkpoint'] !== true) {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} exposed a fenced tail page";
            }
            if ($row['checkpoint_token'] === '') {
                $errors[] = "checkpoint {$row['checkpoint_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['checkpoint_ordinal'];
            $previousToken = (string) $row['checkpoint_token'];
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
