<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelfReferentialForeignKeyActionPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<mixed> $deleteKeys
     * @param array{key:string,parent_key:string,on_delete?:string,deferred?:bool,default?:mixed} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param array{recursive_triggers?:bool,max_depth?:int,current_source?:string,next_source?:string} $options
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,deleted_parent_keys:list<mixed>,trigger_effects:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,deferred_violations:list<array<string,mixed>>,commit_status:string,recursive_triggers:bool,current_source:string,next_source:string,dependencies:list<string>}
     */
    public static function deleteRows(
        array $rows,
        array $deleteKeys,
        array $foreignKey,
        array $triggers = [],
        array $options = [],
    ): array {
        $spec = self::foreignKeySpec($foreignKey);
        $recursiveTriggers = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = self::positiveInt($options['max_depth'] ?? 32, 'max depth');
        $currentSource = (string) ($options['current_source'] ?? 'current');
        $nextSource = (string) ($options['next_source'] ?? 'next');
        $rows = array_values($rows);
        $queue = array_map(static fn (mixed $key): array => ['key' => $key, 'depth' => 0, 'trigger' => null], $deleteKeys);
        $deleted = [];
        $effects = [];
        $actions = [];
        $statement = 0;

        while ($queue !== []) {
            $item = array_shift($queue);
            $depth = (int) $item['depth'];
            if ($depth > $maxDepth) {
                throw new \InvalidArgumentException('SQLite self-referential foreign-key action depth limit exceeded');
            }

            $index = self::rowIndex($rows, $item['key'], $spec['key']);
            if ($index === null) {
                continue;
            }

            $old = $rows[$index];
            array_splice($rows, $index, 1);
            $oldKey = self::rowValue($old, $spec['key'], 'OLD row');
            $deleted[] = $oldKey;

            $fk = self::applyDeleteAction($rows, $oldKey, $spec, $statement, $depth);
            $rows = $fk['rows'];
            $actions = array_merge($actions, $fk['actions']);

            $after = self::afterDeleteTriggers($triggers, $old, $rows, $spec, $statement, $depth, $recursiveTriggers);
            foreach ($after['inserted'] as $inserted) {
                $rows[] = $inserted;
            }
            $effects = array_merge($effects, $after['effects']);
            foreach ($after['deletes'] as $delete) {
                if ($recursiveTriggers) {
                    $queue[] = $delete;
                }
            }

            ++$statement;
        }

        $violations = self::violations($rows, $spec);

        return [
            'parent' => array_values($rows),
            'child' => array_values($rows),
            'deleted_parent_keys' => $deleted,
            'trigger_effects' => $effects,
            'foreign_key_actions' => $actions,
            'deferred_violations' => $violations,
            'commit_status' => $violations === [] ? 'ok' : 'deferred-constraint-failed',
            'recursive_triggers' => $recursiveTriggers,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'dependencies' => [
                'sqlite-self-referential-foreign-key-actions',
                'sqlite-foreign-key-actions-ignore-recursive-trigger-pragma',
                'sqlite-defer-foreign-keys-restrict-commit-check',
            ],
        ];
    }

    /**
     * @param array{key:string,parent_key:string,on_delete?:string,deferred?:bool,default?:mixed} $foreignKey
     * @return array{key:string,parent_key:string,on_delete:string,deferred:bool,default:mixed}
     */
    private static function foreignKeySpec(array $foreignKey): array
    {
        $action = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'no action', 'restrict', 'set null', 'set default'], true)) {
            throw new \InvalidArgumentException('SQLite self-referential foreign-key delete action is unsupported');
        }

        return [
            'key' => self::identifier((string) ($foreignKey['key'] ?? ''), 'row key'),
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'on_delete' => $action,
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
            'default' => $foreignKey['default'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rowIndex(array $rows, mixed $key, string $column): ?int
    {
        foreach ($rows as $index => $row) {
            if (self::rowValue($row, $column, 'row') == $key) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{key:string,parent_key:string,on_delete:string,deferred:bool,default:mixed} $spec
     * @return array{rows:list<array<string,mixed>>,actions:list<array<string,mixed>>}
     */
    private static function applyDeleteAction(array $rows, mixed $oldKey, array $spec, int $statement, int $depth): array
    {
        $actions = [];
        foreach ($rows as $index => &$row) {
            $parentKey = self::rowValue($row, $spec['parent_key'], 'child row');
            if ($parentKey != $oldKey) {
                continue;
            }

            if ($spec['on_delete'] === 'restrict' && !$spec['deferred']) {
                throw new \InvalidArgumentException('SQLite self-referential foreign-key RESTRICT failed');
            }

            $actions[] = [
                'statement' => $statement,
                'depth' => $depth,
                'child_index' => $index,
                'child_key' => self::rowValue($row, $spec['key'], 'child row'),
                'action' => $spec['on_delete'],
                'from' => $oldKey,
            ];

            if ($spec['on_delete'] === 'cascade') {
                $cascadeKey = self::rowValue($row, $spec['key'], 'child row');
                unset($rows[$index]);
                $nested = self::applyDeleteAction(array_values($rows), $cascadeKey, $spec, $statement, $depth + 1);
                $rows = $nested['rows'];
                $actions = array_merge($actions, $nested['actions']);
            } elseif ($spec['on_delete'] === 'set null') {
                $row[$spec['parent_key']] = null;
            } elseif ($spec['on_delete'] === 'set default') {
                $row[$spec['parent_key']] = $spec['default'];
            }
        }
        unset($row);

        return ['rows' => array_values($rows), 'actions' => $actions];
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @param list<array<string,mixed>> $rows
     * @param array{key:string,parent_key:string,on_delete:string,deferred:bool,default:mixed} $spec
     * @return array{inserted:list<array<string,mixed>>,deletes:list<array{key:mixed,depth:int,trigger:string}>,effects:list<array<string,mixed>>}
     */
    private static function afterDeleteTriggers(array $triggers, array $old, array $rows, array $spec, int $statement, int $depth, bool $recursiveTriggers): array
    {
        $inserted = [];
        $deletes = [];
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? 'after')) !== 'after' || strtolower((string) ($trigger['event'] ?? 'delete')) !== 'delete') {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old)) {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'insert-row') {
                $inserted[] = self::project((array) ($trigger['row'] ?? []), $old);
            } elseif ($action === 'enqueue-delete-children') {
                $oldKey = self::rowValue($old, $spec['key'], 'OLD row');
                foreach ($rows as $row) {
                    if (self::rowValue($row, $spec['parent_key'], 'trigger child row') == $oldKey) {
                        $deletes[] = [
                            'key' => self::rowValue($row, $spec['key'], 'trigger child row'),
                            'depth' => $depth + 1,
                            'trigger' => (string) ($trigger['name'] ?? ''),
                        ];
                    }
                }
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite self-referential foreign-key trigger action is unsupported');
            }

            $effects[] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'action' => $action,
                'statement' => $statement,
                'depth' => $depth,
                'recursive_triggers' => $recursiveTriggers,
                'row' => self::project((array) ($trigger['values'] ?? []), $old),
            ];
        }

        return ['inserted' => $inserted, 'deletes' => $deletes, 'effects' => $effects];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{key:string,parent_key:string,on_delete:string,deferred:bool,default:mixed} $spec
     * @return list<array{child_index:int,child_key:mixed,parent_key:mixed,phase:string}>
     */
    private static function violations(array $rows, array $spec): array
    {
        $keys = array_map(static fn (array $row): mixed => self::rowValue($row, $spec['key'], 'row'), $rows);
        $violations = [];
        foreach ($rows as $index => $row) {
            $parentKey = self::rowValue($row, $spec['parent_key'], 'row');
            if ($parentKey !== null && !in_array($parentKey, $keys, true)) {
                $violations[] = [
                    'child_index' => $index,
                    'child_key' => self::rowValue($row, $spec['key'], 'row'),
                    'parent_key' => $parentKey,
                    'phase' => 'commit',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite self-referential foreign-key {$label} {$column} is missing");
        }

        return $row[$column];
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function project(array $template, array $old): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            $row[self::identifier((string) $column, 'projection column')] = self::value($value, $old);
        }

        return $row;
    }

    private static function value(mixed $value, array $old): mixed
    {
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return self::rowValue($old, substr($value, 4), 'OLD row');
        }

        return $value;
    }

    private static function whenMatches(mixed $when, array $old): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite self-referential foreign-key WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old);
        $right = self::value($right, $old);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite self-referential foreign-key WHEN operator is unsupported'),
        };
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("SQLite self-referential foreign-key {$label} is malformed");
        }

        return $identifier;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite self-referential foreign-key {$label} is malformed");
        }

        return $value;
    }
}
