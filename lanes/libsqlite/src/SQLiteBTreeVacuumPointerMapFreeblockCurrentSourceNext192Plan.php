<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext192Plan
{
    /**
     * @param list<array<string, mixed>> $validationRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Plan $basePlan,
        private readonly array $validationRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Plan $basePlan): self
    {
        $rows = self::buildValidationRows($basePlan);
        $errors = self::validationErrorsForRows($rows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next192 validation failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function validationRows(): array
    {
        return $this->validationRows;
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        return self::validationErrorsForRows($this->validationRows);
    }

    /**
     * @return list<int>
     */
    public function admittedReaderPages(): array
    {
        $pages = [];
        foreach ($this->validationRows as $row) {
            foreach ($row['validated_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        $pages = array_keys($pages);
        sort($pages);

        return $pages;
    }

    /**
     * @return list<string>
     */
    public function validationTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['validation_token'], $this->validationRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function validationSummary(): array
    {
        $checkpointSummary = $this->basePlan->checkpointSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next192-ready',
            'validation_row_count' => count($this->validationRows),
            'admitted_reader_pages' => $this->admittedReaderPages(),
            'checkpoint_tokens' => $checkpointSummary['checkpoint_tokens'],
            'validation_tokens' => $this->validationTokens(),
            'validation_signature' => self::signature($this->validationTokens()),
            'current_source_reader_token' => self::signature(array_merge(
                ['next192', $checkpointSummary['current_source_restart_token']],
                $this->admittedReaderPages(),
                $this->validationTokens(),
            )),
            'all_checkpoint_tokens_match' => !in_array(false, array_column($this->validationRows, 'checkpoint_token_matches'), true),
            'all_pointer_maps_validated' => !in_array(false, array_column($this->validationRows, 'pointer_map_validated_before_payload'), true),
            'all_freeblock_pages_validated' => !in_array(false, array_column($this->validationRows, 'leaf_freeblock_validated'), true),
            'all_fenced_pages_excluded' => !in_array(false, array_column($this->validationRows, 'fenced_pages_excluded'), true),
            'all_page_hashes_replayed' => !in_array(false, array_column($this->validationRows, 'page_hashes_replayed'), true),
            'validation_errors' => $this->validationErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next189',
                'sqlite-current-source-next192',
            ],
            'dependency_closure' => 'no new support component needed; next192 reuses next189 checkpoint tokens, cursor page hashes, pointer-map ordering, and fenced-tail current-source metadata',
            'non_overlap' => 'adds next-reader validation after next189 checkpoints; does not repeat next189 checkpoint construction, next186 cursor visibility, next183 receipts, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next192',
            'validation_summary' => $this->validationSummary(),
            'validation_errors' => $this->validationErrors(),
            'validation_rows' => $this->validationRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildValidationRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Plan $basePlan): array
    {
        $checkpointRows = $basePlan->checkpointRows();
        $checkpointTokens = $basePlan->checkpointTokens();
        $fencedPages = array_fill_keys($basePlan->basePlan->basePlan->basePlan->basePlan->fencedPages(), true);
        $rows = [];
        $validatedPages = [];
        $previousValidationToken = null;

        foreach ($checkpointRows as $index => $row) {
            $newPages = array_values(array_map('intval', $row['newly_visible_pages']));
            foreach ($newPages as $pageNumber) {
                $validatedPages[$pageNumber] = true;
            }

            $payloadPages = array_values(array_map('intval', $row['visible_payload_pages']));
            $pointerMapPages = array_values(array_map('intval', $row['visible_pointer_map_pages']));
            $pageHashCount = (int) $row['page_hash_count'];
            $containsFenced = false;
            foreach ($newPages as $pageNumber) {
                if (isset($fencedPages[$pageNumber])) {
                    $containsFenced = true;
                    break;
                }
            }

            $validationState = 'next-reader-admitted';
            $token = self::signature(array_merge(
                ['next192', (int) $row['batch_index'], $previousValidationToken ?? 'initial', (string) $row['checkpoint_token']],
                $newPages,
                $pointerMapPages,
                $payloadPages,
                [(int) $row['current_source_high_water_page'], $pageHashCount],
                $row['receipt_kinds'],
            ));

            $rows[] = [
                'batch_index' => (int) $row['batch_index'],
                'checkpoint_token' => (string) $row['checkpoint_token'],
                'expected_checkpoint_token' => $checkpointTokens[$index] ?? null,
                'checkpoint_token_matches' => ($checkpointTokens[$index] ?? null) === (string) $row['checkpoint_token'],
                'previous_validation_token' => $previousValidationToken,
                'validated_pages' => $newPages,
                'validated_page_count' => count($newPages),
                'cumulative_validated_pages' => self::sortedIntKeys($validatedPages),
                'visible_pointer_map_pages' => $pointerMapPages,
                'visible_payload_pages' => $payloadPages,
                'pointer_map_validated_before_payload' => $payloadPages === [] || $pointerMapPages !== [],
                'leaf_freeblock_validated' => in_array('leaf-freeblock-current-source', $row['receipt_kinds'], true) ? in_array(3, $payloadPages, true) : true,
                'fenced_pages_excluded' => $containsFenced === false && $row['fenced_pages_hidden'] === true,
                'deleted_cell_hidden' => $row['deleted_cell_hidden'],
                'page_hash_count' => $pageHashCount,
                'page_hashes_replayed' => $pageHashCount >= max(1, min(2, count($newPages))),
                'high_water_page' => (int) $row['current_source_high_water_page'],
                'validation_state' => $validationState,
                'validation_token' => $token,
            ];

            $previousValidationToken = $token;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function validationErrorsForRows(array $rows): array
    {
        $errors = [];
        $previousHighWater = 0;
        foreach ($rows as $row) {
            if ($row['validation_state'] !== 'next-reader-admitted') {
                $errors[] = "batch {$row['batch_index']} is not admitted for the next reader";
            }
            if ($row['checkpoint_token_matches'] !== true) {
                $errors[] = "batch {$row['batch_index']} checkpoint token drifted before validation";
            }
            if ($row['pointer_map_validated_before_payload'] !== true) {
                $errors[] = "batch {$row['batch_index']} validates payload before pointer-map pages";
            }
            if ($row['leaf_freeblock_validated'] !== true) {
                $errors[] = "batch {$row['batch_index']} missed the leaf freeblock page";
            }
            if ($row['fenced_pages_excluded'] !== true) {
                $errors[] = "batch {$row['batch_index']} admits a fenced tail page";
            }
            if ($row['deleted_cell_hidden'] !== true) {
                $errors[] = "batch {$row['batch_index']} exposes the deleted cell";
            }
            if ($row['page_hashes_replayed'] !== true) {
                $errors[] = "batch {$row['batch_index']} did not replay enough page hashes";
            }
            if ((int) $row['high_water_page'] < $previousHighWater) {
                $errors[] = "batch {$row['batch_index']} moved high-water backwards";
            }
            if ($row['validation_token'] === '') {
                $errors[] = "batch {$row['batch_index']} has an empty validation token";
            }
            $previousHighWater = (int) $row['high_water_page'];
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
