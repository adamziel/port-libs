<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext186Plan
{
    /**
     * @param list<array<string, mixed>> $cursorRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext183Plan $basePlan,
        private readonly array $cursorRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext183Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext183Plan $basePlan): self
    {
        $rows = self::buildCursorRows($basePlan);
        $errors = self::cursorErrorsForRows($rows, $basePlan);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next186 cursor failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cursorRows(): array
    {
        return $this->cursorRows;
    }

    /**
     * @return list<string>
     */
    public function cursorErrors(): array
    {
        return self::cursorErrorsForRows($this->cursorRows, $this->basePlan);
    }

    /**
     * @return list<string>
     */
    public function resumeTokens(): array
    {
        return array_values(array_map(static fn (array $row): string => $row['resume_token'], $this->cursorRows));
    }

    /**
     * @return list<int>
     */
    public function visibleCurrentSourcePages(): array
    {
        return $this->pagesBy('visible_current_source_pages');
    }

    /**
     * @return list<int>
     */
    public function visiblePointerMapPages(): array
    {
        return $this->pagesBy('visible_pointer_map_pages');
    }

    /**
     * @return list<int>
     */
    public function visibleLeafFreeblockPages(): array
    {
        return $this->pagesBy('visible_leaf_freeblock_pages');
    }

    /**
     * @return list<int>
     */
    public function visibleOverflowPages(): array
    {
        return $this->pagesBy('visible_overflow_pages');
    }

    /**
     * @return array<string, mixed>
     */
    public function cursorSummary(): array
    {
        $summary = $this->basePlan->commitSummary();

        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next186-ready',
            'leaf_page' => $summary['leaf_page'],
            'cursor_row_count' => count($this->cursorRows),
            'visible_current_source_pages' => $this->visibleCurrentSourcePages(),
            'visible_pointer_map_pages' => $this->visiblePointerMapPages(),
            'visible_leaf_freeblock_pages' => $this->visibleLeafFreeblockPages(),
            'visible_overflow_pages' => $this->visibleOverflowPages(),
            'fenced_pages_visible' => $this->fencedPagesVisible(),
            'resume_tokens' => $this->resumeTokens(),
            'resume_signature' => self::signature($this->resumeTokens()),
            'current_source_revision' => self::signature(array_merge(
                $summary['committed_page_images'],
                $summary['committed_pointer_map_pages'],
                [$summary['current_source_receipt_signature'], $summary['freeblock_receipt_signature']],
            )),
            'all_rows_have_pointer_map_or_page_visibility' => !in_array(false, array_column($this->cursorRows, 'has_pointer_map_or_page_visibility'), true),
            'all_rows_hide_deleted_cell' => !in_array(false, array_column($this->cursorRows, 'deleted_cell_hidden'), true),
            'all_rows_hide_fenced_pages' => !in_array(false, array_column($this->cursorRows, 'fenced_pages_hidden'), true),
            'cursor_errors' => $this->cursorErrors(),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next183',
                'sqlite-current-source-next186',
            ],
            'dependency_closure' => 'no new support component needed; next186 reuses next183 commit receipts, native page hashes, and auto-vacuum pointer-map/freeblock visibility metadata',
            'non_overlap' => 'adds post-commit current-source cursor visibility and resume tokens; does not repeat next183 commit receipts, next180 apply ordering, next177 batch construction, overflow freelist release, root collapse, page relocation, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next186',
            'cursor_summary' => $this->cursorSummary(),
            'cursor_errors' => $this->cursorErrors(),
            'cursor_rows' => $this->cursorRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<int>
     */
    private function fencedPagesVisible(): array
    {
        $visible = array_fill_keys($this->visibleCurrentSourcePages(), true);

        return array_values(array_filter(
            $this->basePlan->basePlan->basePlan->fencedPages(),
            static fn (int $pageNumber): bool => isset($visible[$pageNumber]),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCursorRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext183Plan $basePlan): array
    {
        $summary = $basePlan->commitSummary();
        $rows = [];
        foreach ($basePlan->commitRows() as $row) {
            $pageImages = array_values(array_map('intval', $row['page_image_pages']));
            $pointerMaps = array_values(array_map('intval', $row['pointer_map_pages']));
            $leafFreeblocks = array_values(array_map('intval', $row['leaf_freeblock_pages']));
            $overflowPages = array_values(array_map('intval', $row['overflow_page_images']));
            $visible = array_values(array_unique(array_merge($pointerMaps, $pageImages)));
            sort($visible);

            $rows[] = [
                'batch_index' => (int) $row['batch_index'],
                'visible_pointer_map_pages' => $pointerMaps,
                'visible_current_source_pages' => $visible,
                'visible_leaf_freeblock_pages' => $leafFreeblocks,
                'visible_overflow_pages' => $overflowPages,
                'page_hashes' => $row['page_hashes'],
                'resume_token' => self::signature(array_merge(
                    ['next186', (int) $row['batch_index'], $summary['current_source_receipt_signature']],
                    $visible,
                    $row['page_hashes'],
                )),
                'has_pointer_map_or_page_visibility' => $pointerMaps !== [] || $pageImages !== [],
                'deleted_cell_hidden' => $row['deleted_cell_visible_to_next'] === false,
                'fenced_pages_hidden' => $row['contains_fenced_page'] === false,
                'leaf_freeblock_visible' => $leafFreeblocks !== [],
                'overflow_visible_count' => count($overflowPages),
                'receipt_kinds' => $row['receipt_kinds'],
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function cursorErrorsForRows(array $rows, SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext183Plan $basePlan): array
    {
        $errors = [];
        $fenced = array_fill_keys($basePlan->basePlan->basePlan->fencedPages(), true);
        foreach ($rows as $row) {
            if ($row['has_pointer_map_or_page_visibility'] !== true) {
                $errors[] = "batch {$row['batch_index']} has no current-source cursor pages";
            }
            if ($row['deleted_cell_hidden'] !== true) {
                $errors[] = "batch {$row['batch_index']} exposes the deleted cell";
            }
            if ($row['fenced_pages_hidden'] !== true) {
                $errors[] = "batch {$row['batch_index']} exposes a fenced tail page";
            }
            if ($row['resume_token'] === '') {
                $errors[] = "batch {$row['batch_index']} has an empty resume token";
            }
            foreach ($row['visible_current_source_pages'] as $pageNumber) {
                if (isset($fenced[(int) $pageNumber])) {
                    $errors[] = "fenced page {$pageNumber} reached current-source cursor visibility";
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<int>
     */
    private function pagesBy(string $key): array
    {
        $pages = [];
        foreach ($this->cursorRows as $row) {
            foreach ($row[$key] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        $pages = array_keys($pages);
        sort($pages);

        return $pages;
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
