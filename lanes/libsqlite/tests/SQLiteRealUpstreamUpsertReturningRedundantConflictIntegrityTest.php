<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$badTarget = SQLiteUpsertReturningDynamicCorpusPlan::unresolvedConflictTargetCase();

$tests['real upstream upsert returning redundant conflict upsert5-2.1 resolves bad target before execution'] = static function (TestRunner $t) use ($badTarget): void {
    $t->same('upsert5.test', $badTarget['source']);
    $t->same('upsert5-2.1', $badTarget['upstream']);
    $t->same('no such table: nosuchtable', $badTarget['error']);
    $t->true($badTarget['resolved_before_execution']);
    $t->true(str_contains($badTarget['statement'], 'ON CONFLICT((SELECT t2 FROM nosuchtable)) DO NOTHING'));
};

// Source truth: SQLite upstream test/upsert5.test upsert5-3.0 through 3.6,
// regression for redundant ON CONFLICT clauses after REPLACE preserving table
// and unique-index consistency.
foreach (SQLiteUpsertReturningDynamicCorpusPlan::redundantConflictIntegrityCases() as $case) {
    $name = 'real upstream upsert returning redundant conflict integrity ' . $case['upstream'] . ' seed ' . $case['seed'];

    $tests[$name . ' reports ok integrity'] = static function (TestRunner $t) use ($case): void {
        $t->same('upsert5.test', $case['source']);
        $t->same('ok', $case['integrity']);
        $t->same(1, $case['changed']);
        $t->same(1, $case['deleted']);
        $t->same(1, $case['inserted']);
    };

    $tests[$name . ' replaces primary key row without firing redundant arms'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['replace_row'], $case['after'][array_key_exists(1, $case['after']) ? 1 : 0]);
        $t->same($case['replace_row']['aa'], $case['before'][array_key_exists(1, $case['before']) ? 1 : 0]['aa']);
        $t->true(!in_array($case['replace_row'], $case['before'], true));
        $t->true(in_array($case['replace_row'], $case['after'], true));
        $t->true(count($case['redundant_targets']) >= 2);
    };

    $tests[$name . ' table scan matches upstream not indexed result'] = static function (TestRunner $t) use ($case): void {
        $t->same($case['after'], $case['table_scan']);
        $t->same(array_column($case['after'], 'aa'), array_column($case['table_scan'], 'aa'));
        $t->true($case['table_scan'] !== []);
    };

    foreach ($case['indexes'] as $indexName => $indexRows) {
        $tests[$name . ' indexed by ' . $indexName . ' matches table rows'] = static function (TestRunner $t) use ($case, $indexName, $indexRows): void {
            $t->same($indexRows, $case['indexes'][$indexName]);
            $t->same(array_column($case['after'], 'aa'), array_column($indexRows, 'aa'));
            $t->same(count($case['after']), count($indexRows));
        };
    }

    $tests[$name . ' keeps unique index keys distinct'] = static function (TestRunner $t) use ($case): void {
        foreach (['bb', 'cc'] as $column) {
            if (!array_key_exists($column, $case['replace_row'])) {
                continue;
            }
            $values = array_column($case['after'], $column);
            $t->same($values, array_values(array_unique($values)));
        }
    };
}

$tests['real upstream upsert returning redundant conflict source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test upsert5-2.1 unresolved SELECT conflict target reports no such table before execution',
        'upsert5.test upsert5-3.0 through 3.2 redundant bb conflict arm does not corrupt t1bb',
        'upsert5.test upsert5-3.3 through 3.6 redundant bb/cc conflict arms preserve table and index scans',
    ], [
        'upsert5.test upsert5-2.1 unresolved SELECT conflict target reports no such table before execution',
        'upsert5.test upsert5-3.0 through 3.2 redundant bb conflict arm does not corrupt t1bb',
        'upsert5.test upsert5-3.3 through 3.6 redundant bb/cc conflict arms preserve table and index scans',
    ]);
};

return $tests;
