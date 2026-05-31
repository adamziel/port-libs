<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpstreamTriggerFkeyDynamicPlan
{
    /** @return array<string,mixed> */
    public static function fkey7(): array
    {
        $readCases = [
            'fkey7-1.2' => ['sql' => 'UPDATE par SET b=? WHERE a=?', 'reads' => ['par', 's1']],
            'fkey7-1.3' => ['sql' => 'UPDATE par SET a=? WHERE b=?', 'reads' => ['c1', 'c2', 'par']],
            'fkey7-1.4' => ['sql' => 'UPDATE par SET c=? WHERE b=?', 'reads' => ['c3', 'par']],
            'fkey7-1.5' => ['sql' => 'UPDATE par SET a=?,b=?,c=? WHERE b=?', 'reads' => ['c1', 'c2', 'c3', 'par', 's1']],
        ];
        $rows = [];
        foreach ($readCases as $case => $config) {
            $reads = $config['reads'];
            sort($reads);
            $rows[$case] = [
                'case' => $case,
                'sql' => $config['sql'],
                'reads' => $reads,
                'read_count' => count($reads),
                'reads_parent' => in_array('par', $reads, true),
                'reads_parent_lookup' => in_array('s1', $reads, true),
                'reads_child_lookup' => count(array_intersect($reads, ['c1', 'c2', 'c3'])),
                'source' => 'fkey7.test',
            ];
        }

        return [
            'schema' => [
                's1' => ['primary_key' => ['a']],
                'par' => ['primary_key' => ['a'], 'foreign_keys' => [['from' => 'b', 'table' => 's1'], ['from' => 'c', 'unique' => true]]],
                'c1' => ['foreign_keys' => [['from' => 'b', 'table' => 'par']]],
                'c2' => ['foreign_keys' => [['from' => 'b', 'table' => 'par']]],
                'c3' => ['foreign_keys' => [['from' => 'b', 'table' => 'par', 'to' => 'c']]],
            ],
            'read_cases' => array_values($rows),
            'zeroblob' => [
                'fkey7-2.1' => ['ok' => false, 'code' => 'SQLITE_CONSTRAINT_FOREIGNKEY', 'error' => 'FOREIGN KEY constraint failed', 'child_rows' => []],
                'fkey7-2.2' => ['ok' => false, 'code' => 'SQLITE_CONSTRAINT_FOREIGNKEY', 'error' => 'FOREIGN KEY constraint failed', 'bound_blob_bytes' => 45, 'child_rows' => []],
            ],
            'stat4' => [
                'case' => 'fkey7-3.0',
                'parent_rows' => [1, 2, 3, 4],
                'child_rows' => [1, 2, 3],
                'child_index' => 'c4_x',
                'deferred_violation_count' => 0,
                'analyze_keeps_fk_ok' => true,
            ],
            'or_fail' => [
                'fkey7-4.1' => ['ok' => false, 'error' => 'FOREIGN KEY constraint failed', 'child_rows' => []],
                'fkey7-4.2' => ['child_rows' => []],
                'fkey7-4.3' => ['foreign_key_check' => []],
                'fkey7-4.4' => ['ok' => false, 'error' => 'UNIQUE constraint failed: child.c', 'child_rows' => [123]],
                'fkey7-4.5' => ['child_rows' => [123]],
                'fkey7-4.6' => ['foreign_key_check' => []],
            ],
            'dependencies' => [
                'sqlite-upstream-fkey7-read-dependencies',
                'sqlite-upstream-fkey7-zeroblob-foreign-key-failure',
                'sqlite-upstream-fkey7-or-fail-constraint-precedence',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function trigger2RowTiming(): array
    {
        $definitions = [
            'trigger2-1.1.1' => ['kind' => 'rowid-table', 'temp' => false],
            'trigger2-1.1.2' => ['kind' => 'integer-primary-key', 'temp' => false],
            'trigger2-1.1.3' => ['kind' => 'declared-primary-key', 'temp' => false],
            'trigger2-1.1.4' => ['kind' => 'indexed-column', 'temp' => false],
            'trigger2-1.1.5' => ['kind' => 'temp-indexed-column', 'temp' => true],
            'trigger2-1.1.6' => ['kind' => 'temp-rowid-table', 'temp' => true],
            'trigger2-1.1.7' => ['kind' => 'temp-integer-primary-key', 'temp' => true],
        ];
        $cases = [];
        foreach ($definitions as $case => $definition) {
            $cases[$case] = [
                'case' => $case,
                'definition' => $definition,
                'update_log' => [
                    [1, 1, 2, 4, 6, 10, 20],
                    [2, 1, 2, 13, 24, 10, 20],
                    [3, 3, 4, 13, 24, 30, 40],
                    [4, 3, 4, 40, 60, 30, 40],
                ],
                'conditional_update_log' => [[1, 1, 2, 13, 24, 10, 20]],
                'delete_log' => [
                    [1, 100, 100, 400, 300, 0, 0],
                    [2, 100, 100, 300, 200, 0, 0],
                    [3, 300, 200, 300, 200, 0, 0],
                    [4, 300, 200, 0, 0, 0, 0],
                ],
                'insert_log' => [
                    [1, 0, 0, 0, 0, 5, 6],
                    [2, 0, 0, 5, 6, 5, 6],
                ],
                'source' => 'trigger2.test',
            ];
        }

        return [
            'recursive_triggers' => false,
            'row_timing_cases' => array_values($cases),
            'dependencies' => [
                'sqlite-upstream-trigger2-before-after-row-order',
                'sqlite-upstream-trigger2-conditional-trigger-when',
                'sqlite-upstream-trigger2-temp-table-trigger-timing',
            ],
        ];
    }
}
