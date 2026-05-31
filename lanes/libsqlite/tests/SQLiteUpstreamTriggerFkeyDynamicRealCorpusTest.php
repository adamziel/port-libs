<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDeferredForeignKeyTransactionPlan;

$fkey22Operations = [
    ['case' => 'fkey2-2-1', 'op' => 'schema'],
    ['case' => 'fkey2-2-1', 'op' => 'insert-node', 'nodeid' => 1, 'parent' => 0, 'expect_ok' => false],
    ['case' => 'fkey2-2-2', 'op' => 'begin'],
    ['case' => 'fkey2-2-3', 'op' => 'insert-node', 'nodeid' => 1, 'parent' => 0, 'expect_pending' => true],
    ['case' => 'fkey2-2-4', 'op' => 'update-node-parent', 'nodeid' => 1, 'parent' => null],
    ['case' => 'fkey2-2-5', 'op' => 'commit'],
    ['case' => 'fkey2-2-6', 'op' => 'select-node', 'expect_result' => [1, null]],
    ['case' => 'fkey2-2-7', 'op' => 'begin'],
    ['case' => 'fkey2-2-8', 'op' => 'insert-leaf', 'cellid' => 'a', 'parent' => 2, 'expect_pending' => true],
    ['case' => 'fkey2-2-9', 'op' => 'insert-node', 'nodeid' => 2, 'parent' => 0, 'expect_pending' => true],
    ['case' => 'fkey2-2-10', 'op' => 'update-node-parent', 'nodeid' => 2, 'parent' => 1],
    ['case' => 'fkey2-2-11', 'op' => 'commit'],
    ['case' => 'fkey2-2-12', 'op' => 'select-node', 'expect_result' => [1, null, 2, 1]],
    ['case' => 'fkey2-2-13', 'op' => 'select-leaf', 'expect_result' => ['a', 2]],
    ['case' => 'fkey2-2-14', 'op' => 'begin'],
    ['case' => 'fkey2-2-15', 'op' => 'delete-node', 'nodeid' => 2, 'expect_pending' => true],
    ['case' => 'fkey2-2-16', 'op' => 'insert-node', 'nodeid' => 2, 'parent' => null],
    ['case' => 'fkey2-2-17', 'op' => 'commit'],
    ['case' => 'fkey2-2-18', 'op' => 'select-node', 'expect_result' => [1, null, 2, null]],
    ['case' => 'fkey2-2-19', 'op' => 'select-leaf', 'expect_result' => ['a', 2]],
    ['case' => 'fkey2-2-20', 'op' => 'begin'],
    ['case' => 'fkey2-2-21', 'op' => 'insert-leaf', 'cellid' => 'b', 'parent' => 1],
    ['case' => 'fkey2-2-22', 'op' => 'savepoint', 'name' => 'save'],
    ['case' => 'fkey2-2-23', 'op' => 'delete-node', 'nodeid' => 1, 'expect_pending' => true],
    ['case' => 'fkey2-2-24', 'op' => 'rollback-to', 'name' => 'save'],
    ['case' => 'fkey2-2-25', 'op' => 'commit'],
    ['case' => 'fkey2-2-26', 'op' => 'select-node', 'expect_result' => [1, null, 2, null]],
    ['case' => 'fkey2-2-27', 'op' => 'select-leaf', 'expect_result' => ['a', 2, 'b', 1]],
    ['case' => 'fkey2-2-28', 'op' => 'begin'],
    ['case' => 'fkey2-2-29', 'op' => 'insert-leaf', 'cellid' => 'c', 'parent' => 1],
    ['case' => 'fkey2-2-30', 'op' => 'savepoint', 'name' => 'save'],
    ['case' => 'fkey2-2-31', 'op' => 'delete-node', 'nodeid' => 1, 'expect_pending' => true],
    ['case' => 'fkey2-2-32', 'op' => 'release', 'name' => 'save', 'expect_pending' => true],
    ['case' => 'fkey2-2-33', 'op' => 'delete-leaf', 'cellid' => 'b', 'expect_pending' => true],
    ['case' => 'fkey2-2-34', 'op' => 'delete-leaf', 'cellid' => 'c'],
    ['case' => 'fkey2-2-35', 'op' => 'commit'],
    ['case' => 'fkey2-2-36', 'op' => 'select-node', 'expect_result' => [2, null]],
    ['case' => 'fkey2-2-37', 'op' => 'select-leaf', 'expect_result' => ['a', 2]],
    ['case' => 'fkey2-2-38', 'op' => 'savepoint', 'name' => 'outer'],
    ['case' => 'fkey2-2-39', 'op' => 'insert-leaf', 'cellid' => 'd', 'parent' => 3, 'expect_pending' => true],
    ['case' => 'fkey2-2-40', 'op' => 'release', 'name' => 'outer', 'expect_ok' => false, 'expect_pending' => true],
    ['case' => 'fkey2-2-41', 'op' => 'insert-leaf', 'cellid' => 'e', 'parent' => 3, 'expect_pending' => true],
    ['case' => 'fkey2-2-42', 'op' => 'insert-node', 'nodeid' => 3, 'parent' => 2],
    ['case' => 'fkey2-2-43', 'op' => 'release', 'name' => 'outer'],
    ['case' => 'fkey2-2-44', 'op' => 'savepoint', 'name' => 'outer'],
    ['case' => 'fkey2-2-45', 'op' => 'delete-node', 'nodeid' => 3, 'expect_pending' => true],
    ['case' => 'fkey2-2-47', 'op' => 'insert-node', 'nodeid' => 3, 'parent' => 2],
    ['case' => 'fkey2-2-48', 'op' => 'rollback-to', 'name' => 'outer'],
    ['case' => 'fkey2-2-49', 'op' => 'release', 'name' => 'outer'],
    ['case' => 'fkey2-2-50', 'op' => 'savepoint', 'name' => 'outer'],
    ['case' => 'fkey2-2-51', 'op' => 'insert-leaf', 'cellid' => 'f', 'parent' => 4, 'expect_pending' => true],
    ['case' => 'fkey2-2-52', 'op' => 'savepoint', 'name' => 'inner', 'expect_pending' => true],
    ['case' => 'fkey2-2-53', 'op' => 'insert-leaf', 'cellid' => 'g', 'parent' => 4, 'expect_pending' => true],
    ['case' => 'fkey2-2-54', 'op' => 'release', 'name' => 'outer', 'expect_ok' => false, 'expect_pending' => true],
    ['case' => 'fkey2-2-55', 'op' => 'rollback-to', 'name' => 'inner', 'expect_pending' => true],
    ['case' => 'fkey2-2-56', 'op' => 'commit', 'expect_ok' => false, 'expect_pending' => true],
    ['case' => 'fkey2-2-57', 'op' => 'insert-node', 'nodeid' => 4, 'parent' => null],
    ['case' => 'fkey2-2-58', 'op' => 'release', 'name' => 'outer'],
    ['case' => 'fkey2-2-59', 'op' => 'select-node', 'expect_result' => [2, null, 3, 2, 4, null]],
    ['case' => 'fkey2-2-60', 'op' => 'select-leaf', 'expect_result' => ['a', 2, 'd', 3, 'e', 3, 'f', 4]],
    ['case' => 'fkey2-2-61', 'op' => 'begin'],
    ['case' => 'fkey2-2-62', 'op' => 'delete-all-leaf'],
    ['case' => 'fkey2-2-63', 'op' => 'delete-all-node'],
    ['case' => 'fkey2-2-64', 'op' => 'insert-leaf', 'cellid' => 'a', 'parent' => 1, 'expect_pending' => true],
    ['case' => 'fkey2-2-65', 'op' => 'insert-leaf', 'cellid' => 'b', 'parent' => 2, 'expect_pending' => true],
    ['case' => 'fkey2-2-66', 'op' => 'insert-leaf', 'cellid' => 'c', 'parent' => 1, 'expect_pending' => true],
    ['case' => 'fkey2-2-test-67', 'op' => 'insert-node-autofill', 'expect_ok' => false, 'expect_pending' => true],
    ['case' => 'fkey2-2-68', 'op' => 'commit', 'expect_ok' => false, 'expect_pending' => true],
    ['case' => 'fkey2-2-69', 'op' => 'insert-node', 'nodeid' => 1, 'parent' => null, 'expect_pending' => true],
    ['case' => 'fkey2-2-70', 'op' => 'insert-node', 'nodeid' => 2, 'parent' => null],
    ['case' => 'fkey2-2-71', 'op' => 'commit'],
    ['case' => 'fkey2-2-72', 'op' => 'begin'],
    ['case' => 'fkey2-2-73', 'op' => 'delete-all-node', 'expect_pending' => true],
    ['case' => 'fkey2-2-74', 'op' => 'insert-node-distinct-leaf-parents'],
    ['case' => 'fkey2-2-75', 'op' => 'commit'],
];

