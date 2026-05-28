<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param list<SQLiteSchemaRecord> $records
     * @param array{source_id?:string,next_offset?:int|null,offset?:int|null}|null $cursor
     * @return array{status:string,source_id:string,current_source:array{database:string,schemas:string,records:string,foreign_key_sql:string,quickcheck_sql:string},offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{quickcheck_errors:int,stat_tables:int,stat_rows:int,stat_blockers:int,foreign_key_violations:int,schemas:list<string>},next:array{source_id:string,offset:int}|null,next_state:array{ready:bool,blocking:list<string>},rows:list<array<string,mixed>>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $schemas,
        array $records,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        int $offset = 0,
        int $limit = 123,
        string $quickcheckSql = 'PRAGMA quick_check',
        ?array $cursor = null,
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/stat/FK current-source next123 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/stat/FK current-source next123 limit must be positive');
        }

        $source = self::source($database, $schemas, $records, $foreignKeySql, $quickcheckSql);
        if ($cursor !== null) {
            self::validateCursor($cursor, $source['source_id'], $offset);
        }

        $rows = self::collect($database, $schemas, $records, $foreignKeySql, $quickcheckSql);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $current = self::counts($rows);
        $blocking = [];
        if ($current['quickcheck_errors'] > 0) {
            $blocking[] = 'quick_check';
        }
        if ($current['stat_blockers'] > 0) {
            $blocking[] = 'sqlite_stat_catalog';
        }
        if ($current['foreign_key_violations'] > 0) {
            $blocking[] = 'foreign_key_check';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'source_id' => $source['source_id'],
            'current_source' => [
                'database' => $source['database'],
                'schemas' => $source['schemas'],
                'records' => $source['records'],
                'foreign_key_sql' => $source['foreign_key_sql'],
                'quickcheck_sql' => $source['quickcheck_sql'],
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
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    public static function collect(
        string|SQLiteDatabase $database,
        array $schemas,
        array $records,
        string $foreignKeySql = 'PRAGMA foreign_key_check',
        string $quickcheckSql = 'PRAGMA quick_check',
    ): array {
        $quick = SQLitePragmaIntegrityCheck::execute($quickcheckSql, $database);
        if ($quick['pragma'] !== 'quick_check') {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/stat/FK current-source next123 requires PRAGMA quick_check');
        }

        $rows = [];
        foreach ($quick['errors'] as $message) {
            $rows[] = [
                'kind' => 'quick_check',
                'source' => 'quick_check',
                'schema' => $quick['schema'] ?? 'main',
                'table' => null,
                'rowid' => null,
                'message' => $message,
            ];
        }

        foreach (self::statRows($records) as $row) {
            $rows[] = $row;
        }

        $foreignKeys = SQLitePragmaForeignKeyIntegrity::execute($foreignKeySql, $schemas);
        foreach ($foreignKeys['rows'] as $row) {
            $rows[] = [
                'kind' => 'foreign_key_check',
                'source' => 'foreign_key',
                'schema' => $row['schema'],
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'message' => self::foreignKeyMessage($row),
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'kind' => 'quick_check',
                'source' => 'quick_check',
                'schema' => $quick['schema'] ?? 'main',
                'table' => null,
                'rowid' => null,
                'message' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     * @return list<array<string,mixed>>
     */
    private static function statRows(array $records): array
    {
        $tableNames = [];
        foreach ($records as $record) {
            if ($record->type === 'table') {
                $tableNames[strtolower($record->name)] = true;
            }
        }

        $rows = [];
        foreach ($records as $record) {
            if ($record->type !== 'table' || !preg_match('/^sqlite_stat([134])$/i', $record->name, $match)) {
                continue;
            }

            $columns = self::declaredColumns((string) $record->sql);
            $required = $match[1] === '4' ? ['tbl', 'idx', 'neq', 'nlt', 'ndlt', 'sample'] : ['tbl', 'idx', 'stat'];
            $missing = array_values(array_diff($required, $columns));
            $status = $missing === [] ? 'ok' : 'malformed';
            $tracked = count(array_filter(array_keys($tableNames), static fn (string $name): bool => !str_starts_with($name, 'sqlite_')));

            $rows[] = [
                'kind' => 'sqlite_stat' . $match[1],
                'source' => 'stat_catalog',
                'schema' => 'main',
                'table' => $record->name,
                'rowid' => $record->rowId,
                'rootpage' => $record->rootPage,
                'status' => $status,
                'tracked_tables' => $tracked,
                'message' => $status === 'ok'
                    ? "{$record->name} catalog ready for {$tracked} planner tables"
                    : "{$record->name} catalog is missing " . implode('/', $missing) . ' columns',
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private static function declaredColumns(string $sql): array
    {
        if (!preg_match('/\((.*)\)/s', $sql, $match)) {
            return [];
        }

        $columns = [];
        foreach (explode(',', $match[1]) as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }
            $name = strtolower(trim((string) preg_split('/\s+/', $definition)[0], "\"'`[]"));
            if ($name !== '') {
                $columns[] = $name;
            }
        }

        return $columns;
    }

    /**
     * @param array{schema:string,table:string,rowid:int|string|null,parent:string,fkid:int} $row
     */
    private static function foreignKeyMessage(array $row): string
    {
        $rowid = $row['rowid'] === null ? 'NULL' : (string) $row['rowid'];

        return "foreign key mismatch in {$row['schema']}.{$row['table']} rowid {$rowid} references {$row['parent']} fkid {$row['fkid']}";
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{quickcheck_errors:int,stat_tables:int,stat_rows:int,stat_blockers:int,foreign_key_violations:int,schemas:list<string>}
     */
    private static function counts(array $rows): array
    {
        $schemas = [];
        $quick = 0;
        $statTables = [];
        $statRows = 0;
        $statBlockers = 0;
        $fk = 0;
        foreach ($rows as $row) {
            if (($row['source'] ?? null) === 'quick_check' && ($row['message'] ?? null) !== 'ok') {
                $quick++;
            } elseif (($row['source'] ?? null) === 'stat_catalog') {
                $statRows++;
                $statTables[(string) $row['table']] = true;
                if (($row['status'] ?? null) !== 'ok') {
                    $statBlockers++;
                }
            } elseif (($row['source'] ?? null) === 'foreign_key') {
                $fk++;
                if (is_string($row['schema'] ?? null)) {
                    $schemas[] = $row['schema'];
                }
            }
        }

        return [
            'quickcheck_errors' => $quick,
            'stat_tables' => count($statTables),
            'stat_rows' => $statRows,
            'stat_blockers' => $statBlockers,
            'foreign_key_violations' => $fk,
            'schemas' => array_values(array_unique($schemas)),
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @param list<SQLiteSchemaRecord> $records
     * @return array{source_id:string,database:string,schemas:string,records:string,foreign_key_sql:string,quickcheck_sql:string}
     */
    private static function source(string|SQLiteDatabase $database, array $schemas, array $records, string $foreignKeySql, string $quickcheckSql): array
    {
        $source = [
            'database' => is_string($database) ? hash('sha256', $database) : self::databaseHash($database),
            'schemas' => self::stableHash($schemas),
            'records' => self::recordsHash($records),
            'foreign_key_sql' => self::normalizeSql($foreignKeySql),
            'quickcheck_sql' => self::normalizeSql($quickcheckSql),
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

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function recordsHash(array $records): string
    {
        return self::stableHash(array_map(
            static fn (SQLiteSchemaRecord $record): array => [
                'type' => $record->type,
                'name' => $record->name,
                'table' => $record->tableName,
                'rootpage' => $record->rootPage,
                'sql' => $record->sql,
                'rowid' => $record->rowId,
            ],
            $records,
        ));
    }

    /**
     * @param array<string,mixed> $cursor
     */
    private static function validateCursor(array $cursor, string $sourceId, int $offset): void
    {
        if (($cursor['source_id'] ?? null) !== $sourceId) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/stat/FK current-source next123 cursor source changed');
        }

        if (array_key_exists('next_offset', $cursor) && $cursor['next_offset'] !== null && $cursor['next_offset'] !== $offset) {
            throw new InvalidArgumentException('SQLite PRAGMA quickcheck/stat/FK current-source next123 cursor offset changed');
        }
    }

    /**
     * @param mixed $value
     */
    private static function stableHash($value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function normalizeSql(string $sql): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', rtrim($sql, " \t\r\n;")) ?? trim($sql)));
    }
}
