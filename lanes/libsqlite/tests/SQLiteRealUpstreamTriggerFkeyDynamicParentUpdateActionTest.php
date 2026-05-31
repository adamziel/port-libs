<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream fkey3 parent update action cites upstream setup' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey3.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE t2(y INTEGER PRIMARY KEY REFERENCES t1 (x) ON UPDATE SET NULL)'));
        $t->true(is_string($source) && str_contains($source, 'do_test fkey3-2.1'));
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

for ($i = 1; $i <= 80; ++$i) {
    $parents = [
        ['x' => 100, 'label' => 'alpha'],
        ['x' => 101, 'label' => 'beta'],
    ];
    $children = [
        ['y' => 100, 'payload' => 'left'],
        ['y' => 101, 'payload' => 'right'],
    ];
    $newKey = 200 + $i;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::parentUpdateForeignKeyAction(
        $parents,
        $children,
        ['old' => 100, 'new' => $newKey, 'on_update' => 'set null'],
    );
    $case = 'real upstream fkey3-2 parent update set null dynamic ' . $i;
    foreach ([
        'source' => 'fkey3.test fkey3-2.1',
        'operation' => 'parent-update-foreign-key-action',
        'status' => 'commit-ok',
        'matched_parent_rows' => 1,
        'action_count' => 1,
        'action_rows.0.action' => 'set null',
        'action_rows.0.old_child_key' => 100,
        'action_rows.0.new_child_key' => null,
        'parent_key_values.0' => $newKey,
        'child_rows.0.y' => null,
        'child_rows.1.y' => 101,
        'violation_count' => 0,
        'dependencies.0' => 'sqlite-fkey3-parent-update-set-null-child-key-rewrite',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 40; ++$i) {
    $parents = [
        ['x' => 100],
        ['x' => 101],
    ];
    $children = [
        ['y' => 100],
        ['y' => 101],
    ];
    $newKey = 300 + $i;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::parentUpdateForeignKeyAction(
        $parents,
        $children,
        ['old' => 100, 'new' => $newKey, 'on_update' => 'cascade'],
    );
    $case = 'real upstream fkey3 parent update cascade sibling action dynamic ' . $i;
    foreach ([
        'status' => 'commit-ok',
        'on_update' => 'cascade',
        'action_count' => 1,
        'action_rows.0.new_child_key' => $newKey,
        'parent_key_values.0' => $newKey,
        'child_key_values.0' => $newKey,
        'violation_count' => 0,
        'dependencies.1' => 'sqlite-fkey3-parent-update-cascade-child-key-rewrite',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 40; ++$i) {
    $parents = [
        ['x' => 100],
        ['x' => 101],
    ];
    $children = [
        ['y' => 100],
        ['y' => 101],
    ];
    $newKey = 400 + $i;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::parentUpdateForeignKeyAction(
        $parents,
        $children,
        ['old' => 100, 'new' => $newKey, 'on_update' => 'no action'],
    );
    $case = 'real upstream fkey3 parent update no action detects orphan dynamic ' . $i;
    foreach ([
        'status' => 'constraint-failed',
        'on_update' => 'no action',
        'action_count' => 1,
        'action_rows.0.new_child_key' => 100,
        'parent_key_values.0' => $newKey,
        'child_key_values.0' => 100,
        'violation_count' => 1,
        'violations.0.child_key' => 100,
        'dependencies.2' => 'sqlite-fkey3-parent-update-no-action-statement-check',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream fkey3 parent update rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::parentUpdateForeignKeyAction(
        [['x' => 1]],
        [['y' => 1]],
        ['old' => 1, 'new' => 2, 'on_update' => 'restrict'],
    ));
};

return $tests;
