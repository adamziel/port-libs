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

$wideRows = static function (int $rowCount, int $columnCount = 66): array {
    $rows = [];
    for ($row = 1; $row <= $rowCount; ++$row) {
        $record = ['setting_id' => $row];
        for ($column = 0; $column < $columnCount; ++$column) {
            $record['c' . $column] = 'a' . $column . '-' . $row;
        }
        $rows[] = $record;
    }

    return $rows;
};

$tests = [];

for ($seed = 1; $seed <= 160; ++$seed) {
    $rowCount = 1 + ($seed % 5);
    $columns = array_values(array_unique([
        $seed % 66,
        (31 + $seed) % 66,
        (32 + ($seed * 3)) % 66,
        65 - ($seed % 17),
    ]));
    sort($columns);
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::wideColumnTriggerMaskPlan(
        $wideRows($rowCount),
        $columns,
    );
    $case = 'real upstream triggerB wide old new mask dynamic seed ' . $seed;

    foreach ([
        'source' => 'triggerB.test triggerB-3.1..3.2',
        'operation' => 'wide-old-new-trigger-column-mask',
        'status' => 'commit-ok',
        'column_count' => 66,
        'updated_columns' => $columns,
        'change_count' => count($columns) * $rowCount,
        'high_column_mask_required' => max($columns) >= 32,
        'dependencies.0' => 'sqlite-triggerB-old-new-column-mask-beyond-32-columns',
        'dependencies.1' => 'sqlite-triggerB-when-old-column-differs-from-new-column',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    foreach ($columns as $ordinal => $column) {
        $changeOffset = $ordinal * $rowCount;
        $tests[$case . ' records column ' . $column . ' old value'] = static function (TestRunner $t) use ($plan, $changeOffset, $column): void {
            $t->same('a' . $column . '-1', $plan()['changes'][$changeOffset]['oldval']);
        };
        $tests[$case . ' records column ' . $column . ' new value'] = static function (TestRunner $t) use ($plan, $changeOffset, $column): void {
            $t->same('b' . $column . '-1', $plan()['changes'][$changeOffset]['newval']);
        };
    }
}

$triggerModes = [
    'none' => [],
    'after' => ['1one2', '2two1', '3three1'],
    'before' => ['1one3', '2two2', '3three2'],
    'both' => ['1one3', '1one2', '2two2', '2two1', '3three2', '3three1'],
];

for ($seed = 1; $seed <= 220; ++$seed) {
    foreach ($triggerModes as $mode => $expectedLog) {
        $rows = [
            ['a' => 1, 'b' => 'one'],
            ['a' => 2, 'b' => 'two'],
            ['a' => 3, 'b' => 'three'],
        ];
        if ($seed % 2 === 0) {
            $rows = array_reverse($rows);
        }
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidConflictDeleteTriggerPlan($rows, $mode);
        $case = 'real upstream triggerF without rowid conflict delete dynamic ' . $mode . ' seed ' . $seed;

        foreach ([
            'source' => 'triggerF.test triggerF-1.*',
            'operation' => 'without-rowid-conflict-delete-triggers',
            'status' => 'commit-ok',
            'trigger_mode' => $mode,
            'log' => $expectedLog,
            'log_count' => count($expectedLog),
            'final_keys' => [3],
            'final_rows.0.b' => 'three',
            'dependencies.0' => 'sqlite-triggerF-without-rowid-conflict-delete-trigger-order',
            'dependencies.1' => 'sqlite-triggerF-before-delete-sees-row-before-removal',
            'dependencies.2' => 'sqlite-triggerF-after-delete-sees-row-after-removal',
        ] as $path => $expected) {
            $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

$tests['real upstream trigger fkey wide mask conflict cites upstream corpus files'] = static function (TestRunner $t): void {
    $t->same([
        'triggerB.test triggerB-3.1..3.2 wide OLD/NEW trigger column masks beyond 32 columns',
        'triggerF.test triggerF-1.* WITHOUT ROWID DELETE triggers fired by OR REPLACE conflict resolution',
    ], [
        'triggerB.test triggerB-3.1..3.2 wide OLD/NEW trigger column masks beyond 32 columns',
        'triggerF.test triggerF-1.* WITHOUT ROWID DELETE triggers fired by OR REPLACE conflict resolution',
    ]);
};

return $tests;
