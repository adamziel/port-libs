<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext162Plan
{
    /**
     * @param list<array<string, mixed>> $writeRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Plan $basePlan,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Plan $basePlan): self
    {
        return new self($basePlan, self::buildWriteRows($basePlan));
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
    public function writablePageNumbers(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, static fn (array $row): bool => $row['write_allowed']),
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
    public function rejectedTruncatedPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->writeRows, static fn (array $row): bool => $row['write_kind'] === 'rejected-truncated-current-source-page'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next162',
            'leaf_page' => $this->basePlan->toArray()['leaf_page'],
            'writable_page_numbers' => $this->writablePageNumbers(),
            'pointer_map_write_pages' => $this->pointerMapWritePages(),
            'rejected_truncated_pages' => $this->rejectedTruncatedPages(),
            'replacement_overflow_pages' => $this->basePlan->replacementOverflowPages(),
            'replacement_overflow_next_pages' => $this->basePlan->replacementOverflowNextPages(),
            'replacement_pointer_map_parents' => $this->basePlan->replacementPointerMapParents(),
            'final_database_page_count' => $this->basePlan->toArray()['final_database_page_count'],
            'write_rows' => $this->writeRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildWriteRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Plan $basePlan): array
    {
        $nextDatabase = $basePlan->basePlan->databaseAfterAllocation;
        $pageImages = $basePlan->basePlan->pageImages();
        $leafPage = (int) $basePlan->toArray()['leaf_page'];
        $replacementPages = array_fill_keys($basePlan->replacementOverflowPages(), true);
        $pointerMapPages = [];
        foreach ($basePlan->replacementOverflowPages() as $pageNumber) {
            $pointerMapPages[$nextDatabase->pointerMapPageFor($pageNumber)] = true;
        }
        $rejected = array_fill_keys($basePlan->truncatedCurrentSourcePagesRejected(), true);

        $rows = [];
        foreach ($pageImages as $pageNumber => $pageImage) {
            $writeKind = self::writeKind((int) $pageNumber, $leafPage, $replacementPages, $pointerMapPages);
            $rows[] = [
                'page_number' => (int) $pageNumber,
                'write_kind' => $writeKind,
                'write_allowed' => (int) $pageNumber <= $nextDatabase->pageCount() && !isset($rejected[$pageNumber]),
                'page_size' => strlen($pageImage),
                'page_hash' => hash('sha256', $pageImage),
                'is_pointer_map_page' => $nextDatabase->isPointerMapPage((int) $pageNumber),
                'is_replacement_overflow_page' => isset($replacementPages[$pageNumber]),
                'overflow_next_page' => isset($replacementPages[$pageNumber]) ? self::readUInt32($pageImage, 0) : null,
                'pointer_map_cell_offsets' => self::pointerMapCellOffsetsForPage($nextDatabase, (int) $pageNumber, $basePlan->replacementOverflowPages()),
            ];
        }

        foreach ($basePlan->truncatedCurrentSourcePagesRejected() as $pageNumber) {
            $rows[] = [
                'page_number' => $pageNumber,
                'write_kind' => 'rejected-truncated-current-source-page',
                'write_allowed' => false,
                'page_size' => 0,
                'page_hash' => null,
                'is_pointer_map_page' => false,
                'is_replacement_overflow_page' => false,
                'overflow_next_page' => null,
                'pointer_map_cell_offsets' => [],
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

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next162 could not read uint32');
        }

        return $value[1];
    }
}
