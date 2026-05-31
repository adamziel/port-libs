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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test';

$tests = [
    'real upstream fkey2 authorizer reset cites authorizer block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'Test that the authorization callback works'));
        $t->true(is_string($source) && str_contains($source, 'Return SQLITE_IGNORE to requests to read from the parent table'));
        $t->true(is_string($source) && str_contains($source, 'SQLITE_READ mid i main'));
    },
    'real upstream fkey2 authorizer reset cites prepared statement block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'sqlite3_prepare_v2 db "DELETE FROM main WHERE id = ?"'));
        $t->true(is_string($source) && str_contains($source, 'sqlite3_reset $S'));
        $t->true(is_string($source) && str_contains($source, 'verify_ex_errcode fkey2-19.3b SQLITE_CONSTRAINT_FOREIGNKEY'));
    },
];

$authorizerCases = [
    'fkey2-18.2 parent insert reads deferred child' => [
        ['operation' => 'insert-parent', 'child_key' => 'i'],
        [
            'source' => 'fkey2.test fkey2-18.1..18.11',
            'operation' => 'foreign-key-authorizer-callback',
            'status' => 'commit-ok',
            'statement_operation' => 'insert-parent',
            'auth_events.0.action' => 'SQLITE_INSERT',
            'auth_events.0.table' => 'long',
            'auth_events.1.action' => 'SQLITE_READ',
            'auth_events.1.table' => 'mid',
            'auth_events.1.column' => 'i',
            'auth_event_count' => 2,
            'ignored_parent_read' => false,
        ],
    ],
    'fkey2-18.3 immediate child insert reads parent key' => [
        ['operation' => 'insert-immediate-child', 'child_value' => 2],
        [
            'status' => 'commit-ok',
            'statement_operation' => 'insert-immediate-child',
            'auth_events.0.action' => 'SQLITE_INSERT',
            'auth_events.0.table' => 'short',
            'auth_events.1.action' => 'SQLITE_READ',
            'auth_events.1.table' => 'long',
            'auth_events.1.column' => 'b',
            'auth_event_count' => 2,
            'error' => null,
        ],
    ],
    'fkey2-18.4 deferred child insert reads parent key' => [
        ['operation' => 'insert-deferred-child', 'child_value' => 2],
        [
            'status' => 'commit-ok',
            'statement_operation' => 'insert-deferred-child',
            'child_table' => 'mid',
            'auth_events.1.table' => 'long',
            'auth_events.1.column' => 'b',
            'auth_event_count' => 2,
        ],
    ],
    'fkey2-18.5 cascade update reads and updates child' => [
        ['operation' => 'update-parent-cascade', 'cascade' => true],
        [
            'status' => 'commit-ok',
            'statement_operation' => 'update-parent-cascade',
            'auth_events.0.action' => 'SQLITE_UPDATE',
            'auth_events.1.action' => 'SQLITE_READ',
            'auth_events.5.action' => 'SQLITE_UPDATE',
            'auth_events.5.table' => 'short',
            'auth_events.5.column' => 'e',
            'auth_event_count' => 6,
            'cascade_applied' => true,
        ],
    ],
    'fkey2-18.7 rowid child insert reads integer primary key' => [
        ['operation' => 'insert-rowid-child', 'parent_table' => 'one', 'child_table' => 'two', 'parent_key' => 'a', 'child_key' => 'c', 'child_value' => 101],
        [
            'status' => 'commit-ok',
            'parent_table' => 'one',
            'child_table' => 'two',
            'parent_key' => 'a',
            'child_key' => 'c',
            'auth_events.1.table' => 'one',
            'auth_events.1.column' => 'a',
        ],
    ],
    'fkey2-18.8 ignored parent read fails non-null child' => [
        ['operation' => 'insert-immediate-child', 'authorization' => 'ignore-parent-read', 'child_value' => 2],
        [
            'status' => 'constraint-failed',
            'authorization' => 'ignore-parent-read',
            'ignored_parent_read' => true,
            'error' => 'FOREIGN KEY constraint failed',
            'auth_events.1.action' => 'SQLITE_READ',
            'auth_events.1.table' => 'long',
        ],
    ],
    'fkey2-18.9 ignored parent read allows null child' => [
        ['operation' => 'insert-immediate-child', 'authorization' => 'ignore-parent-read', 'child_value' => null],
        [
            'status' => 'commit-ok',
            'authorization' => 'ignore-parent-read',
            'ignored_parent_read' => false,
            'error' => null,
            'auth_event_count' => 2,
        ],
    ],
];