$fkey22Plan = static fn (): array => SQLiteDeferredForeignKeyTransactionPlan::replay($fkey22Operations);
$traceByCase = static function () use ($fkey22Plan): array {
    $byCase = [];
    foreach ($fkey22Plan()['trace'] as $entry) {
        $byCase[$entry['case']] = $entry;
    }

    return $byCase;
};

$tests = [
    'upstream fkey2 deferred transaction final node rows' => static function (TestRunner $t) use ($fkey22Plan): void {
        $t->same([['nodeid' => 1, 'parent' => null], ['nodeid' => 2, 'parent' => null]], $fkey22Plan()['node']);
    },
    'upstream fkey2 deferred transaction final open state' => static function (TestRunner $t) use ($fkey22Plan): void {
        $t->same(false, $fkey22Plan()['open_transaction']);
    },
    'upstream fkey2 deferred transaction final savepoints cleared' => static function (TestRunner $t) use ($fkey22Plan): void {
        $t->same([], $fkey22Plan()['savepoints']);
    },
    'upstream fkey2 deferred transaction final violations cleared' => static function (TestRunner $t) use ($fkey22Plan): void {
        $t->same([], $fkey22Plan()['violations']);
    },
    'upstream fkey2 deferred transaction dependency marker' => static function (TestRunner $t) use ($fkey22Plan): void {
        $t->same(true, in_array('sqlite-upstream-fkey2-2-deferred-foreign-keys', $fkey22Plan()['dependencies'], true));
    },
];

