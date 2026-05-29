<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNext144Plan
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $basePlan,
        public readonly array $rows,
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

    /**
     * @param array<string, mixed> $deleteResult
     */
    public static function indexLeafFromDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResult,
        int $maxTruncatedPages,
        bool $secureDelete = false,
        ?callable $overflowReader = null,
    ): self {
        return self::fromBasePlan(SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan::indexLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $secureDelete,
            $overflowReader,
        ));
    }

    public static function fromBasePlan(SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan $basePlan): self
    {
        $sourceDatabase = $basePlan->basePlan->sourceDatabase;
        $nextDatabase = $basePlan->basePlan->nextDatabase;
        $leafPage = $basePlan->basePlan->deletePlan->leafPageNumber;
        $leafImage = $basePlan->basePlan->deletePlan->leafPageImage;
        $leafHeader = SQLiteBTreePageHeader::parsePage($leafImage, $sourceDatabase->header->pageSize);
        $rows = [[
            'kind' => 'deleted-leaf-freeblock',
            'page_number' => $leafPage,
            'source_page_hash' => hash('sha256', $sourceDatabase->page($leafPage)),
            'next_page_hash' => hash('sha256', $leafImage),
            'source_pointer_map_page' => self::pointerMapPageFor($sourceDatabase, $leafPage),
            'source_pointer_map_type' => self::pointerMapType($sourceDatabase, $leafPage),
            'source_pointer_map_parent' => self::pointerMapParent($sourceDatabase, $leafPage),
            'next_pointer_map_page' => self::pointerMapPageFor($nextDatabase, $leafPage),
            'next_pointer_map_type' => self::pointerMapType($nextDatabase, $leafPage),
            'next_pointer_map_parent' => self::pointerMapParent($nextDatabase, $leafPage),
            'freeblock_count' => count($leafHeader->freeblocks($leafImage)),
            'freeblock_bytes' => $basePlan->basePlan->deletePlan->freeblockBytesAfter,
            'freeblock_status' => $leafHeader->freeblockIntegrityReport($leafImage)['status'],
            'vacuum_status' => 'materialized-leaf-page',
            'freelist_role' => null,
            'materialized' => true,
        ]];

        foreach ($basePlan->rows as $row) {
            $pageNumber = (int) $row['page_number'];
            $materialized = $pageNumber <= $nextDatabase->pageCount();
            $rows[] = [
                'kind' => 'released-overflow-page',
                'page_number' => $pageNumber,
                'chain_position' => $row['chain_position'],
                'source_overflow_next_page' => self::readUInt32($sourceDatabase->page($pageNumber), 0),
                'next_overflow_next_page' => $materialized ? self::readUInt32($nextDatabase->page($pageNumber), 0) : null,
                'source_page_hash' => hash('sha256', $sourceDatabase->page($pageNumber)),
                'next_page_hash' => $materialized ? hash('sha256', $nextDatabase->page($pageNumber)) : null,
                'source_pointer_map_page' => self::pointerMapPageFor($sourceDatabase, $pageNumber),
                'source_pointer_map_type' => $row['current_pointer_map_type'],
                'source_pointer_map_parent' => $row['current_pointer_map_parent'],
                'next_pointer_map_page' => $materialized ? self::pointerMapPageFor($nextDatabase, $pageNumber) : null,
                'next_pointer_map_type' => $row['next_pointer_map_type'],
                'next_pointer_map_parent' => $row['next_pointer_map_parent'],
                'vacuum_status' => $row['vacuum_status'],
                'freelist_role' => $row['freelist_role'],
                'materialized' => $materialized,
            ];
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materializedRows(): array
    {
        return array_values(array_filter($this->rows, static fn (array $row): bool => $row['materialized']));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function truncatedRows(): array
    {
        return array_values(array_filter($this->rows, static fn (array $row): bool => !$row['materialized']));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-pointermap-vacuum-freeblock-current-source-next144',
            'leaf_page' => $this->basePlan->basePlan->deletePlan->leafPageNumber,
            'released_overflow_pages' => $this->basePlan->basePlan->releasedOverflowPages(),
            'surviving_released_overflow_pages' => $this->basePlan->survivingReleasedOverflowPages(),
            'truncated_released_overflow_pages' => $this->basePlan->truncatedReleasedOverflowPages(),
            'materialized_page_numbers' => array_column($this->materializedRows(), 'page_number'),
            'truncated_page_numbers' => array_column($this->truncatedRows(), 'page_number'),
            'final_database_page_count' => $this->basePlan->basePlan->nextDatabase->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->basePlan->nextDatabase->freelistPageNumbers(),
            'updated_page_numbers' => $this->basePlan->basePlan->updatedPageNumbers(),
            'rows' => $this->rows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    private static function pointerMapPageFor(SQLiteDatabase $database, int $pageNumber): ?int
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapPageFor($pageNumber);
    }

    private static function pointerMapType(SQLiteDatabase $database, int $pageNumber): ?string
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->typeName();
    }

    private static function pointerMapParent(SQLiteDatabase $database, int $pageNumber): ?int
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->parentPageNumber;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree pointer-map vacuum freeblock next144 could not read uint32');
        }

        return $value[1];
    }
}
