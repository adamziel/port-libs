<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext248Plan
{
    /**
     * @param list<array<string, mixed>> $sealRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $checkpointPlan,
        private readonly array $sealRows,
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
        return self::fromCheckpointPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext235(
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

    public static function fromCheckpointPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $checkpointPlan): self
    {
        $rows = self::buildSealRows($checkpointPlan);
        $errors = self::sealErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next248 seal failed: ' . implode('; ', $errors));
        }

        return new self($checkpointPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sealRows(): array
    {
        return $this->sealRows;
    }

    /**
     * @return list<string>
     */
    public function sealErrors(): array
    {
        return self::sealErrorsForRows($this->sealRows);
    }

    /**
     * @return list<int>
     */
    public function sealedPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['sealed_page'], $this->sealRows));
    }

    /**
     * @return list<int>
     */
    public function freeblockPublicationPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publishes_freeblock_after_pointer_map'] === true);
    }

    /**
     * @return list<int>
     */
    public function finalPointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['seal_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function reusablePayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['payload_reusable_after_seal'] === true);
    }

    /**
     * @return list<string>
     */
    public function sealTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['seal_token'], $this->sealRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function sealSummary(): array
    {
        $checkpointSummary = $this->checkpointPlan->checkpointSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next248-ready',
            'seal_row_count' => count($this->sealRows),
            'sealed_pages' => $this->sealedPages(),
            'checkpoint_pages' => $checkpointSummary['checkpoint_pages'],
            'sealed_pages_match_checkpoints' => $this->sealedPages() === $checkpointSummary['checkpoint_pages'],
            'final_pointer_map_pages' => $this->finalPointerMapPages(),
            'freeblock_publication_pages' => $this->freeblockPublicationPages(),
            'reusable_payload_pages' => $this->reusablePayloadPages(),
            'all_checkpoint_tokens_match' => !in_array(false, array_column($this->sealRows, 'checkpoint_token_matches'), true),
            'all_pointer_maps_visible_before_freeblock_publication' => !in_array(false, array_column($this->sealRows, 'pointer_maps_visible_before_freeblock_publication'), true),
            'all_freeblock_publications_have_receipts' => !in_array(false, array_column($this->sealRows, 'publishes_freeblock_after_pointer_map'), true),
            'all_payload_reuse_waits_for_freeblock_publication' => !in_array(false, array_column($this->sealRows, 'payload_reuse_waits_for_freeblock_publication'), true),
            'all_tail_pages_remain_fenced' => !in_array(false, array_column($this->sealRows, 'tail_pages_remain_fenced'), true),
            'seal_errors' => $this->sealErrors(),
            'seal_signature' => self::signature($this->sealTokens()),
            'current_source_next248_token' => self::signature(array_merge(
                ['next248', $checkpointSummary['current_source_next235_token']],
                $this->sealedPages(),
                $this->sealTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next235',
                'sqlite-current-source-next248',
            ],
            'dependency_closure' => 'no new support component needed; next248 reuses next235 checkpoint rows and seals current-source freeblock publication after pointer-map visibility',
            'non_overlap' => 'adds the final current-source publication seal after next235 checkpoint replay; does not repeat next235 reusable-payload checkpoints, next232 handoff admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next248',
            'seal_summary' => $this->sealSummary(),
            'seal_errors' => $this->sealErrors(),
            'seal_rows' => $this->sealRows,
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
        foreach ($this->sealRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['sealed_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSealRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $checkpointPlan): array
    {
        $checkpointRows = $checkpointPlan->checkpointRows();
        $checkpointTokens = $checkpointPlan->checkpointTokens();
        $rows = [];
        $previousSealToken = null;
        $visiblePointerMaps = [];
        $freeblockPublished = false;

        foreach ($checkpointRows as $index => $checkpointRow) {
            $pageNumber = (int) $checkpointRow['checkpoint_page'];
            $channel = (string) $checkpointRow['checkpoint_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $visiblePointerMaps[$pageNumber] = true;
            }

            $publishesFreeblock = $checkpointRow['freeblock_receipt_visible_at_checkpoint'] === true
                && $visiblePointerMaps !== []
                && $checkpointRow['tail_pages_remain_fenced'] === true;
            if ($publishesFreeblock) {
                $freeblockPublished = true;
            }

            $payloadReusable = $checkpointRow['payload_reusable_after_checkpoint'] === true
                && $freeblockPublished
                && $checkpointRow['payload_reuse_waits_for_pointer_map'] === true;

            $token = self::signature(array_merge(
                ['next248', $previousSealToken ?? 'initial', $checkpointRow['checkpoint_token']],
                [$ordinal, $pageNumber, $channel, $publishesFreeblock, $payloadReusable],
                self::sortedIntKeys($visiblePointerMaps),
                [$freeblockPublished ? 'freeblock-published' : 'freeblock-pending'],
            ));

            $rows[] = [
                'seal_ordinal' => $ordinal,
                'checkpoint_ordinal' => (int) $checkpointRow['checkpoint_ordinal'],
                'sealed_page' => $pageNumber,
                'seal_channel' => $channel,
                'source_checkpoint_token' => (string) $checkpointRow['checkpoint_token'],
                'expected_checkpoint_token' => $checkpointTokens[$index] ?? null,
                'checkpoint_token_matches' => ($checkpointTokens[$index] ?? null) === (string) $checkpointRow['checkpoint_token'],
                'previous_seal_token' => $previousSealToken,
                'visible_pointer_map_pages' => self::sortedIntKeys($visiblePointerMaps),
                'pointer_maps_visible_before_freeblock_publication' => $visiblePointerMaps !== [],
                'publishes_freeblock_after_pointer_map' => $publishesFreeblock,
                'payload_reusable_after_seal' => $payloadReusable,
                'payload_reuse_waits_for_freeblock_publication' => $channel !== 'payload' || ($payloadReusable && $freeblockPublished),
                'tail_pages_remain_fenced' => $checkpointRow['tail_pages_remain_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'seal_state' => 'current-source-next248-freeblock-publication-sealed',
                'seal_token' => $token,
            ];

            $previousSealToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function sealErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['seal_state'] !== 'current-source-next248-freeblock-publication-sealed') {
                $errors[] = "seal {$row['seal_ordinal']} is not sealed";
            }
            if ((int) $row['seal_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "seal {$row['seal_ordinal']} skipped an ordinal";
            }
            if ((int) $row['checkpoint_ordinal'] !== (int) $row['seal_ordinal']) {
                $errors[] = "seal {$row['seal_ordinal']} drifted from checkpoint ordinal";
            }
            if ($row['checkpoint_token_matches'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} checkpoint token drifted";
            }
            if ($row['previous_seal_token'] !== $previousToken) {
                $errors[] = "seal {$row['seal_ordinal']} broke token chaining";
            }
            if ($row['pointer_maps_visible_before_freeblock_publication'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} published before pointer-map visibility";
            }
            if ($row['publishes_freeblock_after_pointer_map'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} lacks a pointer-map backed freeblock receipt";
            }
            if ($row['payload_reuse_waits_for_freeblock_publication'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} made payload reusable before freeblock publication";
            }
            if ($row['tail_pages_remain_fenced'] !== true) {
                $errors[] = "seal {$row['seal_ordinal']} exposed a fenced tail page";
            }
            if ($row['seal_token'] === '') {
                $errors[] = "seal {$row['seal_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['seal_ordinal'];
            $previousToken = (string) $row['seal_token'];
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
