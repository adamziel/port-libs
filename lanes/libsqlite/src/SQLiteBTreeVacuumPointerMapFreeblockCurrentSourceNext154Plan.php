<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext154Plan
{
    /**
     * @param list<array<string, mixed>> $currentSourceRows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $basePlan,
        private readonly array $currentSourceRows,
        private readonly array $freeblockSummary,
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
        bool $secureDelete = false,
    ): self {
        return self::fromBasePlan(SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $basePlan): self
    {
        return new self(
            $basePlan,
            self::buildCurrentSourceRows($basePlan),
            self::buildFreeblockSummary($basePlan),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currentSourceRows(): array
    {
        return $this->currentSourceRows;
    }

    /**
     * @return array<string, mixed>
     */
    public function freeblockSummary(): array
    {
        return $this->freeblockSummary;
    }

    /**
     * @return list<int>
     */
    public function mismatchedCurrentSourceNextPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter(
                $this->currentSourceRows,
                static fn (array $row): bool => $row['current_source_next_status'] !== 'matches-delete-chain',
            ),
        ));
    }

    /**
     * @return list<int>
     */
    public function survivingCurrentSourceNextPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter(
                $this->currentSourceRows,
                static fn (array $row): bool => $row['post_vacuum_materialized'] === true,
            ),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next154',
            'leaf_page' => $this->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->releasedOverflowPages(),
            'surviving_released_overflow_pages' => $this->basePlan->survivingReleasedOverflowPages(),
            'truncated_released_overflow_pages' => $this->basePlan->truncatedReleasedOverflowPages(),
            'truncated_pointer_map_pages' => $this->basePlan->truncatedPointerMapPages(),
            'surviving_current_source_next_pages' => $this->survivingCurrentSourceNextPages(),
            'mismatched_current_source_next_pages' => $this->mismatchedCurrentSourceNextPages(),
            'freeblock_summary' => $this->freeblockSummary,
            'current_source_next_rows' => $this->currentSourceRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $basePlan): array
    {
        $sourceDatabase = $basePlan->basePlan->sourceDatabase;
        $nextDatabase = $basePlan->basePlan->nextDatabase;
        $releasedPages = $basePlan->basePlan->releasedOverflowPages();
        $releasedLookup = array_fill_keys($releasedPages, true);
        $survivingLookup = array_fill_keys($basePlan->survivingReleasedOverflowPages(), true);
        $truncatedLookup = array_fill_keys($basePlan->truncatedReleasedOverflowPages(), true);
        $rows = [];

        foreach ($releasedPages as $position => $pageNumber) {
            $currentNextPage = self::readPageNextPointer($sourceDatabase, $pageNumber);
            $expectedNextPage = $releasedPages[$position + 1] ?? 0;
            $postVacuumMaterialized = isset($survivingLookup[$pageNumber]) && $pageNumber <= $nextDatabase->pageCount();
            $postVacuumNextPage = $postVacuumMaterialized ? self::readPageNextPointer($nextDatabase, $pageNumber) : null;
            $entry = $sourceDatabase->pointerMapEntryForPage($pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'chain_position' => $position,
                'current_source_next_page' => $currentNextPage,
                'expected_next_page_from_delete_chain' => $expectedNextPage,
                'current_source_next_status' => $currentNextPage === $expectedNextPage
                    ? 'matches-delete-chain'
                    : 'differs-from-delete-chain',
                'current_pointer_map_type' => $entry->typeName(),
                'current_pointer_map_parent' => $entry->parentPageNumber,
                'post_vacuum_materialized' => $postVacuumMaterialized,
                'post_vacuum_next_page' => $postVacuumNextPage,
                'post_vacuum_status' => isset($survivingLookup[$pageNumber])
                    ? 'survives-as-freelist-page'
                    : (isset($truncatedLookup[$pageNumber]) ? 'truncated-by-vacuum' : 'not-released'),
                'next_pointer_targets_released_page' => $currentNextPage !== 0 && isset($releasedLookup[$currentNextPage]),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildFreeblockSummary(SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $basePlan): array
    {
        $pageSize = $basePlan->basePlan->sourceDatabase->header->pageSize;
        $header = SQLiteBTreePageHeader::parsePage($basePlan->basePlan->deletePlan->leafPageImage, $pageSize);
        $integrity = $header->freeblockIntegrityReport($basePlan->basePlan->deletePlan->leafPageImage);

        return [
            'leaf_page' => $basePlan->basePlan->deletePlan->leafPageNumber,
            'leaf_page_type' => $basePlan->basePlan->deletePlan->leafPageType,
            'freeblock_offset' => $header->firstFreeblockOffset,
            'fragmented_free_bytes' => $header->fragmentedFreeBytes,
            'cell_count_after_delete' => $header->cellCount,
            'integrity_status' => $integrity['status'],
            'freeblock_count' => count($integrity['freeblocks'] ?? []),
            'freeblock_total_bytes' => array_sum(array_map(
                static fn (array $freeblock): int => (int) $freeblock['size'],
                $integrity['freeblocks'] ?? [],
            )),
        ];
    }

    private static function readPageNextPointer(SQLiteDatabase $database, int $pageNumber): int
    {
        $value = unpack('N', substr($database->page($pageNumber), 0, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next154 could not read overflow next pointer');
        }

        return $value[1];
    }
}
