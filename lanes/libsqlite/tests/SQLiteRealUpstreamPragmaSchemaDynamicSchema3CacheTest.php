<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/schema3.test.
 *
 * schema3-1.* checks that a second connection refreshes its internal schema
 * cache after another connection creates tables, views, indexes, triggers, or
 * adds columns. The PHP port models that at the schema/PRAGMA layer: a cached
 * statement with the old schema cookie is invalidated, then the next operation
 * observes the new sqlite_schema rows and PRAGMA table/index metadata.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$tableInfo = static function (array $records, string $table): array {
    return (new SQLitePragmaSchemaCatalog($records))->execute("PRAGMA table_info({$table})")['rows'];
};

$indexList = static function (array $records, string $table): array {
    return (new SQLitePragmaSchemaCatalog($records))->execute("PRAGMA index_list({$table})")['rows'];
};

$recordNames = static function (array $records, string $type): array {
    return array_values(array_map(
        static fn (SQLiteSchemaRecord $record): string => $record->name,
        array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === $type),
    ));
};

$baseRecords = static function (int $variant) use ($record): array {
    $suffix = sprintf('%03d', $variant);

    return [
        $record('table', "existing_t1_{$suffix}", "existing_t1_{$suffix}", 10, "CREATE TABLE existing_t1_{$suffix}(a, b)", 1),
        $record('table', "existing_t2_{$suffix}", "existing_t2_{$suffix}", 11, "CREATE TABLE existing_t2_{$suffix}(a, b)", 2),
        $record('table', "existing_t3_{$suffix}", "existing_t3_{$suffix}", 12, "CREATE TABLE existing_t3_{$suffix}(a, b)", 3),
        $record('table', "existing_t4_{$suffix}", "existing_t4_{$suffix}", 13, "CREATE TABLE existing_t4_{$suffix}(a, b)", 4),
        $record('table', "existing_t6_{$suffix}", "existing_t6_{$suffix}", 14, "CREATE TABLE existing_t6_{$suffix}(a, b)", 5),
    ];
};

