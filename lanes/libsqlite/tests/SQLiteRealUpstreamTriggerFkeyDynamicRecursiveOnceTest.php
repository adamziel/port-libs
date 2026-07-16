<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$valueAt = static function (array $array, string $path): mixed {
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
    'real upstream triggerF recursive once corpus cites without rowid delete replace sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test');
        $t->true(is_string($source));
        $t->true(str_contains($source, 'CREATE TABLE t1(a INT PRIMARY KEY, b) WITHOUT ROWID'));
        $t->true(str_contains($source, 'INSERT OR REPLACE INTO t1 VALUES(2, \'three\')'));
        $t->true(str_contains($source, 'UPDATE OR REPLACE t1 SET a=3 WHERE a=2'));
    },
    'real upstream triggerG recursive once corpus cites op once sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test');
        $t->true(is_string($source));
        $t->true(str_contains($source, 'The OP_Once opcode was not working correctly for recursive triggers'));
        $t->true(str_contains($source, 'do_execsql_test 100'));
        $t->true(str_contains($source, 'do_execsql_test 200'));
    },
];

foreach (range(1, 90) as $seed) {
    $rows = [
        ['a' => 1, 'b' => 'one'],
        ['a' => 2, 'b' => 'two'],
        ['a' => 3, 'b' => 'three'],
    ];
    if ($seed % 5 === 0) {
        $rows[] = ['a' => 8, 'b' => 'eight'];
    }

    foreach (['none', 'after-delete', 'before-delete', 'before-after-delete'] as $mode) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerFWithoutRowidDeleteReplacePlan($rows, $mode);
        $case = sprintf('triggerF without rowid replace delete dynamic seed %03d %s', $seed, $mode);
        $extra = $seed % 5 === 0 ? 1 : 0;
        $expectedLog = match ($mode) {
            'none' => [],
            'after-delete' => ['1one' . (2 + $extra), '2two' . (1 + $extra), '3three' . (1 + $extra)],
            'before-delete' => ['1one' . (3 + $extra), '2two' . (2 + $extra), '3three' . (2 + $extra)],
            default => [
                '1one' . (3 + $extra),
                '1one' . (2 + $extra),
                '2two' . (2 + $extra),
                '2two' . (1 + $extra),
                '3three' . (2 + $extra),
                '3three' . (1 + $extra),
            ],
        };
        $expectedRemaining = $seed % 5 === 0
            ? [['a' => 3, 'b' => 'three'], ['a' => 8, 'b' => 'eight']]
            : [['a' => 3, 'b' => 'three']];

        $expectations = [
            'source' => 'triggerF.test triggerF-1.1.0..1.4.2',
            'operation' => 'without-rowid-delete-replace-trigger-log',
            'status' => 'commit-ok',
            'trigger_mode' => $mode,
            'recursive_triggers' => true,
            'log_rows' => $expectedLog,
            'log_count' => count($expectedLog),
            'remaining_rows' => $expectedRemaining,
            'remaining_keys' => array_column($expectedRemaining, 'a'),
            'dependencies.0' => 'sqlite-triggerF-without-rowid-replace-deletes-conflicting-row',
            'dependencies.1' => 'sqlite-triggerF-before-delete-sees-row-before-removal',
            'dependencies.2' => 'sqlite-triggerF-after-delete-sees-table-after-removal',
            'dependencies.3' => 'sqlite-triggerF-update-or-replace-delete-triggers-fire-before-new-row',
        ];
        foreach ($expectations as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $valueAt): void {
                $t->same($expected, $valueAt($plan(), (string) $path));
            };
        }
    }
}

foreach (range(1, 75) as $seed) {
    $base = [0, 2, 3, 8, 9];
    if ($seed % 2 === 0) {
        $base[] = 1;
    }
    if ($seed % 3 === 0) {
        $base[] = 4;
    }
    if ($seed % 7 === 0) {
        $base[] = 5;
    }

    foreach ([2, 3, 4] as $start) {
        foreach (['single', 'join'] as $shape) {
            $eligible = array_values(array_unique(array_filter($base, static fn (int $value): bool => $value >= 1 && $value <= 4)));
            sort($eligible);
            $recursiveRows = range($start, 5);
            $expectedRows = [];
            foreach ($recursiveRows as $c) {
                if ($shape === 'single') {
                    foreach ($eligible as $a) {
                        $expectedRows[] = $c * 100 + $a;
                    }
                } else {
                    foreach ($eligible as $left) {
                        foreach ($eligible as $right) {
                            if ($right >= 2 && $right <= 5) {
                                $expectedRows[] = $c * 10000 + $left * 100 + $right;
                            }
                        }
                    }
                }
            }
            sort($expectedRows);
            $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerGRecursiveSelectOncePlan($base, $start, $shape);
            $case = sprintf('triggerG recursive op once dynamic seed %03d start %d %s', $seed, $start, $shape);
            $expectations = [
                'source' => $shape === 'single' ? 'triggerG.test triggerG-100..110' : 'triggerG.test triggerG-200',
                'operation' => 'recursive-trigger-select-once-index-plan',
                'status' => 'commit-ok',
                'shape' => $shape,
                'start' => $start,
                'recursive_rows' => $recursiveRows,
                'recursive_row_count' => count($recursiveRows),
                'eligible_index_values' => $eligible,
                'result_rows' => $expectedRows,
                'result_count' => count($expectedRows),
                'op_once_resets_per_recursive_frame' => true,
                'dependencies.0' => 'sqlite-triggerG-recursive-trigger-reruns-select-program-per-frame',
                'dependencies.1' => 'sqlite-triggerG-index-in-filter-is-not-stale-across-recursion',
                'dependencies.2' => 'sqlite-triggerG-join-loop-op-once-state-is-frame-local',
            ];
            foreach ($expectations as $path => $expected) {
                $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $valueAt): void {
                    $t->same($expected, $valueAt($plan(), (string) $path));
                };
            }
        }
    }
}

$tests['real upstream triggerF rejects unsupported trigger mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerFWithoutRowidDeleteReplacePlan([], 'instead-of'));
};
$tests['real upstream triggerG rejects unsupported shape'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerGRecursiveSelectOncePlan([1, 2], 2, 'cte'));
};
$tests['real upstream triggerG rejects nonrecursive start'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerGRecursiveSelectOncePlan([1, 2], 5, 'single'));
};

return $tests;
