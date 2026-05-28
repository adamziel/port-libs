<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Plan
{
    /**
     * @param list<array<string, mixed>> $chainRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Plan $basePlan,
        public readonly array $chainRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Plan $basePlan): self
    {
        return new self($basePlan, self::buildChainRows($basePlan));
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowPages(): array
    {
        return $this->basePlan->allocatedOverflowPages();
    }

    /**
     * @return list<int>
     */
    public function replacementOverflowNextPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['overflow_next_page'],
            $this->chainRows,
        ));
    }

    /**
     * @return list<int>
     */
    public function replacementPointerMapParents(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['pointer_map_parent'],
            $this->chainRows,
        ));
    }

    /**
     * @return list<int>
     */
    public function reusedCurrentSourceFreePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['reused_current_source_free_page']),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedCurrentSourcePagesRejected(): array
    {
        return $this->basePlan->truncatedReleasedOverflowPagesRejected();
    }

    /**
     * @return list<int>
     */
    public function leafFreeblockPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->basePlan->rows, static fn (array $row): bool => $row['kind'] === 'deleted-leaf-freeblock'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next160',
            'leaf_page' => $this->basePlan->basePlan->basePlan->basePlan->deletePlan->leafPageNumber,
            'replacement_overflow_pages' => $this->replacementOverflowPages(),
            'replacement_overflow_next_pages' => $this->replacementOverflowNextPages(),
            'replacement_pointer_map_parents' => $this->replacementPointerMapParents(),
            'reused_current_source_free_pages' => $this->reusedCurrentSourceFreePages(),
            'truncated_current_source_pages_rejected' => $this->truncatedCurrentSourcePagesRejected(),
            'leaf_freeblock_pages' => $this->leafFreeblockPages(),
            'final_database_page_count' => $this->basePlan->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'chain_rows' => $this->chainRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildChainRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext156Plan $basePlan): array
    {
        $allocatedPages = $basePlan->allocatedOverflowPages();
        $surviving = array_fill_keys($basePlan->basePlan->basePlan->survivingReleasedOverflowPages(), true);
        $truncated = array_fill_keys($basePlan->basePlan->basePlan->truncatedReleasedOverflowPages(), true);
        $rowsByPage = [];
        foreach ($basePlan->rows as $row) {
            $rowsByPage[(int) $row['page_number']] = $row;
        }

        $rows = [];
        foreach ($allocatedPages as $index => $pageNumber) {
            $entry = $basePlan->databaseAfterAllocation->pointerMapEntryForPage($pageNumber);
            $expectedParent = $index === 0
                ? (int) $entry->parentPageNumber
                : $allocatedPages[$index - 1];
            $overflowNext = self::readUInt32($basePlan->databaseAfterAllocation->page($pageNumber), 0);

            $rows[] = [
                'page_number' => $pageNumber,
                'chain_position' => $index,
                'overflow_next_page' => $overflowNext,
                'expected_next_page' => $allocatedPages[$index + 1] ?? 0,
                'pointer_map_type' => $entry->typeName(),
                'pointer_map_parent' => $entry->parentPageNumber,
                'expected_pointer_map_parent' => $expectedParent,
                'pointer_map_matches_chain' => $entry->parentPageNumber === $expectedParent,
                'next_pointer_matches_chain' => $overflowNext === ($allocatedPages[$index + 1] ?? 0),
                'reused_current_source_free_page' => isset($surviving[$pageNumber]),
                'truncated_current_source_page_reused' => isset($truncated[$pageNumber]),
                'post_vacuum_status' => $rowsByPage[$pageNumber]['post_vacuum_status'] ?? null,
                'final_page_hash' => hash('sha256', $basePlan->databaseAfterAllocation->page($pageNumber)),
            ];
        }

        return $rows;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next160 could not read uint32');
        }

        return $value[1];
    }
}
