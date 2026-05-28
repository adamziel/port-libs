<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record134 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records134 = static fn (): array => [
    $record134('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")', 1),
    $record134('index', 'wp_options_name_lc_legacy', 'wp_options', 3, 'CREATE INDEX wp_options_name_lc_legacy ON wp_options(option_name_lc)', 2),
    $record134('index', 'wp_options_name_lc_expr_legacy', 'wp_options', 4, 'CREATE INDEX wp_options_name_lc_expr_legacy ON wp_options(substr(option_name_lc, 1, 8), option_name)', 3),
    $record134('index', 'wp_options_name_lc_partial_legacy', 'wp_options', 5, 'CREATE INDEX wp_options_name_lc_partial_legacy ON wp_options(option_name) WHERE option_name_lc >= "a"', 4),
    $record134('index', 'wp_options_autoload', 'wp_options', 6, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 5),
    $record134('index', 'wp_settings_name_lc', 'wp_settings', 7, 'CREATE INDEX wp_settings_name_lc ON wp_settings(setting_name_lc)', 6),
    $record134('view', 'wp_options_lc_view', 'wp_options_lc_view', 0, 'CREATE VIEW wp_options_lc_view AS SELECT option_name_lc FROM wp_options', 7),
    $record134('view', 'wp_options_star_view', 'wp_options_star_view', 0, 'CREATE VIEW wp_options_star_view AS SELECT * FROM wp_options', 8),
    $record134('table', 'wp_settings', 'wp_settings', 9, 'CREATE TABLE wp_settings(setting_id INTEGER PRIMARY KEY, setting_name TEXT)', 9),
];

$rows134 = [
    ['option_id' => 1, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'widget_recent-posts', 'option_value' => 'a:1:{}', 'autoload' => 'no'],
];

$plan134 = static fn (?array $records = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $records ?? $records134(),
    ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL'],
    134,
    'main',
    [
        ['id' => 'wp-options-lc-legacy-index', 'schema_cookie' => 134, 'sql' => 'SELECT option_name FROM wp_options INDEXED BY wp_options_name_lc_legacy WHERE option_name_lc = ?'],
        ['id' => 'wp-options-lc-current', 'schema_cookie' => 135, 'sql' => 'SELECT option_name_lc FROM wp_options'],
    ],
    ['wp_options' => $rows134],
);

$operation134 = static fn (): array => $plan134()['operations'][0];

