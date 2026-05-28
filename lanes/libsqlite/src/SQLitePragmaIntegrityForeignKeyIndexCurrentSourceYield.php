<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield
{
    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<array{kind:string,source:string,schema:string|null,target_source:string,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,page:int|null,pointer_map_page:int|null,message:string}>
     */
    public static function collect(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        $rows = [];

        foreach (self::schemaOrder($schemas, $catalog) as $schema) {
            $schemaRows = SQLitePragmaForeignKeyIndexIntegrityYield::collect(
                $catalog->schemaRecords($schema),
                $schemas[$schema]['foreignKeys'],
                $schemas[$schema]['tables'],
            );

            foreach ($schemaRows as $row) {
                $rows[] = [
                    'kind' => $row['kind'],
                    'source' => $row['kind'] === 'foreign_key_check' ? 'foreign_key' : 'index',
                    'schema' => $schema,
                    'target_source' => 'catalog-current',
                    'table' => $row['table'],
                    'rowid' => $row['rowid'],
                    'parent' => $row['parent'],
                    'fkid' => $row['fkid'],
                    'index' => $row['index'],
                    'columns' => $row['columns'],
                    'collations' => $row['collations'],
                    'status' => $row['status'],
                    'page' => null,
                    'pointer_map_page' => null,
                    'message' => self::schemaMessage($schema, $row['message']),
                ];
            }
        }

        foreach (SQLitePragmaIntegrityCurrentNextYield::collect($database, [], $integritySql) as $row) {
            $rows[] = [
                'kind' => $row['kind'],
                'source' => $row['source'],
                'schema' => $row['schema'],
                'target_source' => 'integrity-check',
                'table' => $row['table'],
                'rowid' => $row['rowid'],
                'parent' => $row['parent'],
                'fkid' => $row['fkid'],
                'index' => null,
                'columns' => [],
                'collations' => [],
                'status' => 'error',
                'page' => $row['page'],
                'pointer_map_page' => $row['pointer_map_page'],
                'message' => $row['message'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return array{status:string,offset:int,limit:int,count:int,total:int,next_offset:int|null,complete:bool,current:array{index_admissions:int,index_blockers:int,foreign_key_violations:int,integrity_errors:int,schemas:list<string>},next:array{ready:bool,blocking:list<string>},rows:list<array{kind:string,source:string,schema:string|null,target_source:string,table:string|null,rowid:int|string|null,parent:string|null,fkid:int|null,index:string|null,columns:list<string>,collations:list<string>,status:string,page:int|null,pointer_map_page:int|null,message:string}>}
     */
    public static function page(
        string|SQLiteDatabase $database,
        array $schemas,
        SQLiteAttachedSchemaCatalog $catalog,
        int $offset = 0,
        int $limit = 88,
        string $integritySql = 'PRAGMA integrity_check',
    ): array {
        if ($offset < 0) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity/foreign-key/index current-source next88 offset must be non-negative');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('SQLite PRAGMA integrity/foreign-key/index current-source next88 limit must be positive');
        }

        $rows = self::collect($database, $schemas, $catalog, $integritySql);
        $pageRows = array_slice($rows, $offset, $limit);
        $nextOffset = $offset + count($pageRows);
        $complete = $nextOffset >= count($rows);
        $indexBlockers = count(array_filter(
            $rows,
            static fn (array $row): bool => $row['kind'] === 'index_admission' && $row['status'] === 'blocked'
        ));
        $foreignKeyViolations = self::countRows($rows, 'foreign_key_check');
        $integrityErrors = count(array_filter(
            $rows,
            static fn (array $row): bool => $row['target_source'] === 'integrity-check'
        ));
        $blocking = [];
        if ($indexBlockers > 0) {
            $blocking[] = 'foreign_key_parent_unique_index';
        }
        if ($foreignKeyViolations > 0) {
            $blocking[] = 'foreign_key_check';
        }
        if ($integrityErrors > 0) {
            $blocking[] = 'integrity_check';
        }

        return [
            'status' => $blocking === [] ? 'ok' : 'blocked',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($pageRows),
            'total' => count($rows),
            'next_offset' => $complete ? null : $nextOffset,
            'complete' => $complete,
            'current' => [
                'index_admissions' => self::countRows($rows, 'index_admission'),
                'index_blockers' => $indexBlockers,
                'foreign_key_violations' => $foreignKeyViolations,
                'integrity_errors' => $integrityErrors,
                'schemas' => array_values(array_unique(array_filter(
                    array_map(static fn (array $row): ?string => $row['schema'], $rows),
                    static fn (?string $schema): bool => $schema !== null,
                ))),
            ],
            'next' => [
                'ready' => $blocking === [],
                'blocking' => $blocking,
            ],
            'rows' => $pageRows,
        ];
    }

    /**
     * @param array<string,array{tables:array<string,list<array<string,mixed>>>,foreignKeys:list<array<string,mixed>>}> $schemas
     * @return list<string>
     */
    private static function schemaOrder(array $schemas, SQLiteAttachedSchemaCatalog $catalog): array
    {
        $ordered = [];
        foreach ($catalog->databaseList() as $database) {
            $schema = (string) $database['name'];
            if (isset($schemas[$schema])) {
                $ordered[] = $schema;
            }
        }

        foreach (array_keys($schemas) as $schema) {
            if (!in_array($schema, $ordered, true)) {
                throw new InvalidArgumentException("SQLite PRAGMA integrity/foreign-key/index current-source next88 schema {$schema} is not attached");
            }
        }

        return $ordered;
    }

    /**
     * @param list<array{kind:string}> $rows
     */
    private static function countRows(array $rows, string $kind): int
    {
        return count(array_filter($rows, static fn (array $row): bool => $row['kind'] === $kind));
    }

    private static function schemaMessage(string $schema, string $message): string
    {
        if (str_starts_with($message, $schema . '.')) {
            return $message;
        }

        return $schema . '.' . $message;
    }
}
