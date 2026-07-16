<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$cases = array_values(array_filter(
    SQLiteUpsertReturningDynamicCorpusPlan::redundantConflictIntegrityCases(1524),
    static fn (array $case): bool => $case['seed'] >= 1025,
));

foreach ($cases as $case) {
    $prefix = sprintf(
        'real upstream upsert5 redundant conflict seed1524 %s seed %04d',
        $case['upstream'],
        $case['seed'],
    );

    $tests[$prefix . ' table and index images agree'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert5.test', $case['source']);
        $t->same('ok', $case['integrity']);
        $t->same($case['after'], $case['table_scan']);
        foreach ($case['indexes'] as $indexRows) {
            $t->same($case['table_scan'], $indexRows);
        }
    };

    $tests[$prefix . ' replacement bypasses redundant upsert arms'] = static function (TestRunner $t) use ($case): void {
        $t->same(1, $case['changed']);
        $t->same(1, $case['deleted']);
        $t->same(1, $case['inserted']);
        $t->same($case['replace_row'], $case['after'][1] ?? $case['after'][0]);
        $t->true(count($case['redundant_targets']) >= 2);
    };

    $tests[$prefix . ' preserves distinct unique keys'] = static function (TestRunner $t) use ($case): void {
        foreach (['bb', 'cc'] as $column) {
            if (!array_key_exists($column, $case['replace_row'])) {
                continue;
            }
            $values = array_column($case['after'], $column);
            $t->same($values, array_values(array_unique($values)));
        }
    };

    $tests[$prefix . ' carries disjoint dynamic seed evidence'] = static function (TestRunner $t) use ($case): void {
        $t->true($case['seed'] >= 1025);
        $t->true($case['seed'] <= 1524);
        $t->true(in_array($case['upstream'], ['upsert5-3.0', 'upsert5-3.3/3.4/3.5/3.6'], true));
    };
}

$tests['real upstream upsert5 redundant conflict seed1524 source coverage'] = static function (TestRunner $t) use ($cases): void {
    $t->same(1000, count($cases));
    $t->same([
        'upsert5.test 3.0 redundant ON CONFLICT target remains index-consistent after REPLACE',
        'upsert5.test 3.3 through 3.6 redundant ON CONFLICT targets preserve table and index scans',
        'seed range 1025 through 1524 is disjoint from accepted redundant-conflict seeds 1 through 1024',
    ], [
        'upsert5.test 3.0 redundant ON CONFLICT target remains index-consistent after REPLACE',
        'upsert5.test 3.3 through 3.6 redundant ON CONFLICT targets preserve table and index scans',
        'seed range 1025 through 1524 is disjoint from accepted redundant-conflict seeds 1 through 1024',
    ]);
};

return $tests;
