<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record132 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records132 = static fn (): array => [
    $record132('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_name_lc TEXT AS (lower(option_name)) VIRTUAL, option_value_len INTEGER AS (length(option_value)) VIRTUAL)', 1),
    $record132('index', 'wp_options_generated_lookup', 'wp_options', 3, 'CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc, option_value_len) WHERE option_name_lc >= "a"', 2),
    $record132('index', 'wp_options_autoload', 'wp_options', 4, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 3),
    $record132('table', 'wp_postmeta', 'wp_postmeta', 5, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT)', 4),
];

$ddl132 = [
    'CREATE VIEW wp_options_generated_export AS SELECT option_id, option_name_lc, option_value_len FROM wp_options INDEXED BY wp_options_generated_lookup WHERE option_name_lc >= "a"',
    'CREATE VIEW wp_options_generated_star AS SELECT * FROM wp_options INDEXED BY wp_options_generated_lookup WHERE autoload = "yes"',
    'CREATE VIEW wp_options_plain_export AS SELECT option_id, option_name FROM wp_options INDEXED BY wp_options_autoload WHERE autoload = "yes"',
];

$plan132 = static fn (?array $ddl = null, ?array $records = null, ?array $prepared = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $records ?? $records132(),
    $ddl ?? $ddl132,
    132,
    'main',
    $prepared ?? [
        ['id' => 'stale-generated-view-reader', 'schema_cookie' => 132, 'sql' => 'SELECT * FROM wp_options_generated_export'],
        ['id' => 'first-current-view-reader', 'schema_cookie' => 133, 'sql' => 'SELECT * FROM wp_options_generated_star'],
        ['id' => 'fresh-view-reader', 'schema_cookie' => 135, 'sql' => 'SELECT * FROM wp_options_plain_export'],
    ],
);

$operation132 = static fn (array $plan, int $index): array => $plan['operations'][$index];

$recordSql132 = static function (array $records, string $name): ?string {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && strcasecmp($record->name, $name) === 0) {
            return $record->sql;
        }
    }

    return null;
};

