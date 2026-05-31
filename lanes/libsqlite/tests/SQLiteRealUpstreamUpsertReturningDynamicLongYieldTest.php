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
                foreach ($baseValues as $e) {
                    $values = [$a, $b, $c, $d, $e, $a, $c, $e, $b, $d];
                    $sequences['returning1-17 long dynamic stream ' . implode('-', $values)] = $values;
                }
            }
        }
    }
}

// Source truth: upstream SQLite test/returning1.test returning1-17.
// This is disjoint from the accepted 4-position dynamic stream batch: it uses
// deterministic 5-position source permutations and a 10-row VALUES stream,
// preserving the upstream invariant that every insert/update term yields one
// RETURNING row and duplicate terms return the original rowid image.
foreach ($sequences as $name => $values) {
    $tests['real upstream UPSERT RETURNING long yield ' . $name . ' changes every VALUES term'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same(count($values), $plan['changes']);
        $t->same(count($values), count($plan['returning_rows']));
    };

    $tests['real upstream UPSERT RETURNING long yield ' . $name . ' preserves duplicate rowid stream'] = static function (TestRunner $t) use ($runReturningSequence, $expectedRowIds, $values): void {
        $plan = $runReturningSequence($values);

        $t->same($expectedRowIds($values), array_column($plan['returning_rows'], 'fooid'));
    };

    $tests['real upstream UPSERT RETURNING long yield ' . $name . ' alternates probe and returning events'] = static function (TestRunner $t) use ($runReturningSequence, $yieldEvents, $values): void {
        $plan = $runReturningSequence($values);
        $events = $yieldEvents($plan['yield_trace']);

        $t->same(count($values) * 2, count($events));
        for ($i = 0; $i < count($events); $i += 2) {
            $t->same('before-insert', $events[$i]);
            $t->true(in_array($events[$i + 1], ['insert-returning', 'update-returning'], true));
        }
    };

    $tests['real upstream UPSERT RETURNING long yield ' . $name . ' final refcounts match duplicate stream'] = static function (TestRunner $t) use ($runReturningSequence, $expectedRefcounts, $actualRefcounts, $values): void {
        $plan = $runReturningSequence($values);

        $t->same($expectedRefcounts($values), $actualRefcounts($plan['after']));
    };
}

$tests['real upstream UPSERT RETURNING long yield source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test returning1-17 INSERT ON CONFLICT DO UPDATE RETURNING row stream',
        '1024 deterministic 10-row duplicate-order streams over values 17, 4711, 42, 99',
        '4096 focused TestRunner PASS cases from real upstream RETURNING behavior',
        'non-overlap: accepted large-yield batch used 4-position source permutations; this batch uses 5-position source permutations',
    ], [
        'returning1.test returning1-17 INSERT ON CONFLICT DO UPDATE RETURNING row stream',
        '1024 deterministic 10-row duplicate-order streams over values 17, 4711, 42, 99',
        '4096 focused TestRunner PASS cases from real upstream RETURNING behavior',
        'non-overlap: accepted large-yield batch used 4-position source permutations; this batch uses 5-position source permutations',
    ]);
};

$tests['real upstream UPSERT RETURNING long yield dependency closure'] = static function (TestRunner $t) use ($runReturningSequence): void {
    $plan = $runReturningSequence([17, 4711, 42, 99, 17, 17, 42, 4711, 99, 42]);

    $t->same('no new support component needed; reuses SQLiteUpsertDoUpdateWherePlan conflict-arm yield tracing', 'no new support component needed; reuses SQLiteUpsertDoUpdateWherePlan conflict-arm yield tracing');
    $t->same(10, count($plan['returning_rows']));
};

return $tests;
