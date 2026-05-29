<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeIndexMutationCurrent
{
    /**
     * @param list<mixed> $deleteRecordValues
     * @param list<mixed> $insertRecordValues
     * @param callable(int, int): list<int>|null $overflowPageNumbers
     * @return array{
     *   page:string,
     *   deleted_record_values:list<mixed>,
     *   inserted_record_values:list<mixed>,
     *   obsolete_overflow_page_numbers:list<int>,
     *   before:array<string,mixed>,
     *   after_delete:array<string,mixed>,
     *   after_insert:array<string,mixed>,
     *   reused_freeblock_offset:int,
     *   inserted_cell_offset:int,
     *   mutation_applied:bool,
     *   non_overlap:string,
     *   dependency_closure:string
     * }
     */
    public static function replaceRecordValuesReusingFreedCell(
        string $page,
        array $deleteRecordValues,
        array $insertRecordValues,
        callable $overflowPageNumbers = null,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        int $textEncoding = 1,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): array {
        $usableSize ??= $pageSize;
        $beforeHeader = SQLiteBTreePageHeader::parsePage($page, $pageSize, $headerOffset);
        self::assertIndexLeaf($beforeHeader);

        $deletedCellOffset = self::recordCellOffset($page, $beforeHeader, $deleteRecordValues, $usableSize, $textEncoding, $overflowReader);
        if (self::containsRecordValues($page, $beforeHeader, $insertRecordValues, $usableSize, $textEncoding, $overflowReader)) {
            throw new \InvalidArgumentException('SQLite b-tree index mutation replacement record already exists');
        }

        $delete = $overflowPageNumbers === null
            ? [
                'page' => SQLiteIndexLeafPage::deleteCellByRecordValues(
                    $page,
                    $deleteRecordValues,
                    $pageSize,
                    $headerOffset,
                    $usableSize,
                    $textEncoding,
                    $secureDelete,
                    $overflowReader,
                ),
                'obsolete_overflow_page_numbers' => [],
            ]
            : SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
                $page,
                $deleteRecordValues,
                $overflowPageNumbers,
                $pageSize,
                $headerOffset,
                $usableSize,
                $textEncoding,
                $secureDelete,
                $overflowReader,
            );

        $afterDeleteHeader = SQLiteBTreePageHeader::parsePage($delete['page'], $pageSize, $headerOffset);
        $freedBlock = self::freeblockContaining($afterDeleteHeader, $delete['page'], $deletedCellOffset, $usableSize);
        if ($freedBlock === null) {
            throw new \RuntimeException('SQLite b-tree index mutation did not expose the deleted cell as reusable freeblock space');
        }

        $mutatedPage = SQLiteIndexLeafPage::insertCellByRecordValuesReusingFreeblock(
            $delete['page'],
            $insertRecordValues,
            $pageSize,
            $headerOffset,
            $usableSize,
            $textEncoding,
        );
        $afterInsertHeader = SQLiteBTreePageHeader::parsePage($mutatedPage, $pageSize, $headerOffset);
        $insertedCellOffset = self::recordCellOffset($mutatedPage, $afterInsertHeader, $insertRecordValues, $usableSize, $textEncoding, $overflowReader);

        return [
            'page' => $mutatedPage,
            'deleted_record_values' => array_values($deleteRecordValues),
            'inserted_record_values' => array_values($insertRecordValues),
            'obsolete_overflow_page_numbers' => array_values($delete['obsolete_overflow_page_numbers']),
            'before' => self::headerSummary($beforeHeader, $page),
            'after_delete' => self::headerSummary($afterDeleteHeader, $delete['page']),
            'after_insert' => self::headerSummary($afterInsertHeader, $mutatedPage),
            'reused_freeblock_offset' => $freedBlock->offset,
            'inserted_cell_offset' => $insertedCellOffset,
            'mutation_applied' => $insertedCellOffset === $freedBlock->offset
                && self::containsRecordValues($mutatedPage, $afterInsertHeader, $insertRecordValues, $usableSize, $textEncoding, $overflowReader)
                && !self::containsRecordValues($mutatedPage, $afterInsertHeader, $deleteRecordValues, $usableSize, $textEncoding, $overflowReader),
            'non_overlap' => 'index leaf current mutation reuses the deleted cell freeblock; it does not repeat page relocation, root collapse, overflow freelist release planning, or numbered current-source variants',
            'dependency_closure' => 'no new support component needed; reuses SQLiteIndexLeafPage, SQLiteIndexCell, record comparison, overflow readers, and B-tree page header freeblock parsing',
        ];
    }

    /**
     * @param list<array{delete:list<mixed>,insert:list<mixed>}> $mutations
     * @param callable(int, int): list<int>|null $overflowPageNumbers
     * @return array{page:string,mutations:list<array<string,mixed>>,obsolete_overflow_page_numbers:list<int>,mutation_count:int,all_mutations_applied:bool}
     */
    public static function applyReplacementBatch(
        string $page,
        array $mutations,
        callable $overflowPageNumbers = null,
        int $pageSize = 512,
        int $headerOffset = 0,
        ?int $usableSize = null,
        int $textEncoding = 1,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): array {
        if ($mutations === []) {
            throw new \InvalidArgumentException('SQLite b-tree index mutation batch requires at least one mutation');
        }

        $currentPage = $page;
        $applied = [];
        $obsolete = [];
        foreach ($mutations as $mutation) {
            if (!isset($mutation['delete'], $mutation['insert']) || !is_array($mutation['delete']) || !is_array($mutation['insert'])) {
                throw new \InvalidArgumentException('SQLite b-tree index mutation batch entries require delete and insert record value lists');
            }

            $result = self::replaceRecordValuesReusingFreedCell(
                $currentPage,
                $mutation['delete'],
                $mutation['insert'],
                $overflowPageNumbers,
                $pageSize,
                $headerOffset,
                $usableSize,
                $textEncoding,
                $secureDelete,
                $overflowReader,
            );
            $currentPage = $result['page'];
            $obsolete = array_values(array_unique(array_merge($obsolete, $result['obsolete_overflow_page_numbers'])));
            $applied[] = [
                'deleted_record_values' => $result['deleted_record_values'],
                'inserted_record_values' => $result['inserted_record_values'],
                'reused_freeblock_offset' => $result['reused_freeblock_offset'],
                'inserted_cell_offset' => $result['inserted_cell_offset'],
                'mutation_applied' => $result['mutation_applied'],
            ];
        }

        sort($obsolete);

        return [
            'page' => $currentPage,
            'mutations' => $applied,
            'obsolete_overflow_page_numbers' => $obsolete,
            'mutation_count' => count($applied),
            'all_mutations_applied' => !in_array(false, array_column($applied, 'mutation_applied'), true),
        ];
    }

    private static function assertIndexLeaf(SQLiteBTreePageHeader $header): void
    {
        if ($header->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite b-tree index mutation requires an index leaf page');
        }
    }

    /**
     * @param list<mixed> $recordValues
     */
    private static function recordCellOffset(
        string $page,
        SQLiteBTreePageHeader $header,
        array $recordValues,
        int $usableSize,
        int $textEncoding,
        ?callable $overflowReader,
    ): int {
        foreach (SQLiteIndexCell::parsePageCells($page, $header, $usableSize, $overflowReader) as $cell) {
            if (self::recordValuesEqual($cell->record($textEncoding)->values, $recordValues)) {
                return $cell->offset;
            }
        }

        throw new \InvalidArgumentException('SQLite b-tree index mutation record was not found');
    }

    /**
     * @param list<mixed> $recordValues
     */
    private static function containsRecordValues(
        string $page,
        SQLiteBTreePageHeader $header,
        array $recordValues,
        int $usableSize,
        int $textEncoding,
        ?callable $overflowReader,
    ): bool {
        foreach (SQLiteIndexCell::parsePageCells($page, $header, $usableSize, $overflowReader) as $cell) {
            if (self::recordValuesEqual($cell->record($textEncoding)->values, $recordValues)) {
                return true;
            }
        }

        return false;
    }

    private static function freeblockContaining(SQLiteBTreePageHeader $header, string $page, int $offset, int $usableSize): ?SQLiteBTreeFreeblock
    {
        foreach ($header->freeblocks($page, $usableSize) as $freeblock) {
            if ($freeblock->offset <= $offset && $offset < $freeblock->endOffset()) {
                return $freeblock;
            }
        }

        return null;
    }

    /**
     * @return array{page_type:string,cell_count:int,first_freeblock_offset:int,fragmented_free_bytes:int,freeblock_count:int,free_space_bytes:int,integrity_status:string,cell_pointers:list<int>}
     */
    private static function headerSummary(SQLiteBTreePageHeader $header, string $page): array
    {
        return [
            'page_type' => $header->pageType,
            'cell_count' => $header->cellCount,
            'first_freeblock_offset' => $header->firstFreeblockOffset,
            'fragmented_free_bytes' => $header->fragmentedFreeBytes,
            'freeblock_count' => count($header->freeblocks($page)),
            'free_space_bytes' => $header->freeSpaceBytes($page),
            'integrity_status' => $header->freeblockIntegrityReport($page)['status'],
            'cell_pointers' => $header->cellPointers($page),
        ];
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function recordValuesEqual(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }

        foreach ($left as $index => $leftValue) {
            $comparison = SQLiteAffinityComparison::compare($leftValue, $right[$index], 'NONE', 'NONE', 'BINARY') ?? 0;
            if ($comparison !== 0) {
                return false;
            }
        }

        return true;
    }
}
