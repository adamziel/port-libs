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

$tests = [
    'real upstream trigger fkey dynamic 20260530 cites trigger5 undo corpus' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger5.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger5-1.1'));
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO Undo SELECT'));
        $t->true(is_string($source) && str_contains($source, "quote(old.b)"));
    },
    'real upstream trigger fkey dynamic 20260530 cites fkey6 defer corpus' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'EVIDENCE-OF: R-18981-16292'));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 3.3.4'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA defer_foreign_keys = 1'));
    },
    'real upstream trigger fkey dynamic 20260530 cites fkey8 counter corpus' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
        $t->true(is_string($source) && str_contains($source, 'foreign key constaint counters'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER'));
    },
    'real upstream trigger fkey dynamic 20260530 cites trigger7 selective corpus' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test');
        $t->true(is_string($source) && str_contains($source, 'UPDATE OF syntax'));
        $t->true(is_string($source) && str_contains($source, 'DROP TRIGGER t2r6'));
    },
];

for ($i = 1; $i <= 125; ++$i) {
    $base = $i * 100;
    $undoRows = [
        ['a' => $base + 1, 'b' => 38205.60865 + ($i / 1000), 'c' => $i],
        ['a' => $base + 2, 'b' => 'quote-' . $i, 'c' => $i + 1],
        ['a' => $base + 3, 'b' => null, 'c' => $i + 2],
    ];
    $undoPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deleteUndoTriggerStatements(
        $undoRows,
        static fn (array $row): bool => ($row['a'] ?? 0) !== $base + 2
    );

    foreach ([
        'source' => 'trigger5.test trigger5-1.1',
        'operation' => 'after-delete-trigger-undo-sql-generation',
        'status' => 'commit-ok',
        'deleted_count' => 2,
        'undo_count' => 2,
        'remaining_count' => 1,
        'remaining_rows.0.a' => $base + 2,
        'undo_statements.0' => 'INSERT INTO Item (a,b,c) VALUES (' . ($base + 1) . ',' . (38205.60865 + ($i / 1000)) . ',' . $i . ');',
        'undo_statements.1' => 'INSERT INTO Item (a,b,c) VALUES (' . ($base + 3) . ',NULL,' . ($i + 2) . ');',
        'dependencies.2' => 'sqlite-trigger5-delete-trigger-emits-one-undo-row-per-deleted-row',
    ] as $path => $expected) {
        $tests['trigger5 undo dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($undoPlan, $path, $expected, $value): void {
            $t->same($expected, $value($undoPlan(), (string) $path));
        };
    }

    $parents = [
        ['id' => $base + 1, 'label' => 'one'],
        ['id' => $base + 2, 'label' => 'two'],
    ];
    $children = [
        ['id' => $base + 10, 'parent_id' => $base + 1, 'label' => 'child-one'],
    ];
    $restrictImmediate = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysRestrictDelete(
        $parents,
        $children,
        $base + 1,
        false,
        true
    );
    $restrictDeferredRepair = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysRestrictDelete(
        $parents,
        $children,
        $base + 1,
        true,
        true
    );

    foreach ([
        'source' => 'fkey6.test fkey6-3.3.1..3.3.4',
        'operation' => 'defer-foreign-keys-restrict-delete-trigger-repair',
        'status' => 'constraint-failed',
        'defer_foreign_keys' => false,
        'initial_violation_count' => 1,
        'trigger_repaired' => false,
        'parent_ids.0' => $base + 1,
        'dependencies.0' => 'sqlite-fkey6-restrict-is-immediate-without-defer-foreign-keys',
        'dependencies.1' => 'sqlite-fkey6-defer-foreign-keys-resets-at-transaction-boundary',
        'pragma_after_boundary' => 0,
    ] as $path => $expected) {
        $tests['fkey6 restrict immediate dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($restrictImmediate, $path, $expected, $value): void {
            $t->same($expected, $value($restrictImmediate(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'defer_foreign_keys' => true,
        'initial_violation_count' => 1,
        'commit_violation_count' => 0,
        'trigger_repaired' => true,
        'trigger_inserted_parent.id' => $base + 1,
        'trigger_inserted_parent.label' => 'deleted!',
        'parent_ids.0' => $base + 1,
        'rollback_restored' => false,
        'dependencies.0' => 'sqlite-fkey6-defer-foreign-keys-disables-restrict-until-commit',
    ] as $path => $expected) {
        $tests['fkey6 deferred trigger repair dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($restrictDeferredRepair, $path, $expected, $value): void {
            $t->same($expected, $value($restrictDeferredRepair(), (string) $path));
        };
    }

    $counterPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::replaceDeferredForeignKeyCounter(
        [
            ['id' => $base + 1, 'label' => 'parent-one'],
            ['id' => $base + 2, 'label' => 'parent-two'],
        ],
        [
            ['id' => $base + 20, 'parent_id' => $base + 1, 'label' => 'child-one'],
            ['id' => $base + 21, 'parent_id' => $base + 2, 'label' => 'child-two'],
        ],
        [
            'operation' => $i % 2 === 0 ? 'delete-parent-replace-parent' : 'delete-parent-trigger-replace',
            'target_parent' => $base + 1,
            'replacement_parent' => $base + 1,
            'delete_children' => false,
            'trigger_replaces_parent' => $i % 2 === 1,
        ]
    );

    foreach ([
        'source' => 'fkey8.test fkey8-2.1.2..2.3.1',
        'operation' => 'deferred-foreign-key-counter-implicit-delete',
        'status' => 'commit-ok',
        'deferred_violation_count' => 0,
        'constraint_counter_includes_implicit_deletes' => true,
        'committed_parent_ids.0' => $base + 1,
        'committed_child_parent_ids.0' => $base + 1,
        'implicit_delete_count' => 2,
        'rollback_restored' => false,
        'dependencies.2' => 'sqlite-fkey8-trigger-side-replace-preserves-counter',
    ] as $path => $expected) {
        $tests['fkey8 deferred counter dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($counterPlan, $path, $expected, $value): void {
            $t->same($expected, $value($counterPlan(), (string) $path));
        };
    }

    $trigger7Rows = [
        ['rowid' => 1, 'a' => $base + 1, 'b' => 1],
        ['rowid' => 2, 'a' => $base + 2, 'b' => 2],
        ['rowid' => 3, 'a' => $base + 3, 'b' => 3],
    ];
    $trigger7Plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::beforeTriggerRowidMutation(
        $trigger7Rows,
        $i % 2 === 0 ? 'update' : 'delete',
        $base + 1,
        $i % 3 === 0 ? 'move-rowid-1-to-8' : 'delete-rowid-1'
    );

    foreach ([
        'source' => 'triggerC.test triggerC-7.1..7.9',
        'operation' => 'before-trigger-rowid-mutation',
        'status' => 'commit-ok',
        'target_a' => $base + 1,
        'before_trigger_applied' => true,
        'outer_statement_changed' => false,
        'dependencies.0' => 'sqlite-triggerC-before-trigger-can-delete-target-row',
        'dependencies.1' => 'sqlite-triggerC-before-trigger-can-move-rowid-before-outer-statement',
        'dependencies.2' => 'sqlite-triggerC-after-trigger-fires-only-for-surviving-outer-row-change',
    ] as $path => $expected) {
        $tests['trigger rowid mutation dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($trigger7Plan, $path, $expected, $value): void {
            $t->same($expected, $value($trigger7Plan(), (string) $path));
        };
    }
}

return $tests;
