<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext174Plan
{
    /**
     * @param list<array<string, mixed>> $cursorRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext170Plan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext170Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ), $batchSize);
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext170Plan $basePlan, int $batchSize = 2): self
    {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size');
        }

        $rows = self::buildCursorRows($basePlan, $batchSize);
        foreach ($rows as $row) {
            if ($row['cursor_status'] === 'fenced' && $row['resume_token'] !== null) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock next174 exposed a resume token for a fenced source page');
            }
            if ($row['cursor_status'] === 'readable' && $row['next_page_hash'] === null) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock next174 admitted a readable page without a next page image hash');
            }
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
     * @return list<int>
     */
    public function readableCursorPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->cursorRows, static fn (array $row): bool => $row['cursor_status'] === 'readable'),
        ));
    }

    /**
     * @return list<int>
     */
    public function fencedCursorPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->cursorRows, static fn (array $row): bool => $row['cursor_status'] === 'fenced'),
        ));
    }

    /**
     * @return list<string>
     */
    public function resumeTokens(): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['resume_token'],
            array_filter($this->cursorRows, static fn (array $row): bool => $row['resume_token'] !== null),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function cursorSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next174-ready',
            'leaf_page' => $this->basePlan->handoffSummary()['leaf_page'],
            'readable_cursor_pages' => $this->readableCursorPages(),
            'fenced_cursor_pages' => $this->fencedCursorPages(),
            'resume_tokens' => $this->resumeTokens(),
            'cursor_signature' => self::signature(array_column($this->cursorRows, 'cursor_status')),
            'readable_signature' => self::signature($this->readableCursorPages()),
            'fenced_signature' => self::signature($this->fencedCursorPages()),
            'final_database_page_count' => $this->basePlan->handoffSummary()['final_database_page_count'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next170',
                'sqlite-current-source-next174',
            ],
            'dependency_closure' => 'no new support component needed; next174 reuses native b-tree current-source handoff rows, pointer-map hashes, and page-image hashes',
            'non_overlap' => 'adds bounded next-reader cursor resume rows over next170 readable/fenced handoff pages; does not repeat next170 visibility, next166 write admission, page relocation, root collapse, overflow freelist release, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next174',
            'cursor_summary' => $this->cursorSummary(),
            'cursor_rows' => $this->cursorRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCursorRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext170Plan $basePlan, int $batchSize): array
    {
        $readableSeen = 0;
        $rows = [];

        foreach ($basePlan->handoffRows() as $row) {
            $pageNumber = (int) $row['page_number'];
            $readable = $row['next_readable'] === true;
            $batchIndex = $readable ? intdiv($readableSeen, $batchSize) : null;
            $positionInBatch = $readable ? $readableSeen % $batchSize : null;
            $readableSeen += $readable ? 1 : 0;

            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => $row['write_kind'],
                'cursor_status' => $readable ? 'readable' : 'fenced',
                'batch_index' => $batchIndex,
                'position_in_batch' => $positionInBatch,
                'resume_token' => $readable ? self::resumeToken($pageNumber, $batchIndex ?? 0, $positionInBatch ?? 0, $row) : null,
                'source_page_hash' => $row['source_page_hash'],
                'next_page_hash' => $row['next_page_hash'],
                'next_pointer_map_type' => $row['next_pointer_map_type'],
                'next_pointer_map_parent' => $row['next_pointer_map_parent'],
                'pointer_map_changed' => $row['pointer_map_changed'],
                'deleted_cell_visible_to_next' => $row['deleted_cell_visible_to_next'],
                'read_status' => $row['read_status'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function resumeToken(int $pageNumber, int $batchIndex, int $positionInBatch, array $row): string
    {
        return hash('sha256', implode('|', [
            'next174',
            (string) $pageNumber,
            (string) $batchIndex,
            (string) $positionInBatch,
            (string) $row['write_kind'],
            (string) $row['next_page_hash'],
            (string) ($row['next_pointer_map_type'] ?? ''),
            (string) ($row['next_pointer_map_parent'] ?? ''),
        ]));
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
