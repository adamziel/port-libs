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
    'real upstream fkey8 deferred affinity cites late parent insert scenario' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE child('));
        $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY(c) REFERENCES parent(p) DEFERRABLE INITIALLY DEFERRED'));
        $t->true(is_string($source) && str_contains($source, "INSERT INTO child VALUES(123);"));
        $t->true(is_string($source) && str_contains($source, "INSERT INTO parent VALUES('123');"));
    },
    'real upstream fkey8 deferred affinity cites parent update scenario' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
        $t->true(is_string($source) && str_contains($source, 'do_execsql_test 5.2'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE parent SET p ='));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA integrity_check;'));
    },
];

for ($i = 1; $i <= 110; ++$i) {
    $lateChild = $i * 100 + 23;
    $updateSeed = $i * 100 + 44;
    $updatedParent = $i * 100 + 56;
    $missingChild = $i * 100 + 77;

    $lateParentPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredAffinityParentSatisfaction(
        [],
        [$lateChild],
        [(string) $lateChild]
    );
    $updatedParentPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredAffinityParentSatisfaction(
        [$updateSeed],
        [$updatedParent],
        [],
        [$updateSeed => (string) $updatedParent]
    );
    $missingParentPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredAffinityParentSatisfaction(
        [$updateSeed],
        [$missingChild],
        [],
        [$updateSeed => (string) ($updatedParent + 1)]
    );

    $case = 'real upstream fkey8 deferred affinity dynamic ' . $i;
    foreach ([
        'source' => 'fkey8.test fkey8-5.0..5.3',
        'operation' => 'deferred-foreign-key-affinity-parent-satisfaction',
        'status' => 'commit-ok',
        'deferred_child_keys.0' => $lateChild,
        'inserted_parent_keys.0' => (string) $lateChild,
        'parent_keys_after_commit.0' => (string) $lateChild,
        'child_keys_after_commit.0' => $lateChild,
        'violation_count' => 0,
        'integrity_check' => 'ok',
        'deferred_counter_satisfied_by_late_parent_insert' => true,
        'deferred_counter_satisfied_by_parent_update' => false,
        'dependencies.0' => 'sqlite-fkey8-deferred-child-insert-can-be-satisfied-by-late-parent',
        'dependencies.1' => 'sqlite-fkey8-parent-affinity-controls-deferred-comparison',
    ] as $path => $expected) {
        $tests[$case . ' late parent ' . $path] = static function (TestRunner $t) use ($lateParentPlan, $path, $expected, $value): void {
            $t->same($expected, $value($lateParentPlan(), (string) $path));
        };
    }

    foreach ([
        'source' => 'fkey8.test fkey8-5.0..5.3',
        'status' => 'commit-ok',
        'initial_parent_keys.0' => $updateSeed,
        'deferred_child_keys.0' => $updatedParent,
        'updated_parent_keys.0.old' => $updateSeed,
        'updated_parent_keys.0.new' => (string) $updatedParent,
        'parent_keys_after_commit.0' => (string) $updatedParent,
        'child_keys_after_commit.0' => $updatedParent,
        'violation_count' => 0,
        'integrity_check' => 'ok',
        'deferred_counter_satisfied_by_late_parent_insert' => false,
        'deferred_counter_satisfied_by_parent_update' => true,
        'dependencies.2' => 'sqlite-fkey8-parent-update-can-satisfy-deferred-child-counter',
    ] as $path => $expected) {
        $tests[$case . ' parent update ' . $path] = static function (TestRunner $t) use ($updatedParentPlan, $path, $expected, $value): void {
            $t->same($expected, $value($updatedParentPlan(), (string) $path));
        };
    }

    foreach ([
        'status' => 'deferred-commit-failed',
        'initial_parent_keys.0' => $updateSeed,
        'deferred_child_keys.0' => $missingChild,
        'updated_parent_keys.0.new' => (string) ($updatedParent + 1),
        'parent_keys_after_commit.0' => $updateSeed,
        'child_keys_after_commit' => [],
        'violation_count' => 1,
        'violations.0.child_rowid' => 1,
        'violations.0.child_key' => $missingChild,
        'violations.0.parent_table' => 'parent',
        'integrity_check' => 'foreign-key-constraint-failed',
    ] as $path => $expected) {
        $tests[$case . ' missing parent ' . $path] = static function (TestRunner $t) use ($missingParentPlan, $path, $expected, $value): void {
            $t->same($expected, $value($missingParentPlan(), (string) $path));
        };
    }
}

return $tests;
