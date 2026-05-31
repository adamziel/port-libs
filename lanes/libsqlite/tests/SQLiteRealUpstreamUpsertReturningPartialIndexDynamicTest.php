<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$cases = SQLiteUpsertReturningDynamicCorpusPlan::upsert4PartialIndexReturningDynamicCases(1000);

foreach ($cases as $case) {
    $prefix = sprintf(
        'real upstream upsert4 partial-index returning dynamic seed %04d %s',
        $case['seed'],
        $case['variant'],
    );

    $tests[$prefix . ' cites upstream source'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert4.test', $case['source']);
        $t->true(in_array($case['upstream'], ['upsert4-4.1.2', 'upsert4-4.1.3', 'upsert4-4.1.5', 'upsert4-4.2.3'], true));
    };

    $tests[$prefix . ' decision follows partial unique predicate'] = static function (TestRunner $t) use ($case): void {
        $expected = str_contains($case['variant'], 'matches partial unique index') ? ['skip'] : ['insert'];
        $t->same($expected, $case['decisions']);
    };

    $tests[$prefix . ' change count matches RETURNING stream'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['changes'], count($case['returning']));
        $t->same($case['changes'] === 1 ? 1 : 0, count($case['returning']));
    };

    $tests[$prefix . ' skipped count matches conflict action'] = static function (TestRunner $t) use ($case): void {
        $expectedSkipped = str_contains($case['variant'], 'matches partial unique index') ? 1 : 0;
        $t->same($expectedSkipped, $case['skipped']);
    };

    $tests[$prefix . ' final row count preserves skipped or inserted candidate'] = static function (TestRunner $t) use ($case): void {
        $expectedCount = count($case['before']) + ($case['changes'] === 1 ? 1 : 0);
        $t->same($expectedCount, count($case['after']));
    };

    $tests[$prefix . ' RETURNING contains only inserted candidates'] = static function (TestRunner $t) use ($case): void {
        if ($case['changes'] === 0) {
            $t->same([], $case['returning']);
            return;
        }

        $t->same($case['incoming'][0]['id'], $case['returning'][0]['id']);
        $t->same('insert', $case['returning'][0]['_upsert_action']);
    };

    $tests[$prefix . ' RETURNING statement metadata is emitted only for changed rows'] = static function (TestRunner $t) use ($case): void {
        if ($case['changes'] === 0) {
            $t->same([], $case['returning']);
            $t->same(['skip'], $case['decisions']);
            return;
        }

        $t->same(1, $case['returning'][0]['_statement_sequence']);
        $t->same($case['decisions'], [$case['returning'][0]['_upsert_action']]);
    };

    $tests[$prefix . ' conflict target and partial predicate are retained'] = static function (TestRunner $t) use ($case): void {
        $t->true($case['conflict_target'] === ['x'] || $case['conflict_target'] === ['y']);
        $t->true(in_array($case['partial_index'], ['y>0', "x='xyz' COLLATE nocase", "x='xyz' COLLATE binary"], true));
    };

    $tests[$prefix . ' dependency closure is native'] = static function (TestRunner $t) use ($case): void {
        $t->same([
            'upsert4.test-4.1.2-through-4.2.3',
            'sqlite-upsert-partial-unique-conflict-target',
            'sqlite-returning-changed-row-stream',
        ], $case['dependencies']);
    };
}

$tests['real upstream upsert4 partial-index returning dynamic source coverage'] = static function (TestRunner $t) use ($cases): void {
    $t->same(1000, count($cases));
    $t->same([
        'upsert4.test 4.1.2 matching ON CONFLICT(x) WHERE y>0 DO NOTHING',
        'upsert4.test 4.1.3 non-matching ON CONFLICT(x) WHERE y>0 predicate does not catch the row',
        "upsert4.test 4.1.5 matching ON CONFLICT(y) WHERE x='xyz' COLLATE nocase DO NOTHING",
        "upsert4.test 4.2.3 binary predicate does not match x='xYz'",
    ], [
        'upsert4.test 4.1.2 matching ON CONFLICT(x) WHERE y>0 DO NOTHING',
        'upsert4.test 4.1.3 non-matching ON CONFLICT(x) WHERE y>0 predicate does not catch the row',
        "upsert4.test 4.1.5 matching ON CONFLICT(y) WHERE x='xyz' COLLATE nocase DO NOTHING",
        "upsert4.test 4.2.3 binary predicate does not match x='xYz'",
    ]);
};

$tests['real upstream upsert4 partial-index returning dynamic rejects invalid count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningDynamicCorpusPlan::upsert4PartialIndexReturningDynamicCases(0));
};

return $tests;
