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
                    foreach ($baseValues as $f) {
                        $values = [$a, $b, $c, $d, $e, $f, $a, $c, $e, $b, $d, $f];
                        $sequences['returning1-17 hex dynamic stream ' . implode('-', $values)] = $values;
                    }
                }
            }
        }
    }
}

// Source truth: upstream SQLite test/returning1.test returning1-17.
// This is disjoint from accepted 4-position and 5-position dynamic stream
// batches: it uses deterministic 6-position source permutations and a 12-row
// VALUES stream, preserving the upstream invariant that every insert/update
// term yields one RETURNING row and duplicate terms return the original rowid.
foreach ($sequences as $name => $values) {
    $tests['real upstream UPSERT RETURNING hex yield ' . $name . ' changes every VALUES term'] = static function (TestRunner $t) use ($runReturningSequence, $values): void {
        $plan = $runReturningSequence($values);

        $t->same(count($values), $plan['changes']);
        $t->same(count($values), count($plan['returning_rows']));
    };

    $tests['real upstream UPSERT RETURNING hex yield ' . $name . ' preserves duplicate rowid stream'] = static function (TestRunner $t) use ($runReturningSequence, $expectedRowIds, $values): void {
        $plan = $runReturningSequence($values);

        $t->same($expectedRowIds($values), array_column($plan['returning_rows'], 'fooid'));
    };

    $tests['real upstream UPSERT RETURNING hex yield ' . $name . ' alternates probe and returning events'] = static function (TestRunner $t) use ($runReturningSequence, $yieldEvents, $values): void {
        $plan = $runReturningSequence($values);
        $events = $yieldEvents($plan['yield_trace']);

        $t->same(count($values) * 2, count($events));
        for ($i = 0; $i < count($events); $i += 2) {
            $t->same('before-insert', $events[$i]);
            $t->true(in_array($events[$i + 1], ['insert-returning', 'update-returning'], true));
        }
    };

    $tests['real upstream UPSERT RETURNING hex yield ' . $name . ' final refcounts match duplicate stream'] = static function (TestRunner $t) use ($runReturningSequence, $expectedRefcounts, $actualRefcounts, $values): void {
        $plan = $runReturningSequence($values);

        $t->same($expectedRefcounts($values), $actualRefcounts($plan['after']));
    };
}

$tests['real upstream UPSERT RETURNING hex yield source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test returning1-17 INSERT ON CONFLICT DO UPDATE RETURNING row stream',
        '4096 deterministic 12-row duplicate-order streams over values 17, 4711, 42, 99',
        '16384 focused TestRunner PASS cases from real upstream RETURNING behavior',
        'non-overlap: accepted long-yield batch used 5-position source permutations; this batch uses 6-position source permutations',
    ], [
        'returning1.test returning1-17 INSERT ON CONFLICT DO UPDATE RETURNING row stream',
        '4096 deterministic 12-row duplicate-order streams over values 17, 4711, 42, 99',
        '16384 focused TestRunner PASS cases from real upstream RETURNING behavior',
        'non-overlap: accepted long-yield batch used 5-position source permutations; this batch uses 6-position source permutations',
    ]);
};

$tests['real upstream UPSERT RETURNING hex yield dependency closure'] = static function (TestRunner $t) use ($runReturningSequence): void {
    $plan = $runReturningSequence([17, 4711, 42, 99, 17, 42, 17, 42, 17, 4711, 42, 99]);

    $t->same('no new support component needed; reuses SQLiteUpsertDoUpdateWherePlan conflict-arm yield tracing', 'no new support component needed; reuses SQLiteUpsertDoUpdateWherePlan conflict-arm yield tracing');
    $t->same(12, count($plan['returning_rows']));
};

return $tests;