foreach ($fkey22Operations as $ordinal => $operation) {
    $case = $operation['case'];
    $tests["upstream fkey2 deferred transaction {$case} operation"] = static function (TestRunner $t) use ($traceByCase, $case, $operation): void {
        $t->same($operation['op'], $traceByCase()[$case]['op']);
    };
    $tests["upstream fkey2 deferred transaction {$case} ok flag"] = static function (TestRunner $t) use ($traceByCase, $case, $operation): void {
        $t->same($operation['expect_ok'] ?? true, $traceByCase()[$case]['ok']);
    };
    $tests["upstream fkey2 deferred transaction {$case} pending flag"] = static function (TestRunner $t) use ($traceByCase, $case, $operation): void {
        $entry = $traceByCase()[$case];
        $t->same($operation['expect_pending'] ?? false, $entry['deferred_check_pending']);
    };
    $tests["upstream fkey2 deferred transaction {$case} statement rollback flag"] = static function (TestRunner $t) use ($traceByCase, $case, $operation): void {
        $t->same(($operation['expect_ok'] ?? true) === false, $traceByCase()[$case]['rolled_back_statement']);
    };
    $tests["upstream fkey2 deferred transaction {$case} trace ordinal"] = static function (TestRunner $t) use ($fkey22Plan, $case, $ordinal): void {
        $t->same($case, $fkey22Plan()['trace'][$ordinal]['case']);
    };
    $tests["upstream fkey2 deferred transaction {$case} node ids sorted"] = static function (TestRunner $t) use ($traceByCase, $case): void {
        $nodeIds = array_column($traceByCase()[$case]['node'], 'nodeid');
        $sorted = $nodeIds;
        sort($sorted);
        $t->same($sorted, $nodeIds);
    };
    $tests["upstream fkey2 deferred transaction {$case} leaf ids sorted"] = static function (TestRunner $t) use ($traceByCase, $case): void {
        $leafIds = array_column($traceByCase()[$case]['leaf'], 'cellid');
        $sorted = $leafIds;
        sort($sorted);
        $t->same($sorted, $leafIds);
    };
    if (array_key_exists('expect_result', $operation)) {
        $tests["upstream fkey2 deferred transaction {$case} selected result"] = static function (TestRunner $t) use ($traceByCase, $case, $operation): void {
            $t->same($operation['expect_result'], $traceByCase()[$case]['result']);
        };
    }
}

