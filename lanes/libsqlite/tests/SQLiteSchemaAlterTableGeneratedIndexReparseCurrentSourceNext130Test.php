<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record130 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records130 = static fn (): array => [
    $record130('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")', 1),
    $record130('index', 'wp_options_autoload', 'wp_options', 3, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 2),
    $record130('view', 'wp_options_export', 'wp_options_export', 0, 'CREATE VIEW wp_options_export AS SELECT * FROM wp_options WHERE autoload = "yes"', 3),
    $record130('trigger', 'wp_options_ai', 'wp_options', 0, 'CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN SELECT * FROM wp_options WHERE option_id = new.option_id; END', 4),
];

$rows130 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_mods_twenty', 'option_value' => 'a:1:{}', 'autoload' => 'no'],
];

$ddl130 = [
    'ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")',
    'CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc, lower(autoload)) WHERE option_name_lc >= "a"',
];

$plan130 = static fn (?array $ddl = null, ?array $records = null, ?array $rows = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $records ?? $records130(),
    $ddl ?? $ddl130,
    130,
    'main',
    [
        ['id' => 'wp-options-by-name', 'schema_cookie' => 130, 'sql' => 'SELECT option_name FROM wp_options WHERE option_name_lc >= ?'],
        ['id' => 'wp-options-generated-index', 'schema_cookie' => 131, 'sql' => 'SELECT option_name FROM wp_options INDEXED BY wp_options_generated_lookup'],
        ['id' => 'wp-options-current-index', 'schema_cookie' => 132, 'sql' => 'SELECT option_name_lc FROM wp_options INDEXED BY wp_options_generated_lookup'],
    ],
    ['wp_options' => $rows ?? $rows130],
);

$recordByName130 = static function (array $records, string $name): SQLiteSchemaRecord {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && strcasecmp($record->name, $name) === 0) {
            return $record;
        }
    }

    throw new RuntimeException("Missing record {$name}");
};

