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
    'real upstream fkey2 count changes boundary cites source sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'PRAGMA count_changes = 1'));
        $t->true(is_string($source) && str_contains($source, 'sqlite3_column_text $STMT 0'));
        $t->true(is_string($source) && str_contains($source, 'sqlite3_finalize $STMT'));
        $t->true(is_string($source) && str_contains($source, 'expr [db total_changes] - $nTotal'));
    },
];

for ($i = 1; $i <= 220; ++$i) {
    $base = $i * 100;
    $parents = [
        ['a' => $base + 1, 'b' => $base + 2, 'c' => $base + 3],
        ['a' => $base + 2, 'b' => $base + 3, 'c' => $base + 4],
        ['a' => $base + 3, 'b' => $base + 4, 'c' => $base + 5],
        ['a' => 0, 'b' => 0, 'c' => 0],
    ];
    $children = [
        ['d' => $base + 1, 'e' => $base + 2, 'f' => $base + 3],
        ['d' => $base + 2, 'e' => $base + 3, 'f' => $base + 4],
        ['d' => $base + 3, 'e' => $base + 4, 'f' => $base + 5],
    ];

    $deferredInsert = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2CountChangesBoundary(
        $parents,
        $children,
        ['mode' => 'deferred-insert-step', 'insert' => ['g' => $base + 7, 'h' => $base + 8, 'i' => $base + 9]]
    );
    foreach ([
        'source' => 'fkey2.test fkey2-17.1.10..17.1.14',
        'operation' => 'count-changes-deferred-insert-step-boundary',
        'status' => 'constraint-on-second-step',
        'first_step_result' => 'SQLITE_ROW',
        'count_changes_row' => 1,
        'second_step_result' => 'SQLITE_CONSTRAINT',
        'finalize_result' => 'SQLITE_CONSTRAINT',
        'extended_error' => 'SQLITE_CONSTRAINT_FOREIGNKEY',
        'row_visible_before_constraint_step' => true,
        'deferred_violation_count' => 1,
        'inserted_row.g' => $base + 7,
        'dependencies.0' => 'sqlite-fkey2-count-changes-yields-row-before-deferred-fk-error',
    ] as $path => $expected) {
        $tests['fkey2-17 deferred count_changes insert boundary dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deferredInsert, $path, $expected, $value): void {
            $t->same($expected, $value($deferredInsert(), (string) $path));
        };
    }

    $failedUpdate = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2CountChangesBoundary(
        $parents,
        $children,
        ['mode' => 'update-child-keys', 'update_shift' => 1]
    );
    foreach ([
        'source' => 'fkey2.test fkey2-17.1.5..17.1.9',
        'operation' => 'count-changes-update-fk-boundary',
        'status' => 'constraint-failed',
        'statement_changes' => 0,
        'foreign_key_action_changes' => 0,
        'total_changes_delta' => 0,
        'count_changes_rows' => [],
        'violation_count' => 1,
        'violations.0.d' => $base + 3,
        'transaction_can_commit_after_failed_statement' => true,
        'committed_children.0.e' => $base + 2,
        'dependencies.1' => 'sqlite-fkey2-transaction-can-commit-after-statement-fk-error',
    ] as $path => $expected) {
        $tests['fkey2-17 failed update count_changes boundary dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($failedUpdate, $path, $expected, $value): void {
            $t->same($expected, $value($failedUpdate(), (string) $path));
        };
    }

    $cascade = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2CountChangesBoundary(
        [
            ['a' => 'p-' . $i, 'b' => 'payload-' . $i, 'c' => 'meta-' . $i],
        ],
        [
            ['d' => 'child-' . $i, 'e' => 'payload-' . $i, 'f' => 'p-' . $i],
        ],
        ['mode' => 'cascade-update-delete', 'cascade_key' => 'p-' . $i]
    );
    foreach ([
        'source' => 'fkey2.test fkey2-17.2.1..17.2.10',
        'operation' => 'count-changes-cascade-action-boundary',
        'status' => 'commit-ok',
        'update_statement_changes' => 1,
        'update_fk_action_changes' => 1,
        'update_total_changes_delta' => 2,
        'delete_statement_changes' => 1,
        'delete_fk_action_changes' => 1,
        'delete_total_changes_delta' => 2,
        'count_changes_excludes_fk_actions' => true,
        'total_changes_includes_fk_actions' => true,
        'updated_child_keys.0' => 'p-' . $i . '-next',
        'remaining_parent_count_after_delete' => 0,
        'remaining_child_count_after_delete' => 0,
        'dependencies.2' => 'sqlite-fkey2-count-changes-excludes-cascade-delete',
    ] as $path => $expected) {
        $tests['fkey2-17 cascade count_changes boundary dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($cascade, $path, $expected, $value): void {
            $t->same($expected, $value($cascade(), (string) $path));
        };
    }
}

$tests['real upstream fkey2 count changes boundary rejects unsupported mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey2CountChangesBoundary(
        [],
        [],
        ['mode' => 'vacuum']
    ));
};

return $tests;
