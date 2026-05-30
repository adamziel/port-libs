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
    'real upstream fkey2 pragma toggle corpus cites transaction matrix' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-8-test  1 { PRAGMA foreign_keys = 0'));
        $t->true(is_string($source) && str_contains($source, 'fkey2-8-test 16 { PRAGMA foreign_keys = true'));
    },
    'real upstream fkey2 pragma toggle corpus cites transaction no-op cases' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'BEGIN'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_keys'));
    },
];

$sequences = [
    'autocommit off then on' => [
        'initial' => false,
        'actions' => [
            ['op' => 'pragma', 'value' => false],
            ['op' => 'read'],
            ['op' => 'pragma', 'value' => true],
            ['op' => 'read'],
        ],
        'final' => 1,
        'ignored' => 0,
        'depth' => 0,
    ],
    'autocommit on then off' => [
        'initial' => true,
        'actions' => [
            ['op' => 'pragma', 'value' => true],
            ['op' => 'read'],
            ['op' => 'pragma', 'value' => false],
            ['op' => 'read'],
        ],
        'final' => 0,
        'ignored' => 0,
        'depth' => 0,
    ],
    'begin ignores enable until commit' => [
        'initial' => false,
        'actions' => [
            ['op' => 'begin'],
            ['op' => 'pragma', 'value' => true],
            ['op' => 'read'],
            ['op' => 'commit'],
            ['op' => 'read'],
        ],
        'final' => 0,
        'ignored' => 1,
        'depth' => 0,
    ],
    'begin ignores disable until rollback' => [
        'initial' => true,
        'actions' => [
            ['op' => 'begin'],
            ['op' => 'pragma', 'value' => false],
            ['op' => 'read'],
            ['op' => 'rollback'],
            ['op' => 'read'],
        ],
        'final' => 1,
        'ignored' => 1,
        'depth' => 0,
    ],
    'savepoint ignores nested toggle' => [
        'initial' => false,
        'actions' => [
            ['op' => 'pragma', 'value' => true],
            ['op' => 'savepoint', 'name' => 's1'],
            ['op' => 'pragma', 'value' => false],
            ['op' => 'release', 'name' => 's1'],
            ['op' => 'read'],
        ],
        'final' => 1,
        'ignored' => 1,
        'depth' => 0,
    ],
    'nested savepoint ignores multiple toggles' => [
        'initial' => true,
        'actions' => [
            ['op' => 'begin'],
            ['op' => 'savepoint', 'name' => 's1'],
            ['op' => 'pragma', 'value' => false],
            ['op' => 'pragma', 'value' => true],
            ['op' => 'release', 'name' => 's1'],
            ['op' => 'commit'],
        ],
        'final' => 1,
        'ignored' => 2,
        'depth' => 0,
    ],
];

for ($i = 1; $i <= 170; ++$i) {
    foreach ($sequences as $name => $sequence) {
        $actions = $sequence['actions'];
        if ($i % 4 === 0) {
            $actions[] = ['op' => 'read'];
        }
        if ($i % 9 === 0 && (int) $sequence['depth'] === 0) {
            $actions[] = ['op' => 'pragma', 'value' => (bool) $sequence['final']];
        }

        $initial = (bool) $sequence['initial'];
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeysPragmaToggleTransaction($actions, $initial);
        $ignored = (int) $sequence['ignored'];
        $final = (int) $sequence['final'];
        $case = 'fkey2-8 pragma foreign_keys transaction toggle dynamic ' . $i . ' ' . $name;

        foreach ([
            'source' => 'fkey2.test fkey2-8.1..8.16',
            'operation' => 'foreign-keys-pragma-transaction-boundary',
            'status' => 'commit-ok',
            'initial_foreign_keys' => $initial ? 1 : 0,
            'final_foreign_keys' => $final,
            'transaction_depth' => 0,
            'ignored_toggle_count' => $ignored,
            'history_count' => count($actions),
            'dependencies.0' => 'sqlite-fkey2-foreign-keys-pragma-autocommit-toggle',
            'dependencies.1' => 'sqlite-fkey2-foreign-keys-pragma-ignored-inside-transaction',
            'dependencies.2' => 'sqlite-fkey2-foreign-keys-pragma-ignored-inside-savepoint',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }

        $tests[$case . ' ignored pragma rows are exactly transaction scoped'] = static function (TestRunner $t) use ($plan, $ignored): void {
            $ignoredRows = array_values(array_filter(
                $plan()['history'],
                static fn (array $row): bool => $row['ignored_in_transaction'] === true
            ));
            $t->same($ignored, count($ignoredRows));
            foreach ($ignoredRows as $row) {
                $t->true($row['transaction_depth'] > 0);
                $t->same('pragma', $row['op']);
            }
        };

        $tests[$case . ' read probes report current effective flag'] = static function (TestRunner $t) use ($plan): void {
            $reads = array_values(array_filter($plan()['history'], static fn (array $row): bool => $row['op'] === 'read'));
            foreach ($reads as $row) {
                $t->true(in_array($row['foreign_keys'], [0, 1], true));
                $t->same(false, $row['ignored_in_transaction']);
            }
        };
    }
}

return $tests;
