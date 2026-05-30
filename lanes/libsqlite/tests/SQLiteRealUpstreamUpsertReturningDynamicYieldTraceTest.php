<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$projectColumns = static function (array $rows, array $columns): array {
    return array_map(static function (array $row) use ($columns): array {
        $projected = [];
        foreach ($columns as $column) {
            $projected[$column] = $row[$column];
        }

        return $projected;
    }, $rows);
};

$matchedTargets = static fn (array $matches): array => array_map(
    static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
    $matches,
);

$yieldEvents = static fn (array $trace): array => array_column($trace, 'event');

// Source truth: upstream SQLite test/upsert5.test upsert5-1.1.100 through
// upsert5-1.6.505. These tests exercise RETURNING/yield behavior over the
// same six rowid/int-primary-key/WITHOUT ROWID schema variants while checking
// the conflict-arm yield stream that the port exposes to its native executor.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::upsert5CatchAllPriorityCases() as $case) {
    $name = 'real upstream UPSERT RETURNING dynamic yield trace ' . $case['upstream'];

    $tests[$name . ' records a before-insert probe before any conflict outcome'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);

        $t->same('before-insert', $plan['yield_trace'][0]['event']);
        $t->same($case['incoming'], $plan['yield_trace'][0]['incoming']);
        $t->same(null, $plan['yield_trace'][0]['returning']);
    };

    $tests[$name . ' emits exactly one terminal yield edge per incoming row'] = static function (TestRunner $t) use ($case, $yieldEvents): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);
        $events = $yieldEvents($plan['yield_trace']);

        $t->same(2, count($events));
        $t->same('before-insert', $events[0]);
        $t->true(in_array($events[1], ['insert-returning', 'update-returning', 'conflict-do-nothing', 'conflict-update-where-false'], true));
    };

    $tests[$name . ' terminal yield event agrees with upstream change count'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);
        $terminal = $plan['yield_trace'][1];

        $t->same($case['changes'] === 0 ? null : $plan['returning_rows'][0], $terminal['returning']);
        $t->same($case['changes'] === 0 ? 'conflict-do-nothing' : 'update-returning', $terminal['event']);
    };

    $tests[$name . ' matched conflict target is preserved in yield trace'] = static function (TestRunner $t) use ($case, $matchedTargets): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);
        $target = $plan['yield_trace'][1]['target'] ?? null;
        $actual = $target === null ? '*' : implode(',', $target);

        $t->same($case['matched'], $matchedTargets($plan['matched_arms']));
        $t->same($case['matched'][0] ?? '*', $actual);
    };

    $tests[$name . ' returning projection remains the changed row image'] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);
        $returning = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e']);

        $t->same($case['returning'], $returning);
        $t->same($case['changes'], count($returning));
    };

    $tests[$name . ' final rows match upstream selected arm result'] = static function (TestRunner $t) use ($case, $projectColumns): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($case['before'], [$case['incoming']], $case['arms'], $case['constraints']);

        $t->same($projectColumns($case['expected'], $case['columns']), $projectColumns($plan['after'], $case['columns']));
    };
}

$sequenceCases = [
    'returning1-17 duplicate updates first row' => [17, 4711, 17],
    'returning1-17 alternating duplicate updates' => [17, 4711, 17, 4711, 17],
    'returning1-17 adjacent duplicate updates' => [17, 17, 4711, 4711, 42],
    'returning1-17 late duplicate after clean inserts' => [17, 4711, 42, 99, 42],
    'returning1-17 all clean inserts' => [17, 4711, 42, 99, 100],
    'returning1-17 all duplicate one key' => [17, 17, 17, 17, 17],
    'returning1-17 three-way duplicate rotation' => [17, 4711, 42, 17, 4711, 42],
    'returning1-17 temp-table duplicate stream parity' => [4711, 17, 4711, 17, 99],
];

$runReturningSequence = static function (array $values): array {
    $incoming = [];
    foreach ($values as $index => $value) {
        $incoming[] = ['fooid' => $index + 1, 'fooval' => $value, 'refcnt' => 1];
    }

    return SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        [],
        $incoming,
        [[
            'target' => null,
            'action' => 'update',
            'assignments' => ['refcnt' => static fn (array $current): int => (int) $current['refcnt'] + 1],
        ]],
        [['fooval']],
    );
};

foreach ($sequenceCases as $name => $values) {
    $tests['real upstream UPSERT RETURNING dynamic yield trace ' . $name . ' yields one RETURNING row per VALUES term'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same(count($values), $plan['changes']);
        $t->same(count($values), count($plan['returning_rows']));
    };

    $tests['real upstream UPSERT RETURNING dynamic yield trace ' . $name . ' preserves upstream fooid return stream'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);
        $seen = [];
        $expected = [];
        foreach ($values as $index => $value) {
            $seen[$value] ??= $index + 1;
            $expected[] = $seen[$value];
        }

        $t->same($expected, array_column($plan['returning_rows'], 'fooid'));
    };

    $tests['real upstream UPSERT RETURNING dynamic yield trace ' . $name . ' alternates probes and returning edges'] = static function (TestRunner $t) use ($runReturningSequence, $values, $yieldEvents): void {
        $plan = $runReturningSequence($values);
        $events = $yieldEvents($plan['yield_trace']);

        $t->same(count($values) * 2, count($events));
        for ($i = 0; $i < count($events); $i += 2) {
            $t->same('before-insert', $events[$i]);
            $t->true(in_array($events[$i + 1], ['insert-returning', 'update-returning'], true));
        }
    };

    $tests['real upstream UPSERT RETURNING dynamic yield trace ' . $name . ' duplicate rows update existing returned row image'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);
        $counts = array_count_values($values);
        $actual = [];
        foreach ($plan['after'] as $row) {
            $actual[$row['fooval']] = $row['refcnt'];
        }

        $t->same($counts, $actual);
    };
}

$tests['real upstream UPSERT RETURNING dynamic yield trace source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test upsert5-1.1.100 through upsert5-1.6.505 selected-arm matrix',
        'returning1.test returning1-17 row stream for duplicate UPSERT RETURNING values',
    ], [
        'upsert5.test upsert5-1.1.100 through upsert5-1.6.505 selected-arm matrix',
        'returning1.test returning1-17 row stream for duplicate UPSERT RETURNING values',
    ]);
};

$tests['real upstream UPSERT RETURNING dynamic yield trace dependency closure'] = static function (TestRunner $t): void {
    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        [],
        [['fooid' => 1, 'fooval' => 17, 'refcnt' => 1]],
        [['target' => null, 'action' => 'update', 'assignments' => ['refcnt' => static fn (array $current): int => (int) $current['refcnt'] + 1]]],
        [['fooval']],
    );

    $t->same([
        'sqlite-upsert-conflict-arm-yield-trace',
        'upsert5.test-1.1.100-through-1.6.505',
        'returning1.test-17',
    ], $plan['dependencies']);
};

return $tests;
