<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerDeferredForeignKeyPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $statements
     * @param list<array<string,mixed>> $foreignKeys
     * @return array{tables:array<string,list<array<string,mixed>>>,events:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,deferred:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int,commit_status:string}
     */
    public static function run(array $tables, array $statements, array $foreignKeys): array
    {
        $rows = self::normalizeTables($tables);
        $specs = array_map(self::normalizeForeignKey(...), $foreignKeys);
        self::assertTables($rows, $specs);

        $events = [];
        $foreignKeyActions = [];
        $deferred = [];
        $changes = 0;

        foreach (array_values($statements) as $statementIndex => $statement) {
            $operation = self::operation($statement['operation'] ?? null);
            $table = self::identifier($statement['table'] ?? null, 'statement table');
            if (!array_key_exists($table, $rows)) {
                throw new \InvalidArgumentException("SQLite trigger deferred FK statement table {$table} is missing");
            }

            $triggerName = isset($statement['trigger']) ? self::identifier($statement['trigger'], 'trigger name') : null;
            if ($operation === 'insert') {
                $row = self::row($statement['row'] ?? null, 'insert row');
                $rows[$table][] = $row;
                $changes++;
                $events[] = self::event($statementIndex, $triggerName, 'insert-row', $table, $row);
                self::queueForChangedChild($deferred, $specs, $table, $row, $statementIndex, $triggerName, 'insert');
                continue;
            }

            if ($operation === 'update') {
                $match = self::row($statement['match'] ?? null, 'update match');
                $set = self::row($statement['set'] ?? null, 'update set');
                $matched = false;
                foreach ($rows[$table] as $index => $row) {
                    if (!self::rowMatches($row, $match)) {
                        continue;
                    }
                    $before = $row;
                    foreach ($set as $column => $value) {
                        $row[$column] = $value;
                    }
                    $rows[$table][$index] = $row;
                    $matched = true;
                    $changes++;
                    $events[] = self::event($statementIndex, $triggerName, 'update-row', $table, $row, $before);
                    self::queueForChangedChild($deferred, $specs, $table, $row, $statementIndex, $triggerName, 'update');
                    $fk = self::applyParentUpdate($rows, $deferred, $specs, $table, $before, $row, $statementIndex, $triggerName);
                    $foreignKeyActions = array_merge($foreignKeyActions, $fk['actions']);
                    $changes += $fk['changes'];
                }
                if (!$matched && !empty($statement['require_match'])) {
                    throw new \InvalidArgumentException('SQLite trigger deferred FK update matched no rows');
                }
                continue;
            }

            $match = self::row($statement['match'] ?? null, 'delete match');
            $kept = [];
            foreach ($rows[$table] as $row) {
                if (!self::rowMatches($row, $match)) {
                    $kept[] = $row;
                    continue;
                }
                self::assertRestrictAllowsParentDelete($rows, $specs, $table, $row);
                $changes++;
                $events[] = self::event($statementIndex, $triggerName, 'delete-row', $table, $row);
                self::queueForDeletedParent($deferred, $specs, $table, $row, $statementIndex, $triggerName);
            }
            $rows[$table] = array_values($kept);
        }

        $violations = self::violations($rows, $specs, $deferred);

        return [
            'tables' => self::sortTables($rows),
            'events' => $events,
            'foreign_key_actions' => $foreignKeyActions,
            'deferred' => $deferred,
            'violations' => $violations,
            'changes' => $changes,
            'commit_status' => $violations === [] ? 'commit-ok' : 'commit-blocked',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $statements
     * @param list<array<string,mixed>> $foreignKeys
     * @return array{before:array<string,list<array<string,mixed>>>,after:array<string,list<array<string,mixed>>>,rollback:array{tables:array<string,list<array<string,mixed>>>,deferred:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int,commit_status:string}}
     */
    public static function rollbackPreview(array $tables, array $statements, array $foreignKeys): array
    {
        $before = self::normalizeTables($tables);
        $after = self::run($tables, $statements, $foreignKeys);

        return [
            'before' => self::sortTables($before),
            'after' => $after['tables'],
            'rollback' => [
                'tables' => self::sortTables($before),
                'deferred' => [],
                'violations' => [],
                'changes' => 0,
                'commit_status' => 'rolled-back',
            ],
        ];
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
     * @return array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string,on_update:string,child_default:mixed}
     */
    private static function normalizeForeignKey(array $foreignKey): array
    {
        $onDelete = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($onDelete, ['no action', 'restrict'], true)) {
            throw new \InvalidArgumentException('SQLite trigger deferred FK only supports NO ACTION and RESTRICT checks');
        }
        $onUpdate = strtolower(trim((string) ($foreignKey['on_update'] ?? 'no action')));
        if (!in_array($onUpdate, ['cascade', 'no action', 'restrict', 'set null', 'set default'], true)) {
            throw new \InvalidArgumentException('SQLite trigger deferred FK ON UPDATE action is unsupported');
        }

        return [
            'name' => isset($foreignKey['name']) ? self::identifier($foreignKey['name'], 'foreign key name') : 'fk',
            'parent_table' => self::identifier($foreignKey['parent_table'] ?? null, 'parent table'),
            'parent_key' => self::identifier($foreignKey['parent_key'] ?? 'id', 'parent key'),
            'child_table' => self::identifier($foreignKey['child_table'] ?? null, 'child table'),
            'child_key' => self::identifier($foreignKey['child_key'] ?? null, 'child key'),
            'deferred' => (bool) ($foreignKey['deferred'] ?? true),
            'on_delete' => $onDelete,
            'on_update' => $onUpdate,
            'child_default' => $foreignKey['child_default'] ?? null,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string}> $specs
     */
    private static function assertTables(array $tables, array $specs): void
    {
        foreach ($specs as $spec) {
            if (!array_key_exists($spec['parent_table'], $tables)) {
                throw new \InvalidArgumentException("SQLite trigger deferred FK parent table {$spec['parent_table']} is missing");
            }
            if (!array_key_exists($spec['child_table'], $tables)) {
                throw new \InvalidArgumentException("SQLite trigger deferred FK child table {$spec['child_table']} is missing");
            }
        }
    }

    /**
     * @param list<array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string}> $specs
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $deferred
     */
    private static function queueForChangedChild(array &$deferred, array $specs, string $table, array $row, int $statementIndex, ?string $triggerName, string $operation): void
    {
        foreach ($specs as $spec) {
            if ($spec['child_table'] !== $table) {
                continue;
            }
            $childKey = self::value($row, $spec['child_key'], 'child row');
            if ($childKey === null) {
                continue;
            }
            $deferred[] = [
                'kind' => 'child-check',
                'foreign_key' => $spec['name'],
                'child_table' => $table,
                'child_key' => $childKey,
                'parent_table' => $spec['parent_table'],
                'parent_key' => $childKey,
                'statement' => $statementIndex,
                'trigger' => $triggerName,
                'operation' => $operation,
                'deferred' => $spec['deferred'],
            ];
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string}> $specs
     * @param array<string,mixed> $row
     */
    private static function assertRestrictAllowsParentDelete(array $tables, array $specs, string $table, array $row): void
    {
        foreach ($specs as $spec) {
            if ($spec['parent_table'] !== $table || $spec['on_delete'] !== 'restrict') {
                continue;
            }
            $parentKey = self::value($row, $spec['parent_key'], 'parent row');
            foreach ($tables[$spec['child_table']] as $child) {
                if (self::value($child, $spec['child_key'], 'child row') === $parentKey) {
                    throw new \InvalidArgumentException('SQLite trigger deferred FK RESTRICT prevents parent delete immediately');
                }
            }
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $deferred
     * @param list<array<string,mixed>> $specs
     * @return array{actions:list<array<string,mixed>>,changes:int}
     */
    private static function applyParentUpdate(array &$tables, array &$deferred, array $specs, string $table, array $before, array $after, int $statementIndex, ?string $triggerName): array
    {
        $actions = [];
        $changes = 0;

        foreach ($specs as $spec) {
            if ($spec['parent_table'] !== $table) {
                continue;
            }
            $oldKey = self::value($before, $spec['parent_key'], 'old parent row');
            $newKey = self::value($after, $spec['parent_key'], 'new parent row');
            if ($oldKey === $newKey) {
                continue;
            }

            if ($spec['on_update'] === 'restrict' && self::hasKey($tables[$spec['child_table']], $spec['child_key'], $oldKey)) {
                throw new \InvalidArgumentException('SQLite trigger deferred FK RESTRICT prevents parent update immediately');
            }

            if ($spec['on_update'] === 'no action') {
                $deferred[] = [
                    'kind' => 'parent-update-check',
                    'foreign_key' => $spec['name'],
                    'parent_table' => $table,
                    'old_parent_key' => $oldKey,
                    'new_parent_key' => $newKey,
                    'child_table' => $spec['child_table'],
                    'statement' => $statementIndex,
                    'trigger' => $triggerName,
                    'operation' => 'update',
                    'deferred' => $spec['deferred'],
                ];
                $actions[] = self::parentUpdateAction($spec, $oldKey, $newKey, $statementIndex, $triggerName, 'defer-parent-update-check', null, 0);
                continue;
            }

            foreach ($tables[$spec['child_table']] as &$child) {
                if (self::value($child, $spec['child_key'], 'child row') !== $oldKey) {
                    continue;
                }
                $replacement = match ($spec['on_update']) {
                    'cascade' => $newKey,
                    'set null' => null,
                    'set default' => $spec['child_default'],
                    default => $child[$spec['child_key']],
                };
                $child[$spec['child_key']] = $replacement;
                $changes++;
                $actions[] = self::parentUpdateAction($spec, $oldKey, $newKey, $statementIndex, $triggerName, 'update-child-key', $replacement, 1);
            }
            unset($child);
        }

        return ['actions' => $actions, 'changes' => $changes];
    }

    /**
     * @param array<string,mixed> $spec
     * @return array<string,mixed>
     */
    private static function parentUpdateAction(array $spec, mixed $oldKey, mixed $newKey, int $statementIndex, ?string $triggerName, string $action, mixed $childKey, int $rows): array
    {
        return [
            'kind' => 'parent-update',
            'action' => $action,
            'foreign_key' => $spec['name'],
            'parent_table' => $spec['parent_table'],
            'old_parent_key' => $oldKey,
            'new_parent_key' => $newKey,
            'child_table' => $spec['child_table'],
            'child_key' => $childKey,
            'on_update' => $spec['on_update'],
            'statement' => $statementIndex,
            'trigger' => $triggerName,
            'rows' => $rows,
        ];
    }

    /**
     * @param list<array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string}> $specs
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $deferred
     */
    private static function queueForDeletedParent(array &$deferred, array $specs, string $table, array $row, int $statementIndex, ?string $triggerName): void
    {
        foreach ($specs as $spec) {
            if ($spec['parent_table'] !== $table || $spec['on_delete'] === 'restrict') {
                continue;
            }
            $parentKey = self::value($row, $spec['parent_key'], 'parent row');
            $deferred[] = [
                'kind' => 'parent-delete-check',
                'foreign_key' => $spec['name'],
                'parent_table' => $table,
                'parent_key' => $parentKey,
                'child_table' => $spec['child_table'],
                'statement' => $statementIndex,
                'trigger' => $triggerName,
                'operation' => 'delete',
                'deferred' => $spec['deferred'],
            ];
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string}> $specs
     * @param list<array<string,mixed>> $deferred
     * @return list<array<string,mixed>>
     */
    private static function violations(array $tables, array $specs, array $deferred): array
    {
        $violations = [];
        foreach ($deferred as $check) {
            foreach ($specs as $spec) {
                if ($spec['name'] !== $check['foreign_key']) {
                    continue;
                }
                if ($check['kind'] === 'child-check') {
                    if (!self::hasKey($tables[$spec['parent_table']], $spec['parent_key'], $check['parent_key'])) {
                        $violations[] = $check + ['reason' => 'missing-parent-at-commit'];
                    }
                    continue;
                }

                if ($check['kind'] === 'parent-update-check') {
                    if (self::hasKey($tables[$spec['child_table']], $spec['child_key'], $check['old_parent_key']) && !self::hasKey($tables[$spec['parent_table']], $spec['parent_key'], $check['old_parent_key'])) {
                        $violations[] = $check + ['reason' => 'referenced-parent-updated-at-commit'];
                    }
                    continue;
                }

                if (self::hasKey($tables[$spec['child_table']], $spec['child_key'], $check['parent_key'])) {
                    $violations[] = $check + ['reason' => 'referenced-parent-deleted-at-commit'];
                }
            }
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function hasKey(array $rows, string $column, mixed $expected): bool
    {
        foreach ($rows as $row) {
            if (self::value($row, $column, 'row') === $expected) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $match
     */
    private static function rowMatches(array $row, array $match): bool
    {
        foreach ($match as $column => $value) {
            if (!array_key_exists($column, $row) || $row[$column] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private static function row(mixed $row, string $label): array
    {
        if (!is_array($row)) {
            throw new \InvalidArgumentException("SQLite trigger deferred FK {$label} is malformed");
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function value(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite trigger deferred FK {$label} is missing column {$column}");
        }

        return $row[$column];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed>|null $before
     * @return array<string,mixed>
     */
    private static function event(int $statementIndex, ?string $triggerName, string $action, string $table, array $row, ?array $before = null): array
    {
        $event = [
            'statement' => $statementIndex,
            'trigger' => $triggerName,
            'action' => $action,
            'table' => $table,
            'row' => $row,
        ];
        if ($before !== null) {
            $event['before'] = $before;
        }

        return $event;
    }

    private static function operation(mixed $value): string
    {
        $operation = strtolower(trim((string) $value));
        if (!in_array($operation, ['insert', 'update', 'delete'], true)) {
            throw new \InvalidArgumentException('SQLite trigger deferred FK statement operation is unsupported');
        }

        return $operation;
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger deferred FK {$label} is malformed");
        }

        return $value;
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
}
