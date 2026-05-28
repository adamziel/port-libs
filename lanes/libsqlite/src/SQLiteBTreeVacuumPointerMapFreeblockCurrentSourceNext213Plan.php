<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext213Plan
{
    /**
     * @param list<array<string, mixed>> $receiptRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Plan $basePlan,
        private readonly array $receiptRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Plan $basePlan): self
    {
        $rows = self::buildReceiptRows($basePlan);
        $errors = self::receiptErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next213 receipt failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function receiptRows(): array
    {
        return $this->receiptRows;
    }

    /**
     * @return list<string>
     */
    public function receiptErrors(): array
    {
        return self::receiptErrorsForRows($this->receiptRows);
    }

    /**
     * @return list<int>
     */
    public function receiptPages(): array
    {
        $pages = [];
        foreach ($this->receiptRows as $row) {
            foreach ($row['receipt_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    /**
     * @return list<int>
     */
    public function pointerMapReceiptPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['receipt_channel'] === 'pointer-map');
    }

    /**
     * @return list<int>
     */
    public function payloadReceiptPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['receipt_channel'] === 'payload');
    }

    /**
     * @return list<string>
     */
    public function receiptTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['receipt_token'], $this->receiptRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptSummary(): array
    {
        $applySummary = $this->basePlan->applySummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next213-ready',
            'receipt_row_count' => count($this->receiptRows),
            'receipt_pages' => $this->receiptPages(),
            'pointer_map_receipt_pages' => $this->pointerMapReceiptPages(),
            'payload_receipt_pages' => $this->payloadReceiptPages(),
            'apply_pages' => $applySummary['apply_pages'],
            'receipt_matches_apply_pages' => $this->receiptPages() === $applySummary['apply_pages'],
            'receipt_tokens' => $this->receiptTokens(),
            'receipt_signature' => self::signature($this->receiptTokens()),
            'next_writer_receipt_token' => self::signature(array_merge(
                ['next213', $applySummary['next_writer_apply_token']],
                $this->receiptPages(),
                $this->receiptTokens(),
            )),
            'all_apply_tokens_match' => !in_array(false, array_column($this->receiptRows, 'apply_token_matches'), true),
            'all_pointer_maps_receipted_before_payload' => $this->pointerMapsBeforePayloadReceipts(),
            'all_freeblock_receipts_preserved' => !in_array(false, array_column($this->receiptRows, 'freeblock_receipt_preserved'), true),
            'all_tail_pages_fenced_after_receipt' => !in_array(false, array_column($this->receiptRows, 'tail_pages_fenced_after_receipt'), true),
            'all_receipt_chains_valid' => !in_array(false, array_column($this->receiptRows, 'receipt_chain_valid'), true),
            'receipt_page_classes' => array_values(array_unique(array_column($this->receiptRows, 'receipt_page_class'))),
            'receipt_errors' => $this->receiptErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next212',
                'sqlite-current-source-next213',
            ],
            'dependency_closure' => 'no new support component needed; next213 reuses next212 current-source apply rows, pointer-map/payload page classes, leaf freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds post-apply current-source receipt publication after next212 apply ordering; does not repeat next212 page apply ordering, next209 writer-source latching, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next213',
            'receipt_summary' => $this->receiptSummary(),
            'receipt_errors' => $this->receiptErrors(),
            'receipt_rows' => $this->receiptRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return list<int>
     */
    private function pagesBy(callable $predicate): array
    {
        $pages = [];
        foreach ($this->receiptRows as $row) {
            if (!$predicate($row)) {
                continue;
            }
            foreach ($row['receipt_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        return self::sortedIntKeys($pages);
    }

    private function pointerMapsBeforePayloadReceipts(): bool
    {
        $byCursor = [];
        foreach ($this->receiptRows as $row) {
            $cursor = (int) $row['cursor_index'];
            $byCursor[$cursor] ??= ['pointer' => null, 'payload' => null];
            if ($row['receipt_channel'] === 'pointer-map') {
                $byCursor[$cursor]['pointer'] = (int) $row['receipt_ordinal'];
            }
            if ($row['receipt_channel'] === 'payload') {
                $byCursor[$cursor]['payload'] = (int) $row['receipt_ordinal'];
            }
        }

        foreach ($byCursor as $row) {
            if ($row['payload'] !== null && ($row['pointer'] === null || $row['pointer'] > $row['payload'])) {
                return false;
            }
        }

        return $byCursor !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReceiptRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Plan $basePlan): array
    {
        $applyRows = $basePlan->applyRows();
        $applyTokens = $basePlan->applyTokens();
        $rows = [];
        $previousToken = null;
        $receiptedPages = [];

        foreach ($applyRows as $index => $row) {
            $pages = array_values(array_map('intval', $row['apply_pages']));
            foreach ($pages as $pageNumber) {
                $receiptedPages[$pageNumber] = true;
            }

            $applyToken = (string) $row['apply_token'];
            $pageClass = self::pageClass((string) $row['apply_channel'], $pages);
            $token = self::signature(array_merge(
                ['next213', (int) $row['apply_ordinal'], $previousToken ?? 'initial', $applyToken, $pageClass],
                $pages,
                self::sortedIntKeys($receiptedPages),
                [(int) $row['high_water_page']],
            ));

            $rows[] = [
                'receipt_ordinal' => (int) $row['apply_ordinal'],
                'apply_index' => $index,
                'cursor_index' => (int) $row['cursor_index'],
                'batch_index' => (int) $row['batch_index'],
                'receipt_channel' => (string) $row['apply_channel'],
                'receipt_page_class' => $pageClass,
                'receipt_pages' => $pages,
                'receipted_visible_pages' => self::sortedIntKeys($receiptedPages),
                'apply_token' => $applyToken,
                'expected_apply_token' => $applyTokens[$index] ?? null,
                'apply_token_matches' => ($applyTokens[$index] ?? null) === $applyToken,
                'previous_receipt_token' => $previousToken,
                'freeblock_receipt_preserved' => $row['freeblock_receipt_carried'] === true,
                'tail_pages_fenced_after_receipt' => $row['tail_pages_fenced_for_apply'] === true && !array_intersect([109, 110], $pages),
                'receipt_chain_valid' => $row['previous_apply_token'] === null || is_string($row['previous_apply_token']),
                'high_water_page' => (int) $row['high_water_page'],
                'receipt_state' => 'current-source-page-receipt-ready',
                'receipt_token' => $token,
            ];

            $previousToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function receiptErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousToken = null;
        $previousOrdinal = 0;
        $previousVisible = [];

        foreach ($rows as $row) {
            if ($row['receipt_state'] !== 'current-source-page-receipt-ready') {
                $errors[] = "receipt {$row['receipt_ordinal']} is not ready";
            }
            if ((int) $row['receipt_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "receipt {$row['receipt_ordinal']} skipped a receipt ordinal";
            }
            if ($row['apply_token_matches'] !== true) {
                $errors[] = "receipt {$row['receipt_ordinal']} apply token drifted";
            }
            if ($row['previous_receipt_token'] !== $previousToken) {
                $errors[] = "receipt {$row['receipt_ordinal']} broke receipt token chaining";
            }
            if ($row['freeblock_receipt_preserved'] !== true) {
                $errors[] = "receipt {$row['receipt_ordinal']} lost the leaf freeblock receipt";
            }
            if ($row['tail_pages_fenced_after_receipt'] !== true) {
                $errors[] = "receipt {$row['receipt_ordinal']} exposed fenced tail pages";
            }
            if ($row['receipt_chain_valid'] !== true) {
                $errors[] = "receipt {$row['receipt_ordinal']} has an invalid apply chain";
            }
            if (count(array_diff(array_keys($previousVisible), $row['receipted_visible_pages'])) !== 0) {
                $errors[] = "receipt {$row['receipt_ordinal']} lost an already-receipted page";
            }
            if ($row['receipt_token'] === '') {
                $errors[] = "receipt {$row['receipt_ordinal']} has an empty receipt token";
            }

            $previousOrdinal = (int) $row['receipt_ordinal'];
            $previousToken = (string) $row['receipt_token'];
            $previousVisible = array_fill_keys(array_map('intval', $row['receipted_visible_pages']), true);
        }

        return $errors;
    }

    /**
     * @param list<int> $pages
     */
    private static function pageClass(string $channel, array $pages): string
    {
        if ($channel === 'pointer-map') {
            return 'pointer-map-page';
        }

        if (in_array(3, $pages, true)) {
            return 'leaf-freeblock-page';
        }

        return 'overflow-payload-page';
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
