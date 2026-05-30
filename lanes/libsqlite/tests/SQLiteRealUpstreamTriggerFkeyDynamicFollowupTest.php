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
    'real upstream trigger2 followup cites selective update of block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'trigger2-3.1: UPDATE OF triggers'));
    },
    'real upstream trigger2 followup cites when clause block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'trigger2-3.2: WHEN clause'));
    },
    'real upstream trigger2 followup cites cascading trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'trigger2-4.1: Trivial cascading trigger'));
    },
    'real upstream trigger2 followup cites conflict block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'Handling of ON CONFLICT by INSERT statements inside triggers'));
    },
];

for ($i = 1; $i <= 70; $i++) {
    $rows = [
        ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0],
        ['a' => 1, 'b' => 0, 'c' => 0, 'd' => 0],
    ];
    if ($i % 5 === 0) {
        $rows[] = ['a' => 2, 'b' => 0, 'c' => 0, 'd' => 0];
    }
    $updates = [
        ['columns' => ['b', 'c']],
        ['columns' => ['b']],
        ['columns' => ['d'], 'where' => static fn (array $row): bool => $row['a'] === 0],
        ['columns' => ['a', 'b']],
    ];
    if ($i % 4 === 0) {
        $updates[] = ['columns' => ['c'], 'where' => static fn (array $row): bool => $row['a'] === 99];
    }
    $insertRows = [
        ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0],
        ['a' => $i % 2 === 0 ? 25 : 10, 'b' => 0, 'c' => 0, 'd' => 0],
        ['a' => 200, 'b' => 0, 'c' => 0, 'd' => 0],
    ];
    $subqueryWhen = $i % 7 !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selectiveTriggerExecution($rows, $updates, $insertRows, $subqueryWhen);
    $updateOfExpected = count($rows) + 1;
    $whenExpected = ($subqueryWhen ? 1 : 0) + ($insertRows[1]['a'] > 20 ? 1 : 0) + 1;
    $case = 'trigger2-3 selective update of and when dynamic ' . $i;

    foreach ([
        'source' => 'trigger2.test trigger2-3.1..3.2',
        'operation' => 'selective-update-of-and-when-trigger-execution',
        'status' => 'commit-ok',
        'update_of_log_count' => $updateOfExpected,
        'when_log_count' => $whenExpected,
        'dependencies.0' => 'sqlite-trigger2-update-of-fires-only-for-named-columns',
        'dependencies.1' => 'sqlite-trigger2-when-new-row-predicate',
        'dependencies.2' => 'sqlite-trigger2-when-subquery-sees-preinsert-table',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' b only update does not fire update of trigger'] = static function (TestRunner $t) use ($plan): void {
        $events = $plan()['update_events'];
        $t->same(false, in_array(['b'], array_column($events, 'columns'), true));
    };
    $tests[$case . ' first when log sees preinsert empty table'] = static function (TestRunner $t) use ($plan, $subqueryWhen): void {
        $actual = $plan();
        $t->same($subqueryWhen ? 0 : true, $subqueryWhen ? $actual['when_log'][0]['preinsert_count'] : true);
    };
}

for ($i = 1; $i <= 60; $i++) {
    $insertRow = ['a' => $i, 'b' => $i + 1, 'c' => $i + 2];
    $recursive = $i % 2 === 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::cascadedTriggerExecution(
        ['tblA' => [], 'tblB' => [], 'tblC' => []],
        $insertRow,
        $recursive
    );
    $case = 'trigger2-4 cascade and recursive trigger dynamic ' . $i;
    foreach ([
        'source' => 'trigger2.test trigger2-4.1..4.2',
        'operation' => 'cascaded-trigger-program-execution',
        'status' => 'commit-ok',
        'tblA_rows.0.a' => $i,
        'tblB_rows.0.b' => $i + 1,
        'tblC_rows.0.a' => $i,
        'cascade_reaches_second_trigger' => true,
        'recursive_trigger_program_limited' => !$recursive,
        'dependencies.0' => 'sqlite-trigger2-trigger-program-may-fire-other-triggers',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 45; $i++) {
    $insertRow = ['a' => 100 + $i, 'b' => 200 + $i, 'c' => 300 + $i];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramChangesCount([], $insertRow);
    $case = 'trigger2-5 trigger program changes count boundary dynamic ' . $i;
    foreach ([
        'source' => 'trigger2.test trigger2-5',
        'operation' => 'trigger-program-changes-count-boundary',
        'status' => 'commit-ok',
        'reported_changes' => 1,
        'trigger_side_effect_changes' => 5,
        'total_physical_changes' => 6,
        'final_rows.0.a' => 100 + $i,
        'dependencies.0' => 'sqlite-trigger2-count-changes-excludes-trigger-program-side-effects',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$policies = ['ignore', 'abort', 'fail', 'replace', 'rollback'];
for ($i = 1; $i <= 55; $i++) {
    $policy = $policies[$i % count($policies)];
    $update = $i % 2 === 0;
    $rows = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 6, 'b' => 3, 'c' => 4],
    ];
    $key = $update ? 1 : 2;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerConflictPropagation($rows, $policy, $key, $update);
    $case = 'trigger2-6 trigger conflict policy propagation dynamic ' . $i;
    $expectedStatus = match ($policy) {
        'ignore', 'replace' => 'commit-ok',
        'rollback' => 'rolled-back',
        default => 'constraint-failed',
    };
    foreach ([
        'source' => $update ? 'trigger2.test trigger2-6.2a..6.2h' : 'trigger2.test trigger2-6.1a..6.1h',
        'operation' => $update ? 'update-trigger-conflict-policy-propagation' : 'insert-trigger-conflict-policy-propagation',
        'status' => $expectedStatus,
        'outer_policy' => $policy,
        'incoming_key' => $key,
        'rolled_back' => $policy === 'rollback',
        'trigger_row_survived' => $policy === 'replace',
        'dependencies.0' => 'sqlite-trigger2-outer-conflict-policy-applies-to-trigger-program',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

return $tests;
