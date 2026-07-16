<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerDeferrableFkSavepointPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $program
     * @param list<array<string,mixed>> $foreignKeys
     * @return array{tables:array<string,list<array<string,mixed>>>,events:list<array<string,mixed>>,deferred:list<array<string,mixed>>,violations:list<array<string,mixed>>,savepoints:list<string>,changes:int,status:string}
     */
    public static function run(array $tables, array $program, array $foreignKeys, bool $deferAll = false): array
    {
        $rows = self::normalizeTables($tables);
        $specs = array_map(self::normalizeForeignKey(...), $foreignKeys);
        self::assertTables($rows, $specs);

        $events = [];
        $deferred = [];
        $violations = [];
        $savepoints = [];
        $frames = [];
        $changes = 0;

        foreach (array_values($program) as $index => $statement) {
            $operation = self::operation($statement['operation'] ?? null);
            if ($operation === 'savepoint') {
                $name = self::identifier($statement['name'] ?? null, 'savepoint name');
                $savepoints[] = $name;
                $frames[$name] = ['tables' => $rows, 'deferred' => $deferred, 'changes' => $changes];
                $events[] = self::event($index, null, 'savepoint', $name);
                continue;
            }
            if ($operation === 'release') {
                $name = self::identifier($statement['name'] ?? null, 'savepoint name');
                self::requireOpenSavepoint($savepoints, $name);
                while ($savepoints !== []) {
                    $released = array_pop($savepoints);
                    unset($frames[$released]);
                    $events[] = self::event($index, null, 'release', $released);
                    if ($released === $name) {
                        break;
                    }
                }
                continue;
            }
            if ($operation === 'rollback-to') {
                $name = self::identifier($statement['name'] ?? null, 'savepoint name');
                self::requireOpenSavepoint($savepoints, $name);
                $frame = $frames[$name];
                $rows = $frame['tables'];
                $deferred = $frame['deferred'];
                $changes = $frame['changes'];
                while (($tail = end($savepoints)) !== false && $tail !== $name) {
                    array_pop($savepoints);
                    unset($frames[$tail]);
                }
                $events[] = self::event($index, null, 'rollback-to', $name);
                continue;
            }

            $table = self::identifier($statement['table'] ?? null, 'statement table');
            if (!array_key_exists($table, $rows)) {
                throw new \InvalidArgumentException("SQLite trigger deferrable FK table {$table} is missing");
            }
            $trigger = isset($statement['trigger']) ? self::identifier($statement['trigger'], 'trigger name') : null;

            if ($operation === 'insert') {
                $row = self::row($statement['row'] ?? null, 'insert row');
                $rows[$table][] = $row;
                $changes++;
                $events[] = self::event($index, $trigger, 'insert-row', $table, $row);
                self::checkChangedChild($rows, $specs, $table, $row, $index, $trigger, 'insert', $deferAll, $deferred, $violations);
                continue;
            }

            if ($operation === 'update') {
                $match = self::row($statement['match'] ?? null, 'update match');
                $set = self::row($statement['set'] ?? null, 'update set');
                foreach ($rows[$table] as $rowIndex => $row) {
                    if (!self::rowMatches($row, $match)) {
                        continue;
                    }
                    $before = $row;
                    foreach ($set as $column => $value) {
                        $row[self::identifier((string) $column, 'set column')] = $value;
                    }
                    $rows[$table][$rowIndex] = $row;
                    $changes++;
                    $events[] = self::event($index, $trigger, 'update-row', $table, $row, $before);
                    self::checkChangedChild($rows, $specs, $table, $row, $index, $trigger, 'update', $deferAll, $deferred, $violations);
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
                self::checkDeletedParent($rows, $specs, $table, $row, $index, $trigger, $deferAll, $deferred, $violations);
                $changes++;
                $events[] = self::event($index, $trigger, 'delete-row', $table, $row);
            }
            $rows[$table] = array_values($kept);
        }

        $commitViolations = self::commitViolations($rows, $specs, $deferred);
        $violations = array_merge($violations, $commitViolations);

        return [
            'tables' => self::sortTables($rows),
            'events' => $events,
            'deferred' => $deferred,
            'violations' => $violations,
            'savepoints' => array_values($savepoints),
            'changes' => $changes,
            'status' => $violations === [] ? 'commit-ok' : 'commit-blocked',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string}> $specs
     * @param list<array<string,mixed>> $deferred
     * @param list<array<string,mixed>> $violations
     */
    private static function checkChangedChild(array $tables, array $specs, string $table, array $row, int $statement, ?string $trigger, string $operation, bool $deferAll, array &$deferred, array &$violations): void
    {
        foreach ($specs as $spec) {
            if ($spec['child_table'] !== $table) {
                continue;
            }
            $childKey = self::value($row, $spec['child_key'], 'child row');
            if ($childKey === null) {
                continue;
            }
            $check = [
                'kind' => 'child-check',
                'foreign_key' => $spec['name'],
                'child_table' => $table,
                'child_key' => $childKey,
                'parent_table' => $spec['parent_table'],
                'parent_key' => $childKey,
                'statement' => $statement,
                'trigger' => $trigger,
                'operation' => $operation,
                'deferred' => $spec['deferred'] || $deferAll,
            ];
            if ($check['deferred']) {
                $deferred[] = $check;
            } elseif (!self::hasKey($tables[$spec['parent_table']], $spec['parent_key'], $childKey)) {
                $violations[] = $check + ['reason' => 'missing-parent-at-statement'];
            }
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string}> $specs
     * @param list<array<string,mixed>> $deferred
     * @param list<array<string,mixed>> $violations
     */
    private static function checkDeletedParent(array $tables, array $specs, string $table, array $row, int $statement, ?string $trigger, bool $deferAll, array &$deferred, array &$violations): void
    {
        foreach ($specs as $spec) {
            if ($spec['parent_table'] !== $table) {
                continue;
            }
            $parentKey = self::value($row, $spec['parent_key'], 'parent row');
            $check = [
                'kind' => 'parent-delete-check',
                'foreign_key' => $spec['name'],
                'parent_table' => $table,
                'parent_key' => $parentKey,
                'child_table' => $spec['child_table'],
                'statement' => $statement,
                'trigger' => $trigger,
                'operation' => 'delete',
                'deferred' => $spec['deferred'] || $deferAll,
            ];
            if ($spec['on_delete'] === 'restrict' || !$check['deferred']) {
                if (self::hasKey($tables[$spec['child_table']], $spec['child_key'], $parentKey)) {
                    $violations[] = $check + ['reason' => $spec['on_delete'] === 'restrict' ? 'restrict-parent-delete-at-statement' : 'referenced-parent-deleted-at-statement'];
                }
                continue;
            }
            $deferred[] = $check;
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string}> $specs
     * @param list<array<string,mixed>> $deferred
     * @return list<array<string,mixed>>
     */
    private static function commitViolations(array $tables, array $specs, array $deferred): array
    {
        $violations = [];
        foreach ($deferred as $check) {
            foreach ($specs as $spec) {
                if ($spec['name'] !== $check['foreign_key']) {
                    continue;
                }
                if ($check['kind'] === 'child-check' && !self::hasKey($tables[$spec['parent_table']], $spec['parent_key'], $check['parent_key'])) {
                    $violations[] = $check + ['reason' => 'missing-parent-at-commit'];
                }
                if ($check['kind'] === 'parent-delete-check' && self::hasKey($tables[$spec['child_table']], $spec['child_key'], $check['parent_key'])) {
                    $violations[] = $check + ['reason' => 'referenced-parent-deleted-at-commit'];
                }
            }
        }

        return $violations;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        $normalized = [];
        foreach ($tables as $name => $rows) {
            $normalized[self::identifier((string) $name, 'table')] = array_values($rows);
        }

        return $normalized;
    }

    /**
     * @return array{name:string,parent_table:string,parent_key:string,child_table:string,child_key:string,deferred:bool,on_delete:string}
     */
    private static function normalizeForeignKey(array $foreignKey): array
    {
        $onDelete = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($onDelete, ['no action', 'restrict'], true)) {
            throw new \InvalidArgumentException('SQLite trigger deferrable FK only supports NO ACTION and RESTRICT');
        }

        return [
            'name' => isset($foreignKey['name']) ? self::identifier($foreignKey['name'], 'foreign key name') : 'fk',
            'parent_table' => self::identifier($foreignKey['parent_table'] ?? null, 'parent table'),
            'parent_key' => self::identifier($foreignKey['parent_key'] ?? 'id', 'parent key'),
            'child_table' => self::identifier($foreignKey['child_table'] ?? null, 'child table'),
            'child_key' => self::identifier($foreignKey['child_key'] ?? null, 'child key'),
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
            'on_delete' => $onDelete,
        ];
    }

    /** @param array<string,list<array<string,mixed>>> $tables */
    private static function assertTables(array $tables, array $specs): void
    {
        foreach ($specs as $spec) {
            if (!array_key_exists($spec['parent_table'], $tables) || !array_key_exists($spec['child_table'], $tables)) {
                throw new \InvalidArgumentException('SQLite trigger deferrable FK table is missing');
            }
        }
    }

    /** @param list<string> $savepoints */
    private static function requireOpenSavepoint(array $savepoints, string $name): void
    {
        if (!in_array($name, $savepoints, true)) {
            throw new \InvalidArgumentException("SQLite trigger deferrable FK savepoint {$name} is not open");
        }
    }

    /** @param list<array<string,mixed>> $rows */
    private static function hasKey(array $rows, string $column, mixed $expected): bool
    {
        foreach ($rows as $row) {
            if (self::value($row, $column, 'row') === $expected) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $row */
    private static function value(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite trigger deferrable FK {$label} is missing column {$column}");
        }

        return $row[$column];
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $match */
    private static function rowMatches(array $row, array $match): bool
    {
        foreach ($match as $column => $value) {
            if (!array_key_exists($column, $row) || $row[$column] !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,mixed> */
    private static function row(mixed $row, string $label): array
    {
        if (!is_array($row)) {
            throw new \InvalidArgumentException("SQLite trigger deferrable FK {$label} is malformed");
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private static function event(int $statement, ?string $trigger, string $action, string $table, ?array $row = null, ?array $before = null): array
    {
        $event = ['statement' => $statement, 'trigger' => $trigger, 'action' => $action, 'table' => $table];
        if ($row !== null) {
            $event['row'] = $row;
        }
        if ($before !== null) {
            $event['before'] = $before;
        }

        return $event;
    }

    private static function operation(mixed $value): string
    {
        $operation = strtolower(str_replace('_', '-', trim((string) $value)));
        if (!in_array($operation, ['insert', 'update', 'delete', 'savepoint', 'release', 'rollback-to'], true)) {
            throw new \InvalidArgumentException('SQLite trigger deferrable FK operation is unsupported');
        }

        return $operation;
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger deferrable FK {$label} is malformed");
        }

        return $value;
    }

    /** @param array<string,list<array<string,mixed>>> $tables */
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
