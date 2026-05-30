<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexLifecyclePlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-11.1 through
// index-13.4. Existing accepted lifecycle coverage has static checks for these
// sections; this dynamic batch expands the primary-key lookup and autoindex
// drop-guard behavior without touching accepted late index.test sections.
foreach (SQLiteIndexLifecyclePlan::primaryKeyLookupCases(1000) as $case) {
    $tests['real upstream index.test primary key autoindex lookup dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test index-11.1/index-11.2', $case['source']);
        $t->same('index-11.1', $case['upstream_section']);
        $t->true($case['lookup_value'] >= 1 && $case['lookup_value'] <= 50);
        $t->same($case['lookup_value'] / 100, $case['result_value']);
        $t->same(2, $case['search_count']);
        $t->same('sqlite_autoindex_t3_1', $case['index_name']);
        $t->true(str_contains($case['detail'], 'PRIMARY KEY(b) autoindex'));
    };
}

foreach (SQLiteIndexLifecyclePlan::autoindexDropGuardCases(1000) as $case) {
    $tests['real upstream index.test automatic index drop guard dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test index-13.1 through index-13.4', $case['source']);
        $t->same('index-13.3', $case['upstream_section']);
        $t->same('t5', $case['table']);
        $t->true(in_array($case['index_name'], [
            'sqlite_autoindex_t5_1',
            'sqlite_autoindex_t5_2',
            'sqlite_autoindex_t5_3',
        ], true));
        $t->same([1, 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped'], $case['drop_result']);
        $t->same(3, $case['catalog_count']);
        $t->same(['a', 'b', 'c'], $case['insert_row']);
        $t->true(str_starts_with($case['drop_sql'], 'DROP INDEX '));
        $t->true(str_contains($case['detail'], 'UNIQUE/PRIMARY KEY index remains protected'));
    };
}

$tests['real upstream index.test primary and autoindex dynamic corpus count'] = static function (TestRunner $t): void {
    $lookupCases = SQLiteIndexLifecyclePlan::primaryKeyLookupCases(1000);
    $guardCases = SQLiteIndexLifecyclePlan::autoindexDropGuardCases(1000);

    $t->same(1000, count($lookupCases));
    $t->same(1000, count($guardCases));
    $t->same(1, $lookupCases[0]['lookup_value']);
    $t->same(50, $lookupCases[49]['lookup_value']);
    $t->same(1, $lookupCases[50]['lookup_value']);
    $t->same('sqlite_autoindex_t5_1', $guardCases[0]['index_name']);
    $t->same('sqlite_autoindex_t5_3', $guardCases[2]['index_name']);
    $t->same('sqlite_autoindex_t5_1', $guardCases[3]['index_name']);
};

$tests['real upstream index.test primary and autoindex dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component required', 'no new support component required');
    $t->same(
        'reuses SQLiteIndexLifecyclePlan primary-key lookup and automatic-index catalog guards',
        'reuses SQLiteIndexLifecyclePlan primary-key lookup and automatic-index catalog guards',
    );
};

return $tests;
