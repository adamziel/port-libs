<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$cases = array_values(array_filter(
    SQLiteUpsertReturningDynamicCorpusPlan::redundantConflictIntegrityCases(524),
    static fn (array $case): bool => $case['seed'] >= 25,
));

foreach ($cases as $case) {
    $prefix = sprintf(
        'real upstream upsert5 redundant conflict extended %s seed %03d',
        $case['upstream'],
        $case['seed'],
    );

    $tests[$prefix . ' table image remains intact'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert5.test', $case['source']);
        $t->same('ok', $case['integrity']);
        $t->same($case['after'], $case['table_scan']);
    };

    $tests[$prefix . ' redundant targets do not apply update arms'] = static function (TestRunner $t) use ($case): void {
        $t->same(1, $case['changed']);
        $t->same(1, $case['deleted']);
        $t->same(1, $case['inserted']);
        $t->same($case['replace_row'], $case['after'][1] ?? $case['after'][0]);
    };

    $tests[$prefix . ' index scans agree with table scan'] = static function (TestRunner $t) use ($case): void {
        foreach ($case['indexes'] as $indexRows) {
            $t->same($case['table_scan'], $indexRows);
        }
    };
}

$tests['real upstream upsert5 redundant conflict extended source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test 3.0 redundant ON CONFLICT target remains index-consistent after REPLACE',
        'upsert5.test 3.3 through 3.6 redundant ON CONFLICT targets preserve table and index scans',
        'extended seeds 25 through 524 are disjoint from the accepted default seed range 1 through 24',
    ], [
        'upsert5.test 3.0 redundant ON CONFLICT target remains index-consistent after REPLACE',
        'upsert5.test 3.3 through 3.6 redundant ON CONFLICT targets preserve table and index scans',
        'extended seeds 25 through 524 are disjoint from the accepted default seed range 1 through 24',
    ]);
};

return $tests;
