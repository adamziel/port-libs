<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDynamicTriggerForeignKeyPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param array{id:mixed,parent_id:mixed,label?:string} $incoming
     * @return array<string,mixed>
     */
    public static function replaceSelfReferencingRow(array $rows, array $incoming, bool $deferred = true, bool $rollbackOnViolation = true): array
    {
        $original = array_values($rows);
        $rows = array_values($rows);
        $incomingId = $incoming['id'] ?? throw new \InvalidArgumentException('SQLite dynamic trigger FK incoming row id is required');
        $incomingParent = $incoming['parent_id'] ?? null;
        $deleted = [];

        foreach ($rows as $index => $row) {
            if (($row['id'] ?? null) === $incomingId) {
                $deleted[] = $row;
                unset($rows[$index]);
                break;
            }
        }

        $rows = array_values($rows);
        $queue = array_column($deleted, 'id');
        while ($queue !== []) {
            $parent = array_shift($queue);
            foreach ($rows as $index => $row) {
                if (($row['parent_id'] ?? null) === $parent) {
                    $deleted[] = $row;
                    $queue[] = $row['id'];
                    unset($rows[$index]);
                }
            }
            $rows = array_values($rows);
        }

        $rows[] = $incoming + ['label' => $incoming['label'] ?? 'incoming-' . (string) $incomingId];
        $violations = self::selfReferencingViolations($rows);
        $rollback = $deferred && $rollbackOnViolation && $violations !== [];

        return [
            'source' => 'fkey1.test fkey1-5.1..5.4',
            'operation' => 'insert-or-replace-self-referencing-cascade',
            'status' => $violations === [] ? 'commit-ok' : ($rollback ? 'rolled-back' : 'constraint-failed'),
            'deferred' => $deferred,
            'rollback_on_violation' => $rollbackOnViolation,
            'incoming_id' => $incomingId,
            'incoming_parent_id' => $incomingParent,
            'deleted_ids' => array_values(array_column($deleted, 'id')),
            'deleted_parent_ids' => array_values(array_column($deleted, 'parent_id')),
            'cascade_delete_count' => count($deleted),
            'attempted_rows' => self::sortRows($rows),
            'committed_rows' => self::sortRows($rollback ? $original : $rows),
            'violations' => $violations,
            'violation_count' => count($violations),
            'boundary' => $rollback ? 'replace-delete-cascade-rolled-back' : ($violations === [] ? 'replace-cascade-committed' : 'replace-cascade-blocked'),
            'dependencies' => [
                'sqlite-fkey1-replace-cascade-parent-delete',
                'sqlite-deferred-foreign-key-commit-check',
                'sqlite-trigger-backed-foreign-key-actions',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{event:string,match_column:string,match_value:mixed,delete_column:string,delete_value:mixed,name?:string} $trigger
     * @return array<string,mixed>
     */
    public static function deleteWithAfterTrigger(array $rows, string $whereColumn, mixed $whereValue, array $trigger): array
    {
        $whereColumn = self::identifier($whereColumn, 'WHERE column');
        $matchColumn = self::identifier((string) ($trigger['match_column'] ?? ''), 'trigger match column');
        $deleteColumn = self::identifier((string) ($trigger['delete_column'] ?? ''), 'trigger delete column');
        $triggerName = self::identifier((string) ($trigger['name'] ?? 'audit_delete'), 'trigger name');
        $rows = array_values($rows);
        $outerDeleted = [];
        $triggerDeleted = [];

        foreach ($rows as $index => $row) {
            if (($row[$whereColumn] ?? null) === $whereValue) {
                $outerDeleted[] = $row;
                unset($rows[$index]);
            }
        }
        $rows = array_values($rows);

        foreach ($outerDeleted as $old) {
            if (($old[$matchColumn] ?? null) !== $trigger['match_value']) {
                continue;
            }
            foreach ($rows as $index => $row) {
                if (($row[$deleteColumn] ?? null) === $trigger['delete_value']) {
                    $triggerDeleted[] = $row + ['trigger' => $triggerName];
                    unset($rows[$index]);
                }
            }
            $rows = array_values($rows);
        }

        return [
            'source' => 'trigger1.test trigger1-1.10',
            'operation' => 'delete-statement-with-after-delete-trigger',
            'status' => 'commit-ok',
            'trigger' => $triggerName,
            'outer_deleted_ids' => array_values(array_column($outerDeleted, 'id')),
            'trigger_deleted_ids' => array_values(array_column($triggerDeleted, 'id')),
            'remaining_ids' => array_values(array_column(self::sortRows($rows), 'id')),
            'outer_delete_count' => count($outerDeleted),
            'trigger_delete_count' => count($triggerDeleted),
            'total_changes' => count($outerDeleted) + count($triggerDeleted),
            'statement_delete_preserved' => $outerDeleted !== [],
            'dependencies' => [
                'sqlite-trigger1-delete-trigger-does-not-corrupt-outer-delete',
                'sqlite-row-trigger-old-row-scope',
            ],
        ];
    }

    /**
     * @param list<array{name:string,object_type:string,target?:string,temp?:bool}> $objects
     * @return array<string,mixed>
     */
    public static function schemaLifecycle(array $objects, array $actions): array
    {
        $catalog = [];
        $tempCatalog = [];
        foreach ($objects as $object) {
            self::catalogAdd($catalog, $tempCatalog, $object);
        }

        $snapshots = [];
        $errors = [];
        $transaction = null;
        foreach ($actions as $action) {
            if (!is_array($action)) {
                throw new \InvalidArgumentException('SQLite dynamic trigger FK schema action is malformed');
            }
            $op = strtolower((string) ($action['op'] ?? ''));
            if ($op === 'begin') {
                $transaction = [$catalog, $tempCatalog];
            } elseif ($op === 'rollback') {
                if ($transaction !== null) {
                    [$catalog, $tempCatalog] = $transaction;
                    $transaction = null;
                }
            } elseif ($op === 'commit') {
                $transaction = null;
            } elseif ($op === 'create-trigger') {
                $name = self::identifier((string) ($action['name'] ?? ''), 'trigger name');
                $target = self::identifier((string) ($action['target'] ?? ''), 'trigger target');
                $temp = (bool) ($action['temp'] ?? false);
                if (!isset($catalog[$target]) && !isset($tempCatalog[$target])) {
                    $errors[] = ['op' => $op, 'name' => $name, 'error' => $temp ? "no such table: {$target}" : "no such table: main.{$target}"];
                    continue;
                }
                $destination = $temp ? $tempCatalog : $catalog;
                if (isset($destination[$name])) {
                    $errors[] = ['op' => $op, 'name' => $name, 'error' => "trigger {$name} already exists"];
                    continue;
                }
                self::catalogAdd($catalog, $tempCatalog, ['name' => $name, 'object_type' => 'trigger', 'target' => $target, 'temp' => $temp]);
            } elseif ($op === 'drop-trigger') {
                $name = self::identifier((string) ($action['name'] ?? ''), 'trigger name');
                $ifExists = (bool) ($action['if_exists'] ?? false);
                if (isset($catalog[$name])) {
                    unset($catalog[$name]);
                } elseif (isset($tempCatalog[$name])) {
                    unset($tempCatalog[$name]);
                } elseif (!$ifExists) {
                    $errors[] = ['op' => $op, 'name' => $name, 'error' => "no such trigger: {$name}"];
                }
            } elseif ($op === 'drop-table') {
                $name = self::identifier((string) ($action['name'] ?? ''), 'table name');
                unset($catalog[$name], $tempCatalog[$name]);
                foreach ($catalog as $objectName => $object) {
                    if (($object['object_type'] ?? null) === 'trigger' && ($object['target'] ?? null) === $name) {
                        unset($catalog[$objectName]);
                    }
                }
                foreach ($tempCatalog as $objectName => $object) {
                    if (($object['object_type'] ?? null) === 'trigger' && ($object['target'] ?? null) === $name) {
                        unset($tempCatalog[$objectName]);
                    }
                }
            } else {
                throw new \InvalidArgumentException('SQLite dynamic trigger FK schema action is unsupported');
            }

            $snapshots[] = [
                'op' => $op,
                'main' => self::catalogNames($catalog),
                'temp' => self::catalogNames($tempCatalog),
            ];
        }

        return [
            'source' => 'trigger1.test trigger1-1.2..1.8',
            'operation' => 'trigger-schema-lifecycle',
            'status' => $errors === [] ? 'ok' : 'error-recorded',
            'main_names' => self::catalogNames($catalog),
            'temp_names' => self::catalogNames($tempCatalog),
            'main_trigger_names' => self::catalogNames($catalog, 'trigger'),
            'temp_trigger_names' => self::catalogNames($tempCatalog, 'trigger'),
            'errors' => $errors,
            'error_count' => count($errors),
            'snapshots' => $snapshots,
            'dependencies' => [
                'sqlite-trigger1-create-drop-rollback',
                'sqlite-trigger1-temp-trigger-hidden-from-main-schema',
                'sqlite-trigger1-drop-table-drops-triggers',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function selfReferencingViolations(array $rows): array
    {
        $ids = array_column($rows, 'id');
        $violations = [];
        foreach ($rows as $index => $row) {
            $parent = $row['parent_id'] ?? null;
            if ($parent !== null && !in_array($parent, $ids, true)) {
                $violations[] = [
                    'row_index' => $index,
                    'id' => $row['id'] ?? null,
                    'parent_id' => $parent,
                    'phase' => 'deferred-commit',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function sortRows(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));

        return array_values($rows);
    }

    private static function catalogAdd(array &$catalog, array &$tempCatalog, array $object): void
    {
        $name = self::identifier((string) ($object['name'] ?? ''), 'object name');
        $type = (string) ($object['object_type'] ?? '');
        if (!in_array($type, ['table', 'view', 'trigger'], true)) {
            throw new \InvalidArgumentException('SQLite dynamic trigger FK catalog object type is unsupported');
        }

        $row = [
            'name' => $name,
            'object_type' => $type,
            'target' => isset($object['target']) ? self::identifier((string) $object['target'], 'trigger target') : null,
        ];
        if ((bool) ($object['temp'] ?? false)) {
            $tempCatalog[$name] = $row;
        } else {
            $catalog[$name] = $row;
        }
    }

    /**
     * @return list<string>
     */
    private static function catalogNames(array $catalog, ?string $type = null): array
    {
        $names = [];
        foreach ($catalog as $name => $object) {
            if ($type === null || ($object['object_type'] ?? null) === $type) {
                $names[] = (string) $name;
            }
        }
        sort($names);

        return $names;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite dynamic trigger FK {$label} is malformed");
        }

        return $value;
    }
}
