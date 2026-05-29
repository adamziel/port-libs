<?php
declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan
{
    private function __construct(private readonly object $inner)
    {
    }

    public static function __callStatic(string $name, array $args): self
    {
        $args = self::unwrapArgs($args);
        if (method_exists(SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextExtendedVariantPlan::class, $name)) {
            return new self(SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextExtendedVariantPlan::{$name}(...$args));
        }
        if (method_exists(SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextBaseVariantPlan::class, $name)) {
            return new self(SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextBaseVariantPlan::{$name}(...$args));
        }

        throw new \BadMethodCallException(sprintf('Unknown %s factory method %s', self::class, $name));
    }

    public static function next122FromDeleteResults(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextBaseVariantPlan::fromDeleteResults(...$args));
    }

    public static function next140TableLeafFromCurrentSourceDeleteResult(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextExtendedVariantPlan::tableLeafFromCurrentSourceDeleteResult(...$args));
    }

    public static function next140FromBasePlan(mixed ...$args): self
    {
        $args = self::unwrapArgs($args);
        return new self(SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextExtendedVariantPlan::fromBasePlan(...$args));
    }

    /**
     * @param list<mixed> $args
     * @return list<mixed>
     */
    private static function unwrapArgs(array $args): array
    {
        return array_map(static fn (mixed $arg): mixed => $arg instanceof self ? $arg->inner : $arg, $args);
    }

    public function __call(string $name, array $args): mixed
    {
        return $this->inner->{$name}(...$args);
    }

    public function __get(string $name): mixed
    {
        return $this->inner->{$name};
    }

    public function __isset(string $name): bool
    {
        return isset($this->inner->{$name});
    }

    public function toArray(): array
    {
        return $this->inner->toArray();
    }
}

