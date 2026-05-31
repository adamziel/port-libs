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
    'real upstream trigger fkey deferred restrict op once cites source blocks' => static function (TestRunner $t): void {
        $fkey6 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $triggerF = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test');
        $triggerG = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test');

        $t->true(is_string($fkey6) && str_contains($fkey6, 'Test that defer_foreign_keys disables RESTRICT.'));
        $t->true(is_string($fkey6) && str_contains($fkey6, 'outstanding foreign key violations'));
        $t->true(is_string($triggerF) && str_contains($triggerF, 'CREATE TABLE t1(a INT PRIMARY KEY, b) WITHOUT ROWID'));
        $t->true(is_string($triggerF) && str_contains($triggerF, 'UPDATE OR REPLACE t1 SET a=3 WHERE a=2'));
        $t->true(is_string($triggerG) && str_contains($triggerG, 'The OP_Once opcode was not working correctly for recursive triggers.'));
        $t->true(is_string($triggerG) && str_contains($triggerG, 'FROM t1 AS xx, t1 AS yy'));
    },
];

for ($i = 1; $i <= 96; ++$i) {
    $target = ($i % 3) + 1;
    $parents = [
        ['id' => 1, 'label' => 'one'],
        ['id' => 2, 'label' => 'two'],
        ['id' => 3, 'label' => 'three'],
    ];
    $children = [
        ['id' => 'child-' . $i, 'parent_id' => $target, 'label' => 'child'],
    ];

    $deferredRepair = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        $parents,
        $children,
        [
            'operation' => 'delete',
            'target' => $target,
            'repair_trigger' => true,
            'pragma_defer' => true,
            'transaction' => 'commit',
            'action' => 'restrict',
        ]
    );
    $immediateRestrict = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        $parents,
        $children,
        [
            'operation' => 'delete',
            'target' => $target,
            'repair_trigger' => true,
            'pragma_defer' => false,
            'transaction' => 'statement',
            'action' => 'restrict',
        ]
    );
    $deferredViolation = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        $parents,
        $children,
        [
            'operation' => 'update',
            'target' => $target,
            'repair_trigger' => false,
            'pragma_defer' => true,
            'transaction' => 'commit',
            'action' => 'restrict',
        ]
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
        'commit_failed_with_deferred_violation' => false,
        'rolled_back' => false,
        'repair_trigger_fired' => true,
        'trigger_repaired_parent_ids.0' => $target,
        'violation_count' => 0,
        'dependencies.2' => 'sqlite-fkey6-defer-foreign-keys-disables-restrict-until-commit',
    ] as $path => $expected) {
        $tests['fkey6 deferred restrict repaired by after trigger dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deferredRepair, $path, $expected, $value): void {
            $t->same($expected, $value($deferredRepair(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'pragma_defer_foreign_keys' => false,
        'deferred_fk_dbstatus' => 0,
        'transaction_boundary' => 'statement',
        'pragma_reset_after_boundary' => false,
        'immediate_restrict_failed_before_trigger' => true,
        'repair_trigger_fired' => false,
        'rolled_back' => true,
        'parent_ids' => [1, 2, 3],
        'dependencies.2' => 'sqlite-fkey6-defer-foreign-keys-disables-restrict-until-commit',
    ] as $path => $expected) {
        $tests['fkey6 immediate restrict blocks repair trigger dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($immediateRestrict, $path, $expected, $value): void {
            $t->same($expected, $value($immediateRestrict(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'pragma_defer_foreign_keys' => true,
        'deferred_fk_dbstatus' => 1,
        'commit_failed_with_deferred_violation' => true,
        'rolled_back' => true,
        'violation_count' => 1,
        'parent_ids' => [1, 2, 3],
        'child_parent_ids.0' => $target,
        'dependencies.3' => 'sqlite-fkey6-deferred-dbstatus-tracks-outstanding-violations',
    ] as $path => $expected) {
        $tests['fkey6 deferred restrict update rolls back at commit dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deferredViolation, $path, $expected, $value): void {
            $t->same($expected, $value($deferredViolation(), (string) $path));
        };
    }
}

$triggerModes = [
    'none' => ['log_rows' => [], 'log_count' => 0],
    'after-delete' => ['log_rows' => ['1one2', '2two1', '3three1'], 'log_count' => 3],
    'before-delete' => ['log_rows' => ['1one3', '2two2', '3three2'], 'log_count' => 3],
    'before-after-delete' => ['log_rows' => ['1one3', '1one2', '2two2', '2two1', '3three2', '3three1'], 'log_count' => 6],
];
for ($i = 1; $i <= 80; ++$i) {
    $mode = array_keys($triggerModes)[$i % count($triggerModes)];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerFWithoutRowidDeleteReplacePlan(
        [
            ['a' => 1, 'b' => 'one'],
            ['a' => 2, 'b' => 'two'],
            ['a' => 3, 'b' => 'three'],
        ],
        $mode
    );

    foreach ([
        'source' => 'triggerF.test triggerF-1.1.0..1.4.2',
        'operation' => 'without-rowid-delete-replace-trigger-log',
        'status' => 'commit-ok',
        'trigger_mode' => $mode,
        'recursive_triggers' => true,
        'log_rows' => $triggerModes[$mode]['log_rows'],
        'log_count' => $triggerModes[$mode]['log_count'],
        'remaining_rows' => [['a' => 3, 'b' => 'three']],
        'remaining_keys' => [3],
        'dependencies.0' => 'sqlite-triggerF-without-rowid-replace-deletes-conflicting-row',
        'dependencies.3' => 'sqlite-triggerF-update-or-replace-delete-triggers-fire-before-new-row',
    ] as $path => $expected) {
        $tests['triggerF without rowid delete replace trigger log dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 120; ++$i) {
    $start = 1 + ($i % 4);
    $values = [0, 2, 3, 8, 9];
    if ($i % 5 === 0) {
        $values[] = 4;
    }
    $shape = $i % 2 === 0 ? 'single' : 'join';
    $eligible = array_values(array_unique(array_filter($values, static fn (int $value): bool => $value >= 1 && $value <= 4)));
    sort($eligible);
    $rowCount = 6 - $start;
    $expectedResultCount = $shape === 'single'
        ? $rowCount * count($eligible)
        : $rowCount * count($eligible) * count(array_filter($eligible, static fn (int $value): bool => $value >= 2 && $value <= 5));
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerGRecursiveSelectOncePlan($values, $start, $shape);

    foreach ([
        'source' => $shape === 'single' ? 'triggerG.test triggerG-100..110' : 'triggerG.test triggerG-200',
        'operation' => 'recursive-trigger-select-once-index-plan',
        'status' => 'commit-ok',
        'shape' => $shape,
        'start' => $start,
        'recursive_row_count' => $rowCount,
        'eligible_index_values' => $eligible,
        'result_count' => $expectedResultCount,
        'op_once_resets_per_recursive_frame' => true,
        'dependencies.0' => 'sqlite-triggerG-recursive-trigger-reruns-select-program-per-frame',
        'dependencies.2' => 'sqlite-triggerG-join-loop-op-once-state-is-frame-local',
    ] as $path => $expected) {
        $tests['triggerG recursive op once select frame local dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream trigger fkey deferred restrict op once rejects malformed fkey6 boundary'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction(
        [['id' => 1]],
        [],
        ['operation' => 'insert', 'transaction' => 'commit']
    ));
};

$tests['real upstream trigger fkey deferred restrict op once rejects malformed triggerF mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerFWithoutRowidDeleteReplacePlan(
        [['a' => 1, 'b' => 'one']],
        'instead-of-delete'
    ));
};

$tests['real upstream trigger fkey deferred restrict op once rejects malformed triggerG start'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerGRecursiveSelectOncePlan(
        [2, 3],
        5,
        'single'
    ));
};

return $tests;
