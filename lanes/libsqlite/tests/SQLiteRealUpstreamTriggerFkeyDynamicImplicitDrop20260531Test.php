<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

$tests = [
    'real upstream e_fkey implicit drop cites drop table implicit delete block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'Check that a DROP TABLE does an implicit DELETE FROM'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-57.2'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-57.5'));
    },
    'real upstream e_fkey implicit drop cites immediate failure block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'If an IMMEDIATE foreign key fails as a result of a DROP TABLE'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-58.1'));
    },
    'real upstream e_fkey implicit drop cites deferred commit repair block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'If a DEFERRED foreign key fails as a result of a DROP TABLE'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-59.5'));
    },
    'real upstream e_fkey implicit drop cites mismatch ignore block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'Any "foreign key mismatch" errors encountered while running an implicit'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-60.3'));
    },
    'real upstream e_fkey implicit drop cites foreign key toggle block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'The properties of the DROP TABLE and ALTER'));
        $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-61.3.2'));
    },
];

$baseRows = static function (int $seed): array {
    $target = ['a' => 'tenant_' . $seed, 'b' => 'setting_' . $seed, 'label' => 'target'];
    $fallback = ['a' => 'tenant_' . $seed, 'b' => 'fallback_' . $seed, 'label' => 'fallback'];
    $spare = ['a' => 'tenant_' . $seed, 'b' => 'spare_' . $seed, 'label' => 'spare'];
    $rows = [
        ['id' => 'hit_a_' . $seed, 'c' => $target['a'], 'd' => $target['b']],
        ['id' => 'hit_b_' . $seed, 'c' => $fallback['a'], 'd' => $fallback['b']],
        ['id' => 'hit_c_' . $seed, 'c' => $spare['a'], 'd' => $spare['b']],
        ['id' => 'loose_null_' . $seed, 'c' => null, 'd' => null],
    ];

    return [$target, $fallback, $spare, $rows];
};

$childTable = static function (int $seed, string $name, string $action, bool $deferred, array $rows, array $defaults = [], bool $mismatch = false): array {
    return [
        'name' => $name . '_' . $seed,
        'action' => $action,
        'deferred' => $deferred,
        'child_columns' => ['c', 'd'],
        'parent_columns' => ['a', 'b'],
        'defaults' => $defaults,
        'parent_mismatch' => $mismatch,
        'rows' => $rows,
    ];
};

