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
    'real upstream trigger dynamic wide rowid matrix cites source sections' => static function (TestRunner $t): void {
        $triggerB = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerB.test');
        $triggerD = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test');
        $triggerF = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test');

        $t->true(is_string($triggerB) && str_contains($triggerB, 'Triggers maintain a mask of columns'));
        $t->true(is_string($triggerD) && str_contains($triggerD, 'CREATE TRIGGER r1 BEFORE INSERT ON t1'));
        $t->true(is_string($triggerF) && str_contains($triggerF, 'WITHOUT ROWID'));
    },
];

for ($case = 1; $case <= 50; ++$case) {
    $rows = [];
    for ($row = 1; $row <= 4; ++$row) {
        $entry = ['setting_id' => ($case * 10) + $row];
        foreach ([0, 1, 31, 32, 33, 40, 63, 65] as $column) {
            $entry['c' . $column] = 'a' . $column . '-' . $row;
        }
        $rows[] = $entry;
    }

    $columns = match ($case % 5) {
        0 => [0, 32, 65],
        1 => [1, 31, 63],
        2 => [32, 33, 40],
        3 => [0, 1, 31, 32],
        default => [40, 63, 65],
    };
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::wideColumnTriggerMaskPlan($rows, $columns, 66);
    $expectedChangeCount = count(array_unique($columns)) * count($rows);
    $expectedFirstColumn = array_values(array_unique($columns))[0];

    foreach ([
        'source' => 'triggerB.test triggerB-3.1..3.2',
        'operation' => 'wide-old-new-trigger-column-mask',
        'status' => 'commit-ok',
        'column_count' => 66,
        'change_count' => $expectedChangeCount,
        'high_column_mask_required' => true,
        'dependencies.0' => 'sqlite-triggerB-old-new-column-mask-beyond-32-columns',
        'changes.0.colnum' => $expectedFirstColumn,
    ] as $path => $expected) {
        $tests['triggerB-3 wide old-new column mask dynamic ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($plan(), (string) $path));
        };
    }
}

foreach (range(1, 50) as $case) {
    $declared = ($case % 2) === 0;
    $event = ['insert', 'update', 'delete'][$case % 3];
    $physical = 1000 + $case;
    $row = $declared
        ? ['rowid' => $case, 'oid' => $case + 100, '_rowid_' => $case + 200, 'x' => $case + 300]
        : ['x' => $case + 300];

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolutionPlan($row, $event, $declared, $physical);
    $expectedLogCount = $event === 'update' ? 4 : 2;
    $expectedInsertBefore = !$declared && $event === 'insert';
    $expectedPhysical = $declared ? ($event === 'update' ? $case : $case) : ($event === 'insert' ? -1 : $physical);

    foreach ([
        'source' => 'triggerD.test triggerD-1.1..2.4',
        'operation' => 'trigger-rowid-alias-resolution',
        'status' => 'commit-ok',
        'event' => $event,
        'declared_rowid_columns' => $declared,
        'log_count' => $expectedLogCount,
        'insert_before_trigger_sees_unassigned_rowid' => $expectedInsertBefore,
        'rowid_values.0' => $expectedPhysical,
    ] as $path => $expected) {
        $tests['triggerD rowid alias old-new dynamic ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($plan(), (string) $path));
        };
    }
}

foreach (range(1, 25) as $case) {
    $before = ($case % 3) !== 0;
    $after = !$before || ($case % 2) === 0;
    $rows = [
        ['a' => 1, 'b' => 'one-' . $case],
        ['a' => 2, 'b' => 'two-' . $case],
        ['a' => 3, 'b' => 'three-' . $case],
    ];

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::withoutRowidReplaceDeleteTriggerPlan($rows, $before, $after);
    $expectedTriggerCount = (int) $before + (int) $after;
    $expectedLogCount = 3 * $expectedTriggerCount;

    foreach ([
        'source' => 'triggerF.test 1.2..1.4',
        'operation' => 'without-rowid-replace-delete-trigger-log',
        'status' => 'commit-ok',
        'before_trigger' => $before,
        'after_trigger' => $after,
        'trigger_count' => $expectedTriggerCount,
        'log_count' => $expectedLogCount,
        'without_rowid_primary_key_preserved' => true,
    ] as $path => $expected) {
        $tests['triggerF without-rowid replace delete dynamic ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($plan(), (string) $path));
        };
    }
}

return $tests;
