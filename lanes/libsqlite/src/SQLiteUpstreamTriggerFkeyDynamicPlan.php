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

    /** @param list<mixed> $args */
    private static function trigger6Counter(int &$counter, array $args): int
    {
        $counter++;

        return $counter;
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