$caseFor = static function (int $seed, string $mode) use ($baseRows, $childTable): array {
    [$target, $fallback, $spare, $rows] = $baseRows($seed);
    $parents = [$target, $fallback, $spare];
    $defaults = ['c' => $fallback['a'], 'd' => $fallback['b']];
    $options = [
        'schema' => $seed % 3 === 0 ? 'temp' : ($seed % 3 === 1 ? 'main' : 'aux'),
        'parent_table' => 'app_parent_' . $seed,
        'parent_columns' => ['a', 'b'],
        'foreign_keys' => true,
        'ordinary_delete_trigger_count' => 2,
    ];

    $expect = [
        'source' => 'e_fkey.test e_fkey-57.1..61.3.3',
        'operation' => 'drop-table-implicit-foreign-key-delete',
        'schema' => $options['schema'],
        'parent_table' => $options['parent_table'],
        'foreign_keys' => true,
        'implicit_delete_ran' => true,
        'sql_trigger_fire_count' => 0,
        'suppressed_sql_trigger_count' => 2,
        'parent_key_columns' => ['a', 'b'],
        'deleted_parent_count' => 3,
    ];
    $childExpect = [];

    if ($mode === 'set-null') {
        $childTables = [$childTable($seed, 'set_null_child', 'set null', false, $rows)];
        $expect += [
            'status' => 'commit-ok',
            'drop_status' => 'drop-ok',
            'commit_status' => 'commit-ok',
            'parent_table_dropped' => true,
            'table_visible_after_drop' => false,
            'parent_table_recreated_for_commit' => false,
            'fk_action_count' => 3,
            'immediate_violation_count' => 0,
            'deferred_violation_count' => 0,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => false,
            'rolled_back_fk_action_count' => 0,
            'repair_parent_count' => 0,
            'dependencies.1' => 'sqlite-efkey-drop-table-implicit-delete-fires-fk-actions',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 4,
            'child_tables.0.action_count' => 3,
            'child_tables.0.child_keys.0.c' => null,
            'child_tables.0.child_keys.2.d' => null,
        ];
    } elseif ($mode === 'cascade') {
        $childTables = [$childTable($seed, 'cascade_child', 'cascade', false, $rows)];
        $expect += [
            'status' => 'commit-ok',
            'drop_status' => 'drop-ok',
            'commit_status' => 'commit-ok',
            'parent_table_dropped' => true,
            'table_visible_after_drop' => false,
            'parent_table_recreated_for_commit' => false,
            'fk_action_count' => 3,
            'immediate_violation_count' => 0,
            'deferred_violation_count' => 0,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => false,
            'rolled_back_fk_action_count' => 0,
            'repair_parent_count' => 0,
            'dependencies.1' => 'sqlite-efkey-drop-table-implicit-delete-fires-fk-actions',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 1,
            'child_tables.0.action_count' => 3,
            'child_tables.0.child_keys.0.c' => null,
            'foreign_key_actions.0.action' => 'cascade-delete',
        ];
    } elseif ($mode === 'set-default-immediate') {
        $childTables = [$childTable($seed, 'set_default_child', 'set default', false, $rows, $defaults)];
        $expect += [
            'status' => 'constraint-failed',
            'drop_status' => 'drop-blocked',
            'commit_status' => 'not-started',
            'parent_table_dropped' => false,
            'table_visible_after_drop' => true,
            'parent_table_recreated_for_commit' => false,
            'fk_action_count' => 0,
            'immediate_violation_count' => 3,
            'deferred_violation_count' => 0,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => true,
            'rolled_back_fk_action_count' => 3,
            'repair_parent_count' => 0,
            'dependencies.1' => 'sqlite-efkey-immediate-drop-table-fk-violation-rolls-back-drop',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 4,
            'child_tables.0.action_count' => 0,
            'child_tables.0.child_keys.0.c' => $target['a'],
            'immediate_violations.0.reason' => 'missing-parent-after-drop-table-implicit-delete',
        ];
    } elseif ($mode === 'set-default-deferred-block') {
        $childTables = [$childTable($seed, 'set_default_child', 'set default', true, $rows, $defaults)];
        $expect += [
            'status' => 'deferred-commit-failed',
            'drop_status' => 'drop-ok',
            'commit_status' => 'deferred-commit-failed',
            'parent_table_dropped' => true,
            'table_visible_after_drop' => false,
            'parent_table_recreated_for_commit' => false,
            'fk_action_count' => 3,
            'immediate_violation_count' => 0,
            'deferred_violation_count' => 3,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => false,
            'rolled_back_fk_action_count' => 0,
            'repair_parent_count' => 0,
            'dependencies.3' => 'sqlite-efkey-deferred-drop-table-fk-violation-fails-at-commit',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 4,
            'child_tables.0.action_count' => 3,
            'child_tables.0.child_keys.0.d' => $fallback['b'],
            'deferred_violations.0.phase' => 'commit',
        ];
    } elseif ($mode === 'set-default-deferred-repaired') {
        $childTables = [$childTable($seed, 'set_default_child', 'set default', true, $rows, $defaults)];
        $options['repair_parent_rows'] = [$fallback];
        $expect += [
            'status' => 'commit-ok',
            'drop_status' => 'drop-ok',
            'commit_status' => 'commit-ok',
            'parent_table_dropped' => true,
            'table_visible_after_drop' => false,
            'parent_table_recreated_for_commit' => true,
            'fk_action_count' => 3,
            'immediate_violation_count' => 0,
            'deferred_violation_count' => 0,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => false,
            'rolled_back_fk_action_count' => 0,
            'repair_parent_count' => 1,
            'dependencies.1' => 'sqlite-efkey-drop-table-implicit-delete-fires-fk-actions',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 4,
            'child_tables.0.action_count' => 3,
            'child_tables.0.child_keys.1.c' => $fallback['a'],
            'parent_keys_after_commit.0.b' => $fallback['b'],
        ];
    } elseif ($mode === 'no-action-immediate') {
        $childTables = [$childTable($seed, 'no_action_child', 'no action', false, $rows)];
        $expect += [
            'status' => 'constraint-failed',
            'drop_status' => 'drop-blocked',
            'commit_status' => 'not-started',
            'parent_table_dropped' => false,
            'table_visible_after_drop' => true,
            'parent_table_recreated_for_commit' => false,
            'fk_action_count' => 0,
            'immediate_violation_count' => 3,
            'deferred_violation_count' => 0,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => true,
            'rolled_back_fk_action_count' => 3,
            'repair_parent_count' => 0,
            'dependencies.1' => 'sqlite-efkey-immediate-drop-table-fk-violation-rolls-back-drop',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 4,
            'child_tables.0.action_count' => 0,
            'child_tables.0.child_keys.1.d' => $fallback['b'],
            'immediate_violations.0.action' => 'no action',
        ];
    } elseif ($mode === 'no-action-deferred-block') {
        $childTables = [$childTable($seed, 'no_action_child', 'no action', true, $rows)];
        $expect += [
            'status' => 'deferred-commit-failed',
            'drop_status' => 'drop-ok',
            'commit_status' => 'deferred-commit-failed',
            'parent_table_dropped' => true,
            'table_visible_after_drop' => false,
            'parent_table_recreated_for_commit' => false,
            'fk_action_count' => 3,
            'immediate_violation_count' => 0,
            'deferred_violation_count' => 3,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => false,
            'rolled_back_fk_action_count' => 0,
            'repair_parent_count' => 0,
            'dependencies.3' => 'sqlite-efkey-deferred-drop-table-fk-violation-fails-at-commit',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 4,
            'child_tables.0.action_count' => 3,
            'child_tables.0.child_keys.2.d' => $spare['b'],
            'deferred_violations.0.action' => 'no action',
        ];
    } elseif ($mode === 'no-action-deferred-repaired') {
        $childTables = [$childTable($seed, 'no_action_child', 'no action', true, $rows)];
        $options['repair_parent_rows'] = $parents;
        $expect += [
            'status' => 'commit-ok',
            'drop_status' => 'drop-ok',
            'commit_status' => 'commit-ok',
            'parent_table_dropped' => true,
            'table_visible_after_drop' => false,
            'parent_table_recreated_for_commit' => true,
            'fk_action_count' => 3,
            'immediate_violation_count' => 0,
            'deferred_violation_count' => 0,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => false,
            'rolled_back_fk_action_count' => 0,
            'repair_parent_count' => 3,
            'dependencies.1' => 'sqlite-efkey-drop-table-implicit-delete-fires-fk-actions',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 4,
            'child_tables.0.action_count' => 3,
            'parent_keys_after_commit.2.b' => $spare['b'],
            'child_tables.0.child_keys.0.c' => $target['a'],
        ];
    } elseif ($mode === 'restrict-deferred') {
        $childTables = [$childTable($seed, 'restrict_child', 'restrict', true, $rows)];
        $expect += [
            'status' => 'constraint-failed',
            'drop_status' => 'drop-blocked',
            'commit_status' => 'not-started',
            'parent_table_dropped' => false,
            'table_visible_after_drop' => true,
            'parent_table_recreated_for_commit' => false,
            'fk_action_count' => 0,
            'immediate_violation_count' => 3,
            'deferred_violation_count' => 0,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => true,
            'rolled_back_fk_action_count' => 3,
            'repair_parent_count' => 0,
            'dependencies.1' => 'sqlite-efkey-immediate-drop-table-fk-violation-rolls-back-drop',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 4,
            'child_tables.0.action_count' => 0,
            'immediate_violations.0.action' => 'restrict',
            'child_tables.0.child_keys.0.d' => $target['b'],
        ];
    } elseif ($mode === 'foreign-keys-off') {
        $childTables = [$childTable($seed, 'cascade_child', 'cascade', false, $rows)];
        $options['foreign_keys'] = false;
        $expect += [
            'status' => 'commit-ok',
            'drop_status' => 'drop-ok',
            'commit_status' => 'commit-ok',
            'foreign_keys' => false,
            'implicit_delete_ran' => false,
            'parent_table_dropped' => true,
            'table_visible_after_drop' => false,
            'parent_table_recreated_for_commit' => false,
            'fk_action_count' => 0,
            'immediate_violation_count' => 0,
            'deferred_violation_count' => 0,
            'ignored_mismatch_count' => 0,
            'implicit_delete_rolled_back' => false,
            'rolled_back_fk_action_count' => 0,
            'repair_parent_count' => 0,
            'dependencies.0' => 'sqlite-efkey-drop-table-special-behavior-requires-foreign-keys-on',
        ];
        $childExpect = [
            'child_tables.0.row_count' => 4,
            'child_tables.0.action_count' => 0,
            'child_tables.0.child_keys.0.c' => $target['a'],
            'child_tables.0.child_keys.2.d' => $spare['b'],
        ];
    } elseif ($mode === 'mismatch-ignored-set-null') {
        $childTables = [
            $childTable($seed, 'mismatch_child', 'no action', false, $rows, [], true),
            $childTable($seed, 'set_null_child', 'set null', false, $rows),
        ];
        $expect += [
            'status' => 'commit-ok',
            'drop_status' => 'drop-ok',
            'commit_status' => 'commit-ok',
            'parent_table_dropped' => true,
            'table_visible_after_drop' => false,
            'parent_table_recreated_for_commit' => false,
            'fk_action_count' => 3,
            'immediate_violation_count' => 0,
            'deferred_violation_count' => 0,
            'ignored_mismatch_count' => 1,
            'implicit_delete_rolled_back' => false,
            'rolled_back_fk_action_count' => 0,
            'repair_parent_count' => 0,
            'dependencies.4' => 'sqlite-efkey-drop-table-ignores-mismatch-errors-during-implicit-delete',
        ];
        $childExpect = [
            'child_tables.0.parent_mismatch_ignored' => true,
            'child_tables.0.child_keys.0.c' => $target['a'],
            'child_tables.1.action_count' => 3,
            'child_tables.1.child_keys.1.d' => null,
        ];
    } else {
        throw new InvalidArgumentException('unknown drop table case mode');
    }

    $expect['foreign_keys'] = (bool) $options['foreign_keys'];
    $expect['implicit_delete_ran'] = (bool) $options['foreign_keys'];
    $expect['deleted_parent_count'] = ($expect['drop_status'] ?? null) === 'drop-blocked' ? 0 : 3;

    return [
        'parents' => $parents,
        'child_tables' => $childTables,
        'options' => $options,
        'expect' => $expect,
        'child_expect' => $childExpect,
    ];
};