$tests = [
    'schema alter table generated index reparse current source next130 reports ok' => static fn (TestRunner $t) => $t->same('ok', $plan130()['status']),
    'schema alter table generated index reparse current source next130 before cookie' => static fn (TestRunner $t) => $t->same(130, $plan130()['before_schema_cookie']),
    'schema alter table generated index reparse current source next130 after cookie counts both ddl changes' => static fn (TestRunner $t) => $t->same(132, $plan130()['after_schema_cookie']),
    'schema alter table generated index reparse current source next130 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan130()['schema_changed']),
    'schema alter table generated index reparse current source next130 has two operations' => static fn (TestRunner $t) => $t->same(2, count($plan130()['operations'])),
    'schema alter table generated index reparse current source next130 first operation add column' => static fn (TestRunner $t) => $t->same('alter_table_add_column', $plan130()['operations'][0]['kind']),
    'schema alter table generated index reparse current source next130 second operation create index' => static fn (TestRunner $t) => $t->same('create_index', $plan130()['operations'][1]['kind']),
    'schema alter table generated index reparse current source next130 generated column name' => static fn (TestRunner $t) => $t->same('option_name_lc', $plan130()['operations'][0]['column']),
    'schema alter table generated index reparse current source next130 generated column flag' => static fn (TestRunner $t) => $t->same(true, $plan130()['operations'][0]['generated']),
    'schema alter table generated index reparse current source next130 generated column scans rows' => static fn (TestRunner $t) => $t->same(3, $plan130()['operations'][0]['checked_rows']),
    'schema alter table generated index reparse current source next130 generated column count' => static fn (TestRunner $t) => $t->same(5, $plan130()['operations'][0]['column_count']),
    'schema alter table generated index reparse current source next130 add column dependent count' => static fn (TestRunner $t) => $t->same(3, count($plan130()['operations'][0]['dependent_reparse_records'])),
    'schema alter table generated index reparse current source next130 add column star count' => static fn (TestRunner $t) => $t->same(2, count($plan130()['operations'][0]['star_expansion_records'])),
    'schema alter table generated index reparse current source next130 index name' => static fn (TestRunner $t) => $t->same('wp_options_generated_lookup', $plan130()['operations'][1]['name']),
    'schema alter table generated index reparse current source next130 index table' => static fn (TestRunner $t) => $t->same('wp_options', $plan130()['operations'][1]['table']),
    'schema alter table generated index reparse current source next130 index rootpage' => static fn (TestRunner $t) => $t->same(4, $plan130()['operations'][1]['rootpage']),
    'schema alter table generated index reparse current source next130 index rowid' => static fn (TestRunner $t) => $t->same(5, $plan130()['operations'][1]['rowid']),
    'schema alter table generated index reparse current source next130 index partial' => static fn (TestRunner $t) => $t->same(true, $plan130()['operations'][1]['partial']),
    'schema alter table generated index reparse current source next130 index is not unique' => static fn (TestRunner $t) => $t->same(false, $plan130()['operations'][1]['unique']),
    'schema alter table generated index reparse current source next130 index term count' => static fn (TestRunner $t) => $t->same(2, $plan130()['operations'][1]['term_count']),
    'schema alter table generated index reparse current source next130 first term generated column' => static fn (TestRunner $t) => $t->same('option_name_lc', $plan130()['operations'][1]['terms'][0]),
    'schema alter table generated index reparse current source next130 second term expression' => static fn (TestRunner $t) => $t->same('lower(autoload)', $plan130()['operations'][1]['terms'][1]),
    'schema alter table generated index reparse current source next130 expression terms list' => static fn (TestRunner $t) => $t->same(['lower(autoload)'], $plan130()['operations'][1]['expression_terms']),
    'schema alter table generated index reparse current source next130 generated reference list' => static fn (TestRunner $t) => $t->same(['option_name_lc'], $plan130()['operations'][1]['generated_column_references']),
    'schema alter table generated index reparse current source next130 generated reference count' => static fn (TestRunner $t) => $t->same(1, $plan130()['operations'][1]['generated_column_reference_count']),
    'schema alter table generated index reparse current source next130 index requires current source reparse' => static fn (TestRunner $t) => $t->same(true, $plan130()['operations'][1]['current_source_reparse']),
    'schema alter table generated index reparse current source next130 table count stable' => static fn (TestRunner $t) => $t->same(1, $plan130()['table_count']),
    'schema alter table generated index reparse current source next130 index count increased' => static fn (TestRunner $t) => $t->same(2, $plan130()['index_count']),
    'schema alter table generated index reparse current source next130 invalidates stale prepared statements' => static fn (TestRunner $t) => $t->same(['wp-options-by-name', 'wp-options-generated-index'], $plan130()['invalidated_prepared']),
    'schema alter table generated index reparse current source next130 dependency list' => static fn (TestRunner $t) => $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan130()['dependencies']),
    'schema alter table generated index reparse current source next130 table sql rewritten' => static function (TestRunner $t) use ($plan130, $recordByName130): void {
        $t->same('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> ""))', $recordByName130($plan130()['records'], 'wp_options')->sql);
    },
    'schema alter table generated index reparse current source next130 index sql recorded' => static function (TestRunner $t) use ($plan130, $recordByName130): void {
        $t->same('CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc, lower(autoload)) WHERE option_name_lc >= "a"', $recordByName130($plan130()['records'], 'wp_options_generated_lookup')->sql);
    },
    'schema alter table generated index reparse current source next130 index table name recorded' => static function (TestRunner $t) use ($plan130, $recordByName130): void {
        $t->same('wp_options', $recordByName130($plan130()['records'], 'wp_options_generated_lookup')->tableName);
    },
    'schema alter table generated index reparse current source next130 table xinfo sample exists' => static fn (TestRunner $t) => $t->same('table_xinfo', $plan130()['pragma_samples']['table_xinfo:wp_options']['pragma']),
    'schema alter table generated index reparse current source next130 table xinfo row count' => static fn (TestRunner $t) => $t->same(5, count($plan130()['pragma_samples']['table_xinfo:wp_options']['rows'])),
    'schema alter table generated index reparse current source next130 generated table xinfo hidden' => static fn (TestRunner $t) => $t->same(2, $plan130()['pragma_samples']['table_xinfo:wp_options']['rows'][4]['hidden']),
    'schema alter table generated index reparse current source next130 table info still omits generated' => static function (TestRunner $t) use ($plan130): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan130()['records']);
        $t->same(4, count($catalog->execute('PRAGMA table_info(wp_options)')['rows']));
    },
    'schema alter table generated index reparse current source next130 table xinfo includes generated' => static function (TestRunner $t) use ($plan130): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan130()['records']);
        $t->same('option_name_lc', $catalog->execute('PRAGMA table_xinfo(wp_options)')['rows'][4]['name']);
    },
];

