<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record138 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records138 = static fn (): array => [
    $record138('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_name_lc TEXT AS (lower(option_name)) VIRTUAL, option_value_len INTEGER AS (length(option_value)) VIRTUAL)', 1),
    $record138('index', 'wp_options_generated_lookup', 'wp_options', 3, 'CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc, option_value_len) WHERE option_name_lc >= "a"', 2),
    $record138('index', 'wp_options_autoload', 'wp_options', 4, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 3),
    $record138('table', 'wp_option_audit', 'wp_option_audit', 5, 'CREATE TABLE wp_option_audit(audit_id INTEGER PRIMARY KEY, option_name TEXT, label TEXT)', 4),
    $record138('view', 'wp_options_generated_base', 'wp_options_generated_base', 0, 'CREATE VIEW wp_options_generated_base AS SELECT option_id, option_name_lc, option_value_len FROM wp_options INDEXED BY wp_options_generated_lookup WHERE option_name_lc >= "a"', 5),
    $record138('view', 'wp_options_plain_base', 'wp_options_plain_base', 0, 'CREATE VIEW wp_options_plain_base AS SELECT option_id, option_name FROM wp_options WHERE autoload = "yes"', 6),
];

$ddl138 = [
    'CREATE VIEW wp_options_generated_export AS SELECT option_id, option_name_lc FROM wp_options_generated_base WHERE option_value_len > 0',
    'CREATE VIEW wp_options_generated_star_export AS SELECT * FROM wp_options_generated_base',
    'CREATE TRIGGER wp_options_generated_export_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_name, label) SELECT option_name_lc, "nested" FROM wp_options_generated_export WHERE option_id = new.option_id; END',
    'CREATE VIEW wp_options_plain_export AS SELECT option_id, option_name FROM wp_options_plain_base',
];

$plan138 = static fn (?array $ddl = null, ?array $records = null, ?array $prepared = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $records ?? $records138(),
    $ddl ?? $ddl138,
    138,
    'main',
    $prepared ?? [
        ['id' => 'stale-generated-export-reader', 'schema_cookie' => 138, 'sql' => 'SELECT * FROM wp_options_generated_export'],
        ['id' => 'stale-generated-trigger-insert', 'schema_cookie' => 140, 'sql' => 'INSERT INTO wp_options(option_name) VALUES(?)'],
        ['id' => 'fresh-audit-reader', 'schema_cookie' => 142, 'sql' => 'SELECT * FROM wp_option_audit'],
    ],
);

$op138 = static fn (int $index): array => $plan138()['operations'][$index];

$recordSql138 = static function (array $records, string $name): ?string {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && strcasecmp($record->name, $name) === 0) {
            return $record->sql;
        }
    }

    return null;
};

