<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext164Plan
{
    /**
     * @param list<array<string, mixed>> $chainRows
     */
    private function __construct(
        public readonly SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext161Plan $basePlan,
        private readonly array $chainRows,
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
        return self::fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext161Plan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $replacementOverflowPayload,
            $parentBtreePageNumber,
            $secureDelete,
        ));
    }

    public static function fromBasePlan(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext161Plan $basePlan): self
    {
        $chainRows = self::buildChainRows($basePlan);
        $errors = self::continuityErrorsForRows($chainRows, $basePlan->allocatedOverflowPages());
        if ($errors !== []) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next164 replacement chain is inconsistent: ' . implode('; ', $errors));
        }

        return new self($basePlan, $chainRows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function chainRows(): array
    {
        return $this->chainRows;
    }

    /**
     * @return list<string>
     */
    public function continuityErrors(): array
    {
        return self::continuityErrorsForRows($this->chainRows, $this->basePlan->allocatedOverflowPages());
    }

    /**
     * @return list<int>
     */
    public function currentSourceNextChangedPages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['source_next_page'] !== $row['final_next_page']),
        ));
    }

    /**
     * @return list<int>
     */
    public function reusedTruncatedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->chainRows, static fn (array $row): bool => $row['appended_after_truncate'] === true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-vacuum-pointermap-freeblock-current-source-next164',
            'released_overflow_pages' => $this->basePlan->basePlan->basePlan->basePlan->releasedOverflowPages(),
            'allocated_overflow_pages' => $this->basePlan->allocatedOverflowPages(),
            'appended_overflow_pages' => $this->basePlan->appendedOverflowPages(),
            'current_source_next_changed_pages' => $this->currentSourceNextChangedPages(),
            'reused_truncated_current_source_pages' => $this->reusedTruncatedCurrentSourcePages(),
            'continuity_errors' => $this->continuityErrors(),
            'final_database_page_count' => $this->basePlan->databaseAfterAllocation->pageCount(),
            'final_freelist_page_numbers' => $this->basePlan->databaseAfterAllocation->freelistPageNumbers(),
            'chain_rows' => $this->chainRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildChainRows(SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext161Plan $basePlan): array
    {
        $sourceDatabase = $basePlan->basePlan->basePlan->basePlan->sourceDatabase;
        $postVacuumDatabase = $basePlan->basePlan->basePlan->basePlan->nextDatabase;
        $finalDatabase = $basePlan->databaseAfterAllocation;
        $allocated = array_fill_keys($basePlan->allocatedOverflowPages(), true);
        $appended = array_fill_keys($basePlan->appendedOverflowPages(), true);
        $truncated = array_fill_keys($basePlan->basePlan->basePlan->truncatedReleasedOverflowPages(), true);
        $rows = [];

        foreach ($basePlan->basePlan->basePlan->basePlan->releasedOverflowPages() as $chainPosition => $pageNumber) {
            $sourcePresent = $pageNumber <= $sourceDatabase->pageCount();
            $postVacuumPresent = $pageNumber <= $postVacuumDatabase->pageCount();
            $finalPresent = $pageNumber <= $finalDatabase->pageCount();
            $isAllocated = isset($allocated[$pageNumber]);
            $entry = ($finalPresent && $finalDatabase->isAutoVacuum() && !$finalDatabase->isPointerMapPage($pageNumber))
                ? $finalDatabase->pointerMapEntryForPage($pageNumber)
                : null;

            $rows[] = [
                'page_number' => $pageNumber,
                'chain_position' => $chainPosition,
                'source_materialized' => $sourcePresent,
                'post_vacuum_materialized' => $postVacuumPresent,
                'final_materialized' => $finalPresent,
                'allocated_for_replacement' => $isAllocated,
                'appended_after_truncate' => isset($appended[$pageNumber]) && isset($truncated[$pageNumber]),
                'source_next_page' => $sourcePresent ? self::readUInt32($sourceDatabase->page($pageNumber), 0) : null,
                'post_vacuum_next_page' => $postVacuumPresent ? self::readUInt32($postVacuumDatabase->page($pageNumber), 0) : null,
                'final_next_page' => ($finalPresent && $isAllocated) ? self::readUInt32($finalDatabase->page($pageNumber), 0) : null,
                'final_pointer_map_type' => $entry?->typeName(),
                'final_pointer_map_parent' => $entry?->parentPageNumber,
                'final_page_hash' => $finalPresent ? hash('sha256', $finalDatabase->page($pageNumber)) : null,
                'status' => $isAllocated
                    ? (isset($appended[$pageNumber]) ? 'replacement-overflow-appended' : 'replacement-overflow-reused')
                    : ($postVacuumPresent ? 'post-vacuum-free-page' : 'truncated-tail-page'),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<int> $allocatedPages
     * @return list<string>
     */
    private static function continuityErrorsForRows(array $rows, array $allocatedPages): array
    {
        $rowsByPage = [];
        foreach ($rows as $row) {
            $rowsByPage[(int) $row['page_number']] = $row;
        }

        $errors = [];
        foreach ($allocatedPages as $index => $pageNumber) {
            if (!isset($rowsByPage[$pageNumber])) {
                $errors[] = "allocated page {$pageNumber} was not released by the delete";
                continue;
            }

            $expectedNext = $allocatedPages[$index + 1] ?? 0;
            $row = $rowsByPage[$pageNumber];
            if ($row['final_materialized'] !== true) {
                $errors[] = "allocated page {$pageNumber} is not materialized in the final database";
            }
            if ($row['final_next_page'] !== $expectedNext) {
                $errors[] = "allocated page {$pageNumber} points to {$row['final_next_page']} instead of {$expectedNext}";
            }
            $expectedType = $index === 0 ? 'first-overflow-page' : 'overflow-page';
            if ($row['final_pointer_map_type'] !== $expectedType) {
                $errors[] = "allocated page {$pageNumber} pointer-map type is {$row['final_pointer_map_type']} instead of {$expectedType}";
            }
            $expectedParent = $index === 0 ? null : $allocatedPages[$index - 1];
            if ($index > 0 && $row['final_pointer_map_parent'] !== $expectedParent) {
                $errors[] = "allocated page {$pageNumber} pointer-map parent is {$row['final_pointer_map_parent']} instead of {$expectedParent}";
            }
        }

        return $errors;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite b-tree vacuum pointer-map freeblock next164 could not read uint32');
        }

        return $value[1];
    }
}
