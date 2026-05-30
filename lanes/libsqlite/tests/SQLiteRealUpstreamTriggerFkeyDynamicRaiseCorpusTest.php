<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream trigger3 raise corpus cites table raise setup' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger3.test');
        $t->true(is_string($source) && str_contains($source, 'RAISE(ABORT'));
    },
    'real upstream trigger3 raise corpus cites update delete ignore block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger3.test');
        $t->true(is_string($source) && str_contains($source, 'RAISE(IGNORE) for UPDATE and DELETE'));
    },
    'real upstream trigger3 raise corpus cites nested trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger3.test');
        $t->true(is_string($source) && str_contains($source, 'nested triggers'));
    },
    'real upstream trigger3 raise corpus cites view trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger3.test');
        $t->true(is_string($source) && str_contains($source, 'view-triggers'));
    },
];

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

$raiseCases = [
    'abort' => ['constraint-failed', false, 'Trigger abort', 0, 0],
    'fail' => ['constraint-failed', false, 'Trigger fail', 2, 2],
    'rollback' => ['rolled-back', true, 'Trigger rollback', 0, 0],
    'ignore' => ['commit-ok', false, null, 1, 1],
];

for ($i = 1; $i <= 90; ++$i) {
    $existingRows = $i % 3 === 0 ? [['a' => 5, 'b' => 5, 'c' => 6]] : [];
    foreach ($raiseCases as $raise => [$status, $rolledBack, $error, $rowCount, $insertedCount]) {
        $statementRows = [
            ['a' => 5 + $i, 'b' => 5, 'c' => 6],
            ['a' => match ($raise) {
                'abort' => 1,
                'fail' => 2,
                'rollback' => 3,
                default => 4,
            }, 'b' => 5 + ($i % 7), 'c' => 6 + ($i % 11), 'raise' => $raise],
        ];
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionStatement($existingRows, $statementRows, true);
        $case = 'real upstream trigger3 raise table action ' . $raise . ' dynamic ' . $i;
        foreach ([
            'source' => 'trigger3.test trigger3-1.1..4.2',
            'operation' => 'table-trigger-raise-action',
            'status' => $status,
            'rolled_back' => $rolledBack,
            'error' => $error,
            'row_count' => $raise === 'rollback' ? 0 : $rowCount + count($existingRows),
            'statement_inserted_count' => $insertedCount,
            'ignored_count' => $raise === 'ignore' ? 1 : 0,
            'dependencies.0' => 'sqlite-trigger3-raise-abort-rolls-back-current-statement',
            'dependencies.1' => 'sqlite-trigger3-raise-fail-preserves-prior-row-changes',
            'dependencies.2' => 'sqlite-trigger3-raise-rollback-clears-active-transaction',
            'dependencies.3' => 'sqlite-trigger3-raise-ignore-skips-current-row',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

for ($i = 1; $i <= 80; ++$i) {
    $statementRows = [
        ['a' => 5 + $i, 'b' => 5, 'c' => 6],
        ['a' => 3, 'b' => 9, 'c' => 10, 'raise' => 'rollback'],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionStatement([], $statementRows, false);
    $case = 'real upstream trigger3 raise rollback outside transaction acts like fail dynamic ' . $i;
    foreach ([
        'source' => 'trigger3.test trigger3-1.1..4.2',
        'status' => 'constraint-failed',
        'rolled_back' => false,
        'error' => 'Trigger rollback',
        'row_count' => 0,
        'statement_inserted_count' => 2,
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 120; ++$i) {
    $rows = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 4, 'b' => 5, 'c' => 6],
        ['a' => 7 + $i, 'b' => 8, 'c' => 9],
    ];
    foreach (['update', 'delete'] as $operation) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseIgnoreUpdateDelete($rows, $operation, 1);
        $case = 'real upstream trigger3 raise ignore ' . $operation . ' dynamic ' . $i;
        foreach ([
            'source' => 'trigger3.test trigger3-5.1..5.2',
            'operation' => 'raise-ignore-' . $operation . '-row-suppression',
            'status' => 'commit-ok',
            'ignored_count' => 1,
            'changed_count' => 2,
            'ignored_rows.0.a' => 1,
            'dependencies.0' => 'sqlite-trigger3-raise-ignore-update-skips-current-row',
            'dependencies.1' => 'sqlite-trigger3-raise-ignore-delete-skips-current-row',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

for ($i = 1; $i <= 100; ++$i) {
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::nestedRaiseIgnoreTrigger(['a' => $i, 'b' => $i + 1, 'c' => $i + 2]);
    $case = 'real upstream trigger3 nested raise ignore dynamic ' . $i;
    foreach ([
        'source' => 'trigger3.test trigger3-6',
        'operation' => 'nested-trigger-raise-ignore-boundary',
        'status' => 'commit-ok',
        'nested_row_count' => 2,
        'table_row_count' => 2,
        'outer_inserted.a' => $i,
        'nested_rows.0.a' => $i,
        'dependencies.0' => 'sqlite-trigger3-raise-ignore-stops-nested-step-not-outer-program',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$viewCases = [
    'rollback' => ['rolled-back', 'View rollback', true],
    'ignore' => ['commit-ok', null, false],
    'abort' => ['constraint-failed', 'View abort', false],
];
for ($i = 1; $i <= 90; ++$i) {
    foreach ($viewCases as $raise => [$status, $error, $rolledBack]) {
        $statementRows = [[
            'a' => match ($raise) {
                'rollback' => 1,
                'ignore' => 2,
                default => 3,
            },
            'b' => 2 + $i,
            'c' => 3 + $i,
            'raise' => $raise,
        ]];
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::raiseActionStatement([], $statementRows, true, 'view');
        $case = 'real upstream trigger3 view raise action ' . $raise . ' dynamic ' . $i;
        foreach ([
            'source' => 'trigger3.test trigger3-7.1..7.3',
            'operation' => 'view-trigger-raise-action',
            'target' => 'view',
            'status' => $status,
            'error' => $error,
            'rolled_back' => $rolledBack,
            'row_count' => 0,
            'ignored_count' => $raise === 'ignore' ? 1 : 0,
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

return $tests;
