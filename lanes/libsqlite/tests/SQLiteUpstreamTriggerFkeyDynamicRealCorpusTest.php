<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDeferredForeignKeyTransactionPlan;
use PortLibs\LibSqlite\SQLiteUpstreamTriggerFkeyDynamicPlan;

$triggerGPlan = static fn (): array => SQLiteUpstreamTriggerFkeyDynamicPlan::triggerGRecursiveOnce();
$triggerGSingle = static fn (): array => $triggerGPlan()['single_select'];
$triggerGJoin = static fn (): array => $triggerGPlan()['join_select'];
$fkey8JournalPlan = static fn (): array => SQLiteUpstreamTriggerFkeyDynamicPlan::fkey8StatementJournal();
$fkey8JournalCases = static function () use ($fkey8JournalPlan): array {
    $cases = [];
    foreach ($fkey8JournalPlan()['cases'] as $case) {
        $cases[$case['case']] = $case;
    }

    return $cases;
};

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
    'upstream triggerG recursive trigger source filename' => static function (TestRunner $t) use ($triggerGPlan): void {
        $t->same('triggerG.test', $triggerGPlan()['source']);
    },
    'upstream triggerG recursive trigger enables recursion' => static function (TestRunner $t) use ($triggerGPlan): void {
        $t->same(true, $triggerGPlan()['recursive_triggers']);
    },
    'upstream triggerG recursive trigger dependency marker' => static function (TestRunner $t) use ($triggerGPlan): void {
        $t->same(true, in_array('sqlite-upstream-triggerG-recursive-op-once', $triggerGPlan()['dependencies'], true));
    },
    'upstream triggerG single select t3 recursion rows' => static function (TestRunner $t) use ($triggerGSingle): void {
        $t->same([2, 3, 4, 5], $triggerGSingle()['t3_rows']);
    },
    'upstream triggerG single select t2 rows match triggerG-110' => static function (TestRunner $t) use ($triggerGSingle): void {
        $t->same([202, 203, 302, 303, 402, 403, 502, 503], $triggerGSingle()['t2_rows']);
    },
    'upstream triggerG join select t2 rows match triggerG-200' => static function (TestRunner $t) use ($triggerGJoin): void {
        $t->same([20202, 20203, 20302, 20303, 30202, 30203, 30302, 30303, 40202, 40203, 40302, 40303, 50202, 50203, 50302, 50303], $triggerGJoin()['t2_rows']);
    },
    'upstream triggerG hex literal trigger reports overflow' => static function (TestRunner $t) use ($triggerGPlan): void {
        $t->same('hex literal too big: 0x2147483648e0e0099', $triggerGPlan()['hex_literal']['error']);
    },
    'upstream triggerG instead of delete sees old row' => static function (TestRunner $t) use ($triggerGPlan): void {
        $t->same(1234, $triggerGPlan()['instead_of_view']['old_row_visible']);
    },
    'upstream fkey8 statement journal source filename' => static function (TestRunner $t) use ($fkey8JournalPlan): void {
        $t->same('fkey8.test', $fkey8JournalPlan()['source']);
    },
    'upstream fkey8 statement journal dependency marker' => static function (TestRunner $t) use ($fkey8JournalPlan): void {
        $t->same(true, in_array('sqlite-upstream-fkey8-uses-statement-journal', $fkey8JournalPlan()['dependencies'], true));
    },
    'upstream fkey8 statement journal expected journal case count' => static function (TestRunner $t) use ($fkey8JournalPlan): void {
        $t->same(8, count($fkey8JournalPlan()['journal_cases']));
    },
    'upstream fkey8 statement journal expected no-journal case count' => static function (TestRunner $t) use ($fkey8JournalPlan): void {
        $t->same(5, count($fkey8JournalPlan()['no_journal_cases']));
    },
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

foreach ($triggerGSingle()['fires'] as $index => $fire) {
    $tests["upstream triggerG single select fire {$index} new c"] = static function (TestRunner $t) use ($triggerGSingle, $index, $fire): void {
        $t->same($fire['new_c'], $triggerGSingle()['fires'][$index]['new_c']);
    };
    $tests["upstream triggerG single select fire {$index} inserted next"] = static function (TestRunner $t) use ($triggerGSingle, $index, $fire): void {
        $t->same($fire['inserted_next'], $triggerGSingle()['fires'][$index]['inserted_next']);
    };
    $tests["upstream triggerG single select fire {$index} row additions"] = static function (TestRunner $t) use ($triggerGSingle, $index, $fire): void {
        $t->same($fire['rows_added'], $triggerGSingle()['fires'][$index]['rows_added']);
    };
}

