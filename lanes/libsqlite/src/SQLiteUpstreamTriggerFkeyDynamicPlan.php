<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpstreamTriggerFkeyDynamicPlan
{
    /** @return array<string,mixed> */
    public static function trigger6EvaluateOnce(): array
    {
        $cases = [];
        for ($seed = 1; $seed <= 80; $seed++) {
            $counter = $seed - 1;
            $insertSimple = self::trigger6Counter($counter, []);
            $insertExpression = self::trigger6Counter($counter, [2, 3]) + 4;
            $updateValue = self::trigger6Counter($counter, [5]);

            $cases[] = [
                'case' => 'trigger6-1.' . (($seed % 6) + 1),
                'variant' => $seed,
                'source' => 'trigger6.test',
                'insert_simple' => [
                    'statement_row' => ['x' => 1, 'y' => $insertSimple],
                    'trigger_log' => ['trigger' => 1, 'new_x' => 1, 'new_y' => $insertSimple],
                    'counter_after' => $seed,
                    'evaluations' => 1,
                    'new_matches_statement' => true,
                ],
                'insert_expression' => [
                    'statement_row' => ['x' => 2, 'y' => $insertExpression],
                    'trigger_log' => ['trigger' => 1, 'new_x' => 2, 'new_y' => $insertExpression],
                    'counter_after' => $seed + 1,
                    'evaluations' => 1,
                    'new_matches_statement' => true,
                ],
                'update_expression' => [
                    'statement_row' => ['x' => 2, 'y' => $updateValue],
                    'trigger_log' => ['trigger' => 2, 'new_x' => 2, 'new_y' => $updateValue],
                    'counter_after' => $seed + 2,
                    'evaluations' => 1,
                    'new_matches_statement' => true,
                ],
            ];
        }

        return [
            'source' => 'trigger6.test',
            'scenarios' => ['trigger6-1.1', 'trigger6-1.2', 'trigger6-1.3', 'trigger6-1.4', 'trigger6-1.5', 'trigger6-1.6'],
            'cases' => $cases,
            'dependencies' => [
                'sqlite-upstream-trigger6-insert-expression-evaluated-once',
                'sqlite-upstream-trigger6-update-expression-evaluated-once',
                'sqlite-upstream-trigger6-new-row-uses-statement-expression-value',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function triggerGRecursiveOnce(): array
    {
        $baseValues = [0, 2, 3, 8, 9];
        $singleSelect = self::triggerGReplay($baseValues, 2, false);
        $joinSelect = self::triggerGReplay($baseValues, 2, true);

        return [
            'recursive_triggers' => true,
            'source' => 'triggerG.test',
            'single_select' => [
                'case' => 'triggerG-100/110',
                'seed' => 2,
                't3_rows' => $singleSelect['t3'],
                't2_rows' => $singleSelect['t2'],
                'fires' => $singleSelect['fires'],
                'in_rhs' => [1, 2, 3, 4],
                'once_filter_values' => [2, 3],
            ],
            'join_select' => [
                'case' => 'triggerG-200',
                'seed' => 2,
                't3_rows' => $joinSelect['t3'],
                't2_rows' => $joinSelect['t2'],
                'fires' => $joinSelect['fires'],
                'left_in_rhs' => [1, 2, 3, 4],
                'right_in_rhs' => [2, 3, 4, 5],
                'once_filter_values' => [2, 3],
            ],
            'hex_literal' => [
                'case' => 'triggerG-300/310',
                'ok' => false,
                'error' => 'hex literal too big: 0x2147483648e0e0099',
            ],
            'instead_of_view' => [
                'case' => 'triggerG-400/405/410',
                'view_rows' => [1234],
                'delete_ok' => true,
                'old_row_visible' => 1234,
            ],
            'dependencies' => [
                'sqlite-upstream-triggerG-recursive-op-once',
                'sqlite-upstream-triggerG-recursive-select-in',
                'sqlite-upstream-triggerG-instead-of-view-delete',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function fkey8StatementJournal(): array
    {
        $cases = [
            'fkey8-1.1' => ['sql' => 'DELETE FROM p1', 'action' => 'NO ACTION', 'statement_journal' => true, 'reason' => 'immediate foreign-key counter may roll back statement'],
            'fkey8-1.2.1' => ['sql' => 'DELETE FROM p1', 'action' => 'CASCADE', 'statement_journal' => false, 'reason' => 'cascade delete has no post-action failure source'],
            'fkey8-1.2.2' => ['sql' => 'DELETE FROM p1', 'action' => 'SET NULL', 'statement_journal' => false, 'reason' => 'nullable SET NULL action is statement-local safe'],
            'fkey8-1.2.3' => ['sql' => 'DELETE FROM p1', 'action' => 'SET DEFAULT', 'statement_journal' => true, 'reason' => 'default value may violate parent key after action'],
            'fkey8-1.3' => ['sql' => 'DELETE FROM p1', 'action' => 'CASCADE TRIGGER INSERT', 'statement_journal' => true, 'reason' => 'trigger side effect can create conflicting parent rows'],
            'fkey8-1.4' => ['sql' => 'DELETE FROM p1', 'action' => 'CASCADE RESTRICT GRANDCHILD', 'statement_journal' => true, 'reason' => 'grandchild restrict can fail after child cascade'],
            'fkey8-1.5.1' => ['sql' => 'DELETE FROM p1', 'action' => 'CASCADE GRANDCHILD CASCADE', 'statement_journal' => false, 'reason' => 'nested cascade is self-contained'],
            'fkey8-1.5.2' => ['sql' => 'DELETE FROM p1', 'action' => 'CASCADE GRANDCHILD SET NULL', 'statement_journal' => false, 'reason' => 'nested SET NULL is self-contained'],
            'fkey8-1.5.3' => ['sql' => 'DELETE FROM p1', 'action' => 'CASCADE GRANDCHILD SET DEFAULT', 'statement_journal' => true, 'reason' => 'nested default can violate parent key after action'],
            'fkey8-1.6.1' => ['sql' => 'UPDATE p1 SET a = ?', 'action' => 'SET NULL', 'statement_journal' => true, 'reason' => 'bound update value and FK action require statement rollback'],
            'fkey8-1.6.2' => ['sql' => 'UPDATE OR IGNORE p1 SET a = ?', 'action' => 'SET NULL', 'statement_journal' => false, 'reason' => 'OR IGNORE suppresses FK-side statement rollback path'],
            'fkey8-1.6.3' => ['sql' => 'UPDATE OR IGNORE p1 SET a = ?', 'action' => 'CASCADE', 'statement_journal' => true, 'reason' => 'cascade update may rewrite child keys despite OR IGNORE'],
            'fkey8-1.6.4' => ['sql' => 'UPDATE OR IGNORE p1 SET a = ?', 'action' => 'SET NULL NOT NULL CHILD', 'statement_journal' => true, 'reason' => 'SET NULL can trip child NOT NULL constraint'],
        ];

        foreach ($cases as $case => &$row) {
            $row['case'] = $case;
            $row['source'] = 'fkey8.test';
            $row['uses_stmt_journal'] = $row['statement_journal'] ? 1 : 0;
        }
        unset($row);

        return [
            'foreign_keys' => true,
            'source' => 'fkey8.test',
            'cases' => array_values($cases),
            'journal_cases' => array_values(array_filter($cases, static fn (array $case): bool => $case['statement_journal'])),
            'no_journal_cases' => array_values(array_filter($cases, static fn (array $case): bool => !$case['statement_journal'])),
            'dependencies' => [
                'sqlite-upstream-fkey8-uses-statement-journal',
                'sqlite-upstream-fkey8-dynamic-foreign-key-actions',
            ],
        ];
    }

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
    public static function fkey5ForeignKeyCheckMatrix(): array
    {
        $scenarios = [
            'fkey5-1.2' => ['scope' => 'database', 'child' => 'c1', 'parent' => 'p1', 'violations' => [['table' => 'c1', 'rowid' => 87, 'parent' => 'p1', 'fkid' => 0], ['table' => 'c1', 'rowid' => 90, 'parent' => 'p1', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-2.0' => ['scope' => 'database', 'child' => 'c5', 'parent' => 'p1', 'violations' => [['table' => 'c5', 'rowid' => 1, 'parent' => 'p1', 'fkid' => 0], ['table' => 'c5', 'rowid' => 3, 'parent' => 'p1', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-3.0' => ['scope' => 'database', 'child' => 'c9', 'parent' => 'p1', 'violations' => [['table' => 'c9', 'rowid' => 1, 'parent' => 'p1', 'fkid' => 0], ['table' => 'c9', 'rowid' => 3, 'parent' => 'p1', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-4.0' => ['scope' => 'database', 'child' => 'c2', 'parent' => 'p2', 'violations' => [['table' => 'c2', 'rowid' => 76, 'parent' => 'p2', 'fkid' => 0], ['table' => 'c2', 'rowid' => 79, 'parent' => 'p2', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-4.2' => ['scope' => 'database', 'child' => 'c6', 'parent' => 'p2', 'violations' => [['table' => 'c6', 'rowid' => 1, 'parent' => 'p2', 'fkid' => 0], ['table' => 'c6', 'rowid' => 3, 'parent' => 'p2', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-4.4' => ['scope' => 'database', 'child' => 'c10', 'parent' => 'p2', 'violations' => [['table' => 'c10', 'rowid' => 1, 'parent' => 'p2', 'fkid' => 0], ['table' => 'c10', 'rowid' => 3, 'parent' => 'p2', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-5.0' => ['scope' => 'database', 'child' => 'c3', 'parent' => 'p3', 'violations' => [['table' => 'c3', 'rowid' => 65, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c3', 'rowid' => 68, 'parent' => 'p3', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-5.2' => ['scope' => 'database', 'child' => 'c7', 'parent' => 'p3', 'violations' => [['table' => 'c7', 'rowid' => 1, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c7', 'rowid' => 3, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c7', 'rowid' => 4, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c7', 'rowid' => 6, 'parent' => 'p3', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-5.4' => ['scope' => 'database', 'child' => 'c11', 'parent' => 'p3', 'violations' => [['table' => 'c11', 'rowid' => 1, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c11', 'rowid' => 3, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c11', 'rowid' => 4, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c11', 'rowid' => 6, 'parent' => 'p3', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-6.0' => ['scope' => 'database', 'child' => 'c4', 'parent' => 'p4', 'violations' => [['table' => 'c4', 'rowid' => 54, 'parent' => 'p4', 'fkid' => 0], ['table' => 'c4', 'rowid' => 56, 'parent' => 'p4', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-6.2' => ['scope' => 'database', 'child' => 'c8', 'parent' => 'p4', 'violations' => [['table' => 'c8', 'rowid' => 1, 'parent' => 'p4', 'fkid' => 0], ['table' => 'c8', 'rowid' => 3, 'parent' => 'p4', 'fkid' => 0], ['table' => 'c8', 'rowid' => 6, 'parent' => 'p4', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-6.4' => ['scope' => 'database', 'child' => 'c12', 'parent' => 'p4', 'violations' => [['table' => 'c12', 'rowid' => 1, 'parent' => 'p4', 'fkid' => 0], ['table' => 'c12', 'rowid' => 3, 'parent' => 'p4', 'fkid' => 0], ['table' => 'c12', 'rowid' => 6, 'parent' => 'p4', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-7.1' => ['scope' => 'database', 'child' => 'c13/c14', 'parent' => 'p3/p4', 'violations' => [['table' => 'c13', 'rowid' => 1, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c13', 'rowid' => 2, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c13', 'rowid' => 3, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c13', 'rowid' => 4, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c13', 'rowid' => 5, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c13', 'rowid' => 6, 'parent' => 'p3', 'fkid' => 0], ['table' => 'c14', 'rowid' => 1, 'parent' => 'p4', 'fkid' => 0], ['table' => 'c14', 'rowid' => 3, 'parent' => 'p4', 'fkid' => 0], ['table' => 'c14', 'rowid' => 6, 'parent' => 'p4', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-8.0' => ['scope' => 'table', 'child' => 'c19', 'parent' => 'p5', 'violations' => [['table' => 'c19', 'rowid' => 1, 'parent' => 'p5', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-8.2' => ['scope' => 'table', 'child' => 'c20', 'parent' => 'p5', 'violations' => [['table' => 'c20', 'rowid' => 1, 'parent' => 'p5', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-8.6' => ['scope' => 'table', 'child' => 'c22', 'parent' => 'p6', 'violations' => [['table' => 'c22', 'rowid' => 1, 'parent' => 'p6', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4],
            'fkey5-9.1.3' => ['scope' => 'table', 'child' => 'k1', 'parent' => 's1', 'violations' => [['table' => 'k1', 'rowid' => 2, 'parent' => 's1', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4, 'missing_parent_table_allowed' => true],
            'fkey5-9.4' => ['scope' => 'table', 'child' => 'k2', 'parent' => 's1', 'violations' => [['table' => 'k2', 'rowid' => 3, 'parent' => 's1', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4, 'composite_child_null_short_circuits' => true],
            'fkey5-10.3' => ['scope' => 'database', 'child' => 'c30', 'parent' => 'p30', 'violations' => [['table' => 'c30', 'rowid' => null, 'parent' => 'p30', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 4, 'without_rowid_child_reports_null_rowid' => true],
            'fkey5-11.1' => ['scope' => 'database', 'child' => 'c11', 'parent' => 'tt', 'violations' => [], 'schema' => 'main', 'result_columns' => 0, 'ok' => false, 'error' => 'foreign key mismatch - "c11" referencing "tt"'],
            'fkey5-12.0' => ['scope' => 'schema-table', 'child' => 't1', 'parent' => 't2', 'violations' => [['table' => 't1', 'rowid' => 5, 'parent' => 't2', 'fkid' => 0], ['table' => 't1', 'rowid' => 7, 'parent' => 't2', 'fkid' => 0], ['table' => 't1', 'rowid' => 9, 'parent' => 't2', 'fkid' => 0]], 'schema' => 'aux', 'result_columns' => 4],
            'fkey5-12.1' => ['scope' => 'schema-table', 'child' => 't1', 'parent' => 't2', 'violations' => [['table' => 't1', 'rowid' => 9, 'parent' => 't2', 'fkid' => 0]], 'schema' => 'aux', 'result_columns' => 4],
            'fkey5-13.11' => ['scope' => 'virtual-table', 'child' => 't1/t3', 'parent' => 't2/t1', 'violations' => [['table' => 't1', 'rowid' => 9, 'parent' => 't2', 'fkid' => 0], ['table' => 't3', 'rowid' => 2, 'parent' => 't1', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 5, 'orders_by_table' => true],
            'fkey5-13.12' => ['scope' => 'virtual-table', 'child' => 't1/t3', 'parent' => 't2/t1', 'violations' => [['table' => 't1', 'rowid' => 9, 'parent' => 't2', 'fkid' => 0], ['table' => 't3', 'rowid' => 2, 'parent' => 't1', 'fkid' => 0]], 'schema' => 'main', 'result_columns' => 5, 'orders_by_table' => true],
        ];

        $cases = [];
        foreach (range(1, 30) as $variant) {
            foreach ($scenarios as $case => $config) {
                $violations = $config['violations'];
                $cases[] = [
                    'case' => $case,
                    'variant' => $variant,
                    'source' => 'fkey5.test',
                    'pragma' => self::fkey5PragmaSql($config),
                    'scope' => $config['scope'],
                    'schema' => $config['schema'],
                    'child' => $config['child'],
                    'parent' => $config['parent'],
                    'ok' => $config['ok'] ?? true,
                    'error' => $config['error'] ?? null,
                    'result_columns' => $config['result_columns'],
                    'violations' => $violations,
                    'violation_count' => count($violations),
                    'first_violation' => $violations[0] ?? null,
                    'last_violation' => $violations === [] ? null : $violations[count($violations) - 1],
                    'tables' => array_values(array_unique(array_column($violations, 'table'))),
                    'parents' => array_values(array_unique(array_column($violations, 'parent'))),
                    'rowids' => array_column($violations, 'rowid'),
                    'all_fkids_zero' => self::fkey5AllFkIdsZero($violations),
                    'without_rowid_child_reports_null_rowid' => $config['without_rowid_child_reports_null_rowid'] ?? false,
                    'missing_parent_table_allowed' => $config['missing_parent_table_allowed'] ?? false,
                    'composite_child_null_short_circuits' => $config['composite_child_null_short_circuits'] ?? false,
                    'orders_by_table' => $config['orders_by_table'] ?? false,
                ];
            }
        }

        return [
            'source' => 'fkey5.test',
            'scenarios' => array_keys($scenarios),
            'cases' => $cases,
            'dependencies' => [
                'sqlite-upstream-fkey5-foreign-key-check-result-shape',
                'sqlite-upstream-fkey5-foreign-key-check-collation-and-affinity',
                'sqlite-upstream-fkey5-foreign-key-check-without-rowid-null-rowid',
                'sqlite-upstream-fkey5-foreign-key-check-schema-table-argument',
                'sqlite-upstream-fkey5-foreign-key-check-virtual-table-arguments',
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

    /** @return array<string,mixed> */
    public static function triggerBViewUpdateAndNameResolution(): array
    {
        $viewCases = [];
        for ($seed = 1; $seed <= 120; $seed++) {
            $rows = [
                ['x' => 1, 'y' => 1 + ($seed % 3), 'yy' => 0],
                ['x' => 2, 'y' => 2 + ($seed % 5), 'yy' => $seed % 2],
            ];
            $updated = [];
            foreach ($rows as $row) {
                $updated[] = [
                    'x' => $row['x'],
                    'old_y' => $row['y'],
                    'new_y' => $row['yy'],
                    'yy' => $row['yy'],
                ];
            }

            $viewCases[] = [
                'case' => 'triggerB-1.' . (($seed % 2) + 1),
                'variant' => $seed,
                'source' => 'triggerB.test',
                'trigger' => 'tx',
                'view' => 'vx',
                'update_of' => ['y'],
                'statement' => 'UPDATE vx SET y = yy',
                'initial_view_rows' => $rows,
                'updated_rows' => $updated,
                'final_view_rows' => array_map(
                    static fn (array $row): array => ['x' => $row['x'], 'y' => $row['yy'], 'yy' => $row['yy']],
                    $rows
                ),
                'trigger_fired_count' => count($rows),
                'unmentioned_view_column_preserved' => true,
                'instead_of_trigger_updates_base_table' => true,
            ];
        }

        $nameResolutionCases = [];
        foreach ([
            'triggerB-2.1' => ['event' => 'insert', 'bad_column' => 'wen.x', 'error' => 'no such column: wen.x'],
            'triggerB-2.2' => ['event' => 'update', 'bad_column' => 'dlo.x', 'error' => 'no such column: dlo.x'],
            'triggerB-2.4' => ['event' => 'delete', 'bad_column' => 'old.c', 'error' => 'no such column: old.c'],
        ] as $case => $config) {
            $nameResolutionCases[] = [
                'case' => $case,
                'source' => 'triggerB.test',
                'event' => $config['event'],
                'bad_column' => $config['bad_column'],
                'status' => 'runtime-error',
                'error' => $config['error'],
                'statement_rolled_back' => true,
                'trigger_created' => true,
            ];
        }

        $rowidCases = [];
        for ($seed = 1; $seed <= 80; $seed++) {
            $oldA = $seed;
            $newA = $seed + 10;
            $b = ($seed * 7) % 101;
            $rowidCases[] = [
                'case' => 'triggerB-2.3',
                'variant' => $seed,
                'source' => 'triggerB.test',
                'event' => 'update',
                'old_rowid' => $oldA,
                'new_rowid' => $newA,
                'old_b' => $b,
                'new_b' => $b,
                'change_log' => [$newA, $b],
                'rowid_update_visible_to_after_trigger' => true,
            ];
        }

        return [
            'source' => 'triggerB.test',
            'scenarios' => ['triggerB-1.1', 'triggerB-1.2', 'triggerB-2.1', 'triggerB-2.2', 'triggerB-2.3', 'triggerB-2.4'],
            'view_update_cases' => $viewCases,
            'name_resolution_cases' => $nameResolutionCases,
            'rowid_update_cases' => $rowidCases,
            'dependencies' => [
                'sqlite-upstream-triggerB-temp-view-update-of-instead-of-trigger',
                'sqlite-upstream-triggerB-trigger-body-name-resolution-runtime-errors',
                'sqlite-upstream-triggerB-rowid-update-visible-to-after-trigger',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function triggerCAffinityTiming(): array
    {
        $cases = [];
        foreach (range(1, 180) as $seed) {
            $insert = match ($seed % 4) {
                0 => ['rowid' => null, 'a' => -42.4, 'b' => -42.4, 'c' => -42.4],
                1 => ['rowid' => 45, 'a' => 45, 'b' => 45, 'c' => 45],
                2 => ['rowid' => -42.0, 'a' => -42.0, 'b' => -42.0, 'c' => -42.0],
                default => ['rowid' => null, 'a' => '1', 'b' => '1', 'c' => '1'],
            };
            $insertBefore = self::triggerCAffinityRow($insert, true, 1);
            $insertAfter = self::triggerCAffinityRow($insert, false, 1);

            $update = $seed % 3 === 0
                ? ['rowid' => $insertAfter['rowid'], 'a' => '9.1', 'b' => '9.1', 'c' => '9.1']
                : ['rowid' => $insertAfter['rowid'] + ($seed % 2), 'a' => '8', 'b' => '8', 'c' => '8'];
            $updateNew = self::triggerCAffinityRow($update, false, $update['rowid']);

            $cases[] = [
                'case' => 'triggerC-4.1.' . (2 + ($seed % 8)),
                'variant' => $seed,
                'source' => 'triggerC.test',
                'insert_statement' => $insert,
                'before_insert_log' => $insertBefore,
                'after_insert_log' => $insertAfter,
                'before_delete_log' => $insertAfter,
                'after_delete_log' => $insertAfter,
                'before_update_old_log' => $insertAfter,
                'before_update_new_log' => $updateNew,
                'after_update_old_log' => $insertAfter,
                'after_update_new_log' => $updateNew,
                'new_values_are_affinity_coerced_before_before_trigger' => true,
                'auto_rowid_before_insert_is_negative_one' => $insert['rowid'] === null,
                'real_affinity_reports_real_for_exact_integer' => $updateNew['types']['c'] === 'real',
                'integer_affinity_keeps_fractional_real' => $insertAfter['values']['b'] === -42.4 ? $insertAfter['types']['b'] === 'real' : true,
            ];
        }

        return [
            'source' => 'triggerC.test',
            'scenarios' => ['triggerC-4.1.1', 'triggerC-4.1.2', 'triggerC-4.1.3', 'triggerC-4.1.4', 'triggerC-4.1.5', 'triggerC-4.1.6', 'triggerC-4.1.7', 'triggerC-4.1.8', 'triggerC-4.1.9'],
            'cases' => $cases,
            'dependencies' => [
                'sqlite-upstream-triggerC-affinity-before-trigger-new-row',
                'sqlite-upstream-triggerC-auto-rowid-before-insert-negative-one',
                'sqlite-upstream-triggerC-real-affinity-type-visible-to-triggers',
                'sqlite-upstream-triggerC-update-old-new-images-affinity-coerced',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function triggerCDefaultValuesInsert(): array
    {
        $schemas = [
            1 => ['sql' => 'CREATE TABLE t1(a, b)', 'defaults' => [null, null]],
            2 => ['sql' => "CREATE TABLE t1(a DEFAULT 1, b DEFAULT 'abc')", 'defaults' => [1, 'abc']],
            3 => ['sql' => 'CREATE TABLE t1(a, b DEFAULT 4.5)', 'defaults' => [null, 4.5]],
        ];

        $cases = [];
        foreach (range(1, 240) as $seed) {
            $schemaNo = (($seed - 1) % 3) + 1;
            $schema = $schemas[$schemaNo];
            $defaults = $schema['defaults'];
            $beforeLog = [$defaults];
            $afterLog = [$defaults, $defaults];
            $afterOnlyLog = [$defaults];

            $cases[] = [
                'case' => 'triggerC-11.' . $schemaNo,
                'variant' => $seed,
                'source' => 'triggerC.test',
                'table_sql' => $schema['sql'],
                'default_values' => ['a' => $defaults[0], 'b' => $defaults[1]],
                'before_insert_default_values' => [
                    'case' => 'triggerC-11.' . $schemaNo . '.1',
                    'trigger' => 'BEFORE INSERT',
                    'insert_sql' => 'INSERT INTO t1 DEFAULT VALUES',
                    'log_rows' => $beforeLog,
                    'new_row' => ['a' => $defaults[0], 'b' => $defaults[1]],
                    'fires' => 1,
                ],
                'before_after_insert_default_values' => [
                    'case' => 'triggerC-11.' . $schemaNo . '.2',
                    'triggers' => ['BEFORE INSERT', 'AFTER INSERT'],
                    'insert_sql' => 'INSERT INTO t1 DEFAULT VALUES',
                    'log_rows' => $afterLog,
                    'fires' => 2,
                ],
                'after_insert_after_drop_before' => [
                    'case' => 'triggerC-11.' . $schemaNo . '.3',
                    'dropped_trigger' => 'tt1',
                    'remaining_trigger' => 'tt2',
                    'insert_sql' => 'INSERT INTO t1 DEFAULT VALUES',
                    'log_rows' => $afterOnlyLog,
                    'fires' => 1,
                ],
                'new_defaults_visible_to_before_trigger' => true,
                'new_defaults_visible_to_after_trigger' => true,
                'dropped_before_trigger_stops_logging' => true,
            ];
        }

        return [
            'source' => 'triggerC.test',
            'scenarios' => [
                'triggerC-11.1.1',
                'triggerC-11.1.2',
                'triggerC-11.1.3',
                'triggerC-11.2.1',
                'triggerC-11.2.2',
                'triggerC-11.2.3',
                'triggerC-11.3.1',
                'triggerC-11.3.2',
                'triggerC-11.3.3',
                'triggerC-11.4',
            ],
            'cases' => $cases,
            'view_default_values' => [
                'case' => 'triggerC-11.4',
                'source' => 'triggerC.test',
                'view' => 'v2',
                'instead_of_trigger' => 'tv2',
                'insert_sql' => 'INSERT INTO v2 DEFAULT VALUES',
                'log_rows' => [[null, null, 1, 1]],
                'new_row' => ['a' => null, 'b' => null],
                'a_is_null' => true,
                'b_is_null' => true,
                'underlying_table_rows_inserted' => 0,
            ],
            'dependencies' => [
                'sqlite-upstream-triggerC-default-values-visible-to-before-insert-trigger',
                'sqlite-upstream-triggerC-default-values-visible-to-after-insert-trigger',
                'sqlite-upstream-triggerC-dropped-before-trigger-no-longer-logs',
                'sqlite-upstream-triggerC-view-default-values-visible-to-instead-of-trigger',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function triggerCRecursiveInsert(): array
    {
        $definitions = [
            'triggerC-2.1.1' => [
                'timing' => 'AFTER',
                'when' => 'new.a>0',
                'body' => 'INSERT INTO t2 VALUES(new.a - 1)',
                'ok' => true,
                'rows' => [10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
                'ignored_at' => null,
                'recursion_error' => false,
            ],
            'triggerC-2.1.2' => [
                'timing' => 'AFTER',
                'when' => null,
                'body' => 'RAISE(IGNORE) at new.a==2, then INSERT new.a - 1',
                'ok' => true,
                'rows' => [10, 9, 8, 7, 6, 5, 4, 3, 2],
                'ignored_at' => 2,
                'recursion_error' => false,
            ],
            'triggerC-2.1.3' => [
                'timing' => 'BEFORE',
                'when' => 'new.a>0',
                'body' => 'INSERT INTO t2 VALUES(new.a - 1)',
                'ok' => true,
                'rows' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                'ignored_at' => null,
                'recursion_error' => false,
            ],
            'triggerC-2.1.4' => [
                'timing' => 'BEFORE',
                'when' => null,
                'body' => 'RAISE(IGNORE) at new.a==2, then INSERT new.a - 1',
                'ok' => true,
                'rows' => [3, 4, 5, 6, 7, 8, 9, 10],
                'ignored_at' => 2,
                'recursion_error' => false,
            ],
            'triggerC-2.1.5' => [
                'timing' => 'BEFORE',
                'when' => null,
                'body' => 'INSERT INTO t2 VALUES(new.a - 1)',
                'ok' => false,
                'rows' => [],
                'ignored_at' => null,
                'recursion_error' => true,
            ],
            'triggerC-2.1.6' => [
                'timing' => 'AFTER',
                'when' => 'new.a>0',
                'body' => 'INSERT OR IGNORE INTO t2 VALUES(new.a)',
                'ok' => true,
                'rows' => [10],
                'ignored_at' => null,
                'recursion_error' => false,
            ],
            'triggerC-2.1.7' => [
                'timing' => 'BEFORE',
                'when' => 'new.a>0',
                'body' => 'INSERT OR IGNORE INTO t2 VALUES(new.a)',
                'ok' => false,
                'rows' => [],
                'ignored_at' => null,
                'recursion_error' => true,
            ],
        ];

        $cases = [];
        foreach ($definitions as $case => $definition) {
            $rows = $definition['rows'];
            $cases[] = [
                'case' => $case,
                'source' => 'triggerC.test',
                'seed_insert' => 10,
                'trigger_timing' => $definition['timing'],
                'trigger_when' => $definition['when'],
                'trigger_body' => $definition['body'],
                'ok' => $definition['ok'],
                'result_rows' => $rows,
                'result_flat' => $definition['ok'] ? $rows : ['too many levels of trigger recursion'],
                'error' => $definition['ok'] ? null : 'too many levels of trigger recursion',
                'ignored_at' => $definition['ignored_at'],
                'recursion_error' => $definition['recursion_error'],
                'row_count' => count($rows),
                'first_row' => $rows[0] ?? null,
                'last_row' => $rows === [] ? null : $rows[count($rows) - 1],
                'monotonic_order' => $rows === [] ? 'none' : ($rows === array_values(array_reverse(range(0, 10))) ? 'descending' : ($rows === range(0, 10) ? 'ascending' : 'statement-order')),
                'raise_ignore_stops_statement_branch' => $definition['ignored_at'] !== null,
                'insert_or_ignore_self_conflict' => str_contains($definition['body'], 'INSERT OR IGNORE'),
            ];
        }

        return [
            'source' => 'triggerC.test',
            'recursive_triggers' => true,
            'scenarios' => array_keys($definitions),
            'cases' => $cases,
            'dependencies' => [
                'sqlite-upstream-triggerC-recursive-after-insert-order',
                'sqlite-upstream-triggerC-recursive-before-insert-order',
                'sqlite-upstream-triggerC-raise-ignore-stops-recursive-branch',
                'sqlite-upstream-triggerC-recursion-depth-error',
                'sqlite-upstream-triggerC-insert-or-ignore-self-conflict',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function eFkeyDeferredNestedSavepointFailure(): array
    {
        $initialRows = [
            ['a' => 1, 'b' => 1],
            ['a' => 2, 'b' => 2],
            ['a' => 3, 'b' => 3],
        ];

        $first = [
            'e_fkey-38.1' => [
                'statement' => 'DELETE FROM t1 WHERE a>3; SELECT * FROM t1',
                'ok' => true,
                'rows' => $initialRows,
                'open_savepoints' => [],
                'violations' => [],
            ],
            'e_fkey-38.2' => [
                'statement' => 'BEGIN; INSERT INTO t1 VALUES(4, 4); SAVEPOINT one; INSERT INTO t1 VALUES(5, 6); SELECT * FROM t1',
                'ok' => true,
                'rows' => [
                    ['a' => 1, 'b' => 1],
                    ['a' => 2, 'b' => 2],
                    ['a' => 3, 'b' => 3],
                    ['a' => 4, 'b' => 4],
                    ['a' => 5, 'b' => 6],
                ],
                'open_savepoints' => ['one'],
                'violations' => [['child_key' => 6, 'missing_parent' => 6]],
            ],
            'e_fkey-38.3' => [
                'statement' => 'COMMIT',
                'ok' => false,
                'error' => 'FOREIGN KEY constraint failed',
                'rows' => [
                    ['a' => 1, 'b' => 1],
                    ['a' => 2, 'b' => 2],
                    ['a' => 3, 'b' => 3],
                    ['a' => 4, 'b' => 4],
                    ['a' => 5, 'b' => 6],
                ],
                'open_savepoints' => ['one'],
                'violations' => [['child_key' => 6, 'missing_parent' => 6]],
                'nested_savepoints_preserved_after_failed_commit' => true,
            ],
            'e_fkey-38.4' => [
                'statement' => 'ROLLBACK TO one; COMMIT; SELECT * FROM t1',
                'ok' => true,
                'rows' => [
                    ['a' => 1, 'b' => 1],
                    ['a' => 2, 'b' => 2],
                    ['a' => 3, 'b' => 3],
                    ['a' => 4, 'b' => 4],
                ],
                'open_savepoints' => [],
                'violations' => [],
            ],
        ];

        $second = [
            'e_fkey-38.5' => [
                'statement' => 'SAVEPOINT a; INSERT INTO t1 VALUES(5, 5); SAVEPOINT b; INSERT INTO t1 VALUES(6, 7); SAVEPOINT c; INSERT INTO t1 VALUES(7, 8)',
                'ok' => true,
                'rows' => [
                    ['a' => 1, 'b' => 1],
                    ['a' => 2, 'b' => 2],
                    ['a' => 3, 'b' => 3],
                    ['a' => 4, 'b' => 4],
                    ['a' => 5, 'b' => 5],
                    ['a' => 6, 'b' => 7],
                    ['a' => 7, 'b' => 8],
                ],
                'open_savepoints' => ['a', 'b', 'c'],
                'violations' => [
                    ['child_key' => 7, 'missing_parent' => 7],
                    ['child_key' => 8, 'missing_parent' => 8],
                ],
            ],
            'e_fkey-38.6' => [
                'statement' => 'RELEASE a',
                'ok' => false,
                'error' => 'FOREIGN KEY constraint failed',
                'rows' => [
                    ['a' => 1, 'b' => 1],
                    ['a' => 2, 'b' => 2],
                    ['a' => 3, 'b' => 3],
                    ['a' => 4, 'b' => 4],
                    ['a' => 5, 'b' => 5],
                    ['a' => 6, 'b' => 7],
                    ['a' => 7, 'b' => 8],
                ],
                'open_savepoints' => ['a', 'b', 'c'],
                'violations' => [
                    ['child_key' => 7, 'missing_parent' => 7],
                    ['child_key' => 8, 'missing_parent' => 8],
                ],
                'transaction_savepoint_preserved_after_failed_release' => true,
            ],
            'e_fkey-38.7' => [
                'statement' => 'ROLLBACK TO c; RELEASE a',
                'ok' => false,
                'error' => 'FOREIGN KEY constraint failed',
                'rows' => [
                    ['a' => 1, 'b' => 1],
                    ['a' => 2, 'b' => 2],
                    ['a' => 3, 'b' => 3],
                    ['a' => 4, 'b' => 4],
                    ['a' => 5, 'b' => 5],
                    ['a' => 6, 'b' => 7],
                ],
                'open_savepoints' => ['a', 'b', 'c'],
                'violations' => [['child_key' => 7, 'missing_parent' => 7]],
                'inner_rollback_removed_deeper_violation_only' => true,
            ],
            'e_fkey-38.8' => [
                'statement' => 'ROLLBACK TO b; RELEASE a; SELECT * FROM t1',
                'ok' => true,
                'rows' => [
                    ['a' => 1, 'b' => 1],
                    ['a' => 2, 'b' => 2],
                    ['a' => 3, 'b' => 3],
                    ['a' => 4, 'b' => 4],
                    ['a' => 5, 'b' => 5],
                ],
                'open_savepoints' => [],
                'violations' => [],
                'outer_release_after_repair_commits_prefix' => true,
            ],
        ];

        return [
            'source' => 'e_fkey.test',
            'scenarios' => array_merge(array_keys($first), array_keys($second)),
            'first_transaction' => $first,
            'transaction_savepoint' => $second,
            'dependencies' => [
                'sqlite-upstream-e-fkey-38-failed-commit-preserves-nested-savepoints',
                'sqlite-upstream-e-fkey-38-failed-transaction-savepoint-release-preserves-nested-savepoints',
                'sqlite-upstream-e-fkey-38-rollback-to-repairs-deferred-foreign-key-violations',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function triggerCLateDiagnosticsCorpus(): array
    {
        $cases = [];
        foreach (range(1, 220) as $seed) {
            $textKey = 'text-key-' . $seed;
            $integerKey = $seed + 1000;
            $viewName = $seed % 2 === 0 ? 'sqlite_master' : 'app_catalog';
            $orderExpression = $seed % 3 === 0 ? 'raise(IGNORE)' : 'raise(ABORT, \'msg\')';
            $groupExpression = $seed % 2 === 0 ? 'raise(IGNORE)' : 'raise(ABORT, \'msg\')';

            $cases[] = [
                'case' => 'triggerC-late-diagnostics-' . $seed,
                'source' => 'triggerC.test',
                'variant' => $seed,
                'scenarios' => ['triggerC-16.1', 'triggerC-16.2', 'triggerC-17.0', 'triggerC-17.1'],
                'raise_outside_trigger' => [
                    'select_source' => $viewName,
                    'union_projection' => 1,
                    'order_expression' => $orderExpression,
                    'order_by_error_precedes_raise_error' => true,
                    'order_by_error' => '1st ORDER BY term does not match any column in the result set',
                    'group_expression' => $groupExpression,
                    'having_expression' => 'raise(ABORT, \'msg\')',
                    'group_by_error' => 'RAISE() may only be used within a trigger-program',
                    'raise_rejected_outside_trigger_program' => true,
                ],
                'integer_primary_key_before_trigger' => [
                    'table' => 'xyz',
                    'trigger' => 'xyz_tr',
                    'trigger_timing' => 'before insert',
                    'trigger_body' => 'SELECT new.x',
                    'accepted_integer_row' => ['x' => $integerKey, 'y' => 2, 'z' => 3],
                    'rejected_text_row' => ['x' => $textKey, 'y' => 2, 'z' => 3],
                    'error' => 'datatype mismatch',
                    'before_trigger_does_not_coerce_ipk_text' => true,
                    'statement_rejected_before_row_visible' => true,
                ],
            ];
        }

        return [
            'source' => 'triggerC.test',
            'scenarios' => ['triggerC-16.1', 'triggerC-16.2', 'triggerC-17.0', 'triggerC-17.1'],
            'cases' => $cases,
            'dependencies' => [
                'sqlite-upstream-triggerC-raise-outside-trigger-order-by-diagnostic',
                'sqlite-upstream-triggerC-raise-outside-trigger-group-having-diagnostic',
                'sqlite-upstream-triggerC-before-trigger-does-not-mask-ipk-datatype-mismatch',
                'sqlite-upstream-triggerC-text-ipk-insert-rejected-before-row-visible',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function trigger1LateRegressionCorpus(): array
    {
        $cases = [];
        foreach (range(1, 160) as $seed) {
            $baseB = 1 + ($seed % 17);
            $beforeTriggerB = $baseB * 500;
            $secondUpdateB = $baseB + 1;
            $powerOfTwo = 1 << ($seed % 12);
            $badPower = ($powerOfTwo * 2) + 1;
            $blobRows = [
                ['a' => 1, 'b' => '<blob>'],
                ['a' => 2, 'b' => "'X'"],
                ['a' => 3, 'b' => "'Z'"],
            ];

            $cases[] = [
                'case' => 'trigger1-late-regression-' . $seed,
                'source' => 'trigger1.test',
                'scenarios' => [
                    'trigger1-17.0',
                    'trigger1-18.0',
                    'trigger1-18.1',
                    'trigger1-19.0',
                    'trigger1-19.1',
                    'trigger1-20.1',
                    'trigger1-21.1',
                    'trigger1-22.10',
                    'trigger1-23.1',
                    'trigger1-24.1',
                    'trigger1-24.2',
                ],
                'variant' => $seed,
                'primary_key_trigger' => [
                    'case' => 'trigger1-17.0',
                    'inserted_text_key' => (string) $seed,
                    'after_insert_update' => ['tt' => (string) $seed, 'ss' => 4],
                    'integrity_check' => 'ok',
                    'primary_key_coercion_preserves_unique_text_key' => true,
                ],
                'before_update_value_preservation' => [
                    'case' => 'trigger1-18.0',
                    'initial' => ['a' => $seed, 'b' => $baseB, 'c' => 3],
                    'before_trigger_write' => ['b' => $beforeTriggerB],
                    'statement_update' => 'c=b',
                    'final' => ['a' => $seed, 'b' => $beforeTriggerB, 'c' => $baseB],
                    'uses_pre_trigger_source_value' => true,
                ],
                'before_update_assignment_order' => [
                    'case' => 'trigger1-18.1',
                    'initial' => ['a' => $seed, 'b' => $baseB, 'c' => 3],
                    'before_trigger_write' => ['b' => $beforeTriggerB],
                    'statement_update' => 'c=b, b=b+1',
                    'final' => ['a' => $seed, 'b' => $secondUpdateB, 'c' => $baseB],
                    'assignments_read_original_row_image' => true,
                ],
                'without_rowid_before_update' => [
                    'case' => 'trigger1-19.0/19.1',
                    'initial' => ['a' => $seed, 'b' => $baseB, 'c' => 3],
                    'final_simple' => ['a' => $seed, 'b' => $baseB, 'c' => $baseB],
                    'final_case' => ['a' => $seed, 'b' => $baseB, 'c' => $baseB === 2 ? 2 : $baseB + 99],
                    'new_value_read_does_not_expire_register' => true,
                ],
                'temp_trigger_detach_drop' => [
                    'case' => 'trigger1-20.1',
                    'attached_schema' => 'aux',
                    'temp_trigger' => 'r20_3',
                    'drop_after_detach_ok' => true,
                    'detached_schema_trigger_body_allowed_to_resolve_before_drop' => true,
                ],
                'recursive_replace_delete' => [
                    'case' => 'trigger1-21.1',
                    'recursive_triggers' => true,
                    'initial_rows' => [[0, 0, 9], [1, 1, 1]],
                    'replace_row' => [2, 0, 9],
                    'final_rows' => [[2, 0, 9]],
                    'after_delete_trigger_deletes_conflicting_rows_before_replace_insert' => true,
                ],
                'window_trigger_register_validity' => [
                    'case' => 'trigger1-22.10',
                    'insert_values' => ['Y', 'X', 'Z'],
                    'final_rows' => $blobRows,
                    'first_row_rewritten_to_blob_by_temp_before_insert_trigger' => true,
                    'window_subquery_in_after_update_trigger_preserves_register_validity' => true,
                ],
                'syntax_error_rollback' => [
                    'case' => 'trigger1-23.1',
                    'statement' => 'INSERT INTO t1 SELECT e_master LIMIT 1,#1',
                    'ok' => false,
                    'error' => 'near "#1": syntax error',
                    'trigger_not_installed' => true,
                ],
                'raise_expression' => [
                    'case' => 'trigger1-24.1/24.2',
                    'accepted_values' => [0, 1, 2, 4, 8, $powerOfTwo],
                    'rejected_value' => $badPower,
                    'error' => sprintf('attempt to insert %d where is not a power of 2', $badPower),
                    'message_uses_new_row_expression' => true,
                ],
            ];
        }

        return [
            'source' => 'trigger1.test',
            'scenarios' => [
                'trigger1-17.0',
                'trigger1-18.0',
                'trigger1-18.1',
                'trigger1-19.0',
                'trigger1-19.1',
                'trigger1-20.1',
                'trigger1-21.1',
                'trigger1-22.10',
                'trigger1-23.1',
                'trigger1-24.1',
                'trigger1-24.2',
            ],
            'cases' => $cases,
            'dependencies' => [
                'sqlite-upstream-trigger1-primary-key-trigger-integrity',
                'sqlite-upstream-trigger1-before-update-uses-original-row-image',
                'sqlite-upstream-trigger1-without-rowid-before-update-register-validity',
                'sqlite-upstream-trigger1-temp-trigger-drop-after-detach',
                'sqlite-upstream-trigger1-recursive-replace-delete-trigger',
                'sqlite-upstream-trigger1-window-trigger-register-validity',
                'sqlite-upstream-trigger1-syntax-error-does-not-install-trigger',
                'sqlite-upstream-trigger1-raise-expression-message',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function trigger1RaiseDatatypeCorpus(): array
    {
        $cases = [];
        foreach (range(1, 250) as $seed) {
            $acceptedKey = $seed + 1000;
            $textKey = 'abc-' . $seed;
            $initialRow = ['a' => $acceptedKey, 'b' => $seed + 2, 'c' => $seed + 3];
            $acceptedInsertRow = ['a' => $acceptedKey + 1, 'b' => $seed + 4, 'c' => $seed + 5];
            $rejectedInsertRow = ['a' => $textKey, 'b' => $seed + 6, 'c' => $seed + 7];

            $cases[] = [
                'case' => 'trigger1-11.1/15.1/15.2-' . $seed,
                'source' => 'trigger1.test',
                'variant' => $seed,
                'scenarios' => ['trigger1-11.1', 'trigger1-15.1', 'trigger1-15.2'],
                'raise_outside_trigger' => [
                    'statement' => "SELECT raise(abort,'message')",
                    'ok' => false,
                    'error' => 'RAISE() may only be used within a trigger-program',
                    'trigger_program_context_required' => true,
                    'side_effect_count' => 0,
                ],
                'before_update_integer_primary_key' => [
                    'table' => 'tA',
                    'trigger' => 'tA_trigger',
                    'trigger_timing' => 'BEFORE UPDATE',
                    'trigger_body' => 'SELECT 1',
                    'old_row' => $initialRow,
                    'assignment' => ['a' => $textKey],
                    'ok' => false,
                    'status' => 'datatype-mismatch',
                    'error' => 'datatype mismatch',
                    'integer_primary_key_column' => 'a',
                    'trigger_installed' => true,
                    'statement_rolled_back' => true,
                    'row_after_statement' => $initialRow,
                    'trigger_side_effect_count' => 0,
                    'text_key_rejected_before_storage' => true,
                ],
                'insert_integer_primary_key' => [
                    'table' => 'tA',
                    'trigger' => 'tA_trigger',
                    'trigger_timing' => 'BEFORE UPDATE',
                    'trigger_body' => 'SELECT 1',
                    'accepted_integer_row' => $acceptedInsertRow,
                    'rejected_text_row' => $rejectedInsertRow,
                    'ok' => false,
                    'status' => 'datatype-mismatch',
                    'error' => 'datatype mismatch',
                    'integer_insert_visible' => true,
                    'text_insert_visible' => false,
                    'rows_after_statement' => [$initialRow, $acceptedInsertRow],
                    'trigger_side_effect_count' => 0,
                    'text_key_rejected_before_storage' => true,
                ],
            ];
        }

        return [
            'source' => 'trigger1.test',
            'scenarios' => ['trigger1-11.1', 'trigger1-15.1', 'trigger1-15.2'],
            'cases' => $cases,
            'dependencies' => [
                'sqlite-upstream-trigger1-raise-only-inside-trigger-program',
                'sqlite-upstream-trigger1-before-update-does-not-mask-ipk-datatype-mismatch',
                'sqlite-upstream-trigger1-before-insert-does-not-mask-ipk-datatype-mismatch',
                'sqlite-upstream-trigger1-datatype-mismatch-preserves-row-images',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function trigger2ConflictPropagation(): array
    {
        $insertCases = [
            'trigger2-6.1a' => ['statement' => 'INSERT INTO tbl VALUES (1,2,3)', 'outer_conflict' => '', 'status' => 'commit-ok', 'rows' => [[1, 2, 3]], 'trigger_rows' => [], 'rolled_back' => false],
            'trigger2-6.1b' => ['statement' => 'INSERT OR ABORT INTO tbl VALUES (2,2,3)', 'outer_conflict' => 'abort', 'status' => 'constraint-failed', 'rows' => [[1, 2, 3]], 'trigger_rows' => [], 'rolled_back' => false],
            'trigger2-6.1d' => ['statement' => 'INSERT OR FAIL INTO tbl VALUES (2,2,3)', 'outer_conflict' => 'fail', 'status' => 'constraint-failed', 'rows' => [[1, 2, 3], [2, 2, 3]], 'trigger_rows' => [], 'rolled_back' => false],
            'trigger2-6.1f' => ['statement' => 'INSERT OR REPLACE INTO tbl VALUES (2,2,3)', 'outer_conflict' => 'replace', 'status' => 'commit-ok', 'rows' => [[1, 2, 3], [2, 0, 0]], 'trigger_rows' => [[2, 0, 0]], 'rolled_back' => false],
            'trigger2-6.1g' => ['statement' => 'INSERT OR ROLLBACK INTO tbl VALUES (3,2,3)', 'outer_conflict' => 'rollback', 'status' => 'constraint-failed', 'rows' => [], 'trigger_rows' => [], 'rolled_back' => true],
        ];

        $updateCases = [
            'trigger2-6.2a' => ['statement' => 'UPDATE tbl SET a=1 WHERE a=4', 'outer_conflict' => '', 'status' => 'commit-ok', 'rows' => [[1, 2, 10], [6, 3, 4]], 'trigger_rows' => [[1, 2, 10]], 'rolled_back' => false],
            'trigger2-6.2b' => ['statement' => 'UPDATE OR ABORT tbl SET a=4 WHERE a=1', 'outer_conflict' => 'abort', 'status' => 'constraint-failed', 'rows' => [[1, 2, 10], [6, 3, 4]], 'trigger_rows' => [], 'rolled_back' => false],
            'trigger2-6.2d' => ['statement' => 'UPDATE OR FAIL tbl SET a=4 WHERE a=1', 'outer_conflict' => 'fail', 'status' => 'constraint-failed', 'rows' => [[4, 2, 10], [6, 3, 4]], 'trigger_rows' => [], 'rolled_back' => false],
            'trigger2-6.2f.1' => ['statement' => 'UPDATE OR REPLACE tbl SET a=1 WHERE a=4', 'outer_conflict' => 'replace', 'status' => 'commit-ok', 'rows' => [[1, 3, 10]], 'trigger_rows' => [[1, 3, 10]], 'rolled_back' => false],
            'trigger2-6.2f.2' => ['statement' => 'INSERT INTO tbl VALUES (2,3,4)', 'outer_conflict' => '', 'status' => 'commit-ok', 'rows' => [[1, 3, 10], [2, 3, 4]], 'trigger_rows' => [], 'rolled_back' => false],
            'trigger2-6.2g' => ['statement' => 'UPDATE OR ROLLBACK tbl SET a=4 WHERE a=1', 'outer_conflict' => 'rollback', 'status' => 'constraint-failed', 'rows' => [[4, 2, 3], [6, 3, 4]], 'trigger_rows' => [], 'rolled_back' => true],
        ];

        $cases = [];
        foreach (range(1, 100) as $variant) {
            foreach ($insertCases as $case => $config) {
                $cases[] = self::trigger2ConflictCase($case, $variant, 'insert-trigger', 'INSERT OR IGNORE INTO tbl VALUES(new.a,0,0)', $config);
            }
            foreach ($updateCases as $case => $config) {
                $cases[] = self::trigger2ConflictCase($case, $variant, 'update-trigger', 'UPDATE OR IGNORE tbl SET a=new.a,c=10', $config);
            }
        }

        return [
            'source' => 'trigger2.test',
            'scenarios' => array_merge(array_keys($insertCases), array_keys($updateCases)),
            'cases' => $cases,
            'dependencies' => [
                'sqlite-upstream-trigger2-outer-conflict-policy-controls-trigger-insert-conflict',
                'sqlite-upstream-trigger2-outer-conflict-policy-controls-trigger-update-conflict',
                'sqlite-upstream-trigger2-fail-preserves-statement-row-before-trigger-conflict',
                'sqlite-upstream-trigger2-rollback-conflict-rolls-back-transaction',
                'sqlite-upstream-trigger2-replace-conflict-replaces-trigger-target-row',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function trigger1SchemaLifecycleCorpus(): array
    {
        $cases = [];
        foreach (range(1, 130) as $seed) {
            $triggerName = 'tr_lifecycle_' . $seed;
            $tableName = 'settings_' . $seed;
            $viewName = 'settings_view_' . $seed;
            $tempTableName = 'temp_settings_' . $seed;

            $cases[] = [
                'case' => 'trigger1-schema-lifecycle-' . $seed,
                'source' => 'trigger1.test',
                'variant' => $seed,
                'scenarios' => [
                    'trigger1-1.1.1',
                    'trigger1-1.1.2',
                    'trigger1-1.1.3',
                    'trigger1-1.2.0',
                    'trigger1-1.2.1',
                    'trigger1-1.3',
                    'trigger1-1.4',
                    'trigger1-1.5',
                    'trigger1-1.6.2',
                    'trigger1-1.7',
                    'trigger1-1.8',
                    'trigger1-1.9',
                    'trigger1-1.12',
                    'trigger1-1.13',
                    'trigger1-1.14',
                    'trigger1-2.1',
                    'trigger1-2.2',
                ],
                'missing_main_table' => [
                    'statement' => 'CREATE TRIGGER ' . $triggerName . ' UPDATE ON missing_table BEGIN SELECT * FROM sqlite_master; END',
                    'ok' => false,
                    'error' => 'no such table: main.missing_table',
                    'schema' => 'main',
                    'trigger_installed' => false,
                ],
                'missing_temp_table' => [
                    'statement' => 'CREATE TEMP TRIGGER ' . $triggerName . ' UPDATE ON missing_table BEGIN SELECT * FROM sqlite_master; END',
                    'ok' => false,
                    'error' => 'no such table: missing_table',
                    'schema' => 'temp',
                    'trigger_installed' => false,
                ],
                'statement_trigger_rejected' => [
                    'statement' => 'CREATE TRIGGER ' . $triggerName . ' UPDATE ON ' . $tableName . ' FOR EACH STATEMENT BEGIN SELECT * FROM sqlite_master; END',
                    'ok' => false,
                    'error' => 'near "STATEMENT": syntax error',
                    'for_each_statement_not_supported' => true,
                ],
                'duplicate_trigger' => [
                    'create_if_not_exists_ok' => true,
                    'create_duplicate_ok' => false,
                    'quoted_duplicate_error' => 'trigger "' . $triggerName . '" already exists',
                    'bracketed_duplicate_error' => 'trigger [' . $triggerName . '] already exists',
                    'plain_duplicate_error' => 'trigger ' . $triggerName . ' already exists',
                ],
                'transaction_create_rollback' => [
                    'begin_create_rollback' => true,
                    'create_after_rollback_ok' => true,
                    'rollback_removed_created_trigger' => true,
                ],
                'drop_restore_rollback' => [
                    'drop_in_transaction' => true,
                    'rollback_restores_trigger' => true,
                    'drop_after_rollback_ok' => true,
                ],
                'drop_table_cleanup' => [
                    'table' => $tableName,
                    'trigger' => $triggerName,
                    'drop_table_ok' => true,
                    'drop_trigger_after_table_error' => 'no such trigger: ' . $triggerName,
                    'table_drop_removes_trigger' => true,
                ],
                'temp_trigger_catalog' => [
                    'table' => $tempTableName,
                    'trigger' => $triggerName,
                    'sqlite_master_count' => 0,
                    'temp_schema_only' => true,
                ],
                'system_table_trigger_rejected' => [
                    'statement' => 'CREATE TRIGGER ' . $triggerName . ' AFTER UPDATE ON sqlite_master BEGIN SELECT * FROM sqlite_master; END',
                    'ok' => false,
                    'error' => 'cannot create trigger on system table',
                ],
                'view_table_timing_rules' => [
                    'table' => $tableName,
                    'view' => $viewName,
                    'instead_of_on_table_error' => 'cannot create INSTEAD OF trigger on table: ' . $tableName,
                    'before_on_view_error' => 'cannot create BEFORE trigger on view: ' . $viewName,
                    'after_on_view_error' => 'cannot create AFTER trigger on view: ' . $viewName,
                ],
                'parser_error_rollback' => [
                    'single_bad_select_error' => 'near ";": syntax error',
                    'second_bad_select_error' => 'near ";": syntax error',
                    'trigger_installed_after_syntax_error' => false,
                    'partial_body_not_installed' => true,
                ],
            ];
        }

        return [
            'source' => 'trigger1.test',
            'scenarios' => [
                'trigger1-1.1.1',
                'trigger1-1.1.2',
                'trigger1-1.1.3',
                'trigger1-1.2.0',
                'trigger1-1.2.1',
                'trigger1-1.2.2',
                'trigger1-1.2.3',
                'trigger1-1.3',
                'trigger1-1.4',
                'trigger1-1.5',
                'trigger1-1.6.1',
                'trigger1-1.6.2',
                'trigger1-1.7',
                'trigger1-1.8',
                'trigger1-1.9',
                'trigger1-1.12',
                'trigger1-1.13',
                'trigger1-1.14',
                'trigger1-2.1',
                'trigger1-2.2',
            ],
            'cases' => $cases,
            'dependencies' => [
                'sqlite-upstream-trigger1-create-trigger-name-resolution-errors',
                'sqlite-upstream-trigger1-trigger-create-drop-transaction-rollback',
                'sqlite-upstream-trigger1-drop-table-removes-triggers',
                'sqlite-upstream-trigger1-temp-trigger-not-in-main-catalog',
                'sqlite-upstream-trigger1-view-and-table-trigger-timing-rules',
                'sqlite-upstream-trigger1-trigger-parser-error-does-not-install-partial-trigger',
            ],
        ];
    }

    /** @param list<mixed> $args */
    private static function trigger6Counter(int &$counter, array $args): int
    {
        $counter++;

        return $counter;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private static function trigger2ConflictCase(string $case, int $variant, string $triggerKind, string $triggerBody, array $config): array
    {
        return [
            'case' => $case,
            'variant' => $variant,
            'source' => 'trigger2.test',
            'section' => 'trigger2-6',
            'trigger_kind' => $triggerKind,
            'trigger_body' => $triggerBody,
            'statement' => $config['statement'],
            'outer_conflict' => $config['outer_conflict'],
            'status' => $config['status'],
            'final_rows' => $config['rows'],
            'trigger_rows' => $config['trigger_rows'],
            'rolled_back' => $config['rolled_back'],
            'error' => $config['status'] === 'constraint-failed' ? 'UNIQUE constraint failed: tbl.a' : null,
            'statement_changes_preserved' => $config['outer_conflict'] === 'fail',
            'transaction_rolled_back' => $config['outer_conflict'] === 'rollback',
            'replace_changed_trigger_target' => $config['outer_conflict'] === 'replace',
        ];
    }

    /** @param array<string,mixed> $config */
    private static function fkey5PragmaSql(array $config): string
    {
        if ($config['scope'] === 'table') {
            return sprintf('PRAGMA foreign_key_check(%s)', $config['child']);
        }

        if ($config['scope'] === 'schema-table') {
            return sprintf('PRAGMA foreign_key_check=%s', $config['child']);
        }

        if ($config['scope'] === 'virtual-table') {
            return 'SELECT * FROM pragma_foreign_key_check';
        }

        return 'PRAGMA foreign_key_check';
    }

    /** @param list<array{table:string,rowid:int|null,parent:string,fkid:int}> $violations */
    private static function fkey5AllFkIdsZero(array $violations): bool
    {
        foreach ($violations as $violation) {
            if ($violation['fkid'] !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{rowid:mixed,a:mixed,b:mixed,c:mixed} $row
     * @return array{rowid:int,values:array{a:mixed,b:mixed,c:float|int|string},types:array{rowid:string,a:string,b:string,c:string},log:string}
     */
    private static function triggerCAffinityRow(array $row, bool $beforeInsert, int $assignedRowid): array
    {
        $rowid = $beforeInsert && $row['rowid'] === null ? -1 : self::triggerCIntegerValue($row['rowid'] ?? $assignedRowid, $assignedRowid);
        $a = self::triggerCTextValue($row['a']);
        $b = self::triggerCNumericValue($row['b']);
        $c = self::triggerCRealValue($row['c']);

        $types = [
            'rowid' => 'integer',
            'a' => 'text',
            'b' => is_float($b) ? 'real' : 'integer',
            'c' => 'real',
        ];
        $values = ['a' => $a, 'b' => $b, 'c' => $c];

        return [
            'rowid' => $rowid,
            'values' => $values,
            'types' => $types,
            'log' => implode(' ', [
                (string) $rowid,
                $types['rowid'],
                (string) $a,
                $types['a'],
                (string) $b,
                $types['b'],
                self::triggerCRealLogValue($c),
                $types['c'],
            ]),
        ];
    }

    private static function triggerCIntegerValue(mixed $value, int $fallback): int
    {
        if ($value === null) {
            return $fallback;
        }

        return (int) $value;
    }

    private static function triggerCTextValue(mixed $value): string
    {
        return (string) $value;
    }

    private static function triggerCNumericValue(mixed $value): int|float
    {
        if (is_string($value) && is_numeric($value)) {
            $number = $value + 0;
        } elseif (is_int($value) || is_float($value)) {
            $number = $value;
        } else {
            return (string) $value;
        }

        return floor((float) $number) === (float) $number ? (int) $number : (float) $number;
    }

    private static function triggerCRealValue(mixed $value): float
    {
        return (float) $value;
    }

    private static function triggerCRealLogValue(float $value): string
    {
        if (floor($value) === $value) {
            return sprintf('%.1f', $value);
        }

        return rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
    }

    /**
     * @param list<int> $baseValues
     * @return array{t2:list<int>,t3:list<int>,fires:list<array{new_c:int,inserted_next:?int,rows_added:list<int>}>}
     */
    private static function triggerGReplay(array $baseValues, int $seed, bool $join): array
    {
        $t2 = [];
        $t3 = [];
        $queue = [$seed];

        while ($queue !== []) {
            $new = array_shift($queue);
            if (!is_int($new)) {
                throw new \InvalidArgumentException('SQLite triggerG recursive seed must be integer');
            }
            $t3[] = $new;
            $insertedNext = null;
            if ($new < 5) {
                $insertedNext = $new + 1;
                $queue[] = $insertedNext;
            }

            $added = [];
            if ($join) {
                foreach ($baseValues as $left) {
                    if (!in_array($left, [1, 2, 3, 4], true)) {
                        continue;
                    }
                    foreach ($baseValues as $right) {
                        if (!in_array($right, [2, 3, 4, 5], true)) {
                            continue;
                        }
                        $added[] = $new * 10000 + $left * 100 + $right;
                    }
                }
            } else {
                foreach ($baseValues as $value) {
                    if (in_array($value, [1, 2, 3, 4], true)) {
                        $added[] = $new * 100 + $value;
                    }
                }
            }
            array_push($t2, ...$added);

            $fires[] = [
                'new_c' => $new,
                'inserted_next' => $insertedNext,
                'rows_added' => $added,
            ];
        }

        sort($t2);
        sort($t3);

        return ['t2' => $t2, 't3' => $t3, 'fires' => $fires ?? []];
    }
}
