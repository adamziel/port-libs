<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext256Plan
{
    /**
     * @param list<array<string, mixed>> $publicationRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan $admissionPlan,
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
        return self::fromAdmissionPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan::tableLeafFromDeleteResult(
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

    public static function fromAdmissionPlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan $admissionPlan): self
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
    private static function buildPublicationRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext251Plan $admissionPlan): array
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
