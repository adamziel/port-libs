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
    'real upstream fkey2 recursive cascade pragma cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'Test that FK actions may recurse even when recursive triggers'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA recursive_triggers = off'));
        $t->true(is_string($source) && str_contains($source, 'DELETE FROM t1 WHERE node = 1'));
        $t->true(is_string($source) && str_contains($source, 'DELETE FROM t2 WHERE node = 1'));
    },
];

for ($i = 1; $i <= 180; ++$i) {
    $root = $i * 1000 + 1;
    $rows = [
        ['node' => $root, 'parent' => null],
        ['node' => $root + 1, 'parent' => $root],
        ['node' => $root + 2, 'parent' => $root],
        ['node' => $root + 3, 'parent' => $root + 1],
        ['node' => $root + 4, 'parent' => $root + 1],
        ['node' => $root + 5, 'parent' => $root + 2],
        ['node' => $root + 6, 'parent' => $root + 2],
    ];

    $off = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2RecursiveCascadeIgnoresRecursiveTriggerPragma(
        $rows,
        $rows,
        $root,
        false
    );
    $on = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2RecursiveCascadeIgnoresRecursiveTriggerPragma(
        $rows,
        $rows,
        $root,
        true
    );

    foreach ([
        'source' => 'fkey2.test fkey2-4.1..4.4',
        'operation' => 'recursive-foreign-key-cascade-ignores-recursive-trigger-pragma',
        'status' => 'commit-ok',
        'recursive_triggers' => false,
        'foreign_key_deleted_nodes' => [$root, $root + 1, $root + 2, $root + 3, $root + 4, $root + 5, $root + 6],
        'trigger_deleted_nodes' => [$root, $root + 1, $root + 2],
        'foreign_key_remaining_nodes' => [],
        'trigger_remaining_nodes' => [$root + 3, $root + 4, $root + 5, $root + 6],
        'foreign_key_cascade_reaches_grandchildren' => true,
        'ordinary_trigger_reaches_grandchildren' => false,
        'foreign_key_changes' => 7,
        'trigger_changes' => 3,
        'dependencies.0' => 'sqlite-fkey2-recursive-fk-actions-ignore-recursive-trigger-pragma',
        'dependencies.1' => 'sqlite-fkey2-user-trigger-recursion-obeys-recursive-trigger-pragma',
    ] as $path => $expected) {
        $tests['fkey2-4 recursive fk cascade ignores disabled trigger recursion dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($off, $path, $expected, $value): void {
            $t->same($expected, $value($off(), (string) $path));
        };
    }

    foreach ([
        'recursive_triggers' => true,
        'foreign_key_deleted_nodes' => [$root, $root + 1, $root + 2, $root + 3, $root + 4, $root + 5, $root + 6],
        'trigger_deleted_nodes' => [$root, $root + 1, $root + 2, $root + 3, $root + 4, $root + 5, $root + 6],
        'foreign_key_remaining_nodes' => [],
        'trigger_remaining_nodes' => [],
        'foreign_key_cascade_reaches_grandchildren' => true,
        'ordinary_trigger_reaches_grandchildren' => true,
        'foreign_key_changes' => 7,
        'trigger_changes' => 7,
        'dependencies.2' => 'sqlite-fkey2-cascade-delete-visits-descendant-tree',
    ] as $path => $expected) {
        $tests['fkey2-4 recursive fk and user trigger both recurse when enabled dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($on, $path, $expected, $value): void {
            $t->same($expected, $value($on(), (string) $path));
        };
    }
}

$tests['fkey2-4 recursive cascade rejects unsupported child row shape'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2RecursiveCascadeIgnoresRecursiveTriggerPragma(
        [['node' => 1, 'parent' => null], ['node' => 2, 'parent' => 1]],
        [['node' => 1, 'parent' => null], ['node' => 'bad', 'parent' => 1]],
        1,
        true
    ));
};

return $tests;