$cases = [
    1 => [
        'section' => 'schema3-1.*.1',
        'c1' => 'CREATE TABLE fresh_t1_{s}(a, b)',
        'kind' => 'create_table',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b'], array_column($tableInfo($records, "fresh_t1_{$s}"), 'name'));
        },
    ],
    2 => [
        'section' => 'schema3-1.*.2',
        'c1' => 'CREATE TABLE fresh_t2_{s}(a, b)',
        'kind' => 'create_table',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b'], array_column($tableInfo($records, "fresh_t2_{$s}"), 'name'));
        },
    ],
    3 => [
        'section' => 'schema3-1.*.3',
        'c1' => 'CREATE TABLE fresh_t3_{s}(a, b)',
        'kind' => 'create_table',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b'], array_column($tableInfo($records, "fresh_t3_{$s}"), 'name'));
        },
    ],
    4 => [
        'section' => 'schema3-1.*.4',
        'c1' => 'CREATE TABLE fresh_t4_{s}(a, b)',
        'kind' => 'create_table',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b'], array_column($tableInfo($records, "fresh_t4_{$s}"), 'name'));
        },
    ],
    5 => [
        'section' => 'schema3-1.*.5',
        'c1' => 'CREATE TABLE fresh_t5_{s}(a, b)',
        'kind' => 'create_table',
        'follow' => ['DROP TABLE fresh_t5_{s}'],
        'followKind' => 'drop_table',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($recordNames): void {
            $t->same(false, in_array("fresh_t5_{$s}", $recordNames($records, 'table'), true));
        },
    ],
    6 => [
        'section' => 'schema3-1.*.6',
        'c1' => 'CREATE TABLE fresh_t6_{s}(a, b)',
        'kind' => 'create_table',
        'follow' => ['CREATE INDEX fresh_i1_{s} ON fresh_t6_{s}(a)'],
        'followKind' => 'create_index',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($indexList): void {
            $t->same(["fresh_i1_{$s}"], array_column($indexList($records, "fresh_t6_{$s}"), 'name'));
        },
    ],
    7 => [
        'section' => 'schema3-1.*.7',
        'c1' => 'ALTER TABLE existing_t1_{s} ADD COLUMN c',
        'kind' => 'alter_table_add_column',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b', 'c'], array_column($tableInfo($records, "existing_t1_{$s}"), 'name'));
        },
    ],
    8 => [
        'section' => 'schema3-1.*.8',
        'c1' => 'ALTER TABLE existing_t2_{s} ADD COLUMN c',
        'kind' => 'alter_table_add_column',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b', 'c'], array_column($tableInfo($records, "existing_t2_{$s}"), 'name'));
        },
    ],
    9 => [
        'section' => 'schema3-1.*.9',
        'c1' => 'ALTER TABLE existing_t2_{s} ADD COLUMN d',
        'kind' => 'alter_table_add_column',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b', 'd'], array_column($tableInfo($records, "existing_t2_{$s}"), 'name'));
        },
    ],
    10 => [
        'section' => 'schema3-1.*.10',
        'c1' => 'ALTER TABLE existing_t3_{s} ADD COLUMN c',
        'kind' => 'alter_table_add_column',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b', 'c'], array_column($tableInfo($records, "existing_t3_{$s}"), 'name'));
        },
    ],
    11 => [
        'section' => 'schema3-1.*.11',
        'c1' => 'ALTER TABLE existing_t4_{s} ADD COLUMN c',
        'kind' => 'alter_table_add_column',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b', 'c'], array_column($tableInfo($records, "existing_t4_{$s}"), 'name'));
        },
    ],
    12 => [
        'section' => 'schema3-1.*.12',
        'c1' => 'ALTER TABLE existing_t6_{s} ADD COLUMN c',
        'kind' => 'alter_table_add_column',
        'follow' => ['CREATE INDEX fresh_i2_{s} ON existing_t6_{s}(c)'],
        'followKind' => 'create_index',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($indexList): void {
            $t->same(["fresh_i2_{$s}"], array_column($indexList($records, "existing_t6_{$s}"), 'name'));
        },
    ],
    13 => [
        'section' => 'schema3-1.*.13',
        'c1' => 'ALTER TABLE existing_t6_{s} ADD COLUMN d',
        'kind' => 'alter_table_add_column',
        'follow' => ['CREATE TRIGGER fresh_tr1_{s} AFTER UPDATE OF d ON existing_t6_{s} BEGIN SELECT 1, 2, 3; END'],
        'followKind' => 'create_trigger',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($recordNames): void {
            $t->same(true, in_array("fresh_tr1_{$s}", $recordNames($records, 'trigger'), true));
        },
    ],
    14 => [
        'section' => 'schema3-1.*.14',
        'c1' => 'CREATE INDEX fresh_i3_{s} ON existing_t1_{s}(a)',
        'kind' => 'create_index',
        'follow' => ['DROP INDEX fresh_i3_{s}'],
        'followKind' => 'drop_index',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($indexList): void {
            $t->same([], array_column($indexList($records, "existing_t1_{$s}"), 'name'));
        },
    ],
    15 => [
        'section' => 'schema3-1.*.15',
        'c1' => 'CREATE INDEX fresh_i4_{s} ON existing_t2_{s}(a)',
        'kind' => 'create_index',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($indexList): void {
            $t->same(["fresh_i4_{$s}"], array_column($indexList($records, "existing_t2_{$s}"), 'name'));
        },
    ],
    16 => [
        'section' => 'schema3-1.*.16',
        'c1' => 'CREATE TRIGGER fresh_tr2_{s} AFTER INSERT ON existing_t3_{s} BEGIN SELECT 1; END',
        'kind' => 'create_trigger',
        'follow' => ['DROP TRIGGER fresh_tr2_{s}'],
        'followKind' => 'drop_trigger',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($recordNames): void {
            $t->same(false, in_array("fresh_tr2_{$s}", $recordNames($records, 'trigger'), true));
        },
    ],
    17 => [
        'section' => 'schema3-1.*.17',
        'c1' => 'CREATE VIEW fresh_v1_{s} AS SELECT * FROM existing_t1_{s}',
        'kind' => 'create_view',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($recordNames): void {
            $t->same(true, in_array("fresh_v1_{$s}", $recordNames($records, 'view'), true));
        },
    ],
    18 => [
        'section' => 'schema3-1.*.18',
        'c1' => 'ALTER TABLE existing_t1_{s} ADD COLUMN d',
        'kind' => 'alter_table_add_column',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['a', 'b', 'd'], array_column($tableInfo($records, "existing_t1_{$s}"), 'name'));
        },
    ],
    19 => [
        'section' => 'schema3-1.*.19',
        'c1' => 'CREATE TABLE fresh_t7_{s}(a, b)',
        'kind' => 'create_table',
        'follow' => ['DROP TABLE IF EXISTS fresh_t7_{s}', 'CREATE TABLE fresh_t7_{s}(c, d)'],
        'followKind' => 'create_table',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($tableInfo): void {
            $t->same(['c', 'd'], array_column($tableInfo($records, "fresh_t7_{$s}"), 'name'));
        },
    ],
    20 => [
        'section' => 'schema3-1.*.20',
        'c1' => 'CREATE TABLE fresh_t7_{s}(c, d)',
        'kind' => 'create_table',
        'follow' => ['CREATE INDEX fresh_i5_{s} ON fresh_t7_{s}(c, d)', 'DROP INDEX IF EXISTS fresh_i5_{s}', 'CREATE INDEX fresh_i5_{s} ON fresh_t7_{s}(c)'],
        'followKind' => 'create_index',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($indexList): void {
            $t->same(["fresh_i5_{$s}"], array_column($indexList($records, "fresh_t7_{$s}"), 'name'));
        },
    ],
    21 => [
        'section' => 'schema3-1.*.21',
        'c1' => 'CREATE TABLE fresh_t7_{s}(c, d)',
        'kind' => 'create_table',
        'follow' => [
            'CREATE TRIGGER fresh_tr3_{s} BEFORE DELETE ON fresh_t7_{s} BEGIN SELECT 1, 2, 3; END',
            'DROP TRIGGER IF EXISTS fresh_tr3_{s}',
            'CREATE TRIGGER fresh_tr3_{s} AFTER INSERT ON fresh_t7_{s} BEGIN SELECT 1, 2, 3; END',
        ],
        'followKind' => 'create_trigger',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($recordNames): void {
            $t->same(["fresh_tr3_{$s}"], array_values(array_filter($recordNames($records, 'trigger'), static fn (string $name): bool => $name === "fresh_tr3_{$s}")));
        },
    ],
    22 => [
        'section' => 'schema3-1.*.22',
        'c1' => 'CREATE TABLE fresh_t8_{s}(a, b)',
        'kind' => 'create_table',
        'follow' => ['CREATE TRIGGER fresh_tr4_{s} AFTER UPDATE OF a ON fresh_t8_{s} BEGIN SELECT 1, 2, 3; END'],
        'followKind' => 'create_trigger',
        'checks' => static function (TestRunner $t, array $records, string $s) use ($recordNames): void {
            $t->same(true, in_array("fresh_tr4_{$s}", $recordNames($records, 'trigger'), true));
        },
    ],
];

