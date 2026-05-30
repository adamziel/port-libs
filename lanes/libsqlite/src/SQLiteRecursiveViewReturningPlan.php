<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecursiveViewReturningPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $viewRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,array<string,mixed>,array<string,mixed>,string):mixed>|null $returning
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string,view_name?:string,savepoint?:string,view_trigger?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $parentRows,
        array $childRows,
        array $viewRows,
        array $uniqueColumns,
        array $assignments,
        array $foreignKey,
        array $triggers,
        ?array $returning = null,
        array $options = [],
    ): array {
        $viewName = self::identifier((string) ($options['view_name'] ?? 'active_settings'), 'view name');
        $savepoint = trim((string) ($options['savepoint'] ?? 'view-returning'));
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite recursive view RETURNING savepoint cannot be empty');
        }
        $viewTrigger = (string) ($options['view_trigger'] ?? $viewName . '_instead_of_upsert');
        if ($viewTrigger === '') {
            throw new \InvalidArgumentException('SQLite recursive view RETURNING trigger cannot be empty');
        }

        $incoming = [];
        $viewImages = [];
        foreach ($viewRows as $ordinal => $viewRow) {
            $baseRow = self::viewToBaseRow($viewRow, $ordinal);
            $incoming[] = $baseRow;
            $viewImages[] = self::viewImage($viewRow, $baseRow, $viewName);
        }

        $plan = SQLiteRecursiveSavepointUpsertPlan::execute(
            $savepoint,
            $parentRows,
            $childRows,
            $incoming,
            $uniqueColumns,
            $assignments,
            $foreignKey,
            $triggers,
            $options,
        );

        $returningRows = [];
        $yieldedByOrdinal = [];
        foreach ($plan['yielded'] as $yielded) {
            if (($yielded['depth'] ?? 0) !== 0 || ($yielded['status'] ?? '') !== 'changed') {
                continue;
            }
            $yieldedByOrdinal[(int) $yielded['ordinal']] = $yielded;
        }
        foreach ($viewImages as $ordinal => $viewImage) {
            if (!isset($yieldedByOrdinal[$ordinal])) {
                continue;
            }
            $baseRow = $incoming[$ordinal];
            $current = self::findCurrentRow($plan['current_parent'], $baseRow, $uniqueColumns);
            $returningRows[] = self::returningRow($returning, $viewImage, $baseRow, $current, $yieldedByOrdinal[$ordinal], $viewName);
        }

        return $plan + [
            'view' => $viewName,
            'view_trigger' => $viewTrigger,
            'incoming_view_rows' => $viewImages,
            'top_level_yielded' => array_values($yieldedByOrdinal),
            'returning_rows' => $returningRows,
            'returning_count' => count($returningRows),
            'view_dependencies' => [
                'sqlite-instead-of-view-trigger',
                'sqlite-recursive-trigger-current-savepoint',
                'sqlite-returning-current-row',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function viewToBaseRow(array $viewRow, int $ordinal): array
    {
        foreach (['setting_id', 'key_name', 'key_value'] as $column) {
            if (!array_key_exists($column, $viewRow)) {
                throw new \InvalidArgumentException("SQLite recursive view RETURNING row {$ordinal} missing {$column}");
            }
        }

        return [
            'setting_id' => $viewRow['setting_id'],
            'key_name' => $viewRow['key_name'],
            'key_value' => $viewRow['key_value'],
            'level' => (int) ($viewRow['level'] ?? 1),
            'load_policy' => $viewRow['load_policy'] ?? 'yes',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function viewImage(array $viewRow, array $baseRow, string $viewName): array
    {
        return [
            'view' => $viewName,
            'setting_id' => $baseRow['setting_id'],
            'key_name' => $baseRow['key_name'],
            'key_value' => $baseRow['key_value'],
            'load_policy' => $baseRow['load_policy'],
            'level' => $baseRow['level'],
            'source' => $viewRow['source'] ?? 'view',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     * @return array<string,mixed>
     */
    private static function findCurrentRow(array $rows, array $baseRow, array $uniqueColumns): array
    {
        foreach ($rows as $row) {
            foreach ($uniqueColumns as $column) {
                self::identifier($column, 'unique column');
                if (!array_key_exists($column, $row) || !array_key_exists($column, $baseRow) || $row[$column] != $baseRow[$column]) {
                    continue 2;
                }
            }

            return $row;
        }

        return $baseRow;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,array<string,mixed>,array<string,mixed>,string):mixed>|null $projection
     * @return array<string,mixed>
     */
    private static function returningRow(?array $projection, array $viewRow, array $incoming, array $current, array $yielded, string $viewName): array
    {
        if ($projection === null) {
            return $viewRow;
        }

        $row = [];
        foreach ($projection as $index => $entry) {
            if (is_string($entry)) {
                if ($entry === '*') {
                    $row['*'] = $viewRow;
                    continue;
                }
                $alias = str_contains($entry, '.') ? substr($entry, (int) strrpos($entry, '.') + 1) : $entry;
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($entry, $viewRow, $incoming, $current, $yielded, $viewName);
                continue;
            }
            if (is_array($entry)) {
                $expr = (string) ($entry['expr'] ?? '');
                $alias = (string) ($entry['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($expr, $viewRow, $incoming, $current, $yielded, $viewName);
                continue;
            }
            if (is_callable($entry)) {
                $row['expr' . $index] = $entry($viewRow, $incoming, $current, $yielded, $viewName);
                continue;
            }

            throw new \InvalidArgumentException('SQLite recursive view RETURNING projection is malformed');
        }

        return $row;
    }

    private static function returningValue(string $expr, array $viewRow, array $incoming, array $current, array $yielded, string $viewName): mixed
    {
        $expr = trim($expr);
        if ($expr === 'view') {
            return $viewName;
        }
        if (str_starts_with($expr, 'view.')) {
            return self::rowValue($viewRow, substr($expr, 5), 'view RETURNING row');
        }
        if (str_starts_with($expr, 'excluded.')) {
            return self::rowValue($incoming, substr($expr, 9), 'view RETURNING incoming row');
        }
        if (str_starts_with($expr, 'current.')) {
            return self::rowValue($current, substr($expr, 8), 'view RETURNING current row');
        }
        if (str_starts_with($expr, 'yield.')) {
            return self::rowValue($yielded, substr($expr, 6), 'view RETURNING yield row');
        }

        return self::rowValue($viewRow, $expr, 'view RETURNING row');
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite recursive view RETURNING {$label} missing column {$column}");
        }

        return $row[$column];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite recursive view RETURNING {$label} is malformed");
        }

        return $value;
    }
}
