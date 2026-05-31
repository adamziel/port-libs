<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaSchemaLiveReloadPlan
{
    /**
     * @param list<SQLiteSchemaRecord> $beforeRecords
     * @param list<SQLiteSchemaRecord> $afterRecords
     * @param list<array{id:string,sql:string}> $queries
     * @return array{
     *     status:string,
     *     operation:string,
     *     before_generation:int,
     *     after_generation:int,
     *     generation_changed:bool,
     *     changed_queries:list<string>,
     *     preserved_queries:list<string>,
     *     queries:array<string,array{
     *         id:string,
     *         sql:string,
     *         pragma:string,
     *         target:string,
     *         before_rows:list<array<string,int|string|null>>,
     *         after_rows:list<array<string,int|string|null>>,
     *         changed:bool,
     *         reprepare_required:bool,
     *         reason:string
     *     }>,
     *     dependencies:list<string>
     * }
     */
    public static function compare(
        array $beforeRecords,
        array $afterRecords,
        array $queries,
        int $beforeGeneration = 0,
        ?int $afterGeneration = null,
    ): array {
        self::assertRecords($beforeRecords, 'before');
        self::assertRecords($afterRecords, 'after');

        if ($queries === []) {
            throw new InvalidArgumentException('SQLite PRAGMA schema live reload plan requires at least one query');
        }

        $afterGeneration ??= $beforeGeneration + (self::recordsSignature($beforeRecords) === self::recordsSignature($afterRecords) ? 0 : 1);
        $beforeCatalog = new SQLitePragmaSchemaCatalog($beforeRecords);
        $afterCatalog = new SQLitePragmaSchemaCatalog($afterRecords);
        $queryRows = [];
        $changed = [];
        $preserved = [];

        foreach ($queries as $query) {
            $id = trim((string) ($query['id'] ?? ''));
            $sql = trim((string) ($query['sql'] ?? ''));
            if ($id === '' || $sql === '') {
                throw new InvalidArgumentException('SQLite PRAGMA schema live reload queries need non-empty id and sql');
            }
            if (isset($queryRows[$id])) {
                throw new InvalidArgumentException("SQLite PRAGMA schema live reload query {$id} is duplicated");
            }

            $before = $beforeCatalog->execute($sql);
            $after = $afterCatalog->execute($sql);
            $rowsChanged = $before['rows'] !== $after['rows'];

            if ($rowsChanged) {
                $changed[] = $id;
            } else {
                $preserved[] = $id;
            }

            $queryRows[$id] = [
                'id' => $id,
                'sql' => $sql,
                'pragma' => $after['pragma'],
                'target' => $after['target'],
                'before_rows' => $before['rows'],
                'after_rows' => $after['rows'],
                'changed' => $rowsChanged,
                'reprepare_required' => $rowsChanged && $afterGeneration !== $beforeGeneration,
                'reason' => $rowsChanged ? 'schema_cookie_changed_refresh_pragma_rows' : 'pragma_rows_stable_after_schema_refresh',
            ];
        }

        return [
            'status' => 'ok',
            'operation' => 'pragma-schema-live-reload',
            'before_generation' => $beforeGeneration,
            'after_generation' => $afterGeneration,
            'generation_changed' => $afterGeneration !== $beforeGeneration,
            'changed_queries' => $changed,
            'preserved_queries' => $preserved,
            'queries' => $queryRows,
            'dependencies' => ['sqlite-pragma-schema-catalog', 'sqlite-schema-cookie-live-reload'],
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function assertRecords(array $records, string $label): void
    {
        foreach ($records as $record) {
            if (!$record instanceof SQLiteSchemaRecord) {
                throw new InvalidArgumentException("SQLite PRAGMA schema live reload {$label} records must be SQLiteSchemaRecord instances");
            }
        }
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function recordsSignature(array $records): string
    {
        $parts = [];
        foreach ($records as $record) {
            $parts[] = implode("\0", [
                $record->type,
                $record->name,
                $record->tableName,
                (string) ($record->rootPage ?? ''),
                (string) ($record->sql ?? ''),
                (string) $record->rowId,
            ]);
        }

        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }
}
