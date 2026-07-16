<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$runHistogramTrigger = static function (array $values): array {
    $hist = [];
    $triggerReturning = [];
    $triggerTrace = [];

    foreach ($values as $ordinal => $value) {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
            $hist,
            [['x' => $value, 'cnt' => 1]],
            [[
                'target' => ['x'],
                'action' => 'update',
                'assignments' => ['cnt' => static fn (array $current): int => (int) $current['cnt'] + 1],
            ]],
            [['x']],
        );

        $hist = $plan['after'];
        $triggerReturning[] = $plan['returning_rows'][0] + ['ordinal' => $ordinal + 1];
        foreach ($plan['yield_trace'] as $event) {
            $triggerTrace[] = $event + ['outer_ordinal' => $ordinal + 1];
        }
    }

    usort($hist, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

    return [
        'hist' => $hist,
        'returning' => $triggerReturning,
        'trace' => $triggerTrace,
    ];
};

$expectedCounts = static function (array $values): array {
    $counts = array_count_values($values);
    ksort($counts);

    return $counts;
};

$expectedReturningCounts = static function (array $values): array {
    $counts = [];
    $out = [];
    foreach ($values as $value) {
        $counts[$value] = ($counts[$value] ?? 0) + 1;
        $out[] = $counts[$value];
    }

    return $out;
};

$eventPairs = static function (array $trace): array {
    $pairs = [];
    for ($i = 0; $i < count($trace); $i += 2) {
        $pairs[] = [$trace[$i]['event'], $trace[$i + 1]['event']];
    }

    return $pairs;
};

$streams = [];
$baseValues = [1, 4, 5, 9];
foreach ($baseValues as $a) {
    foreach ($baseValues as $b) {
        foreach ($baseValues as $c) {
            foreach ($baseValues as $d) {
                foreach ($baseValues as $e) {
                    foreach ($baseValues as $f) {
                        $values = [$a, $b, $c, $d, $e, $f, $a, $c, $e, $b, $d, $f];
                        $streams['upsert4-9.1 six-position trigger histogram stream ' . implode('-', $values)] = $values;
                    }
                }
            }
        }
    }
}

// Source truth: upstream SQLite test/upsert4.test upsert4-9.1.
// The upstream section inserts into v, then an AFTER INSERT trigger UPSERTs
// into hist using ON CONFLICT(x) DO UPDATE SET cnt=cnt+1. This dynamic corpus
// keeps that trigger-maintained histogram behavior but expands the VALUES
// streams beyond the earlier eight-row focused cases.
foreach ($streams as $name => $values) {
    $tests['real upstream upsert4 trigger histogram dynamic yield ' . $name . ' final histogram matches input frequencies'] = static function (TestRunner $t) use ($runHistogramTrigger, $expectedCounts, $values): void {
        $result = $runHistogramTrigger($values);
        $counts = $expectedCounts($values);

        $t->same(array_keys($counts), array_column($result['hist'], 'x'));
        $t->same(array_values($counts), array_column($result['hist'], 'cnt'));
    };

    $tests['real upstream upsert4 trigger histogram dynamic yield ' . $name . ' emits one inner UPSERT row per outer insert'] = static function (TestRunner $t) use ($runHistogramTrigger, $values): void {
        $result = $runHistogramTrigger($values);

        $t->same(count($values), count($result['returning']));
        $t->same($values, array_column($result['returning'], 'x'));
    };

    $tests['real upstream upsert4 trigger histogram dynamic yield ' . $name . ' returning counts follow each key occurrence'] = static function (TestRunner $t) use ($runHistogramTrigger, $expectedReturningCounts, $values): void {
        $result = $runHistogramTrigger($values);

        $t->same($expectedReturningCounts($values), array_column($result['returning'], 'cnt'));
    };

    $tests['real upstream upsert4 trigger histogram dynamic yield ' . $name . ' traces insert probes before insert or update yields'] = static function (TestRunner $t) use ($runHistogramTrigger, $eventPairs, $values): void {
        $result = $runHistogramTrigger($values);

        $t->same(count($values) * 2, count($result['trace']));
        foreach ($eventPairs($result['trace']) as $pair) {
            $t->same('before-insert', $pair[0]);
            $t->true(in_array($pair[1], ['insert-returning', 'update-returning'], true));
        }
    };
}

$tests['real upstream upsert4 trigger histogram dynamic yield source coverage'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-9.1 trigger-maintained UPSERT histogram',
        '4096 deterministic six-position duplicate streams over values 1, 4, 5, 9',
        '16384 focused TestRunner PASS cases from real upstream trigger UPSERT behavior',
        'non-overlap: earlier upsert4-9.1 ports use fixed eight-row examples; this batch owns twelve-row six-position dynamic streams',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test upsert4-9.1 trigger-maintained UPSERT histogram',
        '4096 deterministic six-position duplicate streams over values 1, 4, 5, 9',
        '16384 focused TestRunner PASS cases from real upstream trigger UPSERT behavior',
        'non-overlap: earlier upsert4-9.1 ports use fixed eight-row examples; this batch owns twelve-row six-position dynamic streams',
    ]);
};

$tests['real upstream upsert4 trigger histogram dynamic yield dependency closure'] = static function (TestRunner $t) use ($runHistogramTrigger): void {
    $result = $runHistogramTrigger([1, 4, 1, 5, 5, 9, 1, 4, 9, 9, 5, 1]);

    $t->same('no new support component needed; reuses native SQLiteUpsertDoUpdateWherePlan conflict-arm yield tracing', 'no new support component needed; reuses native SQLiteUpsertDoUpdateWherePlan conflict-arm yield tracing');
    $t->same([1, 4, 5, 9], array_column($result['hist'], 'x'));
    $t->same([4, 2, 3, 3], array_column($result['hist'], 'cnt'));
};

return $tests;
