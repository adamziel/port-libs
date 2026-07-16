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
    'real upstream fkey6 deferred restrict trigger repair cites pragma evidence' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'EVIDENCE-OF: R-18981-16292'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA defer_foreign_keys = 1'));
    },
    'real upstream fkey6 deferred restrict trigger repair cites trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER p2t AFTER DELETE ON p2'));
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 3.3.4'));
    },
];

for ($i = 1; $i <= 70; ++$i) {
    $deleteKey = $i * 10;
    $otherKey = $deleteKey + 1;
    $parents = [
        ['setting_id' => $deleteKey, 'label' => 'deleted-' . $i],
        ['setting_id' => $otherKey, 'label' => 'stable-' . $i],
    ];
    $children = [
        ['entry_id' => $i * 100 + 1, 'setting_id' => $deleteKey],
        ['entry_id' => $i * 100 + 2, 'setting_id' => $otherKey],
    ];
    if ($i % 3 === 0) {
        $children[] = ['entry_id' => $i * 100 + 3, 'setting_id' => null];
    }
    if ($i % 5 === 0) {
        $children[] = ['entry_id' => $i * 100 + 4, 'setting_id' => $deleteKey];
    }

    $deferredRepair = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredRestrictDeleteTriggerRepair(
        $parents,
        $children,
        'setting_id',
        'setting_id',
        $deleteKey,
        true,
        true
    );
    $immediateRestrict = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredRestrictDeleteTriggerRepair(
        $parents,
        $children,
        'setting_id',
        'setting_id',
        $deleteKey,
        false,
        true
    );
    $deferredNoRepair = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredRestrictDeleteTriggerRepair(
        $parents,
        $children,
        'setting_id',
        'setting_id',
        $deleteKey,
        true,
        false
    );

    $case = 'real upstream fkey6 deferred restrict trigger repair dynamic ' . $i;
    $matchingChildIndexes = array_keys(array_filter(
        $children,
        static fn (array $child): bool => ($child['setting_id'] ?? null) === $deleteKey
    ));
    $expectedChildKeys = array_column($children, 'setting_id');

    foreach ([
        'source' => 'fkey6.test 3.3.1..3.3.4',
        'operation' => 'deferred-restrict-delete-trigger-repair',
        'status' => 'commit-ok',
        'defer_foreign_keys' => true,
        'after_delete_trigger_repair' => true,
        'deleted_parent_keys' => [$deleteKey],
        'trigger_inserted_keys' => [$deleteKey],
        'referencing_child_indexes' => $matchingChildIndexes,
        'deferred_violation_count' => 0,
        'child_keys_after_commit' => $expectedChildKeys,
        'commit_boundary' => 'outer-commit-after-trigger-repair',
        'dependencies.0' => 'sqlite-fkey6-defer-foreign-keys-delays-restrict',
        'dependencies.1' => 'sqlite-fkey6-after-delete-trigger-can-repair-deferred-restrict',
    ] as $path => $expected) {
        $tests[$case . ' repaired ' . $path] = static function (TestRunner $t) use ($deferredRepair, $path, $expected, $value): void {
            $t->same($expected, $value($deferredRepair(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'defer_foreign_keys' => false,
        'deleted_parent_keys' => [],
        'trigger_inserted_keys' => [],
        'referencing_child_indexes' => $matchingChildIndexes,
        'deferred_violation_count' => 0,
        'parent_keys_after_commit' => [$deleteKey, $otherKey],
        'commit_boundary' => 'restrict-checked-before-trigger-repair',
        'dependencies.0' => 'sqlite-fkey6-restrict-is-immediate-without-defer-foreign-keys',
    ] as $path => $expected) {
        $tests[$case . ' immediate restrict ' . $path] = static function (TestRunner $t) use ($immediateRestrict, $path, $expected, $value): void {
            $t->same($expected, $value($immediateRestrict(), (string) $path));
        };
    }

    foreach ([
        'status' => 'deferred-commit-failed',
        'defer_foreign_keys' => true,
        'after_delete_trigger_repair' => false,
        'deleted_parent_keys' => [$deleteKey],
        'trigger_inserted_keys' => [],
        'referencing_child_indexes' => $matchingChildIndexes,
        'deferred_violation_count' => count($matchingChildIndexes),
        'parent_keys_after_commit' => [$deleteKey, $otherKey],
        'commit_boundary' => 'outer-commit-foreign-key-check',
    ] as $path => $expected) {
        $tests[$case . ' no repair ' . $path] = static function (TestRunner $t) use ($deferredNoRepair, $path, $expected, $value): void {
            $t->same($expected, $value($deferredNoRepair(), (string) $path));
        };
    }

    $tests[$case . ' repaired statement restores deleted key after stable row'] = static function (TestRunner $t) use ($deferredRepair, $deleteKey, $otherKey): void {
        $t->same([$otherKey, $deleteKey], $deferredRepair()['parent_keys_after_statement']);
    };
    $tests[$case . ' no repair reports first missing parent key'] = static function (TestRunner $t) use ($deferredNoRepair, $deleteKey): void {
        $t->same($deleteKey, $deferredNoRepair()['violations'][0]['child_key']);
    };
    $tests[$case . ' invalid parent column is rejected'] = static function (TestRunner $t) use ($parents, $children, $deleteKey): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::deferredRestrictDeleteTriggerRepair($parents, $children, 'bad-key', 'setting_id', $deleteKey, true));
    };
}

return $tests;