foreach ($triggerGJoin()['fires'] as $index => $fire) {
    $tests["upstream triggerG join select fire {$index} new c"] = static function (TestRunner $t) use ($triggerGJoin, $index, $fire): void {
        $t->same($fire['new_c'], $triggerGJoin()['fires'][$index]['new_c']);
    };
    $tests["upstream triggerG join select fire {$index} inserted next"] = static function (TestRunner $t) use ($triggerGJoin, $index, $fire): void {
        $t->same($fire['inserted_next'], $triggerGJoin()['fires'][$index]['inserted_next']);
    };
    $tests["upstream triggerG join select fire {$index} row additions count"] = static function (TestRunner $t) use ($triggerGJoin, $index, $fire): void {
        $t->same(count($fire['rows_added']), count($triggerGJoin()['fires'][$index]['rows_added']));
    };
    foreach ($fire['rows_added'] as $rowIndex => $value) {
        $tests["upstream triggerG join select fire {$index} row {$rowIndex} value"] = static function (TestRunner $t) use ($triggerGJoin, $index, $rowIndex, $value): void {
            $t->same($value, $triggerGJoin()['fires'][$index]['rows_added'][$rowIndex]);
        };
    }
}

foreach ($fkey8JournalCases() as $case => $expectation) {
    $tests["upstream fkey8 {$case} uses statement journal flag"] = static function (TestRunner $t) use ($fkey8JournalCases, $case, $expectation): void {
        $t->same($expectation['uses_stmt_journal'], $fkey8JournalCases()[$case]['uses_stmt_journal']);
    };
    $tests["upstream fkey8 {$case} statement journal boolean"] = static function (TestRunner $t) use ($fkey8JournalCases, $case, $expectation): void {
        $t->same($expectation['statement_journal'], $fkey8JournalCases()[$case]['statement_journal']);
    };
    $tests["upstream fkey8 {$case} source filename"] = static function (TestRunner $t) use ($fkey8JournalCases, $case): void {
        $t->same('fkey8.test', $fkey8JournalCases()[$case]['source']);
    };
    $tests["upstream fkey8 {$case} SQL preserved"] = static function (TestRunner $t) use ($fkey8JournalCases, $case, $expectation): void {
        $t->same($expectation['sql'], $fkey8JournalCases()[$case]['sql']);
    };
    $tests["upstream fkey8 {$case} action preserved"] = static function (TestRunner $t) use ($fkey8JournalCases, $case, $expectation): void {
        $t->same($expectation['action'], $fkey8JournalCases()[$case]['action']);
    };
    $tests["upstream fkey8 {$case} reason is nonempty"] = static function (TestRunner $t) use ($fkey8JournalCases, $case): void {
        $t->same(true, $fkey8JournalCases()[$case]['reason'] !== '');
    };
}

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

$fkey7Plan = static fn (): array => SQLiteUpstreamTriggerFkeyDynamicPlan::fkey7();
$fkey7ReadCases = static function () use ($fkey7Plan): array {
    $cases = [];
    foreach ($fkey7Plan()['read_cases'] as $case) {
        $cases[$case['case']] = $case;
    }

    return $cases;
};

$tests['upstream fkey7 schema parent table primary key'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same(['a'], $fkey7Plan()['schema']['s1']['primary_key']);
};
$tests['upstream fkey7 schema par primary key'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same(['a'], $fkey7Plan()['schema']['par']['primary_key']);
};
$tests['upstream fkey7 corpus dependency marker'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same(true, in_array('sqlite-upstream-fkey7-read-dependencies', $fkey7Plan()['dependencies'], true));
};

$fkey7ReadExpectations = [
    'fkey7-1.2' => ['par', 's1'],
    'fkey7-1.3' => ['c1', 'c2', 'par'],
    'fkey7-1.4' => ['c3', 'par'],
    'fkey7-1.5' => ['c1', 'c2', 'c3', 'par', 's1'],
];

