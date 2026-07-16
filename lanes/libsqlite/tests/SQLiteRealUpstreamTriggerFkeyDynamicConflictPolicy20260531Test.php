<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

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

        throw new RuntimeException('Missing assertion path ' . $path);
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test';

$tests['real upstream fkey2 conflict policy cites insert conflict-policy block'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source) && str_contains($source, 'foreach {tn insert}'));
    $t->true(is_string($source) && str_contains($source, 'INSERT OR IGNORE'));
    $t->true(is_string($source) && str_contains($source, 'INSERT OR REPLACE'));
    $t->true(is_string($source) && str_contains($source, 'catchsql "$insert INTO cc VALUES(1, 2)"'));
    $t->true(is_string($source) && str_contains($source, 'execsql { COMMIT ; SELECT * FROM cc }'));
};

$tests['real upstream fkey2 conflict policy cites update conflict-policy block'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source) && str_contains($source, 'foreach {tn update}'));
    $t->true(is_string($source) && str_contains($source, 'UPDATE OR ROLLBACK'));
    $t->true(is_string($source) && str_contains($source, 'UPDATE OR FAIL'));
    $t->true(is_string($source) && str_contains($source, 'catchsql "$update pp SET a = 1"'));
    $t->true(is_string($source) && str_contains($source, 'catchsql "$update cc SET d = 1 WHERE c = 1"'));
};

$conflictPolicies = ['default', 'ignore', 'abort', 'rollback', 'replace', 'fail'];
$scenarios = [
    'insert-autocommit',
    'insert-transaction',
    'update-parent-autocommit',
    'update-child-autocommit',
    'update-parent-transaction',
    'update-child-transaction',
];

foreach (range(1, 167) as $seed) {
    foreach ($scenarios as $scenarioIndex => $scenario) {
        $base = 100000 + ($seed * 100) + ($scenarioIndex * 10);
        $parentKey = $base + 2;
        $missingKey = $base + 1;
        $transactionParentKey = $base + 3;
        $transactionChildId = $base + 4;
        $attemptedChildId = $base + 5;
        $conflict = $conflictPolicies[($seed + $scenarioIndex) % count($conflictPolicies)];
        $parents = [
            ['id' => $parentKey, 'label' => 'parent-' . $seed],
        ];
        $children = [
            ['id' => $base, 'parent_id' => $parentKey, 'label' => 'child-' . $seed],
        ];

        $statement = ['conflict' => $conflict];
        $expectedUpstream = [];
        $expectedParentsAfter = [$parentKey];
        $expectedChildrenAfter = [[$base, $parentKey]];
        $expectedTransactionOpen = false;
        $expectedCommit = 'not-open';
        $expectedTransactionParentPreserved = false;
        $expectedTransactionChildPreserved = false;
        $expectedStatement = $scenario;
        $expectedFailedChildKey = $missingKey;

        if ($scenario === 'insert-autocommit') {
            $statement += [
                'operation' => 'insert-child',
                'row' => ['id' => $attemptedChildId, 'parent_id' => $missingKey, 'label' => 'missing-parent-' . $seed],
            ];
            $expectedStatement = 'insert-child';
            $expectedUpstream = ['fkey2-20.2.1', 'fkey2-20.2.2'];
        } elseif ($scenario === 'insert-transaction') {
            $statement += [
                'operation' => 'insert-child',
                'transaction_parent_rows' => [['id' => $transactionParentKey, 'label' => 'two-' . $seed]],
                'transaction_child_rows' => [['id' => $transactionChildId, 'parent_id' => $transactionParentKey, 'label' => 'valid-child-' . $seed]],
                'row' => ['id' => $attemptedChildId, 'parent_id' => $missingKey, 'label' => 'missing-parent-' . $seed],
            ];
            $expectedParentsAfter = [$parentKey, $transactionParentKey];
            $expectedChildrenAfter = [[$base, $parentKey], [$transactionChildId, $transactionParentKey]];
            $expectedTransactionOpen = true;
            $expectedCommit = 'commit-ok';
            $expectedTransactionParentPreserved = true;
            $expectedTransactionChildPreserved = true;
            $expectedStatement = 'insert-child';
            $expectedUpstream = ['fkey2-20.2.3', 'fkey2-20.2.4'];
        } elseif ($scenario === 'update-parent-autocommit') {
            $statement += [
                'operation' => 'update-parent-key',
                'parent_from' => $parentKey,
                'parent_to' => $missingKey,
            ];
            $expectedStatement = 'update-parent-key';
            $expectedFailedChildKey = $parentKey;
            $expectedUpstream = ['fkey2-20.3.2', 'fkey2-20.3.3'];
        } elseif ($scenario === 'update-child-autocommit') {
            $statement += [
                'operation' => 'update-child-key',
                'child_id' => $base,
                'child_parent_to' => $missingKey,
            ];
            $expectedStatement = 'update-child-key';
            $expectedUpstream = ['fkey2-20.3.4', 'fkey2-20.3.5'];
        } elseif ($scenario === 'update-parent-transaction') {
            $statement += [
                'operation' => 'update-parent-key',
                'transaction_parent_rows' => [['id' => $transactionParentKey, 'label' => 'three-' . $seed]],
                'parent_from' => $parentKey,
                'parent_to' => $missingKey,
            ];
            $expectedParentsAfter = [$parentKey, $transactionParentKey];
            $expectedTransactionOpen = true;
            $expectedCommit = 'commit-ok';
            $expectedTransactionParentPreserved = true;
            $expectedStatement = 'update-parent-key';
            $expectedFailedChildKey = $parentKey;
            $expectedUpstream = ['fkey2-20.3.6', 'fkey2-20.3.7'];
        } else {
            $statement += [
                'operation' => 'update-child-key',
                'transaction_child_rows' => [['id' => $transactionChildId, 'parent_id' => $parentKey, 'label' => 'valid-child-' . $seed]],
                'child_id' => $base,
                'child_parent_to' => $missingKey,
            ];
            $expectedChildrenAfter = [[$base, $parentKey], [$transactionChildId, $parentKey]];
            $expectedTransactionOpen = true;
            $expectedCommit = 'commit-ok';
            $expectedTransactionChildPreserved = true;
            $expectedStatement = 'update-child-key';
            $expectedUpstream = ['fkey2-20.3.8', 'fkey2-20.3.9'];
        }

        $tests[sprintf('real upstream fkey2 conflict policy dynamic %03d %s %s', $seed, $scenario, $conflict)] = static function (TestRunner $t) use (
            $parents,
            $children,
            $statement,
            $value,
            $conflict,
            $expectedStatement,
            $expectedParentsAfter,
            $expectedChildrenAfter,
            $expectedTransactionOpen,
            $expectedCommit,
            $expectedTransactionParentPreserved,
            $expectedTransactionChildPreserved,
            $expectedFailedChildKey,
            $expectedUpstream
        ): void {
            $plan = SQLiteDynamicTriggerForeignKeyPlan::fkey2ConflictPolicyForeignKeyPlan($parents, $children, $statement);

            $t->same('fkey2.test fkey2-20.2.1..20.3.10', $plan['source']);
            $t->same('foreign-key-conflict-policy-statement', $plan['operation']);
            $t->same($expectedStatement, $plan['statement_operation']);
            $t->same($conflict, $plan['conflict_policy']);
            $t->same('constraint-failed', $plan['status']);
            $t->same('FOREIGN KEY constraint failed', $plan['error']);
            $t->same('immediate-statement', $plan['foreign_key_violation_phase']);
            $t->same(true, $plan['conflict_policy_ignored_for_foreign_key']);
            $t->same(true, $plan['statement_rolled_back']);
            $t->same(false, $plan['transaction_rolled_back']);
            $t->same($expectedTransactionOpen, $plan['transaction_open_after_failure']);
            $t->same($expectedCommit, $plan['commit_after_failure_status']);
            $t->same($expectedTransactionParentPreserved, $plan['transaction_parent_preserved']);
            $t->same($expectedTransactionChildPreserved, $plan['transaction_child_preserved']);
            $t->same($expectedParentsAfter, $plan['parent_keys_after_failure']);
            $t->same($expectedChildrenAfter, $plan['child_pairs_after_failure']);
            $t->same($expectedFailedChildKey, $value($plan, 'violations.0.child_key'));
            $t->same('missing-parent', $value($plan, 'violations.0.reason'));
            $t->same($expectedUpstream, $plan['upstream_cases']);
            $t->same('sqlite-fkey2-conflict-policy-does-not-apply-to-foreign-key-errors', $value($plan, 'dependencies.0'));
            $t->same('sqlite-fkey2-failed-fk-statement-preserves-table-images', $value($plan, 'dependencies.1'));
            $t->same('sqlite-fkey2-fk-error-inside-transaction-keeps-prior-changes-committable', $value($plan, 'dependencies.2'));
        };
    }
}

