<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;
use Throwable;

final class SQLiteAttachTempSchemaResolutionPlan
{
    /**
     * @param list<array{label?:string,sql:string}> $statements
     * @param list<array{kind:string,name:string,new?:array<string,mixed>,old?:array<string,mixed>|null}> $probes
     * @param callable(string,string): list<SQLiteSchemaRecord>|null $recordLoader
     * @return array{status:string,steps:list<array<string,mixed>>,final:array<string,mixed>}
     */
    public static function transitionTrace(SQLiteAttachedSchemaCatalog $catalog, array $statements, array $probes, ?callable $recordLoader = null): array
    {
        $steps = [];
        foreach ($statements as $index => $statement) {
            $sql = trim((string) ($statement['sql'] ?? ''));
            if ($sql === '') {
                throw new InvalidArgumentException('SQLite ATTACH/TEMP schema transition SQL cannot be empty');
            }

            $before = self::snapshot($catalog, $probes);
            $apply = $catalog->executeAttachDetachSql($sql, $recordLoader);
            $after = self::snapshot($catalog, $probes);

            $steps[] = [
                'label' => (string) ($statement['label'] ?? 'step-' . ($index + 1)),
                'sql' => $sql,
                'operation' => $apply['operation'],
                'schema' => $apply['schema'],
                'file' => $apply['file'],
                'databaseList' => $apply['database_list'],
                'before' => $before,
                'after' => $after,
            ];
        }

        return [
            'status' => 'resolved',
            'steps' => $steps,
            'final' => self::snapshot($catalog, $probes),
        ];
    }

    /**
     * @param list<array{kind:string,name:string,new?:array<string,mixed>,old?:array<string,mixed>|null}> $probes
     * @return array{searchOrder:list<string>,databaseList:list<array{seq:int,name:string,file:string|null}>,probes:array<string,array<string,mixed>>}
     */
    public static function snapshot(SQLiteAttachedSchemaCatalog $catalog, array $probes): array
    {
        $resolved = [];
        foreach ($probes as $probe) {
            $kind = strtolower((string) ($probe['kind'] ?? ''));
            $name = (string) ($probe['name'] ?? '');
            if ($kind === '' || $name === '') {
                throw new InvalidArgumentException('SQLite schema resolution probes require kind and name');
            }
            if (!in_array($kind, ['table', 'index', 'trigger', 'schema-table', 'pragma-table-info', 'pragma-index-list', 'yield'], true)) {
                throw new InvalidArgumentException("Unsupported SQLite schema resolution probe kind: {$kind}");
            }

            $key = $kind . ':' . $name;
            $resolved[$key] = self::probe($catalog, $kind, $name, $probe);
        }

        return [
            'searchOrder' => $catalog->searchOrder(),
            'databaseList' => $catalog->databaseList(),
            'probes' => $resolved,
        ];
    }

    /**
     * @param array{kind:string,name:string,new?:array<string,mixed>,old?:array<string,mixed>|null} $probe
     * @return array<string,mixed>
     */
    private static function probe(SQLiteAttachedSchemaCatalog $catalog, string $kind, string $name, array $probe): array
    {
        try {
            return match ($kind) {
                'table' => self::recordProbe($catalog->resolveTable($name), $name),
                'index' => self::recordProbe($catalog->resolveIndex($name), $name),
                'trigger' => self::triggerProbe($catalog, $name),
                'schema-table' => self::recordProbe($catalog->resolveTable($name), $name),
                'pragma-table-info' => self::pragmaProbe($catalog, 'PRAGMA table_info(' . self::quoteString($name) . ')'),
                'pragma-index-list' => self::pragmaProbe($catalog, 'PRAGMA index_list(' . self::quoteString($name) . ')'),
                'yield' => self::yieldProbe($catalog, $name, $probe['new'] ?? [], $probe['old'] ?? null),
                default => throw new InvalidArgumentException("Unsupported SQLite schema resolution probe kind: {$kind}"),
            };
        } catch (Throwable $throwable) {
            return [
                'status' => 'missing',
                'name' => $name,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * @param array{schema:string,record:SQLiteSchemaRecord}|null $resolved
     * @return array<string,mixed>
     */
    private static function recordProbe(?array $resolved, string $name): array
    {
        if ($resolved === null) {
            return [
                'status' => 'missing',
                'name' => $name,
            ];
        }

        return [
            'status' => 'resolved',
            'schema' => $resolved['schema'],
            'name' => $resolved['record']->name,
            'type' => $resolved['record']->type,
            'table' => $resolved['record']->tableName,
            'rootPage' => $resolved['record']->rootPage,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function triggerProbe(SQLiteAttachedSchemaCatalog $catalog, string $name): array
    {
        $trigger = SQLiteAttachTempViewTriggerResolution::resolve($catalog, $name);

        return [
            'status' => $trigger['status'],
            'schema' => $trigger['triggerSchema'],
            'name' => $trigger['trigger'],
            'targetSchema' => $trigger['targetSchema'],
            'target' => $trigger['target'],
            'temporary' => $trigger['triggerTemporary'],
            'targetTemporary' => $trigger['targetTemporary'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function pragmaProbe(SQLiteAttachedSchemaCatalog $catalog, string $sql): array
    {
        $result = $catalog->executeSchemaPragma($sql);

        return [
            'status' => $result['status'],
            'schema' => $result['schema'],
            'target' => $result['target'],
            'rowCount' => count($result['rows']),
        ];
    }

    /**
     * @param array<string,mixed> $newRow
     * @param array<string,mixed>|null $oldRow
     * @return array<string,mixed>
     */
    private static function yieldProbe(SQLiteAttachedSchemaCatalog $catalog, string $name, array $newRow, ?array $oldRow): array
    {
        $result = SQLiteAttachTempViewTriggerYieldPlan::yield($catalog, $name, $newRow, $oldRow);

        return [
            'status' => $result['status'],
            'schema' => $result['triggerSchema'],
            'targetSchema' => $result['targetSchema'],
            'operationCount' => $result['operationCount'],
            'writesBySchema' => $result['writesBySchema'],
            'firstOperationSchema' => $result['operations'][0]['schema'] ?? null,
            'firstOperationTable' => $result['operations'][0]['table'] ?? null,
        ];
    }

    private static function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
