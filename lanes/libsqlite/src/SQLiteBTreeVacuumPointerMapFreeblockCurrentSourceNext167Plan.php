<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext167Plan
{
    /**
     * @param list<array<string, mixed>> $leafRows
     * @param list<array<string, mixed>> $releasedPageRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan,
        private readonly array $leafRows,
        private readonly array $releasedPageRows,
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
        $releasedPageRows = self::buildReleasedPageRows($basePlan);
        $errors = self::integrityErrorsForRows($leafRows, $releasedPageRows);
        if ($errors !== []) {
            throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock next167 final image is inconsistent: ' . implode('; ', $errors));
        }

        return new self($basePlan, $leafRows, $releasedPageRows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leafRows(): array
    {
        return $this->leafRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function releasedPageRows(): array
    {
        return $this->releasedPageRows;
    }

    /**
     * @return list<int>
     */
    public function stableLeafPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->leafRows, static fn (array $row): bool => $row['final_hash_matches_post_vacuum'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function freePointerMapPagesAfterVacuum(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->releasedPageRows, static fn (array $row): bool => $row['final_pointer_map_type'] === 'free-page'),
        ));
    }

    /**
     * @return list<int>
     */
    public function replacementPointerMapPagesAfterVacuum(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->releasedPageRows, static fn (array $row): bool => in_array($row['final_pointer_map_type'], ['first-overflow-page', 'overflow-page'], true)),
        ));
    }

    /**
     * @return list<string>
     */
    public function integrityErrors(): array
    {
        return self::integrityErrorsForRows($this->leafRows, $this->releasedPageRows);
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSourceAudit(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next167-ready',
            'stable_leaf_pages' => $this->stableLeafPages(),
            'free_pointer_map_pages_after_vacuum' => $this->freePointerMapPagesAfterVacuum(),
            'replacement_pointer_map_pages_after_vacuum' => $this->replacementPointerMapPagesAfterVacuum(),
            'reused_truncated_current_source_pages' => $this->basePlan->reusedTruncatedCurrentSourcePages(),
            'changed_current_source_next_pages' => $this->basePlan->currentSourceNextChangedPages(),
            'dependency_closure' => 'no new support component needed; next167 reuses native b-tree leaf parsing, overflow allocation, pointer-map page image application, and vacuum truncation helpers',
            'non_overlap' => 'adds final leaf freeblock/page-image and surviving pointer-map audit after current-source overflow pages are reused; does not repeat next164 overflow chain continuity, next163 current-source fencing, root collapse, page move, overflow freelist release, or bulk overflow freeblock materialization',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next167',
            'current_source_audit' => $this->currentSourceAudit(),
            'integrity_errors' => $this->integrityErrors(),
            'leaf_rows' => $this->leafRows,
            'released_page_rows' => $this->releasedPageRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildLeafRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $base144 = $basePlan->basePlan->basePlan;
        $sourceDatabase = $base144->basePlan->basePlan->sourceDatabase;
        $postVacuumDatabase = $base144->basePlan->basePlan->nextDatabase;
        $finalDatabase = $basePlan->basePlan->databaseAfterAllocation;
        $leafPageNumber = $base144->basePlan->basePlan->deletePlan->leafPageNumber;

        $sourcePage = $sourceDatabase->page($leafPageNumber);
        $postVacuumPage = $postVacuumDatabase->page($leafPageNumber);
        $finalPage = $finalDatabase->page($leafPageNumber);

        $sourceHeader = SQLiteBTreePageHeader::parsePage($sourcePage, $sourceDatabase->header->pageSize, $leafPageNumber === 1 ? 100 : 0);
        $postVacuumHeader = SQLiteBTreePageHeader::parsePage($postVacuumPage, $postVacuumDatabase->header->pageSize, $leafPageNumber === 1 ? 100 : 0);
        $finalHeader = SQLiteBTreePageHeader::parsePage($finalPage, $finalDatabase->header->pageSize, $leafPageNumber === 1 ? 100 : 0);

        return [[
            'page_number' => $leafPageNumber,
            'source_cell_count' => $sourceHeader->cellCount,
            'post_vacuum_cell_count' => $postVacuumHeader->cellCount,
            'final_cell_count' => $finalHeader->cellCount,
            'source_freeblock_count' => count($sourceHeader->freeblocks($sourcePage, $sourceDatabase->usablePageSize())),
            'post_vacuum_freeblock_count' => count($postVacuumHeader->freeblocks($postVacuumPage, $postVacuumDatabase->usablePageSize())),
            'final_freeblock_count' => count($finalHeader->freeblocks($finalPage, $finalDatabase->usablePageSize())),
            'source_freeblock_bytes' => $sourceHeader->freeSpaceBytes($sourcePage, $sourceDatabase->usablePageSize()),
            'post_vacuum_freeblock_bytes' => $postVacuumHeader->freeSpaceBytes($postVacuumPage, $postVacuumDatabase->usablePageSize()),
            'final_freeblock_bytes' => $finalHeader->freeSpaceBytes($finalPage, $finalDatabase->usablePageSize()),
            'post_vacuum_freeblocks' => self::freeblockArrays($postVacuumHeader, $postVacuumPage, $postVacuumDatabase->usablePageSize()),
            'final_freeblocks' => self::freeblockArrays($finalHeader, $finalPage, $finalDatabase->usablePageSize()),
            'source_pointer_map_type' => self::pointerMapType($sourceDatabase, $leafPageNumber),
            'post_vacuum_pointer_map_type' => self::pointerMapType($postVacuumDatabase, $leafPageNumber),
            'final_pointer_map_type' => self::pointerMapType($finalDatabase, $leafPageNumber),
            'source_pointer_map_parent' => self::pointerMapParent($sourceDatabase, $leafPageNumber),
            'post_vacuum_pointer_map_parent' => self::pointerMapParent($postVacuumDatabase, $leafPageNumber),
            'final_pointer_map_parent' => self::pointerMapParent($finalDatabase, $leafPageNumber),
            'source_hash' => hash('sha256', $sourcePage),
            'post_vacuum_hash' => hash('sha256', $postVacuumPage),
            'final_hash' => hash('sha256', $finalPage),
            'final_hash_matches_post_vacuum' => hash('sha256', $finalPage) === hash('sha256', $postVacuumPage),
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildReleasedPageRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan $basePlan): array
    {
        $sourceDatabase = $basePlan->basePlan->basePlan->basePlan->basePlan->sourceDatabase;
        $postVacuumDatabase = $basePlan->basePlan->basePlan->basePlan->basePlan->nextDatabase;
        $finalDatabase = $basePlan->basePlan->databaseAfterAllocation;
        $allocated = array_fill_keys($basePlan->basePlan->allocatedOverflowPages(), true);
        $rows = [];

        foreach ($basePlan->chainRows() as $row) {
            $pageNumber = (int) $row['page_number'];
            $finalMaterialized = $pageNumber <= $finalDatabase->pageCount();
            $rows[] = [
                'page_number' => $pageNumber,
                'source_pointer_map_type' => self::pointerMapType($sourceDatabase, $pageNumber),
                'post_vacuum_pointer_map_type' => self::pointerMapType($postVacuumDatabase, $pageNumber),
                'final_pointer_map_type' => self::pointerMapType($finalDatabase, $pageNumber),
                'source_pointer_map_parent' => self::pointerMapParent($sourceDatabase, $pageNumber),
                'post_vacuum_pointer_map_parent' => self::pointerMapParent($postVacuumDatabase, $pageNumber),
                'final_pointer_map_parent' => self::pointerMapParent($finalDatabase, $pageNumber),
                'allocated_for_replacement' => isset($allocated[$pageNumber]),
                'final_materialized' => $finalMaterialized,
                'post_vacuum_materialized' => $row['post_vacuum_materialized'],
                'final_next_page' => $row['final_next_page'],
                'final_status' => $row['status'],
                'final_page_hash' => $finalMaterialized ? hash('sha256', $finalDatabase->page($pageNumber)) : null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $leafRows
     * @param list<array<string, mixed>> $releasedPageRows
     * @return list<string>
     */
    private static function integrityErrorsForRows(array $leafRows, array $releasedPageRows): array
    {
        $errors = [];
        foreach ($leafRows as $row) {
            if ($row['final_hash_matches_post_vacuum'] !== true) {
                $errors[] = "leaf page {$row['page_number']} changed during replacement overflow allocation";
            }
            if ($row['final_freeblocks'] !== $row['post_vacuum_freeblocks']) {
                $errors[] = "leaf page {$row['page_number']} freeblocks changed during replacement overflow allocation";
            }
            if ($row['final_pointer_map_type'] !== $row['post_vacuum_pointer_map_type']) {
                $errors[] = "leaf page {$row['page_number']} pointer-map type changed during allocation";
            }
        }

        foreach ($releasedPageRows as $row) {
            if ($row['allocated_for_replacement'] === true && !in_array($row['final_pointer_map_type'], ['first-overflow-page', 'overflow-page'], true)) {
                $errors[] = "allocated page {$row['page_number']} is not an overflow pointer-map page";
            }
            if ($row['allocated_for_replacement'] === false && $row['final_materialized'] === true && $row['final_pointer_map_type'] !== 'free-page') {
                $errors[] = "unallocated surviving page {$row['page_number']} is not a free pointer-map page";
            }
        }

        return $errors;
    }

    /**
     * @return list<array{offset:int,size:int,end_offset:int,next_offset:?int}>
     */
    private static function freeblockArrays(SQLiteBTreePageHeader $header, string $page, int $usableSize): array
    {
        return array_map(
            static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(),
            $header->freeblocks($page, $usableSize),
        );
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
}
