<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record128 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records128 = static fn (): array => [
    $record128('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")', 1),
    $record128('index', 'wp_options_autoload', 'wp_options', 3, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 2),
    $record128('view', 'wp_options_export', 'wp_options_export', 0, 'CREATE VIEW wp_options_export AS SELECT * FROM wp_options WHERE autoload = "yes"', 3),
    $record128('view', 'wp_options_names', 'wp_options_names', 0, 'CREATE VIEW wp_options_names AS SELECT option_name FROM wp_options WHERE autoload = "yes"', 4),
    $record128('trigger', 'wp_options_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name) VALUES(new.option_id, new.option_name); SELECT * FROM wp_options WHERE option_id = new.option_id; END', 5),
    $record128('trigger', 'wp_options_au', 'wp_options', 0, 'CREATE TRIGGER wp_options_au AFTER UPDATE OF option_value ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name) VALUES(new.option_id, new.option_name); END', 6),
    $record128('view', 'wp_postmeta_names', 'wp_postmeta_names', 0, 'CREATE VIEW wp_postmeta_names AS SELECT meta_key FROM wp_postmeta', 7),
];

$rows128 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_mods_twenty', 'option_value' => 'a:1:{}', 'autoload' => 'no'],
];

$plan128 = static fn (?array $records = null, ?array $rows = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $records ?? $records128(),
    ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'],
    128,
    'main',
    [
        ['id' => 'wp-options-export-star', 'schema_cookie' => 128, 'sql' => 'SELECT * FROM wp_options_export'],
        ['id' => 'current-generated-column-reader', 'schema_cookie' => 129, 'sql' => 'SELECT option_name_lc FROM wp_options'],
    ],
    ['wp_options' => $rows ?? $rows128],
);

$recordSql128 = static function (array $records, string $name): ?string {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && $record->name === $name) {
            return $record->sql;
        }
    }

    return null;
};