$tests = [
    'schema generated index view reparse current source next132 reports ok' => static fn (TestRunner $t) => $t->same('ok', $plan132()['status']),
    'schema generated index view reparse current source next132 before cookie' => static fn (TestRunner $t) => $t->same(132, $plan132()['before_schema_cookie']),
    'schema generated index view reparse current source next132 after cookie advances per view' => static fn (TestRunner $t) => $t->same(135, $plan132()['after_schema_cookie']),
    'schema generated index view reparse current source next132 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan132()['schema_changed']),
    'schema generated index view reparse current source next132 operation count' => static fn (TestRunner $t) => $t->same(3, count($plan132()['operations'])),
    'schema generated index view reparse current source next132 table count stable' => static fn (TestRunner $t) => $t->same(2, $plan132()['table_count']),
    'schema generated index view reparse current source next132 index count stable' => static fn (TestRunner $t) => $t->same(2, $plan132()['index_count']),
    'schema generated index view reparse current source next132 dependency list' => static fn (TestRunner $t) => $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan132()['dependencies']),
    'schema generated index view reparse current source next132 invalidates stale only' => static fn (TestRunner $t) => $t->same(['stale-generated-view-reader', 'first-current-view-reader'], $plan132()['invalidated_prepared']),
    'schema generated index view reparse current source next132 first kind' => static fn (TestRunner $t) => $t->same('create_view', $operation132($plan132(), 0)['kind']),
    'schema generated index view reparse current source next132 second kind' => static fn (TestRunner $t) => $t->same('create_view', $operation132($plan132(), 1)['kind']),
    'schema generated index view reparse current source next132 third kind' => static fn (TestRunner $t) => $t->same('create_view', $operation132($plan132(), 2)['kind']),
    'schema generated index view reparse current source next132 first rowid' => static fn (TestRunner $t) => $t->same(5, $operation132($plan132(), 0)['rowid']),
    'schema generated index view reparse current source next132 second rowid' => static fn (TestRunner $t) => $t->same(6, $operation132($plan132(), 1)['rowid']),
    'schema generated index view reparse current source next132 third rowid' => static fn (TestRunner $t) => $t->same(7, $operation132($plan132(), 2)['rowid']),
    'schema generated index view reparse current source next132 first rootpage zero' => static fn (TestRunner $t) => $t->same(0, $operation132($plan132(), 0)['rootpage']),
    'schema generated index view reparse current source next132 source table detected' => static fn (TestRunner $t) => $t->same(['wp_options'], $operation132($plan132(), 0)['source_tables']),
    'schema generated index view reparse current source next132 indexed by detected' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $operation132($plan132(), 0)['indexed_by']),
    'schema generated index view reparse current source next132 generated columns detected' => static fn (TestRunner $t) => $t->same(['option_name_lc', 'option_value_len'], $operation132($plan132(), 0)['generated_column_references']),
    'schema generated index view reparse current source next132 generated column count' => static fn (TestRunner $t) => $t->same(2, $operation132($plan132(), 0)['generated_column_reference_count']),
    'schema generated index view reparse current source next132 generated index detected' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $operation132($plan132(), 0)['generated_index_references']),
    'schema generated index view reparse current source next132 generated index count' => static fn (TestRunner $t) => $t->same(1, $operation132($plan132(), 0)['generated_index_reference_count']),
    'schema generated index view reparse current source next132 first view requires current source' => static fn (TestRunner $t) => $t->same(true, $operation132($plan132(), 0)['current_source_reparse']),
    'schema generated index view reparse current source next132 first view no star expansion' => static fn (TestRunner $t) => $t->same([], $operation132($plan132(), 0)['star_expansion_records']),
    'schema generated index view reparse current source next132 star view source table' => static fn (TestRunner $t) => $t->same(['wp_options'], $operation132($plan132(), 1)['source_tables']),
    'schema generated index view reparse current source next132 star view index' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $operation132($plan132(), 1)['indexed_by']),
    'schema generated index view reparse current source next132 star view generated columns from index' => static fn (TestRunner $t) => $t->same([], $operation132($plan132(), 1)['generated_column_references']),
    'schema generated index view reparse current source next132 star view generated index' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $operation132($plan132(), 1)['generated_index_references']),
    'schema generated index view reparse current source next132 star expansion record' => static fn (TestRunner $t) => $t->same(['view:wp_options_generated_star'], $operation132($plan132(), 1)['star_expansion_records']),
    'schema generated index view reparse current source next132 star view requires current source' => static fn (TestRunner $t) => $t->same(true, $operation132($plan132(), 1)['current_source_reparse']),
    'schema generated index view reparse current source next132 plain view source table' => static fn (TestRunner $t) => $t->same(['wp_options'], $operation132($plan132(), 2)['source_tables']),
    'schema generated index view reparse current source next132 plain view indexed by ordinary index' => static fn (TestRunner $t) => $t->same(['wp_options_autoload'], $operation132($plan132(), 2)['indexed_by']),
    'schema generated index view reparse current source next132 plain view no generated columns' => static fn (TestRunner $t) => $t->same([], $operation132($plan132(), 2)['generated_column_references']),
    'schema generated index view reparse current source next132 plain view no generated index' => static fn (TestRunner $t) => $t->same([], $operation132($plan132(), 2)['generated_index_references']),
    'schema generated index view reparse current source next132 plain view not current source' => static fn (TestRunner $t) => $t->same(false, $operation132($plan132(), 2)['current_source_reparse']),
    'schema generated index view reparse current source next132 view sql recorded' => static function (TestRunner $t) use ($plan132, $recordSql132): void {
        $t->same('CREATE VIEW wp_options_generated_export AS SELECT option_id, option_name_lc, option_value_len FROM wp_options INDEXED BY wp_options_generated_lookup WHERE option_name_lc >= "a"', $recordSql132($plan132()['records'], 'wp_options_generated_export'));
    },
    'schema generated index view reparse current source next132 star view sql recorded' => static function (TestRunner $t) use ($plan132, $recordSql132): void {
        $t->same('CREATE VIEW wp_options_generated_star AS SELECT * FROM wp_options INDEXED BY wp_options_generated_lookup WHERE autoload = "yes"', $recordSql132($plan132()['records'], 'wp_options_generated_star'));
    },
    'schema generated index view reparse current source next132 plain view sql recorded' => static function (TestRunner $t) use ($plan132, $recordSql132): void {
        $t->same('CREATE VIEW wp_options_plain_export AS SELECT option_id, option_name FROM wp_options INDEXED BY wp_options_autoload WHERE autoload = "yes"', $recordSql132($plan132()['records'], 'wp_options_plain_export'));
    },
    'schema generated index view reparse current source next132 catalog includes generated view' => static function (TestRunner $t) use ($plan132): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan132()['records']);
        $rows = $catalog->execute('PRAGMA table_list')['rows'];
        $t->same(true, in_array('wp_options_generated_export', array_column($rows, 'name'), true));
    },
    'schema generated index view reparse current source next132 catalog preserves generated columns' => static function (TestRunner $t) use ($plan132): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan132()['records']);
        $t->same(['option_name_lc', 'option_value_len'], array_slice(array_column($catalog->execute('PRAGMA table_xinfo(wp_options)')['rows'], 'name'), -2));
    },
    'schema generated index view reparse current source next132 table info omits generated columns' => static function (TestRunner $t) use ($plan132): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan132()['records']);
        $t->same(['option_id', 'option_name', 'option_value', 'autoload'], array_column($catalog->execute('PRAGMA table_info(wp_options)')['rows'], 'name'));
    },
    'schema generated index view reparse current source next132 duplicate view is no-op' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_generated_export AS SELECT option_name_lc FROM wp_options', 'CREATE VIEW wp_options_generated_export AS SELECT option_name_lc FROM wp_options']);
        $t->same('view_already_exists', $plan['operations'][1]['reason']);
    },
    'schema generated index view reparse current source next132 duplicate view changes once' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_generated_export AS SELECT option_name_lc FROM wp_options', 'CREATE VIEW wp_options_generated_export AS SELECT option_name_lc FROM wp_options']);
        $t->same(133, $plan['after_schema_cookie']);
    },
    'schema generated index view reparse current source next132 quoted generated column detected' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_quoted_generated AS SELECT "option_name_lc" FROM "wp_options" INDEXED BY "wp_options_generated_lookup"']);
        $t->same(['option_name_lc'], $plan['operations'][0]['generated_column_references']);
    },
    'schema generated index view reparse current source next132 quoted index detected' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_quoted_generated AS SELECT "option_name_lc" FROM "wp_options" INDEXED BY "wp_options_generated_lookup"']);
        $t->same(['wp_options_generated_lookup'], $plan['operations'][0]['generated_index_references']);
    },
    'schema generated index view reparse current source next132 join source tables detected' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_joined_generated AS SELECT o.option_name_lc, m.meta_key FROM wp_options AS o JOIN wp_postmeta AS m ON m.meta_key = o.option_name_lc']);
        $t->same(['wp_options', 'wp_postmeta'], $plan['operations'][0]['source_tables']);
    },
    'schema generated index view reparse current source next132 join generated column detected' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_joined_generated AS SELECT o.option_name_lc, m.meta_key FROM wp_options AS o JOIN wp_postmeta AS m ON m.meta_key = o.option_name_lc']);
        $t->same(['option_name_lc'], $plan['operations'][0]['generated_column_references']);
    },
    'schema generated index view reparse current source next132 join requires current source' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_joined_generated AS SELECT o.option_name_lc, m.meta_key FROM wp_options AS o JOIN wp_postmeta AS m ON m.meta_key = o.option_name_lc']);
        $t->same(true, $plan['operations'][0]['current_source_reparse']);
    },
    'schema generated index view reparse current source next132 table star detected' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_table_star AS SELECT wp_options.* FROM wp_options']);
        $t->same(['view:wp_options_table_star'], $plan['operations'][0]['star_expansion_records']);
    },
    'schema generated index view reparse current source next132 table star requires current source' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_table_star AS SELECT wp_options.* FROM wp_options']);
        $t->same(true, $plan['operations'][0]['current_source_reparse']);
    },
    'schema generated index view reparse current source next132 distinct star detected' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_distinct_star AS SELECT DISTINCT * FROM wp_options']);
        $t->same(['view:wp_options_distinct_star'], $plan['operations'][0]['star_expansion_records']);
    },
    'schema generated index view reparse current source next132 expression generated column detected' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_expr_generated AS SELECT substr(option_name_lc, 1, 4) AS slug FROM wp_options']);
        $t->same(['option_name_lc'], $plan['operations'][0]['generated_column_references']);
    },
    'schema generated index view reparse current source next132 expression view requires current source' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_expr_generated AS SELECT substr(option_name_lc, 1, 4) AS slug FROM wp_options']);
        $t->same(true, $plan['operations'][0]['current_source_reparse']);
    },
    'schema generated index view reparse current source next132 ordinary expression view not current source' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_expr_plain AS SELECT lower(option_name) AS slug FROM wp_options']);
        $t->same(false, $plan['operations'][0]['current_source_reparse']);
    },
    'schema generated index view reparse current source next132 ordinary expression no generated refs' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_expr_plain AS SELECT lower(option_name) AS slug FROM wp_options']);
        $t->same([], $plan['operations'][0]['generated_column_references']);
    },
    'schema generated index view reparse current source next132 generated indexed by with no selected generated column still reparse' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_forced_generated_index AS SELECT option_id FROM wp_options INDEXED BY wp_options_generated_lookup WHERE autoload = "yes"']);
        $t->same(true, $plan['operations'][0]['current_source_reparse']);
    },
    'schema generated index view reparse current source next132 generated indexed by reports zero column refs when not selected' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_forced_generated_index AS SELECT option_id FROM wp_options INDEXED BY wp_options_generated_lookup WHERE autoload = "yes"']);
        $t->same([], $plan['operations'][0]['generated_column_references']);
    },
    'schema generated index view reparse current source next132 generated indexed by reports index refs when not selected' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_options_forced_generated_index AS SELECT option_id FROM wp_options INDEXED BY wp_options_generated_lookup WHERE autoload = "yes"']);
        $t->same(['wp_options_generated_lookup'], $plan['operations'][0]['generated_index_references']);
    },
    'schema generated index view reparse current source next132 rejects missing source table' => static function (TestRunner $t) use ($plan132): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan132(['CREATE VIEW broken_source AS SELECT * FROM missing_options']));
    },
    'schema generated index view reparse current source next132 rejects missing indexed by' => static function (TestRunner $t) use ($plan132): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan132(['CREATE VIEW broken_index AS SELECT option_name FROM wp_options INDEXED BY missing_index']));
    },
    'schema generated index view reparse current source next132 keeps no-source constant view valid' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_constant AS SELECT 1 AS one']);
        $t->same([], $plan['operations'][0]['source_tables']);
    },
    'schema generated index view reparse current source next132 constant view not current source' => static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132(['CREATE VIEW wp_constant AS SELECT 1 AS one']);
        $t->same(false, $plan['operations'][0]['current_source_reparse']);
    },
];

return $tests;
