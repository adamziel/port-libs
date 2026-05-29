<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,catalog:string,schemas:string,foreign_key_sql:string},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{foreign_key_violations:int,child_rootpage_errors:int,parent_rootpage_errors:int,missing_catalog_rootpages:int,pointer_map_conflicts:int,schemas:list<string>},next:array{source_id:string,offset:int}|null,next_state:array{ready:bool,blocking:list<string>},rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 122,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA rootpage pointer-map FK current-source next122 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA rootpage pointer-map FK current-source next122 limit must be positive');
        }

        $source = self::source($database, $schemas, $catalog, $foreignKeySql);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($database, $schemas, $catalog, $foreignKeySql);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $current = self::counts($rows);
        $blocking = [];
        if ($current['foreign_key_violations'] > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if ($current['missing_catalog_rootpages'] > 0) {
            $blocking[] = 'foreign_key_rootpage_catalog';
        }
        if ($current['pointer_map_conflicts'] > 0) {
            $blocking[] = 'rootpage_pointer_map';
        }
        if ($current['child_rootpage_errors'] > 0 || $current['parent_rootpage_errors'] > 0) {
            $blocking[] = 'rootpage_integrity';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'catalog' => $source['catalog'],
                'schemas' => $source['schemas'],
                'foreign_key_sql' => $source['foreign_key_sql'],
            ],
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $current,
            'next' => $complete ? null : [
                'source_id' => $source['source_id'],
                'offset' => $nextOffset,
            ],
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array<string,mixed>>
     */
    public static function collect(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
    ): array {
        $rootRows = self::rootRows($database);
        $foreignKeys = self::executeForeignKeySql($foreignKeySql, $schemas, $catalog);
        $rows = [];

        foreach ($foreignKeys['rows'] as $row) {
            $child = self::catalogRoot($catalog, $row['schema'], $row['table']);
            $parent = self::catalogRoot($catalog, $row['schema'], $row['parent']);
            $childRoot = $child === null ? null : self::rootRowForCatalogRoot($rootRows, $child);
            $parentRoot = $parent === null ? null : self::rootRowForCatalogRoot($rootRows, $parent);

            $rows[] = [
                'kind' => 'foreign_key_rootpage_pointer_map',
                'source' => 'foreign_key',
                'pragma_schema' => $foreignKeys['schema'],
                'target_schema' => $foreignKeys['target_schema'],
                'target' => $foreignKeys['target'],
                'target_source' => $foreignKeys['target_source'],
                'schema' => $row['schema'],
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'child_rootpage' => $child['rootpage'] ?? null,
                'parent_rootpage' => $parent['rootpage'] ?? null,
                'child_rootpage_status' => self::rootStatus($child, $childRoot),
                'parent_rootpage_status' => self::rootStatus($parent, $parentRoot),
                'child_pointer_map_type' => $childRoot['pointer_map_type'] ?? null,
                'child_pointer_map_parent' => $childRoot['pointer_map_parent'] ?? null,
                'child_pointer_map_page' => $childRoot['pointer_map_page'] ?? null,
                'parent_pointer_map_type' => $parentRoot['pointer_map_type'] ?? null,
                'parent_pointer_map_parent' => $parentRoot['pointer_map_parent'] ?? null,
                'parent_pointer_map_page' => $parentRoot['pointer_map_page'] ?? null,
                'message' => self::message($row, $child, $parent, $childRoot, $parentRoot),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function rootRows(string|SQLiteDatabase $database): array
    {
        $analysis = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($database);
        $rows = [
            'by_name' => [],
            'by_rootpage' => [],
        ];
        foreach ($analysis['rows'] as $row) {
            if (($row['type'] ?? null) !== 'table') {
                continue;
            }
            if (is_string($row['name'] ?? null)) {
                $rows['by_name'][$row['name']] = $row;
            }
            if (is_int($row['rootpage'] ?? null)) {
                $rows['by_rootpage'][$row['rootpage']] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array{name:string,rootpage:int|null}|null
     */
    private static function catalogRoot(SQLiteAttachedSchemaCatalog $catalog, string $schema, string $table): ?array
    {
        foreach ($catalog->schemaRecords($schema) as $record) {
            if ($record->type === 'table' && strcasecmp($record->name, $table) === 0) {
                return [
                    'name' => $record->name,
                    'rootpage' => $record->rootPage,
                ];
            }
        }

        return null;
    }

    /**
     * @param array{by_name:array<string,array<string,mixed>>,by_rootpage:array<int,array<string,mixed>>} $rootRows
     * @param array{name:string,rootpage:int|null} $catalogRoot
     * @return array<string,mixed>|null
     */
    private static function rootRowForCatalogRoot(array $rootRows, array $catalogRoot): ?array
    {
        $rootPage = $catalogRoot['rootpage'];
        if ($rootPage !== null && isset($rootRows['by_rootpage'][$rootPage])) {
            return $rootRows['by_rootpage'][$rootPage];
        }

        return $rootRows['by_name'][$catalogRoot['name']] ?? null;
    }

    /**
     * @param array{name:string,rootpage:int|null}|null $catalogRoot
     * @param array<string,mixed>|null $rootRow
     */
    private static function rootStatus(?array $catalogRoot, ?array $rootRow): string
    {
        if ($catalogRoot === null || ($catalogRoot['rootpage'] ?? null) === null) {
            return 'missing_catalog_rootpage';
        }
        if ($rootRow === null) {
            return 'missing_schema_rootpage';
        }

        return (string) ($rootRow['page_status'] ?? 'unknown');
    }

    /**
     * @param array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int} $row
     * @param array{name:string,rootpage:int|null}|null $child
     * @param array{name:string,rootpage:int|null}|null $parent
     * @param array<string,mixed>|null $childRoot
     * @param array<string,mixed>|null $parentRoot
     */
    private static function message(array $row, ?array $child, ?array $parent, ?array $childRoot, ?array $parentRoot): string
    {
        $rowid = $row['rowid'] === null ? 'NULL' : (string) $row['rowid'];
        $childStatus = self::rootStatus($child, $childRoot);
        $parentStatus = self::rootStatus($parent, $parentRoot);
        $childPointer = self::pointerSummary($childRoot);
        $parentPointer = self::pointerSummary($parentRoot);

        return "foreign key mismatch in {$row['schema']}.{$row['table']} rowid {$rowid} references {$row['parent']} fkid {$row['fkid']} (child {$childStatus} {$childPointer}; parent {$parentStatus} {$parentPointer})";
    }

    /**
     * @param array<string,mixed>|null $rootRow
     */
    private static function pointerSummary(?array $rootRow): string
    {
        if ($rootRow === null) {
            return 'pointer-map unavailable';
        }
        if (($rootRow['pointer_map_type'] ?? null) === null) {
            return 'pointer-map none';
        }

        return "pointer-map {$rootRow['pointer_map_type']} parent {$rootRow['pointer_map_parent']} page {$rootRow['pointer_map_page']}";
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,pragma:string,schema:string,target_schema:string,target:string|null,target_source:string,rows:list<array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    private static function executeForeignKeySql(string $sql, array $schemas, SQLiteAttachedSchemaCatalog $catalog): array
    {
        try {
            return SQLitePragmaForeignKeyIntegrity::executeTableValued($sql, $schemas, $catalog);
        } catch (InvalidArgumentException $tableValuedError) {
            try {
                return SQLitePragmaForeignKeyIntegrity::execute($sql, $schemas, $catalog);
            } catch (InvalidArgumentException) {
                throw $tableValuedError;
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{foreign_key_violations:int,child_rootpage_errors:int,parent_rootpage_errors:int,missing_catalog_rootpages:int,pointer_map_conflicts:int,schemas:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'foreign_key_violations' => 0,
            'child_rootpage_errors' => 0,
            'parent_rootpage_errors' => 0,
            'missing_catalog_rootpages' => 0,
            'pointer_map_conflicts' => 0,
            'schemas' => [],
        ];

        foreach ($rows as $row) {
            $counts['foreign_key_violations']++;
            if (is_string($row['schema'] ?? null)) {
                $counts['schemas'][] = $row['schema'];
            }
            foreach (['child', 'parent'] as $side) {
                $status = $row[$side . '_rootpage_status'] ?? null;
                if ($status === 'missing_catalog_rootpage' || $status === 'missing_schema_rootpage') {
                    $counts['missing_catalog_rootpages']++;
                } elseif ($status !== 'ok') {
                    $counts[$side . '_rootpage_errors']++;
                }
                if ($status === 'pointer_map') {
                    $counts['pointer_map_conflicts']++;
                }
            }
        }

        $counts['schemas'] = array_values(array_unique($counts['schemas']));

        return $counts;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{source_id:string,database:string,catalog:string,schemas:string,foreign_key_sql:string}
     */
    private static function source(string|SQLiteDatabase $database, array $schemas, SQLiteAttachedSchemaCatalog $catalog, string $foreignKeySql): array
    {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'catalog' => self::catalogHash($catalog),
            'schemas' => self::stableHash($schemas),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
        ];

        return [
            ...$source,
            'source_id' => self::stableHash($source),
        ];
    }

    private static function databaseHash(SQLiteDatabase $database): string
    {
        $context = hash_init('sha256');
        hash_update($context, (string) $database->header->pageSize);
        hash_update($context, ':');
        hash_update($context, (string) $database->pageCount());
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            hash_update($context, $database->page($pageNumber));
        }

        return hash_final($context);
    }

    private static function catalogHash(SQLiteAttachedSchemaCatalog $catalog): string
    {
        $snapshot = [
            'database_list' => $catalog->databaseList(),
            'schemas' => [],
        ];
        foreach ($catalog->databaseList() as $database) {
            $schema = (string) $database['name'];
            $snapshot['schemas'][$schema] = array_map(
                static fn (SQLiteSchemaRecord $record): array => [
                    'type' => $record->type,
                    'name' => $record->name,
                    'table' => $record->tableName,
                    'rootpage' => $record->rootPage,
                    'sql' => $record->sql,
                    'rowid' => $record->rowId,
                ],
                $catalog->schemaRecords($schema),
            );
        }

        return self::stableHash($snapshot);
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA rootpage pointer-map FK current-source next122 cursor does not match the current database/catalog/schema source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA rootpage pointer-map FK current-source next122 cursor offset does not match the requested page offset');
        }
    }

    private static function stableHash(mixed $value): string
    {
        return hash('sha256', self::stableEncode($value));
    }

    private static function stableEncode(mixed $value): string
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value);
            }

            return '[' . implode(',', array_map(static fn (mixed $item, string|int $key): string => self::stableEncode((string) $key) . ':' . self::stableEncode($item), $value, array_keys($value))) . ']';
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
