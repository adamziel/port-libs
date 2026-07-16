<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeySetDefaultRecursivePlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $foreignKeys
     * @param list<array{table:string,key:mixed}> $deleteQueue
     * @return array{tables:array<string,list<array<string,mixed>>>,actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int,max_depth:int}
     */
    public static function apply(array $tables, array $foreignKeys, array $deleteQueue, int $depthLimit = 1000): array
    {
        $rows = self::normalizeTables($tables);
        $specs = array_map(self::normalizeForeignKey(...), $foreignKeys);
        self::assertReferencedTables($rows, $specs);
        $queue = array_values($deleteQueue);
        $actions = [];
        $changes = 0;
        $maxDepth = 0;

        while ($queue !== []) {
            if (count($actions) > $depthLimit) {
                throw new \InvalidArgumentException('SQLite recursive SET DEFAULT foreign key action depth limit exceeded');
            }

            $delete = array_shift($queue);
            $table = self::identifier($delete['table'] ?? null, 'delete table');
            $key = $delete['key'] ?? null;
            if (!array_key_exists($table, $rows)) {
                throw new \InvalidArgumentException("SQLite recursive SET DEFAULT table {$table} is missing");
            }

            $deleted = self::deleteRow($rows[$table], $key);
            if ($deleted === null) {
                continue;
            }

            $changes++;
            $maxDepth = max($maxDepth, (int) ($delete['depth'] ?? 0));
            $actions[] = [
                'action' => 'delete-parent',
                'table' => $table,
                'key' => $key,
                'depth' => (int) ($delete['depth'] ?? 0),
                'row' => $deleted,
            ];

            foreach ($specs as $spec) {
                if ($spec['parent_table'] !== $table) {
                    continue;
                }
                foreach ($rows[$spec['child_table']] as $index => $child) {
                    $childKey = self::rowValue($child, $spec['child_key'], "{$spec['child_table']} child row");
                    if ($childKey === null || $childKey !== $key) {
                        continue;
                    }

                    if ($spec['on_delete'] === 'cascade') {
                        $queue[] = [
                            'table' => $spec['child_table'],
                            'key' => self::rowValue($child, $spec['child_row_key'], "{$spec['child_table']} child row"),
                            'depth' => ((int) ($delete['depth'] ?? 0)) + 1,
                        ];
                        $actions[] = [
                            'action' => 'queue-cascade-delete',
                            'table' => $spec['child_table'],
                            'key' => self::rowValue($child, $spec['child_row_key'], "{$spec['child_table']} child row"),
                            'from_table' => $table,
                            'from_key' => $key,
                            'depth' => ((int) ($delete['depth'] ?? 0)) + 1,
                        ];
                        continue;
                    }

                    if ($spec['on_delete'] !== 'set default') {
                        continue;
                    }

                    $rows[$spec['child_table']][$index][$spec['child_key']] = $spec['default'];
                    $changes++;
                    $actions[] = [
                        'action' => 'set-default-child',
                        'table' => $spec['child_table'],
                        'row_key' => self::rowValue($child, $spec['child_row_key'], "{$spec['child_table']} child row"),
                        'child_key' => $childKey,
                        'default' => $spec['default'],
                        'from_table' => $table,
                        'from_key' => $key,
                        'depth' => (int) ($delete['depth'] ?? 0),
                    ];
                }
            }
        }

        return [
            'tables' => self::sortTables($rows),
            'actions' => $actions,
            'violations' => self::violations($rows, $specs),
            'changes' => $changes,
            'max_depth' => $maxDepth,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{parent_table:string,parent_key:string,child_table:string,child_row_key:string,child_key:string,on_delete:string,default:mixed}> $specs
     */
    private static function assertReferencedTables(array $tables, array $specs): void
    {
        foreach ($specs as $spec) {
            if (!array_key_exists($spec['parent_table'], $tables)) {
                throw new \InvalidArgumentException("SQLite recursive SET DEFAULT parent table {$spec['parent_table']} is missing");
            }
            if (!array_key_exists($spec['child_table'], $tables)) {
                throw new \InvalidArgumentException("SQLite recursive SET DEFAULT child table {$spec['child_table']} is missing");
            }
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        $normalized = [];
        foreach ($tables as $name => $rows) {
            $table = self::identifier($name, 'table');
            $normalized[$table] = array_values($rows);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $foreignKey
     * @return array{parent_table:string,parent_key:string,child_table:string,child_row_key:string,child_key:string,on_delete:string,default:mixed}
     */
    private static function normalizeForeignKey(array $foreignKey): array
    {
        $action = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'set default')));
        if (!in_array($action, ['set default', 'cascade'], true)) {
            throw new \InvalidArgumentException('SQLite recursive SET DEFAULT foreign key action is unsupported');
        }

        return [
            'parent_table' => self::identifier($foreignKey['parent_table'] ?? null, 'parent table'),
            'parent_key' => self::identifier($foreignKey['parent_key'] ?? 'id', 'parent key'),
            'child_table' => self::identifier($foreignKey['child_table'] ?? null, 'child table'),
            'child_row_key' => self::identifier($foreignKey['child_row_key'] ?? 'id', 'child row key'),
            'child_key' => self::identifier($foreignKey['child_key'] ?? null, 'child key'),
            'on_delete' => $action,
            'default' => $foreignKey['default'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>|null
     */
    private static function deleteRow(array &$rows, mixed $key): ?array
    {
        foreach ($rows as $index => $row) {
            if (self::rowValue($row, 'id', 'parent row') !== $key) {
                continue;
            }
            unset($rows[$index]);
            $rows = array_values($rows);
            return $row;
        }

        return null;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{parent_table:string,parent_key:string,child_table:string,child_row_key:string,child_key:string,on_delete:string,default:mixed}> $specs
     * @return list<array<string,mixed>>
     */
    private static function violations(array $tables, array $specs): array
    {
        $violations = [];
        foreach ($specs as $spec) {
            $parents = self::keySet($tables[$spec['parent_table']], $spec['parent_key']);
            foreach ($tables[$spec['child_table']] as $child) {
                $childKey = self::rowValue($child, $spec['child_key'], "{$spec['child_table']} child row");
                if ($childKey === null || array_key_exists((string) $childKey, $parents)) {
                    continue;
                }
                $violations[] = [
                    'table' => $spec['child_table'],
                    'row_key' => self::rowValue($child, $spec['child_row_key'], "{$spec['child_table']} child row"),
                    'child_key' => $childKey,
                    'parent_table' => $spec['parent_table'],
                    'reason' => 'missing-default-parent',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,bool>
     */
    private static function keySet(array $rows, string $column): array
    {
        $set = [];
        foreach ($rows as $row) {
            $value = self::rowValue($row, $column, 'parent row');
            if ($value !== null) {
                $set[(string) $value] = true;
            }
        }

        return $set;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function sortTables(array $tables): array
    {
        ksort($tables);
        foreach ($tables as &$rows) {
            $rows = array_values($rows);
        }
        unset($rows);

        return $tables;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite recursive SET DEFAULT {$label} is missing column {$column}");
        }

        return $row[$column];
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite recursive SET DEFAULT {$label} is malformed");
        }

        return $value;
    }
}
