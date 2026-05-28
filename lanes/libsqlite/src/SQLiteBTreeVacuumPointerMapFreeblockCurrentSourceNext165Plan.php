<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext165Plan
{
    /**
     * @param list<array<string, mixed>> $sourceNextRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext162Plan $basePlan,
        private readonly array $sourceNextRows,
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
    ): self {
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext162Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext162Plan $basePlan): self
    {
        return new self($basePlan, self::buildSourceNextRows($basePlan));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourceNextRows(): array
    {
        return $this->sourceNextRows;
    }

    /**
     * @return list<int>
     */
    public function changedWritablePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceNextRows, static fn (array $row): bool => $row['write_allowed'] && $row['page_changed']),
        ));
    }

    /**
     * @return list<int>
     */
    public function unchangedWritablePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceNextRows, static fn (array $row): bool => $row['write_allowed'] && !$row['page_changed']),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->sourceNextRows, static fn (array $row): bool => !$row['write_allowed']),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next165',
            'leaf_page' => $this->basePlan->toArray()['leaf_page'],
            'changed_writable_pages' => $this->changedWritablePages(),
            'unchanged_writable_pages' => $this->unchangedWritablePages(),
            'rejected_current_source_pages' => $this->rejectedCurrentSourcePages(),
            'replacement_overflow_pages' => $this->basePlan->basePlan->replacementOverflowPages(),
            'replacement_overflow_next_pages' => $this->basePlan->basePlan->replacementOverflowNextPages(),
            'replacement_pointer_map_parents' => $this->basePlan->basePlan->replacementPointerMapParents(),
            'source_next_rows' => $this->sourceNextRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSourceNextRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext162Plan $basePlan): array
    {
        $sourceDatabase = $basePlan->basePlan->basePlan->basePlan->basePlan->basePlan->sourceDatabase;
        $nextDatabase = $basePlan->basePlan->basePlan->databaseAfterAllocation;
        $rows = [];

        foreach ($basePlan->writeRows() as $writeRow) {
            $pageNumber = (int) $writeRow['page_number'];
            $writeAllowed = (bool) $writeRow['write_allowed'];
            $currentPage = $pageNumber <= $sourceDatabase->pageCount() ? $sourceDatabase->page($pageNumber) : null;
            $nextPage = $writeAllowed && $pageNumber <= $nextDatabase->pageCount() ? $nextDatabase->page($pageNumber) : null;
            $currentPointerMapEntry = self::pointerMapEntry($sourceDatabase, $pageNumber);
            $nextPointerMapEntry = $nextPage !== null ? self::pointerMapEntry($nextDatabase, $pageNumber) : null;

            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => $writeRow['write_kind'],
                'write_allowed' => $writeAllowed,
                'current_materialized' => $currentPage !== null,
                'next_materialized' => $nextPage !== null,
                'current_page_hash' => $currentPage === null ? null : hash('sha256', $currentPage),
                'next_page_hash' => $nextPage === null ? null : hash('sha256', $nextPage),
                'page_changed' => $currentPage !== null && $nextPage !== null && $currentPage !== $nextPage,
                'current_overflow_next_page' => self::overflowNextPage($currentPage, (string) $writeRow['write_kind']),
                'next_overflow_next_page' => self::overflowNextPage($nextPage, (string) $writeRow['write_kind']),
                'current_pointer_map_type' => $currentPointerMapEntry['type_name'] ?? null,
                'current_pointer_map_parent' => $currentPointerMapEntry['parent_page_number'] ?? null,
                'next_pointer_map_type' => $nextPointerMapEntry['type_name'] ?? null,
                'next_pointer_map_parent' => $nextPointerMapEntry['parent_page_number'] ?? null,
                'pointer_map_changed' => $currentPointerMapEntry !== $nextPointerMapEntry,
                'pointer_map_cell_offsets' => $writeRow['pointer_map_cell_offsets'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber < 2 || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
    }

    private static function overflowNextPage(?string $page, string $writeKind): ?int
    {
        if ($page === null || $writeKind !== 'replacement-overflow-page') {
            return null;
        }

        $value = unpack('N', substr($page, 0, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next165 could not read uint32');
        }

        return $value[1];
    }
}