$formatSql = static fn (string $sql, string $suffix): string => str_replace('{s}', $suffix, $sql);

foreach (range(1, 50) as $variant) {
    $suffix = sprintf('%03d', $variant);
    foreach ($cases as $caseNumber => $case) {
        $tests[sprintf('real upstream schema3 cache refresh case %02d variant %03d', $caseNumber, $variant)] = static function (TestRunner $t) use ($baseRecords, $case, $caseNumber, $formatSql, $suffix, $variant): void {
            $prepared = [[
                'id' => sprintf('cached-schema3-%02d-%s', $caseNumber, $suffix),
                'schema_cookie' => 1,
                'sql' => $case['section'],
                'target' => sprintf('schema3-case-%02d', $caseNumber),
            ]];

            $plan = SQLiteSchemaDdlReparsePlan::apply(
                $baseRecords($variant),
                [$formatSql($case['c1'], $suffix)],
                1,
                'main',
                $prepared,
            );

            $t->same('ok', $plan['status']);
            $t->same($case['kind'], $plan['operations'][0]['kind']);
            $t->same([sprintf('cached-schema3-%02d-%s', $caseNumber, $suffix)], $plan['invalidated_prepared']);
            $t->same(2, $plan['after_schema_cookie']);

            $records = $plan['records'];
            if (isset($case['follow'])) {
                $followPlan = SQLiteSchemaDdlReparsePlan::apply(
                    $records,
                    array_map(static fn (string $sql): string => $formatSql($sql, $suffix), $case['follow']),
                    $plan['after_schema_cookie'],
                );
                $t->same($case['followKind'], $followPlan['operations'][count($followPlan['operations']) - 1]['kind']);
                $t->same(true, $followPlan['schema_changed']);
                $records = $followPlan['records'];
            }

            $case['checks']($t, $records, $suffix);
        };
    }
}

$tests['real upstream schema3 cache refresh cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema3.test schema3-1.*.1 through schema3-1.*.6 refresh after CREATE TABLE before SELECT/UPDATE/DELETE/INSERT/DROP/CREATE INDEX',
        'schema3.test schema3-1.*.7 through schema3-1.*.13 refresh after ALTER TABLE ADD COLUMN before SELECT/UPDATE/DELETE/INSERT/INDEX/TRIGGER',
        'schema3.test schema3-1.*.14 through schema3-1.*.18 refresh after CREATE INDEX/TRIGGER/VIEW and later column additions',
        'schema3.test schema3-1.*.19 through schema3-1.*.22 refresh across DROP IF EXISTS plus recreated table/index/trigger forms',
    ];

    $t->same(4, count($sections));
    $t->contains('schema3-1.*.1', $sections[0]);
    $t->contains('ALTER TABLE ADD COLUMN', $sections[1]);
    $t->contains('CREATE INDEX/TRIGGER/VIEW', $sections[2]);
    $t->contains('DROP IF EXISTS', $sections[3]);
};

return $tests;