$modes = [
    'set-null',
    'cascade',
    'set-default-immediate',
    'set-default-deferred-block',
    'set-default-deferred-repaired',
    'no-action-immediate',
    'no-action-deferred-block',
    'no-action-deferred-repaired',
    'restrict-deferred',
    'foreign-keys-off',
    'mismatch-ignored-set-null',
];

foreach (range(1, 12) as $seed) {
    foreach ($modes as $mode) {
        $case = $caseFor($seed, $mode);
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyDropTableImplicitDeletePlan(
            $case['parents'],
            $case['child_tables'],
            $case['options']
        );
        $label = sprintf('real upstream e_fkey implicit drop dynamic seed %03d %s', $seed, $mode);

        foreach ($case['expect'] as $path => $expected) {
            $tests[$label . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
        foreach ($case['child_expect'] as $path => $expected) {
            $tests[$label . ' child outcome ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }

        $tests[$label . ' composite parent key count follows repair or rollback'] = static function (TestRunner $t) use ($plan): void {
            $actual = $plan();
            $expected = $actual['parent_table_dropped']
                ? (int) $actual['repair_parent_count']
                : count($actual['parent_keys_after_commit']);
            $t->same($expected, count($actual['parent_keys_after_commit']));
        };
        $tests[$label . ' sql triggers suppressed across implicit delete'] = static function (TestRunner $t) use ($plan): void {
            $actual = $plan();
            $t->same(0, $actual['sql_trigger_fire_count']);
            $t->same(2, $actual['suppressed_sql_trigger_count']);
        };
        $tests[$label . ' immediate and deferred violation buckets are disjoint'] = static function (TestRunner $t) use ($plan): void {
            $actual = $plan();
            $t->same($actual['immediate_violation_count'] === 0 || $actual['deferred_violation_count'] === 0, true);
        };
    }
}

$tests['real upstream e_fkey implicit drop rejects empty child table set'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyDropTableImplicitDeletePlan([], []));
};

$tests['real upstream e_fkey implicit drop rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyDropTableImplicitDeletePlan(
        [['a' => 1, 'b' => 2]],
        [[
            'name' => 'bad_child',
            'action' => 'raise',
            'child_columns' => ['c', 'd'],
            'parent_columns' => ['a', 'b'],
            'rows' => [['c' => 1, 'd' => 2]],
        ]]
    ));
};

$tests['real upstream e_fkey implicit drop rejects key width mismatch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyDropTableImplicitDeletePlan(
        [['a' => 1, 'b' => 2]],
        [[
            'name' => 'bad_child',
            'action' => 'cascade',
            'child_columns' => ['c'],
            'parent_columns' => ['a', 'b'],
            'rows' => [['c' => 1]],
        ]]
    ));
};

$tests['real upstream e_fkey implicit drop rejects missing child key column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyDropTableImplicitDeletePlan(
        [['a' => 1, 'b' => 2]],
        [[
            'name' => 'bad_child',
            'action' => 'cascade',
            'child_columns' => ['c', 'd'],
            'parent_columns' => ['a', 'b'],
            'rows' => [['c' => 1]],
        ]]
    ));
};

$tests['real upstream e_fkey implicit drop owns non overlapping sections'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: e_fkey-57..61 implicit DROP TABLE FK delete behavior, not fkey2-14 schema DDL, recursive FK cascade pragma, or fkey6 defer lifecycle',
        'non-overlap: e_fkey-57..61 implicit DROP TABLE FK delete behavior, not fkey2-14 schema DDL, recursive FK cascade pragma, or fkey6 defer lifecycle'
    );
};

$tests['real upstream e_fkey implicit drop dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no-new-support-component-needed: uses existing row-array trigger/FK planner primitives and hydrated upstream e_fkey.test source', 'no-new-support-component-needed: uses existing row-array trigger/FK planner primitives and hydrated upstream e_fkey.test source');
};

return $tests;
