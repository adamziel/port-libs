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
    'real upstream fkey7 insert or fail cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey7.test');
        $t->true(is_string($source) && str_contains($source, 'do_catchsql_test 4.1'));
        $t->true(is_string($source) && str_contains($source, 'INSERT OR FAIL INTO child VALUES(123), (123)'));
    },
    'real upstream trigger1 raise expression cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
        $t->true(is_string($source) && str_contains($source, 'attempt to insert %d where is not a power of 2'));
        $t->true(is_string($source) && str_contains($source, 'RAISE(ABORT'));
    },
    'real upstream triggerB wide column mask cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerB.test');
        $t->true(is_string($source) && str_contains($source, 'for {set i 0} {$i<=64} {incr i}'));
        $t->true(is_string($source) && str_contains($source, 'WHEN old.c$i!=new.c$i'));
    },
    'real upstream triggerF without rowid conflict cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test');
        $t->true(is_string($source) && str_contains($source, 'WITHOUT ROWID'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA recursive_triggers = on'));
    },
];

for ($i = 1; $i <= 90; ++$i) {
    $parent = 1200 + $i;
    $first = $parent;
    $second = $i % 4 === 0 ? $parent + 1 : $parent;
    $third = $i % 5 === 0 ? $parent : $parent + 99;
    $unique = $i % 3 !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insertOrFailForeignKeyBatch(
        [$parent, $parent + 1],
        [$first, $second, $third],
        $unique
    );
    $expectedInserted = [];
    $failedIndex = null;
    $failedValue = null;
    $failedReason = null;
    foreach ([$first, $second, $third] as $index => $childValue) {
        if (!in_array($childValue, [$parent, $parent + 1], true)) {
            $failedIndex = $index;
            $failedValue = $childValue;
            $failedReason = 'foreign-key';
            break;
        }
        if ($unique && in_array($childValue, $expectedInserted, true)) {
            $failedIndex = $index;
            $failedValue = $childValue;
            $failedReason = 'unique';
            break;
        }
        $expectedInserted[] = $childValue;
    }
    $case = 'real upstream fkey7 insert or fail dynamic ' . $i;

    foreach ([
        'source' => 'fkey7.test fkey7-4.1..4.6',
        'operation' => 'insert-or-fail-foreign-key-batch',
        'status' => $failedIndex === null ? 'commit-ok' : 'constraint-failed',
        'conflict_policy' => 'fail',
        'unique_child' => $unique,
        'inserted_child_values' => $expectedInserted,
        'inserted_count' => count($expectedInserted),
        'failed_index' => $failedIndex,
        'failed_value' => $failedValue,
        'failed_reason' => $failedReason,
        'foreign_key_check_rows' => [],
        'statement_preserves_prior_successes' => $failedIndex !== null && $expectedInserted !== [],
        'dependencies.0' => 'sqlite-fkey7-insert-or-fail-stops-at-first-fk-violation',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 90; ++$i) {
    $bad = 48 + ($i * 2);
    if (($bad & ($bad - 1)) === 0) {
        ++$bad;
    }
    $rows = [
        ['a' => 1],
        ['a' => 2],
        ['a' => 4],
        ['a' => $bad],
        ['a' => 8],
    ];
    $action = ['abort', 'fail', 'rollback'][$i % 3];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRaiseExpressionPowerOfTwo($rows, $action);
    $rolledBack = in_array($action, ['abort', 'rollback'], true);
    $case = 'real upstream trigger1 raise expression dynamic ' . $i;

    foreach ([
        'source' => 'trigger1.test trigger1-24.1..24.2',
        'operation' => 'trigger-raise-expression-message',
        'status' => 'constraint-failed',
        'raise_action' => $action,
        'attempted_values' => [1, 2, 4, $bad, 8],
        'inserted_values' => $rolledBack ? [] : [1, 2, 4],
        'failed_index' => 3,
        'failed_value' => $bad,
        'error_message' => sprintf('attempt to insert %d where is not a power of 2', $bad),
        'statement_rolled_back' => $rolledBack,
        'prior_successes_preserved' => !$rolledBack,
        'dependencies.1' => 'sqlite-trigger1-raise-expression-can-reference-new-row',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 60; ++$i) {
    $rowId = 700 + $i;
    $rows = [
        ['setting_id' => $rowId, 'c1' => 'old-low', 'c33' => 'old-mid', 'c60' => 'old-high'],
        ['setting_id' => $rowId + 1, 'c1' => 'old-low-b', 'c33' => 'old-mid-b', 'c60' => 'old-high-b'],
    ];
    $columns = $i % 2 === 0 ? [1, 33, 60] : [60, 33, 1, 60];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::wideColumnTriggerMaskPlan($rows, $columns, 66);
    $expectedColumns = $i % 2 === 0 ? [1, 33, 60] : [60, 33, 1];
    $case = 'real upstream triggerB wide column mask dynamic ' . $i;

    foreach ([
        'source' => 'triggerB.test triggerB-3.1..3.2',
        'operation' => 'wide-old-new-trigger-column-mask',
        'status' => 'commit-ok',
        'column_count' => 66,
        'updated_columns' => $expectedColumns,
        'change_count' => 6,
        'high_column_mask_required' => true,
        'changes.0.rowid' => $rowId,
        'changes.0.colnum' => $expectedColumns[0],
        'changes.0.newval' => 'b' . $expectedColumns[0] . '-' . $rowId,
        'dependencies.0' => 'sqlite-triggerB-old-new-column-mask-beyond-32-columns',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 60; ++$i) {
    $rows = [
        ['a' => 1, 'b' => 'one-' . $i],
        ['a' => 2, 'b' => 'two-' . $i],
        ['a' => 3, 'b' => 'old-three-' . $i],
    ];
    $mode = ['none', 'before', 'after', 'both'][$i % 4];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidConflictDeleteTriggerPlan($rows, $mode);
    $case = 'real upstream triggerF without rowid conflict delete dynamic ' . $i;
    $expectedLogCount = match ($mode) {
        'none' => 0,
        'before', 'after' => 3,
        default => 6,
    };

    foreach ([
        'source' => 'triggerF.test triggerF-1.*',
        'operation' => 'without-rowid-conflict-delete-triggers',
        'status' => 'commit-ok',
        'trigger_mode' => $mode,
        'log_count' => $expectedLogCount,
        'final_keys' => [3],
        'final_rows.0.a' => 3,
        'final_rows.0.b' => 'three',
        'dependencies.0' => 'sqlite-triggerF-without-rowid-conflict-delete-trigger-order',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream trigger dynamic insert or fail rejects empty batch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::insertOrFailForeignKeyBatch([1], [], true));
};
$tests['real upstream trigger dynamic raise rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerRaiseExpressionPowerOfTwo([['a' => 1]], 'ignore'));
};
$tests['real upstream trigger dynamic wide mask rejects invalid high column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::wideColumnTriggerMaskPlan([['c1' => 'x']], [66], 66));
};
$tests['real upstream trigger dynamic without rowid rejects unsupported trigger mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidConflictDeleteTriggerPlan([], 'recursive'));
};

return $tests;
