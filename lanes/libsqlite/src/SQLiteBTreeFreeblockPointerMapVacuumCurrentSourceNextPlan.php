<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNextPlan
{
    /**
     * @param array<int, string> $pageImages
     * @param list<array<string, mixed>> $leafFreeblockTransitions
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteDatabase $coalescedDatabase,
        public readonly SQLiteDatabase $nextDatabase,
        public readonly array $pageImages,
        public readonly array $leafFreeblockTransitions,
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
        $coalescePlan = SQLiteBTreeFreeblockCoalescePlan::fromDatabasePage(
            $database,
            $leafPageNumber,
            $clearCoalescedFragments,
        );
        $coalescedDatabase = self::databaseWithPageImages($database, $coalescePlan->pageImages());
        $vacuumPlan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
            $coalescedDatabase,
            $deleteResults,
            $maxTruncatedPages,
            $secureDelete,
        );

        $pageImages = $coalescePlan->pageImages();
        foreach ($vacuumPlan->pageImages as $pageNumber => $pageImage) {
            $pageImages[$pageNumber] = $pageImage;
        }
        foreach (array_keys($pageImages) as $pageNumber) {
            if ($pageNumber > $vacuumPlan->finalDatabasePageCount()) {
                unset($pageImages[$pageNumber]);
            }
        }
        ksort($pageImages);

        $nextDatabase = self::databaseWithPageImages(
            $database,
            $pageImages,
            $vacuumPlan->finalDatabasePageCount(),
        );

        return new self(
            $coalescePlan,
            $vacuumPlan,
            $database,
            $coalescedDatabase,
            $nextDatabase,
            $pageImages,
            self::leafFreeblockTransitions($coalescePlan),
            self::rows($database, $nextDatabase, $coalescePlan, $vacuumPlan),
        );
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        return array_keys($this->pageImages);
    }

    /**
     * @return list<int>
     */
    public function truncatedPageNumbers(): array
    {
        return $this->vacuumPlan->truncatedPageNumbers();
    }

    /**
     * @return list<int>
     */
    public function survivingFreelistPageNumbers(): array
    {
        return $this->nextDatabase->freelistPageNumbers();
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
            $this->truncatedPageNumbers(),
            fn (int $pageNumber): bool => $this->sourceDatabase->isPointerMapPage($pageNumber),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function materializedApplySummary(): array
    {
        return [
            'database_page_count' => $this->nextDatabase->pageCount(),
            'byte_length' => strlen($this->nextDatabase->toBytes()),
            'first_freelist_trunk_page' => $this->nextDatabase->header->firstFreelistTrunkPage,
            'freelist_page_count' => $this->nextDatabase->header->freelistPageCount,
            'freelist_page_numbers' => $this->survivingFreelistPageNumbers(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-freeblock-pointermap-vacuum-current-source-next',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'leaf_page_type' => $this->coalescePlan->pageType,
            'fragmented_bytes_before' => $this->coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->coalescePlan->fragmentedBytesAfter,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'leaf_freeblock_transitions' => $this->leafFreeblockTransitions,
            'released_overflow_pages' => $this->vacuumPlan->releasedOverflowPages(),
            'current_freelist_page_numbers' => $this->vacuumPlan->currentFreelistPageNumbers(),
            'surviving_freelist_page_numbers' => $this->survivingFreelistPageNumbers(),
            'truncated_page_numbers' => $this->truncatedPageNumbers(),
            'truncated_pointer_map_pages' => $this->truncatedPointerMapPages(),
            'materialized_page_numbers' => array_column($this->materializedRows(), 'page_number'),
            'truncated_row_page_numbers' => array_column($this->truncatedRows(), 'page_number'),
            'final_database_page_count' => $this->nextDatabase->pageCount(),
            'pointer_map_vacuum_transitions' => $this->vacuumPlan->pointerMapVacuumTransitions(),
            'materialized_apply' => $this->materializedApplySummary(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'rows' => $this->rows,
            'coalesce' => $this->coalescePlan->toArray(),
            'vacuum' => $this->vacuumPlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(
        SQLiteDatabase $sourceDatabase,
        SQLiteDatabase $nextDatabase,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
    ): array {
        $sourceByPage = self::sourceByReleasedPage($vacuumPlan);
        $rows = [self::leafRow($sourceDatabase, $nextDatabase, $coalescePlan)];

        foreach ($vacuumPlan->pointerMapVacuumTransitions() as $transition) {
            $pageNumber = (int) $transition['page_number'];
            $materialized = $pageNumber <= $nextDatabase->pageCount();
            $rows[] = [
                'kind' => 'released-overflow-page',
                'source' => $sourceByPage[$pageNumber] ?? null,
                'page_number' => $pageNumber,
                'pointer_map_page' => $sourceDatabase->pointerMapPageFor($pageNumber),
                'source_pointer_map_type' => $transition['current_type_name'],
                'source_pointer_map_parent' => self::pointerMapParent($sourceDatabase, $pageNumber),
                'free_pointer_map_type' => $transition['next_type_name'],
                'truncated_pointer_map_type' => $transition['truncated_type_name'],
                'vacuum_status' => $transition['status'],
                'materialized' => $materialized,
                'source_overflow_next_page' => self::readUInt32($sourceDatabase->page($pageNumber), 0),
                'next_overflow_next_page' => $materialized ? self::readUInt32($nextDatabase->page($pageNumber), 0) : null,
                'source_page_hash' => hash('sha256', $sourceDatabase->page($pageNumber)),
                'next_page_hash' => $materialized ? hash('sha256', $nextDatabase->page($pageNumber)) : null,
                'freelist_role' => $materialized ? self::freelistRole($nextDatabase, $pageNumber) : null,
            ];
        }

        foreach ($vacuumPlan->truncatedPageNumbers() as $pageNumber) {
            if (!$sourceDatabase->isPointerMapPage($pageNumber)) {
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
                'source_page_hash' => hash('sha256', $sourceDatabase->page($pageNumber)),
                'next_page_hash' => null,
                'freelist_role' => null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['page_number'], $a['kind']] <=> [$b['page_number'], $b['kind']]);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function leafRow(
        SQLiteDatabase $sourceDatabase,
        SQLiteDatabase $nextDatabase,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
    ): array {
        $leafPageNumber = $coalescePlan->pageNumber;
        $leafImage = $nextDatabase->page($leafPageNumber);
        $leafHeader = SQLiteBTreePageHeader::parsePage($leafImage, $sourceDatabase->header->pageSize);

        return [
            'kind' => 'deleted-leaf-freeblock',
            'source' => 'coalesced-delete-leaf',
            'page_number' => $leafPageNumber,
            'pointer_map_page' => $sourceDatabase->pointerMapPageFor($leafPageNumber),
            'source_pointer_map_type' => $sourceDatabase->pointerMapEntryForPage($leafPageNumber)->typeName(),
            'source_pointer_map_parent' => $sourceDatabase->pointerMapEntryForPage($leafPageNumber)->parentPageNumber,
            'free_pointer_map_type' => $nextDatabase->pointerMapEntryForPage($leafPageNumber)->typeName(),
            'truncated_pointer_map_type' => null,
            'vacuum_status' => 'materialized-leaf-page',
            'materialized' => true,
            'source_overflow_next_page' => null,
            'next_overflow_next_page' => null,
            'source_page_hash' => hash('sha256', $sourceDatabase->page($leafPageNumber)),
            'next_page_hash' => hash('sha256', $leafImage),
            'freelist_role' => null,
            'freeblock_offsets' => array_column($coalescePlan->afterFreeblocks, 'offset'),
            'freeblock_sizes' => array_column($coalescePlan->afterFreeblocks, 'size'),
            'fragmented_bytes_before' => $coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $coalescePlan->fragmentedBytesAfter,
            'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
            'freeblock_status' => $leafHeader->freeblockIntegrityReport($leafImage)['status'],
            'current_next_fragment_bytes' => $leafHeader->freeblockCurrentNextFragmentReport($leafImage)['current_next_fragment_bytes'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function sourceByReleasedPage(SQLiteOverflowVacuumTruncatePlan $vacuumPlan): array
    {
        $sourceByPage = [];
        foreach ($vacuumPlan->releasePlan->sources as $source) {
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
            throw new \InvalidArgumentException('SQLite b-tree freeblock pointer-map vacuum current-source-next could not read uint32');
        }

        return $value[1];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function leafFreeblockTransitions(SQLiteBTreeFreeblockCoalescePlan $plan): array
    {
        return [
            [
                'phase' => 'current',
                'freeblock_count' => count($plan->beforeFreeblocks),
                'freeblocks' => $plan->beforeFreeblocks,
                'fragmented_bytes' => $plan->fragmentedBytesBefore,
            ],
            [
                'phase' => 'next',
                'freeblock_count' => count($plan->afterFreeblocks),
                'freeblocks' => $plan->afterFreeblocks,
                'fragmented_bytes' => $plan->fragmentedBytesAfter,
            ],
        ];
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages, ?int $pageCount = null): SQLiteDatabase
    {
        $pageCount ??= max($database->pageCount(), $database->header->databaseSizePages);
        foreach ($pageImages as $pageNumber => $pageImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite freeblock pointer-map vacuum page images must use one-based page numbers');
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $database->header->pageSize) {
                throw new \InvalidArgumentException('SQLite freeblock pointer-map vacuum page image length does not match page size');
            }
            $pageCount = max($pageCount, $pageNumber);
        }

        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? (
                $pageNumber <= $database->pageCount()
                    ? $database->page($pageNumber)
                    : str_repeat("\0", $database->header->pageSize)
            );
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }
}
