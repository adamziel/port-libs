<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext168Plan
{
    /**
     * @param list<array<string, mixed>> $leafRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $leafRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext164(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): self
    {
        $leafRows = self::buildLeafRows($basePlan);
        $errors = self::leafErrorsForRows($leafRows);
        if ($errors !== []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next168 leaf image is inconsistent: ' . implode('; ', $errors));
        }

        return new self($basePlan, $leafRows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leafRows(): array
    {
        return $this->leafRows;
    }

    /**
     * @return list<string>
     */
    public function leafErrors(): array
    {
        return self::leafErrorsForRows($this->leafRows);
    }

    /**
     * @return list<int>
     */
    public function stableLeafPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->leafRows, static fn (array $row): bool => $row['source_hash'] !== $row['deleted_hash'] && $row['deleted_hash'] === $row['final_hash']),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next168',
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->basePlan->basePlan->allocatedOverflowPages(),
            'appended_overflow_pages' => $this->basePlan->basePlan->appendedOverflowPages(),
            'stable_leaf_pages' => $this->stableLeafPages(),
            'leaf_errors' => $this->leafErrors(),
            'leaf_rows' => $this->leafRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildLeafRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $deletePlan = $basePlan->basePlan->basePlan->basePlan->basePlan->deletePlan;
        $sourceDatabase = $basePlan->basePlan->basePlan->basePlan->basePlan->sourceDatabase;
        $finalDatabase = $basePlan->basePlan->databaseAfterAllocation;
        $leafPageNumber = $deletePlan->leafPageNumber;
        $sourcePage = $sourceDatabase->page($leafPageNumber);
        $deletedPage = $deletePlan->leafPageImage;
        $finalPage = $finalDatabase->page($leafPageNumber);
        $sourceHeader = SQLiteBTreePageHeader::parsePage($sourcePage, $sourceDatabase->header->pageSize);
        $deletedHeader = SQLiteBTreePageHeader::parsePage($deletedPage, $sourceDatabase->header->pageSize);
        $finalHeader = SQLiteBTreePageHeader::parsePage($finalPage, $finalDatabase->header->pageSize);
        $entry = $finalDatabase->isAutoVacuum() && !$finalDatabase->isPointerMapPage($leafPageNumber)
            ? $finalDatabase->pointerMapEntryForPage($leafPageNumber)
            : null;

        return [[
            'page_number' => $leafPageNumber,
            'source_hash' => hash('sha256', $sourcePage),
            'deleted_hash' => hash('sha256', $deletedPage),
            'final_hash' => hash('sha256', $finalPage),
            'source_cell_count' => $sourceHeader->cellCount,
            'deleted_cell_count' => $deletedHeader->cellCount,
            'final_cell_count' => $finalHeader->cellCount,
            'source_freeblock_count' => count($sourceHeader->freeblocks($sourcePage, $sourceDatabase->usablePageSize())),
            'deleted_freeblock_count' => count($deletedHeader->freeblocks($deletedPage, $sourceDatabase->usablePageSize())),
            'final_freeblock_count' => count($finalHeader->freeblocks($finalPage, $finalDatabase->usablePageSize())),
            'source_freeblock_bytes' => $sourceHeader->freeblockIntegrityReport($sourcePage, $sourceDatabase->usablePageSize())['freeblock_bytes'],
            'deleted_freeblock_bytes' => $deletedHeader->freeblockIntegrityReport($deletedPage, $sourceDatabase->usablePageSize())['freeblock_bytes'],
            'final_freeblock_bytes' => $finalHeader->freeblockIntegrityReport($finalPage, $finalDatabase->usablePageSize())['freeblock_bytes'],
            'deleted_freeblock_status' => $deletedHeader->freeblockIntegrityReport($deletedPage, $sourceDatabase->usablePageSize())['status'],
            'final_freeblock_status' => $finalHeader->freeblockIntegrityReport($finalPage, $finalDatabase->usablePageSize())['status'],
            'deleted_freeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $deletedHeader->freeblocks($deletedPage, $sourceDatabase->usablePageSize())),
            'final_freeblocks' => array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $finalHeader->freeblocks($finalPage, $finalDatabase->usablePageSize())),
            'final_pointer_map_type' => $entry?->typeName(),
            'final_pointer_map_parent' => $entry?->parentPageNumber,
            'final_database_page_count' => $finalDatabase->pageCount(),
        ]];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function leafErrorsForRows(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            $pageNumber = (int) $row['page_number'];
            if ($row['source_hash'] === $row['deleted_hash']) {
                $errors[] = "leaf page {$pageNumber} was not changed by delete";
            }
            if ($row['deleted_hash'] !== $row['final_hash']) {
                $errors[] = "leaf page {$pageNumber} final image does not match the delete freeblock image";
            }
            if ($row['deleted_freeblock_status'] !== 'ok' || $row['final_freeblock_status'] !== 'ok') {
                $errors[] = "leaf page {$pageNumber} has corrupt freeblock accounting";
            }
            if ($row['deleted_freeblock_bytes'] !== $row['final_freeblock_bytes']) {
                $errors[] = "leaf page {$pageNumber} final freeblock bytes changed during overflow allocation";
            }
            if ($row['final_pointer_map_type'] !== 'root-page') {
                $errors[] = "leaf page {$pageNumber} pointer-map type is {$row['final_pointer_map_type']} instead of root-page";
            }
            if ($row['final_pointer_map_parent'] !== 0) {
                $errors[] = "leaf page {$pageNumber} pointer-map parent is {$row['final_pointer_map_parent']} instead of 0";
            }
        }

        return $errors;
    }
}
