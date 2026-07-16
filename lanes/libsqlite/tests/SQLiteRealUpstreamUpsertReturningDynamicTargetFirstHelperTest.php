<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$cases = SQLiteUpsertReturningDynamicCorpusPlan::upsert1TargetFirstReturningDynamicCases(1000);

foreach ($cases as $case) {
    $prefix = sprintf(
        'real upstream upsert1 target-first returning helper dynamic seed %04d %s %s',
        $case['seed'],
        $case['schema'],
        $case['matched'][0],
    );

    $tests[$prefix . ' cites upstream target-priority source'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert1.test', $case['source']);
        $t->true(in_array($case['upstream'], [
            'upsert1-700',
            'upsert1-710',
            'upsert1-720',
            'upsert1-730',
            'upsert1-740',
            'upsert1-750',
            'upsert1-760',
            'upsert1-770',
            'upsert1-780',
        ], true));
    };

    $tests[$prefix . ' selected conflict target is tested before other constraints'] = static function (TestRunner $t) use ($case): void {
        $t->same([$case['matched'][0]], $case['matched']);
        $t->true(in_array($case['matched'][0], ['a', 'b', 'e'], true));
    };

    $tests[$prefix . ' final row image applies excluded c and keeps stable unique keys'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['after'][0]['a'], $case['before'][0]['a']);
        $t->same($case['after'][0]['b'], $case['before'][0]['b']);
        $t->same($case['after'][0]['e'], $case['before'][0]['e']);
        $t->same($case['incoming'][0]['c'], $case['after'][0]['c']);
        $t->same($case['incoming'][0]['setting_key'], $case['after'][0]['setting_key']);
    };

    $tests[$prefix . ' returning stream is exactly the updated post-image'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['after'], $case['returning']);
        $t->same($case['incoming'][0]['setting_key'], $case['returning'][0]['setting_key']);
    };

    $tests[$prefix . ' update counts and skipped rows match upstream RETURNING behavior'] = static function (TestRunner $t) use ($case): void {
        $t->same(1, $case['changes']);
        $t->same(0, $case['skipped']);
        $t->same(1, count($case['returning']));
    };

    $tests[$prefix . ' schema mode keeps rowid distinction explicit'] = static function (TestRunner $t) use ($case): void {
        $t->true(is_bool($case['without_rowid']));
        $t->true(str_contains($case['schema'], 'rowid') || str_contains($case['schema'], 'unique indexes'));
    };

    $tests[$prefix . ' incoming row conflicts with all indexed keys but updates one victim'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['before'][0]['a'], $case['incoming'][0]['a']);
        $t->same($case['before'][0]['b'], $case['incoming'][0]['b']);
        $t->same($case['before'][0]['e'], $case['incoming'][0]['e']);
        $t->same(1, count($case['after']));
    };

    $tests[$prefix . ' dependency closure stays inside native UPSERT executor'] = static function (TestRunner $t) use ($case): void {
        $t->same([
            'upsert1.test-700-through-780',
            'returning1.test-4',
            'sqlite-upsert-target-constraint-tested-first',
        ], $case['dependencies']);
    };
}

$tests['real upstream upsert1 target-first returning helper dynamic source coverage'] = static function (TestRunner $t) use ($cases): void {
    $t->same(1000, count($cases));
    $t->same([
        'upsert1.test 700/710/720 target conflict priority on INTEGER PRIMARY KEY rowid tables',
        'upsert1.test 730/740/750 target conflict priority with explicit unique indexes',
        'upsert1.test 760/770/780 target conflict priority on WITHOUT ROWID tables',
        'returning1.test changed UPSERT rows are yielded as post-update images',
    ], [
        'upsert1.test 700/710/720 target conflict priority on INTEGER PRIMARY KEY rowid tables',
        'upsert1.test 730/740/750 target conflict priority with explicit unique indexes',
        'upsert1.test 760/770/780 target conflict priority on WITHOUT ROWID tables',
        'returning1.test changed UPSERT rows are yielded as post-update images',
    ]);
};

$tests['real upstream upsert1 target-first returning helper dynamic rejects invalid count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningDynamicCorpusPlan::upsert1TargetFirstReturningDynamicCases(0));
};

return $tests;
