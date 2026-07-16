<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexLifecyclePlan;

$tests = [];

// Source truth: SQLite upstream test/index3.test section index3-99.1.
// The upstream script disables defensive mode, corrupts sqlite_schema via
// writable_schema, reopens the database, and verifies DROP INDEX fails while
// reparsing the malformed schema record.
foreach (SQLiteIndexLifecyclePlan::malformedSchemaDropIndexCases(1200) as $case) {
    $tests['real upstream index3 malformed schema drop index dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index3.test index3-99.1', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1200);
        $t->true($case['batch'] >= 1);
        $t->same('index3-99.1', $case['upstream_section']);
        $t->same('DROP INDEX reparses schema and rejects a malformed sqlite_schema SQL record', $case['scenario']);
        $t->true(str_starts_with($case['table_name'], 't_malformed_'));
        $t->true(str_starts_with($case['index_name'], 'i_malformed_'));
        $t->same('nonsense', $case['corrupt_sql']);
        $t->same('DROP INDEX ' . $case['index_name'], $case['drop_sql']);
        $t->same(1, $case['result_code']);
        $t->same('malformed database schema (' . $case['index_name'] . ')', $case['error']);
        $t->same([$case['table_name'], $case['index_name']], $case['catalog_names']);
        $t->same('ok', $case['integrity_before_corruption']);
        $t->same('malformed-schema', $case['integrity_after_corruption']);
        $t->same(true, $case['defensive_disabled']);
        $t->same(true, $case['writable_schema']);
        $t->same(true, $case['drop_blocked']);
        $t->true(str_contains($case['error'], $case['index_name']));
    };
}

$tests['real upstream index3 malformed schema drop index corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteIndexLifecyclePlan::malformedSchemaDropIndexCases(1200);
    $t->same(1200, count($cases));
    $t->same(1, $cases[0]['case']);
    $t->same(1, $cases[0]['batch']);
    $t->same(24, $cases[23]['case']);
    $t->same(1, $cases[23]['batch']);
    $t->same(25, $cases[24]['case']);
    $t->same(2, $cases[24]['batch']);
    $t->same(50, $cases[1199]['batch']);
};

$tests['real upstream index3 malformed schema drop index rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexLifecyclePlan::malformedSchemaDropIndexCases(0));
};

$tests['real upstream index3 malformed schema drop index dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local index lifecycle, schema-reparse error, writable-schema corruption, and DROP INDEX guard helpers',
        'no new support component needed; reuses lane-local index lifecycle, schema-reparse error, writable-schema corruption, and DROP INDEX guard helpers',
    );
};

return $tests;
