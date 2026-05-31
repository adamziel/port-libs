<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream fkey2 deferred graph corpus cites upstream sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-2.*: Tests to verify that deferred foreign keys work inside'));
        $t->true(is_string($source) && str_contains($source, 'fkey2-2-test 3  1   "INSERT INTO node VALUES(1, 0)"'));
        $t->true(is_string($source) && str_contains($source, 'fkey2-2-test 15 1   "DELETE FROM node WHERE nodeid = 2"'));
        $t->true(is_string($source) && str_contains($source, 'fkey2-2-test 17 0 "COMMIT"'));
    },
];

for ($i = 1; $i <= 260; ++$i) {
    $base = $i * 10;
    $nodes = [
        ['nodeid' => $base + 1, 'parent' => null],
        ['nodeid' => $base + 2, 'parent' => $base + 1],
    ];
    $leaves = [
        ['id' => 'seed-' . $i, 'nodeid' => $base + 2],
    ];

    $repairBeforeCommit = [
        ['action' => 'begin'],
        ['action' => 'insert-leaf', 'id' => 'a-' . $i, 'nodeid' => $base + 3],
        ['action' => 'insert-node', 'nodeid' => $base + 3, 'parent' => $base + 2],
        ['action' => 'commit'],
    ];
    $tests['fkey2-2 deferred leaf insert repaired before commit dynamic upstream case ' . $i] = static function (TestRunner $t) use ($nodes, $leaves, $repairBeforeCommit, $base): void {
        $actual = SQLiteDynamicTriggerForeignKeyPlan::fkey2DeferredGraphTransaction($nodes, $leaves, $repairBeforeCommit);

        $t->same('fkey2.test fkey2-2.1..2.17', $actual['source']);
        $t->same('deferred-foreign-key-graph-transaction', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same(false, $actual['transaction_open']);
        $t->same([$base + 1, $base + 2, $base + 3], $actual['node_ids']);
        $t->same(['seed-' . ($base / 10), 'a-' . ($base / 10)], $actual['leaf_ids']);
        $t->same(1, $actual['commit_attempt_count']);
        $t->same('commit-ok', $actual['commit_attempts'][0]['status']);
        $t->same(0, $actual['violation_count']);
        $t->same('sqlite-fkey2-deferred-child-insert-can-be-repaired-before-commit', $actual['dependencies'][0]);
    };

    $blockedThenRepaired = [
        ['action' => 'begin'],
        ['action' => 'insert-node', 'nodeid' => $base + 3, 'parent' => 0],
        ['action' => 'commit'],
        ['action' => 'update-node-parent', 'nodeid' => $base + 3, 'parent' => $base + 2],
        ['action' => 'commit'],
    ];
    $tests['fkey2-2 deferred self reference failed commit remains open dynamic upstream case ' . $i] = static function (TestRunner $t) use ($nodes, $leaves, $blockedThenRepaired, $base): void {
        $actual = SQLiteDynamicTriggerForeignKeyPlan::fkey2DeferredGraphTransaction($nodes, $leaves, $blockedThenRepaired);

        $t->same('commit-ok', $actual['status']);
        $t->same(false, $actual['transaction_open']);
        $t->same(2, $actual['commit_attempt_count']);
        $t->same('commit-blocked', $actual['commit_attempts'][0]['status']);
        $t->same(1, $actual['commit_attempts'][0]['violation_count']);
        $t->same('node', $actual['commit_attempts'][0]['violations'][0]['table']);
        $t->same(0, $actual['commit_attempts'][0]['violations'][0]['child_key']);
        $t->same('commit-ok', $actual['commit_attempts'][1]['status']);
        $t->same([$base + 1, $base + 2, $base + 3], $actual['node_ids']);
        $t->same('sqlite-fkey2-failed-commit-leaves-transaction-open', $actual['dependencies'][2]);
    };

    $deleteReinsert = [
        ['action' => 'begin'],
        ['action' => 'delete-node', 'nodeid' => $base + 2],
        ['action' => 'commit'],
        ['action' => 'insert-node', 'nodeid' => $base + 2, 'parent' => $base + 1],
        ['action' => 'commit'],
    ];
    $tests['fkey2-2 deferred parent delete repaired after failed commit dynamic upstream case ' . $i] = static function (TestRunner $t) use ($nodes, $leaves, $deleteReinsert, $base): void {
        $actual = SQLiteDynamicTriggerForeignKeyPlan::fkey2DeferredGraphTransaction($nodes, $leaves, $deleteReinsert);

        $t->same('commit-ok', $actual['status']);
        $t->same(2, $actual['commit_attempt_count']);
        $t->same('commit-blocked', $actual['commit_attempts'][0]['status']);
        $t->same('leaf', $actual['commit_attempts'][0]['violations'][0]['table']);
        $t->same($base + 2, $actual['commit_attempts'][0]['violations'][0]['child_key']);
        $t->same('commit-ok', $actual['commit_attempts'][1]['status']);
        $t->same([$base + 1, $base + 2], $actual['node_ids']);
        $t->same([$base + 2], $actual['leaf_nodeids']);
        $t->same('sqlite-fkey2-delete-parent-remains-deferred-until-commit', $actual['dependencies'][3]);
    };

    $rollback = [
        ['action' => 'begin'],
        ['action' => 'insert-leaf', 'id' => 'bad-' . $i, 'nodeid' => $base + 99],
        ['action' => 'commit'],
        ['action' => 'rollback'],
    ];
    $tests['fkey2-2 deferred violation rollback restores original graph dynamic upstream case ' . $i] = static function (TestRunner $t) use ($nodes, $leaves, $rollback, $base): void {
        $actual = SQLiteDynamicTriggerForeignKeyPlan::fkey2DeferredGraphTransaction($nodes, $leaves, $rollback);

        $t->same('commit-ok', $actual['status']);
        $t->same(false, $actual['transaction_open']);
        $t->same(1, $actual['commit_attempt_count']);
        $t->same('commit-blocked', $actual['commit_attempts'][0]['status']);
        $t->same('leaf', $actual['commit_attempts'][0]['violations'][0]['table']);
        $t->same([$base + 1, $base + 2], $actual['node_ids']);
        $t->same(['seed-' . ($base / 10)], $actual['leaf_ids']);
        $t->same(0, $actual['violation_count']);
        $t->same('rollback', $actual['events'][3]['step']);
    };
}

$tests['real upstream fkey2 deferred graph rejects unsupported transaction action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkey2DeferredGraphTransaction(
        [['nodeid' => 1, 'parent' => null]],
        [],
        [['action' => 'vacuum']]
    ));
};

return $tests;
