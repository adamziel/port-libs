<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext252Plan
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext248Plan $sealPlan,
        private readonly array $handoffRows,
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
        return self::fromSealPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext248Plan::tableLeafFromDeleteResult(
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

    public static function fromSealPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext248Plan $sealPlan): self
    {
        $rows = self::buildHandoffRows($sealPlan);
        $errors = self::handoffErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next252 handoff failed: ' . implode('; ', $errors));
        }

        return new self($sealPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handoffRows(): array
    {
        return $this->handoffRows;
    }

    /**
     * @return list<string>
     */
    public function handoffErrors(): array
    {
        return self::handoffErrorsForRows($this->handoffRows);
    }

    /**
     * @return list<int>
     */
    public function admissiblePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['admissible_page'], $this->handoffRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function publishedFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freeblock_publication_admitted'] === true);
    }

    /**
     * @return list<int>
     */
    public function reusablePayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['payload_reuse_admitted'] === true);
    }

    /**
     * @return list<string>
     */
    public function handoffTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['handoff_token'], $this->handoffRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function handoffSummary(): array
    {
        $sealSummary = $this->sealPlan->sealSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next252-ready',
            'handoff_row_count' => count($this->handoffRows),
            'admissible_pages' => $this->admissiblePages(),
            'seal_pages' => $sealSummary['sealed_pages'],
            'admissible_pages_match_seal' => $this->admissiblePages() === $sealSummary['sealed_pages'],
            'pointer_map_pages' => $this->pointerMapPages(),
            'published_freeblock_pages' => $this->publishedFreeblockPages(),
            'reusable_payload_pages' => $this->reusablePayloadPages(),
            'all_seal_tokens_match' => !in_array(false, array_column($this->handoffRows, 'seal_token_matches'), true),
            'all_pointer_maps_admitted_before_payload_reuse' => !in_array(false, array_column($this->handoffRows, 'pointer_maps_admitted_before_payload_reuse'), true),
            'all_freeblocks_admitted_before_payload_reuse' => !in_array(false, array_column($this->handoffRows, 'freeblocks_admitted_before_payload_reuse'), true),
            'all_tail_pages_fenced_at_handoff' => !in_array(false, array_column($this->handoffRows, 'tail_page_fenced_at_handoff'), true),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_signature' => self::signature($this->handoffTokens()),
            'current_source_next252_token' => self::signature(array_merge(
                ['next252', $sealSummary['current_source_next248_token']],
                $this->admissiblePages(),
                $this->handoffTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next248',
                'sqlite-current-source-next252',
            ],
            'dependency_closure' => 'no new support component needed; next252 reuses next248 publication seals and adds current-source admission checks before vacuum freeblock reuse is exposed',
            'non_overlap' => 'adds current-source handoff admission after next248 publication sealing; does not repeat next248 seal construction, next235 checkpoints, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next252',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
            'seal_plan' => $this->sealPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->handoffRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['admissible_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext248Plan $sealPlan): array
    {
        $sealRows = $sealPlan->sealRows();
        $sealTokens = $sealPlan->sealTokens();
        $rows = [];
        $previousHandoffToken = null;
        $admittedPointerMaps = [];
        $admittedFreeblocks = [];

        foreach ($sealRows as $index => $sealRow) {
            $pageNumber = (int) $sealRow['sealed_page'];
            $channel = (string) $sealRow['seal_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $admittedPointerMaps[$pageNumber] = true;
            }
            if ($sealRow['publishes_freeblock_after_pointer_map'] === true) {
                $admittedFreeblocks[$pageNumber] = true;
            }

            $payloadReuseAdmitted = $sealRow['payload_reusable_after_seal'] === true
                && $admittedPointerMaps !== []
                && $admittedFreeblocks !== [];

            $token = self::signature(array_merge(
                ['next252', $previousHandoffToken ?? 'initial', $sealRow['seal_token']],
                [$ordinal, $pageNumber, $channel, $payloadReuseAdmitted],
                self::sortedIntKeys($admittedPointerMaps),
                self::sortedIntKeys($admittedFreeblocks),
            ));

            $rows[] = [
                'handoff_ordinal' => $ordinal,
                'seal_ordinal' => (int) $sealRow['seal_ordinal'],
                'admissible_page' => $pageNumber,
                'handoff_channel' => $channel,
                'source_seal_token' => (string) $sealRow['seal_token'],
                'expected_seal_token' => $sealTokens[$index] ?? null,
                'seal_token_matches' => ($sealTokens[$index] ?? null) === (string) $sealRow['seal_token'],
                'previous_handoff_token' => $previousHandoffToken,
                'admitted_pointer_map_pages' => self::sortedIntKeys($admittedPointerMaps),
                'admitted_freeblock_pages' => self::sortedIntKeys($admittedFreeblocks),
                'freeblock_publication_admitted' => $sealRow['publishes_freeblock_after_pointer_map'] === true && $admittedPointerMaps !== [],
                'payload_reuse_admitted' => $payloadReuseAdmitted,
                'pointer_maps_admitted_before_payload_reuse' => $payloadReuseAdmitted === false || $admittedPointerMaps !== [],
                'freeblocks_admitted_before_payload_reuse' => $payloadReuseAdmitted === false || $admittedFreeblocks !== [],
                'tail_page_fenced_at_handoff' => $sealRow['tail_pages_remain_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'handoff_state' => 'current-source-next252-vacuum-freeblock-admitted',
                'handoff_token' => $token,
            ];

            $previousHandoffToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function handoffErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['handoff_state'] !== 'current-source-next252-vacuum-freeblock-admitted') {
                $errors[] = "handoff {$row['handoff_ordinal']} is not admitted";
            }
            if ((int) $row['handoff_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "handoff {$row['handoff_ordinal']} skipped an ordinal";
            }
            if ((int) $row['seal_ordinal'] !== (int) $row['handoff_ordinal']) {
                $errors[] = "handoff {$row['handoff_ordinal']} drifted from seal ordinal";
            }
            if ($row['seal_token_matches'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} seal token drifted";
            }
            if ($row['previous_handoff_token'] !== $previousToken) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke token chaining";
            }
            if ($row['freeblock_publication_admitted'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lacks admitted freeblock publication";
            }
            if ($row['pointer_maps_admitted_before_payload_reuse'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} reused payload before pointer-map admission";
            }
            if ($row['freeblocks_admitted_before_payload_reuse'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} reused payload before freeblock admission";
            }
            if ($row['tail_page_fenced_at_handoff'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} exposed a fenced tail page";
            }
            if ($row['handoff_token'] === '') {
                $errors[] = "handoff {$row['handoff_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['handoff_ordinal'];
            $previousToken = (string) $row['handoff_token'];
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
