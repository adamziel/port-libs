<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream fkey6 defer pragma corpus cites dbstatus block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'SQLITE_DBSTATUS_DEFERRED_FKS'));
        $t->true(is_string($source) && str_contains($source, 'do_test fkey6-1.5.1'));
    },
    'real upstream fkey6 defer pragma corpus cites reset block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'automatically switched off at each COMMIT or ROLLBACK'));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test fkey6-1.10.1'));
    },
    'real upstream fkey6 defer pragma corpus cites restrict block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'Test that defer_foreign_keys disables RESTRICT'));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 3.3.4'));
    },
    'real upstream fkey6 defer pragma corpus cites failed commit block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'be committed if there are outstanding foreign key violations'));
        $t->true(is_string($source) && str_contains($source, 'do_catchsql_test 4.2'));
    },
];

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

for ($i = 1; $i <= 125; ++$i) {
    $target = 1000 + $i;
    $parents = [
        ['id' => $target, 'label' => 'target-' . $i],
        ['id' => $target + 1, 'label' => 'sibling-' . $i],
    ];
    $children = [
        ['id' => 'c' . $i, 'parent_id' => $target, 'label' => 'child-' . $i],
        ['id' => 's' . $i, 'parent_id' => $target + 1, 'label' => 'sibling-child-' . $i],
    ];

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        $parents,
        $children,
        ['operation' => 'delete', 'target' => $target, 'action' => 'restrict', 'pragma_defer' => false, 'repair_trigger' => true, 'transaction' => 'statement']
    );
    foreach ([
        'source' => 'fkey6.test fkey6-1.0..4.2',
        'operation' => 'pragma-defer-foreign-keys-transaction-boundary',
        'status' => 'constraint-failed',
        'pragma_defer_foreign_keys' => false,
        'deferred_fk_dbstatus' => 0,
        'immediate_restrict_failed_before_trigger' => true,
        'repair_trigger_fired' => false,
        'rolled_back' => true,
        'parent_ids.0' => $target,
        'dependencies.2' => 'sqlite-fkey6-defer-foreign-keys-disables-restrict-until-commit',
    ] as $path => $expected) {
        $tests['fkey6-3.2 restrict without defer fails before trigger dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        $parents,
        $children,
        ['operation' => 'delete', 'target' => $target, 'action' => 'restrict', 'pragma_defer' => true, 'repair_trigger' => true, 'transaction' => 'commit']
    );
    foreach ([
        'source' => 'fkey6.test fkey6-1.0..4.2',
        'status' => 'commit-ok',
        'pragma_defer_foreign_keys' => true,
        'deferred_fk_dbstatus' => 0,
        'repair_trigger_fired' => true,
        'trigger_repaired_parent_ids.0' => $target,
        'violation_count' => 0,
        'pragma_reset_after_boundary' => true,
        'parent_ids.0' => $target,
        'dependencies.0' => 'sqlite-fkey6-defer-foreign-keys-delays-all-actions-to-outer-commit',
    ] as $path => $expected) {
        $tests['fkey6-3.3 defer restrict lets trigger repair parent dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        $parents,
        $children,
        ['operation' => 'delete', 'target' => $target, 'action' => 'no action', 'pragma_defer' => true, 'repair_trigger' => false, 'transaction' => 'commit']
    );
    foreach ([
        'source' => 'fkey6.test fkey6-1.0..4.2',
        'status' => 'constraint-failed',
        'pragma_defer_foreign_keys' => true,
        'deferred_fk_dbstatus' => 1,
        'commit_failed_with_deferred_violation' => true,
        'rolled_back' => true,
        'violation_count' => 1,
        'violations.0.child_id' => 'c' . $i,
        'parent_ids.0' => $target,
        'dependencies.3' => 'sqlite-fkey6-deferred-dbstatus-tracks-outstanding-violations',
    ] as $path => $expected) {
        $tests['fkey6-4 deferred no action cannot commit violation dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        $parents,
        $children,
        ['operation' => 'delete', 'target' => $target, 'action' => 'no action', 'pragma_defer' => true, 'repair_trigger' => false, 'transaction' => 'rollback']
    );
    foreach ([
        'source' => 'fkey6.test fkey6-1.0..4.2',
        'status' => 'commit-ok',
        'pragma_defer_foreign_keys' => true,
        'deferred_fk_dbstatus' => 1,
        'transaction_boundary' => 'rollback',
        'pragma_reset_after_boundary' => true,
        'rolled_back' => true,
        'violation_count' => 1,
        'parent_ids.0' => $target,
        'dependencies.1' => 'sqlite-fkey6-defer-foreign-keys-resets-after-commit-or-rollback',
    ] as $path => $expected) {
        $tests['fkey6-1 rollback clears deferred status and pragma dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

return $tests;
