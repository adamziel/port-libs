<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

require_once __DIR__ . '/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan.php';

final class SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::baseTableLeafFromDeleteResult(
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
        return self::fromBasePlan(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::baseIndexLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $secureDelete,
            $overflowReader,
        ));
    }

    public static function fromBasePlan(SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan $basePlan): self
    {
        $released = array_fill_keys($basePlan->releasedOverflowPages(), true);
        $truncated = array_fill_keys($basePlan->truncatePlan->truncatedPageNumbers, true);
        $survivors = array_fill_keys($basePlan->survivingReleasedOverflowPages(), true);
        $rows = [];

        foreach ($basePlan->releasedOverflowPages() as $position => $pageNumber) {
            $currentEntry = self::entryFor($basePlan->sourceDatabase, $pageNumber);
            $nextEntry = self::entryFor($basePlan->nextDatabase, $pageNumber);
            $rows[] = [
                'phase' => 'released-overflow',
                'page_number' => $pageNumber,
                'chain_position' => $position,
                'pointer_map_page' => $basePlan->sourceDatabase->pointerMapPageFor($pageNumber),
                'current_pointer_map_type' => $currentEntry['type_name'] ?? null,
                'current_pointer_map_parent' => $currentEntry['parent_page_number'] ?? null,
                'next_pointer_map_type' => $nextEntry['type_name'] ?? null,
                'next_pointer_map_parent' => $nextEntry['parent_page_number'] ?? null,
                'freelist_role' => isset($survivors[$pageNumber]) ? self::freelistRole($basePlan->nextDatabase, $pageNumber) : null,
                'vacuum_status' => isset($truncated[$pageNumber]) ? 'truncated' : 'survives-as-free-page',
            ];
        }

        foreach ($basePlan->truncatePlan->truncatedPageNumbers as $pageNumber) {
            if (isset($released[$pageNumber])) {
                continue;
            }
            $rows[] = [
                'phase' => $basePlan->sourceDatabase->isPointerMapPage($pageNumber) ? 'truncated-pointer-map-page' : 'truncated-freelist-page',
                'page_number' => $pageNumber,
                'chain_position' => null,
                'pointer_map_page' => $basePlan->sourceDatabase->isPointerMapPage($pageNumber) ? $pageNumber : $basePlan->sourceDatabase->pointerMapPageFor($pageNumber),
                'current_pointer_map_type' => null,
                'current_pointer_map_parent' => null,
                'next_pointer_map_type' => null,
                'next_pointer_map_parent' => null,
                'freelist_role' => null,
                'vacuum_status' => 'truncated',
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['page_number'], $a['phase']] <=> [$b['page_number'], $b['phase']]);

        return new self($basePlan, $rows);
    }

    /**
     * @return list<int>
     */
    public function survivingReleasedOverflowPages(): array
    {
        return $this->basePlan->survivingReleasedOverflowPages();
    }

    /**
     * @return list<int>
     */
    public function truncatedReleasedOverflowPages(): array
    {
        return $this->basePlan->truncatedReleasedOverflowPages();
    }

    /**
     * @return list<int>
     */
    public function truncatedPointerMapPages(): array
    {
        return array_values(array_filter(
            $this->basePlan->truncatePlan->truncatedPageNumbers,
            fn (int $pageNumber): bool => $this->basePlan->sourceDatabase->isPointerMapPage($pageNumber),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function materializedApplySummary(): array
    {
        $summary = $this->basePlan->materializedApplySummary();
        $summary['truncated_pointer_map_pages'] = $this->truncatedPointerMapPages();
        $summary['surviving_released_overflow_pages'] = $this->survivingReleasedOverflowPages();
        $summary['truncated_released_overflow_pages'] = $this->truncatedReleasedOverflowPages();
        $summary['freeblock_integrity_status'] = SQLiteBTreePageHeader::parsePage(
            $this->basePlan->deletePlan->leafPageImage,
            $this->basePlan->sourceDatabase->header->pageSize,
        )->freeblockIntegrityReport($this->basePlan->deletePlan->leafPageImage)['status'];

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-pointermap-freeblock-vacuum-current-source-next135',
            'leaf_page' => $this->basePlan->deletePlan->leafPageNumber,
            'leaf_page_type' => $this->basePlan->deletePlan->leafPageType,
            'released_overflow_pages' => $this->basePlan->releasedOverflowPages(),
            'surviving_released_overflow_pages' => $this->survivingReleasedOverflowPages(),
            'truncated_released_overflow_pages' => $this->truncatedReleasedOverflowPages(),
            'truncated_pointer_map_pages' => $this->truncatedPointerMapPages(),
            'final_database_page_count' => $this->basePlan->nextDatabase->pageCount(),
            'final_first_freelist_trunk_page' => $this->basePlan->nextDatabase->header->firstFreelistTrunkPage,
            'final_freelist_page_count' => $this->basePlan->nextDatabase->header->freelistPageCount,
            'final_freelist_page_numbers' => $this->basePlan->nextDatabase->freelistPageNumbers(),
            'updated_page_numbers' => $this->basePlan->updatedPageNumbers(),
            'rows' => $this->rows,
            'materialized_apply' => $this->materializedApplySummary(),
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function entryFor(SQLiteDatabase $database, int $pageNumber): ?array
    {
        if (!$database->isAutoVacuum() || $pageNumber > $database->pageCount() || $database->isPointerMapPage($pageNumber)) {
            return null;
        }

        return $database->pointerMapEntryForPage($pageNumber)->toArray();
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
}
