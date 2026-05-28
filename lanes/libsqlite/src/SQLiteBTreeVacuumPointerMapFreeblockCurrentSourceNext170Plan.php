<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext170Plan
{
    /**
     * @param list<array<string, mixed>> $handoffRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext166Plan $basePlan,
        private readonly array $handoffRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext166Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext166Plan $basePlan): self
    {
        $rows = self::buildHandoffRows($basePlan);
        foreach ($rows as $row) {
            if ($row['read_status'] === 'rejected-truncated-source' && $row['next_readable'] === true) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next170 exposed a truncated source page to the next reader');
            }
            if ($row['write_kind'] === 'leaf-freeblock-page' && $row['deleted_cell_visible_to_next'] === true) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next170 exposed deleted leaf cell bytes to the next reader');
            }
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handoffRows(): array
    {
        return $this->handoffRows;
    }

    /**
     * @return list<int>
     */
    public function nextReadablePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->handoffRows, static fn (array $row): bool => $row['next_readable'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function fencedSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->handoffRows, static fn (array $row): bool => $row['read_status'] === 'rejected-truncated-source'),
        ));
    }

    /**
     * @return list<int>
     */
    public function changedNextPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->handoffRows, static fn (array $row): bool => $row['source_next_changed'] === true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function handoffSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next170-ready',
            'leaf_page' => $this->basePlan->writeAdmissionSummary()['leaf_page'],
            'next_readable_pages' => $this->nextReadablePages(),
            'fenced_source_pages' => $this->fencedSourcePages(),
            'changed_next_pages' => $this->changedNextPages(),
            'pointer_map_pages' => $this->basePlan->pointerMapWritePages(),
            'replacement_overflow_pages' => $this->basePlan->replacementOverflowWritePages(),
            'final_database_page_count' => $this->basePlan->writeAdmissionSummary()['final_database_page_count'],
            'next_read_signature' => self::signature($this->nextReadablePages()),
            'fenced_source_signature' => self::signature($this->fencedSourcePages()),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next166',
                'sqlite-current-source-next170',
            ],
            'dependency_closure' => 'no new support component needed; next170 reuses native b-tree vacuum page images, pointer-map entries, freeblock headers, and current-source admission rows',
            'non_overlap' => 'adds next-reader handoff visibility for freeblock and pointer-map page images after next166 write admission; does not repeat next166 write admission, next163 source admission, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next170',
            'handoff_summary' => $this->handoffSummary(),
            'handoff_rows' => $this->handoffRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildHandoffRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext166Plan $basePlan): array
    {
        $base156 = $basePlan->basePlan->basePlan->basePlan;
        $sourceDatabase = $base156->basePlan->basePlan->basePlan->sourceDatabase;
        $nextDatabase = $base156->databaseAfterAllocation;

        $rows = [];
        foreach ($basePlan->writeRows() as $writeRow) {
            $pageNumber = (int) $writeRow['page_number'];
            $nextReadable = $pageNumber <= $nextDatabase->pageCount() && $writeRow['write_admitted'] === true;
            $sourcePage = $pageNumber <= $sourceDatabase->pageCount() ? $sourceDatabase->page($pageNumber) : null;
            $nextPage = $nextReadable ? $nextDatabase->page($pageNumber) : null;
            $sourcePointerMapEntry = self::pointerMapEntry($sourceDatabase, $pageNumber);
            $nextPointerMapEntry = $nextPage === null ? null : self::pointerMapEntry($nextDatabase, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => $writeRow['write_kind'],
                'next_readable' => $nextReadable,
                'read_status' => $nextReadable ? 'next-source-readable' : 'rejected-truncated-source',
                'source_materialized' => $sourcePage !== null,
                'next_materialized' => $nextPage !== null,
                'source_page_hash' => $sourcePage === null ? null : hash('sha256', $sourcePage),
                'next_page_hash' => $nextPage === null ? null : hash('sha256', $nextPage),
                'source_next_changed' => $sourcePage !== null && $nextPage !== null && $sourcePage !== $nextPage,
                'source_pointer_map_type' => $sourcePointerMapEntry['type_name'] ?? null,
                'source_pointer_map_parent' => $sourcePointerMapEntry['parent_page_number'] ?? null,
                'next_pointer_map_type' => $nextPointerMapEntry['type_name'] ?? null,
                'next_pointer_map_parent' => $nextPointerMapEntry['parent_page_number'] ?? null,
                'pointer_map_changed' => $sourcePointerMapEntry !== $nextPointerMapEntry,
                'source_overflow_next_page' => self::overflowNextPage($sourcePage, (string) $writeRow['write_kind']),
                'next_overflow_next_page' => self::overflowNextPage($nextPage, (string) $writeRow['write_kind']),
                'leaf_freeblock_offset' => $writeRow['leaf_freeblock_offset'],
                'deleted_cell_visible_to_next' => $nextPage !== null && str_contains($nextPage, '_transient_next170'),
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
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next170 could not read uint32');
        }

        return $value[1];
    }

    /**
     * @param list<int> $pageNumbers
     */
    private static function signature(array $pageNumbers): string
    {
        return hash('sha256', implode(',', $pageNumbers));
    }
}