foreach ($fkey7ReadExpectations as $case => $reads) {
    $tests["upstream fkey7 {$case} reads exact dependency tables"] = static function (TestRunner $t) use ($fkey7ReadCases, $case, $reads): void {
        $t->same($reads, $fkey7ReadCases()[$case]['reads']);
    };
    $tests["upstream fkey7 {$case} read count"] = static function (TestRunner $t) use ($fkey7ReadCases, $case, $reads): void {
        $t->same(count($reads), $fkey7ReadCases()[$case]['read_count']);
    };
    $tests["upstream fkey7 {$case} reads parent table"] = static function (TestRunner $t) use ($fkey7ReadCases, $case): void {
        $t->same(true, $fkey7ReadCases()[$case]['reads_parent']);
    };
    $tests["upstream fkey7 {$case} source filename"] = static function (TestRunner $t) use ($fkey7ReadCases, $case): void {
        $t->same('fkey7.test', $fkey7ReadCases()[$case]['source']);
    };
    foreach (['c1', 'c2', 'c3', 'par', 's1'] as $table) {
        $tests["upstream fkey7 {$case} read membership {$table}"] = static function (TestRunner $t) use ($fkey7ReadCases, $case, $table, $reads): void {
            $t->same(in_array($table, $reads, true), in_array($table, $fkey7ReadCases()[$case]['reads'], true));
        };
    }
}

$tests['upstream fkey7 zeroblob literal FK failure code'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same('SQLITE_CONSTRAINT_FOREIGNKEY', $fkey7Plan()['zeroblob']['fkey7-2.1']['code']);
};
$tests['upstream fkey7 zeroblob literal leaves child empty'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same([], $fkey7Plan()['zeroblob']['fkey7-2.1']['child_rows']);
};
$tests['upstream fkey7 bound zeroblob FK failure code'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same('SQLITE_CONSTRAINT_FOREIGNKEY', $fkey7Plan()['zeroblob']['fkey7-2.2']['code']);
};
$tests['upstream fkey7 bound zeroblob byte count'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same(45, $fkey7Plan()['zeroblob']['fkey7-2.2']['bound_blob_bytes']);
};

foreach (['fkey7-4.1' => 'FOREIGN KEY constraint failed', 'fkey7-4.4' => 'UNIQUE constraint failed: child.c'] as $case => $message) {
    $tests["upstream fkey7 OR FAIL {$case} error precedence"] = static function (TestRunner $t) use ($fkey7Plan, $case, $message): void {
        $t->same($message, $fkey7Plan()['or_fail'][$case]['error']);
    };
}
foreach (['fkey7-4.2' => [], 'fkey7-4.5' => [123]] as $case => $rows) {
    $tests["upstream fkey7 OR FAIL {$case} child rows"] = static function (TestRunner $t) use ($fkey7Plan, $case, $rows): void {
        $t->same($rows, $fkey7Plan()['or_fail'][$case]['child_rows']);
    };
}
foreach (['fkey7-4.3', 'fkey7-4.6'] as $case) {
    $tests["upstream fkey7 OR FAIL {$case} foreign key check clean"] = static function (TestRunner $t) use ($fkey7Plan, $case): void {
        $t->same([], $fkey7Plan()['or_fail'][$case]['foreign_key_check']);
    };
}

$tests['upstream fkey7 stat4 analyze keeps child index'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same('c4_x', $fkey7Plan()['stat4']['child_index']);
};
$tests['upstream fkey7 stat4 analyze deferred violations clear'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same(0, $fkey7Plan()['stat4']['deferred_violation_count']);
};
$tests['upstream fkey7 stat4 parent insert visible'] = static function (TestRunner $t) use ($fkey7Plan): void {
    $t->same([1, 2, 3, 4], $fkey7Plan()['stat4']['parent_rows']);
};

$trigger2Plan = static fn (): array => SQLiteUpstreamTriggerFkeyDynamicPlan::trigger2RowTiming();
$trigger2Cases = static function () use ($trigger2Plan): array {
    $cases = [];
    foreach ($trigger2Plan()['row_timing_cases'] as $case) {
        $cases[$case['case']] = $case;
    }

    return $cases;
};

$tests['upstream trigger2 disables recursive triggers for row timing corpus'] = static function (TestRunner $t) use ($trigger2Plan): void {
    $t->same(false, $trigger2Plan()['recursive_triggers']);
};
$tests['upstream trigger2 corpus dependency marker'] = static function (TestRunner $t) use ($trigger2Plan): void {
    $t->same(true, in_array('sqlite-upstream-trigger2-before-after-row-order', $trigger2Plan()['dependencies'], true));
};

$trigger2ExpectedUpdate = [
    [1, 1, 2, 4, 6, 10, 20],
    [2, 1, 2, 13, 24, 10, 20],
    [3, 3, 4, 13, 24, 30, 40],
    [4, 3, 4, 40, 60, 30, 40],
];
$trigger2ExpectedDelete = [
    [1, 100, 100, 400, 300, 0, 0],
    [2, 100, 100, 300, 200, 0, 0],
    [3, 300, 200, 300, 200, 0, 0],
    [4, 300, 200, 0, 0, 0, 0],
];
$trigger2ExpectedInsert = [
    [1, 0, 0, 0, 0, 5, 6],
    [2, 0, 0, 5, 6, 5, 6],
];

