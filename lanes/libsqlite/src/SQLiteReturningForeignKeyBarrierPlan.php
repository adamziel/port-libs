<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteReturningForeignKeyBarrierPlan
{
    private const SOURCE = 'returning1.test';
    private const SCENARIO = 'returning1-14.1 immediate foreign-key failure is reported before RETURNING rows';

    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param array<string,mixed> $incoming
     * @param array{parent_key:string,child_key:string,id_key?:string} $foreignKey
     * @param list<string> $returning
     * @return array{source:string,scenario:string,ok:bool,error:?string,parent:list<array<string,mixed>>,before:list<array<string,mixed>>,attempted:array<string,mixed>,after:list<array<string,mixed>>,returning_projection:list<string>,returning_evaluated:bool,returning_rows:list<array<string,mixed>>,changes:int,violations:list<array{child_key:string,child_value:mixed,parent_key:string}>,dependencies:list<string>}
     */
    public static function insertChildReturning(
        array $parentRows,
        array $childRows,
        array $incoming,
        array $foreignKey,
        array $returning = ['id']
    ): array {
        self::assertRows($parentRows, 'parent');
        self::assertRows($childRows, 'child');

        $parentKey = self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key');
        $childKey = self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key');
        $idKey = self::identifier((string) ($foreignKey['id_key'] ?? 'id'), 'child id key');
        $returning = self::returningProjection($returning);

        $attempted = self::completeIncomingRow($childRows, $incoming, $idKey);
        if (!array_key_exists($childKey, $attempted)) {
            throw new InvalidArgumentException("SQLite RETURNING foreign-key child row missing {$childKey}");
        }

        $violation = null;
        if ($attempted[$childKey] !== null && !self::parentExists($parentRows, $parentKey, $attempted[$childKey])) {
            $violation = [
                'child_key' => $childKey,
                'child_value' => $attempted[$childKey],
                'parent_key' => $parentKey,
            ];
        }

        if ($violation !== null) {
            return [
                'source' => self::SOURCE,
                'scenario' => self::SCENARIO,
                'ok' => false,
                'error' => 'FOREIGN KEY constraint failed',
                'parent' => array_values($parentRows),
                'before' => array_values($childRows),
                'attempted' => $attempted,
                'after' => array_values($childRows),
                'returning_projection' => $returning,
                'returning_evaluated' => false,
                'returning_rows' => [],
                'changes' => 0,
                'violations' => [$violation],
                'dependencies' => self::dependencies(),
            ];
        }

        $after = array_values($childRows);
        $after[] = $attempted;

        return [
            'source' => self::SOURCE,
            'scenario' => self::SCENARIO,
            'ok' => true,
            'error' => null,
            'parent' => array_values($parentRows),
            'before' => array_values($childRows),
            'attempted' => $attempted,
            'after' => $after,
            'returning_projection' => $returning,
            'returning_evaluated' => true,
            'returning_rows' => [self::project($attempted, $returning)],
            'changes' => 1,
            'violations' => [],
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function assertRows(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException("SQLite RETURNING {$label} rows must be a list");
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("SQLite RETURNING {$label} row {$index} must be an array");
            }
            foreach ($row as $column => $_value) {
                if (!is_string($column) || $column === '') {
                    throw new InvalidArgumentException("SQLite RETURNING {$label} row {$index} has invalid column name");
                }
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $childRows
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    private static function completeIncomingRow(array $childRows, array $incoming, string $idKey): array
    {
        foreach ($incoming as $column => $_value) {
            if (!is_string($column) || $column === '') {
                throw new InvalidArgumentException('SQLite RETURNING incoming child row has invalid column name');
            }
        }

        if (array_key_exists($idKey, $incoming)) {
            return $incoming;
        }

        $maxId = 0;
        foreach ($childRows as $row) {
            if (array_key_exists($idKey, $row) && is_int($row[$idKey])) {
                $maxId = max($maxId, $row[$idKey]);
            }
        }

        return [$idKey => $maxId + 1] + $incoming;
    }

    /**
     * @param list<array<string,mixed>> $parentRows
     */
    private static function parentExists(array $parentRows, string $parentKey, mixed $value): bool
    {
        foreach ($parentRows as $parent) {
            if (array_key_exists($parentKey, $parent) && $parent[$parentKey] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $returning
     * @return list<string>
     */
    private static function returningProjection(array $returning): array
    {
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite RETURNING foreign-key projection cannot be empty');
        }

        return array_map(
            static fn (string $column): string => self::identifier($column, 'RETURNING column'),
            $returning,
        );
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $returning
     * @return array<string,mixed>
     */
    private static function project(array $row, array $returning): array
    {
        $projected = [];
        foreach ($returning as $column) {
            if (!array_key_exists($column, $row)) {
                throw new InvalidArgumentException("SQLite RETURNING foreign-key row missing {$column}");
            }
            $projected[$column] = $row[$column];
        }

        return $projected;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite RETURNING invalid {$label}");
        }

        return $value;
    }

    /** @return list<string> */
    private static function dependencies(): array
    {
        return [
            'sqlite-returning-immediate-foreign-key-barrier',
            'returning1.test-14.0',
            'returning1.test-14.1',
        ];
    }
}
