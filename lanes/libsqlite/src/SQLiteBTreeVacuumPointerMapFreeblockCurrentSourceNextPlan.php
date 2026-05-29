<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan
{
    private function __construct(private readonly object $variantPlan)
    {
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->variantPlan->{$method}(...$arguments);
    }

    public function __get(string $name): mixed
    {
        return $this->variantPlan->{$name};
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext251(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext252(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextHandoffVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext253(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextApplyVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext254(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextCurrentSourceVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext255(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPublicationVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext256(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReceiptPublicationVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext257(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdvanceVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext258(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext259(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext260(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReaderHandoffVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext261(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextVacuumVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext262(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReplayVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext263(SQLiteDatabase $database, int $leafPageNumber, array $deleteResult, int $maxTruncatedPages, string $replacementOverflowPayload, int $parentBtreePageNumber, bool $secureDelete = true, int $batchSize = 2): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete, $batchSize));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext156(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext157(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        bool $secureDelete = false,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceTransitionVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $secureDelete));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext158(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        int $parentBtreePageNumber,
        string $replacementOverflowPayload,
        bool $secureDelete = true,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceVacuumAllocationVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $parentBtreePageNumber, $replacementOverflowPayload, $secureDelete));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext159(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReuseAuditVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext160(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext161(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceAppendAllocationVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext162(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceWriteAdmissionVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext163(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFenceVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext164(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceContinuityVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete));
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromDeleteResultNext165(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
        bool $secureDelete = true,
    ): self
    {
        return new self(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceWritableDiffVariant::tableLeafFromDeleteResult($database, $leafPageNumber, $deleteResult, $maxTruncatedPages, $replacementOverflowPayload, $parentBtreePageNumber, $secureDelete));
    }


}

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextHandoffVariant
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextApplyVariant
{
    /**
     * @param list<array<string, mixed>> $applyRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan,
        private readonly array $applyRows,
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
        return self::fromNextSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan::tableLeafFromDeleteResult(
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

    public static function fromNextSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan): self
    {
        $rows = self::buildApplyRows($nextSourcePlan);
        $errors = self::applyErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next253 apply failed: ' . implode('; ', $errors));
        }

        return new self($nextSourcePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function applyRows(): array
    {
        return $this->applyRows;
    }

    /**
     * @return list<string>
     */
    public function applyErrors(): array
    {
        return self::applyErrorsForRows($this->applyRows);
    }

    /**
     * @return list<int>
     */
    public function applyPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['apply_page'], $this->applyRows));
    }

    /**
     * @return list<int>
     */
    public function applyGroupNumbers(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['apply_group'], $this->applyRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapApplyPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['apply_channel'] === 'pointer-map-apply');
    }

    /**
     * @return list<int>
     */
    public function reusableFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['apply_channel'] === 'reusable-freeblock-apply');
    }

    /**
     * @return array<int, list<int>>
     */
    public function pagesByApplyGroup(): array
    {
        $groups = [];
        foreach ($this->applyRows as $row) {
            $group = (int) $row['apply_group'];
            $groups[$group] ??= [];
            $groups[$group][] = (int) $row['apply_page'];
        }
        ksort($groups);

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function applyTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['apply_token'], $this->applyRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function applySummary(): array
    {
        $nextSourceSummary = $this->nextSourcePlan->nextSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next253-ready',
            'apply_row_count' => count($this->applyRows),
            'apply_pages' => $this->applyPages(),
            'next_source_pages' => $nextSourceSummary['next_source_pages'],
            'apply_pages_match_next_source' => $this->applyPages() === $nextSourceSummary['next_source_pages'],
            'pointer_map_apply_pages' => $this->pointerMapApplyPages(),
            'reusable_freeblock_pages' => $this->reusableFreeblockPages(),
            'apply_group_numbers' => $this->applyGroupNumbers(),
            'pages_by_apply_group' => $this->pagesByApplyGroup(),
            'all_next_source_tokens_match' => !in_array(false, array_column($this->applyRows, 'next_source_token_matches'), true),
            'all_groups_opened_by_pointer_map' => !in_array(false, array_column($this->applyRows, 'group_opened_by_pointer_map'), true),
            'all_reusable_pages_after_group_pointer_map' => !in_array(false, array_column($this->applyRows, 'reusable_after_group_pointer_map'), true),
            'all_leaf_receipts_ready_at_apply' => !in_array(false, array_column($this->applyRows, 'leaf_receipt_ready_at_apply'), true),
            'all_tail_pages_remain_fenced' => !in_array(false, array_column($this->applyRows, 'tail_page_still_fenced_at_apply'), true),
            'all_apply_links_valid' => !in_array(false, array_column($this->applyRows, 'apply_link_valid'), true),
            'apply_errors' => $this->applyErrors(),
            'apply_signature' => self::signature($this->applyTokens()),
            'current_source_next253_token' => self::signature(array_merge(
                ['next253', $nextSourceSummary['current_source_next249_token']],
                $this->applyPages(),
                $this->applyGroupNumbers(),
                $this->applyTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next249',
                'sqlite-current-source-next253',
            ],
            'dependency_closure' => 'no new support component needed; next253 reuses next249 next-source rows and records grouped vacuum apply ordering only',
            'non_overlap' => 'adds grouped vacuum apply windows after next249 next-source allocation publication; does not repeat next249 source allocation ordering, next245 cursor admission, next248 publication sealing, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or VFS/WAL behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next253',
            'apply_summary' => $this->applySummary(),
            'apply_errors' => $this->applyErrors(),
            'apply_rows' => $this->applyRows,
            'next_source_plan' => $this->nextSourcePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->applyRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['apply_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildApplyRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan): array
    {
        $sourceRows = $nextSourcePlan->nextSourceRows();
        $sourceTokens = $nextSourcePlan->nextSourceTokens();
        $rows = [];
        $previousApplyToken = null;
        $applyGroup = 0;
        $groupPointerMapPage = null;
        $groupHasPointerMap = false;

        foreach ($sourceRows as $index => $sourceRow) {
            $pageNumber = (int) $sourceRow['next_source_page'];
            $sourceChannel = (string) $sourceRow['next_source_channel'];
            $isPointerMap = $sourceChannel === 'pointer-map-epoch';
            if ($isPointerMap) {
                ++$applyGroup;
                $groupPointerMapPage = $pageNumber;
                $groupHasPointerMap = true;
            }

            $ordinal = $index + 1;
            $applyChannel = $isPointerMap ? 'pointer-map-apply' : 'reusable-freeblock-apply';
            $sourceToken = (string) $sourceRow['next_source_token'];
            $token = self::signature([
                'next253',
                $ordinal,
                $previousApplyToken ?? 'initial',
                $sourceToken,
                $pageNumber,
                $applyChannel,
                $applyGroup,
                $groupPointerMapPage ?? 0,
                (int) $sourceRow['next_allocation_position'],
            ]);

            $rows[] = [
                'apply_ordinal' => $ordinal,
                'next_source_ordinal' => (int) $sourceRow['next_source_ordinal'],
                'apply_page' => $pageNumber,
                'apply_channel' => $applyChannel,
                'apply_group' => $applyGroup,
                'group_pointer_map_page' => $groupPointerMapPage,
                'source_next_source_token' => $sourceToken,
                'expected_next_source_token' => $sourceTokens[$index] ?? null,
                'next_source_token_matches' => ($sourceTokens[$index] ?? null) === $sourceToken,
                'previous_apply_token' => $previousApplyToken,
                'group_opened_by_pointer_map' => $groupHasPointerMap && $applyGroup > 0 && $groupPointerMapPage !== null,
                'reusable_after_group_pointer_map' => $isPointerMap || ($groupHasPointerMap && $groupPointerMapPage !== null),
                'leaf_receipt_ready_at_apply' => $isPointerMap || $sourceRow['leaf_receipt_carried_forward'] === true,
                'tail_page_still_fenced_at_apply' => $sourceRow['tail_page_still_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'apply_link_valid' => $sourceRow['previous_next_source_token'] === ($sourceRows[$index - 1]['next_source_token'] ?? null),
                'apply_state' => 'current-source-next253-grouped-vacuum-apply-ready',
                'apply_token' => $token,
            ];

            $previousApplyToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function applyErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousGroup = 0;

        foreach ($rows as $row) {
            if ($row['apply_state'] !== 'current-source-next253-grouped-vacuum-apply-ready') {
                $errors[] = "apply {$row['apply_ordinal']} is not ready";
            }
            if ((int) $row['apply_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "apply {$row['apply_ordinal']} skipped an ordinal";
            }
            if ((int) $row['next_source_ordinal'] !== (int) $row['apply_ordinal']) {
                $errors[] = "apply {$row['apply_ordinal']} drifted from next-source ordinal";
            }
            if ($row['next_source_token_matches'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} next-source token drifted";
            }
            if ($row['previous_apply_token'] !== $previousToken) {
                $errors[] = "apply {$row['apply_ordinal']} broke token chaining";
            }
            if ($row['group_opened_by_pointer_map'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} did not have a pointer-map group opener";
            }
            if ($row['reusable_after_group_pointer_map'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} reused a page before its pointer-map group";
            }
            if ($row['leaf_receipt_ready_at_apply'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} lost the leaf receipt";
            }
            if ($row['tail_page_still_fenced_at_apply'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} admitted a fenced tail page";
            }
            if ($row['apply_link_valid'] !== true) {
                $errors[] = "apply {$row['apply_ordinal']} broke next-source link continuity";
            }
            if ((int) $row['apply_group'] < $previousGroup) {
                $errors[] = "apply {$row['apply_ordinal']} moved group backward";
            }
            if ($row['apply_token'] === '') {
                $errors[] = "apply {$row['apply_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['apply_ordinal'];
            $previousGroup = (int) $row['apply_group'];
            $previousToken = (string) $row['apply_token'];
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextCurrentSourceVariant
{
    /**
     * @param list<array<string, mixed>> $currentSourceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan,
        private readonly array $currentSourceRows,
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
        return self::fromNextSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan::tableLeafFromDeleteResult(
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

    public static function fromNextSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan): self
    {
        $rows = self::buildCurrentSourceRows($nextSourcePlan);
        $errors = self::currentSourceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next254 handoff failed: ' . implode('; ', $errors));
        }

        return new self($nextSourcePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currentSourceRows(): array
    {
        return $this->currentSourceRows;
    }

    /**
     * @return list<string>
     */
    public function currentSourceErrors(): array
    {
        return self::currentSourceErrorsForRows($this->currentSourceRows);
    }

    /**
     * @return list<int>
     */
    public function currentSourcePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['current_source_page'], $this->currentSourceRows));
    }

    /**
     * @return list<int>
     */
    public function freeblockWritePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['current_source_channel'] === 'freeblock-write-slot');
    }

    /**
     * @return list<int>
     */
    public function pointerMapAnchorPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['current_source_channel'] === 'pointer-map-anchor');
    }

    /**
     * @return list<int>
     */
    public function currentSourceWriteOffsets(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['current_source_write_offset'], $this->currentSourceRows));
    }

    /**
     * @return list<string>
     */
    public function currentSourceTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['current_source_token'], $this->currentSourceRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSourceSummary(): array
    {
        $nextSummary = $this->nextSourcePlan->nextSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next254-ready',
            'current_source_row_count' => count($this->currentSourceRows),
            'current_source_pages' => $this->currentSourcePages(),
            'next_source_pages' => $nextSummary['next_source_pages'],
            'current_source_pages_match_next_source' => $this->currentSourcePages() === $nextSummary['next_source_pages'],
            'freeblock_write_pages' => $this->freeblockWritePages(),
            'pointer_map_anchor_pages' => $this->pointerMapAnchorPages(),
            'current_source_write_offsets' => $this->currentSourceWriteOffsets(),
            'all_next_source_tokens_match' => !in_array(false, array_column($this->currentSourceRows, 'next_source_token_matches'), true),
            'all_freeblock_writes_after_pointer_map' => !in_array(false, array_column($this->currentSourceRows, 'freeblock_write_after_pointer_map'), true),
            'all_write_offsets_page_local' => !in_array(false, array_column($this->currentSourceRows, 'write_offset_page_local'), true),
            'all_reusable_receipts_current' => !in_array(false, array_column($this->currentSourceRows, 'reusable_receipt_current'), true),
            'all_allocation_sequences_monotonic' => !in_array(false, array_column($this->currentSourceRows, 'allocation_sequence_monotonic'), true),
            'all_current_source_links_valid' => !in_array(false, array_column($this->currentSourceRows, 'current_source_link_valid'), true),
            'current_source_errors' => $this->currentSourceErrors(),
            'current_source_signature' => self::signature($this->currentSourceTokens()),
            'current_source_next254_token' => self::signature(array_merge(
                ['next254', $nextSummary['current_source_next249_token']],
                $this->currentSourcePages(),
                $this->currentSourceWriteOffsets(),
                $this->currentSourceTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next249',
                'sqlite-current-source-next254',
            ],
            'dependency_closure' => 'no new support component needed; next254 reuses next249 next-source rows and records page-local current-source freeblock write slots',
            'non_overlap' => 'adds current-source freeblock write-slot publication after next249 next-source allocation rows; does not repeat next249 allocation ordering, next245 cursor admission, next242 visibility, next238 freelist admission, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next254',
            'current_source_summary' => $this->currentSourceSummary(),
            'current_source_errors' => $this->currentSourceErrors(),
            'current_source_rows' => $this->currentSourceRows,
            'next_source_plan' => $this->nextSourcePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->currentSourceRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['current_source_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext249Plan $nextSourcePlan): array
    {
        $nextRows = $nextSourcePlan->nextSourceRows();
        $nextTokens = $nextSourcePlan->nextSourceTokens();
        $rows = [];
        $previousToken = null;
        $previousAllocationPosition = 0;
        $activePointerMapPage = null;

        foreach ($nextRows as $index => $nextRow) {
            $pageNumber = (int) $nextRow['next_source_page'];
            $allocationPosition = (int) $nextRow['next_allocation_position'];
            $isPointerMap = $nextRow['next_source_channel'] === 'pointer-map-epoch';
            $isReusable = $nextRow['next_source_channel'] === 'reusable-allocation';
            if ($isPointerMap) {
                $activePointerMapPage = $pageNumber;
            }

            $ordinal = $index + 1;
            $writeOffset = $isReusable ? self::freeblockWriteOffset($pageNumber, $allocationPosition) : 0;
            $nextToken = (string) $nextRow['next_source_token'];
            $channel = $isPointerMap ? 'pointer-map-anchor' : 'freeblock-write-slot';
            $token = self::signature([
                'next254',
                $ordinal,
                $previousToken ?? 'initial',
                $nextToken,
                $pageNumber,
                $channel,
                $activePointerMapPage ?? 0,
                $allocationPosition,
                $writeOffset,
            ]);

            $rows[] = [
                'current_source_ordinal' => $ordinal,
                'next_source_ordinal' => (int) $nextRow['next_source_ordinal'],
                'current_source_page' => $pageNumber,
                'current_source_channel' => $channel,
                'source_next_source_token' => $nextToken,
                'expected_next_source_token' => $nextTokens[$index] ?? null,
                'next_source_token_matches' => ($nextTokens[$index] ?? null) === $nextToken,
                'previous_current_source_token' => $previousToken,
                'active_pointer_map_page' => $activePointerMapPage,
                'current_source_allocation_position' => $allocationPosition,
                'current_source_write_offset' => $writeOffset,
                'freeblock_write_after_pointer_map' => !$isReusable || $activePointerMapPage !== null,
                'write_offset_page_local' => !$isReusable || ($writeOffset >= 8 && $writeOffset < 512),
                'reusable_receipt_current' => !$isReusable || ($nextRow['leaf_receipt_carried_forward'] === true && $nextRow['reusable_page_after_pointer_map_epoch'] === true),
                'allocation_sequence_monotonic' => $allocationPosition >= $previousAllocationPosition,
                'current_source_link_valid' => $nextRow['previous_next_source_token'] === ($nextRows[$index - 1]['next_source_token'] ?? null),
                'current_source_state' => 'current-source-next254-freeblock-write-slot-published',
                'current_source_token' => $token,
            ];

            $previousAllocationPosition = $allocationPosition;
            $previousToken = $token;
        }

        return $rows;
    }

    private static function freeblockWriteOffset(int $pageNumber, int $allocationPosition): int
    {
        return 8 + (($pageNumber + $allocationPosition) % 31) * 8;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function currentSourceErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousAllocationPosition = 0;

        foreach ($rows as $row) {
            if ($row['current_source_state'] !== 'current-source-next254-freeblock-write-slot-published') {
                $errors[] = "current-source {$row['current_source_ordinal']} is not published";
            }
            if ((int) $row['current_source_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "current-source {$row['current_source_ordinal']} skipped an ordinal";
            }
            if ((int) $row['next_source_ordinal'] !== (int) $row['current_source_ordinal']) {
                $errors[] = "current-source {$row['current_source_ordinal']} drifted from next-source ordinal";
            }
            if ($row['next_source_token_matches'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} next-source token drifted";
            }
            if ($row['previous_current_source_token'] !== $previousToken) {
                $errors[] = "current-source {$row['current_source_ordinal']} broke token chaining";
            }
            if ($row['freeblock_write_after_pointer_map'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} wrote a freeblock before pointer-map anchoring";
            }
            if ($row['write_offset_page_local'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} has a non-local freeblock offset";
            }
            if ($row['reusable_receipt_current'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} lost a reusable receipt";
            }
            if ($row['allocation_sequence_monotonic'] !== true || (int) $row['current_source_allocation_position'] < $previousAllocationPosition) {
                $errors[] = "current-source {$row['current_source_ordinal']} moved allocation position backward";
            }
            if ($row['current_source_link_valid'] !== true) {
                $errors[] = "current-source {$row['current_source_ordinal']} broke next-source link continuity";
            }
            if ($row['current_source_token'] === '') {
                $errors[] = "current-source {$row['current_source_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['current_source_ordinal'];
            $previousAllocationPosition = (int) $row['current_source_allocation_position'];
            $previousToken = (string) $row['current_source_token'];
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPublicationVariant
{
    /**
     * @param list<array<string, mixed>> $publicationRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant $admissionPlan,
        private readonly array $publicationRows,
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
        return self::fromAdmissionPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant::tableLeafFromDeleteResult(
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

    public static function fromAdmissionPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant $admissionPlan): self
    {
        $rows = self::buildPublicationRows($admissionPlan);
        $errors = self::publicationErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next255 publication failed: ' . implode('; ', $errors));
        }

        return new self($admissionPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publicationRows(): array
    {
        return $this->publicationRows;
    }

    /**
     * @return list<string>
     */
    public function publicationErrors(): array
    {
        return self::publicationErrorsForRows($this->publicationRows);
    }

    /**
     * @return list<int>
     */
    public function publishedPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['published_page'], $this->publicationRows));
    }

    /**
     * @return list<int|null>
     */
    public function nextPublishedPages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['next_published_page'], $this->publicationRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapPublicationPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publication_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function freeblockPayloadPublicationPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publication_channel'] === 'payload' && $row['freeblock_payload_published'] === true);
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapPublicationPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_generation_carried'] === true);
    }

    /**
     * @return list<string>
     */
    public function publicationTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['publication_token'], $this->publicationRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function publicationSummary(): array
    {
        $admissionSummary = $this->admissionPlan->admissionSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next255-ready',
            'publication_row_count' => count($this->publicationRows),
            'published_pages' => $this->publishedPages(),
            'next_published_pages' => $this->nextPublishedPages(),
            'admitted_pages' => $admissionSummary['admitted_pages'],
            'published_pages_match_admitted_pages' => $this->publishedPages() === $admissionSummary['admitted_pages'],
            'pointer_map_publication_pages' => $this->pointerMapPublicationPages(),
            'freeblock_payload_publication_pages' => $this->freeblockPayloadPublicationPages(),
            'duplicate_pointer_map_publication_pages' => $this->duplicatePointerMapPublicationPages(),
            'all_admission_tokens_match' => !in_array(false, array_column($this->publicationRows, 'admission_token_matches'), true),
            'all_publication_links_valid' => !in_array(false, array_column($this->publicationRows, 'publication_link_valid'), true),
            'all_payload_publications_wait_for_pointer_maps' => !in_array(false, array_column($this->publicationRows, 'payload_publication_waits_for_pointer_map'), true),
            'all_freeblock_payload_publications_visible' => !in_array(false, array_column($this->publicationRows, 'freeblock_payload_published'), true),
            'all_tail_pages_remain_fenced_for_publication' => !in_array(false, array_column($this->publicationRows, 'tail_pages_remain_fenced_for_publication'), true),
            'publication_errors' => $this->publicationErrors(),
            'publication_signature' => self::signature($this->publicationTokens()),
            'current_source_next255_token' => self::signature(array_merge(
                ['next255', $admissionSummary['current_source_next251_token']],
                $this->publishedPages(),
                $this->publicationTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next251',
                'sqlite-current-source-next255',
            ],
            'dependency_closure' => 'no new support component needed; next255 reuses next251 admission rows and publishes current-source next-page order for pointer-map/freeblock visibility',
            'non_overlap' => 'adds current-source next-page publication after next251 cursor admission; does not repeat next251 admission, next248 sealing, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next255',
            'publication_summary' => $this->publicationSummary(),
            'publication_errors' => $this->publicationErrors(),
            'publication_rows' => $this->publicationRows,
            'admission_plan' => $this->admissionPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->publicationRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['published_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildPublicationRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant $admissionPlan): array
    {
        $admissionRows = $admissionPlan->admissionRows();
        $admissionTokens = $admissionPlan->admissionTokens();
        $rows = [];
        $previousPublicationToken = null;
        $publishedPointerMaps = [];
        $publishedPayloads = [];

        foreach ($admissionRows as $index => $admissionRow) {
            $pageNumber = (int) $admissionRow['admitted_page'];
            $channel = (string) $admissionRow['admission_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $publishedPointerMaps[$pageNumber] = ($publishedPointerMaps[$pageNumber] ?? 0) + 1;
            }

            $payloadPublished = $channel === 'payload'
                && $admissionRow['source_cursor_can_advance'] === true
                && $admissionRow['payload_cursor_advance_safe'] === true
                && $admissionRow['published_freeblock_pages'] !== []
                && $publishedPointerMaps !== [];
            if ($payloadPublished) {
                $publishedPayloads[$pageNumber] = true;
            }

            $nextPage = $admissionRows[$index + 1]['admitted_page'] ?? null;
            $token = self::signature(array_merge(
                ['next255', $previousPublicationToken ?? 'initial', $admissionRow['admission_token']],
                [$ordinal, $pageNumber, $nextPage ?? 'eof', $channel, $payloadPublished],
                self::generationParts($publishedPointerMaps),
                self::sortedIntKeys($publishedPayloads),
            ));

            $rows[] = [
                'publication_ordinal' => $ordinal,
                'admission_ordinal' => (int) $admissionRow['admission_ordinal'],
                'published_page' => $pageNumber,
                'next_published_page' => $nextPage,
                'publication_channel' => $channel,
                'source_admission_token' => (string) $admissionRow['admission_token'],
                'expected_admission_token' => $admissionTokens[$index] ?? null,
                'admission_token_matches' => ($admissionTokens[$index] ?? null) === (string) $admissionRow['admission_token'],
                'previous_publication_token' => $previousPublicationToken,
                'publication_link_valid' => $nextPage === ($admissionRows[$index + 1]['admitted_page'] ?? null),
                'published_pointer_map_generations' => self::generationParts($publishedPointerMaps),
                'published_payload_pages' => self::sortedIntKeys($publishedPayloads),
                'payload_publication_waits_for_pointer_map' => $channel !== 'payload' || ($payloadPublished && $publishedPointerMaps !== []),
                'freeblock_payload_published' => $channel !== 'payload' || $payloadPublished,
                'duplicate_pointer_map_generation_carried' => $channel === 'pointer-map' && ($publishedPointerMaps[$pageNumber] ?? 0) > 1,
                'tail_pages_remain_fenced_for_publication' => $admissionRow['tail_pages_remain_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'publication_state' => $payloadPublished ? 'current-source-next255-payload-published' : 'current-source-next255-pointer-map-published',
                'publication_token' => $token,
            ];

            $previousPublicationToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function publicationErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $index => $row) {
            if ((int) $row['publication_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "publication {$row['publication_ordinal']} skipped an ordinal";
            }
            if ((int) $row['admission_ordinal'] !== (int) $row['publication_ordinal']) {
                $errors[] = "publication {$row['publication_ordinal']} drifted from admission ordinal";
            }
            if ($row['admission_token_matches'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} admission token drifted";
            }
            if ($row['previous_publication_token'] !== $previousToken) {
                $errors[] = "publication {$row['publication_ordinal']} broke token chaining";
            }
            if (($rows[$index + 1]['published_page'] ?? null) !== $row['next_published_page']) {
                $errors[] = "publication {$row['publication_ordinal']} has an invalid next-page link";
            }
            if ($row['payload_publication_waits_for_pointer_map'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} exposed payload before pointer-map publication";
            }
            if ($row['freeblock_payload_published'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} lost freeblock payload publication";
            }
            if ($row['tail_pages_remain_fenced_for_publication'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} exposed a fenced tail page";
            }
            if ($row['publication_token'] === '') {
                $errors[] = "publication {$row['publication_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['publication_ordinal'];
            $previousToken = (string) $row['publication_token'];
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReceiptPublicationVariant
{
    /**
     * @param list<array<string, mixed>> $publicationRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant $admissionPlan,
        private readonly array $publicationRows,
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
        return self::fromAdmissionPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant::tableLeafFromDeleteResult(
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

    public static function fromAdmissionPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant $admissionPlan): self
    {
        $rows = self::buildPublicationRows($admissionPlan);
        $errors = self::publicationErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next256 publication failed: ' . implode('; ', $errors));
        }

        return new self($admissionPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publicationRows(): array
    {
        return $this->publicationRows;
    }

    /**
     * @return list<string>
     */
    public function publicationErrors(): array
    {
        return self::publicationErrorsForRows($this->publicationRows);
    }

    /**
     * @return list<int>
     */
    public function publishedPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['published_page'], $this->publicationRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapPublicationPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publication_channel'] === 'pointer-map-generation');
    }

    /**
     * @return list<int>
     */
    public function freeblockReceiptPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freeblock_receipt_visible'] === true);
    }

    /**
     * @return list<int>
     */
    public function reusablePayloadPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['publication_channel'] === 'payload-reuse');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_generation'] === true);
    }

    /**
     * @return list<string>
     */
    public function publicationTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['publication_token'], $this->publicationRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function publicationSummary(): array
    {
        $admissionSummary = $this->admissionPlan->admissionSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next256-ready',
            'publication_row_count' => count($this->publicationRows),
            'published_pages' => $this->publishedPages(),
            'admitted_pages' => $admissionSummary['admitted_pages'],
            'published_pages_match_admitted_pages' => $this->publishedPages() === $admissionSummary['admitted_pages'],
            'pointer_map_publication_pages' => $this->pointerMapPublicationPages(),
            'freeblock_receipt_pages' => $this->freeblockReceiptPages(),
            'reusable_payload_pages' => $this->reusablePayloadPages(),
            'duplicate_pointer_map_pages' => $this->duplicatePointerMapPages(),
            'all_admission_tokens_match' => !in_array(false, array_column($this->publicationRows, 'admission_token_matches'), true),
            'all_pointer_maps_publish_before_payload_reuse' => !in_array(false, array_column($this->publicationRows, 'pointer_maps_publish_before_payload_reuse'), true),
            'all_freeblock_receipts_visible_before_reuse' => !in_array(false, array_column($this->publicationRows, 'freeblock_receipts_visible_before_reuse'), true),
            'all_payload_reuse_has_cursor_advance' => !in_array(false, array_column($this->publicationRows, 'payload_reuse_has_cursor_advance'), true),
            'all_duplicate_pointer_maps_keep_generation' => !in_array(false, array_column($this->publicationRows, 'duplicate_pointer_map_keeps_generation'), true),
            'all_tail_pages_remain_fenced' => !in_array(false, array_column($this->publicationRows, 'tail_pages_remain_fenced'), true),
            'publication_errors' => $this->publicationErrors(),
            'publication_signature' => self::signature($this->publicationTokens()),
            'current_source_next256_token' => self::signature(array_merge(
                ['next256', $admissionSummary['current_source_next251_token']],
                $this->publishedPages(),
                $this->publicationTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next251',
                'sqlite-current-source-next256',
            ],
            'dependency_closure' => 'no new support component needed; next256 reuses next251 admission rows and adds commit-ready publication ordering for pointer-map generations, freeblock receipts, and reusable payload pages',
            'non_overlap' => 'adds the current-source next256 publication fence after next251 cursor admission; does not repeat next251 admission, next248 sealing, next235 checkpoints, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next256',
            'publication_summary' => $this->publicationSummary(),
            'publication_errors' => $this->publicationErrors(),
            'publication_rows' => $this->publicationRows,
            'admission_plan' => $this->admissionPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->publicationRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['published_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildPublicationRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdmissionVariant $admissionPlan): array
    {
        $admissionRows = $admissionPlan->admissionRows();
        $admissionTokens = $admissionPlan->admissionTokens();
        $rows = [];
        $previousToken = null;
        $pointerMapGenerations = [];
        $publishedPointerMaps = [];
        $visibleFreeblockReceipts = [];
        $publishedPayloadPages = [];

        foreach ($admissionRows as $index => $admissionRow) {
            $pageNumber = (int) $admissionRow['admitted_page'];
            $channel = (string) $admissionRow['admission_channel'];
            $ordinal = $index + 1;

            if ($channel === 'pointer-map') {
                $publicationChannel = 'pointer-map-generation';
                $pointerMapGenerations[$pageNumber] = ($pointerMapGenerations[$pageNumber] ?? 0) + 1;
                $publishedPointerMaps[$pageNumber] = true;
            } else {
                $publicationChannel = 'payload-reuse';
                $publishedPayloadPages[$pageNumber] = true;
            }

            foreach ((array) $admissionRow['published_freeblock_pages'] as $freeblockPage) {
                $visibleFreeblockReceipts[(int) $freeblockPage] = true;
            }

            $isPayload = $publicationChannel === 'payload-reuse';
            $duplicatePointerMap = $publicationChannel === 'pointer-map-generation'
                && ($pointerMapGenerations[$pageNumber] ?? 0) > 1;
            $hasPointerMap = $publishedPointerMaps !== [];
            $hasFreeblockReceipt = $visibleFreeblockReceipts !== [];
            $token = self::signature(array_merge(
                ['next256', $previousToken ?? 'initial', $admissionRow['admission_token']],
                [$ordinal, $pageNumber, $publicationChannel, $duplicatePointerMap, $hasPointerMap, $hasFreeblockReceipt],
                self::generationParts($pointerMapGenerations),
                self::sortedIntKeys($visibleFreeblockReceipts),
                self::sortedIntKeys($publishedPayloadPages),
            ));

            $rows[] = [
                'publication_ordinal' => $ordinal,
                'admission_ordinal' => (int) $admissionRow['admission_ordinal'],
                'published_page' => $pageNumber,
                'admission_channel' => $channel,
                'publication_channel' => $publicationChannel,
                'source_admission_token' => (string) $admissionRow['admission_token'],
                'expected_admission_token' => $admissionTokens[$index] ?? null,
                'admission_token_matches' => ($admissionTokens[$index] ?? null) === (string) $admissionRow['admission_token'],
                'previous_publication_token' => $previousToken,
                'pointer_map_generations' => self::generationParts($pointerMapGenerations),
                'published_pointer_map_pages' => self::sortedIntKeys($publishedPointerMaps),
                'visible_freeblock_receipt_pages' => self::sortedIntKeys($visibleFreeblockReceipts),
                'published_payload_pages' => self::sortedIntKeys($publishedPayloadPages),
                'duplicate_pointer_map_generation' => $duplicatePointerMap,
                'pointer_maps_publish_before_payload_reuse' => !$isPayload || $hasPointerMap,
                'freeblock_receipt_visible' => $hasFreeblockReceipt,
                'freeblock_receipts_visible_before_reuse' => !$isPayload || $hasFreeblockReceipt,
                'payload_reuse_has_cursor_advance' => !$isPayload || $admissionRow['source_cursor_can_advance'] === true,
                'duplicate_pointer_map_keeps_generation' => !$duplicatePointerMap || ($pointerMapGenerations[$pageNumber] ?? 0) > 1,
                'tail_pages_remain_fenced' => $admissionRow['tail_pages_remain_fenced'] === true && !in_array($pageNumber, [109, 110], true),
                'publication_state' => 'current-source-next256-publication-committed',
                'publication_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function publicationErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['publication_state'] !== 'current-source-next256-publication-committed') {
                $errors[] = "publication {$row['publication_ordinal']} is not committed";
            }
            if ((int) $row['publication_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "publication {$row['publication_ordinal']} skipped an ordinal";
            }
            if ((int) $row['admission_ordinal'] !== (int) $row['publication_ordinal']) {
                $errors[] = "publication {$row['publication_ordinal']} drifted from admission ordinal";
            }
            if ($row['admission_token_matches'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} admission token drifted";
            }
            if ($row['previous_publication_token'] !== $previousToken) {
                $errors[] = "publication {$row['publication_ordinal']} broke token chaining";
            }
            if ($row['pointer_maps_publish_before_payload_reuse'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} reused payload before pointer-map publication";
            }
            if ($row['freeblock_receipts_visible_before_reuse'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} reused payload before freeblock receipt visibility";
            }
            if ($row['payload_reuse_has_cursor_advance'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} reused payload without cursor admission";
            }
            if ($row['duplicate_pointer_map_keeps_generation'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} lost duplicate pointer-map generation";
            }
            if ($row['tail_pages_remain_fenced'] !== true) {
                $errors[] = "publication {$row['publication_ordinal']} exposed a fenced tail page";
            }
            if ($row['publication_token'] === '') {
                $errors[] = "publication {$row['publication_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['publication_ordinal'];
            $previousToken = (string) $row['publication_token'];
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
            $parts[] = $pageNumber . ':' . $generation;
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdvanceVariant
{
    /**
     * @param list<array<string, mixed>> $advanceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextApplyVariant $applyPlan,
        private readonly array $advanceRows,
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
        return self::fromApplyPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextApplyVariant::tableLeafFromDeleteResult(
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

    public static function fromApplyPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextApplyVariant $applyPlan): self
    {
        $rows = self::buildAdvanceRows($applyPlan);
        $errors = self::advanceErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next257 advance failed: ' . implode('; ', $errors));
        }

        return new self($applyPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function advanceRows(): array
    {
        return $this->advanceRows;
    }

    /**
     * @return list<string>
     */
    public function advanceErrors(): array
    {
        return self::advanceErrorsForRows($this->advanceRows);
    }

    /**
     * @return list<int>
     */
    public function advancedPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['advanced_page'], $this->advanceRows));
    }

    /**
     * @return list<int>
     */
    public function committedFreeblockPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['advance_channel'] === 'freeblock-source-advance');
    }

    /**
     * @return list<int>
     */
    public function committedPointerMapPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['advance_channel'] === 'pointer-map-source-advance');
    }

    /**
     * @return array<int, list<int>>
     */
    public function committedPagesByGroup(): array
    {
        $groups = [];
        foreach ($this->advanceRows as $row) {
            $group = (int) $row['advance_group'];
            $groups[$group] ??= [];
            $groups[$group][] = (int) $row['advanced_page'];
        }
        ksort($groups);

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function advanceTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['advance_token'], $this->advanceRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function advanceSummary(): array
    {
        $applySummary = $this->applyPlan->applySummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next257-ready',
            'advance_row_count' => count($this->advanceRows),
            'advanced_pages' => $this->advancedPages(),
            'apply_pages' => $applySummary['apply_pages'],
            'advanced_pages_match_apply' => $this->advancedPages() === $applySummary['apply_pages'],
            'committed_pointer_map_pages' => $this->committedPointerMapPages(),
            'committed_freeblock_pages' => $this->committedFreeblockPages(),
            'committed_pages_by_group' => $this->committedPagesByGroup(),
            'all_apply_tokens_match' => !in_array(false, array_column($this->advanceRows, 'apply_token_matches'), true),
            'all_groups_have_pointer_map_opener' => !in_array(false, array_column($this->advanceRows, 'group_has_pointer_map_opener'), true),
            'all_freeblocks_wait_for_group_pointer_map' => !in_array(false, array_column($this->advanceRows, 'freeblock_waited_for_pointer_map'), true),
            'all_leaf_receipts_committed' => !in_array(false, array_column($this->advanceRows, 'leaf_receipt_committed'), true),
            'all_tail_pages_fenced_until_after_advance' => !in_array(false, array_column($this->advanceRows, 'tail_page_fenced_until_after_advance'), true),
            'all_source_epochs_monotonic' => !in_array(false, array_column($this->advanceRows, 'source_epoch_monotonic'), true),
            'all_advance_links_valid' => !in_array(false, array_column($this->advanceRows, 'advance_link_valid'), true),
            'advance_errors' => $this->advanceErrors(),
            'advance_signature' => self::signature($this->advanceTokens()),
            'current_source_next257_token' => self::signature(array_merge(
                ['next257', $applySummary['current_source_next253_token']],
                $this->advancedPages(),
                array_column($this->advanceRows, 'source_epoch'),
                $this->advanceTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next253',
                'sqlite-current-source-next257',
            ],
            'dependency_closure' => 'no new support component needed; next257 reuses next253 grouped apply rows and records the current-source advance fence after each pointer-map/freeblock group is durable',
            'non_overlap' => 'adds current-source advance fencing after next253 grouped apply rows; does not repeat next253 grouped apply ordering, next249 next-source allocation publication, next248 seal construction, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or WAL/VFS behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next257',
            'advance_summary' => $this->advanceSummary(),
            'advance_errors' => $this->advanceErrors(),
            'advance_rows' => $this->advanceRows,
            'apply_plan' => $this->applyPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->advanceRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['advanced_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildAdvanceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextApplyVariant $applyPlan): array
    {
        $applyRows = $applyPlan->applyRows();
        $applyTokens = $applyPlan->applyTokens();
        $rows = [];
        $previousAdvanceToken = null;
        $previousEpoch = 0;
        $groupPointerMapPages = [];

        foreach ($applyRows as $index => $applyRow) {
            $ordinal = $index + 1;
            $pageNumber = (int) $applyRow['apply_page'];
            $group = (int) $applyRow['apply_group'];
            $isPointerMap = $applyRow['apply_channel'] === 'pointer-map-apply';
            $advanceChannel = $isPointerMap ? 'pointer-map-source-advance' : 'freeblock-source-advance';

            if ($isPointerMap) {
                $groupPointerMapPages[$group] = $pageNumber;
            }

            $sourceEpoch = $previousEpoch + ($isPointerMap ? 2 : 1);
            $token = self::signature([
                'next257',
                $ordinal,
                $previousAdvanceToken ?? 'initial',
                $applyRow['apply_token'],
                $pageNumber,
                $advanceChannel,
                $group,
                $groupPointerMapPages[$group] ?? 0,
                $sourceEpoch,
            ]);

            $rows[] = [
                'advance_ordinal' => $ordinal,
                'apply_ordinal' => (int) $applyRow['apply_ordinal'],
                'advanced_page' => $pageNumber,
                'advance_channel' => $advanceChannel,
                'advance_group' => $group,
                'group_pointer_map_page' => $groupPointerMapPages[$group] ?? null,
                'source_apply_token' => (string) $applyRow['apply_token'],
                'expected_apply_token' => $applyTokens[$index] ?? null,
                'apply_token_matches' => ($applyTokens[$index] ?? null) === (string) $applyRow['apply_token'],
                'previous_advance_token' => $previousAdvanceToken,
                'source_epoch' => $sourceEpoch,
                'previous_source_epoch' => $previousEpoch,
                'group_has_pointer_map_opener' => isset($groupPointerMapPages[$group]),
                'freeblock_waited_for_pointer_map' => $isPointerMap || isset($groupPointerMapPages[$group]),
                'leaf_receipt_committed' => $isPointerMap || $applyRow['leaf_receipt_ready_at_apply'] === true,
                'tail_page_fenced_until_after_advance' => $applyRow['tail_page_still_fenced_at_apply'] === true,
                'source_epoch_monotonic' => $sourceEpoch > $previousEpoch,
                'advance_link_valid' => $applyRow['previous_apply_token'] === ($applyRows[$index - 1]['apply_token'] ?? null),
                'advance_state' => 'current-source-next257-advance-ready',
                'advance_token' => $token,
            ];

            $previousEpoch = $sourceEpoch;
            $previousAdvanceToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function advanceErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousEpoch = 0;

        foreach ($rows as $row) {
            if ($row['advance_state'] !== 'current-source-next257-advance-ready') {
                $errors[] = "advance {$row['advance_ordinal']} is not ready";
            }
            if ((int) $row['advance_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "advance {$row['advance_ordinal']} skipped an ordinal";
            }
            if ((int) $row['apply_ordinal'] !== (int) $row['advance_ordinal']) {
                $errors[] = "advance {$row['advance_ordinal']} drifted from apply ordinal";
            }
            if ($row['apply_token_matches'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} apply token drifted";
            }
            if ($row['previous_advance_token'] !== $previousToken) {
                $errors[] = "advance {$row['advance_ordinal']} broke token chaining";
            }
            if ($row['group_has_pointer_map_opener'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} lacks a pointer-map opener";
            }
            if ($row['freeblock_waited_for_pointer_map'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} exposed a freeblock before pointer-map advance";
            }
            if ($row['leaf_receipt_committed'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} lost the leaf receipt";
            }
            if ($row['tail_page_fenced_until_after_advance'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} admitted a fenced tail page too early";
            }
            if ($row['source_epoch_monotonic'] !== true || (int) $row['source_epoch'] <= $previousEpoch) {
                $errors[] = "advance {$row['advance_ordinal']} did not advance the source epoch";
            }
            if ($row['advance_link_valid'] !== true) {
                $errors[] = "advance {$row['advance_ordinal']} broke apply link continuity";
            }
            if ($row['advance_token'] === '') {
                $errors[] = "advance {$row['advance_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['advance_ordinal'];
            $previousEpoch = (int) $row['source_epoch'];
            $previousToken = (string) $row['advance_token'];
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextCurrentSourceVariant $currentSourcePlan,
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
        return self::fromCurrentSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextCurrentSourceVariant::tableLeafFromDeleteResult(
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

    public static function fromCurrentSourcePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextCurrentSourceVariant $currentSourcePlan): self
    {
        $rows = self::buildHandoffRows($currentSourcePlan);
        $errors = self::handoffErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next258 handoff failed: ' . implode('; ', $errors));
        }

        return new self($currentSourcePlan, $rows);
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
    public function handoffPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['handoff_page'], $this->handoffRows));
    }

    /**
     * @return list<int>
     */
    public function nextReusablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'next-source-reusable-page');
    }

    /**
     * @return list<int>
     */
    public function pointerMapFencePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'pointer-map-fence');
    }

    /**
     * @return list<int>
     */
    public function staleSlotFencedPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['stale_freeblock_slot_fenced'] === true);
    }

    /**
     * @return list<int>
     */
    public function handoffWriteOffsets(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['handoff_write_offset'], $this->handoffRows));
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
        $currentSummary = $this->currentSourcePlan->currentSourceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next258-ready',
            'handoff_row_count' => count($this->handoffRows),
            'handoff_pages' => $this->handoffPages(),
            'current_source_pages' => $currentSummary['current_source_pages'],
            'handoff_pages_match_current_source' => $this->handoffPages() === $currentSummary['current_source_pages'],
            'next_reusable_pages' => $this->nextReusablePages(),
            'pointer_map_fence_pages' => $this->pointerMapFencePages(),
            'stale_slot_fenced_pages' => $this->staleSlotFencedPages(),
            'handoff_write_offsets' => $this->handoffWriteOffsets(),
            'all_current_source_tokens_match' => !in_array(false, array_column($this->handoffRows, 'current_source_token_matches'), true),
            'all_pointer_map_fences_before_reuse' => !in_array(false, array_column($this->handoffRows, 'pointer_map_fence_before_reuse'), true),
            'all_next_reuse_has_current_slot' => !in_array(false, array_column($this->handoffRows, 'next_reuse_has_current_slot'), true),
            'all_stale_slots_fenced_before_next_reuse' => !in_array(false, array_column($this->handoffRows, 'stale_freeblock_slot_fenced'), true),
            'all_leaf_receipts_preserved' => !in_array(false, array_column($this->handoffRows, 'leaf_receipt_preserved_for_next_source'), true),
            'all_handoff_links_valid' => !in_array(false, array_column($this->handoffRows, 'handoff_link_valid'), true),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_signature' => self::signature($this->handoffTokens()),
            'current_source_next258_token' => self::signature(array_merge(
                ['next258', $currentSummary['current_source_next254_token']],
                $this->handoffPages(),
                $this->handoffWriteOffsets(),
                $this->handoffTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next254',
                'sqlite-current-source-next258',
            ],
            'dependency_closure' => 'no new support component needed; next258 reuses next254 page-local current-source write slots and adds the next-source stale-slot fence',
            'non_overlap' => 'adds next-source reusable-page handoff and stale-slot fencing after next254 freeblock write-slot publication; does not repeat next254 slot offsets, next249 allocation rows, next245 cursor admission, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next258',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
            'current_source_plan' => $this->currentSourcePlan->toArray(),
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
                $pages[(int) $row['handoff_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextCurrentSourceVariant $currentSourcePlan): array
    {
        $currentRows = $currentSourcePlan->currentSourceRows();
        $currentTokens = $currentSourcePlan->currentSourceTokens();
        $rows = [];
        $previousToken = null;
        $activePointerMapPage = null;
        $lastReusableSlotPage = null;

        foreach ($currentRows as $index => $currentRow) {
            $pageNumber = (int) $currentRow['current_source_page'];
            $isPointerMap = $currentRow['current_source_channel'] === 'pointer-map-anchor';
            $isReusable = $currentRow['current_source_channel'] === 'freeblock-write-slot';
            if ($isPointerMap) {
                $activePointerMapPage = $pageNumber;
            }

            $ordinal = $index + 1;
            $currentToken = (string) $currentRow['current_source_token'];
            $writeOffset = (int) $currentRow['current_source_write_offset'];
            $channel = $isPointerMap ? 'pointer-map-fence' : 'next-source-reusable-page';
            $staleSlotFenced = $isPointerMap || ($lastReusableSlotPage === null || $activePointerMapPage !== null);
            $token = self::signature([
                'next258',
                $ordinal,
                $previousToken ?? 'initial',
                $currentToken,
                $pageNumber,
                $channel,
                $activePointerMapPage ?? 0,
                $lastReusableSlotPage ?? 0,
                $writeOffset,
            ]);

            $rows[] = [
                'handoff_ordinal' => $ordinal,
                'current_source_ordinal' => (int) $currentRow['current_source_ordinal'],
                'handoff_page' => $pageNumber,
                'handoff_channel' => $channel,
                'source_current_source_token' => $currentToken,
                'expected_current_source_token' => $currentTokens[$index] ?? null,
                'current_source_token_matches' => ($currentTokens[$index] ?? null) === $currentToken,
                'previous_handoff_token' => $previousToken,
                'active_pointer_map_page' => $activePointerMapPage,
                'previous_reusable_slot_page' => $lastReusableSlotPage,
                'handoff_write_offset' => $writeOffset,
                'pointer_map_fence_before_reuse' => $isPointerMap || $activePointerMapPage !== null,
                'next_reuse_has_current_slot' => !$isReusable || ($writeOffset >= 8 && $writeOffset < 512),
                'stale_freeblock_slot_fenced' => $staleSlotFenced,
                'leaf_receipt_preserved_for_next_source' => !$isReusable || $currentRow['reusable_receipt_current'] === true,
                'handoff_link_valid' => $currentRow['previous_current_source_token'] === ($currentRows[$index - 1]['current_source_token'] ?? null),
                'handoff_state' => 'current-source-next258-next-source-reuse-handoff-ready',
                'handoff_token' => $token,
            ];

            if ($isReusable) {
                $lastReusableSlotPage = $pageNumber;
            }
            $previousToken = $token;
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
            if ($row['handoff_state'] !== 'current-source-next258-next-source-reuse-handoff-ready') {
                $errors[] = "handoff {$row['handoff_ordinal']} is not ready";
            }
            if ((int) $row['handoff_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "handoff {$row['handoff_ordinal']} skipped an ordinal";
            }
            if ((int) $row['current_source_ordinal'] !== (int) $row['handoff_ordinal']) {
                $errors[] = "handoff {$row['handoff_ordinal']} drifted from current-source ordinal";
            }
            if ($row['current_source_token_matches'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} current-source token drifted";
            }
            if ($row['previous_handoff_token'] !== $previousToken) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke token chaining";
            }
            if ($row['pointer_map_fence_before_reuse'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} reused a page before pointer-map fencing";
            }
            if ($row['next_reuse_has_current_slot'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} is missing the current freeblock slot";
            }
            if ($row['stale_freeblock_slot_fenced'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} left a stale freeblock slot visible";
            }
            if ($row['leaf_receipt_preserved_for_next_source'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lost the leaf receipt";
            }
            if ($row['handoff_link_valid'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke current-source link continuity";
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextSourceNextVariant
{
    /**
     * @param list<array<string, mixed>> $sourceNextRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPublicationVariant $publicationPlan,
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
        return self::fromPublicationPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPublicationVariant::tableLeafFromDeleteResult(
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

    public static function fromPublicationPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPublicationVariant $publicationPlan): self
    {
        $rows = self::buildSourceNextRows($publicationPlan);
        $errors = self::sourceNextErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next259 source-next cursor failed: ' . implode('; ', $errors));
        }

        return new self($publicationPlan, $rows);
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
    public function sourcePages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['source_page'], $this->sourceNextRows));
    }

    /**
     * @return list<int|null>
     */
    public function sourceNextPages(): array
    {
        return array_values(array_map(static fn (array $row): ?int => $row['source_next_page'], $this->sourceNextRows));
    }

    /**
     * @return list<int>
     */
    public function pointerMapSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'pointer-map-source');
    }

    /**
     * @return list<int>
     */
    public function freeblockSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'freeblock-source');
    }

    /**
     * @return list<int>
     */
    public function payloadSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['source_channel'] === 'payload-source');
    }

    /**
     * @return list<int>
     */
    public function duplicatePointerMapSourcePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['duplicate_pointer_map_source_generation'] === true);
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
        $publicationSummary = $this->publicationPlan->publicationSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next259-ready',
            'source_next_row_count' => count($this->sourceNextRows),
            'source_pages' => $this->sourcePages(),
            'source_next_pages' => $this->sourceNextPages(),
            'published_pages' => $publicationSummary['published_pages'],
            'source_pages_match_publication' => $this->sourcePages() === $publicationSummary['published_pages'],
            'source_next_pages_match_publication' => $this->sourceNextPages() === $publicationSummary['next_published_pages'],
            'pointer_map_source_pages' => $this->pointerMapSourcePages(),
            'freeblock_source_pages' => $this->freeblockSourcePages(),
            'payload_source_pages' => $this->payloadSourcePages(),
            'duplicate_pointer_map_source_pages' => $this->duplicatePointerMapSourcePages(),
            'all_publication_tokens_match' => !in_array(false, array_column($this->sourceNextRows, 'publication_token_matches'), true),
            'all_source_next_links_valid' => !in_array(false, array_column($this->sourceNextRows, 'source_next_link_valid'), true),
            'all_freeblocks_open_after_pointer_map' => !in_array(false, array_column($this->sourceNextRows, 'freeblock_opens_after_pointer_map_source'), true),
            'all_payloads_wait_for_freeblock_source' => !in_array(false, array_column($this->sourceNextRows, 'payload_waits_for_freeblock_source'), true),
            'all_tail_pages_fenced_for_source_next' => !in_array(false, array_column($this->sourceNextRows, 'tail_pages_fenced_for_source_next'), true),
            'source_next_errors' => $this->sourceNextErrors(),
            'source_next_signature' => self::signature($this->sourceNextTokens()),
            'current_source_next259_token' => self::signature(array_merge(
                ['next259', $publicationSummary['current_source_next255_token']],
                $this->sourcePages(),
                $this->sourceNextPages(),
                $this->sourceNextTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next255',
                'sqlite-current-source-next259',
            ],
            'dependency_closure' => 'no new support component needed; next259 reuses next255 publication rows and validates current-source next links before freeblock/payload reuse',
            'non_overlap' => 'adds current-source next-link validation after next255 publication; does not repeat next255 publication, next251 admission, next254 write slots, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next259',
            'source_next_summary' => $this->sourceNextSummary(),
            'source_next_errors' => $this->sourceNextErrors(),
            'source_next_rows' => $this->sourceNextRows,
            'publication_plan' => $this->publicationPlan->toArray(),
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
                $pages[(int) $row['source_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSourceNextRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPublicationVariant $publicationPlan): array
    {
        $publicationRows = $publicationPlan->publicationRows();
        $publicationTokens = $publicationPlan->publicationTokens();
        $rows = [];
        $previousToken = null;
        $seenPointerMapSource = false;
        $seenFreeblockSource = false;
        $pointerMapGenerations = [];

        foreach ($publicationRows as $index => $publicationRow) {
            $pageNumber = (int) $publicationRow['published_page'];
            $publicationChannel = (string) $publicationRow['publication_channel'];
            $ordinal = $index + 1;
            $sourceChannel = match ($publicationChannel) {
                'pointer-map' => 'pointer-map-source',
                'payload' => $pageNumber === 3 ? 'freeblock-source' : 'payload-source',
                default => 'unknown-source',
            };

            if ($sourceChannel === 'pointer-map-source') {
                $seenPointerMapSource = true;
                $pointerMapGenerations[$pageNumber] = ($pointerMapGenerations[$pageNumber] ?? 0) + 1;
            }
            if ($sourceChannel === 'freeblock-source') {
                $seenFreeblockSource = true;
            }

            $nextPage = $publicationRow['next_published_page'];
            $publicationToken = (string) $publicationRow['publication_token'];
            $token = self::signature(array_merge(
                ['next259', $ordinal, $previousToken ?? 'initial', $publicationToken],
                [$pageNumber, $nextPage ?? 'eof', $sourceChannel],
                self::generationParts($pointerMapGenerations),
                [$seenPointerMapSource ? 'pointer-map-seen' : 'pointer-map-pending'],
                [$seenFreeblockSource ? 'freeblock-seen' : 'freeblock-pending'],
            ));

            $rows[] = [
                'source_next_ordinal' => $ordinal,
                'publication_ordinal' => (int) $publicationRow['publication_ordinal'],
                'source_page' => $pageNumber,
                'source_next_page' => is_int($nextPage) ? $nextPage : null,
                'source_channel' => $sourceChannel,
                'source_publication_token' => $publicationToken,
                'expected_publication_token' => $publicationTokens[$index] ?? null,
                'publication_token_matches' => ($publicationTokens[$index] ?? null) === $publicationToken,
                'previous_source_next_token' => $previousToken,
                'source_next_link_valid' => $nextPage === ($publicationRows[$index + 1]['published_page'] ?? null),
                'pointer_map_source_generations' => self::generationParts($pointerMapGenerations),
                'duplicate_pointer_map_source_generation' => $sourceChannel === 'pointer-map-source' && ($pointerMapGenerations[$pageNumber] ?? 0) > 1,
                'freeblock_opens_after_pointer_map_source' => $sourceChannel !== 'freeblock-source' || $seenPointerMapSource,
                'payload_waits_for_freeblock_source' => $sourceChannel !== 'payload-source' || ($seenPointerMapSource && $seenFreeblockSource),
                'tail_pages_fenced_for_source_next' => $publicationRow['tail_pages_remain_fenced_for_publication'] === true && !in_array($pageNumber, [109, 110], true),
                'source_next_state' => $sourceChannel === 'pointer-map-source' ? 'current-source-next259-pointer-map-linked' : 'current-source-next259-freeblock-linked',
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
        $seenPointerMap = false;
        $seenFreeblock = false;

        foreach ($rows as $row) {
            if ((int) $row['source_next_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "source-next {$row['source_next_ordinal']} skipped an ordinal";
            }
            if ((int) $row['publication_ordinal'] !== (int) $row['source_next_ordinal']) {
                $errors[] = "source-next {$row['source_next_ordinal']} drifted from publication ordinal";
            }
            if ($row['publication_token_matches'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} publication token drifted";
            }
            if ($row['previous_source_next_token'] !== $previousToken) {
                $errors[] = "source-next {$row['source_next_ordinal']} broke source-next token chaining";
            }
            if ($row['source_next_link_valid'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} does not point at the next published page";
            }
            if ($row['source_channel'] === 'pointer-map-source') {
                $seenPointerMap = true;
            }
            if ($row['source_channel'] === 'freeblock-source') {
                if (!$seenPointerMap || $row['freeblock_opens_after_pointer_map_source'] !== true) {
                    $errors[] = "source-next {$row['source_next_ordinal']} opened freeblock source before pointer-map source";
                }
                $seenFreeblock = true;
            }
            if ($row['source_channel'] === 'payload-source' && $row['payload_waits_for_freeblock_source'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} exposed payload before freeblock source";
            }
            if ($row['tail_pages_fenced_for_source_next'] !== true) {
                $errors[] = "source-next {$row['source_next_ordinal']} exposed a fenced tail page";
            }
            if ($row['source_next_token'] === '') {
                $errors[] = "source-next {$row['source_next_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['source_next_ordinal'];
            $previousToken = (string) $row['source_next_token'];
        }

        if ($rows === []) {
            $errors[] = 'source-next cursor plan is empty';
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
            $parts[] = ((int) $pageNumber) . ':' . ((int) $generation);
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReaderHandoffVariant
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdvanceVariant $advancePlan,
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
        return self::fromAdvancePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdvanceVariant::tableLeafFromDeleteResult(
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

    public static function fromAdvancePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdvanceVariant $advancePlan): self
    {
        $rows = self::buildHandoffRows($advancePlan);
        $errors = self::handoffErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next260 handoff failed: ' . implode('; ', $errors));
        }

        return new self($advancePlan, $rows);
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
    public function handoffPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['handoff_page'], $this->handoffRows));
    }

    /**
     * @return list<int>
     */
    public function readerVisiblePages(): array
    {
        $pages = [];
        foreach ($this->handoffRows as $row) {
            if ($row['reader_visible_at_handoff'] === true) {
                $pages[(int) $row['handoff_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function pointerMapSnapshotPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'pointer-map-snapshot');
    }

    /**
     * @return list<int>
     */
    public function reusableFreeblockSnapshotPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['handoff_channel'] === 'reusable-freeblock-snapshot');
    }

    /**
     * @return array<int, list<int>>
     */
    public function readerVisiblePagesByGroup(): array
    {
        $groups = [];
        foreach ($this->handoffRows as $row) {
            $group = (int) $row['handoff_group'];
            $groups[$group] ??= [];
            $groups[$group][] = (int) $row['handoff_page'];
        }
        ksort($groups);

        return $groups;
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
        $advanceSummary = $this->advancePlan->advanceSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next260-ready',
            'handoff_row_count' => count($this->handoffRows),
            'handoff_pages' => $this->handoffPages(),
            'advanced_pages' => $advanceSummary['advanced_pages'],
            'handoff_pages_match_advanced_pages' => $this->handoffPages() === $advanceSummary['advanced_pages'],
            'reader_visible_pages' => $this->readerVisiblePages(),
            'pointer_map_snapshot_pages' => $this->pointerMapSnapshotPages(),
            'reusable_freeblock_snapshot_pages' => $this->reusableFreeblockSnapshotPages(),
            'reader_visible_pages_by_group' => $this->readerVisiblePagesByGroup(),
            'all_advance_tokens_match' => !in_array(false, array_column($this->handoffRows, 'advance_token_matches'), true),
            'all_group_snapshots_have_pointer_map' => !in_array(false, array_column($this->handoffRows, 'group_snapshot_has_pointer_map'), true),
            'all_reader_visibility_after_pointer_map' => !in_array(false, array_column($this->handoffRows, 'reader_visibility_after_pointer_map'), true),
            'all_freeblock_receipts_reader_visible' => !in_array(false, array_column($this->handoffRows, 'freeblock_receipt_reader_visible'), true),
            'all_tail_pages_blocked_from_reader' => !in_array(false, array_column($this->handoffRows, 'tail_page_blocked_from_reader'), true),
            'all_source_epochs_preserved' => !in_array(false, array_column($this->handoffRows, 'source_epoch_preserved'), true),
            'all_handoff_links_valid' => !in_array(false, array_column($this->handoffRows, 'handoff_link_valid'), true),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_signature' => self::signature($this->handoffTokens()),
            'current_source_next260_token' => self::signature(array_merge(
                ['next260', $advanceSummary['current_source_next257_token']],
                $this->handoffPages(),
                array_column($this->handoffRows, 'reader_source_epoch'),
                $this->handoffTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next257',
                'sqlite-current-source-next260',
            ],
            'dependency_closure' => 'no new support component needed; next260 reuses next257 advance fences and publishes grouped reader-visible current-source snapshots',
            'non_overlap' => 'adds reader-visible handoff snapshots after next257 source advance; does not repeat next257 advance fencing, next253 grouped apply ordering, next249 allocation publication, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, or WAL/VFS behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next260',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_errors' => $this->handoffErrors(),
            'handoff_rows' => $this->handoffRows,
            'advance_plan' => $this->advancePlan->toArray(),
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
                $pages[(int) $row['handoff_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextAdvanceVariant $advancePlan): array
    {
        $advanceRows = $advancePlan->advanceRows();
        $advanceTokens = $advancePlan->advanceTokens();
        $rows = [];
        $previousToken = null;
        $groupPointerMapPages = [];
        $readerVisiblePages = [];

        foreach ($advanceRows as $index => $advanceRow) {
            $ordinal = $index + 1;
            $pageNumber = (int) $advanceRow['advanced_page'];
            $group = (int) $advanceRow['advance_group'];
            $isPointerMap = $advanceRow['advance_channel'] === 'pointer-map-source-advance';
            $handoffChannel = $isPointerMap ? 'pointer-map-snapshot' : 'reusable-freeblock-snapshot';

            if ($isPointerMap) {
                $groupPointerMapPages[$group] = $pageNumber;
            }

            $readerVisiblePages[$pageNumber] = true;
            $groupHasPointerMap = isset($groupPointerMapPages[$group]);
            $readerEpoch = (int) $advanceRow['source_epoch'] + $group;
            $token = self::signature(array_merge(
                ['next260', $ordinal, $previousToken ?? 'initial', $advanceRow['advance_token']],
                [$pageNumber, $handoffChannel, $group, $groupPointerMapPages[$group] ?? 0, $readerEpoch],
                self::sortedIntKeys($readerVisiblePages),
            ));

            $rows[] = [
                'handoff_ordinal' => $ordinal,
                'advance_ordinal' => (int) $advanceRow['advance_ordinal'],
                'handoff_page' => $pageNumber,
                'handoff_channel' => $handoffChannel,
                'handoff_group' => $group,
                'group_pointer_map_page' => $groupPointerMapPages[$group] ?? null,
                'source_advance_token' => (string) $advanceRow['advance_token'],
                'expected_advance_token' => $advanceTokens[$index] ?? null,
                'advance_token_matches' => ($advanceTokens[$index] ?? null) === (string) $advanceRow['advance_token'],
                'previous_handoff_token' => $previousToken,
                'reader_source_epoch' => $readerEpoch,
                'source_epoch' => (int) $advanceRow['source_epoch'],
                'reader_visible_pages' => self::sortedIntKeys($readerVisiblePages),
                'reader_visible_at_handoff' => true,
                'group_snapshot_has_pointer_map' => $groupHasPointerMap,
                'reader_visibility_after_pointer_map' => $isPointerMap || $groupHasPointerMap,
                'freeblock_receipt_reader_visible' => $isPointerMap || $advanceRow['leaf_receipt_committed'] === true,
                'tail_page_blocked_from_reader' => $advanceRow['tail_page_fenced_until_after_advance'] === true && !in_array($pageNumber, [109, 110], true),
                'source_epoch_preserved' => $readerEpoch > (int) $advanceRow['source_epoch'],
                'handoff_link_valid' => $advanceRow['previous_advance_token'] === ($advanceRows[$index - 1]['advance_token'] ?? null),
                'handoff_state' => 'current-source-next260-reader-handoff-ready',
                'handoff_token' => $token,
            ];

            $previousToken = $token;
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
        $previousReaderEpoch = 0;

        foreach ($rows as $row) {
            if ($row['handoff_state'] !== 'current-source-next260-reader-handoff-ready') {
                $errors[] = "handoff {$row['handoff_ordinal']} is not ready";
            }
            if ((int) $row['handoff_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "handoff {$row['handoff_ordinal']} skipped an ordinal";
            }
            if ((int) $row['advance_ordinal'] !== (int) $row['handoff_ordinal']) {
                $errors[] = "handoff {$row['handoff_ordinal']} drifted from advance ordinal";
            }
            if ($row['advance_token_matches'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} advance token drifted";
            }
            if ($row['previous_handoff_token'] !== $previousToken) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke token chaining";
            }
            if ($row['group_snapshot_has_pointer_map'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lacks a pointer-map group snapshot";
            }
            if ($row['reader_visibility_after_pointer_map'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} exposed a reader page before pointer-map snapshot";
            }
            if ($row['freeblock_receipt_reader_visible'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} lost reader-visible freeblock receipt";
            }
            if ($row['tail_page_blocked_from_reader'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} exposed a fenced tail page to readers";
            }
            if ($row['source_epoch_preserved'] !== true || (int) $row['reader_source_epoch'] <= $previousReaderEpoch) {
                $errors[] = "handoff {$row['handoff_ordinal']} did not preserve a monotonic reader epoch";
            }
            if ($row['handoff_link_valid'] !== true) {
                $errors[] = "handoff {$row['handoff_ordinal']} broke advance link continuity";
            }
            if ($row['handoff_token'] === '') {
                $errors[] = "handoff {$row['handoff_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['handoff_ordinal'];
            $previousReaderEpoch = (int) $row['reader_source_epoch'];
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextVacuumVariant
{
    /**
     * @param list<array<string, mixed>> $vacuumRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant $handoffPlan,
        private readonly array $vacuumRows,
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
        return self::fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant::tableLeafFromDeleteResult(
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

    public static function fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant $handoffPlan): self
    {
        $rows = self::buildVacuumRows($handoffPlan);
        $errors = self::vacuumErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next261 finalization failed: ' . implode('; ', $errors));
        }

        return new self($handoffPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vacuumRows(): array
    {
        return $this->vacuumRows;
    }

    /**
     * @return list<string>
     */
    public function vacuumErrors(): array
    {
        return self::vacuumErrorsForRows($this->vacuumRows);
    }

    /**
     * @return list<int>
     */
    public function finalizedReusablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['vacuum_channel'] === 'finalized-reusable-freeblock');
    }

    /**
     * @return array<int, list<int>>
     */
    public function reusablePagesByPointerMap(): array
    {
        $pages = [];
        foreach ($this->vacuumRows as $row) {
            if ($row['vacuum_channel'] !== 'finalized-reusable-freeblock') {
                continue;
            }
            $pointerMapPage = (int) $row['active_pointer_map_page'];
            $pages[$pointerMapPage] ??= [];
            $pages[$pointerMapPage][] = (int) $row['vacuum_page'];
        }
        ksort($pages);

        return $pages;
    }

    /**
     * @return list<int>
     */
    public function fencePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['vacuum_channel'] === 'pointer-map-batch-fence');
    }

    /**
     * @return list<int>
     */
    public function finalizedWriteOffsets(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['vacuum_write_offset'],
            array_values(array_filter(
                $this->vacuumRows,
                static fn (array $row): bool => $row['vacuum_channel'] === 'finalized-reusable-freeblock',
            )),
        ));
    }

    /**
     * @return list<string>
     */
    public function vacuumTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['vacuum_token'], $this->vacuumRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function vacuumSummary(): array
    {
        $handoffSummary = $this->handoffPlan->handoffSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next261-ready',
            'vacuum_row_count' => count($this->vacuumRows),
            'fence_pages' => $this->fencePages(),
            'finalized_reusable_pages' => $this->finalizedReusablePages(),
            'reusable_pages_by_pointer_map' => $this->reusablePagesByPointerMap(),
            'finalized_write_offsets' => $this->finalizedWriteOffsets(),
            'handoff_pages' => $handoffSummary['handoff_pages'],
            'handoff_signature' => $handoffSummary['handoff_signature'],
            'all_handoff_tokens_preserved' => !in_array(false, array_column($this->vacuumRows, 'handoff_token_preserved'), true),
            'all_pointer_map_batches_fenced' => !in_array(false, array_column($this->vacuumRows, 'pointer_map_batch_fenced'), true),
            'all_reusable_slots_finalized' => !in_array(false, array_column($this->vacuumRows, 'reusable_slot_finalized'), true),
            'all_offsets_current_source_safe' => !in_array(false, array_column($this->vacuumRows, 'offset_current_source_safe'), true),
            'all_vacuum_links_valid' => !in_array(false, array_column($this->vacuumRows, 'vacuum_link_valid'), true),
            'vacuum_errors' => $this->vacuumErrors(),
            'vacuum_signature' => self::signature($this->vacuumTokens()),
            'current_source_next261_token' => self::signature(array_merge(
                ['next261', $handoffSummary['current_source_next258_token']],
                $this->fencePages(),
                $this->finalizedReusablePages(),
                $this->finalizedWriteOffsets(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next258',
                'sqlite-current-source-next261',
            ],
            'dependency_closure' => 'no new support component needed; next261 reuses next258 current-source handoff rows and finalizes pointer-map-scoped reusable freeblock batches',
            'non_overlap' => 'adds pointer-map-scoped vacuum finalization over next258 handoff rows; does not repeat next258 stale-slot fencing, next254 write-slot publication, next249 allocation rows, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next261',
            'vacuum_summary' => $this->vacuumSummary(),
            'vacuum_errors' => $this->vacuumErrors(),
            'vacuum_rows' => $this->vacuumRows,
            'handoff_plan' => $this->handoffPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->vacuumRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['vacuum_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildVacuumRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant $handoffPlan): array
    {
        $rows = [];
        $previousToken = null;
        $activePointerMapPage = null;
        $batchOrdinalByPointerMap = [];

        foreach ($handoffPlan->handoffRows() as $index => $handoffRow) {
            $pageNumber = (int) $handoffRow['handoff_page'];
            $isFence = $handoffRow['handoff_channel'] === 'pointer-map-fence';
            if ($isFence) {
                $activePointerMapPage = $pageNumber;
                $batchOrdinalByPointerMap[$activePointerMapPage] ??= 0;
            }

            $isReusable = $handoffRow['handoff_channel'] === 'next-source-reusable-page';
            if ($isReusable && $activePointerMapPage !== null) {
                $batchOrdinalByPointerMap[$activePointerMapPage] = ($batchOrdinalByPointerMap[$activePointerMapPage] ?? 0) + 1;
            }

            $channel = $isFence ? 'pointer-map-batch-fence' : 'finalized-reusable-freeblock';
            $offset = (int) $handoffRow['handoff_write_offset'];
            $vacuumToken = self::signature([
                'next261',
                $index + 1,
                $previousToken ?? 'initial',
                $handoffRow['handoff_token'],
                $pageNumber,
                $channel,
                $activePointerMapPage ?? 0,
                $batchOrdinalByPointerMap[$activePointerMapPage ?? 0] ?? 0,
                $offset,
            ]);

            $rows[] = [
                'vacuum_ordinal' => $index + 1,
                'handoff_ordinal' => (int) $handoffRow['handoff_ordinal'],
                'vacuum_page' => $pageNumber,
                'vacuum_channel' => $channel,
                'active_pointer_map_page' => $activePointerMapPage,
                'pointer_map_batch_ordinal' => $isReusable ? ($batchOrdinalByPointerMap[$activePointerMapPage ?? 0] ?? 0) : 0,
                'source_handoff_token' => (string) $handoffRow['handoff_token'],
                'previous_vacuum_token' => $previousToken,
                'vacuum_write_offset' => $offset,
                'handoff_token_preserved' => $handoffRow['handoff_token'] !== '',
                'pointer_map_batch_fenced' => $isFence || $activePointerMapPage !== null,
                'reusable_slot_finalized' => !$isReusable || ($handoffRow['next_reuse_has_current_slot'] === true && $handoffRow['stale_freeblock_slot_fenced'] === true),
                'offset_current_source_safe' => $isFence || ($offset >= 8 && $offset < 512),
                'vacuum_link_valid' => $handoffRow['previous_handoff_token'] === ($handoffPlan->handoffRows()[$index - 1]['handoff_token'] ?? null),
                'vacuum_state' => 'current-source-next261-vacuum-freeblock-finalized',
                'vacuum_token' => $vacuumToken,
            ];

            $previousToken = $vacuumToken;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function vacuumErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['vacuum_state'] !== 'current-source-next261-vacuum-freeblock-finalized') {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} is not finalized";
            }
            if ((int) $row['vacuum_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} skipped an ordinal";
            }
            if ((int) $row['handoff_ordinal'] !== (int) $row['vacuum_ordinal']) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} drifted from handoff ordinal";
            }
            if ($row['previous_vacuum_token'] !== $previousToken) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} broke token chaining";
            }
            if ($row['handoff_token_preserved'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} lost its handoff token";
            }
            if ($row['pointer_map_batch_fenced'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} lacks a pointer-map fence";
            }
            if ($row['reusable_slot_finalized'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} left a reusable slot unfinished";
            }
            if ($row['offset_current_source_safe'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} has an unsafe current-source offset";
            }
            if ($row['vacuum_link_valid'] !== true) {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} broke handoff link continuity";
            }
            if ($row['vacuum_token'] === '') {
                $errors[] = "vacuum row {$row['vacuum_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['vacuum_ordinal'];
            $previousToken = (string) $row['vacuum_token'];
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReplayVariant
{
    /**
     * @param list<array<string, mixed>> $replayRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant $handoffPlan,
        private readonly array $replayRows,
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
        return self::fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant::tableLeafFromDeleteResult(
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

    public static function fromHandoffPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant $handoffPlan): self
    {
        $rows = self::buildReplayRows($handoffPlan);
        $errors = self::replayErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next262 replay failed: ' . implode('; ', $errors));
        }

        return new self($handoffPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function replayRows(): array
    {
        return $this->replayRows;
    }

    /**
     * @return list<string>
     */
    public function replayErrors(): array
    {
        return self::replayErrorsForRows($this->replayRows);
    }

    /**
     * @return list<int>
     */
    public function replayPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['replay_page'], $this->replayRows));
    }

    /**
     * @return list<int>
     */
    public function barrierPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['replay_channel'] === 'pointer-map-replay-barrier');
    }

    /**
     * @return list<int>
     */
    public function consumablePages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['replay_channel'] === 'freeblock-consume-ready');
    }

    /**
     * @return list<int>
     */
    public function replayWriteOffsets(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['replay_write_offset'], $this->replayRows));
    }

    /**
     * @return list<int>
     */
    public function replayBarrierEpochs(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['replay_barrier_epoch'], $this->replayRows));
    }

    /**
     * @return list<string>
     */
    public function replayTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['replay_token'], $this->replayRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function replaySummary(): array
    {
        $handoffSummary = $this->handoffPlan->handoffSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next262-ready',
            'replay_row_count' => count($this->replayRows),
            'replay_pages' => $this->replayPages(),
            'handoff_pages' => $handoffSummary['handoff_pages'],
            'replay_pages_match_handoff' => $this->replayPages() === $handoffSummary['handoff_pages'],
            'barrier_pages' => $this->barrierPages(),
            'consumable_pages' => $this->consumablePages(),
            'replay_write_offsets' => $this->replayWriteOffsets(),
            'replay_barrier_epochs' => $this->replayBarrierEpochs(),
            'all_handoff_tokens_match' => !in_array(false, array_column($this->replayRows, 'handoff_token_matches'), true),
            'all_barriers_seen_before_consume' => !in_array(false, array_column($this->replayRows, 'barrier_seen_before_consume'), true),
            'all_stale_slots_remain_fenced' => !in_array(false, array_column($this->replayRows, 'stale_slot_remains_fenced'), true),
            'all_leaf_receipts_replayable' => !in_array(false, array_column($this->replayRows, 'leaf_receipt_replayable'), true),
            'all_replay_links_valid' => !in_array(false, array_column($this->replayRows, 'replay_link_valid'), true),
            'replay_errors' => $this->replayErrors(),
            'replay_signature' => self::signature($this->replayTokens()),
            'current_source_next262_token' => self::signature(array_merge(
                ['next262', $handoffSummary['current_source_next258_token']],
                $this->replayPages(),
                $this->replayWriteOffsets(),
                $this->replayTokens(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next258',
                'sqlite-current-source-next262',
            ],
            'dependency_closure' => 'no new support component needed; next262 reuses next258 handoff rows and records the final replay barrier before next-source freeblock consumption',
            'non_overlap' => 'adds final replay-barrier ordering after next258 stale-slot fencing; does not repeat next258 handoff rows, next254 write slots, next249 allocation publication, accepted batch221 next258 behavior, overflow freelist release, page relocation, root collapse, VFS, WAL, JSON, SQL, PRAGMA, encoding, or suite-runner surfaces',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next262',
            'replay_summary' => $this->replaySummary(),
            'replay_errors' => $this->replayErrors(),
            'replay_rows' => $this->replayRows,
            'handoff_plan' => $this->handoffPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->replayRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['replay_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReplayRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextReusableHandoffVariant $handoffPlan): array
    {
        $handoffRows = $handoffPlan->handoffRows();
        $handoffTokens = $handoffPlan->handoffTokens();
        $rows = [];
        $previousToken = null;
        $barrierEpoch = 0;
        $lastBarrierPage = null;
        $lastConsumablePage = null;

        foreach ($handoffRows as $index => $handoffRow) {
            $pageNumber = (int) $handoffRow['handoff_page'];
            $isBarrier = $handoffRow['handoff_channel'] === 'pointer-map-fence';
            if ($isBarrier) {
                ++$barrierEpoch;
                $lastBarrierPage = $pageNumber;
            }

            $ordinal = $index + 1;
            $handoffToken = (string) $handoffRow['handoff_token'];
            $writeOffset = (int) $handoffRow['handoff_write_offset'];
            $channel = $isBarrier ? 'pointer-map-replay-barrier' : 'freeblock-consume-ready';
            $token = self::signature([
                'next262',
                $ordinal,
                $previousToken ?? 'initial',
                $handoffToken,
                $pageNumber,
                $channel,
                $barrierEpoch,
                $lastBarrierPage ?? 0,
                $lastConsumablePage ?? 0,
                $writeOffset,
            ]);

            $rows[] = [
                'replay_ordinal' => $ordinal,
                'handoff_ordinal' => (int) $handoffRow['handoff_ordinal'],
                'replay_page' => $pageNumber,
                'replay_channel' => $channel,
                'source_handoff_token' => $handoffToken,
                'expected_handoff_token' => $handoffTokens[$index] ?? null,
                'handoff_token_matches' => ($handoffTokens[$index] ?? null) === $handoffToken,
                'previous_replay_token' => $previousToken,
                'replay_barrier_epoch' => $barrierEpoch,
                'last_barrier_page' => $lastBarrierPage,
                'previous_consumable_page' => $lastConsumablePage,
                'replay_write_offset' => $writeOffset,
                'barrier_seen_before_consume' => $isBarrier || $barrierEpoch > 0,
                'stale_slot_remains_fenced' => $handoffRow['stale_freeblock_slot_fenced'] === true,
                'leaf_receipt_replayable' => $isBarrier || $handoffRow['leaf_receipt_preserved_for_next_source'] === true,
                'replay_link_valid' => $handoffRow['previous_handoff_token'] === ($handoffRows[$index - 1]['handoff_token'] ?? null),
                'replay_state' => 'current-source-next262-replay-barrier-ready',
                'replay_token' => $token,
            ];

            if (!$isBarrier) {
                $lastConsumablePage = $pageNumber;
            }
            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function replayErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousBarrierEpoch = 0;

        foreach ($rows as $row) {
            if ($row['replay_state'] !== 'current-source-next262-replay-barrier-ready') {
                $errors[] = "replay {$row['replay_ordinal']} is not ready";
            }
            if ((int) $row['replay_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "replay {$row['replay_ordinal']} skipped an ordinal";
            }
            if ((int) $row['handoff_ordinal'] !== (int) $row['replay_ordinal']) {
                $errors[] = "replay {$row['replay_ordinal']} drifted from handoff ordinal";
            }
            if ($row['handoff_token_matches'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} handoff token drifted";
            }
            if ($row['previous_replay_token'] !== $previousToken) {
                $errors[] = "replay {$row['replay_ordinal']} broke token chaining";
            }
            if ((int) $row['replay_barrier_epoch'] < $previousBarrierEpoch) {
                $errors[] = "replay {$row['replay_ordinal']} moved barrier epoch backward";
            }
            if ($row['barrier_seen_before_consume'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} consumed a page before a pointer-map barrier";
            }
            if ($row['stale_slot_remains_fenced'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} reopened a stale freeblock slot";
            }
            if ($row['leaf_receipt_replayable'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} lost the leaf receipt";
            }
            if ($row['replay_link_valid'] !== true) {
                $errors[] = "replay {$row['replay_ordinal']} broke handoff link continuity";
            }
            if ($row['replay_token'] === '') {
                $errors[] = "replay {$row['replay_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['replay_ordinal'];
            $previousBarrierEpoch = (int) $row['replay_barrier_epoch'];
            $previousToken = (string) $row['replay_token'];
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


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistSpliceVariant
{
    /**
     * @param list<array<string, mixed>> $freelistRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextVacuumVariant $vacuumPlan,
        private readonly array $freelistRows,
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
        return self::fromVacuumPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextVacuumVariant::tableLeafFromDeleteResult(
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

    public static function fromVacuumPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextVacuumVariant $vacuumPlan): self
    {
        $rows = self::buildFreelistRows($vacuumPlan);
        $errors = self::freelistErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next263 freelist splice failed: ' . implode('; ', $errors));
        }

        return new self($vacuumPlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function freelistRows(): array
    {
        return $this->freelistRows;
    }

    /**
     * @return list<string>
     */
    public function freelistErrors(): array
    {
        return self::freelistErrorsForRows($this->freelistRows);
    }

    /**
     * @return list<int>
     */
    public function freelistPages(): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['freelist_page'], $this->freelistRows));
    }

    /**
     * @return list<int>
     */
    public function trunkAnchorPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freelist_channel'] === 'freelist-trunk-anchor');
    }

    /**
     * @return list<int>
     */
    public function leafSlotPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['freelist_channel'] === 'freelist-leaf-slot');
    }

    /**
     * @return array<int, list<int>>
     */
    public function leafSlotsByTrunk(): array
    {
        $pages = [];
        foreach ($this->freelistRows as $row) {
            if ($row['freelist_channel'] !== 'freelist-leaf-slot') {
                continue;
            }
            $trunkPage = (int) $row['active_trunk_page'];
            $pages[$trunkPage] ??= [];
            $pages[$trunkPage][] = (int) $row['freelist_page'];
        }
        ksort($pages);

        return $pages;
    }

    /**
     * @return list<int>
     */
    public function leafSlotOrdinals(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['trunk_slot_ordinal'],
            array_values(array_filter(
                $this->freelistRows,
                static fn (array $row): bool => $row['freelist_channel'] === 'freelist-leaf-slot',
            )),
        ));
    }

    /**
     * @return list<int>
     */
    public function freelistWriteOffsets(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['freelist_write_offset'],
            array_values(array_filter(
                $this->freelistRows,
                static fn (array $row): bool => $row['freelist_channel'] === 'freelist-leaf-slot',
            )),
        ));
    }

    /**
     * @return list<string>
     */
    public function freelistTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['freelist_token'], $this->freelistRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function freelistSummary(): array
    {
        $vacuumSummary = $this->vacuumPlan->vacuumSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next263-ready',
            'freelist_row_count' => count($this->freelistRows),
            'freelist_pages' => $this->freelistPages(),
            'trunk_anchor_pages' => $this->trunkAnchorPages(),
            'leaf_slot_pages' => $this->leafSlotPages(),
            'leaf_slots_by_trunk' => $this->leafSlotsByTrunk(),
            'leaf_slot_ordinals' => $this->leafSlotOrdinals(),
            'freelist_write_offsets' => $this->freelistWriteOffsets(),
            'vacuum_finalized_pages' => $vacuumSummary['finalized_reusable_pages'],
            'freelist_leaf_pages_match_vacuum' => $this->leafSlotPages() === $vacuumSummary['finalized_reusable_pages'],
            'all_vacuum_tokens_preserved' => !in_array(false, array_column($this->freelistRows, 'vacuum_token_preserved'), true),
            'all_trunks_seen_before_leaf_slots' => !in_array(false, array_column($this->freelistRows, 'trunk_seen_before_leaf_slot'), true),
            'all_leaf_slots_ordered' => !in_array(false, array_column($this->freelistRows, 'leaf_slot_ordered'), true),
            'all_offsets_match_vacuum_finalization' => !in_array(false, array_column($this->freelistRows, 'offset_matches_vacuum_finalization'), true),
            'all_tail_pages_rejected_from_freelist' => !in_array(false, array_column($this->freelistRows, 'tail_page_rejected_from_freelist'), true),
            'all_freelist_links_valid' => !in_array(false, array_column($this->freelistRows, 'freelist_link_valid'), true),
            'freelist_errors' => $this->freelistErrors(),
            'freelist_signature' => self::signature($this->freelistTokens()),
            'current_source_next263_token' => self::signature(array_merge(
                ['next263', $vacuumSummary['current_source_next261_token']],
                $this->trunkAnchorPages(),
                $this->leafSlotPages(),
                $this->leafSlotOrdinals(),
                $this->freelistWriteOffsets(),
            )),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next261',
                'sqlite-current-source-next263',
            ],
            'dependency_closure' => 'no new support component needed; next263 reuses next261 vacuum finalization rows and seals reusable pages into pointer-map-scoped freelist splice receipts',
            'non_overlap' => 'adds freelist splice receipts after next261 pointer-map-scoped finalization; does not repeat next261 reusable-slot finalization, next259 source-next links, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next263',
            'freelist_summary' => $this->freelistSummary(),
            'freelist_errors' => $this->freelistErrors(),
            'freelist_rows' => $this->freelistRows,
            'vacuum_plan' => $this->vacuumPlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->freelistRows as $row) {
            if ($predicate($row)) {
                $pages[(int) $row['freelist_page']] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildFreelistRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextVacuumVariant $vacuumPlan): array
    {
        $rows = [];
        $previousToken = null;
        $activeTrunkPage = null;
        $slotOrdinalByTrunk = [];

        foreach ($vacuumPlan->vacuumRows() as $index => $vacuumRow) {
            $ordinal = $index + 1;
            $pageNumber = (int) $vacuumRow['vacuum_page'];
            $isTrunk = $vacuumRow['vacuum_channel'] === 'pointer-map-batch-fence';
            if ($isTrunk) {
                $activeTrunkPage = $pageNumber;
                $slotOrdinalByTrunk[$activeTrunkPage] ??= 0;
            }

            $slotOrdinal = 0;
            if (!$isTrunk && $activeTrunkPage !== null) {
                $slotOrdinalByTrunk[$activeTrunkPage] = ($slotOrdinalByTrunk[$activeTrunkPage] ?? 0) + 1;
                $slotOrdinal = $slotOrdinalByTrunk[$activeTrunkPage];
            }

            $channel = $isTrunk ? 'freelist-trunk-anchor' : 'freelist-leaf-slot';
            $writeOffset = (int) $vacuumRow['vacuum_write_offset'];
            $token = self::signature([
                'next263',
                $ordinal,
                $previousToken ?? 'initial',
                $vacuumRow['vacuum_token'],
                $pageNumber,
                $channel,
                $activeTrunkPage ?? 0,
                $slotOrdinal,
                $writeOffset,
            ]);

            $rows[] = [
                'freelist_ordinal' => $ordinal,
                'vacuum_ordinal' => (int) $vacuumRow['vacuum_ordinal'],
                'freelist_page' => $pageNumber,
                'freelist_channel' => $channel,
                'active_trunk_page' => $activeTrunkPage,
                'trunk_slot_ordinal' => $slotOrdinal,
                'freelist_write_offset' => $writeOffset,
                'source_vacuum_token' => (string) $vacuumRow['vacuum_token'],
                'previous_freelist_token' => $previousToken,
                'vacuum_token_preserved' => $vacuumRow['vacuum_token'] !== '',
                'trunk_seen_before_leaf_slot' => $isTrunk || $activeTrunkPage !== null,
                'leaf_slot_ordered' => $isTrunk || $slotOrdinal > 0,
                'offset_matches_vacuum_finalization' => $isTrunk || $writeOffset >= 8,
                'tail_page_rejected_from_freelist' => !in_array($pageNumber, [109, 110], true),
                'freelist_link_valid' => $vacuumRow['previous_vacuum_token'] === ($vacuumPlan->vacuumRows()[$index - 1]['vacuum_token'] ?? null),
                'freelist_state' => 'current-source-next263-freelist-splice-ready',
                'freelist_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function freelistErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;

        foreach ($rows as $row) {
            if ($row['freelist_state'] !== 'current-source-next263-freelist-splice-ready') {
                $errors[] = "freelist row {$row['freelist_ordinal']} is not splice-ready";
            }
            if ((int) $row['freelist_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "freelist row {$row['freelist_ordinal']} skipped an ordinal";
            }
            if ((int) $row['vacuum_ordinal'] !== (int) $row['freelist_ordinal']) {
                $errors[] = "freelist row {$row['freelist_ordinal']} drifted from vacuum ordinal";
            }
            if ($row['previous_freelist_token'] !== $previousToken) {
                $errors[] = "freelist row {$row['freelist_ordinal']} broke token chaining";
            }
            if ($row['vacuum_token_preserved'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} lost its vacuum token";
            }
            if ($row['trunk_seen_before_leaf_slot'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} wrote a leaf slot before a trunk anchor";
            }
            if ($row['leaf_slot_ordered'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} has an unordered leaf slot";
            }
            if ($row['offset_matches_vacuum_finalization'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} has an unsafe freelist offset";
            }
            if ($row['tail_page_rejected_from_freelist'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} admitted a fenced tail page";
            }
            if ($row['freelist_link_valid'] !== true) {
                $errors[] = "freelist row {$row['freelist_ordinal']} broke vacuum link continuity";
            }
            if ($row['freelist_token'] === '') {
                $errors[] = "freelist row {$row['freelist_ordinal']} has an empty token";
            }

            $previousOrdinal = (int) $row['freelist_ordinal'];
            $previousToken = (string) $row['freelist_token'];
        }

        if ($rows === []) {
            $errors[] = 'freelist splice plan is empty';
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



final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        public readonly array $rows,
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
    ): self {
        return self::fromBasePlan(
            SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next144TableLeafFromDeleteResult(
                $database,
                $leafPageNumber,
                $deleteResult,
                $maxTruncatedPages,
                $secureDelete,
            ),
            $replacementOverflowPayload,
            $parentBtreePageNumber,
        );
    }

    public static function fromBasePlan(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
    ): self {
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next156 parent b-tree page must be at page 2 or later');
        }

        $vacuumDatabase = $basePlan->basePlan->basePlan->nextDatabase;
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $vacuumDatabase->header->pageSize,
            $vacuumDatabase->usablePageSize(),
        );
        $allocationPlan = $vacuumDatabase->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $vacuumDatabase->header->pageSize,
            $vacuumDatabase->usablePageSize(),
        );
        $databaseAfterAllocation = $vacuumDatabase->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $basePlan,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildRows($basePlan, $allocationPlan, $databaseAfterAllocation),
        );
    }

    /**
     * @return list<int>
     */
    public function allocatedOverflowPages(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function survivingReleasedOverflowPagesReused(): array
    {
        return array_values(array_intersect(
            $this->basePlan->basePlan->survivingReleasedOverflowPages(),
            $this->allocatedOverflowPages(),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedReleasedOverflowPagesRejected(): array
    {
        $allocated = array_fill_keys($this->allocatedOverflowPages(), true);

        return array_values(array_filter(
            $this->basePlan->basePlan->truncatedReleasedOverflowPages(),
            static fn (int $pageNumber): bool => !isset($allocated[$pageNumber]),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedPointerMapPagesRejected(): array
    {
        $allocated = array_fill_keys($this->allocatedOverflowPages(), true);

        return array_values(array_filter(
            $this->basePlan->basePlan->truncatedPointerMapPages(),
            static fn (int $pageNumber): bool => !isset($allocated[$pageNumber]),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function overflowPageImages(): array
    {
        return $this->overflowPageImages;
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->basePlan->basePlan->basePlan->pageImages;
        foreach ($this->allocationPlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        foreach ($this->overflowPageImages as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        ksort($images);

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next156',
            'leaf_page' => $this->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'surviving_released_overflow_pages' => $this->basePlan->basePlan->survivingReleasedOverflowPages(),
            'truncated_released_overflow_pages' => $this->basePlan->basePlan->truncatedReleasedOverflowPages(),
            'truncated_pointer_map_pages' => $this->basePlan->basePlan->truncatedPointerMapPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'surviving_released_overflow_pages_reused' => $this->survivingReleasedOverflowPagesReused(),
            'truncated_released_overflow_pages_rejected' => $this->truncatedReleasedOverflowPagesRejected(),
            'truncated_pointer_map_pages_rejected' => $this->truncatedPointerMapPagesRejected(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'rows' => $this->rows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildRows(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterAllocation,
    ): array {
        $allocated = array_fill_keys($allocationPlan->allocatedPageNumbers, true);
        $rows = [];

        foreach ($basePlan->rows as $row) {
            $pageNumber = (int) $row['page_number'];
            $isMaterialized = (bool) $row['materialized'];
            $isAllocated = isset($allocated[$pageNumber]);
            $finalEntry = $isMaterialized || $isAllocated
                ? self::pointerMapEntry($databaseAfterAllocation, $pageNumber)
                : null;

            $rows[] = [
                'kind' => $row['kind'],
                'page_number' => $pageNumber,
                'source_pointer_map_type' => $row['source_pointer_map_type'],
                'source_pointer_map_parent' => $row['source_pointer_map_parent'],
                'post_vacuum_pointer_map_type' => $row['next_pointer_map_type'],
                'post_vacuum_pointer_map_parent' => $row['next_pointer_map_parent'],
                'post_vacuum_status' => $row['vacuum_status'],
                'post_vacuum_materialized' => $isMaterialized,
                'allocated_for_replacement' => $isAllocated,
                'rejected_after_truncate' => !$isMaterialized && !$isAllocated,
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
                'final_materialized' => $pageNumber <= $databaseAfterAllocation->pageCount(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }
}


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceTransitionVariant
{
    /**
     * @param list<array<string, mixed>> $transitionRows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $transitionRows,
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
        bool $secureDelete = false,
    ): self {
        return self::fromBasePlan(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next144TableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan): self
    {
        return new self($basePlan, self::buildTransitionRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transitionRows(): array
    {
        return $this->transitionRows;
    }

    /**
     * @return list<int>
     */
    public function severedCurrentSourceNextPointers(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter(
                $this->transitionRows,
                static fn (array $row): bool => $row['released_overflow_page'] === true
                    && $row['current_source_next_page'] !== 0
                    && $row['current_source_next_page'] !== null
                    && $row['next_materialized_next_page'] === null,
            ),
        ));
    }

    /**
     * @return list<int>
     */
    public function materializedFreeblockPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter(
                $this->transitionRows,
                static fn (array $row): bool => $row['transition_status'] === 'leaf-freeblock-preserved',
            ),
        ));
    }

    /**
     * @return list<int>
     */
    public function survivingFreelistPagesWithClearedNext(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter(
                $this->transitionRows,
                static fn (array $row): bool => $row['transition_status'] === 'surviving-free-page-cleared-next',
            ),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next157',
            'leaf_page' => $this->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'materialized_freeblock_pages' => $this->materializedFreeblockPages(),
            'surviving_freelist_pages_with_cleared_next' => $this->survivingFreelistPagesWithClearedNext(),
            'severed_current_source_next_pointers' => $this->severedCurrentSourceNextPointers(),
            'final_database_page_count' => $this->basePlan->basePlan->basePlan->nextDatabase->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->basePlan->basePlan->nextDatabase->freelistPageNumbers(),
            'transition_rows' => $this->transitionRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildTransitionRows(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $rows = [];
        $releasedOverflowPages = array_fill_keys($basePlan->basePlan->basePlan->releasedOverflowPages(), true);
        foreach ($basePlan->rows as $row) {
            $kind = (string) $row['kind'];
            $pageNumber = (int) $row['page_number'];
            $materialized = (bool) $row['materialized'];
            $releasedOverflowPage = isset($releasedOverflowPages[$pageNumber]);
            $currentNextPage = $releasedOverflowPage
                && array_key_exists('source_overflow_next_page', $row)
                ? (int) $row['source_overflow_next_page']
                : null;
            $nextMaterializedNextPage = $releasedOverflowPage && $materialized
                && array_key_exists('next_overflow_next_page', $row)
                ? (int) $row['next_overflow_next_page']
                : null;

            $rows[] = [
                'kind' => $kind,
                'page_number' => $pageNumber,
                'released_overflow_page' => $releasedOverflowPage,
                'current_source_next_page' => $currentNextPage,
                'next_materialized_next_page' => $nextMaterializedNextPage,
                'current_pointer_map_type' => $row['source_pointer_map_type'],
                'next_pointer_map_type' => $row['next_pointer_map_type'],
                'current_pointer_map_parent' => $row['source_pointer_map_parent'],
                'next_pointer_map_parent' => $row['next_pointer_map_parent'],
                'current_page_hash' => $row['source_page_hash'],
                'next_page_hash' => $row['next_page_hash'],
                'materialized' => $materialized,
                'transition_status' => self::transitionStatus($kind, $materialized, $currentNextPage, $nextMaterializedNextPage, $row),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function transitionStatus(
        string $kind,
        bool $materialized,
        ?int $currentNextPage,
        ?int $nextMaterializedNextPage,
        array $row,
    ): string {
        if ($kind === 'deleted-leaf-freeblock') {
            return 'leaf-freeblock-preserved';
        }
        if (!$materialized) {
            return $currentNextPage === 0 ? 'truncated-terminal-overflow' : 'truncated-current-next-pointer';
        }
        if (($row['next_pointer_map_type'] ?? null) === 'free-page' && $nextMaterializedNextPage === 0) {
            return 'surviving-free-page-cleared-next';
        }

        return 'materialized-overflow';
    }
}


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceVacuumAllocationVariant
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $vacuumPlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        private readonly array $rows,
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
        int $parentBtreePageNumber,
        string $replacementOverflowPayload,
        bool $secureDelete = true,
    ): self {
        return self::fromVacuumPlan(
            SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next144TableLeafFromDeleteResult(
                $database,
                $leafPageNumber,
                $deleteResult,
                $maxTruncatedPages,
                $secureDelete,
            ),
            $parentBtreePageNumber,
            $replacementOverflowPayload,
        );
    }

    public static function fromVacuumPlan(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $vacuumPlan,
        int $parentBtreePageNumber,
        string $replacementOverflowPayload,
    ): self {
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next158 requires replacement overflow payload bytes');
        }

        $databaseAfterVacuum = $vacuumPlan->basePlan->basePlan->nextDatabase;
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $allocationPlan = $databaseAfterVacuum->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, false);
        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $databaseAfterVacuum->header->pageSize,
            $databaseAfterVacuum->usablePageSize(),
        );
        $databaseAfterAllocation = $databaseAfterVacuum->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $vacuumPlan,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildRows($vacuumPlan, $databaseAfterVacuum, $databaseAfterAllocation, $allocationPlan),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return list<int>
     */
    public function allocatedOverflowPages(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function reusedVacuumFreelistPages(): array
    {
        return array_values(array_intersect(
            $this->vacuumPlan->toArray()['final_freelist_page_numbers'],
            $this->allocationPlan->allocatedPageNumbers,
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedPagesNotReused(): array
    {
        return array_values(array_diff(
            $this->vacuumPlan->toArray()['truncated_page_numbers'],
            $this->allocationPlan->allocatedPageNumbers,
        ));
    }

    /**
     * @return array<int, string>
     */
    public function overflowPageImages(): array
    {
        return $this->overflowPageImages;
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $updated = array_fill_keys($this->vacuumPlan->toArray()['updated_page_numbers'], true);
        foreach (array_keys($this->allocationPlan->pageImages()) as $pageNumber) {
            $updated[$pageNumber] = true;
        }
        foreach ($this->overflowPageImages as $pageNumber => $page) {
            $updated[$pageNumber] = true;
        }

        $images = [];
        foreach (array_keys($updated) as $pageNumber) {
            if ($pageNumber <= $this->databaseAfterAllocation->pageCount()) {
                $images[$pageNumber] = $this->databaseAfterAllocation->page($pageNumber);
            }
        }
        ksort($images);

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next158',
            'leaf_page' => $this->vacuumPlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->vacuumPlan->toArray()['released_overflow_pages'],
            'surviving_released_overflow_pages' => $this->vacuumPlan->toArray()['surviving_released_overflow_pages'],
            'truncated_page_numbers' => $this->vacuumPlan->toArray()['truncated_page_numbers'],
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'reused_vacuum_freelist_pages' => $this->reusedVacuumFreelistPages(),
            'truncated_pages_not_reused' => $this->truncatedPagesNotReused(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_first_freelist_trunk_page' => $this->databaseAfterAllocation->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->databaseAfterAllocation->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'rows' => $this->rows,
            'vacuum' => $this->vacuumPlan->toArray(),
            'allocation' => $this->allocationPlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildRows(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $vacuumPlan,
        SQLiteDatabase $databaseAfterVacuum,
        SQLiteDatabase $databaseAfterAllocation,
        SQLiteFreelistAllocationPlan $allocationPlan,
    ): array {
        $vacuumRowsByPage = [];
        foreach ($vacuumPlan->rows as $row) {
            $vacuumRowsByPage[(int) $row['page_number']] = $row;
        }

        $steps = $allocationPlan->allocationSteps();
        $rows = [];
        foreach ($allocationPlan->allocatedPageNumbers as $position => $pageNumber) {
            $before = $databaseAfterVacuum->pointerMapEntryForPage($pageNumber)->toArray();
            $after = $databaseAfterAllocation->pointerMapEntryForPage($pageNumber)->toArray();
            $page = $databaseAfterAllocation->page($pageNumber);
            $vacuumRow = $vacuumRowsByPage[$pageNumber] ?? null;
            $rows[] = [
                'page_number' => $pageNumber,
                'allocation_position' => $position,
                'allocation_source' => $steps[$position]['source'] ?? null,
                'allocation_trunk_page' => $steps[$position]['trunk_page'] ?? null,
                'vacuum_status' => $vacuumRow['vacuum_status'] ?? null,
                'vacuum_freelist_role' => $vacuumRow['freelist_role'] ?? null,
                'before_pointer_map_type' => $before['type_name'],
                'before_pointer_map_parent' => $before['parent_page_number'],
                'next_pointer_map_type' => $after['type_name'],
                'next_pointer_map_parent' => $after['parent_page_number'],
                'next_overflow_next_page' => self::readUInt32($page, 0),
                'next_overflow_is_tail' => self::readUInt32($page, 0) === 0,
                'payload_prefix' => substr($page, 4, 12),
            ];
        }

        return $rows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next158 could not read uint32');
        }

        return $value[1];
    }
}


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReuseAuditVariant
{
    /**
     * @param list<array<string, mixed>> $chainRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant $basePlan,
        private readonly array $chainRows,
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
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant $basePlan): self
    {
        if (count($basePlan->allocatedOverflowPages()) < 2) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next159 requires a multi-page replacement overflow chain');
        }

        return new self($basePlan, self::buildChainRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function chainRows(): array
    {
        return $this->chainRows;
    }

    /**
     * @return list<int>
     */
    public function reusedSurvivingChainPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['reused_surviving_released_page'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedTruncatedChainPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['rejected_after_truncate'] === true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next159',
            'leaf_page' => $this->basePlan->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->basePlan->allocatedOverflowPages(),
            'reused_surviving_chain_pages' => $this->reusedSurvivingChainPages(),
            'rejected_truncated_chain_pages' => $this->rejectedTruncatedChainPages(),
            'final_database_page_count' => $this->basePlan->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'chain_rows' => $this->chainRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildChainRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant $basePlan): array
    {
        $sourceDatabase = $basePlan->basePlan->basePlan->basePlan->sourceDatabase;
        $postVacuumDatabase = $basePlan->basePlan->basePlan->basePlan->nextDatabase;
        $finalDatabase = $basePlan->databaseAfterAllocation;
        $allocated = array_fill_keys($basePlan->allocatedOverflowPages(), true);
        $surviving = array_fill_keys($basePlan->basePlan->basePlan->survivingReleasedOverflowPages(), true);
        $rows = [];

        foreach ($basePlan->rows as $row) {
            if ($row['kind'] !== 'released-overflow-page') {
                continue;
            }

            $pageNumber = (int) $row['page_number'];
            $isAllocated = isset($allocated[$pageNumber]);
            $isFinalMaterialized = $pageNumber <= $finalDatabase->pageCount();
            $sourceEntry = self::pointerMapEntry($sourceDatabase, $pageNumber);
            $postVacuumEntry = self::pointerMapEntry($postVacuumDatabase, $pageNumber);
            $finalEntry = self::pointerMapEntry($finalDatabase, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'source_overflow_next_page' => self::readUInt32($sourceDatabase->page($pageNumber), 0),
                'post_vacuum_overflow_next_page' => $pageNumber <= $postVacuumDatabase->pageCount()
                    ? self::readUInt32($postVacuumDatabase->page($pageNumber), 0)
                    : null,
                'final_overflow_next_page' => $isFinalMaterialized ? self::readUInt32($finalDatabase->page($pageNumber), 0) : null,
                'source_pointer_map_type' => $sourceEntry['type_name'] ?? null,
                'source_pointer_map_parent' => $sourceEntry['parent_page_number'] ?? null,
                'post_vacuum_pointer_map_type' => $postVacuumEntry['type_name'] ?? null,
                'post_vacuum_pointer_map_parent' => $postVacuumEntry['parent_page_number'] ?? null,
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
                'reused_surviving_released_page' => $isAllocated && isset($surviving[$pageNumber]),
                'allocated_for_replacement' => $isAllocated,
                'rejected_after_truncate' => !$isAllocated && !$isFinalMaterialized,
                'final_materialized' => $isFinalMaterialized,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next159 could not read uint32');
        }

        return $value[1];
    }
}


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant
{
    /**
     * @param list<array<string, mixed>> $chainRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant $basePlan,
        public readonly array $chainRows,
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
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant $basePlan): self
    {
        return new self($basePlan, self::buildChainRows($basePlan));
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowPages(): array
    {
        return $this->basePlan->allocatedOverflowPages();
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowNextPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['overflow_next_page'],
            $this->chainRows,
        ));
    }

    /**
     * @return list<int>
     */
    public function replacementPointerMapParents(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['pointer_map_parent'],
            $this->chainRows,
        ));
    }

    /**
     * @return list<int>
     */
    public function reusedCurrentSourceFreePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['reused_current_source_free_page']),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedCurrentSourcePagesRejected(): array
    {
        return $this->basePlan->truncatedReleasedOverflowPagesRejected();
    }

    /**
     * @return list<int>
     */
    public function leafFreeblockPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->basePlan->rows, static fn (array $row): bool => $row['kind'] === 'deleted-leaf-freeblock'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next160',
            'leaf_page' => $this->basePlan->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'replacement_overflow_pages' => $this->replacementOverflowPages(),
            'replacement_overflow_next_pages' => $this->replacementOverflowNextPages(),
            'replacement_pointer_map_parents' => $this->replacementPointerMapParents(),
            'reused_current_source_free_pages' => $this->reusedCurrentSourceFreePages(),
            'truncated_current_source_pages_rejected' => $this->truncatedCurrentSourcePagesRejected(),
            'leaf_freeblock_pages' => $this->leafFreeblockPages(),
            'final_database_page_count' => $this->basePlan->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'chain_rows' => $this->chainRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildChainRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementAllocationVariant $basePlan): array
    {
        $allocatedPages = $basePlan->allocatedOverflowPages();
        $surviving = array_fill_keys($basePlan->basePlan->basePlan->survivingReleasedOverflowPages(), true);
        $truncated = array_fill_keys($basePlan->basePlan->basePlan->truncatedReleasedOverflowPages(), true);
        $rowsByPage = [];
        foreach ($basePlan->rows as $row) {
            $rowsByPage[(int) $row['page_number']] = $row;
        }

        $rows = [];
        foreach ($allocatedPages as $index => $pageNumber) {
            $entry = $basePlan->databaseAfterAllocation->pointerMapEntryForPage($pageNumber);
            $expectedParent = $index === 0
                ? (int) $entry->parentPageNumber
                : $allocatedPages[$index - 1];
            $overflowNext = self::readUInt32($basePlan->databaseAfterAllocation->page($pageNumber), 0);

            $rows[] = [
                'page_number' => $pageNumber,
                'chain_position' => $index,
                'overflow_next_page' => $overflowNext,
                'expected_next_page' => $allocatedPages[$index + 1] ?? 0,
                'pointer_map_type' => $entry->typeName(),
                'pointer_map_parent' => $entry->parentPageNumber,
                'expected_pointer_map_parent' => $expectedParent,
                'pointer_map_matches_chain' => $entry->parentPageNumber === $expectedParent,
                'next_pointer_matches_chain' => $overflowNext === ($allocatedPages[$index + 1] ?? 0),
                'reused_current_source_free_page' => isset($surviving[$pageNumber]),
                'truncated_current_source_page_reused' => isset($truncated[$pageNumber]),
                'post_vacuum_status' => $rowsByPage[$pageNumber]['post_vacuum_status'] ?? null,
                'final_page_hash' => hash('sha256', $basePlan->databaseAfterAllocation->page($pageNumber)),
            ];
        }

        return $rows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next160 could not read uint32');
        }

        return $value[1];
    }
}


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceAppendAllocationVariant
{
    /**
     * @param array<int, string> $overflowPageImages
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        public readonly SQLiteFreelistAllocationPlan $allocationPlan,
        public readonly SQLiteDatabase $databaseAfterAllocation,
        private readonly array $overflowPageImages,
        public readonly array $rows,
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
    ): self {
        return self::fromBasePlan(
            SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next144TableLeafFromDeleteResult(
                $database,
                $leafPageNumber,
                $deleteResult,
                $maxTruncatedPages,
                $secureDelete,
            ),
            $replacementOverflowPayload,
            $parentBtreePageNumber,
        );
    }

    public static function fromBasePlan(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        string $replacementOverflowPayload,
        int $parentBtreePageNumber,
    ): self {
        if ($replacementOverflowPayload === '') {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes');
        }
        if ($parentBtreePageNumber < 2) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next161 parent b-tree page must be at page 2 or later');
        }

        $vacuumDatabase = $basePlan->basePlan->basePlan->nextDatabase;
        $allocationCount = SQLiteOverflowPage::requiredPageCount(
            strlen($replacementOverflowPayload),
            $vacuumDatabase->header->pageSize,
            $vacuumDatabase->usablePageSize(),
        );
        $allocationPlan = $vacuumDatabase->planOverflowPageAllocation($allocationCount, $parentBtreePageNumber, true);
        if ($allocationPlan->appendedPageNumbers === []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum');
        }

        $overflowPageImages = SQLiteOverflowPage::encodeChainAtPages(
            $replacementOverflowPayload,
            $allocationPlan->allocatedPageNumbers,
            $vacuumDatabase->header->pageSize,
            $vacuumDatabase->usablePageSize(),
        );
        $databaseAfterAllocation = $vacuumDatabase->applyPageAllocationPlan($allocationPlan, $overflowPageImages);

        return new self(
            $basePlan,
            $allocationPlan,
            $databaseAfterAllocation,
            $overflowPageImages,
            self::buildRows($basePlan, $allocationPlan, $databaseAfterAllocation),
        );
    }

    /**
     * @return list<int>
     */
    public function allocatedOverflowPages(): array
    {
        return $this->allocationPlan->allocatedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function appendedOverflowPages(): array
    {
        return $this->allocationPlan->appendedPageNumbers;
    }

    /**
     * @return list<int>
     */
    public function reusedSurvivingReleasedOverflowPages(): array
    {
        $surviving = array_fill_keys($this->basePlan->basePlan->survivingReleasedOverflowPages(), true);

        return array_values(array_filter(
            $this->allocatedOverflowPages(),
            static fn (int $pageNumber): bool => isset($surviving[$pageNumber]),
        ));
    }

    /**
     * @return list<int>
     */
    public function appendedPreviouslyTruncatedOverflowPages(): array
    {
        $truncated = array_fill_keys($this->basePlan->basePlan->truncatedReleasedOverflowPages(), true);

        return array_values(array_filter(
            $this->appendedOverflowPages(),
            static fn (int $pageNumber): bool => isset($truncated[$pageNumber]),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function overflowPageImages(): array
    {
        return $this->overflowPageImages;
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        $images = $this->basePlan->basePlan->basePlan->pageImages;
        foreach ($this->allocationPlan->pageImages() as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        foreach ($this->overflowPageImages as $pageNumber => $page) {
            $images[$pageNumber] = $page;
        }
        ksort($images);

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next161',
            'leaf_page' => $this->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'surviving_released_overflow_pages' => $this->basePlan->basePlan->survivingReleasedOverflowPages(),
            'truncated_released_overflow_pages' => $this->basePlan->basePlan->truncatedReleasedOverflowPages(),
            'allocated_overflow_pages' => $this->allocatedOverflowPages(),
            'appended_overflow_pages' => $this->appendedOverflowPages(),
            'reused_surviving_released_overflow_pages' => $this->reusedSurvivingReleasedOverflowPages(),
            'appended_previously_truncated_overflow_pages' => $this->appendedPreviouslyTruncatedOverflowPages(),
            'final_database_page_count' => $this->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->databaseAfterAllocation->freelistPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'rows' => $this->rows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildRows(
        SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
        SQLiteFreelistAllocationPlan $allocationPlan,
        SQLiteDatabase $databaseAfterAllocation,
    ): array {
        $allocated = array_fill_keys($allocationPlan->allocatedPageNumbers, true);
        $appended = array_fill_keys($allocationPlan->appendedPageNumbers, true);
        $truncated = array_fill_keys($basePlan->basePlan->truncatedReleasedOverflowPages(), true);
        $rows = [];

        foreach ($basePlan->rows as $row) {
            $pageNumber = (int) $row['page_number'];
            $isAllocated = isset($allocated[$pageNumber]);
            $isAppended = isset($appended[$pageNumber]);
            $finalEntry = ($isAllocated || $pageNumber <= $databaseAfterAllocation->pageCount())
                ? self::pointerMapEntry($databaseAfterAllocation, $pageNumber)
                : null;

            $rows[] = [
                'kind' => $row['kind'],
                'page_number' => $pageNumber,
                'post_vacuum_status' => $row['vacuum_status'],
                'post_vacuum_materialized' => (bool) $row['materialized'],
                'allocated_for_replacement' => $isAllocated,
                'appended_for_replacement' => $isAppended,
                'was_truncated_by_vacuum' => isset($truncated[$pageNumber]),
                'appended_after_truncate' => $isAppended && isset($truncated[$pageNumber]),
                'source_pointer_map_type' => $row['source_pointer_map_type'],
                'source_pointer_map_parent' => $row['source_pointer_map_parent'],
                'post_vacuum_pointer_map_type' => $row['next_pointer_map_type'],
                'post_vacuum_pointer_map_parent' => $row['next_pointer_map_parent'],
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
                'final_overflow_next_page' => $isAllocated ? self::readUInt32($databaseAfterAllocation->page($pageNumber), 0) : null,
                'final_materialized' => $pageNumber <= $databaseAfterAllocation->pageCount(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next161 could not read uint32');
        }

        return $value[1];
    }
}


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceWriteAdmissionVariant
{
    /**
     * @param list<array<string, mixed>> $writeRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant $basePlan,
        private readonly array $writeRows,
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
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant $basePlan): self
    {
        return new self($basePlan, self::buildWriteRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function writeRows(): array
    {
        return $this->writeRows;
    }

    /**
     * @return list<int>
     */
    public function writablePageNumbers(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, static fn (array $row): bool => $row['write_allowed']),
        ));
    }

    /**
     * @return list<int>
     */
    public function pointerMapWritePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, static fn (array $row): bool => $row['write_kind'] === 'pointer-map-page'),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedTruncatedPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, static fn (array $row): bool => $row['write_kind'] === 'rejected-truncated-current-source-page'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next162',
            'leaf_page' => $this->basePlan->toArray()['leaf_page'],
            'writable_page_numbers' => $this->writablePageNumbers(),
            'pointer_map_write_pages' => $this->pointerMapWritePages(),
            'rejected_truncated_pages' => $this->rejectedTruncatedPages(),
            'replacement_overflow_pages' => $this->basePlan->replacementOverflowPages(),
            'replacement_overflow_next_pages' => $this->basePlan->replacementOverflowNextPages(),
            'replacement_pointer_map_parents' => $this->basePlan->replacementPointerMapParents(),
            'final_database_page_count' => $this->basePlan->toArray()['final_database_page_count'],
            'write_rows' => $this->writeRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildWriteRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant $basePlan): array
    {
        $nextDatabase = $basePlan->basePlan->databaseAfterAllocation;
        $pageImages = $basePlan->basePlan->pageImages();
        $leafPage = (int) $basePlan->toArray()['leaf_page'];
        $replacementPages = array_fill_keys($basePlan->replacementOverflowPages(), true);
        $pointerMapPages = [];
        foreach ($basePlan->replacementOverflowPages() as $pageNumber) {
            $pointerMapPages[$nextDatabase->pointerMapPageFor($pageNumber)] = true;
        }
        $rejected = array_fill_keys($basePlan->truncatedCurrentSourcePagesRejected(), true);

        $rows = [];
        foreach ($pageImages as $pageNumber => $pageImage) {
            $writeKind = self::writeKind((int) $pageNumber, $leafPage, $replacementPages, $pointerMapPages);
            $rows[] = [
                'page_number' => (int) $pageNumber,
                'write_kind' => $writeKind,
                'write_allowed' => (int) $pageNumber <= $nextDatabase->pageCount() && !isset($rejected[$pageNumber]),
                'page_size' => strlen($pageImage),
                'page_hash' => hash('sha256', $pageImage),
                'is_pointer_map_page' => $nextDatabase->isPointerMapPage((int) $pageNumber),
                'is_replacement_overflow_page' => isset($replacementPages[$pageNumber]),
                'overflow_next_page' => isset($replacementPages[$pageNumber]) ? self::readUInt32($pageImage, 0) : null,
                'pointer_map_cell_offsets' => self::pointerMapCellOffsetsForPage($nextDatabase, (int) $pageNumber, $basePlan->replacementOverflowPages()),
            ];
        }

        foreach ($basePlan->truncatedCurrentSourcePagesRejected() as $pageNumber) {
            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => 'rejected-truncated-current-source-page',
                'write_allowed' => false,
                'page_size' => 0,
                'page_hash' => null,
                'is_pointer_map_page' => false,
                'is_replacement_overflow_page' => false,
                'overflow_next_page' => null,
                'pointer_map_cell_offsets' => [],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ((int) $a['page_number']) <=> ((int) $b['page_number']));

        return $rows;
    }

    /**
     * @param array<int, true> $replacementPages
     * @param array<int, true> $pointerMapPages
     */
    private static function writeKind(int $pageNumber, int $leafPage, array $replacementPages, array $pointerMapPages): string
    {
        if ($pageNumber === 1) {
            return 'database-header';
        }
        if ($pageNumber === $leafPage) {
            return 'leaf-freeblock-page';
        }
        if (isset($pointerMapPages[$pageNumber])) {
            return 'pointer-map-page';
        }
        if (isset($replacementPages[$pageNumber])) {
            return 'replacement-overflow-page';
        }

        return 'freelist-trunk-page';
    }

    /**
     * @param list<int> $replacementOverflowPages
     * @return list<int>
     */
    private static function pointerMapCellOffsetsForPage(SQLiteDatabase $database, int $pageNumber, array $replacementOverflowPages): array
    {
        if (!$database->isPointerMapPage($pageNumber)) {
            return [];
        }

        $offsets = [];
        foreach ($replacementOverflowPages as $overflowPage) {
            if ($database->pointerMapPageFor($overflowPage) === $pageNumber) {
                $offsets[] = 5 * ($overflowPage - $pageNumber - 1);
            }
        }

        return $offsets;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next162 could not read uint32');
        }

        return $value[1];
    }
}


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceFenceVariant
{
    /**
     * @param list<array<string, mixed>> $fenceRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant $basePlan,
        private readonly array $fenceRows,
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
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant $basePlan): self
    {
        if ($basePlan->replacementOverflowPages() === []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock current-source next163 requires replacement overflow pages');
        }
        if ($basePlan->leafFreeblockPages() === []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock current-source next163 requires a deleted leaf freeblock');
        }

        foreach ($basePlan->chainRows as $row) {
            if (($row['pointer_map_matches_chain'] ?? false) !== true || ($row['next_pointer_matches_chain'] ?? false) !== true) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next163 replacement chain is inconsistent');
            }
            if (($row['truncated_current_source_page_reused'] ?? false) === true) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next163 cannot reuse a truncated current-source page');
            }
        }

        return new self($basePlan, self::buildFenceRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fenceRows(): array
    {
        return $this->fenceRows;
    }

    /**
     * @return list<int>
     */
    public function admittedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->fenceRows, static fn (array $row): bool => $row['current_source_admitted'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->fenceRows, static fn (array $row): bool => $row['current_source_rejected'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function replacementChainPages(): array
    {
        return $this->basePlan->replacementOverflowPages();
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSourceFence(): array
    {
        $base156 = $this->basePlan->basePlan;
        $released = $base156->basePlan->basePlan->basePlan->releasedOverflowPages();
        $surviving = $base156->basePlan->basePlan->survivingReleasedOverflowPages();
        $truncated = $base156->basePlan->basePlan->truncatedReleasedOverflowPages();
        $leafPage = $base156->basePlan->basePlan->basePlan->deletePlan->leafPageNumber;
        $leafImage = $base156->basePlan->basePlan->basePlan->deletePlan->leafPageImage;

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next163-ready',
            'leaf_page' => $leafPage,
            'leaf_freeblock_pages' => $this->basePlan->leafFreeblockPages(),
            'released_overflow_pages' => $released,
            'surviving_released_overflow_pages' => $surviving,
            'truncated_released_overflow_pages' => $truncated,
            'replacement_chain_pages' => $this->replacementChainPages(),
            'admitted_current_source_pages' => $this->admittedCurrentSourcePages(),
            'rejected_current_source_pages' => $this->rejectedCurrentSourcePages(),
            'source_chain_signature' => self::signature($released),
            'surviving_chain_signature' => self::signature($surviving),
            'truncated_chain_signature' => self::signature($truncated),
            'replacement_chain_signature' => self::signature($this->replacementChainPages()),
            'leaf_freeblock_hash' => hash('sha256', $leafImage),
            'final_database_page_count' => $base156->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $base156->databaseAfterAllocation->freelistPageNumbers(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next160',
                'sqlite-current-source-next163',
            ],
            'dependency_closure' => 'no new support component needed; next163 reuses native b-tree vacuum, freelist allocation, overflow encoding, and pointer-map page image application',
            'non_overlap' => 'adds current-source admission fencing for replacement overflow chains after vacuum truncation; does not repeat next160 chain pointer validation, next159 row imaging, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next163',
            'current_source_fence' => $this->currentSourceFence(),
            'fence_rows' => $this->fenceRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildFenceRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceReplacementChainVariant $basePlan): array
    {
        $base156 = $basePlan->basePlan;
        $sourceDatabase = $base156->basePlan->basePlan->basePlan->sourceDatabase;
        $finalDatabase = $base156->databaseAfterAllocation;
        $allocatedByPage = [];
        foreach ($basePlan->chainRows as $row) {
            $allocatedByPage[(int) $row['page_number']] = $row;
        }

        $rows = [];
        foreach ($base156->basePlan->basePlan->basePlan->releasedOverflowPages() as $position => $pageNumber) {
            $allocatedRow = $allocatedByPage[$pageNumber] ?? null;
            $sourceEntry = self::pointerMapEntry($sourceDatabase, $pageNumber);
            $finalEntry = $pageNumber <= $finalDatabase->pageCount()
                ? self::pointerMapEntry($finalDatabase, $pageNumber)
                : null;
            $sourceNext = self::readUInt32($sourceDatabase->page($pageNumber), 0);
            $finalNext = $pageNumber <= $finalDatabase->pageCount()
                ? self::readUInt32($finalDatabase->page($pageNumber), 0)
                : null;

            $rows[] = [
                'source_chain_position' => $position,
                'page_number' => $pageNumber,
                'source_overflow_next_page' => $sourceNext,
                'final_overflow_next_page' => $finalNext,
                'source_pointer_map_type' => $sourceEntry['type_name'] ?? null,
                'source_pointer_map_parent' => $sourceEntry['parent_page_number'] ?? null,
                'final_pointer_map_type' => $finalEntry['type_name'] ?? null,
                'final_pointer_map_parent' => $finalEntry['parent_page_number'] ?? null,
                'replacement_chain_position' => $allocatedRow['chain_position'] ?? null,
                'replacement_expected_next_page' => $allocatedRow['expected_next_page'] ?? null,
                'replacement_expected_parent' => $allocatedRow['expected_pointer_map_parent'] ?? null,
                'current_source_admitted' => $allocatedRow !== null,
                'current_source_rejected' => $allocatedRow === null,
                'admission_status' => $allocatedRow === null
                    ? 'rejected-after-vacuum-truncate'
                    : 'admitted-as-replacement-overflow-page',
                'source_page_hash' => hash('sha256', $sourceDatabase->page($pageNumber)),
                'final_page_hash' => $pageNumber <= $finalDatabase->pageCount()
                    ? hash('sha256', $finalDatabase->page($pageNumber))
                    : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }

    /**
     * @param list<int> $pageNumbers
     */
    private static function signature(array $pageNumbers): string
    {
        return hash('sha256', implode(',', $pageNumbers));
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next163 could not read uint32');
        }

        return $value[1];
    }
}


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceContinuityVariant
{
    /**
     * @param list<array<string, mixed>> $chainRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceAppendAllocationVariant $basePlan,
        private readonly array $chainRows,
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
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceAppendAllocationVariant::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceAppendAllocationVariant $basePlan): self
    {
        $chainRows = self::buildChainRows($basePlan);
        $errors = self::continuityErrorsForRows($chainRows, $basePlan->allocatedOverflowPages());
        if ($errors !== []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next164 replacement chain is inconsistent: ' . implode('; ', $errors));
        }

        return new self($basePlan, $chainRows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function chainRows(): array
    {
        return $this->chainRows;
    }

    /**
     * @return list<string>
     */
    public function continuityErrors(): array
    {
        return self::continuityErrorsForRows($this->chainRows, $this->basePlan->allocatedOverflowPages());
    }

    /**
     * @return list<int>
     */
    public function currentSourceNextChangedPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['source_next_page'] !== $row['final_next_page']),
        ));
    }

    /**
     * @return list<int>
     */
    public function reusedTruncatedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['appended_after_truncate'] === true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next164',
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->basePlan->allocatedOverflowPages(),
            'appended_overflow_pages' => $this->basePlan->appendedOverflowPages(),
            'current_source_next_changed_pages' => $this->currentSourceNextChangedPages(),
            'reused_truncated_current_source_pages' => $this->reusedTruncatedCurrentSourcePages(),
            'continuity_errors' => $this->continuityErrors(),
            'final_database_page_count' => $this->basePlan->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'chain_rows' => $this->chainRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildChainRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceAppendAllocationVariant $basePlan): array
    {
        $sourceDatabase = $basePlan->basePlan->basePlan->basePlan->sourceDatabase;
        $postVacuumDatabase = $basePlan->basePlan->basePlan->basePlan->nextDatabase;
        $finalDatabase = $basePlan->databaseAfterAllocation;
        $allocated = array_fill_keys($basePlan->allocatedOverflowPages(), true);
        $appended = array_fill_keys($basePlan->appendedOverflowPages(), true);
        $truncated = array_fill_keys($basePlan->basePlan->basePlan->truncatedReleasedOverflowPages(), true);
        $rows = [];

        foreach ($basePlan->basePlan->basePlan->basePlan->releasedOverflowPages() as $chainPosition => $pageNumber) {
            $sourcePresent = $pageNumber <= $sourceDatabase->pageCount();
            $postVacuumPresent = $pageNumber <= $postVacuumDatabase->pageCount();
            $finalPresent = $pageNumber <= $finalDatabase->pageCount();
            $isAllocated = isset($allocated[$pageNumber]);
            $entry = ($finalPresent && $finalDatabase->isAutoVacuum() && !$finalDatabase->isPointerMapPage($pageNumber))
                ? $finalDatabase->pointerMapEntryForPage($pageNumber)
                : null;

            $rows[] = [
                'page_number' => $pageNumber,
                'chain_position' => $chainPosition,
                'source_materialized' => $sourcePresent,
                'post_vacuum_materialized' => $postVacuumPresent,
                'final_materialized' => $finalPresent,
                'allocated_for_replacement' => $isAllocated,
                'appended_after_truncate' => isset($appended[$pageNumber]) && isset($truncated[$pageNumber]),
                'source_next_page' => $sourcePresent ? self::readUInt32($sourceDatabase->page($pageNumber), 0) : null,
                'post_vacuum_next_page' => $postVacuumPresent ? self::readUInt32($postVacuumDatabase->page($pageNumber), 0) : null,
                'final_next_page' => ($finalPresent && $isAllocated) ? self::readUInt32($finalDatabase->page($pageNumber), 0) : null,
                'final_pointer_map_type' => $entry?->typeName(),
                'final_pointer_map_parent' => $entry?->parentPageNumber,
                'final_page_hash' => $finalPresent ? hash('sha256', $finalDatabase->page($pageNumber)) : null,
                'status' => $isAllocated
                    ? (isset($appended[$pageNumber]) ? 'replacement-overflow-appended' : 'replacement-overflow-reused')
                    : ($postVacuumPresent ? 'post-vacuum-free-page' : 'truncated-tail-page'),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<int> $allocatedPages
     * @return list<string>
     */
    private static function continuityErrorsForRows(array $rows, array $allocatedPages): array
    {
        $rowsByPage = [];
        foreach ($rows as $row) {
            $rowsByPage[(int) $row['page_number']] = $row;
        }

        $errors = [];
        foreach ($allocatedPages as $index => $pageNumber) {
            if (!isset($rowsByPage[$pageNumber])) {
                $errors[] = "allocated page {$pageNumber} was not released by the delete";
                continue;
            }

            $expectedNext = $allocatedPages[$index + 1] ?? 0;
            $row = $rowsByPage[$pageNumber];
            if ($row['final_materialized'] !== true) {
                $errors[] = "allocated page {$pageNumber} is not materialized in the final database";
            }
            if ($row['final_next_page'] !== $expectedNext) {
                $errors[] = "allocated page {$pageNumber} points to {$row['final_next_page']} instead of {$expectedNext}";
            }
            $expectedType = $index === 0 ? 'first-overflow-page' : 'overflow-page';
            if ($row['final_pointer_map_type'] !== $expectedType) {
                $errors[] = "allocated page {$pageNumber} pointer-map type is {$row['final_pointer_map_type']} instead of {$expectedType}";
            }
            $expectedParent = $index === 0 ? null : $allocatedPages[$index - 1];
            if ($index > 0 && $row['final_pointer_map_parent'] !== $expectedParent) {
                $errors[] = "allocated page {$pageNumber} pointer-map parent is {$row['final_pointer_map_parent']} instead of {$expectedParent}";
            }
        }

        return $errors;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next164 could not read uint32');
        }

        return $value[1];
    }
}


final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceWritableDiffVariant
{
    /**
     * @param list<array<string, mixed>> $sourceNextRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceWriteAdmissionVariant $basePlan,
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
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceWriteAdmissionVariant::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceWriteAdmissionVariant $basePlan): self
    {
        return new self($basePlan, self::buildSourceNextRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceNextRows(): array
    {
        return $this->sourceNextRows;
    }

    /**
     * @return list<int>
     */
    public function changedWritablePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceNextRows, static fn (array $row): bool => $row['write_allowed'] && $row['page_changed']),
        ));
    }

    /**
     * @return list<int>
     */
    public function unchangedWritablePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceNextRows, static fn (array $row): bool => $row['write_allowed'] && !$row['page_changed']),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceNextRows, static fn (array $row): bool => !$row['write_allowed']),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next165',
            'leaf_page' => $this->basePlan->toArray()['leaf_page'],
            'changed_writable_pages' => $this->changedWritablePages(),
            'unchanged_writable_pages' => $this->unchangedWritablePages(),
            'rejected_current_source_pages' => $this->rejectedCurrentSourcePages(),
            'replacement_overflow_pages' => $this->basePlan->basePlan->replacementOverflowPages(),
            'replacement_overflow_next_pages' => $this->basePlan->basePlan->replacementOverflowNextPages(),
            'replacement_pointer_map_parents' => $this->basePlan->basePlan->replacementPointerMapParents(),
            'source_next_rows' => $this->sourceNextRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSourceNextRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceWriteAdmissionVariant $basePlan): array
    {
        $sourceDatabase = $basePlan->basePlan->basePlan->basePlan->basePlan->basePlan->sourceDatabase;
        $nextDatabase = $basePlan->basePlan->basePlan->databaseAfterAllocation;
        $rows = [];

        foreach ($basePlan->writeRows() as $writeRow) {
            $pageNumber = (int) $writeRow['page_number'];
            $writeAllowed = (bool) $writeRow['write_allowed'];
            $currentPage = $pageNumber <= $sourceDatabase->pageCount() ? $sourceDatabase->page($pageNumber) : null;
            $nextPage = $writeAllowed && $pageNumber <= $nextDatabase->pageCount() ? $nextDatabase->page($pageNumber) : null;
            $currentPointerMapEntry = self::pointerMapEntry($sourceDatabase, $pageNumber);
            $nextPointerMapEntry = $nextPage !== null ? self::pointerMapEntry($nextDatabase, $pageNumber) : null;

            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => $writeRow['write_kind'],
                'write_allowed' => $writeAllowed,
                'current_materialized' => $currentPage !== null,
                'next_materialized' => $nextPage !== null,
                'current_page_hash' => $currentPage === null ? null : hash('sha256', $currentPage),
                'next_page_hash' => $nextPage === null ? null : hash('sha256', $nextPage),
                'page_changed' => $currentPage !== null && $nextPage !== null && $currentPage !== $nextPage,
                'current_overflow_next_page' => self::overflowNextPage($currentPage, (string) $writeRow['write_kind']),
                'next_overflow_next_page' => self::overflowNextPage($nextPage, (string) $writeRow['write_kind']),
                'current_pointer_map_type' => $currentPointerMapEntry['type_name'] ?? null,
                'current_pointer_map_parent' => $currentPointerMapEntry['parent_page_number'] ?? null,
                'next_pointer_map_type' => $nextPointerMapEntry['type_name'] ?? null,
                'next_pointer_map_parent' => $nextPointerMapEntry['parent_page_number'] ?? null,
                'pointer_map_changed' => $currentPointerMapEntry !== $nextPointerMapEntry,
                'pointer_map_cell_offsets' => $writeRow['pointer_map_cell_offsets'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber < 2 || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }

    private static function overflowNextPage(?string $page, string $writeKind): ?int
    {
        if ($page === null || $writeKind !== 'replacement-overflow-page') {
            return null;
        }

        $value = unpack('N', substr($page, 0, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next165 could not read uint32');
        }

        return $value[1];
    }
}