foreach (array_keys($trigger2Cases()) as $case) {
    $tests["upstream trigger2 {$case} update row timing log"] = static function (TestRunner $t) use ($trigger2Cases, $case, $trigger2ExpectedUpdate): void {
        $t->same($trigger2ExpectedUpdate, $trigger2Cases()[$case]['update_log']);
    };
    $tests["upstream trigger2 {$case} conditional update log"] = static function (TestRunner $t) use ($trigger2Cases, $case): void {
        $t->same([[1, 1, 2, 13, 24, 10, 20]], $trigger2Cases()[$case]['conditional_update_log']);
    };
    $tests["upstream trigger2 {$case} delete row timing log"] = static function (TestRunner $t) use ($trigger2Cases, $case, $trigger2ExpectedDelete): void {
        $t->same($trigger2ExpectedDelete, $trigger2Cases()[$case]['delete_log']);
    };
    $tests["upstream trigger2 {$case} insert row timing log"] = static function (TestRunner $t) use ($trigger2Cases, $case, $trigger2ExpectedInsert): void {
        $t->same($trigger2ExpectedInsert, $trigger2Cases()[$case]['insert_log']);
    };
    $tests["upstream trigger2 {$case} source filename"] = static function (TestRunner $t) use ($trigger2Cases, $case): void {
        $t->same('trigger2.test', $trigger2Cases()[$case]['source']);
    };
    foreach (['update_log' => 4, 'conditional_update_log' => 1, 'delete_log' => 4, 'insert_log' => 2] as $key => $count) {
        $tests["upstream trigger2 {$case} {$key} row count"] = static function (TestRunner $t) use ($trigger2Cases, $case, $key, $count): void {
            $t->same($count, count($trigger2Cases()[$case][$key]));
        };
    }
    foreach (['update_log', 'delete_log', 'insert_log'] as $key) {
        foreach ([0, 1] as $rowIndex) {
            $tests["upstream trigger2 {$case} {$key} row {$rowIndex} sequence id"] = static function (TestRunner $t) use ($trigger2Cases, $case, $key, $rowIndex): void {
                $t->same($rowIndex + 1, $trigger2Cases()[$case][$key][$rowIndex][0]);
            };
        }
    }
    foreach (['update_log' => $trigger2ExpectedUpdate, 'delete_log' => $trigger2ExpectedDelete, 'insert_log' => $trigger2ExpectedInsert] as $key => $rows) {
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $tests["upstream trigger2 {$case} {$key} row {$rowIndex} column {$columnIndex} value"] = static function (TestRunner $t) use ($trigger2Cases, $case, $key, $rowIndex, $columnIndex, $value): void {
                    $t->same($value, $trigger2Cases()[$case][$key][$rowIndex][$columnIndex]);
                };
            }
        }
    }
}

$triggerCRecursivePlan = static fn (): array => SQLiteUpstreamTriggerFkeyDynamicPlan::triggerCRecursiveInsert();
$triggerCRecursiveCases = static function () use ($triggerCRecursivePlan): array {
    $cases = [];
    foreach ($triggerCRecursivePlan()['cases'] as $case) {
        $cases[$case['case']] = $case;
    }

    return $cases;
};

$tests['upstream triggerC recursive insert source filename'] = static function (TestRunner $t) use ($triggerCRecursivePlan): void {
    $t->same('triggerC.test', $triggerCRecursivePlan()['source']);
};
$tests['upstream triggerC recursive insert enables recursive triggers'] = static function (TestRunner $t) use ($triggerCRecursivePlan): void {
    $t->same(true, $triggerCRecursivePlan()['recursive_triggers']);
};
$tests['upstream triggerC recursive insert dependency marker'] = static function (TestRunner $t) use ($triggerCRecursivePlan): void {
    $t->same(true, in_array('sqlite-upstream-triggerC-recursive-after-insert-order', $triggerCRecursivePlan()['dependencies'], true));
};
$tests['upstream triggerC recursive insert scenario count'] = static function (TestRunner $t) use ($triggerCRecursivePlan): void {
    $t->same(7, count($triggerCRecursivePlan()['scenarios']));
};

