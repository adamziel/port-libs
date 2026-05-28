<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan
{
    /**
     * @param list<array<string, mixed>> $admissionRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext248Plan $sealPlan,
        private readonly array $admissionRows,
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
        $rows = self::buildAdmissionRows($sealPlan);
        $errors = self::admissionErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next251 admission failed: ' . implode('; ', $errors));
        }

        return new self($sealPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function admissionRows(): array
    {
        return $this->admissionRows;
    }

    /**
     * @return list<string>
     */
    public function admissionErrors(): array
    {
        return self::admissionErrorsForRows($this->admissionRows);
    }

    /**
     * @return list<int>
     */
    public function admittedPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['admitted_page'], $this->admissionRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapAdmissionPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['admission_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function reusablePayloadAdmissionPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_cursor_can_advance'] === true && $row['admission_channel'] === 'payload');
    }

    /**
     * @return list<int>
     */
    public function heldPayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_cursor_can_advance'] === false && $row['admission_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function admissionTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['admission_token'], $this->admissionRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function admissionSummary(): array
    {
        $sealSummary = $this->sealPlan->sealSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next251-ready',
            'admission_row_count' => count($this->admissionRows),
            'admitted_pages' => $this->admittedPages(),
            'sealed_pages' => $sealSummary['sealed_pages'],
            'admitted_pages_match_seals' => $this->admittedPages() === $sealSummary['sealed_pages'],
            'pointer_map_admission_pages' => $this->pointerMapAdmissionPages(),
            'reusable_payload_admission_pages' => $this->reusablePayloadAdmissionPages(),
            'held_payload_pages' => $this->heldPayloadPages(),
            'all_seal_tokens_match' => !in_array(false, array_column($this->admissionRows, 'seal_token_matches'), true),
            'all_pointer_maps_visible_before_cursor_advance' => !in_array(false, array_column($this->admissionRows, 'pointer_maps_visible_before_cursor_advance'), true),
            'all_freeblock_receipts_required' => !in_array(false, array_column($this->admissionRows, 'freeblock_receipt_required'), true),
            'all_payload_cursor_advances_are_safe' => !in_array(false, array_column($this->admissionRows, 'payload_cursor_advance_safe'), true),
            'all_tail_pages_remain_fenced' => !in_array(false, array_column($this->admissionRows, 'tail_pages_remain_fenced'), true),
            'admission_errors' => $this->admissionErrors(),
            'admission_signature' => self::signature($this->admissionTokens()),
            'current_source_next251_token' => self::signature(array_merge(
                ['next251', $sealSummary['current_source_next248_token']],
                $this->admittedPages(),
                $this->admissionTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next248',
                'sqlite-current-source-next251',
            ],
            'dependency_closure' => 'no new support component needed; next251 reuses next248 seal rows and adds current-source cursor advancement admission for pointer-map/freeblock visibility',
            'non_overlap' => 'adds the next current-source cursor advancement barrier after next248 seal publication; does not repeat next248 sealing, next235 checkpoints, next232 handoff admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next251',
            'admission_summary' => $this->admissionSummary(),
            'admission_errors' => $this->admissionErrors(),
            'admission_rows' => $this->admissionRows,
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
        foreach ($this->admissionRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['admitted_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildAdmissionRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext248Plan $sealPlan): array
    {
        $sealRows = $sealPlan->sealRows();
        $sealTokens = $sealPlan->sealTokens();
        $rows = [];
        $previousAdmissionToken = null;
        $visiblePointerMaps = [];
        $publishedFreeblocks = [];

        foreach ($sealRows as $index => $sealRow) {
            $pageNumber = (int) $sealRow['sealed_page'];
            $channel = (string) $sealRow['seal_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $visiblePointerMaps[$pageNumber] = true;
            }
            if ($sealRow['publishes_freeblock_after_pointer_map'] === true) {
                $publishedFreeblocks[$pageNumber] = true;
            }

            $cursorCanAdvance = $channel === 'pointer-map' || (
                $sealRow['payload_reusable_after_seal'] === true
                && $sealRow['payload_reuse_waits_for_freeblock_publication'] === true
                && $sealRow['tail_pages_remain_fenced'] === true
                && $visiblePointerMaps !== []
                && $publishedFreeblocks !== []
            );
            $payloadCursorSafe = $channel !== 'payload' || $cursorCanAdvance;

            $token = self::signature(array_merge(
                ['next251', $previousAdmissionToken ?? 'initial', $sealRow['seal_token']],
                [$ordinal, $pageNumber, $channel, $cursorCanAdvance],
                self::sortedIntKeys($visiblePointerMaps),
                self::sortedIntKeys($publishedFreeblocks),
            ));

            $rows[] = [
                'admission_ordinal' => $ordinal,
                'seal_ordinal' => (int) $sealRow['seal_ordinal'],
                'admitted_page' => $pageNumber,
                'admission_channel' => $channel,
                'source_seal_token' => (string) $sealRow['seal_token'],
                'expected_seal_token' => $sealTokens[$index] ?? null,
                'seal_token_matches' => ($sealTokens[$index] ?? null) === (string) $sealRow['seal_token'],
                'previous_admission_token' => $previousAdmissionToken,
                'visible_pointer_map_pages' => self::sortedIntKeys($visiblePointerMaps),
                'published_freeblock_pages' => self::sortedIntKeys($publishedFreeblocks),
                'pointer_maps_visible_before_cursor_advance' => $visiblePointerMaps !== [],
                'freeblock_receipt_required' => $sealRow['publishes_freeblock_after_pointer_map'] === true,
                'source_cursor_can_advance' => $cursorCanAdvance,
                'payload_cursor_advance_safe' => $payloadCursorSafe,
                'tail_pages_remain_fenced' => $sealRow['tail_pages_remain_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'admission_state' => 'current-source-next251-cursor-advance-admitted',
                'admission_token' => $token,
            ];

            $previousAdmissionToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function admissionErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['admission_state'] !== 'current-source-next251-cursor-advance-admitted') {
                $errors[] = "admission {$row['admission_ordinal']} is not admitted";
            }
            if ((int) $row['admission_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "admission {$row['admission_ordinal']} skipped an ordinal";
            }
            if ((int) $row['seal_ordinal'] !== (int) $row['admission_ordinal']) {
                $errors[] = "admission {$row['admission_ordinal']} drifted from seal ordinal";
            }
            if ($row['seal_token_matches'] !== true) {
                $errors[] = "admission {$row['admission_ordinal']} seal token drifted";
            }
            if ($row['previous_admission_token'] !== $previousToken) {
                $errors[] = "admission {$row['admission_ordinal']} broke token chaining";
            }
            if ($row['pointer_maps_visible_before_cursor_advance'] !== true) {
                $errors[] = "admission {$row['admission_ordinal']} advanced before pointer-map visibility";
            }
            if ($row['freeblock_receipt_required'] !== true) {
                $errors[] = "admission {$row['admission_ordinal']} lacks a freeblock receipt";
            }
            if ($row['payload_cursor_advance_safe'] !== true) {
                $errors[] = "admission {$row['admission_ordinal']} advanced a payload cursor before seal safety";
            }
            if ($row['tail_pages_remain_fenced'] !== true) {
                $errors[] = "admission {$row['admission_ordinal']} exposed a fenced tail page";
            }
            if ($row['admission_token'] === '') {
                $errors[] = "admission {$row['admission_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['admission_ordinal'];
            $previousToken = (string) $row['admission_token'];
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
