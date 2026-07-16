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
    'real upstream fkey6 defer restrict cites transaction repair block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'Test that defer_foreign_keys disables RESTRICT'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER p2t AFTER DELETE ON p2'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA defer_foreign_keys = 1;'));
    },
    'real upstream fkey8 action journal cites statement journal matrix' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test');
        $t->true(is_string($source) && str_contains($source, 'foreach {tn use_stmt sql schema}'));
        $t->true(is_string($source) && str_contains($source, 'ON UPDATE SET NULL'));
        $t->true(is_string($source) && str_contains($source, 'WITHOUT ROWID'));
    },
    'real upstream trigger2 and triggerG cites recursive trigger corpus' => static function (TestRunner $t): void {
        $trigger2 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $triggerG = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test');
        $t->true(is_string($trigger2) && str_contains($trigger2, 'do_test trigger2-4.1'));
        $t->true(is_string($trigger2) && str_contains($trigger2, 'do_test trigger2-4.2'));
        $t->true(is_string($triggerG) && str_contains($triggerG, 'OP_Once opcode was not working correctly for recursive triggers'));
    },
];

for ($i = 1; $i <= 90; ++$i) {
    $parents = [
        ['id' => 'p' . $i . '-a', 'label' => 'alpha-' . $i],
        ['id' => 'p' . $i . '-b', 'label' => 'beta-' . $i],
    ];
    $children = [
        ['id' => 'c' . $i . '-a', 'parent_id' => 'p' . $i . '-a', 'label' => 'child-alpha-' . $i],
        ['id' => 'c' . $i . '-b', 'parent_id' => 'p' . $i . '-b', 'label' => 'child-beta-' . $i],
    ];

    $deferredRepair = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        $parents,
        $children,
        [
            'operation' => 'delete',
            'target' => 'p' . $i . '-a',
            'action' => 'restrict',
            'pragma_defer' => true,
            'repair_trigger' => true,
            'transaction' => 'commit',
        ],
    );
    foreach ([
        'source' => 'fkey6.test fkey6-1.0..4.2',
        'operation' => 'pragma-defer-foreign-keys-transaction-boundary',
        'status' => 'commit-ok',
        'pragma_defer_foreign_keys' => true,
        'deferred_fk_dbstatus' => 0,
        'transaction_boundary' => 'commit',
        'pragma_reset_after_boundary' => true,
        'action' => 'restrict',
        'immediate_restrict_failed_before_trigger' => false,
        'repair_trigger_fired' => true,
        'changed_parent_ids.0' => 'p' . $i . '-a',
        'trigger_repaired_parent_ids.0' => 'p' . $i . '-a',
        'violation_count' => 0,
        'dependencies.2' => 'sqlite-fkey6-defer-foreign-keys-disables-restrict-until-commit',
    ] as $path => $expected) {
        $tests['fkey6-3.3 deferred restrict trigger repair dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deferredRepair, $path, $expected, $value): void {
            $t->same($expected, $value($deferredRepair(), (string) $path));
        };
    }

    $immediateRestrict = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        $parents,
        $children,
        [
            'operation' => 'delete',
            'target' => 'p' . $i . '-a',
            'action' => 'restrict',
            'pragma_defer' => false,
            'repair_trigger' => true,
            'transaction' => 'commit',
        ],
    );
    foreach ([
        'status' => 'constraint-failed',
        'pragma_defer_foreign_keys' => false,
        'immediate_restrict_failed_before_trigger' => true,
        'repair_trigger_fired' => false,
        'rolled_back' => true,
        'parent_ids.0' => 'p' . $i . '-a',
        'child_parent_ids.0' => 'p' . $i . '-a',
        'dependencies.0' => 'sqlite-fkey6-defer-foreign-keys-delays-all-actions-to-outer-commit',
    ] as $path => $expected) {
        $tests['fkey6-3.3 immediate restrict before trigger dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($immediateRestrict, $path, $expected, $value): void {
            $t->same($expected, $value($immediateRestrict(), (string) $path));
        };
    }

    $journal = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyActionJournalPlan(
        [
            ['pid' => $i, 'label' => 'parent-' . $i],
            ['pid' => $i + 1000, 'label' => 'default-' . $i],
        ],
        [
            ['cid' => 'child-a-' . $i, 'pid' => $i, 'payload' => 'payload-a'],
            ['cid' => 'child-b-' . $i, 'pid' => $i + 1000, 'payload' => 'payload-b'],
        ],
        [
            'operation' => 'delete',
            'action' => $i % 2 === 0 ? 'set default' : 'set null',
            'default' => $i + 1000,
            'attached' => $i % 3 === 0,
        ],
    );
    $expectedAction = $i % 2 === 0 ? 'set default' : 'set null';
    $expectedChildPid = $expectedAction === 'set default' ? $i + 1000 : null;
    $expectedJournalStatus = $expectedAction === 'set default' ? 'constraint-failed' : 'commit-ok';
    $expectedJournalViolations = $expectedAction === 'set default' ? 2 : 0;
    foreach ([
        'source' => 'fkey8.test fkey8-1.2.1..1.5.3',
        'operation' => 'foreign-key-action-statement-journal-plan',
        'status' => $expectedJournalStatus,
        'statement_journal' => $expectedAction === 'set default',
        'foreign_key_action' => $expectedAction,
        'statement_operation' => 'delete',
        'attached_schema' => $i % 3 === 0,
        'action_count' => 2,
        'actions.0.new_pid' => $expectedChildPid,
        'child_pids.0' => $expectedChildPid,
        'violation_count' => $expectedJournalViolations,
        'dependencies.1' => 'sqlite-fkey8-set-null-default-child-key-rewrite',
    ] as $path => $expected) {
        $tests['fkey8-1 action statement journal dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($journal, $path, $expected, $value): void {
            $t->same($expected, $value($journal(), (string) $path));
        };
    }

    $cascade = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::cascadedTriggerExecution(
        [
            'tblA' => [],
            'tblB' => [],
            'tblC' => [],
        ],
        ['a' => $i, 'b' => $i + 1, 'c' => $i + 2],
        $i % 2 === 0,
    );
    foreach ([
        'source' => 'trigger2.test trigger2-4.1..4.2',
        'operation' => 'cascaded-trigger-program-execution',
        'status' => 'commit-ok',
        'tblA_rows.0.a' => $i,
        'tblB_rows.0.b' => $i + 1,
        'tblC_rows.0.c' => $i + 2,
        'recursive_trigger_program_limited' => $i % 2 !== 0,
        'cascade_reaches_second_trigger' => true,
        'dependencies.0' => 'sqlite-trigger2-trigger-program-may-fire-other-triggers',
    ] as $path => $expected) {
        $tests['trigger2-4 cascade recursive setting dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($cascade, $path, $expected, $value): void {
            $t->same($expected, $value($cascade(), (string) $path));
        };
    }

    $indexed = [$i % 5, 2, 3, 8, 9];
    $recursiveOnce = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::recursiveOnceTriggerSelectPlan(
        2,
        $indexed,
        5,
        true,
        $i % 2 === 0,
    );
    $expectedFireCount = 4;
    $leftCount = count(array_values(array_filter($indexed, static fn (int $value): bool => $value >= 1 && $value <= 4)));
    $rightCount = count(array_values(array_filter($indexed, static fn (int $value): bool => $value >= 2 && $value <= 5)));
    $expectedT2Count = $i % 2 === 0 ? $leftCount * $rightCount * $expectedFireCount : $leftCount * $expectedFireCount;
    foreach ([
        'source' => 'triggerG.test triggerG-100..200',
        'operation' => 'recursive-trigger-select-subprogram-once-reset',
        'status' => 'commit-ok',
        'recursive_triggers' => true,
        'seed' => 2,
        'recursive_limit' => 5,
        'cross_join' => $i % 2 === 0,
        'trigger_fire_count' => $expectedFireCount,
        't3_values' => [2, 3, 4, 5],
        't2_count' => $expectedT2Count,
        'once_subprogram_reset_per_firing' => true,
        'dependencies.1' => 'sqlite-triggerG-op-once-resets-for-each-trigger-invocation',
    ] as $path => $expected) {
        $tests['triggerG recursive once indexed select dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($recursiveOnce, $path, $expected, $value): void {
            $t->same($expected, $value($recursiveOnce(), (string) $path));
        };
    }
}

return $tests;