$triggerCRecursiveExpectations = [
    'triggerC-2.1.1' => ['timing' => 'AFTER', 'ok' => true, 'rows' => [10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 0], 'order' => 'descending', 'ignored_at' => null, 'self_conflict' => false],
    'triggerC-2.1.2' => ['timing' => 'AFTER', 'ok' => true, 'rows' => [10, 9, 8, 7, 6, 5, 4, 3, 2], 'order' => 'statement-order', 'ignored_at' => 2, 'self_conflict' => false],
    'triggerC-2.1.3' => ['timing' => 'BEFORE', 'ok' => true, 'rows' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 'order' => 'ascending', 'ignored_at' => null, 'self_conflict' => false],
    'triggerC-2.1.4' => ['timing' => 'BEFORE', 'ok' => true, 'rows' => [3, 4, 5, 6, 7, 8, 9, 10], 'order' => 'statement-order', 'ignored_at' => 2, 'self_conflict' => false],
    'triggerC-2.1.5' => ['timing' => 'BEFORE', 'ok' => false, 'rows' => [], 'order' => 'none', 'ignored_at' => null, 'self_conflict' => false],
    'triggerC-2.1.6' => ['timing' => 'AFTER', 'ok' => true, 'rows' => [10], 'order' => 'statement-order', 'ignored_at' => null, 'self_conflict' => true],
    'triggerC-2.1.7' => ['timing' => 'BEFORE', 'ok' => false, 'rows' => [], 'order' => 'none', 'ignored_at' => null, 'self_conflict' => true],
];

foreach ($triggerCRecursiveExpectations as $case => $expected) {
    $tests["upstream triggerC recursive insert {$case} source filename"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case): void {
        $t->same('triggerC.test', $triggerCRecursiveCases()[$case]['source']);
    };
    $tests["upstream triggerC recursive insert {$case} seed value"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case): void {
        $t->same(10, $triggerCRecursiveCases()[$case]['seed_insert']);
    };
    $tests["upstream triggerC recursive insert {$case} trigger timing"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['timing'], $triggerCRecursiveCases()[$case]['trigger_timing']);
    };
    $tests["upstream triggerC recursive insert {$case} ok flag"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['ok'], $triggerCRecursiveCases()[$case]['ok']);
    };
    $tests["upstream triggerC recursive insert {$case} result rows"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['rows'], $triggerCRecursiveCases()[$case]['result_rows']);
    };
    $tests["upstream triggerC recursive insert {$case} row count"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same(count($expected['rows']), $triggerCRecursiveCases()[$case]['row_count']);
    };
    $tests["upstream triggerC recursive insert {$case} first row"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['rows'][0] ?? null, $triggerCRecursiveCases()[$case]['first_row']);
    };
    $tests["upstream triggerC recursive insert {$case} last row"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $rows = $expected['rows'];
        $t->same($rows === [] ? null : $rows[count($rows) - 1], $triggerCRecursiveCases()[$case]['last_row']);
    };
    $tests["upstream triggerC recursive insert {$case} monotonic order"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['order'], $triggerCRecursiveCases()[$case]['monotonic_order']);
    };
    $tests["upstream triggerC recursive insert {$case} ignored at"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['ignored_at'], $triggerCRecursiveCases()[$case]['ignored_at']);
    };
    $tests["upstream triggerC recursive insert {$case} raise ignore flag"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['ignored_at'] !== null, $triggerCRecursiveCases()[$case]['raise_ignore_stops_statement_branch']);
    };
    $tests["upstream triggerC recursive insert {$case} self conflict flag"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['self_conflict'], $triggerCRecursiveCases()[$case]['insert_or_ignore_self_conflict']);
    };
    $tests["upstream triggerC recursive insert {$case} recursion error flag"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same(!$expected['ok'], $triggerCRecursiveCases()[$case]['recursion_error']);
    };
    $tests["upstream triggerC recursive insert {$case} error message"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['ok'] ? null : 'too many levels of trigger recursion', $triggerCRecursiveCases()[$case]['error']);
    };
    $tests["upstream triggerC recursive insert {$case} result flat"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $expected): void {
        $t->same($expected['ok'] ? $expected['rows'] : ['too many levels of trigger recursion'], $triggerCRecursiveCases()[$case]['result_flat']);
    };
    foreach ($expected['rows'] as $rowIndex => $value) {
        $tests["upstream triggerC recursive insert {$case} row {$rowIndex} value"] = static function (TestRunner $t) use ($triggerCRecursiveCases, $case, $rowIndex, $value): void {
            $t->same($value, $triggerCRecursiveCases()[$case]['result_rows'][$rowIndex]);
        };
    }
}

return $tests;
