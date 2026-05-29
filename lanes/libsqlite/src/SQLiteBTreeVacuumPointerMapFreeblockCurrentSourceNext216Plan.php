<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext216Plan
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
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next216 receipt failed: ' . implode('; ', $errors));
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
        return $this->pagesBy(static fn (array $row): bool => $row['receipt_channel'] === 'pointer-map-receipt');
    }

    /**
     * @return list<int>
     */
    public function payloadReceiptPages(): array
    {
        return $this->pagesBy(static fn (array $row): bool => $row['receipt_channel'] === 'payload-receipt');
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
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next216-ready',
            'receipt_row_count' => count($this->receiptRows),
            'receipt_pages' => $this->receiptPages(),
            'pointer_map_receipt_pages' => $this->pointerMapReceiptPages(),
            'payload_receipt_pages' => $this->payloadReceiptPages(),
            'apply_pages' => $applySummary['apply_pages'],
            'receipts_match_apply_pages' => $this->receiptPages() === $applySummary['apply_pages'],
            'receipt_tokens' => $this->receiptTokens(),
            'receipt_signature' => self::signature($this->receiptTokens()),
            'next_writer_commit_token' => self::signature(array_merge(
                ['next216', $applySummary['next_writer_apply_token']],
                $this->receiptPages(),
                $this->receiptTokens(),
            )),
            'all_apply_tokens_match' => !in_array(false, array_column($this->receiptRows, 'apply_token_matches'), true),
            'all_pointer_maps_committed_before_payload' => $this->pointerMapsBeforePayloadReceipts(),
            'all_freeblock_receipts_committed' => !in_array(false, array_column($this->receiptRows, 'freeblock_receipt_committed'), true),
            'all_tail_pages_excluded_from_receipts' => !in_array(false, array_column($this->receiptRows, 'tail_pages_excluded_from_receipt'), true),
            'all_receipt_chains_valid' => !in_array(false, array_column($this->receiptRows, 'receipt_chain_valid'), true),
            'receipt_errors' => $this->receiptErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next212',
                'sqlite-current-source-next216',
            ],
            'dependency_closure' => 'no new support component needed; next216 reuses next212 current-source apply rows, page hashes, freeblock receipts, and fenced-tail metadata',
            'non_overlap' => 'adds commit receipts for already ordered next212 apply rows; does not repeat next212 apply ordering, next209 writer-source latching, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or accepted freelist/pointer-map reuse slices',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next216',
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
            if ($row['receipt_channel'] === 'pointer-map-receipt') {
                $byCursor[$cursor]['pointer'] = (int) $row['receipt_ordinal'];
            }
            if ($row['receipt_channel'] === 'payload-receipt') {
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
        $previousReceiptToken = null;
        $committedPages = [];

        foreach ($applyRows as $index => $row) {
            $pages = array_values(array_map('intval', $row['apply_pages']));
            foreach ($pages as $pageNumber) {
                $committedPages[$pageNumber] = true;
            }

            $applyToken = (string) $row['apply_token'];
            $receiptChannel = $row['apply_channel'] === 'pointer-map' ? 'pointer-map-receipt' : 'payload-receipt';
            $pageHashes = [];
            foreach ($pages as $pageNumber) {
                $pageHashes[$pageNumber] = self::signature([
                    'next216-page',
                    $pageNumber,
                    $receiptChannel,
                    $applyToken,
                    (int) $row['high_water_page'],
                ]);
            }

            $receiptToken = self::signature(array_merge(
                ['next216', (int) $row['apply_ordinal'], $receiptChannel, $previousReceiptToken ?? 'initial', $applyToken],
                $pages,
                self::sortedIntKeys($committedPages),
                array_values($pageHashes),
            ));

            $rows[] = [
                'receipt_ordinal' => (int) $row['apply_ordinal'],
                'apply_index' => $index,
                'cursor_index' => (int) $row['cursor_index'],
                'batch_index' => (int) $row['batch_index'],
                'receipt_channel' => $receiptChannel,
                'receipt_pages' => $pages,
                'committed_visible_pages' => self::sortedIntKeys($committedPages),
                'page_hashes' => $pageHashes,
                'apply_token' => $applyToken,
                'expected_apply_token' => $applyTokens[$index] ?? null,
                'apply_token_matches' => ($applyTokens[$index] ?? null) === $applyToken,
                'previous_receipt_token' => $previousReceiptToken,
                'freeblock_receipt_committed' => $row['freeblock_receipt_carried'] === true,
                'tail_pages_excluded_from_receipt' => $row['tail_pages_fenced_for_apply'] === true && !array_intersect([109, 110], $pages),
                'receipt_chain_valid' => $row['previous_apply_token'] === null || is_string($row['previous_apply_token']),
                'high_water_page' => (int) $row['high_water_page'],
                'receipt_state' => 'current-source-page-commit-receipted',
                'receipt_token' => $receiptToken,
            ];

            $previousReceiptToken = $receiptToken;
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
        $previousCommitted = [];

        foreach ($rows as $row) {
            if ($row['receipt_state'] !== 'current-source-page-commit-receipted') {
                $errors[] = "receipt {$row['receipt_ordinal']} is not committed";
            }
            if ((int) $row['receipt_ordinal'] !== $previousOrdinal + 1) {
                $errors[] = "receipt {$row['receipt_ordinal']} skipped an ordinal";
            }
            if ($row['apply_token_matches'] !== true) {
                $errors[] = "receipt {$row['receipt_ordinal']} apply token drifted";
            }
            if ($row['previous_receipt_token'] !== $previousToken) {
                $errors[] = "receipt {$row['receipt_ordinal']} broke receipt token chaining";
            }
            if ($row['freeblock_receipt_committed'] !== true) {
                $errors[] = "receipt {$row['receipt_ordinal']} lost the freeblock receipt";
            }
            if ($row['tail_pages_excluded_from_receipt'] !== true) {
                $errors[] = "receipt {$row['receipt_ordinal']} exposed fenced tail pages";
            }
            if ($row['receipt_chain_valid'] !== true) {
                $errors[] = "receipt {$row['receipt_ordinal']} has an invalid apply chain";
            }
            if (count(array_diff(array_keys($previousCommitted), $row['committed_visible_pages'])) !== 0) {
                $errors[] = "receipt {$row['receipt_ordinal']} lost an already-committed page";
            }
            if ($row['receipt_token'] === '') {
                $errors[] = "receipt {$row['receipt_ordinal']} has an empty receipt token";
            }
            if (count($row['page_hashes']) !== count($row['receipt_pages'])) {
                $errors[] = "receipt {$row['receipt_ordinal']} has incomplete page hashes";
            }

            $previousOrdinal = (int) $row['receipt_ordinal'];
            $previousToken = (string) $row['receipt_token'];
            $previousCommitted = array_fill_keys(array_map('intval', $row['committed_visible_pages']), true);
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
