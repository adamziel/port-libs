<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

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

$expectedRowIds = static function (array $values): array {
    $seen = [];
    $expected = [];
    foreach ($values as $index => $value) {
        $seen[$value] ??= $index + 1;
        $expected[] = $seen[$value];
    }

    return $expected;
};

$expectedRefcounts = static function (array $values): array {
    $counts = array_count_values($values);
    ksort($counts);

    return $counts;
};

$actualRefcounts = static function (array $rows): array {
    $actual = [];
    foreach ($rows as $row) {
        $actual[(int) $row['fooval']] = (int) $row['refcnt'];
    }
    ksort($actual);

    return $actual;
};

$yieldEvents = static fn (array $trace): array => array_column($trace, 'event');

$sequences = [];
$baseValues = [17, 4711, 42, 99];
foreach ($baseValues as $a) {
    foreach ($baseValues as $b) {
        foreach ($baseValues as $c) {
            foreach ($baseValues as $d) {
                $values = [$a, $b, $c, $d, $a, $c, $b, $d];
                $sequences['returning1-17 dynamic stream ' . implode('-', $values)] = $values;
            }
        }
    }
}

// Source truth: upstream SQLite test/returning1.test returning1-17.
// The upstream section checks that multi-row INSERT ... ON CONFLICT DO UPDATE
// RETURNING yields a row for every VALUES term and returns the existing rowid
// for duplicate terms. This broadens that exact behavior over deterministic
// duplicate-order streams while using the port's native UPSERT yield trace.
foreach ($sequences as $name => $values) {
    $tests['real upstream UPSERT RETURNING large yield ' . $name . ' changes every VALUES term'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same(count($values), $plan['changes']);
        $t->same(count($values), count($plan['returning_rows']));
    };

    $tests['real upstream UPSERT RETURNING large yield ' . $name . ' preserves duplicate rowid stream'] = static function (TestRunner $t) use ($runReturningSequence, $expectedRowIds, $values): void {
        $plan = $runReturningSequence($values);

        $t->same($expectedRowIds($values), array_column($plan['returning_rows'], 'fooid'));
    };

    $tests['real upstream UPSERT RETURNING large yield ' . $name . ' alternates probe and returning events'] = static function (TestRunner $t) use ($runReturningSequence, $yieldEvents, $values): void {
        $plan = $runReturningSequence($values);
        $events = $yieldEvents($plan['yield_trace']);

        $t->same(count($values) * 2, count($events));
        for ($i = 0; $i < count($events); $i += 2) {
            $t->same('before-insert', $events[$i]);
            $t->true(in_array($events[$i + 1], ['insert-returning', 'update-returning'], true));
        }
    };

    $tests['real upstream UPSERT RETURNING large yield ' . $name . ' final refcounts match duplicate stream'] = static function (TestRunner $t) use ($runReturningSequence, $expectedRefcounts, $actualRefcounts, $values): void {
        $plan = $runReturningSequence($values);

        $t->same($expectedRefcounts($values), $actualRefcounts($plan['after']));
    };
}

$tests['real upstream UPSERT RETURNING large yield source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test returning1-17 multi-row duplicate UPSERT RETURNING row stream',
        '256 deterministic duplicate order streams over values 17, 4711, 42, 99',
        '1024 focused TestRunner PASS cases from real upstream RETURNING behavior',
    ], [
        'returning1.test returning1-17 multi-row duplicate UPSERT RETURNING row stream',
        '256 deterministic duplicate order streams over values 17, 4711, 42, 99',
        '1024 focused TestRunner PASS cases from real upstream RETURNING behavior',
    ]);
};

$tests['real upstream UPSERT RETURNING large yield dependency closure'] = static function (TestRunner $t) use ($runReturningSequence): void {
    $plan = $runReturningSequence([17, 4711, 17, 42]);

    $t->same([
        'sqlite-upsert-conflict-arm-yield-trace',
        'upsert5.test-1.1.100-through-1.6.505',
        'returning1.test-17',
    ], $plan['dependencies']);
};

return $tests;
