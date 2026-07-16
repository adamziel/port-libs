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
    'real upstream fkey2 set default cites delete action block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'The following tests, fkey2-9.*, test SET DEFAULT actions.'));
        $t->true(is_string($source) && str_contains($source, 'd INTEGER DEFAULT 1 REFERENCES t1 ON DELETE SET DEFAULT'));
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-9.1.5'));
    },
    'real upstream fkey2 set default cites update composite block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY(f, d) REFERENCES pp'));
        $t->true(is_string($source) && str_contains($source, 'ON UPDATE SET DEFAULT'));
        $t->true(is_string($source) && str_contains($source, 'do_test fkey2-9.2.3'));
    },
];

for ($i = 1; $i <= 100; ++$i) {
    $default = $i * 10 + 1;
    $deletedKey = $i * 10 + 2;
    $updatedKey = $i * 10 + 8;
    $parents = [
        ['a' => $default, 'b' => 'default-' . $i],
        ['a' => $deletedKey, 'b' => 'delete-target-' . $i],
        ['a' => $updatedKey, 'b' => 'update-target-' . $i],
    ];
    $children = [
        ['c' => $i * 100 + 1, 'd' => $deletedKey, 'label' => 'delete-child-a'],
        ['c' => $i * 100 + 2, 'd' => $updatedKey, 'label' => 'update-child-a'],
        ['c' => $i * 100 + 3, 'd' => null, 'label' => 'loose-child'],
    ];

    $deleteCommit = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2SetDefaultActionPlan(
        $parents,
        $children,
        ['operation' => 'delete', 'parent_key' => $deletedKey, 'default' => $default, 'deferred' => true]
    );
    $deleteMissingDefault = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2SetDefaultActionPlan(
        array_values(array_filter($parents, static fn (array $row): bool => $row['a'] !== $default)),
        $children,
        ['operation' => 'delete', 'parent_key' => $deletedKey, 'default' => $default, 'deferred' => true]
    );
    $deleteRepairedDefault = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2SetDefaultActionPlan(
        array_values(array_filter($parents, static fn (array $row): bool => $row['a'] !== $default)),
        $children,
        ['operation' => 'delete', 'parent_key' => $deletedKey, 'default' => $default, 'deferred' => true, 'insert_default_parent' => true]
    );
    $updateCommit = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2SetDefaultActionPlan(
        $parents,
        $children,
        ['operation' => 'update', 'parent_key' => $updatedKey, 'new_parent_key' => $updatedKey + 1000, 'default' => $default, 'deferred' => true]
    );

    foreach ([
        'source' => 'fkey2.test fkey2-9.1.1..9.1.5',
        'operation' => 'foreign-key-set-default-action',
        'status' => 'commit-ok',
        'statement_operation' => 'delete',
        'default_key' => $default,
        'parent_keys' => [$default, $updatedKey],
        'child_keys' => [$default, $updatedKey, null],
        'attempted_child_keys' => [$default, $updatedKey, null],
        'action_count' => 1,
        'actions.0.action' => 'set-default-child',
        'actions.0.old_parent_key' => $deletedKey,
        'actions.0.new_parent_key' => $default,
        'violation_count' => 0,
        'rolled_back' => false,
        'dependencies.0' => 'sqlite-fkey2-set-default-delete-rewrites-child-key',
    ] as $path => $expected) {
        $tests['fkey2-9.1 set default delete committed dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deleteCommit, $path, $expected, $value): void {
            $t->same($expected, $value($deleteCommit(), (string) $path));
        };
    }

    foreach ([
        'status' => 'rolled-back',
        'parent_keys' => [$deletedKey, $updatedKey],
        'child_keys' => [$deletedKey, $updatedKey, null],
        'attempted_child_keys' => [$default, $updatedKey, null],
        'violation_count' => 1,
        'violations.0.pid' => $default,
        'rollback_parent_keys' => [$deletedKey, $updatedKey],
        'rollback_child_keys' => [$deletedKey, $updatedKey, null],
        'dependencies.2' => 'sqlite-fkey2-set-default-missing-parent-fails-at-constraint-check',
    ] as $path => $expected) {
        $tests['fkey2-9.1 set default delete missing default rolls back dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deleteMissingDefault, $path, $expected, $value): void {
            $t->same($expected, $value($deleteMissingDefault(), (string) $path));
        };
    }

    foreach ([
        'status' => 'commit-ok',
        'parent_keys' => [$updatedKey, $default],
        'child_keys' => [$default, $updatedKey, null],
        'actions.0.action' => 'insert-default-parent',
        'actions.1.action' => 'set-default-child',
        'action_count' => 2,
        'dependencies.3' => 'sqlite-fkey2-set-default-existing-parent-commits',
    ] as $path => $expected) {
        $tests['fkey2-9.1 set default delete repaired default commits dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deleteRepairedDefault, $path, $expected, $value): void {
            $t->same($expected, $value($deleteRepairedDefault(), (string) $path));
        };
    }

    foreach ([
        'source' => 'fkey2.test fkey2-9.2.1..9.2.3',
        'status' => 'commit-ok',
        'statement_operation' => 'update',
        'parent_keys' => [$default, $deletedKey, $updatedKey + 1000],
        'child_keys' => [$deletedKey, $default, null],
        'actions.0.action' => 'update-parent',
        'actions.1.action' => 'set-default-child',
        'actions.1.old_parent_key' => $updatedKey,
        'actions.1.new_parent_key' => $default,
        'violation_count' => 0,
        'dependencies.1' => 'sqlite-fkey2-set-default-update-rewrites-composite-child-key',
    ] as $path => $expected) {
        $tests['fkey2-9.2 set default update committed dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($updateCommit, $path, $expected, $value): void {
            $t->same($expected, $value($updateCommit(), (string) $path));
        };
    }
}

$tests['real upstream fkey2 set default rejects unsupported operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2SetDefaultActionPlan(
        [],
        [],
        ['operation' => 'insert', 'parent_key' => 1, 'default' => 0]
    ));
};

$tests['real upstream fkey2 set default rejects update without target key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2SetDefaultActionPlan(
        [['a' => 1]],
        [['c' => 1, 'd' => 1]],
        ['operation' => 'update', 'parent_key' => 1, 'default' => 0]
    ));
};

return $tests;