$tests = [
    'schema alter view trigger generated current source next128 reports ok' => static fn (TestRunner $t) => $t->same('ok', $plan128()['status']),
    'schema alter view trigger generated current source next128 before cookie' => static fn (TestRunner $t) => $t->same(128, $plan128()['before_schema_cookie']),
    'schema alter view trigger generated current source next128 after cookie' => static fn (TestRunner $t) => $t->same(129, $plan128()['after_schema_cookie']),
    'schema alter view trigger generated current source next128 changed' => static fn (TestRunner $t) => $t->same(true, $plan128()['schema_changed']),
    'schema alter view trigger generated current source next128 operation kind' => static fn (TestRunner $t) => $t->same('alter_table_add_column', $plan128()['operations'][0]['kind']),
    'schema alter view trigger generated current source next128 operation table' => static fn (TestRunner $t) => $t->same('wp_options', $plan128()['operations'][0]['table']),
    'schema alter view trigger generated current source next128 operation column' => static fn (TestRunner $t) => $t->same('option_name_lc', $plan128()['operations'][0]['column']),
    'schema alter view trigger generated current source next128 marks generated' => static fn (TestRunner $t) => $t->same(true, $plan128()['operations'][0]['generated']),
    'schema alter view trigger generated current source next128 scans current rows' => static fn (TestRunner $t) => $t->same(3, $plan128()['operations'][0]['checked_rows']),
    'schema alter view trigger generated current source next128 current row count' => static fn (TestRunner $t) => $t->same(3, $plan128()['operations'][0]['current_row_count']),
    'schema alter view trigger generated current source next128 column count' => static fn (TestRunner $t) => $t->same(5, $plan128()['operations'][0]['column_count']),
    'schema alter view trigger generated current source next128 invalidates stale prepared' => static fn (TestRunner $t) => $t->same(['wp-options-export-star'], $plan128()['invalidated_prepared']),
    'schema alter view trigger generated current source next128 table count stable' => static fn (TestRunner $t) => $t->same(1, $plan128()['table_count']),
    'schema alter view trigger generated current source next128 index count stable' => static fn (TestRunner $t) => $t->same(1, $plan128()['index_count']),
    'schema alter view trigger generated current source next128 dependency list' => static fn (TestRunner $t) => $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan128()['dependencies']),
    'schema alter view trigger generated current source next128 dependent index listed' => static fn (TestRunner $t) => $t->same(true, in_array('index:wp_options_autoload', $plan128()['operations'][0]['dependent_reparse_records'], true)),
    'schema alter view trigger generated current source next128 dependent star view listed' => static fn (TestRunner $t) => $t->same(true, in_array('view:wp_options_export', $plan128()['operations'][0]['dependent_reparse_records'], true)),
    'schema alter view trigger generated current source next128 dependent named view listed' => static fn (TestRunner $t) => $t->same(true, in_array('view:wp_options_names', $plan128()['operations'][0]['dependent_reparse_records'], true)),
    'schema alter view trigger generated current source next128 dependent insert trigger listed' => static fn (TestRunner $t) => $t->same(true, in_array('trigger:wp_options_ai', $plan128()['operations'][0]['dependent_reparse_records'], true)),
    'schema alter view trigger generated current source next128 dependent update trigger listed' => static fn (TestRunner $t) => $t->same(true, in_array('trigger:wp_options_au', $plan128()['operations'][0]['dependent_reparse_records'], true)),
    'schema alter view trigger generated current source next128 unrelated view excluded' => static fn (TestRunner $t) => $t->same(false, in_array('view:wp_postmeta_names', $plan128()['operations'][0]['dependent_reparse_records'], true)),
    'schema alter view trigger generated current source next128 dependent reparse count' => static fn (TestRunner $t) => $t->same(5, count($plan128()['operations'][0]['dependent_reparse_records'])),
    'schema alter view trigger generated current source next128 star view listed' => static fn (TestRunner $t) => $t->same(true, in_array('view:wp_options_export', $plan128()['operations'][0]['star_expansion_records'], true)),
    'schema alter view trigger generated current source next128 star trigger listed' => static fn (TestRunner $t) => $t->same(true, in_array('trigger:wp_options_ai', $plan128()['operations'][0]['star_expansion_records'], true)),
    'schema alter view trigger generated current source next128 named view not star listed' => static fn (TestRunner $t) => $t->same(false, in_array('view:wp_options_names', $plan128()['operations'][0]['star_expansion_records'], true)),
    'schema alter view trigger generated current source next128 named trigger not star listed' => static fn (TestRunner $t) => $t->same(false, in_array('trigger:wp_options_au', $plan128()['operations'][0]['star_expansion_records'], true)),
    'schema alter view trigger generated current source next128 star count' => static fn (TestRunner $t) => $t->same(2, count($plan128()['operations'][0]['star_expansion_records'])),
    'schema alter view trigger generated current source next128 table xinfo sample exists' => static fn (TestRunner $t) => $t->same('table_xinfo', $plan128()['pragma_samples']['table_xinfo:wp_options']['pragma']),
    'schema alter view trigger generated current source next128 table xinfo row count' => static fn (TestRunner $t) => $t->same(5, count($plan128()['pragma_samples']['table_xinfo:wp_options']['rows'])),
    'schema alter view trigger generated current source next128 generated xinfo name' => static fn (TestRunner $t) => $t->same('option_name_lc', $plan128()['pragma_samples']['table_xinfo:wp_options']['rows'][4]['name']),
    'schema alter view trigger generated current source next128 generated xinfo hidden' => static fn (TestRunner $t) => $t->same(2, $plan128()['pragma_samples']['table_xinfo:wp_options']['rows'][4]['hidden']),
    'schema alter view trigger generated current source next128 table sql includes generated column' => static function (TestRunner $t) use ($plan128, $recordSql128): void {
        $t->same('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> ""))', $recordSql128($plan128()['records'], 'wp_options'));
    },
    'schema alter view trigger generated current source next128 view sql preserved for reparse' => static function (TestRunner $t) use ($plan128, $recordSql128): void {
        $t->same('CREATE VIEW wp_options_export AS SELECT * FROM wp_options WHERE autoload = "yes"', $recordSql128($plan128()['records'], 'wp_options_export'));
    },
    'schema alter view trigger generated current source next128 trigger sql preserved for reparse' => static function (TestRunner $t) use ($plan128, $recordSql128): void {
        $t->same('CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name) VALUES(new.option_id, new.option_name); SELECT * FROM wp_options WHERE option_id = new.option_id; END', $recordSql128($plan128()['records'], 'wp_options_ai'));
    },
    'schema alter view trigger generated current source next128 table info omits generated' => static function (TestRunner $t) use ($plan128): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan128()['records']);
        $t->same(4, count($catalog->execute('PRAGMA table_info(wp_options)')['rows']));
    },
    'schema alter view trigger generated current source next128 table xinfo includes generated' => static function (TestRunner $t) use ($plan128): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan128()['records']);
        $t->same('option_name_lc', $catalog->execute('PRAGMA table_xinfo(wp_options)')['rows'][4]['name']);
    },
    'schema alter view trigger generated current source next128 empty rows avoid validation scan failures' => static fn (TestRunner $t) => $t->same(0, $plan128(null, [])['operations'][0]['checked_rows']),
    'schema alter view trigger generated current source next128 rejects current blank generated value' => static function (TestRunner $t) use ($records128): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply(
            $records128(),
            ["ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> '')"],
            128,
            'main',
            [],
            ['wp_options' => [['option_id' => 4, 'option_name' => '', 'autoload' => 'yes']]],
        ));
    },
];

