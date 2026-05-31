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
    'real upstream e_fkey savepoint boundary cites deferred savepoint section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test');

        $t->true(is_string($source) && str_contains($source, 'e_fkey-36.1'));
        $t->true(is_string($source) && str_contains($source, 'A nested savepoint transaction may be'));
        $t->true(is_string($source) && str_contains($source, 'e_fkey-37.2'));
        $t->true(is_string($source) && str_contains($source, 'A transaction savepoint'));
        $t->true(is_string($source) && str_contains($source, 'e_fkey-38.3'));
        $t->true(is_string($source) && str_contains($source, 'the nested savepoints remain open'));
    },
];

for ($i = 1; $i <= 220; ++$i) {
    $base = $i * 10;
    $rows = [
        ['a' => $base + 1, 'b' => $base + 1],
        ['a' => $base + 2, 'b' => $base + 2],
        ['a' => $base + 3, 'b' => $base + 3],
    ];

    $nestedRelease = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredForeignKeySavepointBoundaryPlan($rows, [
        ['action' => 'begin'],
        ['action' => 'savepoint', 'name' => 'one'],
        ['action' => 'insert', 'a' => $base + 4, 'b' => $base + 5],
        ['action' => 'release', 'name' => 'one'],
        ['action' => 'commit'],
        ['action' => 'update-a', 'from' => $base + 4, 'to' => $base + 5],
        ['action' => 'commit'],
    ]);

    foreach ([
        'source' => 'e_fkey.test e_fkey-36.1..38.8',
        'operation' => 'deferred-foreign-key-savepoint-boundary',
        'status' => 'repaired-after-blocked-boundary',
        'blocked_boundary_count' => 1,
        'blocked_boundaries.0.step' => 'commit',
        'blocked_boundaries.0.violation_count' => 1,
        'events.3.step' => 'release',
        'events.3.status' => 'ok',
        'events.4.status' => 'constraint-failed',
        'events.5.status' => 'ok',
        'events.6.status' => 'ok',
        'transaction_open' => false,
        'open_savepoints' => [],
        'violation_count' => 0,
        'row_pairs.3' => ($base + 5) . ':' . ($base + 5),
        'dependencies.0' => 'sqlite-e-fkey-nested-savepoint-release-can-leave-deferred-violation',
        'dependencies.2' => 'sqlite-e-fkey-failed-commit-preserves-nested-savepoints',
    ] as $path => $expected) {
        $tests['e_fkey-36 nested release defers violation until outer commit dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($nestedRelease, $path, $expected, $value): void {
            $t->same($expected, $value($nestedRelease(), (string) $path));
        };
    }

    $transactionSavepoint = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredForeignKeySavepointBoundaryPlan($rows, [
        ['action' => 'savepoint', 'name' => 'outer'],
        ['action' => 'savepoint', 'name' => 'inner'],
        ['action' => 'insert', 'a' => $base + 6, 'b' => $base + 7],
        ['action' => 'release', 'name' => 'inner'],
        ['action' => 'release', 'name' => 'outer'],
        ['action' => 'update-a', 'from' => $base + 6, 'to' => $base + 7],
        ['action' => 'release', 'name' => 'outer'],
    ]);

    foreach ([
        'status' => 'repaired-after-blocked-boundary',
        'blocked_boundary_count' => 1,
        'blocked_boundaries.0.step' => 'release',
        'blocked_boundaries.0.name' => 'outer',
        'blocked_boundaries.0.open_savepoints' => ['outer'],
        'events.3.open_savepoints' => ['outer'],
        'events.4.status' => 'constraint-failed',
        'events.4.open_savepoints' => ['outer'],
        'events.6.status' => 'ok',
        'transaction_open' => false,
        'open_savepoints' => [],
        'violation_count' => 0,
        'row_pairs.3' => ($base + 7) . ':' . ($base + 7),
        'dependencies.1' => 'sqlite-e-fkey-transaction-savepoint-release-checks-deferred-violations',
    ] as $path => $expected) {
        $tests['e_fkey-37 transaction savepoint release checks deferred violation dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($transactionSavepoint, $path, $expected, $value): void {
            $t->same($expected, $value($transactionSavepoint(), (string) $path));
        };
    }

    $rollbackToInner = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredForeignKeySavepointBoundaryPlan($rows, [
        ['action' => 'begin'],
        ['action' => 'insert', 'a' => $base + 4, 'b' => $base + 4],
        ['action' => 'savepoint', 'name' => 'b'],
        ['action' => 'insert', 'a' => $base + 5, 'b' => $base + 5],
        ['action' => 'savepoint', 'name' => 'c'],
        ['action' => 'insert', 'a' => $base + 6, 'b' => $base + 7],
        ['action' => 'commit'],
        ['action' => 'rollback-to', 'name' => 'c'],
        ['action' => 'commit'],
    ]);

    foreach ([
        'status' => 'repaired-after-blocked-boundary',
        'blocked_boundary_count' => 1,
        'blocked_boundaries.0.step' => 'commit',
        'blocked_boundaries.0.open_savepoints' => ['b', 'c'],
        'events.6.status' => 'constraint-failed',
        'events.7.step' => 'rollback-to',
        'events.7.open_savepoints' => ['b', 'c'],
        'events.8.status' => 'ok',
        'events.8.open_savepoints' => [],
        'transaction_open' => false,
        'open_savepoints' => [],
        'violation_count' => 0,
        'row_pairs' => [($base + 1) . ':' . ($base + 1), ($base + 2) . ':' . ($base + 2), ($base + 3) . ':' . ($base + 3), ($base + 4) . ':' . ($base + 4), ($base + 5) . ':' . ($base + 5)],
        'dependencies.3' => 'sqlite-e-fkey-rollback-to-savepoint-restores-deferred-violation-counter',
    ] as $path => $expected) {
        $tests['e_fkey-38 rollback to nested savepoint preserves outer boundary dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($rollbackToInner, $path, $expected, $value): void {
            $t->same($expected, $value($rollbackToInner(), (string) $path));
        };
    }
}

$tests['real upstream e_fkey savepoint boundary rejects unsupported operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferredForeignKeySavepointBoundaryPlan(
        [['a' => 1, 'b' => 1]],
        [['action' => 'detach']]
    ));
};

return $tests;