$tests = [
    'schema generated column index reparse current source next134 reports ok' => static fn (TestRunner $t) => $t->same('ok', $plan134()['status']),
    'schema generated column index reparse current source next134 before cookie' => static fn (TestRunner $t) => $t->same(134, $plan134()['before_schema_cookie']),
    'schema generated column index reparse current source next134 after cookie' => static fn (TestRunner $t) => $t->same(135, $plan134()['after_schema_cookie']),
    'schema generated column index reparse current source next134 changed' => static fn (TestRunner $t) => $t->same(true, $plan134()['schema_changed']),
    'schema generated column index reparse current source next134 operation kind' => static fn (TestRunner $t) => $t->same('alter_table_add_column', $operation134()['kind']),
    'schema generated column index reparse current source next134 table' => static fn (TestRunner $t) => $t->same('wp_options', $operation134()['table']),
    'schema generated column index reparse current source next134 column' => static fn (TestRunner $t) => $t->same('option_name_lc', $operation134()['column']),
    'schema generated column index reparse current source next134 generated flag' => static fn (TestRunner $t) => $t->same(true, $operation134()['generated']),
    'schema generated column index reparse current source next134 checked rows' => static fn (TestRunner $t) => $t->same(3, $operation134()['checked_rows']),
    'schema generated column index reparse current source next134 column count' => static fn (TestRunner $t) => $t->same(5, $operation134()['column_count']),
    'schema generated column index reparse current source next134 dependent count' => static fn (TestRunner $t) => $t->same(6, $operation134()['dependent_reparse_count']),
    'schema generated column index reparse current source next134 dependent generated index' => static fn (TestRunner $t) => $t->same(true, in_array('index:wp_options_name_lc_legacy', $operation134()['dependent_reparse_records'], true)),
    'schema generated column index reparse current source next134 dependent expression index' => static fn (TestRunner $t) => $t->same(true, in_array('index:wp_options_name_lc_expr_legacy', $operation134()['dependent_reparse_records'], true)),
    'schema generated column index reparse current source next134 dependent partial index' => static fn (TestRunner $t) => $t->same(true, in_array('index:wp_options_name_lc_partial_legacy', $operation134()['dependent_reparse_records'], true)),
    'schema generated column index reparse current source next134 dependent ordinary index on table' => static fn (TestRunner $t) => $t->same(true, in_array('index:wp_options_autoload', $operation134()['dependent_reparse_records'], true)),
    'schema generated column index reparse current source next134 skips other table index' => static fn (TestRunner $t) => $t->same(false, in_array('index:wp_settings_name_lc', $operation134()['dependent_reparse_records'], true)),
    'schema generated column index reparse current source next134 index reparse records' => static fn (TestRunner $t) => $t->same([
        'index:wp_options_name_lc_legacy',
        'index:wp_options_name_lc_expr_legacy',
        'index:wp_options_name_lc_partial_legacy',
        'index:wp_options_autoload',
    ], $operation134()['index_reparse_records']),
    'schema generated column index reparse current source next134 generated indexes' => static fn (TestRunner $t) => $t->same([
        'index:wp_options_name_lc_legacy',
        'index:wp_options_name_lc_expr_legacy',
    ], $operation134()['generated_column_index_records']),
    'schema generated column index reparse current source next134 expression indexes' => static fn (TestRunner $t) => $t->same(['index:wp_options_name_lc_expr_legacy'], $operation134()['expression_index_reparse_records']),
    'schema generated column index reparse current source next134 partial indexes' => static fn (TestRunner $t) => $t->same(['index:wp_options_name_lc_partial_legacy'], $operation134()['partial_index_reparse_records']),
    'schema generated column index reparse current source next134 generated reference plain' => static fn (TestRunner $t) => $t->same(['option_name_lc'], $operation134()['index_generated_column_references']['wp_options_name_lc_legacy']),
    'schema generated column index reparse current source next134 generated reference expression' => static fn (TestRunner $t) => $t->same(['option_name_lc'], $operation134()['index_generated_column_references']['wp_options_name_lc_expr_legacy']),
    'schema generated column index reparse current source next134 generated reference omits ordinary index' => static fn (TestRunner $t) => $t->same(false, array_key_exists('wp_options_autoload', $operation134()['index_generated_column_references'])),
    'schema generated column index reparse current source next134 generated reference omits partial predicate only' => static fn (TestRunner $t) => $t->same(false, array_key_exists('wp_options_name_lc_partial_legacy', $operation134()['index_generated_column_references'])),
    'schema generated column index reparse current source next134 generated view records' => static fn (TestRunner $t) => $t->same(['view:wp_options_lc_view'], $operation134()['generated_column_view_records']),
    'schema generated column index reparse current source next134 star view records' => static fn (TestRunner $t) => $t->same(['view:wp_options_star_view'], $operation134()['star_expansion_records']),
    'schema generated column index reparse current source next134 invalidates stale prepared only' => static fn (TestRunner $t) => $t->same(['wp-options-lc-legacy-index'], $plan134()['invalidated_prepared']),
    'schema generated column index reparse current source next134 table count stable' => static fn (TestRunner $t) => $t->same(2, $plan134()['table_count']),
    'schema generated column index reparse current source next134 index count stable' => static fn (TestRunner $t) => $t->same(5, $plan134()['index_count']),
    'schema generated column index reparse current source next134 pragma sample exists' => static fn (TestRunner $t) => $t->same('table_xinfo', $plan134()['pragma_samples']['table_xinfo:wp_options']['pragma']),
    'schema generated column index reparse current source next134 xinfo includes generated' => static fn (TestRunner $t) => $t->same('option_name_lc', $plan134()['pragma_samples']['table_xinfo:wp_options']['rows'][4]['name']),
    'schema generated column index reparse current source next134 xinfo hidden flag' => static fn (TestRunner $t) => $t->same(2, $plan134()['pragma_samples']['table_xinfo:wp_options']['rows'][4]['hidden']),
    'schema generated column index reparse current source next134 table info omits generated' => static function (TestRunner $t) use ($plan134): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan134()['records']);
        $t->same(['option_id', 'option_name', 'option_value', 'autoload'], array_column($catalog->execute('PRAGMA table_info(wp_options)')['rows'], 'name'));
    },
    'schema generated column index reparse current source next134 table xinfo includes all columns' => static function (TestRunner $t) use ($plan134): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan134()['records']);
        $t->same(['option_id', 'option_name', 'option_value', 'autoload', 'option_name_lc'], array_column($catalog->execute('PRAGMA table_xinfo(wp_options)')['rows'], 'name'));
    },
    'schema generated column index reparse current source next134 dependencies stable' => static fn (TestRunner $t) => $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan134()['dependencies']),
];

$variants134 = [
    'quoted generated index reference' => [
        [
            $record134('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
            $record134('index', 'wp_options_slug', 'wp_options', 3, 'CREATE INDEX wp_options_slug ON wp_options("option slug")', 2),
        ],
        'ALTER TABLE wp_options ADD COLUMN "option slug" TEXT AS (lower(option_name)) VIRTUAL',
        'generated_column_index_records',
        ['index:wp_options_slug'],
    ],
    'collated generated term remains simple index' => [
        [
            $record134('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
            $record134('index', 'wp_options_slug', 'wp_options', 3, 'CREATE INDEX wp_options_slug ON wp_options(option_name_lc COLLATE nocase DESC)', 2),
        ],
        'ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL',
        'expression_index_reparse_records',
        [],
    ],
    'expression generated term is classified' => [
        [
            $record134('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
            $record134('index', 'wp_options_slug_expr', 'wp_options', 3, 'CREATE INDEX wp_options_slug_expr ON wp_options(lower(option_name_lc))', 2),
        ],
        'ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL',
        'expression_index_reparse_records',
        ['index:wp_options_slug_expr'],
    ],
    'partial generated predicate without term remains partial only' => [
        [
            $record134('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
            $record134('index', 'wp_options_partial', 'wp_options', 3, 'CREATE INDEX wp_options_partial ON wp_options(option_name) WHERE option_name_lc IS NOT NULL', 2),
        ],
        'ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL',
        'generated_column_index_records',
        [],
    ],
    'other table generated index is ignored' => [
        [
            $record134('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
            $record134('table', 'wp_settings', 'wp_settings', 3, 'CREATE TABLE wp_settings(setting_name TEXT)', 2),
            $record134('index', 'wp_settings_lc', 'wp_settings', 4, 'CREATE INDEX wp_settings_lc ON wp_settings(option_name_lc)', 3),
        ],
        'ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL',
        'index_reparse_records',
        [],
    ],
];

foreach ($variants134 as $name => [$records, $ddl, $key, $expected]) {
    $tests['schema generated column index reparse current source next134 ' . $name] = static function (TestRunner $t) use ($records, $ddl, $key, $expected): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($records, [$ddl], 134, 'main');
        $t->same($expected, $plan['operations'][0][$key]);
    };
}

return $tests;
