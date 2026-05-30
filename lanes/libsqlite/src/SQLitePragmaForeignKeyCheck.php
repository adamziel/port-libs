<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaForeignKeyCheck
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $foreignKeys
     * @return list<array{table:string,rowid:int|string|null,parent:string,fkid:int}>
     */
    public static function check(array $tables, array $foreignKeys, ?string $table = null): array
    {
        $target = $table === null ? null : self::identifier($table, 'target table');
        $rows = [];

        foreach ($foreignKeys as $ordinal => $foreignKey) {
            $spec = self::normalizeForeignKey($foreignKey, $ordinal);
            if ($target !== null && strcasecmp($spec['table'], $target) !== 0) {
                continue;
            }

            $childRows = $tables[$spec['table']] ?? [];
            $parentRows = $tables[$spec['parent']] ?? [];
            foreach ($childRows as $child) {
                if (self::hasNullChildKey($child, $spec['columns'])) {
                    continue;
                }

                if (self::hasParentMatch($child, $parentRows, $spec['columns'])) {
                    continue;
                }

                $rows[] = [
                    'table' => $spec['table'],
                    'rowid' => self::rowid($child, $spec['withoutRowid']),
                    'parent' => $spec['parent'],
                    'fkid' => $spec['id'],
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $foreignKeys
     * @return array{status:string,pragma:string,schema:string,target:string|null,rows:list<array{table:string,rowid:int|string|null,parent:string,fkid:int}>}
     */
    public static function execute(string $sql, array $tables, array $foreignKeys): array
    {
        $parsed = self::parsePragma($sql);

        return [
            'status' => 'ok',
            'pragma' => 'foreign_key_check',
            'schema' => $parsed['schema'] ?? 'main',
            'target' => $parsed['target'],
            'rows' => self::check($tables, $foreignKeys, $parsed['target']),
        ];
    }

    /**
     * @param array<string,mixed> $foreignKey
     * @return array{table:string,parent:string,columns:list<array{child:string,parent:string,affinity:string,collation:string}>,id:int,withoutRowid:bool}
     */
    private static function normalizeForeignKey(array $foreignKey, int $ordinal): array
    {
        $table = self::identifier($foreignKey['table'] ?? null, 'child table');
        $parent = self::identifier($foreignKey['parent'] ?? null, 'parent table');
        $columns = $foreignKey['columns'] ?? null;
        if (!is_array($columns) || $columns === []) {
            throw new InvalidArgumentException('SQLite foreign_key_check requires one or more child-to-parent columns');
        }

        $pairs = [];
        foreach ($columns as $child => $parentColumn) {
            if (is_int($child) && is_array($parentColumn)) {
                $pairs[] = [
                    'child' => self::identifier($parentColumn['child'] ?? null, 'child column'),
                    'parent' => self::identifier($parentColumn['parent'] ?? null, 'parent column'),
                    'affinity' => self::affinity($parentColumn['affinity'] ?? 'none'),
                    'collation' => self::collation($parentColumn['collation'] ?? 'binary'),
                ];
                continue;
            }

            $pairs[] = [
                'child' => self::identifier($child, 'child column'),
                'parent' => self::identifier($parentColumn, 'parent column'),
                'affinity' => 'none',
                'collation' => 'binary',
            ];
        }

        return [
            'table' => $table,
            'parent' => $parent,
            'columns' => $pairs,
            'id' => isset($foreignKey['id']) ? self::nonNegativeInt($foreignKey['id'], 'foreign key id') : $ordinal,
            'withoutRowid' => (bool) ($foreignKey['without_rowid'] ?? false),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array{child:string,parent:string,affinity:string,collation:string}> $columns
     */
    private static function hasNullChildKey(array $row, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column['child'], $row)) {
                throw new InvalidArgumentException("SQLite foreign_key_check child row is missing column {$column['child']}");
            }

            if ($row[$column['child']] === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $child
     * @param list<array<string,mixed>> $parentRows
     * @param list<array{child:string,parent:string,affinity:string,collation:string}> $columns
     */
    private static function hasParentMatch(array $child, array $parentRows, array $columns): bool
    {
        foreach ($parentRows as $parent) {
            foreach ($columns as $column) {
                if (!array_key_exists($column['parent'], $parent)) {
                    throw new InvalidArgumentException("SQLite foreign_key_check parent row is missing column {$column['parent']}");
                }

                if (!self::valuesMatch($child[$column['child']], $parent[$column['parent']], $column['affinity'], $column['collation'])) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    private static function valuesMatch(mixed $child, mixed $parent, string $affinity, string $collation): bool
    {
        $affinity = strtoupper($affinity);

        try {
            return SQLiteAffinityComparison::equals(
                SQLiteAffinityComparison::applyAffinity($child, $affinity),
                SQLiteAffinityComparison::applyAffinity($parent, $affinity),
                'NONE',
                'NONE',
                strtoupper($collation),
            );
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowid(array $row, bool $withoutRowid): int|string|null
    {
        if ($withoutRowid) {
            return null;
        }

        foreach (['rowid', '_rowid_', 'oid'] as $column) {
            if (array_key_exists($column, $row)) {
                $value = $row[$column];
                if (is_int($value) || is_string($value)) {
                    return $value;
                }
                throw new InvalidArgumentException('SQLite foreign_key_check rowid must be an integer or text rowid alias');
            }
        }

        return null;
    }

    /**
     * @return array{schema:string|null,target:string|null}
     */
    private static function parsePragma(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        if (!preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?foreign_key_check\s*(?:\(\s*(?<target>(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*))\s*\))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only PRAGMA foreign_key_check[(table)] is supported');
        }

        return [
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? strtolower($matches['schema']) : null,
            'target' => isset($matches['target']) && $matches['target'] !== '' ? self::unquoteIdentifier($matches['target']) : null,
        ];
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite foreign_key_check {$label} is malformed");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite foreign_key_check {$label} must be a non-negative integer");
        }

        return $value;
    }

    private static function affinity(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('SQLite foreign_key_check parent affinity is malformed');
        }

        $affinity = strtolower($value);
        if (!in_array($affinity, ['none', 'integer', 'numeric', 'real', 'text', 'blob'], true)) {
            throw new InvalidArgumentException("SQLite foreign_key_check parent affinity {$value} is unsupported");
        }

        return $affinity === 'blob' ? 'none' : $affinity;
    }

    private static function collation(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('SQLite foreign_key_check parent collation is malformed');
        }

        $collation = strtolower($value);
        if (!in_array($collation, ['binary', 'nocase', 'rtrim'], true)) {
            throw new InvalidArgumentException("SQLite foreign_key_check parent collation {$value} is unsupported");
        }

        return $collation;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        $first = $identifier[0] ?? '';
        $last = substr($identifier, -1);
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($identifier, 1, -1));
        }
        if ($first === "'" && $last === "'") {
            return str_replace("''", "'", substr($identifier, 1, -1));
        }
        if (($first === '`' && $last === '`') || ($first === '[' && $last === ']')) {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }
}
