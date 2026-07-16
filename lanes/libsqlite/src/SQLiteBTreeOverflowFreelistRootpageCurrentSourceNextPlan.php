<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $rootpageRows
     */
    private function __construct(
        public readonly SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan $reusePlan,
        public readonly string $objectType,
        public readonly string $objectName,
        public readonly string $tableName,
        public readonly string $sql,
        public readonly array $rootpageRows,
    ) {
    }

    /**
     * @param list<array{source?:string,first_page:int,overflow_payload_bytes:int,rowids?:list<int>,record_values?:list<list<mixed>>}> $chains
     * @param array<int, string> $rootPageImages
     */
    public static function fromOverflowChains(
        SQLiteDatabase $database,
        array $chains,
        string $objectType,
        string $objectName,
        string $tableName,
        string $sql,
        array $rootPageImages,
        bool $secureDelete = false,
    ): self {
        self::assertSchemaObject($objectType, $objectName, $tableName, $sql);
        if ($rootPageImages === []) {
            throw new \InvalidArgumentException('SQLite overflow freelist rootpage reuse requires at least one root page image');
        }

        $reusePlan = SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowChains(
            $database,
            $chains,
            count($rootPageImages),
            null,
            $rootPageImages,
            $secureDelete,
        );

        $unexpectedImages = array_diff(array_keys($rootPageImages), $reusePlan->reusedPageNumbers());
        if ($unexpectedImages !== []) {
            throw new \InvalidArgumentException('SQLite overflow freelist rootpage image was not reused from released overflow pages');
        }

        return new self(
            $reusePlan,
            $objectType,
            $objectName,
            $tableName,
            $sql,
            self::rootpageRows($reusePlan, $objectType, $objectName, $tableName, $sql),
        );
    }

    /**
     * @return list<int>
     */
    public function rootPageNumbers(): array
    {
        return $this->reusePlan->reusedPageNumbers();
    }

    /**
     * @return array<int, string>
     */
    public function pageImages(): array
    {
        return $this->reusePlan->pageImages();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => 'btree-overflow-freelist-rootpage-current-source-next126',
            'schema_object' => [
                'type' => $this->objectType,
                'name' => $this->objectName,
                'table_name' => $this->tableName,
                'sql' => $this->sql,
            ],
            'released_overflow_pages' => $this->reusePlan->releasedOverflowPages(),
            'root_page_numbers' => $this->rootPageNumbers(),
            'updated_page_numbers' => array_keys($this->pageImages()),
            'reuse' => $this->reusePlan->toArray(),
            'btree_overflow_freelist_rootpage_current_source_next126' => $this->rootpageRows,
        ];
    }

    private static function assertSchemaObject(string $objectType, string $objectName, string $tableName, string $sql): void
    {
        if (!in_array($objectType, ['table', 'index'], true)) {
            throw new \InvalidArgumentException('SQLite overflow freelist rootpage object type must be table or index');
        }
        foreach (['object name' => $objectName, 'table name' => $tableName, 'SQL' => $sql] as $label => $value) {
            if ($value === '') {
                throw new \InvalidArgumentException("SQLite overflow freelist rootpage {$label} must be non-empty");
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rootpageRows(
        SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan $reusePlan,
        string $objectType,
        string $objectName,
        string $tableName,
        string $sql,
    ): array {
        $rows = [];
        foreach ($reusePlan->reuseRows as $row) {
            if (($row['reuse_pointer_map_type'] ?? null) !== 'root-page') {
                continue;
            }

            $pageNumber = (int) $row['page_number'];
            $rows[] = [
                'object_type' => $objectType,
                'object_name' => $objectName,
                'table_name' => $tableName,
                'schema_rootpage' => $pageNumber,
                'sql' => $sql,
                'release_source' => $row['release_source'] ?? null,
                'allocation_source' => $row['allocation_source'] ?? null,
                'current_source_state' => 'obsolete-overflow-page',
                'free_source_state' => 'freelist-page',
                'next_source_state' => 'schema-rootpage',
                'before_pointer_map_type' => $row['before_pointer_map_type'] ?? null,
                'before_pointer_map_parent' => $row['before_pointer_map_parent'] ?? null,
                'free_pointer_map_type' => $row['free_pointer_map_type'] ?? null,
                'free_pointer_map_parent' => $row['free_pointer_map_parent'] ?? null,
                'root_pointer_map_type' => $row['reuse_pointer_map_type'] ?? null,
                'root_pointer_map_parent' => $row['reuse_pointer_map_parent'] ?? null,
                'materialized_with_supplied_image' => $row['materialized_with_supplied_image'] ?? false,
                'root_page_type_byte' => $row['next_page_type_byte'] ?? null,
            ];
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite overflow freelist rootpage reuse did not allocate a root page');
        }

        return $rows;
    }
}
