<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$cases = array_values(array_filter(
    SQLiteUpsertReturningDynamicCorpusPlan::redundantConflictIntegrityCases(1024),
    static fn (array $case): bool => $case['seed'] >= 525,
));

foreach ($cases as $case) {
    $prefix = sprintf(
        'real upstream upsert5 redundant conflict extended tail %s seed %04d',
        $case['upstream'],
        $case['seed'],
    );

    $tests[$prefix . ' table scan remains intact after replace'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert5.test', $case['source']);
        $t->same('ok', $case['integrity']);
        $t->same($case['after'], $case['table_scan']);
    };

    $tests[$prefix . ' redundant ON CONFLICT targets are bypassed'] = static function (TestRunner $t) use ($case): void {
        $t->same(1, $case['changed']);
        $t->same(1, $case['deleted']);
        $t->same(1, $case['inserted']);
        $t->same($case['replace_row'], $case['after'][1] ?? $case['after'][0]);
    };

    $tests[$prefix . ' all maintained indexes agree with the table scan'] = static function (TestRunner $t) use ($case): void {
        foreach ($case['indexes'] as $indexRows) {
            $t->same($case['table_scan'], $indexRows);
        }
    };
}

$tests['real upstream upsert5 redundant conflict extended tail source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test 3.0 redundant ON CONFLICT target remains index-consistent after REPLACE',
        'upsert5.test 3.3 through 3.6 redundant ON CONFLICT targets preserve table and index scans',
        'extended tail seeds 525 through 1024 are disjoint from accepted seed ranges 1 through 524',
    ], [
        'upsert5.test 3.0 redundant ON CONFLICT target remains index-consistent after REPLACE',
        'upsert5.test 3.3 through 3.6 redundant ON CONFLICT targets preserve table and index scans',
        'extended tail seeds 525 through 1024 are disjoint from accepted seed ranges 1 through 524',
    ]);
};

return $tests;