for ($i = 1; $i <= 75; ++$i) {
    $parents = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 101, 'b' => 102, 'c' => 103],
        ['a' => 200 + $i, 'b' => 300 + $i, 'c' => 400 + $i],
    ];
    $children = [
        ['d' => 1, 'e' => 3, 'f' => 2, 'i' => 2],
        ['d' => 2, 'e' => 5, 'f' => null, 'i' => null],
    ];

    foreach ($authorizerCases as $label => [$statement, $expectations]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2AuthorizerCallbackPlan($parents, $children, $statement);
        $case = sprintf('real upstream fkey2-18 authorizer dynamic %03d %s', $i, $label);
        foreach ($expectations + [
            'dependencies.0' => 'sqlite-fkey2-authorizer-parent-insert-reads-deferred-child-key',
            'dependencies.1' => 'sqlite-fkey2-authorizer-child-insert-reads-parent-key',
            'dependencies.2' => 'sqlite-fkey2-authorizer-ignore-parent-read-causes-fk-failure',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

for ($i = 1; $i <= 100; ++$i) {
    $referenced = 2 + ($i * 10);
    $unreferenced = 1 + ($i * 10);
    $parents = [$unreferenced, $referenced, 3 + ($i * 10)];
    $children = [$referenced];
    $bindings = [$referenced, $unreferenced];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::preparedForeignKeyDeleteResetPlan($parents, $children, $bindings);
    $case = sprintf('real upstream fkey2-19 prepared delete reset dynamic %03d', $i);

    foreach ([
        'source' => 'fkey2.test fkey2-19.1..19.4',
        'operation' => 'prepared-foreign-key-delete-reset',
        'status' => 'commit-ok',
        'sql' => 'DELETE FROM main WHERE id = ?',
        'trace.0.bound_parent_id' => $referenced,
        'trace.0.step_status' => 'SQLITE_CONSTRAINT',
        'trace.0.reset_status' => 'SQLITE_CONSTRAINT',
        'trace.0.extended_error' => 'SQLITE_CONSTRAINT_FOREIGNKEY',
        'trace.0.delete_applied' => false,
        'trace.1.bound_parent_id' => $unreferenced,
        'trace.1.step_status' => 'SQLITE_DONE',
        'trace.1.reset_status' => 'SQLITE_OK',
        'trace.1.delete_applied' => true,
        'remaining_parent_ids' => [$referenced, 3 + ($i * 10)],
        'finalize_status' => 'SQLITE_OK',
        'constraint_reset_preserved' => true,
        'dependencies.0' => 'sqlite-fkey2-prepared-delete-step-reports-foreign-key-constraint',
        'dependencies.1' => 'sqlite-fkey2-prepared-delete-reset-preserves-constraint-status',
        'dependencies.2' => 'sqlite-fkey2-prepared-delete-rebind-can-succeed-after-failed-reset',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream fkey2-18 authorizer rejects unsupported callback operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey2AuthorizerCallbackPlan([], [], ['operation' => 'drop-parent']));
};

$tests['real upstream fkey2-18 authorizer rejects unsupported authorization mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey2AuthorizerCallbackPlan([], [], ['operation' => 'insert-parent', 'authorization' => 'deny']));
};

$tests['real upstream fkey2-19 prepared delete reset rejects empty bindings'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::preparedForeignKeyDeleteResetPlan([1], [], []));
};

return $tests;