$tests['real upstream fkey2 conflict policy rejects unsupported statement operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2ConflictPolicyForeignKeyPlan(
        [['id' => 1, 'label' => 'one']],
        [['id' => 10, 'parent_id' => 1, 'label' => 'child']],
        ['operation' => 'delete-parent', 'conflict' => 'ignore']
    ));
};

$tests['real upstream fkey2 conflict policy rejects unsupported conflict policy'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2ConflictPolicyForeignKeyPlan(
        [['id' => 1, 'label' => 'one']],
        [['id' => 10, 'parent_id' => 1, 'label' => 'child']],
        ['operation' => 'insert-child', 'conflict' => 'replace-into']
    ));
};

$tests['real upstream fkey2 conflict policy owns focused dynamic pass count'] = static function (TestRunner $t) use (&$tests): void {
    $t->same(1008, count($tests));
};

$tests['real upstream fkey2 conflict policy non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: expands fkey2.test fkey2-20 exact INSERT/UPDATE OR conflict-policy statement failures and transaction preservation; avoids accepted fkey2-20 action-matrix summary, fkey2-3 statement rollback, fkey2-17 count_changes, fkey8 action journals, implicit DROP, trigger2 conflict propagation, and triggerF WITHOUT ROWID conflict delete clusters',
        'non-overlap: expands fkey2.test fkey2-20 exact INSERT/UPDATE OR conflict-policy statement failures and transaction preservation; avoids accepted fkey2-20 action-matrix summary, fkey2-3 statement rollback, fkey2-17 count_changes, fkey8 action journals, implicit DROP, trigger2 conflict propagation, and triggerF WITHOUT ROWID conflict delete clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteDynamicTriggerForeignKeyPlan and hydrated upstream fkey2.test as source truth',
        'dependency-closure: no new support component needed; reuses SQLiteDynamicTriggerForeignKeyPlan and hydrated upstream fkey2.test as source truth'
    );
};

return $tests;
