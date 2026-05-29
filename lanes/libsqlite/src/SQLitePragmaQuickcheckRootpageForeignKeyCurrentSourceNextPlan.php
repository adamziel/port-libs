<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaQuickcheckRootpageForeignKeyCurrentSourceNextPlan
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $currentSchemas
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $nextSchemas
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array<string,mixed>,next_source:array<string,mixed>,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array<string,mixed>,next_counts:array<string,mixed>,delta:array<string,mixed>,next_state:array{ready:bool,blocking:list<string>},next:array{source_id:string,offset:int}|null,rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $currentDatabase,
        array $currentSchemas,
        SQLiteAttachedSchemaCatalog $currentCatalog,
        string|SQLiteDatabase $nextDatabase,
        array $nextSchemas,
        SQLiteAttachedSchemaCatalog $nextCatalog,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        string $quickCheckSql = 'PRAGMA quick_check',
        int $offset = 0,
        int $limit = 142,
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck rootpage foreign-key current-source next142 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck rootpage foreign-key current-source next142 limit must be positive');
        }
        self::validateQuickCheckSql($quickCheckSql);

        $currentRows = self::sideRows('current', $currentDatabase, $currentSchemas, $currentCatalog, $foreignKeySql, $quickCheckSql);
        $nextRows = self::sideRows('next', $nextDatabase, $nextSchemas, $nextCatalog, $foreignKeySql, $quickCheckSql);
        $currentSource = self::source($currentDatabase, $currentSchemas, $currentCatalog, $foreignKeySql, $quickCheckSql);
        $nextSource = self::source($nextDatabase, $nextSchemas, $nextCatalog, $foreignKeySql, $quickCheckSql);
        $sourceId = self::stableHash([
            'mode' => 'pragma-quickcheck-rootpage-foreignkey-current-source-next142',
            'current' => $currentSource['source_id'],
            'next' => $nextSource['source_id'],
        ]);
        if ($cursor !== null) {
            self::validateCursor($cursor, $sourceId, $offset);
        }

        $rows = [...$currentRows, ...$nextRows];
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $currentCounts = self::counts($currentRows);
        $nextCounts = self::counts($nextRows);
        $blocking = self::blocking($nextCounts);

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $sourceId,
            'current_source' => self::publicSource($currentSource),
            'next_source' => self::publicSource($nextSource),
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => $currentCounts,
            'next_counts' => $nextCounts,
            'delta' => self::delta($currentCounts, $nextCounts),
            'next_state' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'next' => $complete ? null : [
                'source_id' => $sourceId,
                'offset' => $nextOffset,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array<string,mixed>>
     */
    private static function sideRows(
        string $side,
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        string $foreignKeySql,
        string $quickCheckSql,
    ): array {
        $quickRows = self::quickCheckRows($database, $quickCheckSql);
        $foreignKeyRows = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::collect($database, $schemas, $catalog, $foreignKeySql);

        return [
            ...array_map(
                static fn (array $row): array => [
                    'side' => $side,
                    'phase' => 'quick_check_rootpage',
                    'kind' => 'quick_check_rootpage',
                    'source' => 'quick_check',
                    ...$row,
                ],
                $quickRows,
            ),
            ...array_map(
                static fn (array $row): array => [
                    'side' => $side,
                    'phase' => 'foreign_key_rootpage',
                    ...$row,
                ],
                $foreignKeyRows,
            ),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function quickCheckRows(string|SQLiteDatabase $database, string $quickCheckSql): array
    {
        $scope = self::quickCheckScope($quickCheckSql);
        $analysis = SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext::analyze($database);
        $rows = [];
        foreach ($analysis['rows'] as $row) {
            if (($row['status'] ?? null) === 'ok' || ($row['status'] ?? null) === 'ignored') {
                continue;
            }
            if ($scope['target'] !== null && !self::rowMatchesTarget($database, $row, $scope['target'])) {
                continue;
            }
            $rows[] = $row;
        }

        return $scope['limit'] === null ? $rows : array_slice($rows, 0, $scope['limit']);
    }

    private static function rowMatchesTarget(string|SQLiteDatabase $database, array $row, string $target): bool
    {
        if (is_string($database)) {
            $database = SQLiteDatabase::fromBytes($database);
        }
        $names = [];
        foreach ($database->schemaRecords() as $record) {
            if ($record->type === 'table' && strcasecmp($record->name, $target) === 0) {
                $names[$record->name] = true;
                break;
            }
        }
        foreach ($database->schemaRecords() as $record) {
            if ($record->type === 'index' && isset($names[$record->tableName])) {
                $names[$record->name] = true;
            }
        }

        return isset($names[(string) ($row['name'] ?? '')]) || isset($names[(string) ($row['table'] ?? '')]);
    }

    /**
     * @return array{pragma:string,scope:string,target:string|null,limit:int|null}
     */
    private static function quickCheckScope(string $sql): array
    {
        $trimmed = trim(rtrim(trim($sql), ';'));
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
        if (preg_match('/^PRAGMA\s+(?:(?:[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(?<pragma>quick_check)\s*(?:\(\s*(?<limit1>\d+)\s*\)|=\s*(?<limit2>\d+))$/i', $trimmed, $matches) === 1) {
            return [
                'pragma' => 'quick_check',
                'scope' => 'database',
                'target' => null,
                'limit' => (int) ($matches['limit1'] !== '' ? $matches['limit1'] : $matches['limit2']),
            ];
        }
        if (preg_match('/^PRAGMA\s+(?:(?:[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(?<pragma>quick_check)\s*\(\s*(?<target>' . $identifier . ')\s*\)$/i', $trimmed, $matches) === 1) {
            return [
                'pragma' => 'quick_check',
                'scope' => 'table',
                'target' => self::unquoteIdentifier($matches['target']),
                'limit' => null,
            ];
        }
        if (preg_match('/^PRAGMA\s+(?:(?:[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(?<pragma>quick_check)$/i', $trimmed) === 1) {
            return [
                'pragma' => 'quick_check',
                'scope' => 'database',
                'target' => null,
                'limit' => null,
            ];
        }

        throw new InvalidArgumentException('SQLite PRAGMA quickcheck rootpage foreign-key current-source next142 requires PRAGMA quick_check SQL');
    }

    private static function validateQuickCheckSql(string $sql): void
    {
        self::quickCheckScope($sql);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{quick_check_rootpages:int,quick_check_errors:int,foreign_key_violations:int,child_rootpage_errors:int,parent_rootpage_errors:int,missing_catalog_rootpages:int,pointer_map_conflicts:int,row_phases:array<string,int>,schemas:list<string>}
     */
    private static function counts(array $rows): array
    {
        $counts = [
            'quick_check_rootpages' => 0,
            'quick_check_errors' => 0,
            'foreign_key_violations' => 0,
            'child_rootpage_errors' => 0,
            'parent_rootpage_errors' => 0,
            'missing_catalog_rootpages' => 0,
            'pointer_map_conflicts' => 0,
            'row_phases' => [],
            'schemas' => [],
        ];
        foreach ($rows as $row) {
            $phase = (string) ($row['phase'] ?? 'unknown');
            $counts['row_phases'][$phase] = ($counts['row_phases'][$phase] ?? 0) + 1;
            if (is_string($row['schema'] ?? null)) {
                $counts['schemas'][] = $row['schema'];
            }
            if ($phase === 'quick_check_rootpage') {
                $counts['quick_check_rootpages']++;
                $counts['quick_check_errors']++;
                if (($row['page_status'] ?? null) === 'pointer_map') {
                    $counts['pointer_map_conflicts']++;
                }
                continue;
            }
            if ($phase === 'foreign_key_rootpage') {
                $counts['foreign_key_violations']++;
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
        }
        $counts['schemas'] = array_values(array_unique($counts['schemas']));

        return $counts;
    }

    /**
     * @param array<string,mixed> $counts
     * @return list<string>
     */
    private static function blocking(array $counts): array
    {
        $blocking = [];
        if (($counts['quick_check_errors'] ?? 0) > 0) {
            $blocking[] = 'quick_check';
        }
        if (($counts['foreign_key_violations'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if (($counts['missing_catalog_rootpages'] ?? 0) > 0) {
            $blocking[] = 'foreign_key_rootpage_catalog';
        }
        if (($counts['pointer_map_conflicts'] ?? 0) > 0) {
            $blocking[] = 'rootpage_pointer_map';
        }
        if (($counts['child_rootpage_errors'] ?? 0) > 0 || ($counts['parent_rootpage_errors'] ?? 0) > 0) {
            $blocking[] = 'rootpage_integrity';
        }

        return $blocking;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return array<string,mixed>
     */
    private static function delta(array $current, array $next): array
    {
        $keys = ['quick_check_errors', 'foreign_key_violations', 'child_rootpage_errors', 'parent_rootpage_errors', 'missing_catalog_rootpages', 'pointer_map_conflicts'];
        $delta = [];
        foreach ($keys as $key) {
            $delta[$key] = (int) ($next[$key] ?? 0) - (int) ($current[$key] ?? 0);
        }
        $delta['total'] = ((int) $next['quick_check_rootpages'] + (int) $next['foreign_key_violations']) - ((int) $current['quick_check_rootpages'] + (int) $current['foreign_key_violations']);
        $delta['cleared'] = ((int) $current['quick_check_errors'] + (int) $current['foreign_key_violations']) > 0
            && ((int) $next['quick_check_errors'] + (int) $next['foreign_key_violations']) === 0;

        return $delta;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array<string,mixed>
     */
    private static function source(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        string $foreignKeySql,
        string $quickCheckSql,
    ): array {
        $scope = self::quickCheckScope($quickCheckSql);
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'catalog' => self::stableHash(self::catalogSource($catalog)),
            'schemas' => self::stableHash($schemas),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'quick_check_sql' => self::normalizeSql($quickCheckSql),
            'quick_check_scope' => $scope['scope'],
            'quick_check_target' => $scope['target'],
            'quick_check_limit' => $scope['limit'],
        ];

        return [
            ...$source,
            'source_id' => self::stableHash($source),
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function publicSource(array $source): array
    {
        unset($source['source_id']);

        return $source;
    }

    /**
     * @return array{generation:int,search_order:list<string>,database_list:list<array{seq:int,name:string,file:string|null}>,schema_records:array<string,list<array{type:string,name:string,table:string,rootpage:int|null,sql:string|null,rowid:int}>>}
     */
    private static function catalogSource(SQLiteAttachedSchemaCatalog $catalog): array
    {
        $schemaRecords = [];
        foreach ($catalog->databaseList() as $database) {
            $schema = $database['name'];
            $schemaRecords[$schema] = array_map(
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

        return [
            'generation' => $catalog->schemaGeneration(),
            'search_order' => $catalog->searchOrder(),
            'database_list' => $catalog->databaseList(),
            'schema_records' => $schemaRecords,
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

    private static function normalizeSql(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', rtrim(trim($sql), ';')) ?? trim($sql));
    }

    private static function unquoteIdentifier(string $value): string
    {
        $value = trim($value);
        $first = $value[0] ?? '';
        $last = substr($value, -1);
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($value, 1, -1));
        }
        if ($first === "'" && $last === "'") {
            return str_replace("''", "'", substr($value, 1, -1));
        }
        if (($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck rootpage foreign-key current-source next142 cursor does not match the current/next source');
        }
        $cursorOffset = $cursor['next_offset'] ?? $cursor['offset'] ?? null;
        if ($cursorOffset !== null && $cursorOffset !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck rootpage foreign-key current-source next142 cursor offset does not match the requested page offset');
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
