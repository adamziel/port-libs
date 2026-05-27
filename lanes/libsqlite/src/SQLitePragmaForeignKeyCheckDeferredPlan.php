<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyCheckDeferredPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $foreignKeys
     * @param list<array<string,mixed>> $operations
     * @return array{status:string,committed:bool,deferred_violations:int,snapshots:array<string,array{rows:list<array{table:string,rowid:int|string|null,parent:string,fkid:int}>,deferred_violations:int,tables:array<string,list<array<string,mixed>>>}>,tables:array<string,list<array<string,mixed>>>}
     */
    public static function plan(array $tables, array $foreignKeys, array $operations): array
    {
        $current = self::normalizeTables($tables);
        $savepoints = [];
        $snapshots = [];
        $committed = false;

        foreach ($operations as $operation) {
            $op = self::operationName($operation);
            if ($op === 'insert') {
                $table = self::identifier($operation['table'] ?? null, 'insert table');
                $row = self::row($operation['row'] ?? null, 'insert row');
                $current[$table] ??= [];
                $current[$table][] = $row;
                continue;
            }

            if ($op === 'update') {
                $table = self::identifier($operation['table'] ?? null, 'update table');
                $where = self::row($operation['where'] ?? null, 'update where');
                $set = self::row($operation['set'] ?? null, 'update set');
                $current[$table] = self::updateRows($current[$table] ?? [], $where, $set);
                continue;
            }

            if ($op === 'delete') {
                $table = self::identifier($operation['table'] ?? null, 'delete table');
                $where = self::row($operation['where'] ?? null, 'delete where');
                $current[$table] = array_values(array_filter(
                    $current[$table] ?? [],
                    static fn (array $row): bool => !self::rowMatches($row, $where)
                ));
                continue;
            }

            if ($op === 'savepoint') {
                $name = self::identifier($operation['name'] ?? null, 'savepoint name');
                $savepoints[$name] = $current;
                continue;
            }

            if ($op === 'rollback_to') {
                $name = self::identifier($operation['name'] ?? null, 'savepoint name');
                if (!array_key_exists($name, $savepoints)) {
                    throw new InvalidArgumentException("SQLite foreign_key_check rollback target {$name} is not active");
                }
                $current = $savepoints[$name];
                continue;
            }

            if ($op === 'release') {
                $name = self::identifier($operation['name'] ?? null, 'savepoint name');
                if (!array_key_exists($name, $savepoints)) {
                    throw new InvalidArgumentException("SQLite foreign_key_check release target {$name} is not active");
                }
                unset($savepoints[$name]);
                continue;
            }

            if ($op === 'check') {
                $label = self::label($operation['label'] ?? null);
                $target = array_key_exists('table', $operation) ? self::identifier($operation['table'], 'target table') : null;
                $rows = SQLitePragmaForeignKeyCheck::check($current, $foreignKeys, $target);
                $snapshots[$label] = [
                    'rows' => $rows,
                    'deferred_violations' => count($rows),
                    'tables' => $current,
                ];
                continue;
            }

            if ($op === 'commit') {
                $rows = SQLitePragmaForeignKeyCheck::check($current, $foreignKeys);
                if ($rows !== []) {
                    throw new InvalidArgumentException('SQLite deferred foreign key constraint failed at COMMIT');
                }
                $committed = true;
                continue;
            }

            throw new InvalidArgumentException("SQLite foreign_key_check deferred operation {$op} is unsupported");
        }

        $rows = SQLitePragmaForeignKeyCheck::check($current, $foreignKeys);

        return [
            'status' => $rows === [] ? 'ok' : 'deferred-violations',
            'committed' => $committed,
            'deferred_violations' => count($rows),
            'snapshots' => $snapshots,
            'tables' => $current,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        $normalized = [];
        foreach ($tables as $table => $rows) {
            $name = self::identifier($table, 'table');
            if (!is_array($rows)) {
                throw new InvalidArgumentException("SQLite foreign_key_check table {$name} rows are malformed");
            }
            $normalized[$name] = [];
            foreach ($rows as $row) {
                $normalized[$name][] = self::row($row, "table {$name} row");
            }
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $where
     * @param array<string,mixed> $set
     * @return list<array<string,mixed>>
     */
    private static function updateRows(array $rows, array $where, array $set): array
    {
        foreach ($rows as $index => $row) {
            if (self::rowMatches($row, $where)) {
                $rows[$index] = array_replace($row, $set);
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $where
     */
    private static function rowMatches(array $row, array $where): bool
    {
        foreach ($where as $column => $value) {
            if (!array_key_exists($column, $row) || $row[$column] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    private static function row(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("SQLite foreign_key_check {$label} is malformed");
        }

        foreach (array_keys($value) as $column) {
            self::identifier($column, 'row column');
        }

        return $value;
    }

    private static function operationName(array $operation): string
    {
        $op = $operation['op'] ?? null;
        if (!is_string($op) || $op === '') {
            throw new InvalidArgumentException('SQLite foreign_key_check deferred operation is malformed');
        }

        return strtolower($op);
    }

    private static function label(mixed $label): string
    {
        if (!is_string($label) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $label) !== 1) {
            throw new InvalidArgumentException('SQLite foreign_key_check snapshot label is malformed');
        }

        return $label;
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite foreign_key_check {$label} is malformed");
        }

        return $value;
    }
}