final class SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextBaseVariantPlan
{
    /**
     * @param list<array<string, mixed>> $deleteResults
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly SQLiteDatabase $sourceDatabase,
        public readonly SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        public readonly SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        public readonly array $deleteResults,
        public readonly array $rows,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     */
    public static function fromDeleteResults(
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

        return new self(
            $database,
            $coalescePlan,
            $vacuumPlan,
            $deleteResults,
            self::rows($database, $coalescePlan, $vacuumPlan, $deleteResults),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function overflowFreeblockVacuumRows(): array
    {
        return $this->rows;
    }

    /**
     * @return list<int>
     */
    public function releasedOverflowPages(): array
    {
        return $this->vacuumPlan->releasedOverflowPages();
    }

    /**
     * @return list<int>
     */
    public function survivingFreedPointerMapPages(): array
    {
        return $this->vacuumPlan->survivingFreedPointerMapPages();
    }

    /**
     * @return list<int>
     */
    public function truncatedFreedPointerMapPages(): array
    {
        return $this->vacuumPlan->truncatedFreedPointerMapPages();
    }

    /**
     * @return list<int>
     */
    public function updatedPageNumbers(): array
    {
        $pages = array_merge(array_keys($this->coalescePlan->pageImages()), array_keys($this->vacuumPlan->pageImages));
        $pages = array_values(array_unique(array_map('intval', $pages)));
        sort($pages);

        return $pages;
    }

    /**
     * @return array<string, mixed>
     */
    public function materializedApplySummary(): array
    {
        return $this->vacuumPlan->materializedApplySummary();
    }

    public function materializedDatabase(): SQLiteDatabase
    {
        return $this->vacuumPlan->materializedDatabase();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freeblock-vacuum-current-source-next122',
            'leaf_page' => $this->coalescePlan->pageNumber,
            'coalesced_fragment_bytes' => $this->coalescePlan->coalescedFragmentBytes,
            'fragmented_bytes_before' => $this->coalescePlan->fragmentedBytesBefore,
            'fragmented_bytes_after' => $this->coalescePlan->fragmentedBytesAfter,
            'freeblock_count_before' => count($this->coalescePlan->beforeFreeblocks),
            'freeblock_count_after' => count($this->coalescePlan->afterFreeblocks),
            'released_overflow_pages' => $this->releasedOverflowPages(),
            'surviving_freed_pointer_map_pages' => $this->survivingFreedPointerMapPages(),
            'truncated_freed_pointer_map_pages' => $this->truncatedFreedPointerMapPages(),
            'final_database_page_count' => $this->vacuumPlan->finalDatabasePageCount(),
            'final_first_freelist_trunk_page' => $this->vacuumPlan->finalFirstFreelistTrunkPage(),
            'final_freelist_page_count' => $this->vacuumPlan->finalFreelistPageCount(),
            'updated_page_numbers' => $this->updatedPageNumbers(),
            'overflow_freeblock_vacuum_current_source_next122' => $this->rows,
            'pointer_map_vacuum_transitions' => $this->vacuumPlan->pointerMapVacuumTransitions(),
            'materialized_apply' => $this->materializedApplySummary(),
            'coalesce_plan' => $this->coalescePlan->toArray(),
            'vacuum_plan' => $this->vacuumPlan->toArray(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $deleteResults
     * @return list<array<string, mixed>>
     */
    private static function rows(
        SQLiteDatabase $database,
        SQLiteBTreeFreeblockCoalescePlan $coalescePlan,
        SQLiteOverflowVacuumTruncatePlan $vacuumPlan,
        array $deleteResults,
    ): array {
        $transitionByPage = [];
        foreach ($vacuumPlan->pointerMapVacuumTransitions() as $transition) {
            $transitionByPage[(int) $transition['page_number']] = $transition;
        }

        $rows = [];
        foreach (array_values($deleteResults) as $deleteIndex => $deleteResult) {
            if (!is_array($deleteResult)) {
                throw new \InvalidArgumentException('SQLite overflow freeblock vacuum next122 delete results must be arrays');
            }

            $pages = $deleteResult['obsolete_overflow_page_numbers'] ?? null;
            if (!is_array($pages)) {
                throw new \InvalidArgumentException('SQLite overflow freeblock vacuum next122 requires obsolete overflow pages');
            }

            foreach (array_values($pages) as $chainPosition => $pageNumber) {
                if (!is_int($pageNumber)) {
                    throw new \InvalidArgumentException('SQLite overflow freeblock vacuum next122 overflow pages must be integers');
                }

                $currentEntry = $database->isAutoVacuum() && !$database->isPointerMapPage($pageNumber)
                    ? $database->pointerMapEntryForPage($pageNumber)->toArray()
                    : null;
                $transition = $transitionByPage[$pageNumber] ?? null;

                $rows[] = [
                    'source' => self::sourceLabel($deleteResult, $deleteIndex),
                    'leaf_page' => $coalescePlan->pageNumber,
                    'chain_position' => $chainPosition,
                    'page_number' => $pageNumber,
                    'coalesced_fragment_bytes' => $coalescePlan->coalescedFragmentBytes,
                    'fragmented_bytes_before' => $coalescePlan->fragmentedBytesBefore,
                    'fragmented_bytes_after' => $coalescePlan->fragmentedBytesAfter,
                    'freeblock_count_before' => count($coalescePlan->beforeFreeblocks),
                    'freeblock_count_after' => count($coalescePlan->afterFreeblocks),
                    'current_overflow_next_page' => self::readUInt32($database->page($pageNumber), 0),
                    'current_pointer_map_type' => $currentEntry['type_name'] ?? null,
                    'current_pointer_map_parent' => $currentEntry['parent_page_number'] ?? null,
                    'vacuum_status' => $transition['status'] ?? null,
                    'next_pointer_map_type' => $transition['next_type_name'] ?? null,
                    'truncated_pointer_map_type' => $transition['truncated_type_name'] ?? null,
                    'materialized' => ($transition['status'] ?? null) === 'survives-as-free-page',
                    'truncated' => ($transition['status'] ?? null) === 'truncated-from-database',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $deleteResult
     */
    private static function sourceLabel(array $deleteResult, int $deleteIndex): string
    {
        $source = $deleteResult['source'] ?? null;
        if (is_string($source) && $source !== '') {
            return $source;
        }

        return "delete-{$deleteIndex}";
    }

    /**
     * @param array<int, string> $pageImages
     */
    private static function databaseWithPageImages(SQLiteDatabase $database, array $pageImages): SQLiteDatabase
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
        }

        return SQLiteDatabase::fromBytes(implode('', $pages));
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('SQLite overflow freeblock vacuum next122 could not read uint32');
        }

        return $value[1];
    }
}

final class SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextExtendedVariantPlan
{
    /**
     * @param list<array<string, mixed>> $currentSourceRows
     * @param list<array<string, mixed>> $vacuumRows
     */
    private function __construct(
        public readonly SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan $basePlan,
        private readonly array $currentSourceRows,
        private readonly array $vacuumRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $currentOverflowChains
     * @param array<string, mixed> $deleteResult
     */
    public static function tableLeafFromCurrentSourceDeleteResult(
        SQLiteDatabase $database,
        int $leafPageNumber,
        array $currentOverflowChains,
        array $deleteResult,
        int $maxTruncatedPages,
        bool $secureDelete = false,
    ): self {
        $currentSourceRows = self::buildCurrentSourceRows($database, $currentOverflowChains);
        $basePlan = SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan::tableLeafFromDeleteResult(
            $database,
            $leafPageNumber,
            $deleteResult,
            $maxTruncatedPages,
            $secureDelete,
        );

        return self::fromBasePlan($basePlan, $currentSourceRows);
    }

    /**
     * @param list<array<string, mixed>> $currentSourceRows
     */
    public static function fromBasePlan(
        SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan $basePlan,
        array $currentSourceRows,
    ): self {
        if ($currentSourceRows === []) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 requires current-source overflow rows');
        }

        $currentByPage = [];
        foreach ($currentSourceRows as $row) {
            $pageNumber = $row['page_number'] ?? null;
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 current-source row page must be an integer');
            }
            $currentByPage[$pageNumber] = $row;
        }

        $vacuumRows = [];
        foreach ($basePlan->rows as $row) {
            $pageNumber = (int) $row['page_number'];
            $current = $currentByPage[$pageNumber] ?? null;
            $vacuumRows[] = [
                'page_number' => $pageNumber,
                'source' => $current['source'] ?? null,
                'chain_position' => $current['chain_position'] ?? null,
                'current_next_page' => $current['current_next_page'] ?? null,
                'current_terminal' => $current['current_terminal'] ?? null,
                'current_payload_bytes' => $current['current_payload_bytes'] ?? null,
                'current_pointer_map_type' => $current['current_pointer_map_type'] ?? $row['current_pointer_map_type'],
                'current_pointer_map_parent' => $current['current_pointer_map_parent'] ?? $row['current_pointer_map_parent'],
                'leaf_freeblock_status' => $basePlan->materializedApplySummary()['freeblock_integrity_status'],
                'freelist_role' => $row['freelist_role'],
                'vacuum_status' => $row['vacuum_status'],
                'next_pointer_map_type' => $row['next_pointer_map_type'],
                'next_pointer_map_parent' => $row['next_pointer_map_parent'],
                'materialized_after_vacuum' => $row['vacuum_status'] !== 'truncated',
                'truncated_after_vacuum' => $row['vacuum_status'] === 'truncated',
            ];
        }

        return new self($basePlan, $currentSourceRows, $vacuumRows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currentSourceRows(): array
    {
        return $this->currentSourceRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vacuumRows(): array
    {
        return $this->vacuumRows;
    }

    /**
     * @return list<int>
     */
    public function survivingCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->vacuumRows, static fn (array $row): bool => $row['vacuum_status'] !== 'truncated'),
        ));
    }

    /**
     * @return list<int>
     */
    public function truncatedCurrentSourcePages(): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($this->vacuumRows, static fn (array $row): bool => $row['vacuum_status'] === 'truncated'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freeblock-vacuum-current-source-next140',
            'leaf_page' => $this->basePlan->toArray()['leaf_page'],
            'leaf_page_type' => $this->basePlan->toArray()['leaf_page_type'],
            'released_overflow_pages' => $this->basePlan->toArray()['released_overflow_pages'],
            'surviving_current_source_pages' => $this->survivingCurrentSourcePages(),
            'truncated_current_source_pages' => $this->truncatedCurrentSourcePages(),
            'final_database_page_count' => $this->basePlan->toArray()['final_database_page_count'],
            'final_first_freelist_trunk_page' => $this->basePlan->toArray()['final_first_freelist_trunk_page'],
            'final_freelist_page_count' => $this->basePlan->toArray()['final_freelist_page_count'],
            'final_freelist_page_numbers' => $this->basePlan->toArray()['final_freelist_page_numbers'],
            'updated_page_numbers' => $this->basePlan->toArray()['updated_page_numbers'],
            'current_source_overflow_chain_rows' => $this->currentSourceRows,
            'btree_overflow_freeblock_vacuum_current_source_next140' => $this->vacuumRows,
            'base_plan' => $this->basePlan->toArray(),
        ];
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int}> $chains
     * @return list<array<string, mixed>>
     */
    private static function buildCurrentSourceRows(SQLiteDatabase $database, array $chains): array
    {
        $rows = [];
        foreach (array_values($chains) as $chainIndex => $chain) {
            $firstPage = $chain['first_page'] ?? null;
            $payloadBytes = $chain['overflow_payload_bytes'] ?? null;
            if (!is_int($firstPage)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 chain is missing a first overflow page');
            }
            if (!is_int($payloadBytes)) {
                throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 chain is missing an overflow payload byte count');
            }

            $source = $chain['source'] ?? "current-overflow-chain-{$chainIndex}";
            foreach (SQLiteOverflowPage::chainLinksFromDatabase($database, $firstPage, $payloadBytes) as $position => $link) {
                $entry = $database->pointerMapEntryForPage($link['current_page'])->toArray();
                $rows[] = [
                    'source' => $source,
                    'chain_index' => $chainIndex,
                    'chain_position' => $position,
                    'page_number' => $link['current_page'],
                    'current_next_page' => $link['next_page'],
                    'current_payload_bytes' => $link['payload_bytes'],
                    'current_terminal' => $link['terminal'],
                    'current_pointer_map_type' => $entry['type_name'],
                    'current_pointer_map_parent' => $entry['parent_page_number'],
                ];
            }
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite b-tree overflow freeblock vacuum next140 requires at least one current-source overflow page');
        }

        return $rows;
    }
}