$tests = [
    'schema generated view index reparse current source next138 reports ok' => static fn (TestRunner $t) => $t->same('ok', $plan138()['status']),
    'schema generated view index reparse current source next138 before cookie' => static fn (TestRunner $t) => $t->same(138, $plan138()['before_schema_cookie']),
    'schema generated view index reparse current source next138 after cookie' => static fn (TestRunner $t) => $t->same(142, $plan138()['after_schema_cookie']),
    'schema generated view index reparse current source next138 changed flag' => static fn (TestRunner $t) => $t->same(true, $plan138()['schema_changed']),
    'schema generated view index reparse current source next138 operation count' => static fn (TestRunner $t) => $t->same(4, count($plan138()['operations'])),
    'schema generated view index reparse current source next138 table count stable' => static fn (TestRunner $t) => $t->same(2, $plan138()['table_count']),
    'schema generated view index reparse current source next138 index count stable' => static fn (TestRunner $t) => $t->same(2, $plan138()['index_count']),
    'schema generated view index reparse current source next138 invalidates stale readers' => static fn (TestRunner $t) => $t->same(['stale-generated-export-reader', 'stale-generated-trigger-insert'], $plan138()['invalidated_prepared']),
    'schema generated view index reparse current source next138 dependencies stable' => static fn (TestRunner $t) => $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan138()['dependencies']),
    'schema generated view index reparse current source next138 first operation view' => static fn (TestRunner $t) => $t->same('create_view', $op138(0)['kind']),
    'schema generated view index reparse current source next138 first view name' => static fn (TestRunner $t) => $t->same('wp_options_generated_export', $op138(0)['name']),
    'schema generated view index reparse current source next138 first view rootpage zero' => static fn (TestRunner $t) => $t->same(0, $op138(0)['rootpage']),
    'schema generated view index reparse current source next138 first view rowid' => static fn (TestRunner $t) => $t->same(7, $op138(0)['rowid']),
    'schema generated view index reparse current source next138 first view has no direct table' => static fn (TestRunner $t) => $t->same([], $op138(0)['source_tables']),
    'schema generated view index reparse current source next138 first view source view' => static fn (TestRunner $t) => $t->same(['wp_options_generated_base'], $op138(0)['source_views']),
    'schema generated view index reparse current source next138 first view reference label' => static fn (TestRunner $t) => $t->same(['view:wp_options_generated_base'], $op138(0)['view_references']),
    'schema generated view index reparse current source next138 first view inherits generated columns' => static fn (TestRunner $t) => $t->same(['option_name_lc', 'option_value_len'], $op138(0)['generated_column_references']),
    'schema generated view index reparse current source next138 first view generated column count' => static fn (TestRunner $t) => $t->same(2, $op138(0)['generated_column_reference_count']),
    'schema generated view index reparse current source next138 first view inherits generated index' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $op138(0)['generated_index_references']),
    'schema generated view index reparse current source next138 first view generated index count' => static fn (TestRunner $t) => $t->same(1, $op138(0)['generated_index_reference_count']),
    'schema generated view index reparse current source next138 first view current source reparse' => static fn (TestRunner $t) => $t->same(true, $op138(0)['current_source_reparse']),
    'schema generated view index reparse current source next138 first view no star expansion' => static fn (TestRunner $t) => $t->same([], $op138(0)['star_expansion_records']),
    'schema generated view index reparse current source next138 star view name' => static fn (TestRunner $t) => $t->same('wp_options_generated_star_export', $op138(1)['name']),
    'schema generated view index reparse current source next138 star view source view' => static fn (TestRunner $t) => $t->same(['wp_options_generated_base'], $op138(1)['source_views']),
    'schema generated view index reparse current source next138 star view inherits generated columns' => static fn (TestRunner $t) => $t->same(['option_name_lc', 'option_value_len'], $op138(1)['generated_column_references']),
    'schema generated view index reparse current source next138 star view inherits generated index' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $op138(1)['generated_index_references']),
    'schema generated view index reparse current source next138 star view records itself' => static fn (TestRunner $t) => $t->same(['view:wp_options_generated_star_export'], $op138(1)['star_expansion_records']),
    'schema generated view index reparse current source next138 trigger operation kind' => static fn (TestRunner $t) => $t->same('create_trigger', $op138(2)['kind']),
    'schema generated view index reparse current source next138 trigger name' => static fn (TestRunner $t) => $t->same('wp_options_generated_export_ai', $op138(2)['name']),
    'schema generated view index reparse current source next138 trigger target' => static fn (TestRunner $t) => $t->same('wp_options', $op138(2)['table']),
    'schema generated view index reparse current source next138 trigger body tables audit only' => static fn (TestRunner $t) => $t->same(['wp_option_audit'], $op138(2)['body_source_tables']),
    'schema generated view index reparse current source next138 trigger body source view' => static fn (TestRunner $t) => $t->same(['wp_options_generated_export'], $op138(2)['body_source_views']),
    'schema generated view index reparse current source next138 trigger view reference label' => static fn (TestRunner $t) => $t->same(['view:wp_options_generated_export'], $op138(2)['view_references']),
    'schema generated view index reparse current source next138 trigger inherits generated columns through view' => static fn (TestRunner $t) => $t->same(['option_name_lc', 'option_value_len'], $op138(2)['generated_column_references']),
    'schema generated view index reparse current source next138 trigger inherits generated index through view' => static fn (TestRunner $t) => $t->same(['wp_options_generated_lookup'], $op138(2)['generated_index_references']),
    'schema generated view index reparse current source next138 trigger generated column count' => static fn (TestRunner $t) => $t->same(2, $op138(2)['generated_column_reference_count']),
    'schema generated view index reparse current source next138 trigger generated index count' => static fn (TestRunner $t) => $t->same(1, $op138(2)['generated_index_reference_count']),
    'schema generated view index reparse current source next138 trigger current source reparse' => static fn (TestRunner $t) => $t->same(true, $op138(2)['current_source_reparse']),
    'schema generated view index reparse current source next138 plain view name' => static fn (TestRunner $t) => $t->same('wp_options_plain_export', $op138(3)['name']),
    'schema generated view index reparse current source next138 plain view source view' => static fn (TestRunner $t) => $t->same(['wp_options_plain_base'], $op138(3)['source_views']),
    'schema generated view index reparse current source next138 plain view reference label' => static fn (TestRunner $t) => $t->same(['view:wp_options_plain_base'], $op138(3)['view_references']),
    'schema generated view index reparse current source next138 plain view no generated columns' => static fn (TestRunner $t) => $t->same([], $op138(3)['generated_column_references']),
    'schema generated view index reparse current source next138 plain view no generated indexes' => static fn (TestRunner $t) => $t->same([], $op138(3)['generated_index_references']),
    'schema generated view index reparse current source next138 plain view still current source by view dependency' => static fn (TestRunner $t) => $t->same(true, $op138(3)['current_source_reparse']),
    'schema generated view index reparse current source next138 schema record count' => static fn (TestRunner $t) => $t->same(10, count($plan138()['records'])),
    'schema generated view index reparse current source next138 nested view record stored' => static function (TestRunner $t) use ($plan138, $recordSql138): void {
        $t->same(true, str_contains((string) $recordSql138($plan138()['records'], 'wp_options_generated_export'), 'wp_options_generated_base'));
    },
    'schema generated view index reparse current source next138 trigger record stored' => static function (TestRunner $t) use ($plan138, $recordSql138): void {
        $t->same(true, str_contains((string) $recordSql138($plan138()['records'], 'wp_options_generated_export_ai'), 'wp_options_generated_export'));
    },
    'schema generated view index reparse current source next138 pragma table list keeps nested views' => static function (TestRunner $t) use ($plan138): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan138()['records']);
        $t->same(true, in_array('wp_options_generated_export', array_column($catalog->execute('PRAGMA table_list')['rows'], 'name'), true));
    },
    'schema generated view index reparse current source next138 direct table view still works' => static function (TestRunner $t) use ($plan138): void {
        $plan = $plan138(['CREATE VIEW wp_options_direct_generated AS SELECT option_name_lc FROM wp_options INDEXED BY wp_options_generated_lookup']);
        $t->same(['wp_options'], $plan['operations'][0]['source_tables']);
    },
    'schema generated view index reparse current source next138 direct table generated index still works' => static function (TestRunner $t) use ($plan138): void {
        $plan = $plan138(['CREATE VIEW wp_options_direct_generated AS SELECT option_name_lc FROM wp_options INDEXED BY wp_options_generated_lookup']);
        $t->same(['wp_options_generated_lookup'], $plan['operations'][0]['generated_index_references']);
    },
    'schema generated view index reparse current source next138 duplicate nested view no op' => static function (TestRunner $t) use ($plan138): void {
        $plan = $plan138(['CREATE VIEW wp_options_generated_export AS SELECT option_id FROM wp_options_generated_base', 'CREATE VIEW wp_options_generated_export AS SELECT option_id FROM wp_options_generated_base']);
        $t->same('view_already_exists', $plan['operations'][1]['reason']);
    },
    'schema generated view index reparse current source next138 duplicate nested view cookie advances once' => static function (TestRunner $t) use ($plan138): void {
        $plan = $plan138(['CREATE VIEW wp_options_generated_export AS SELECT option_id FROM wp_options_generated_base', 'CREATE VIEW wp_options_generated_export AS SELECT option_id FROM wp_options_generated_base']);
        $t->same(139, $plan['after_schema_cookie']);
    },
    'schema generated view index reparse current source next138 quoted source view resolves' => static function (TestRunner $t) use ($plan138): void {
        $plan = $plan138(['CREATE VIEW "wp_options_generated_quoted" AS SELECT option_name_lc FROM "wp_options_generated_base"']);
        $t->same(['wp_options_generated_base'], $plan['operations'][0]['source_views']);
    },
    'schema generated view index reparse current source next138 quoted source view inherits generated index' => static function (TestRunner $t) use ($plan138): void {
        $plan = $plan138(['CREATE VIEW "wp_options_generated_quoted" AS SELECT option_name_lc FROM "wp_options_generated_base"']);
        $t->same(['wp_options_generated_lookup'], $plan['operations'][0]['generated_index_references']);
    },
    'schema generated view index reparse current source next138 rejects missing source view' => static function (TestRunner $t) use ($plan138): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan138(['CREATE VIEW broken_options_export AS SELECT * FROM missing_options_view']));
    },
    'schema generated view index reparse current source next138 rejects missing view in trigger body' => static function (TestRunner $t) use ($plan138): void {
        $plan = $plan138(['CREATE TRIGGER missing_view_ai AFTER INSERT ON wp_options BEGIN SELECT * FROM missing_options_view; END']);
        $t->same([], $plan['operations'][0]['body_source_views']);
    },
];

return $tests;
