<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext172Plan
{
    /**
     * @param list<array<string, mixed>> $materializationRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext166Plan $basePlan,
        public readonly SQLiteDatabase $materializedDatabase,
        private readonly array $materializationRows,
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
        $base156 = $basePlan->basePlan->basePlan->basePlan;
        $sourceDatabase = $base156->basePlan->basePlan->basePlan->sourceDatabase;
        $finalDatabase = $base156->databaseAfterAllocation;
        $pageImages = $base156->pageImages();
        $admitted = array_fill_keys($basePlan->admittedWritePages(), true);
        $rejected = array_fill_keys($basePlan->rejectedWritePages(), true);
        $pages = [];
        $rows = [];

        for ($pageNumber = 1; $pageNumber <= $finalDatabase->pageCount(); $pageNumber++) {
            $sourcePage = $pageNumber <= $sourceDatabase->pageCount() ? $sourceDatabase->page($pageNumber) : str_repeat("\0", $sourceDatabase->header->pageSize);
            $nextPage = isset($admitted[$pageNumber]) && isset($pageImages[$pageNumber])
                ? $pageImages[$pageNumber]
                : $sourcePage;
            $pages[] = $nextPage;
            $rows[] = self::rowForPage($sourceDatabase, $finalDatabase, $pageNumber, $sourcePage, $nextPage, isset($admitted[$pageNumber]));
        }

        foreach ($basePlan->rejectedWritePages() as $pageNumber) {
            if ($pageNumber <= $finalDatabase->pageCount()) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next172 retained a rejected page inside the final database image');
            }
            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => 'truncated-current-source-page',
                'write_admitted' => false,
                'source_materialized' => $pageNumber <= $sourceDatabase->pageCount(),
                'final_materialized' => false,
                'source_page_hash' => $pageNumber <= $sourceDatabase->pageCount() ? hash('sha256', $sourceDatabase->page($pageNumber)) : null,
                'final_page_hash' => null,
                'page_changed' => null,
                'pointer_map_type' => null,
                'pointer_map_parent' => null,
                'overflow_next_page' => null,
                'freeblock_offset' => null,
            ];
        }

        $materializedDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
        if ($materializedDatabase->toBytes() !== $finalDatabase->toBytes()) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next172 materialized image does not match the allocated next database image');
        }

        return new self($basePlan, $materializedDatabase, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materializationRows(): array
    {
        return $this->materializationRows;
    }

    /**
     * @return list<int>
     */
    public function changedPageNumbers(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->materializationRows, static fn (array $row): bool => $row['page_changed'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function unchangedPageNumbers(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->materializationRows, static fn (array $row): bool => $row['page_changed'] === false),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedPageNumbers(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->materializationRows, static fn (array $row): bool => $row['write_kind'] === 'truncated-current-source-page'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function materializationSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next172-ready',
            'source_page_count' => $this->basePlan->writeAdmissionSummary()['final_database_page_count'] + count($this->basePlan->rejectedWritePages()),
            'final_database_page_count' => $this->materializedDatabase->pageCount(),
            'changed_page_numbers' => $this->changedPageNumbers(),
            'unchanged_page_count' => count($this->unchangedPageNumbers()),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'materialized_database_hash' => hash('sha256', $this->materializedDatabase->toBytes()),
            'admitted_write_pages' => $this->basePlan->admittedWritePages(),
            'rejected_write_pages' => $this->basePlan->rejectedWritePages(),
            'pointer_map_write_pages' => $this->basePlan->pointerMapWritePages(),
            'leaf_page' => $this->basePlan->writeAdmissionSummary()['leaf_page'],
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next166',
                'sqlite-current-source-next172',
            ],
            'dependency_closure' => 'no new support component needed; next172 reuses native database page images, b-tree freeblock pages, overflow allocation, and pointer-map write admission from next166',
            'non_overlap' => 'adds complete database-image materialization and rejected-current-source truncation fencing after next166 write admission; does not repeat next166 write admission, next163 source fencing, overflow freelist release, page relocation, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next172',
            'materialization_summary' => $this->materializationSummary(),
            'materialization_rows' => $this->materializationRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function rowForPage(
        SQLiteDatabase $sourceDatabase,
        SQLiteDatabase $finalDatabase,
        int $pageNumber,
        string $sourcePage,
        string $nextPage,
        bool $writeAdmitted,
    ): array {
        $entry = self::pointerMapEntry($finalDatabase, $pageNumber);

        return [
            'page_number' => $pageNumber,
            'write_kind' => self::writeKind($finalDatabase, $pageNumber, $writeAdmitted),
            'write_admitted' => $writeAdmitted,
            'source_materialized' => $pageNumber <= $sourceDatabase->pageCount(),
            'final_materialized' => true,
            'source_page_hash' => hash('sha256', $sourcePage),
            'final_page_hash' => hash('sha256', $nextPage),
            'page_changed' => $sourcePage !== $nextPage,
            'pointer_map_type' => $entry['type_name'] ?? null,
            'pointer_map_parent' => $entry['parent_page_number'] ?? null,
            'overflow_next_page' => self::overflowNextPage($nextPage, $entry['type_name'] ?? null),
            'freeblock_offset' => $pageNumber === (int) $finalDatabase->header->largestRootBtreePage ? self::readUInt16($nextPage, 1) : null,
        ];
    }

    private static function writeKind(SQLiteDatabase $database, int $pageNumber, bool $writeAdmitted): string
    {
        if ($pageNumber === 1) {
            return 'database-header';
        }
        if ($database->isPointerMapPage($pageNumber)) {
            return 'pointer-map-page';
        }
        if ($writeAdmitted) {
            $entry = self::pointerMapEntry($database, $pageNumber);
            if (($entry['type_name'] ?? null) === 'first-overflow-page' || ($entry['type_name'] ?? null) === 'overflow-page') {
                return 'replacement-overflow-page';
            }

            return 'changed-btree-page';
        }

        return 'unchanged-page';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pointerMapEntry(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber < 2 || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        try {
            return $database->pointerMapEntryForPage($pageNumber)->toArray();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function overflowNextPage(string $page, ?string $pointerMapType): ?int
    {
        if ($pointerMapType !== 'first-overflow-page' && $pointerMapType !== 'overflow-page') {
            return null;
        }

        $value = unpack('N', substr($page, 0, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next172 could not read uint32');
        }

        return $value[1];
    }

    private static function readUInt16(string $bytes, int $offset): int
    {
        $value = unpack('n', substr($bytes, $offset, 2));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next172 could not read uint16');
        }

        return $value[1];
    }
}
