<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteSchemaObjectNamespacePlan
{
    /** @var array<string, array<string, array{name:string, type:string, target?:string, sql:string, temp:bool}>> */
    private array $objectsByType = [
        'table' => [],
        'view' => [],
        'index' => [],
        'trigger' => [],
    ];

    /** @var list<array{x:string, a:mixed, b:mixed}> */
    private array $log = [];

    /**
     * @param list<array{name:string,type:string,target?:string,sql?:string,temp?:bool}> $objects
     */
    public function __construct(array $objects = [])
    {
        foreach ($objects as $object) {
            $this->createObject($object['type'], $object['name'], $object['target'] ?? null, $object['sql'] ?? null, $object['temp'] ?? false);
        }
    }

    public static function schema4Fixture(string $suffix): self
    {
        $plan = new self();
        $plan->createObject('table', 'log_' . $suffix);
        $plan->createObject('table', 'tbl_' . $suffix);
        $plan->createObject('table', 't1_' . $suffix);
        $plan->createObject('view', 'v1_' . $suffix, 'tbl_' . $suffix);
        $plan->createObject('index', 'i1_' . $suffix, 'tbl_' . $suffix);
        $plan->createObject('trigger', 't1_' . $suffix, 'tbl_' . $suffix, 'after insert');
        $plan->createObject('trigger', 'v1_' . $suffix, 'tbl_' . $suffix, 'after update');
        $plan->createObject('trigger', 'i1_' . $suffix, 'tbl_' . $suffix, 'after delete');

        return $plan;
    }

    /**
     * @return array{operation:string,type:string,name:string,target:string|null,temp:bool,namespace_key:string}
     */
    public function createObject(string $type, string $name, ?string $target = null, ?string $sql = null, bool $temp = false): array
    {
        $type = strtolower($type);
        if (!array_key_exists($type, $this->objectsByType)) {
            throw new InvalidArgumentException('Unsupported schema object type: ' . $type);
        }

        $key = $this->key($name);
        $this->objectsByType[$type][$key] = [
            'name' => $name,
            'type' => $type,
            'target' => $target ?? '',
            'sql' => $sql ?? $this->defaultSql($type, $name, $target),
            'temp' => $temp,
        ];

        return [
            'operation' => 'create_schema_object',
            'type' => $type,
            'name' => $name,
            'target' => $target,
            'temp' => $temp,
            'namespace_key' => $type . ':' . $key,
        ];
    }

    /**
     * @return array{operation:string,type:string,name:string,dropped:bool,triggers_preserved:list<string>,same_name_trigger_preserved:bool}
     */
    public function dropObject(string $type, string $name): array
    {
        $type = strtolower($type);
        if (!array_key_exists($type, $this->objectsByType)) {
            throw new InvalidArgumentException('Unsupported schema object type: ' . $type);
        }

        $key = $this->key($name);
        $dropped = isset($this->objectsByType[$type][$key]);
        unset($this->objectsByType[$type][$key]);

        return [
            'operation' => 'drop_schema_object',
            'type' => $type,
            'name' => $name,
            'dropped' => $dropped,
            'triggers_preserved' => array_column($this->objectsByType['trigger'], 'name'),
            'same_name_trigger_preserved' => isset($this->objectsByType['trigger'][$key]),
        ];
    }

    /**
     * @return array{operation:string,from:string,to:string,renamed:bool,temp_sql:list<string>,triggers:list<string>}
     */
    public function renameTable(string $from, string $to): array
    {
        $fromKey = $this->key($from);
        $toKey = $this->key($to);
        $renamed = isset($this->objectsByType['table'][$fromKey]);
        if ($renamed) {
            $object = $this->objectsByType['table'][$fromKey];
            unset($this->objectsByType['table'][$fromKey]);
            $object['name'] = $to;
            $object['sql'] = $this->defaultSql('table', $to, null);
            $this->objectsByType['table'][$toKey] = $object;
        }

        foreach ($this->objectsByType['trigger'] as &$trigger) {
            if (($trigger['target'] ?? '') === $from) {
                $trigger['target'] = $to;
            }
        }

        $triggers = array_column($this->objectsByType['trigger'], 'name');
        sort($triggers);

        return [
            'operation' => 'rename_table',
            'from' => $from,
            'to' => $to,
            'renamed' => $renamed,
            'temp_sql' => $this->tempSql(),
            'triggers' => $triggers,
        ];
    }

    /**
     * @return list<array{x:string, a:mixed, b:mixed}>
     */
    public function exerciseTriggers(string $table, mixed $a, mixed $b): array
    {
        $insert = ['x' => 'after insert', 'a' => $a, 'b' => $b];
        $updated = ['a' => is_numeric($a) ? $a + 1 : $a . '_updated', 'b' => is_numeric($a) && is_numeric($b) ? $a + $b : $b . '_updated'];
        $update = ['x' => 'after update', 'a' => $updated['a'], 'b' => $updated['b']];
        $delete = ['x' => 'after delete', 'a' => $updated['a'], 'b' => $updated['b']];

        foreach ([$insert, $update, $delete] as $entry) {
            if ($this->hasTriggerFor($table, $entry['x'])) {
                $this->log[] = $entry;
            }
        }

        return $this->log;
    }

    /**
     * @return array{objects:array<string,list<string>>, triggers_by_target:array<string,list<string>>, log:list<array{x:string,a:mixed,b:mixed}>, temp_sql:list<string>, dependencies:list<string>}
     */
    public function snapshot(): array
    {
        $objects = [];
        foreach ($this->objectsByType as $type => $byName) {
            $objects[$type] = array_column($byName, 'name');
            sort($objects[$type]);
        }

        $triggersByTarget = [];
        foreach ($this->objectsByType['trigger'] as $trigger) {
            $target = $trigger['target'] ?? '';
            $triggersByTarget[$target][] = $trigger['name'];
        }
        foreach ($triggersByTarget as &$names) {
            sort($names);
        }
        ksort($triggersByTarget);

        return [
            'objects' => $objects,
            'triggers_by_target' => $triggersByTarget,
            'log' => $this->log,
            'temp_sql' => $this->tempSql(),
            'dependencies' => ['sqlite-schema-object-namespace', 'sqlite-trigger-dispatch'],
        ];
    }

    private function hasTriggerFor(string $target, string $action): bool
    {
        foreach ($this->objectsByType['trigger'] as $trigger) {
            if (($trigger['target'] ?? '') === $target && $trigger['sql'] === $action) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function tempSql(): array
    {
        $sql = [];
        foreach ($this->objectsByType as $objects) {
            foreach ($objects as $object) {
                if ($object['temp']) {
                    $sql[] = $object['sql'];
                }
            }
        }
        sort($sql);

        return $sql;
    }

    private function key(string $name): string
    {
        return strtolower($name);
    }

    private function defaultSql(string $type, string $name, ?string $target): string
    {
        return match ($type) {
            'table' => 'CREATE TABLE ' . $name . '(a, b)',
            'view' => 'CREATE VIEW ' . $name . ' AS SELECT * FROM ' . ($target ?? 'sqlite_schema'),
            'index' => 'CREATE INDEX ' . $name . ' ON ' . ($target ?? 'sqlite_schema') . '(a)',
            'trigger' => 'after insert',
            default => throw new InvalidArgumentException('Unsupported schema object type: ' . $type),
        };
    }
}
