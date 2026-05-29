<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext180Plan
{
    /**
     * @param list<array<string, mixed>> $applyRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan::tableLeafFromDeleteResult(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan $basePlan): self
    {
        $rows = self::buildApplyRows($basePlan);
        $errors = self::applyErrorsForRows($rows, $basePlan->fencedPages());
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next180 apply order failed: ' . implode('; ', $errors));
        }

        return new self($basePlan, $rows);
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
        return self::applyErrorsForRows($this->applyRows, $this->basePlan->fencedPages());
    }

    /**
     * @return list<int>
     */
    public function applyPages(): array
    {
        $pages = [];
        foreach ($this->applyRows as $row) {
            foreach ($row['page_write_pages'] as $pageNumber) {
                $pages[] = (int) $pageNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     */
    public function pointerMapWritePages(): array
    {
        $pages = [];
        foreach ($this->applyRows as $row) {
            foreach ($row['dependency_write_pages'] as $pageNumber) {
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
    public function fencedApplyPages(): array
    {
        $fenced = array_fill_keys($this->basePlan->fencedPages(), true);

        return array_values(array_filter(
            $this->applyPages(),
            static fn (int $pageNumber): bool => isset($fenced[$pageNumber]),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function writeSequence(): array
    {
        $sequence = [];
        foreach ($this->applyRows as $row) {
            foreach ($row['write_sequence'] as $write) {
                $sequence[] = $write;
            }
        }

        return $sequence;
    }

    /**
     * @return array<string, mixed>
     */
    public function applySummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next180-ready',
            'leaf_page' => $this->basePlan->nextSourceSummary()['leaf_page'],
            'batch_count' => count($this->applyRows),
            'apply_pages' => $this->applyPages(),
            'pointer_map_write_pages' => $this->pointerMapWritePages(),
            'fenced_pages' => $this->basePlan->fencedPages(),
            'fenced_apply_pages' => $this->fencedApplyPages(),
            'apply_sequence_count' => count($this->writeSequence()),
            'apply_signature' => self::signature(array_map(
                static fn (array $write): string => $write['kind'] . ':' . $write['page_number'] . ':' . $write['batch_index'],
                $this->writeSequence(),
            )),
            'batch_signature' => self::signature(array_column($this->applyRows, 'batch_apply_key')),
            'final_database_page_count' => $this->basePlan->nextSourceSummary()['final_database_page_count'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next177',
                'sqlite-current-source-next180',
            ],
            'dependency_closure' => 'no new support component needed; next180 reuses next177 readable batches, pointer-map dependency pages, page-image hashes, and fenced tail pages',
            'non_overlap' => 'adds current-source apply ordering for next177 replay batches; it does not repeat next177 batch construction, next174 cursor fencing, overflow freelist release, root collapse, page relocation, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next180',
            'apply_summary' => $this->applySummary(),
            'apply_errors' => $this->applyErrors(),
            'apply_rows' => $this->applyRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildApplyRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan $basePlan): array
    {
        $rows = [];
        foreach ($basePlan->batchRows() as $row) {
            $batchIndex = (int) $row['batch_index'];
            $dependencyPages = array_values(array_map('intval', $row['pointer_map_dependency_pages']));
            sort($dependencyPages);
            $pageNumbers = array_values(array_map('intval', $row['page_numbers']));

            $sequence = [];
            foreach ($dependencyPages as $pageNumber) {
                $sequence[] = [
                    'kind' => 'pointer-map',
                    'batch_index' => $batchIndex,
                    'page_number' => $pageNumber,
                    'must_precede_pages' => $pageNumbers,
                ];
            }
            foreach ($pageNumbers as $offset => $pageNumber) {
                $sequence[] = [
                    'kind' => 'page-image',
                    'batch_index' => $batchIndex,
                    'page_number' => $pageNumber,
                    'resume_token' => $row['resume_tokens'][$offset],
                    'next_page_hash' => $row['next_page_hashes'][$offset],
                ];
            }

            $rows[] = [
                'batch_index' => $batchIndex,
                'dependency_write_pages' => $dependencyPages,
                'page_write_pages' => $pageNumbers,
                'page_write_count' => count($pageNumbers),
                'pointer_map_write_count' => count($dependencyPages),
                'write_sequence' => $sequence,
                'pointer_map_precedes_pages' => self::pointerMapPrecedesPages($sequence),
                'contains_fenced_page' => $row['contains_fenced_page'],
                'deleted_cell_visible_to_next' => $row['deleted_cell_visible_to_next'],
                'batch_apply_key' => self::signature(array_merge($dependencyPages, $pageNumbers, array_column($sequence, 'kind'))),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $sequence
     */
    private static function pointerMapPrecedesPages(array $sequence): bool
    {
        $firstPageImage = null;
        $lastPointerMap = null;
        foreach ($sequence as $index => $write) {
            if ($write['kind'] === 'page-image' && $firstPageImage === null) {
                $firstPageImage = $index;
            }
            if ($write['kind'] === 'pointer-map') {
                $lastPointerMap = $index;
            }
        }

        return $lastPointerMap === null || ($firstPageImage !== null && $lastPointerMap < $firstPageImage);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<int> $fencedPages
     * @return list<string>
     */
    private static function applyErrorsForRows(array $rows, array $fencedPages): array
    {
        $errors = [];
        $fenced = array_fill_keys($fencedPages, true);
        foreach ($rows as $row) {
            if ($row['contains_fenced_page'] === true) {
                $errors[] = "batch {$row['batch_index']} contains a fenced page";
            }
            if ($row['page_write_count'] < 1) {
                $errors[] = "batch {$row['batch_index']} has no page-image writes";
            }
            if ($row['pointer_map_precedes_pages'] !== true) {
                $errors[] = "batch {$row['batch_index']} writes a page image before its pointer-map dependencies";
            }
            foreach ($row['page_write_pages'] as $pageNumber) {
                if (isset($fenced[$pageNumber])) {
                    $errors[] = "fenced page {$pageNumber} reached the page-image apply sequence";
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<mixed> $values
     */
    private static function signature(array $values): string
    {
        return hash('sha256', implode(',', array_map(
            static fn (mixed $value): string => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            $values,
        )));
    }
}