$variants = [
    'quoted table name dependent count' => [
        'ALTER TABLE "wp_options" ADD COLUMN "option slug" TEXT AS (lower(option_name)) VIRTUAL CHECK("option slug" <> "")',
        'dependent_reparse_records',
        5,
    ],
    'quoted table name star count' => [
        'ALTER TABLE "wp_options" ADD COLUMN "option slug" TEXT AS (lower(option_name)) VIRTUAL CHECK("option slug" <> "")',
        'star_expansion_records',
        2,
    ],
    'ordinary column dependent count' => [
        'ALTER TABLE wp_options ADD COLUMN option_note TEXT',
        'dependent_reparse_records',
        5,
    ],
    'ordinary column star count' => [
        'ALTER TABLE wp_options ADD COLUMN option_note TEXT',
        'star_expansion_records',
        2,
    ],
    'ordinary default check scans rows' => [
        'ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT "core" CHECK(option_source <> "")',
        'checked_rows',
        3,
    ],
    'ordinary default check still lists star records' => [
        'ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT "core" CHECK(option_source <> "")',
        'star_expansion_records',
        2,
    ],
    'ordinary no scan keeps dependent count' => [
        'ALTER TABLE wp_options ADD COLUMN option_note TEXT',
        'checked_rows',
        0,
    ],
    'ordinary no scan current count' => [
        'ALTER TABLE wp_options ADD COLUMN option_note TEXT',
        'current_row_count',
        3,
    ],
    'not null default scan keeps dependent count' => [
        'ALTER TABLE wp_options ADD COLUMN site_id INTEGER NOT NULL DEFAULT 1',
        'dependent_reparse_records',
        5,
    ],
    'not null default scan checked rows' => [
        'ALTER TABLE wp_options ADD COLUMN site_id INTEGER NOT NULL DEFAULT 1',
        'checked_rows',
        3,
    ],
    'generated length check dependent count' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 2)',
        'dependent_reparse_records',
        5,
    ],
    'generated length check star count' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 2)',
        'star_expansion_records',
        2,
    ],
    'generated length check row scan' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 2)',
        'checked_rows',
        3,
    ],
    'generated length check column count' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 2)',
        'column_count',
        5,
    ],
];

foreach ($variants as $name => [$sql, $key, $expected]) {
    $tests['schema alter view trigger generated current source next128 ' . $name] = static function (TestRunner $t) use ($records128, $rows128, $sql, $key, $expected): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($records128(), [$sql], 128, 'main', [], ['wp_options' => $rows128]);
        $actual = is_array($plan['operations'][0][$key]) ? count($plan['operations'][0][$key]) : $plan['operations'][0][$key];
        $t->same($expected, $actual);
    };
}

return $tests;
