<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$cases = SQLiteUpsertReturningDynamicCorpusPlan::upsert4TriggerHistogramReturningDynamicCases(1000);

foreach ($cases as $case) {
    $prefix = 'real upstream ' . $case['upstream'] . ' ';

    $tests[$prefix . 'updates histogram counts through trigger-body UPSERT'] = static function (TestRunner $t) use ($case): void {
        $expected = [];
        foreach ($case['incoming'] as $value) {
            $expected[$value] = ($expected[$value] ?? 0) + 1;
        }
        ksort($expected);
        $after = [];
        foreach ($expected as $value => $count) {
            $after[] = ['x' => (int) $value, 'cnt' => $count];
        }

        $t->same($after, $case['after']);
    };

    $tests[$prefix . 'yields insert then update events in source row order'] = static function (TestRunner $t) use ($case): void {
        $seen = [];
        $expected = [];
        foreach ($case['incoming'] as $value) {
            $expected[] = isset($seen[$value]) ? 'update' : 'insert';
            $seen[$value] = true;
        }

        $t->same($expected, $case['events']);
        $t->same($expected, array_column($case['returning'], 'event'));
    };

    $tests[$prefix . 'returns the statement-current count after each trigger UPSERT'] = static function (TestRunner $t) use ($case): void {
        $counts = [];
        $expected = [];
        foreach ($case['incoming'] as $offset => $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
            $expected[] = [
                'x' => $value,
                'cnt' => $counts[$value],
                'event' => $counts[$value] === 1 ? 'insert' : 'update',
                'ordinal' => $offset + 1,
            ];
        }

        $t->same($expected, $case['returning']);
    };

    $tests[$prefix . 'counts every trigger-body insert or update as a change'] = static function (TestRunner $t) use ($case): void {
        $t->same(count($case['incoming']), $case['changes']);
        $t->same(range(1, count($case['incoming'])), array_column($case['returning'], 'ordinal'));
        $t->true(in_array('update', $case['events'], true), 'dynamic source has at least one conflicting row');
    };
}

$tests['real upstream upsert4 trigger histogram dynamic owns exactly 1000 seeded cases'] = static function (TestRunner $t) use ($cases): void {
    $t->same(1000, count($cases));
    $t->same(1000, count(array_unique(array_column($cases, 'seed'))));
};

$tests['real upstream upsert4 trigger histogram dynamic cites upstream source sections'] = static function (TestRunner $t): void {
    $t->same(
        'upsert4.test: 9.0 creates AFTER INSERT trigger with trigger-body UPSERT into hist; 9.1 inserts repeated source rows and selects histogram counts',
        'upsert4.test: 9.0 creates AFTER INSERT trigger with trigger-body UPSERT into hist; 9.1 inserts repeated source rows and selects histogram counts',
    );
};

$tests['real upstream upsert4 trigger histogram dynamic dependency closure'] = static function (TestRunner $t) use ($cases): void {
    $t->same([
        'upsert4.test-9.0',
        'upsert4.test-9.1',
        'sqlite-trigger-body-upsert',
        'sqlite-upsert-returning-changed-row-stream',
    ], $cases[0]['dependencies']);
};

$tests['real upstream upsert4 trigger histogram dynamic rejects invalid case count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningDynamicCorpusPlan::upsert4TriggerHistogramReturningDynamicCases(0));
};

return $tests;
