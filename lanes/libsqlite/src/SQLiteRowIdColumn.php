<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowIdColumn
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     */
    public static function resolveTables(array $tables, string $requested, array $uniqueConstraints = []): string
    {
        foreach ($tables as $rows) {
            if ($rows !== []) {
                return self::resolveRows($rows, $requested, $uniqueConstraints);
            }
        }

        return self::normalizeRequested($requested);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<list<string>> $uniqueConstraints
     */
    public static function resolveRows(array $rows, string $requested, array $uniqueConstraints = []): string
    {
        $requested = self::normalizeRequested($requested);
        $arrayRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $arrayRows[] = $row;
            if (array_key_exists($requested, $row)) {
                return $requested;
            }
        }

        $preferred = self::preferredIdentifierColumn($arrayRows);
        if ($preferred !== null) {
            return $preferred;
        }

        $singleColumnUnique = self::singleColumnUniqueIdentifierColumn($arrayRows, $uniqueConstraints);
        if ($singleColumnUnique !== null) {
            return $singleColumnUnique;
        }

        $inferred = self::uniqueIdentifierColumn($arrayRows, $uniqueConstraints);
        if ($inferred !== null) {
            return $inferred;
        }

        return $requested;
    }

    private static function normalizeRequested(string $requested): string
    {
        $requested = trim($requested);
        if ($requested === '') {
            throw new \InvalidArgumentException('SQLite row id column must be a non-empty identifier');
        }

        return $requested;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function preferredIdentifierColumn(array $rows): ?string
    {
        foreach (['id', 'rowid'] as $preferred) {
            if (self::allRowsHaveScalarColumn($rows, $preferred)) {
                return $preferred;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<list<string>> $uniqueConstraints
     */
    private static function singleColumnUniqueIdentifierColumn(array $rows, array $uniqueConstraints): ?string
    {
        foreach ($uniqueConstraints as $constraint) {
            if (count($constraint) !== 1) {
                continue;
            }
            $column = $constraint[0];
            if (!is_string($column) || !str_ends_with($column, '_id')) {
                continue;
            }
            if (self::allRowsHaveScalarColumn($rows, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<list<string>> $uniqueConstraints
     */
    private static function uniqueIdentifierColumn(array $rows, array $uniqueConstraints): ?string
    {
        if (count($rows) < 2) {
            return null;
        }

        $firstRow = $rows[0] ?? [];
        $candidates = [];
        foreach ($firstRow as $column => $_value) {
            if (is_string($column) && str_ends_with($column, '_id')) {
                $candidates[] = $column;
            }
        }

        foreach ([false, true] as $allowCompositeUniqueColumns) {
            foreach ($candidates as $column) {
                if (!$allowCompositeUniqueColumns && self::appearsInCompositeUniqueConstraint($column, $uniqueConstraints)) {
                    continue;
                }
                if (self::allRowsHaveUniqueScalarColumn($rows, $column)) {
                    return $column;
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allRowsHaveScalarColumn(array $rows, string $column): bool
    {
        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (!array_key_exists($column, $row) || (!is_int($row[$column]) && !is_string($row[$column]))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allRowsHaveUniqueScalarColumn(array $rows, string $column): bool
    {
        $seen = [];
        foreach ($rows as $row) {
            if (!array_key_exists($column, $row) || (!is_int($row[$column]) && !is_string($row[$column]))) {
                return false;
            }
            $key = gettype($row[$column]) . ':' . (string) $row[$column];
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
        }

        return true;
    }

    /**
     * @param list<list<string>> $uniqueConstraints
     */
    private static function appearsInCompositeUniqueConstraint(string $column, array $uniqueConstraints): bool
    {
        foreach ($uniqueConstraints as $constraint) {
            if (count($constraint) > 1 && in_array($column, $constraint, true)) {
                return true;
            }
        }

        return false;
    }
}
