<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeysEnforcement
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $foreignKeys
     * @param list<array<string,mixed>> $rows
     * @param array{foreign_keys?:bool|string,defer_foreign_keys?:bool|string} $options
     * @return array{status:string,foreign_keys:bool,defer_foreign_keys:bool,table:string,tables:array<string,list<array<string,mixed>>>,inserted_rows:list<array<string,mixed>>,violations:list<array{table:string,rowid:int|string|null,parent:string,fkid:int}>,deferred_violations:list<array{table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    public static function insertRows(
        array $tables,
        array $foreignKeys,
        string $table,
        array $rows,
        array $options = [],
    ): array {
        $table = self::identifier($table, 'target table');
        if (!array_key_exists($table, $tables) || !array_is_list($tables[$table])) {
            throw new InvalidArgumentException("SQLite foreign_keys enforcement target table {$table} is missing");
        }
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite foreign_keys enforcement inserted rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite foreign_keys enforcement inserted row must be an array');
            }
        }

        $enabled = self::optionBool($options['foreign_keys'] ?? true, 'foreign_keys');
        $deferred = self::optionBool($options['defer_foreign_keys'] ?? false, 'defer_foreign_keys');
        $nextTables = $tables;
        $nextTables[$table] = array_values(array_merge($tables[$table], $rows));
        $violations = SQLitePragmaForeignKeyCheck::check($nextTables, $foreignKeys, $table);

        if (!$enabled) {
            return self::result('foreign_keys_disabled', false, $deferred, $table, $nextTables, $rows, $violations, []);
        }

        if ($violations !== [] && !$deferred) {
            throw new InvalidArgumentException(self::violationMessage($violations));
        }

        return self::result(
            $violations === [] ? 'ok' : 'deferred',
            true,
            $deferred,
            $table,
            $nextTables,
            $rows,
            $violations,
            $deferred ? $violations : [],
        );
    }

    /**
     * @param array{deferred_violations?:list<array{table:string,rowid:int|string|null,parent:string,fkid:int}>} $result
     * @return array<string,mixed>
     */
    public static function commit(array $result): array
    {
        $violations = $result['deferred_violations'] ?? [];
        if (!is_array($violations)) {
            throw new InvalidArgumentException('SQLite foreign_keys deferred violations must be a list');
        }
        if ($violations !== []) {
            throw new InvalidArgumentException(self::violationMessage($violations));
        }

        $result['status'] = 'committed';

        return $result;
    }

    public static function parseForeignKeysPragma(string $sql): bool
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (preg_match('/^PRAGMA\s+(?:[A-Za-z_][A-Za-z0-9_]*\.)?foreign_keys\s*=\s*(ON|OFF|TRUE|FALSE|1|0)$/i', $sql, $match) !== 1) {
            throw new InvalidArgumentException('SQLite PRAGMA foreign_keys enforcement requires assignment to ON/OFF or 1/0');
        }

        return match (strtolower($match[1])) {
            'on', 'true', '1' => true,
            'off', 'false', '0' => false,
        };
    }

    public static function parseDeferForeignKeysPragma(string $sql): bool
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (preg_match('/^PRAGMA\s+(?:[A-Za-z_][A-Za-z0-9_]*\.)?defer_foreign_keys\s*=\s*(ON|OFF|TRUE|FALSE|1|0)$/i', $sql, $match) !== 1) {
            throw new InvalidArgumentException('SQLite PRAGMA defer_foreign_keys requires assignment to ON/OFF or 1/0');
        }

        return match (strtolower($match[1])) {
            'on', 'true', '1' => true,
            'off', 'false', '0' => false,
        };
    }

    /**
     * @param list<array{table:string,rowid:int|string|null,parent:string,fkid:int}> $violations
     */
    private static function violationMessage(array $violations): string
    {
        $first = $violations[0] ?? null;
        if (!is_array($first)) {
            return 'SQLite foreign key constraint failed';
        }

        return 'SQLite foreign key constraint failed: '
            . (string) ($first['table'] ?? 'unknown')
            . ' rowid '
            . (string) ($first['rowid'] ?? 'null')
            . ' references '
            . (string) ($first['parent'] ?? 'unknown');
    }

    private static function optionBool(mixed $value, string $label): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^PRAGMA\s+/i', $value) === 1) {
                return $label === 'foreign_keys'
                    ? self::parseForeignKeysPragma($value)
                    : self::parseDeferForeignKeysPragma($value);
            }
            return match (strtolower($value)) {
                'on', 'true', '1' => true,
                'off', 'false', '0' => false,
                default => throw new InvalidArgumentException("SQLite {$label} option must be boolean-like"),
            };
        }
        if (is_int($value)) {
            if ($value === 0) {
                return false;
            }
            if ($value === 1) {
                return true;
            }
        }

        throw new InvalidArgumentException("SQLite {$label} option must be boolean-like");
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite foreign_keys enforcement {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $insertedRows
     * @param list<array{table:string,rowid:int|string|null,parent:string,fkid:int}> $violations
     * @param list<array{table:string,rowid:int|string|null,parent:string,fkid:int}> $deferredViolations
     * @return array{status:string,foreign_keys:bool,defer_foreign_keys:bool,table:string,tables:array<string,list<array<string,mixed>>>,inserted_rows:list<array<string,mixed>>,violations:list<array{table:string,rowid:int|string|null,parent:string,fkid:int}>,deferred_violations:list<array{table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    private static function result(
        string $status,
        bool $enabled,
        bool $deferred,
        string $table,
        array $tables,
        array $insertedRows,
        array $violations,
        array $deferredViolations,
    ): array {
        return [
            'status' => $status,
            'foreign_keys' => $enabled,
            'defer_foreign_keys' => $deferred,
            'table' => $table,
            'tables' => $tables,
            'inserted_rows' => $insertedRows,
            'violations' => $violations,
            'deferred_violations' => $deferredViolations,
        ];
    }
}
