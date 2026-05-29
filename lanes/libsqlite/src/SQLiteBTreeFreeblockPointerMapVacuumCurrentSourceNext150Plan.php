<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext150Plan
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext93Plan $basePlan,
        public readonly array $rows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDatabaseDeleteResults(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $deleteResults,
        int $maxTruncatedPages,
        bool $secureDelete = false,
        bool $clearCoalescedFragments = true,
    ): self {
        return self::fromBasePlan(SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext93Plan::fromDatabaseDeleteResults(
            $database,
            $leafPageNumber,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
            $clearCoalescedFragments,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext93Plan $basePlan): self
    {
        $sourceByPage = self::sourceByReleasedPage($basePlan);
        $rows = [self::leafRow($basePlan)];

        foreach ($basePlan->vacuumPlan->pointerMapVacuumTransitions() as $transition) {
            $pageNumber = (int) $transition['page_number'];
            $materialized = $pageNumber <= $basePlan->nextDatabase->pageCount();
            $rows[] = [
                'kind' => 'released-overflow-page',
                'source' => $sourceByPage[$pageNumber] ?? null,
                'page_number' => $pageNumber,
                'pointer_map_page' => $basePlan->sourceDatabase->pointerMapPageFor($pageNumber),
                'source_pointer_map_type' => $transition['current_type_name'],
                'source_pointer_map_parent' => self::pointerMapParent($basePlan->sourceDatabase, $pageNumber),
                'free_pointer_map_type' => $transition['next_type_name'],
                'truncated_pointer_map_type' => $transition['truncated_type_name'],
                'vacuum_status' => $transition['status'],
                'materialized' => $materialized,
                'source_overflow_next_page' => self::readUInt32($basePlan->sourceDatabase->page($pageNumber), 0),
                'next_overflow_next_page' => $materialized ? self::readUInt32($basePlan->nextDatabase->page($pageNumber), 0) : null,
                'source_page_hash' => hash('sha256', $basePlan->sourceDatabase->page($pageNumber)),
                'next_page_hash' => $materialized ? hash('sha256', $basePlan->nextDatabase->page($pageNumber)) : null,
                'freelist_role' => $materialized ? self::freelistRole($basePlan->nextDatabase, $pageNumber) : null,
            ];
        }

        foreach ($basePlan->truncatedPageNumbers() as $pageNumber) {
            if (!$basePlan->sourceDatabase->isPointerMapPage($pageNumber)) {
                continue;
            }
            $rows[] = [
                'kind' => 'truncated-pointer-map-page',
                'source' => null,
                'page_number' => $pageNumber,
                'pointer_map_page' => $pageNumber,
                'source_pointer_map_type' => null,
                'source_pointer_map_parent' => null,
                'free_pointer_map_type' => null,
                'truncated_pointer_map_type' => 'pointer-map-page',
                'vacuum_status' => 'truncated-from-database',
                'materialized' => false,
                'source_overflow_next_page' => null,
                'next_overflow_next_page' => null,
                'source_page_hash' => hash('sha256', $basePlan->sourceDatabase->page($pageNumber)),
                'next_page_hash' => null,
                'freelist_role' => null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['page_number'], $a['kind']] <=> [$b['page_number'], $b['kind']]);

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
     * @return list<int>
     */
    public function truncatedPointerMapPages(): array
    {
        return array_values(array_filter(
            $this->basePlan->truncatedPageNumbers(),
            fn (int $pageNumber): bool => $this->basePlan->sourceDatabase->isPointerMapPage($pageNumber),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freeblock-pointermap-vacuum-current-source-next150',
            'leaf_page' => $this->basePlan->coalescePlan->pageNumber,
            'leaf_page_type' => $this->basePlan->coalescePlan->pageType,
            'released_overflow_pages' => $this->basePlan->vacuumPlan->releasedOverflowPages(),
            'truncated_page_numbers' => $this->basePlan->truncatedPageNumbers(),
            'truncated_pointer_map_pages' => $this->truncatedPointerMapPages(),
            'surviving_freelist_page_numbers' => $this->basePlan->survivingFreelistPageNumbers(),
            'materialized_page_numbers' => array_column($this->materializedRows(), 'page_number'),
            'truncated_row_page_numbers' => array_column($this->truncatedRows(), 'page_number'),
            'final_database_page_count' => $this->basePlan->nextDatabase->pageCount(),
            'updated_page_numbers' => $this->basePlan->updatedPageNumbers(),
            'rows' => $this->rows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function leafRow(SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext93Plan $basePlan): array
    {
        $leafPageNumber = $basePlan->coalescePlan->pageNumber;
        $leafImage = $basePlan->nextDatabase->page($leafPageNumber);
        $leafHeader = SQLiteBTreePageHeader::parsePage($leafImage, $basePlan->sourceDatabase->header->pageSize);

        return [
            'kind' => 'deleted-leaf-freeblock',
            'source' => 'coalesced-delete-leaf',
            'page_number' => $leafPageNumber,
            'pointer_map_page' => $basePlan->sourceDatabase->pointerMapPageFor($leafPageNumber),
            'source_pointer_map_type' => $basePlan->sourceDatabase->pointerMapEntryForPage($leafPageNumber)->typeName(),
            'source_pointer_map_parent' => $basePlan->sourceDatabase->pointerMapEntryForPage($leafPageNumber)->parentPageNumber,
            'free_pointer_map_type' => $basePlan->nextDatabase->pointerMapEntryForPage($leafPageNumber)->typeName(),
            'truncated_pointer_map_type' => null,
            'vacuum_status' => 'materialized-leaf-page',
            'materialized' => true,
            'source_overflow_next_page' => null,
            'next_overflow_next_page' => null,
            'source_page_hash' => hash('sha256', $basePlan->sourceDatabase->page($leafPageNumber)),
            'next_page_hash' => hash('sha256', $leafImage),
            'freelist_role' => null,
            'freeblock_offsets' => array_column($basePlan->coalescePlan->afterFreeblocks, 'offset'),
            'freeblock_sizes' => array_column($basePlan->coalescePlan->afterFreeblocks, 'size'),
            'fragmented_bytes_before' => $basePlan->coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $basePlan->coalescePlan->fragmentedBytesAfter,
            'coalesced_fragment_bytes' => $basePlan->coalescePlan->coalescedFragmentBytes,
            'freeblock_status' => $leafHeader->freeblockIntegrityReport($leafImage)['status'],
            'current_next_fragment_bytes' => $leafHeader->freeblockCurrentNextFragmentReport($leafImage)['current_next_fragment_bytes'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function sourceByReleasedPage(SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext93Plan $basePlan): array
    {
        $sourceByPage = [];
        foreach ($basePlan->vacuumPlan->releasePlan->sources as $source) {
            foreach ($source['pages'] as $pageNumber) {
                $sourceByPage[(int) $pageNumber] = (string) $source['source'];
            }
        }

        return $sourceByPage;
    }

    private static function pointerMapParent(SQLiteDatabase $database, int $pageNumber): ?int
    {
        if (!$database->isAutoVacuum() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->parentPageNumber;
    }

    private static function freelistRole(SQLiteDatabase $database, int $pageNumber): ?string
    {
        foreach ($database->freelistTrunkPages() as $trunkPage) {
            if ($trunkPage->pageNumber === $pageNumber) {
                return 'freelist-trunk';
            }
            if (in_array($pageNumber, $trunkPage->leafPageNumbers, true)) {
                return 'freelist-leaf';
            }
        }

        return null;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree freeblock pointer-map vacuum next150 could not read uint32');
        }

        return $value[1];
    }
}
