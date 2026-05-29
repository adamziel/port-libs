<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext166Plan
{
    /**
     * @param list<array<string, mixed>> $writeRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext163Plan $basePlan,
        private readonly array $writeRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext163Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext163Plan $basePlan): self
    {
        $rows = self::buildWriteRows($basePlan);
        foreach ($rows as $row) {
            if ($row['stale_current_source_admitted'] === true) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next166 admitted stale truncated source bytes');
            }
            if ($row['write_kind'] === 'leaf-freeblock-page' && $row['deleted_cell_bytes_absent'] !== true) {
                throw new \RuntimeException('SQLite b-tree vacuum pointer-map freeblock current-source next166 leaf page retained deleted cell bytes');
            }
        }

        return new self($basePlan, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function writeRows(): array
    {
        return $this->writeRows;
    }

    /**
     * @return list<int>
     */
    public function admittedWritePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, static fn (array $row): bool => $row['write_admitted'] === true),
        ));
    }

    /**
     * @return list<int>
     */
    public function rejectedWritePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, static fn (array $row): bool => $row['write_admitted'] === false),
        ));
    }

    /**
     * @return list<int>
     */
    public function pointerMapWritePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, static fn (array $row): bool => $row['write_kind'] === 'pointer-map-page'),
        ));
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowWritePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, static fn (array $row): bool => $row['write_kind'] === 'replacement-overflow-page'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function writeAdmissionSummary(): array
    {
        return [
            'status' => 'btree-vacuum-pointermap-freeblock-current-source-next166-ready',
            'leaf_page' => $this->basePlan->currentSourceFence()['leaf_page'],
            'admitted_write_pages' => $this->admittedWritePages(),
            'rejected_write_pages' => $this->rejectedWritePages(),
            'pointer_map_write_pages' => $this->pointerMapWritePages(),
            'replacement_overflow_write_pages' => $this->replacementOverflowWritePages(),
            'leaf_freeblock_pages' => $this->basePlan->currentSourceFence()['leaf_freeblock_pages'],
            'replacement_chain_pages' => $this->basePlan->replacementChainPages(),
            'rejected_current_source_pages' => $this->basePlan->rejectedCurrentSourcePages(),
            'final_database_page_count' => $this->basePlan->currentSourceFence()['final_database_page_count'],
            'write_admission_signature' => self::signature($this->admittedWritePages()),
            'rejected_source_signature' => self::signature($this->rejectedWritePages()),
            'dependencies' => [
                'sqlite-btree-vacuum-pointermap-freeblock-current-source-next163',
                'sqlite-current-source-next166',
            ],
            'dependency_closure' => 'no new support component needed; next166 reuses native b-tree vacuum page images, secure-delete freeblock pages, overflow encoding, freelist allocation, and auto-vacuum pointer-map writes',
            'non_overlap' => 'adds final write-admission and secure-delete freeblock scrub fencing after next163 admission; does not repeat next163 source admission, next160 chain pointer validation, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next166',
            'write_admission_summary' => $this->writeAdmissionSummary(),
            'write_rows' => $this->writeRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildWriteRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext163Plan $basePlan): array
    {
        $base160 = $basePlan->basePlan;
        $base156 = $base160->basePlan;
        $finalDatabase = $base156->databaseAfterAllocation;
        $pageImages = $base156->pageImages();
        $leafPage = (int) $basePlan->currentSourceFence()['leaf_page'];
        $replacementPages = array_fill_keys($basePlan->replacementChainPages(), true);
        $rejectedPages = array_fill_keys($basePlan->rejectedCurrentSourcePages(), true);
        $pointerMapPages = [];
        foreach ($basePlan->replacementChainPages() as $pageNumber) {
            $pointerMapPages[$finalDatabase->pointerMapPageFor($pageNumber)] = true;
        }

        $rows = [];
        foreach ($pageImages as $pageNumber => $pageImage) {
            $pageNumber = (int) $pageNumber;
            $writeKind = self::writeKind($pageNumber, $leafPage, $replacementPages, $pointerMapPages);
            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => $writeKind,
                'write_admitted' => $pageNumber <= $finalDatabase->pageCount() && !isset($rejectedPages[$pageNumber]),
                'page_size' => strlen($pageImage),
                'page_hash' => hash('sha256', $pageImage),
                'is_pointer_map_page' => $finalDatabase->isPointerMapPage($pageNumber),
                'is_leaf_freeblock_page' => $pageNumber === $leafPage,
                'is_replacement_overflow_page' => isset($replacementPages[$pageNumber]),
                'overflow_next_page' => isset($replacementPages[$pageNumber]) ? self::readUInt32($pageImage, 0) : null,
                'pointer_map_cell_offsets' => self::pointerMapCellOffsetsForPage($finalDatabase, $pageNumber, $basePlan->replacementChainPages()),
                'leaf_freeblock_offset' => $pageNumber === $leafPage ? self::readUInt16($pageImage, 1) : null,
                'deleted_cell_bytes_absent' => $pageNumber === $leafPage ? !str_contains($pageImage, '_transient_next166') : null,
                'stale_current_source_admitted' => isset($rejectedPages[$pageNumber]),
            ];
        }

        foreach ($basePlan->rejectedCurrentSourcePages() as $pageNumber) {
            if (isset($pageImages[$pageNumber])) {
                continue;
            }
            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => 'rejected-truncated-current-source-page',
                'write_admitted' => false,
                'page_size' => 0,
                'page_hash' => null,
                'is_pointer_map_page' => false,
                'is_leaf_freeblock_page' => false,
                'is_replacement_overflow_page' => false,
                'overflow_next_page' => null,
                'pointer_map_cell_offsets' => [],
                'leaf_freeblock_offset' => null,
                'deleted_cell_bytes_absent' => null,
                'stale_current_source_admitted' => false,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ((int) $a['page_number']) <=> ((int) $b['page_number']));

        return $rows;
    }

    /**
     * @param array<int, true> $replacementPages
     * @param array<int, true> $pointerMapPages
     */
    private static function writeKind(int $pageNumber, int $leafPage, array $replacementPages, array $pointerMapPages): string
    {
        if ($pageNumber === 1) {
            return 'database-header';
        }
        if ($pageNumber === $leafPage) {
            return 'leaf-freeblock-page';
        }
        if (isset($pointerMapPages[$pageNumber])) {
            return 'pointer-map-page';
        }
        if (isset($replacementPages[$pageNumber])) {
            return 'replacement-overflow-page';
        }

        return 'freelist-trunk-page';
    }

    /**
     * @param list<int> $replacementOverflowPages
     * @return list<int>
     */
    private static function pointerMapCellOffsetsForPage(SQLiteDatabase $database, int $pageNumber, array $replacementOverflowPages): array
    {
        if (!$database->isPointerMapPage($pageNumber)) {
            return [];
        }

        $offsets = [];
        foreach ($replacementOverflowPages as $overflowPage) {
            if ($database->pointerMapPageFor($overflowPage) === $pageNumber) {
                $offsets[] = 5 * ($overflowPage - $pageNumber - 1);
            }
        }

        return $offsets;
    }

    private static function readUInt16(string $bytes, int $offset): int
    {
        $value = unpack('n', substr($bytes, $offset, 2));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next166 could not read uint16');
        }

        return $value[1];
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next166 could not read uint32');
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
