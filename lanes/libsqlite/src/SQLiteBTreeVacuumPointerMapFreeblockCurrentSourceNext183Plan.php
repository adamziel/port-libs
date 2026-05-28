<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext183Plan
{
    /**
     * @param list<array<string, mixed>> $commitRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext180Plan $basePlan,
        private readonly array $commitRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext180Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext180Plan $basePlan): self
    {
        $rows = self::buildCommitRows($basePlan);
        $errors = self::commitErrorsForRows($rows, $basePlan->fencedApplyPages());
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next183 commit receipt failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function commitRows(): array
    {
        return $this->commitRows;
    }

    /**
     * @return list<string>
     */
    public function commitErrors(): array
    {
        return self::commitErrorsForRows($this->commitRows, $this->basePlan->fencedApplyPages());
    }

    /**
     * @return list<int>
     */
    public function committedPageImages(): array
    {
        return $this->pagesBy('page_image_pages');
    }

    /**
     * @return list<int>
     */
    public function committedPointerMapPages(): array
    {
        return $this->pagesBy('pointer_map_pages');
    }

    /**
     * @return list<int>
     */
    public function committedLeafFreeblockPages(): array
    {
        return $this->pagesBy('leaf_freeblock_pages');
    }

    /**
     * @return list<int>
     */
    public function committedOverflowPages(): array
    {
        return $this->pagesBy('overflow_page_images');
    }

    /**
     * @return list<int>
     */
    public function committedFencedPages(): array
    {
        $fenced = array_fill_keys($this->basePlan->basePlan->fencedPages(), true);

        return array_values(array_filter(
            $this->committedPageImages(),
            static fn (int $pageNumber): bool => isset($fenced[$pageNumber]),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function commitSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next183-ready',
            'leaf_page' => $this->basePlan->applySummary()['leaf_page'],
            'batch_count' => count($this->commitRows),
            'committed_page_images' => $this->committedPageImages(),
            'committed_pointer_map_pages' => $this->committedPointerMapPages(),
            'committed_leaf_freeblock_pages' => $this->committedLeafFreeblockPages(),
            'committed_overflow_pages' => $this->committedOverflowPages(),
            'committed_fenced_pages' => $this->committedFencedPages(),
            'receipt_count' => array_sum(array_column($this->commitRows, 'receipt_count')),
            'all_pointer_maps_precede_pages' => !in_array(false, array_column($this->commitRows, 'pointer_map_precedes_pages'), true),
            'all_page_hashes_present' => !in_array(false, array_column($this->commitRows, 'page_hashes_present'), true),
            'current_source_receipt_signature' => self::signature(array_column($this->commitRows, 'current_source_receipt_key')),
            'freeblock_receipt_signature' => self::signature($this->committedLeafFreeblockPages()),
            'final_database_page_count' => $this->basePlan->applySummary()['final_database_page_count'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next180',
                'sqlite-current-source-next183',
            ],
            'dependency_closure' => 'no new support component needed; next183 reuses next180 apply ordering, next177 replay batches, native page-image hashes, and auto-vacuum pointer-map dependency pages',
            'non_overlap' => 'adds commit receipts for current-source page-image publication after next180 apply ordering; does not repeat next180 apply ordering, next177 batch construction, overflow freelist release, root collapse, page relocation, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next183',
            'commit_summary' => $this->commitSummary(),
            'commit_errors' => $this->commitErrors(),
            'commit_rows' => $this->commitRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return list<int>
     */
    private static function pageImagePages(array $row): array
    {
        return array_values(array_map(
            static fn (array $write): int => (int) $write['page_number'],
            array_filter(
                $row['write_sequence'],
                static fn (array $write): bool => $write['kind'] === 'page-image',
            ),
        ));
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private static function pageImageHashes(array $row): array
    {
        return array_values(array_map(
            static fn (array $write): string => (string) $write['next_page_hash'],
            array_filter(
                $row['write_sequence'],
                static fn (array $write): bool => $write['kind'] === 'page-image',
            ),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCommitRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext180Plan $basePlan): array
    {
        $leafPage = (int) $basePlan->applySummary()['leaf_page'];
        $rows = [];
        foreach ($basePlan->applyRows() as $row) {
            $pageImages = self::pageImagePages($row);
            $pageHashes = self::pageImageHashes($row);
            $pointerMapPages = array_values(array_map('intval', $row['dependency_write_pages']));
            $leafFreeblockPages = in_array($leafPage, $pageImages, true) ? [$leafPage] : [];
            $overflowPages = array_values(array_filter($pageImages, static fn (int $pageNumber): bool => $pageNumber !== $leafPage && $pageNumber !== 1 && $pageNumber !== 105));
            $receiptKinds = [];
            if ($pointerMapPages !== []) {
                $receiptKinds[] = 'pointer-map-before-page-image';
            }
            if ($leafFreeblockPages !== []) {
                $receiptKinds[] = 'leaf-freeblock-current-source';
            }
            if ($overflowPages !== []) {
                $receiptKinds[] = 'overflow-page-image-current-source';
            }

            $rows[] = [
                'batch_index' => (int) $row['batch_index'],
                'pointer_map_pages' => $pointerMapPages,
                'page_image_pages' => $pageImages,
                'leaf_freeblock_pages' => $leafFreeblockPages,
                'overflow_page_images' => $overflowPages,
                'receipt_kinds' => $receiptKinds,
                'receipt_count' => count($receiptKinds),
                'pointer_map_precedes_pages' => $row['pointer_map_precedes_pages'],
                'page_hashes' => $pageHashes,
                'page_hashes_present' => count($pageHashes) === count($pageImages) && !in_array('', $pageHashes, true),
                'contains_fenced_page' => $row['contains_fenced_page'],
                'deleted_cell_visible_to_next' => $row['deleted_cell_visible_to_next'],
                'current_source_receipt_key' => self::signature(array_merge(
                    [(int) $row['batch_index']],
                    $pointerMapPages,
                    $pageImages,
                    $receiptKinds,
                    $pageHashes,
                )),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<int> $fencedApplyPages
     * @return list<string>
     */
    private static function commitErrorsForRows(array $rows, array $fencedApplyPages): array
    {
        $errors = [];
        $fenced = array_fill_keys($fencedApplyPages, true);
        foreach ($rows as $row) {
            if ($row['pointer_map_precedes_pages'] !== true) {
                $errors[] = "batch {$row['batch_index']} committed page images before pointer-map receipts";
            }
            if ($row['page_hashes_present'] !== true) {
                $errors[] = "batch {$row['batch_index']} has missing page-image hashes";
            }
            if ($row['contains_fenced_page'] === true) {
                $errors[] = "batch {$row['batch_index']} contains a fenced page";
            }
            if ($row['deleted_cell_visible_to_next'] === true) {
                $errors[] = "batch {$row['batch_index']} keeps the deleted cell visible";
            }
            foreach ($row['page_image_pages'] as $pageNumber) {
                if (isset($fenced[$pageNumber])) {
                    $errors[] = "fenced page {$pageNumber} reached current-source commit receipts";
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
        foreach ($this->commitRows as $row) {
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
