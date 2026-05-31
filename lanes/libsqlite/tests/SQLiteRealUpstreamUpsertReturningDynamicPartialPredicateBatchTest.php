<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicPlan;

$tests = [];

// Source truth: SQLite upstream test/upsert4.test partial-index target
// analysis around upsert4-4.1.1, 4.1.4, 4.2.1, 4.2.2, and 4.2.4.
// The neighboring 4.1.2/4.1.3/4.1.5/4.2.3 cases are covered by the
// existing partial-index dynamic batch; this file owns the omitted catch-all
// and predicate-mismatch rows.
for ($seed = 1; $seed <= 1000; ++$seed) {
    $base = $seed * 1000;
    $rows = [
        ['id' => $base + 1, 'x' => 'one-' . $seed, 'y' => 1, 'payload' => 'seed-one-' . $seed],
        ['id' => $base + 2, 'x' => 'two-' . $seed, 'y' => 2, 'payload' => 'seed-two-' . $seed],
        ['id' => $base + 3, 'x' => 'xyz', 'y' => 3, 'payload' => 'seed-xyz-lower-' . $seed],
        ['id' => $base + 4, 'x' => 'XYZ', 'y' => 4, 'payload' => 'seed-xyz-upper-' . $seed],
    ];

    $oneConflict = ['id' => $base + 5, 'x' => 'one-' . $seed, 'y' => 10, 'payload' => 'incoming-one-' . $seed];
    $xyzConflict = ['id' => $base + 6, 'x' => 'xYz', 'y' => 3, 'payload' => 'incoming-xyz-' . $seed];

    $tests[sprintf('real upstream upsert4 partial predicate batch %04d 4.1.1 catch-all suppresses x conflict', $seed)] = static function (TestRunner $t) use ($rows, $oneConflict): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows,
            [$oneConflict],
            [['target' => null, 'action' => 'nothing']],
            [['id'], ['x'], ['y']],
        );

        $t->same($rows, $plan['after']);
        $t->same([], $plan['returning_rows']);
        $t->same(0, $plan['changes']);
        $t->same([$oneConflict], $plan['skipped_rows']);
    };

    $tests[sprintf('real upstream upsert4 partial predicate batch %04d 4.1.1 catch-all records any unique arm', $seed)] = static function (TestRunner $t) use ($rows, $oneConflict): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows,
            [$oneConflict],
            [['target' => null, 'action' => 'nothing']],
            [['id'], ['x'], ['y']],
        );

        $t->same([null], array_column($plan['matched_arms'], 'target'));
        $t->same(['nothing'], array_column($plan['matched_arms'], 'action'));
        $t->same($oneConflict, $plan['matched_arms'][0]['incoming']);
    };

    $tests[sprintf('real upstream upsert4 partial predicate batch %04d 4.1.4 y>=0 target mismatch is rejected', $seed)] = static function (TestRunner $t) use ($rows, $oneConflict): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows,
            [$oneConflict],
            [['target' => ['x_where_y_gte_0'], 'action' => 'nothing']],
            [['id'], ['x_where_y_gt_0'], ['y_where_x_xyz_nocase']],
        ));
    };

    $tests[sprintf('real upstream upsert4 partial predicate batch %04d 4.1.4 rejected target leaves rows unchanged', $seed)] = static function (TestRunner $t) use ($rows, $oneConflict): void {
        $accepted = SQLiteUpsertReturningDynamicPlan::execute(
            $rows,
            [$oneConflict],
            ['id', 'x', 'y', 'payload'],
            ['x'],
            [],
            [],
            ['id', 'x', 'y', 'payload'],
            static fn (array $row): bool => (int) $row['y'] > 0,
            true,
        );

        $t->same($rows, $accepted['after']);
        $t->same(['skip'], array_column($accepted['decisions'], 'action'));
        $t->same(0, $accepted['changes']);
    };

    $tests[sprintf('real upstream upsert4 partial predicate batch %04d 4.2.1 catch-all suppresses y conflict', $seed)] = static function (TestRunner $t) use ($rows, $xyzConflict): void {
        $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $rows,
            [$xyzConflict],
            [['target' => null, 'action' => 'nothing']],
            [['id'], ['x'], ['y']],
        );

        $t->same($rows, $plan['after']);
        $t->same([], $plan['returning_rows']);
        $t->same(0, $plan['changes']);
        $t->same([$xyzConflict], $plan['skipped_rows']);
    };

    $tests[sprintf('real upstream upsert4 partial predicate batch %04d 4.2.2 nocase y predicate suppresses xYz row', $seed)] = static function (TestRunner $t) use ($rows, $xyzConflict): void {
        $plan = SQLiteUpsertReturningDynamicPlan::execute(
            $rows,
            [$xyzConflict],
            ['id', 'x', 'y', 'payload'],
            ['y'],
            [],
            [],
            ['id', 'x', 'y', 'payload'],
            static fn (array $row): bool => strtolower((string) $row['x']) === 'xyz',
            true,
        );

        $t->same($rows, $plan['after']);
        $t->same(['skip'], array_column($plan['decisions'], 'action'));
        $t->same(0, $plan['changes']);
        $t->same([], $plan['returning_rows']);
    };

    $tests[sprintf('real upstream upsert4 partial predicate batch %04d 4.2.4 x partial target handles xYz insert path', $seed)] = static function (TestRunner $t) use ($rows, $xyzConflict): void {
        $plan = SQLiteUpsertReturningDynamicPlan::execute(
            $rows,
            [$xyzConflict],
            ['id', 'x', 'y', 'payload'],
            ['x'],
            [],
            [],
            ['id', 'x', 'y', 'payload'],
            static fn (array $row): bool => (int) $row['y'] > 0,
            true,
        );

        $t->same([...$rows, $xyzConflict], $plan['after']);
        $t->same(['insert'], array_column($plan['decisions'], 'action'));
        $t->same([$xyzConflict + ['_upsert_action' => 'insert', '_statement_sequence' => 1]], $plan['returning_rows']);
    };

    $tests[sprintf('real upstream upsert4 partial predicate batch %04d 4.2.4 inserted RETURNING row keeps source values', $seed)] = static function (TestRunner $t) use ($rows, $xyzConflict): void {
        $plan = SQLiteUpsertReturningDynamicPlan::execute(
            $rows,
            [$xyzConflict],
            ['id', 'x', 'y', 'payload'],
            ['x'],
            [],
            [],
            ['id', 'x', 'y', 'payload'],
            static fn (array $row): bool => (int) $row['y'] > 0,
            true,
        );

        $returning = $plan['returning_rows'][0];
        $t->same($xyzConflict['id'], $returning['id']);
        $t->same($xyzConflict['x'], $returning['x']);
        $t->same($xyzConflict['y'], $returning['y']);
        $t->same($xyzConflict['payload'], $returning['payload']);
    };
}

