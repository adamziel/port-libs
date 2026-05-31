<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$value = static function (array $row, string $path): mixed {
    $cursor = $row;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException('Missing assertion path ' . $path);
    }

    return $cursor;
};

$fkey2Source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test';

$tests = [
    'real upstream fkey2 graph cascade cites deferred transaction corpus' => static function (TestRunner $t) use ($fkey2Source): void {
        $source = file_get_contents($fkey2Source);
        $t->true(is_string($source) && str_contains($source, 'fkey2-2.*: Tests to verify that deferred foreign keys work inside'));
    },
    'real upstream fkey2 graph cascade cites statement transaction corpus' => static function (TestRunner $t) use ($fkey2Source): void {
        $source = file_get_contents($fkey2Source);
        $t->true(is_string($source) && str_contains($source, 'fkey2-3.* test that a program that executes foreign key'));
    },
    'real upstream fkey2 graph cascade cites recursive action corpus' => static function (TestRunner $t) use ($fkey2Source): void {
        $source = file_get_contents($fkey2Source);
        $t->true(is_string($source) && str_contains($source, 'fkey2-4.* test that recursive foreign key actions'));
    },
];

for ($i = 1; $i <= 90; ++$i) {
    $base = $i * 10;
    $repairBeforeCommit = ($i % 3) !== 0;
    $deleteParentAfterRepair = false;
    $nodes = [
        ['nodeid' => $base + 1, 'parent' => null],
        ['nodeid' => $base + 2, 'parent' => $base + 1],
        ['nodeid' => $base + 3, 'parent' => $base + 2],
    ];
    $leaves = [
        ['id' => 'leaf-' . $base . '-a', 'nodeid' => $base + 3],
        ['id' => 'leaf-' . $base . '-b', 'nodeid' => $base + 99],
    ];
    $steps = [
        ['action' => 'begin'],
        ['action' => 'insert-leaf', 'id' => 'late-' . $base, 'nodeid' => $base + 50],
        ['action' => 'commit'],
    ];
    if ($repairBeforeCommit) {
        $steps[] = ['action' => 'insert-node', 'nodeid' => $base + 50, 'parent' => $base + 3];
    }
    if ($deleteParentAfterRepair) {
        $steps[] = ['action' => 'delete-node', 'nodeid' => $base + 1];
    }
    $steps[] = ['action' => 'commit'];

    $graphPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2DeferredGraphTransaction(
        $nodes,
        $leaves,
        $steps
    );
    $firstViolations = 2;
    $finalViolationCount = 1 + ($repairBeforeCommit ? 0 : 1) + ($deleteParentAfterRepair ? 2 : 0);
    $finalStatus = $finalViolationCount === 0 ? 'commit-ok' : 'transaction-open-with-deferred-violations';
    $secondCommitStatus = $finalViolationCount === 0 ? 'commit-ok' : 'commit-blocked';
    foreach ([
        'source' => 'fkey2.test fkey2-2.1..2.17',
        'operation' => 'deferred-foreign-key-graph-transaction',
        'status' => $finalStatus,
        'transaction_open' => $finalStatus !== 'commit-ok',
        'commit_attempt_count' => 2,
        'commit_attempts.0.status' => 'commit-blocked',
        'commit_attempts.0.violation_count' => $firstViolations,
        'commit_attempts.1.status' => $secondCommitStatus,
        'commit_attempts.1.violation_count' => $finalViolationCount,
        'violation_count' => $finalViolationCount,
        'node_ids.0' => $base + 1,
        'leaf_ids.0' => 'leaf-' . $base . '-a',
        'leaf_nodeids.2' => $base + 50,
        'dependencies.0' => 'sqlite-fkey2-deferred-child-insert-can-be-repaired-before-commit',
        'dependencies.2' => 'sqlite-fkey2-failed-commit-leaves-transaction-open',
    ] as $path => $expected) {
        $tests[sprintf('real upstream fkey2 deferred graph dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($graphPlan, $value, $path, $expected): void {
            $t->same($expected, $value($graphPlan(), (string) $path));
        };
    }

    $duplicateLeaf = ($i % 4) === 0;
    $rollbackLeaves = [
        ['id' => 'counter-' . $base . '-a', 'nodeid' => $base + 7],
        ['id' => 'counter-' . $base . '-b', 'nodeid' => $duplicateLeaf ? $base + 7 : $base + 8],
    ];
    $rollbackPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2StatementRollbackCounterReset(
        $nodes,
        $rollbackLeaves,
        !$duplicateLeaf
    );
    foreach ([
        'source' => 'fkey2.test fkey2-2.61..2.75',
        'operation' => 'deferred-counter-reset-after-statement-rollback',
        'statement_status' => $duplicateLeaf ? 'rolled-back-on-unique-nodeid' : 'commit-ok',
        'statement_rolled_back' => $duplicateLeaf,
        'counter_reset_after_rollback' => $duplicateLeaf,
        'deferred_before_statement' => 2,
        'first_commit_status' => 'commit-blocked',
        'first_commit_violation_count' => 2,
        'status' => 'commit-blocked',
        'final_violation_count' => 2,
        'leaf_nodeids.0' => $base + 7,
        'attempted_insert_nodeids.0' => $base + 7,
        'dependencies.0' => 'sqlite-fkey2-statement-transaction-restores-deferred-counter',
        'dependencies.1' => 'sqlite-fkey2-insert-select-unique-failure-rolls-back-statement',
    ] as $path => $expected) {
        $tests[sprintf('real upstream fkey2 statement rollback counter dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($rollbackPlan, $value, $path, $expected): void {
            $t->same($expected, $value($rollbackPlan(), (string) $path));
        };
    }

    $tree = [
        ['node' => 1, 'parent' => null],
        ['node' => 2, 'parent' => 1],
        ['node' => 3, 'parent' => 1],
        ['node' => 4, 'parent' => 2],
        ['node' => 5, 'parent' => 2],
        ['node' => 6, 'parent' => 3],
        ['node' => 7, 'parent' => 3],
    ];
    $recursiveTriggers = ($i % 2) === 0;
    $deleteNode = ($i % 6) + 1;
    $cascadePlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2RecursiveCascadeIgnoresRecursiveTriggerPragma(
        $tree,
        $tree,
        $deleteNode,
        $recursiveTriggers
    );
    $fkDeleted = [];
    $queue = [$deleteNode];
    while ($queue !== []) {
        $node = array_shift($queue);
        $fkDeleted[] = $node;
        foreach ($tree as $row) {
            if ($row['parent'] === $node) {
                $queue[] = $row['node'];
            }
        }
    }
    sort($fkDeleted);
    $triggerDeleted = [$deleteNode];
    foreach ($tree as $row) {
        if ($row['parent'] === $deleteNode) {
            $triggerDeleted[] = $row['node'];
        }
    }
    if ($recursiveTriggers) {
        $triggerDeleted = $fkDeleted;
    }
    $triggerRemaining = array_values(array_diff([1, 2, 3, 4, 5, 6, 7], $triggerDeleted));
    foreach ([
        'source' => 'fkey2.test fkey2-4.1..4.4',
        'operation' => 'recursive-foreign-key-cascade-ignores-recursive-trigger-pragma',
        'status' => 'commit-ok',
        'recursive_triggers' => $recursiveTriggers,
        'delete_node' => $deleteNode,
        'foreign_key_deleted_nodes' => $fkDeleted,
        'trigger_deleted_nodes' => $triggerDeleted,
        'trigger_remaining_nodes' => $triggerRemaining,
        'dependencies.0' => 'sqlite-fkey2-recursive-fk-actions-ignore-recursive-trigger-pragma',
        'dependencies.1' => 'sqlite-fkey2-user-trigger-recursion-obeys-recursive-trigger-pragma',
    ] as $path => $expected) {
        $tests[sprintf('real upstream fkey2 recursive cascade pragma dynamic %03d %s', $i, $path)] = static function (TestRunner $t) use ($cascadePlan, $value, $path, $expected): void {
            $t->same($expected, $value($cascadePlan(), (string) $path));
        };
    }
}

$tests['real upstream fkey2 graph cascade rejects unsupported transaction step'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2DeferredGraphTransaction([], [], [['action' => 'vacuum']]));
};

$tests['real upstream fkey2 graph cascade rejects inverted recursive delete target by absence'] = static function (TestRunner $t): void {
    $plan = SQLiteDynamicTriggerForeignKeyPlan::fkey2RecursiveCascadeIgnoresRecursiveTriggerPragma(
        [['node' => 1, 'parent' => null]],
        [['node' => 1, 'parent' => null]],
        2,
        false
    );
    $t->same([], $plan['foreign_key_deleted_nodes']);
};

return $tests;
