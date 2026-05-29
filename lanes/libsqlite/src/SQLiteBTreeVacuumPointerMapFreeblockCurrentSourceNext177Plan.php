<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Plan
{
    /**
     * @param list<array<string, mixed>> $batchRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $batchRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext174(
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

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): self
    {
        $rows = self::buildBatchRows($basePlan);
        foreach ($rows as $row) {
            if ($row['contains_fenced_page'] === true) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock next177 admitted a fenced current-source page into a next-source batch');
            }
            if ($row['page_count'] < 1 || $row['resume_token_count'] !== $row['page_count']) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock next177 built an invalid next-source batch');
            }
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function batchRows(): array
    {
        return $this->batchRows;
    }

    /**
     * @return list<int>
     */
    public function replayPages(): array
    {
        $pages = [];
        foreach ($this->batchRows as $row) {
            foreach ($row['page_numbers'] as $pageNumber) {
                $pages[] = (int) $pageNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     */
    public function fencedPages(): array
    {
        return $this->basePlan->fencedCursorPages();
    }

    /**
     * @return list<int>
     */
    public function pointerMapDependencyPages(): array
    {
        $pages = [];
        foreach ($this->batchRows as $row) {
            foreach ($row['pointer_map_dependency_pages'] as $pageNumber) {
                $pages[(int) $pageNumber] = true;
            }
        }

        $pages = array_keys($pages);
        sort($pages);

        return $pages;
    }

    /**
     * @return array<string, mixed>
     */
    public function nextSourceSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next177-ready',
            'leaf_page' => $this->basePlan->cursorSummary()['leaf_page'],
            'batch_count' => count($this->batchRows),
            'replay_pages' => $this->replayPages(),
            'fenced_pages' => $this->fencedPages(),
            'pointer_map_dependency_pages' => $this->pointerMapDependencyPages(),
            'batch_signature' => self::signature(array_column($this->batchRows, 'batch_replay_key')),
            'replay_signature' => self::signature($this->replayPages()),
            'fenced_signature' => self::signature($this->fencedPages()),
            'final_database_page_count' => $this->basePlan->cursorSummary()['final_database_page_count'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next174',
                'sqlite-current-source-next177',
            ],
            'dependency_closure' => 'no new support component needed; next177 reuses native b-tree cursor rows, page-image hashes, pointer-map parent/type data, and current-source truncation fences',
            'non_overlap' => 'adds deterministic next-source replay batches after next174 cursor fencing; does not repeat next174 cursor resume generation, next171 transition classification, next166 write admission, overflow freelist release, root collapse, page move, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next177',
            'next_source_summary' => $this->nextSourceSummary(),
            'batch_rows' => $this->batchRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildBatchRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $batches = [];
        foreach ($basePlan->cursorRows() as $row) {
            if ($row['cursor_status'] !== 'readable') {
                continue;
            }

            $batchIndex = (int) $row['batch_index'];
            $batches[$batchIndex][] = $row;
        }

        $rows = [];
        foreach ($batches as $batchIndex => $batchRows) {
            $pageNumbers = array_values(array_map(static fn (array $row): int => (int) $row['page_number'], $batchRows));
            $resumeTokens = array_values(array_map(static fn (array $row): string => (string) $row['resume_token'], $batchRows));
            $pointerMapPages = [];
            foreach ($batchRows as $row) {
                if ($row['next_pointer_map_type'] === null) {
                    continue;
                }
                $pointerMapPages[] = self::pointerMapPageFor((int) $row['page_number']);
            }
            $pointerMapPages = array_values(array_unique($pointerMapPages));
            sort($pointerMapPages);

            $rows[] = [
                'batch_index' => (int) $batchIndex,
                'page_numbers' => $pageNumbers,
                'page_count' => count($pageNumbers),
                'first_page' => min($pageNumbers),
                'last_page' => max($pageNumbers),
                'resume_tokens' => $resumeTokens,
                'resume_token_count' => count($resumeTokens),
                'next_page_hashes' => array_values(array_map(static fn (array $row): string => (string) $row['next_page_hash'], $batchRows)),
                'pointer_map_dependency_pages' => $pointerMapPages,
                'pointer_map_types' => array_values(array_map(static fn (array $row): mixed => $row['next_pointer_map_type'], $batchRows)),
                'pointer_map_parents' => array_values(array_map(static fn (array $row): mixed => $row['next_pointer_map_parent'], $batchRows)),
                'contains_fenced_page' => false,
                'deleted_cell_visible_to_next' => in_array(true, array_column($batchRows, 'deleted_cell_visible_to_next'), true),
                'batch_replay_key' => self::signature(array_merge($pageNumbers, $resumeTokens)),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ((int) $a['batch_index']) <=> ((int) $b['batch_index']));

        return $rows;
    }

    private static function pointerMapPageFor(int $pageNumber): int
    {
        if ($pageNumber < 3) {
            return 2;
        }

        return 2 + (int) (floor(($pageNumber - 3) / 103) * 103);
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