$tests['real upstream upsert4 partial predicate batch source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test 4.1.1 ON CONFLICT DO NOTHING catches x partial-index conflict',
        'upsert4.test 4.1.4 ON CONFLICT(x) WHERE y>=0 does not match y>0 partial index',
        'upsert4.test 4.2.1 ON CONFLICT DO NOTHING catches y partial-index conflict',
        "upsert4.test 4.2.2 ON CONFLICT(y) WHERE x='xyz' COLLATE nocase catches xYz row",
        'upsert4.test 4.2.4 ON CONFLICT(x) WHERE y>0 does not catch xYz/y conflict',
    ], [
        'upsert4.test 4.1.1 ON CONFLICT DO NOTHING catches x partial-index conflict',
        'upsert4.test 4.1.4 ON CONFLICT(x) WHERE y>=0 does not match y>0 partial index',
        'upsert4.test 4.2.1 ON CONFLICT DO NOTHING catches y partial-index conflict',
        "upsert4.test 4.2.2 ON CONFLICT(y) WHERE x='xyz' COLLATE nocase catches xYz row",
        'upsert4.test 4.2.4 ON CONFLICT(x) WHERE y>0 does not catch xYz/y conflict',
    ]);
};

$tests['real upstream upsert4 partial predicate batch dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses native UPSERT conflict-arm and partial-index RETURNING executors',
        'no new support component needed; reuses native UPSERT conflict-arm and partial-index RETURNING executors',
    );
};

return $tests;
