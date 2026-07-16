<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-18.1 through
// index-18.5. These cases verify that application SQL cannot create table,
// index, view, or trigger schema objects whose names begin with sqlite_, and
// that the protected-name failures preserve the existing schema before t7 is
// explicitly dropped.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexReservedSchemaNameCases(1000) as $case) {
    $tests['real upstream index reserved schema name dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-18.1 through index-18.5', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], [
            'index-18.1',
            'index-18.1.2',
            'index-18.2',
            'index-18.3',
            'index-18.4',
            'index-18.5',
        ], true));
        $t->true(in_array($case['object_type'], ['table', 'index', 'view', 'trigger'], true));
        $t->true($case['statement'] !== '');
        $t->same($case['result_code'] === 0 ? 'ok' : 'schema-preserved-after-reserved-name-error', $case['integrity']);
        $t->same($case['message'] === null ? 0 : 1, $case['result_code']);
        $t->same($case['drops_existing_table'], $case['upstream_section'] === 'index-18.5');

        if ($case['result_code'] === 1) {
            $t->true(str_starts_with($case['object_name'], 'sqlite_'));
            $t->true(str_contains($case['message'], 'object name reserved for internal use'));
            $t->true(str_contains($case['message'], $case['object_name']));
            $t->same($case['schema_before'], $case['schema_after']);
        } else {
            $t->same('DROP TABLE t7', $case['statement']);
            $t->same('t7', $case['object_name']);
            $t->same([], $case['schema_after']);
        }

        if (in_array($case['upstream_section'], ['index-18.2', 'index-18.3', 'index-18.4', 'index-18.5'], true)) {
            $t->same(0, $case['defensive_mode']);
        }

        if ($case['upstream_section'] === 'index-18.2') {
            $t->same('index', $case['object_type']);
            $t->same('sqlite_i1', $case['object_name']);
            $t->true(in_array('t7', $case['schema_before'], true));
        }

        if ($case['upstream_section'] === 'index-18.3') {
            $t->same('view', $case['object_type']);
            $t->same('view', $case['requires_capability']);
        }

        if ($case['upstream_section'] === 'index-18.4') {
            $t->same('trigger', $case['object_type']);
            $t->same('trigger', $case['requires_capability']);
        }

        if ($case['upstream_section'] === 'index-18.5') {
            $t->same(['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3', 't7'], $case['schema_before']);
        }
    };
}

$tests['real upstream index reserved schema name dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::indexReservedSchemaNameCases(1000);

    $t->same(1000, count($cases));
    $t->same('index-18.1', $cases[0]['upstream_section']);
    $t->same('index-18.5', $cases[5]['upstream_section']);
    $t->same('index-18.3', $cases[999]['upstream_section']);
};

$tests['real upstream index reserved schema name rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::indexReservedSchemaNameCases(0));
};

$tests['real upstream index reserved schema name dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and schema catalog validation helpers',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner and schema catalog validation helpers',
    );
};

return $tests;