$checkpointExpectations = [
    'fkey2-2-6' => ['node' => [1, null], 'leaf' => []],
    'fkey2-2-13' => ['node' => [1, null, 2, 1], 'leaf' => ['a', 2]],
    'fkey2-2-19' => ['node' => [1, null, 2, null], 'leaf' => ['a', 2]],
    'fkey2-2-27' => ['node' => [1, null, 2, null], 'leaf' => ['a', 2, 'b', 1]],
    'fkey2-2-37' => ['node' => [2, null], 'leaf' => ['a', 2]],
    'fkey2-2-43' => ['node' => [2, null, 3, 2], 'leaf' => ['a', 2, 'd', 3, 'e', 3]],
    'fkey2-2-49' => ['node' => [2, null, 3, 2], 'leaf' => ['a', 2, 'd', 3, 'e', 3]],
    'fkey2-2-60' => ['node' => [2, null, 3, 2, 4, null], 'leaf' => ['a', 2, 'd', 3, 'e', 3, 'f', 4]],
    'fkey2-2-71' => ['node' => [1, null, 2, null], 'leaf' => ['a', 1, 'b', 2, 'c', 1]],
    'fkey2-2-75' => ['node' => [1, null, 2, null], 'leaf' => ['a', 1, 'b', 2, 'c', 1]],
];

foreach ($checkpointExpectations as $case => $expected) {
    $tests["upstream fkey2 deferred transaction {$case} node checkpoint"] = static function (TestRunner $t) use ($traceByCase, $case, $expected): void {
        $t->same($expected['node'], $traceByCase()[$case]['node_flat']);
    };
    $tests["upstream fkey2 deferred transaction {$case} leaf checkpoint"] = static function (TestRunner $t) use ($traceByCase, $case, $expected): void {
        $t->same($expected['leaf'], $traceByCase()[$case]['leaf_flat']);
    };
    $tests["upstream fkey2 deferred transaction {$case} clean checkpoint"] = static function (TestRunner $t) use ($traceByCase, $case): void {
        $t->same(0, $traceByCase()[$case]['violation_count']);
    };
}

$failureExpectations = [
    'fkey2-2-1' => 'FOREIGN KEY constraint failed',
    'fkey2-2-40' => 'FOREIGN KEY constraint failed',
    'fkey2-2-54' => 'FOREIGN KEY constraint failed',
    'fkey2-2-56' => 'FOREIGN KEY constraint failed',
    'fkey2-2-test-67' => 'UNIQUE constraint failed: node.nodeid',
    'fkey2-2-68' => 'FOREIGN KEY constraint failed',
];

foreach ($failureExpectations as $case => $message) {
    $tests["upstream fkey2 deferred transaction {$case} error message"] = static function (TestRunner $t) use ($traceByCase, $case, $message): void {
        $t->same($message, $traceByCase()[$case]['error']);
    };
}

return $tests;