$variants = [
    'plain generated index reference' => [
        ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL', 'CREATE INDEX wp_options_lc ON wp_options(option_name_lc)'],
        'generated_column_references',
        ['option_name_lc'],
    ],
    'quoted generated index reference' => [
        ['ALTER TABLE wp_options ADD COLUMN "option slug" TEXT AS (lower(option_name)) VIRTUAL', 'CREATE INDEX wp_options_slug ON wp_options("option slug")'],
        'generated_column_references',
        ['option slug'],
    ],
    'generated expression reference' => [
        ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL', 'CREATE INDEX wp_options_lc_expr ON wp_options(substr(option_name_lc, 1, 4))'],
        'generated_column_references',
        ['option_name_lc'],
    ],
    'ordinary column no generated reference' => [
        ['ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT "core"', 'CREATE INDEX wp_options_source ON wp_options(option_source)'],
        'generated_column_references',
        [],
    ],
    'ordinary partial index reparses current source' => [
        ['ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT "core"', 'CREATE INDEX wp_options_source ON wp_options(option_source) WHERE option_source = "core"'],
        'current_source_reparse',
        true,
    ],
    'ordinary non partial index does not require generated reparse' => [
        ['ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT "core"', 'CREATE INDEX wp_options_source ON wp_options(option_source)'],
        'current_source_reparse',
        false,
    ],
    'collated generated term remains identifier term' => [
        ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL', 'CREATE INDEX wp_options_lc ON wp_options(option_name_lc COLLATE nocase DESC)'],
        'expression_terms',
        [],
    ],
    'function term is expression term' => [
        ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL', 'CREATE INDEX wp_options_lc ON wp_options(lower(option_name_lc))'],
        'expression_terms',
        ['lower(option_name_lc)'],
    ],
    'multi generated references preserve order' => [
        ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL', 'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL', 'CREATE INDEX wp_options_mix ON wp_options(option_value_len, option_name_lc)'],
        'generated_column_references',
        ['option_value_len', 'option_name_lc'],
    ],
    'create index after two generated columns has two terms' => [
        ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL', 'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL', 'CREATE INDEX wp_options_mix ON wp_options(option_value_len, option_name_lc)'],
        'term_count',
        2,
    ],
    'unique generated index is marked unique' => [
        ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL', 'CREATE UNIQUE INDEX wp_options_lc_unique ON wp_options(option_name_lc)'],
        'unique',
        true,
    ],
    'duplicate generated index no-op keeps reason' => [
        ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL', 'CREATE INDEX wp_options_autoload ON wp_options(option_name_lc)'],
        'reason',
        'index_already_exists',
    ],
];

foreach ($variants as $name => [$ddl, $key, $expected]) {
    $tests['schema alter table generated index reparse current source next130 ' . $name] = static function (TestRunner $t) use ($plan130, $ddl, $key, $expected): void {
        $plan = $plan130($ddl);
        $operation = $plan['operations'][count($plan['operations']) - 1];
        $t->same($expected, $operation[$key]);
    };
}

$tests['schema alter table generated index reparse current source next130 rejects index before generated column exists'] = static function (TestRunner $t) use ($records130): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply(
        $records130(),
        ['CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc)'],
    ));
};

$tests['schema alter table generated index reparse current source next130 rejects generated check before index'] = static function (TestRunner $t) use ($plan130): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan130(
        ["ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> '')", 'CREATE INDEX wp_options_generated_lookup ON wp_options(option_name_lc)'],
        null,
        [['option_id' => 4, 'option_name' => '', 'autoload' => 'yes']],
    ));
};

return $tests;
