<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext193Plan
{
    /**
     * @param list<array<string, mixed>> $manifestRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Plan $basePlan,
        private readonly array $manifestRows,
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
        $rows = self::buildManifestRows($basePlan);
        $errors = self::manifestErrorsForRows($rows, $basePlan);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next193 manifest failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function manifestRows(): array
    {
        return $this->manifestRows;
    }

    /**
     * @return list<string>
     */
    public function manifestErrors(): array
    {
        return self::manifestErrorsForRows($this->manifestRows, $this->basePlan);
    }

    /**
     * @return list<int>
     */
    public function publishedPages(): array
    {
        $pages = [];
        foreach ($this->manifestRows as $row) {
            foreach ($row['published_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        $pages = array_keys($pages);
        sort($pages);

        return $pages;
    }

    /**
     * @return list<int>
     */
    public function fencedTailPages(): array
    {
        $pages = [];
        foreach ($this->manifestRows as $row) {
            foreach ($row['fenced_tail_pages'] as $pageNumber) {
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
    public function manifestTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['manifest_token'], $this->manifestRows));
    }

    /**
     * @return array<string, mixed>
     */
    public function manifestSummary(): array
    {
        $checkpointSummary = $this->basePlan->checkpointSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next193-ready',
            'manifest_row_count' => count($this->manifestRows),
            'published_pages' => $this->publishedPages(),
            'fenced_tail_pages' => $this->fencedTailPages(),
            'final_visible_page_count' => $checkpointSummary['final_visible_page_count'],
            'checkpoint_signature' => $checkpointSummary['checkpoint_signature'],
            'all_manifest_tokens_unique' => !in_array(false, array_column($this->manifestRows, 'manifest_token_unique'), true),
            'all_checkpoints_preserve_order' => !in_array(false, array_column($this->manifestRows, 'checkpoint_order_preserved'), true),
            'all_tail_pages_fenced' => !in_array(false, array_column($this->manifestRows, 'tail_pages_fenced'), true),
            'all_published_pages_readable' => !in_array(false, array_column($this->manifestRows, 'published_pages_readable'), true),
            'all_pointer_maps_precede_payload' => !in_array(false, array_column($this->manifestRows, 'pointer_map_before_payload'), true),
            'manifest_tokens' => $this->manifestTokens(),
            'manifest_signature' => self::signature($this->manifestTokens()),
            'reader_restart_token' => self::signature(array_merge(
                ['next193', $checkpointSummary['current_source_restart_token'], $checkpointSummary['final_visible_page_count']],
                $this->publishedPages(),
                $this->fencedTailPages(),
                $this->manifestTokens(),
            )),
            'manifest_errors' => $this->manifestErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next189',
                'sqlite-current-source-next193',
            ],
            'dependency_closure' => 'no new support component needed; next193 reuses next189 checkpoint tokens, current-source high-water pages, pointer-map ordering, and next-reader EOF fences',
            'non_overlap' => 'adds a published current-source manifest after next189 checkpoint resume; does not repeat next189 checkpoint construction, next186 cursor visibility, next185 durability receipts, overflow freelist release, page relocation, root collapse, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next193',
            'manifest_summary' => $this->manifestSummary(),
            'manifest_errors' => $this->manifestErrors(),
            'manifest_rows' => $this->manifestRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildManifestRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Plan $basePlan): array
    {
        $rows = [];
        $seenTokens = [];
        $checkpointRows = $basePlan->checkpointRows();
        $checkpointSummary = $basePlan->checkpointSummary();
        $readablePages = array_fill_keys($checkpointSummary['visible_current_source_pages'], true);
        $fencedTailPages = self::tailPagesFromBase($basePlan);
        $previousBatch = -1;

        foreach ($checkpointRows as $ordinal => $row) {
            $publishedPages = array_values(array_map('intval', $row['newly_visible_pages']));
            sort($publishedPages);
            $payloadPages = array_values(array_map('intval', $row['visible_payload_pages']));
            $pointerMapPages = array_values(array_map('intval', $row['visible_pointer_map_pages']));
            $token = self::signature(array_merge(
                ['next193', $ordinal, $row['checkpoint_token'], $row['current_source_high_water_page']],
                $publishedPages,
                $pointerMapPages,
                $payloadPages,
                $fencedTailPages,
            ));

            $publishedReadable = true;
            foreach ($publishedPages as $pageNumber) {
                if (!isset($readablePages[$pageNumber])) {
                    $publishedReadable = false;
                    break;
                }
            }

            $rows[] = [
                'manifest_order' => (int) $ordinal,
                'checkpoint_batch_index' => (int) $row['batch_index'],
                'checkpoint_order_preserved' => (int) $row['batch_index'] > $previousBatch,
                'checkpoint_token' => $row['checkpoint_token'],
                'previous_checkpoint_token' => $row['previous_resume_token'],
                'published_pages' => $publishedPages,
                'published_page_count' => count($publishedPages),
                'current_source_high_water_page' => (int) $row['current_source_high_water_page'],
                'visible_pointer_map_pages' => $pointerMapPages,
                'visible_payload_pages' => $payloadPages,
                'pointer_map_before_payload' => $payloadPages === [] || $pointerMapPages !== [],
                'fenced_tail_pages' => $fencedTailPages,
                'tail_pages_fenced' => $fencedTailPages !== [] && $row['fenced_pages_hidden'] === true,
                'published_pages_readable' => $publishedReadable,
                'manifest_token_unique' => !isset($seenTokens[$token]),
                'manifest_state' => 'current-source-manifest-published',
                'manifest_token' => $token,
            ];

            $seenTokens[$token] = true;
            $previousBatch = (int) $row['batch_index'];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function tailPagesFromBase(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Plan $basePlan): array
    {
        $cursorBase = $basePlan->basePlan->basePlan->basePlan->basePlan;
        $pages = $cursorBase->fencedPages();
        sort($pages);

        return $pages;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function manifestErrorsForRows(array $rows, SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext189Plan $basePlan): array
    {
        $errors = [];
        if ($rows === []) {
            $errors[] = 'current-source manifest has no checkpoint rows';
        }

        $checkpointSummary = $basePlan->checkpointSummary();
        $published = [];
        foreach ($rows as $row) {
            if ($row['manifest_state'] !== 'current-source-manifest-published') {
                $errors[] = "manifest row {$row['manifest_order']} is not published";
            }
            if ($row['checkpoint_order_preserved'] !== true) {
                $errors[] = "manifest row {$row['manifest_order']} lost checkpoint order";
            }
            if ($row['manifest_token_unique'] !== true) {
                $errors[] = "manifest row {$row['manifest_order']} reused a manifest token";
            }
            if ($row['tail_pages_fenced'] !== true) {
                $errors[] = "manifest row {$row['manifest_order']} did not fence vacuum tail pages";
            }
            if ($row['published_pages_readable'] !== true) {
                $errors[] = "manifest row {$row['manifest_order']} published an unreadable page";
            }
            if ($row['pointer_map_before_payload'] !== true) {
                $errors[] = "manifest row {$row['manifest_order']} published payload before pointer-map visibility";
            }
            if ($row['manifest_token'] === '') {
                $errors[] = "manifest row {$row['manifest_order']} has an empty token";
            }
            foreach ($row['published_pages'] as $pageNumber) {
                $published[(int) $pageNumber] = true;
            }
        }

        $publishedPages = array_keys($published);
        sort($publishedPages);
        if ($publishedPages !== $checkpointSummary['newly_visible_pages']) {
            $errors[] = 'published manifest pages do not match next189 newly visible pages';
        }

        return $errors;
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
