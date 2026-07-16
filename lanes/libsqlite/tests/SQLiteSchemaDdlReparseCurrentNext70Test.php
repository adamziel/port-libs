<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$baseRecords = static fn (): array => [
    $record(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')",
        1,
    ),
    $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    $record('index', 'wp_options_autoload', 'wp_options', 4, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 3),
    $record('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, "CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes'", 4),
    $record('trigger', 'wp_options_touch', 'wp_options', 0, "CREATE TRIGGER wp_options_touch AFTER UPDATE ON wp_options BEGIN SELECT new.option_name; END", 5),
    $record('trigger', 'wp_autoloaded_insert', 'wp_autoloaded_options', 0, "CREATE TRIGGER wp_autoloaded_insert INSTEAD OF INSERT ON wp_autoloaded_options BEGIN INSERT INTO wp_options(option_id, option_name, autoload) VALUES(new.option_id, new.option_name, 'yes'); END", 6),
];

$byName = static function (array $records, string $name): ?SQLiteSchemaRecord {
    foreach ($records as $record) {
        if ($record->name === $name) {
            return $record;
        }
    }

    return null;
};

$tests = [
    'schema ddl reparse current next70 creates view and trigger records' => static function (TestRunner $t) use ($baseRecords, $byName): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            array_slice($baseRecords(), 0, 3),
            [
                "CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes'",
                "CREATE TRIGGER wp_autoloaded_insert INSTEAD OF INSERT ON wp_autoloaded_options BEGIN INSERT INTO wp_options(option_id, option_name, autoload) VALUES(new.option_id, new.option_name, 'yes'); END",
            ],
            70,
            'main',
            [
                ['id' => 'wp-options-all', 'schema_cookie' => 70, 'sql' => 'SELECT * FROM wp_options'],
                ['id' => 'already-new-cookie', 'schema_cookie' => 72, 'sql' => 'SELECT option_name FROM wp_options'],
            ],
        );

        $t->same('ok', $plan['status']);
        $t->same(70, $plan['before_schema_cookie']);
        $t->same(72, $plan['after_schema_cookie']);
        $t->same(true, $plan['schema_changed']);
        $t->same(2, count($plan['operations']));
        $t->same('create_view', $plan['operations'][0]['kind']);
        $t->same('wp_autoloaded_options', $plan['operations'][0]['name']);
        $t->same(0, $plan['operations'][0]['rootpage']);
        $t->same(4, $plan['operations'][0]['rowid']);
        $t->same(true, $plan['operations'][0]['changed']);
        $t->same('create_trigger', $plan['operations'][1]['kind']);
        $t->same('wp_autoloaded_insert', $plan['operations'][1]['name']);
        $t->same('wp_autoloaded_options', $plan['operations'][1]['table']);
        $t->same(0, $plan['operations'][1]['rootpage']);
        $t->same(5, $plan['operations'][1]['rowid']);
        $t->same(1, $plan['table_count']);
        $t->same(2, $plan['index_count']);
        $t->same(['wp-options-all'], $plan['invalidated_prepared']);

        $view = $byName($plan['records'], 'wp_autoloaded_options');
        $trigger = $byName($plan['records'], 'wp_autoloaded_insert');
        $t->same('view', $view?->type);
        $t->same('wp_autoloaded_options', $view?->tableName);
        $t->same(0, $view?->rootPage);
        $t->same("CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes'", $view?->sql);
        $t->same('trigger', $trigger?->type);
        $t->same('wp_autoloaded_options', $trigger?->tableName);
        $t->same(0, $trigger?->rootPage);
        $t->same(true, str_contains((string) $trigger?->sql, "VALUES(new.option_id, new.option_name, 'yes')"));
        $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan['dependencies']);
    },
    'schema ddl reparse current next70 drops view and trigger independently' => static function (TestRunner $t) use ($baseRecords, $byName): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords(),
            [
                'DROP TRIGGER wp_autoloaded_insert',
                'DROP VIEW wp_autoloaded_options',
            ],
            80,
        );

        $t->same(82, $plan['after_schema_cookie']);
        $t->same(2, count($plan['operations']));
        $t->same('drop_trigger', $plan['operations'][0]['kind']);
        $t->same('wp_autoloaded_insert', $plan['operations'][0]['name']);
        $t->same('wp_autoloaded_options', $plan['operations'][0]['table']);
        $t->same(true, $plan['operations'][0]['changed']);
        $t->same('drop_view', $plan['operations'][1]['kind']);
        $t->same('wp_autoloaded_options', $plan['operations'][1]['name']);
        $t->same(true, $plan['operations'][1]['changed']);
        $t->same(null, $byName($plan['records'], 'wp_autoloaded_insert'));
        $t->same(null, $byName($plan['records'], 'wp_autoloaded_options'));
        $t->same('wp_options_touch', $byName($plan['records'], 'wp_options_touch')?->name);
        $t->same(1, $plan['table_count']);
        $t->same(2, $plan['index_count']);
    },
    'schema ddl reparse current next70 drops table indexes and table triggers but leaves dependent views' => static function (TestRunner $t) use ($baseRecords, $byName): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['DROP TABLE wp_options'], 90);

        $t->same(91, $plan['after_schema_cookie']);
        $t->same('drop_table', $plan['operations'][0]['kind']);
        $t->same('wp_options', $plan['operations'][0]['name']);
        $t->same(
            ['table:wp_options', 'index:sqlite_autoindex_wp_options_1', 'index:wp_options_autoload', 'trigger:wp_options_touch'],
            $plan['operations'][0]['removed_records'],
        );
        $t->same([2, 3, 4], $plan['operations'][0]['freed_rootpages']);
        $t->same(0, $plan['table_count']);
        $t->same(0, $plan['index_count']);
        $t->same(null, $byName($plan['records'], 'wp_options_touch'));
        $t->same('wp_autoloaded_options', $byName($plan['records'], 'wp_autoloaded_options')?->name);
        $t->same('wp_autoloaded_insert', $byName($plan['records'], 'wp_autoloaded_insert')?->name);

        $catalog = new SQLitePragmaSchemaCatalog($plan['records']);
        $t->same([], $catalog->execute('PRAGMA table_info(wp_options)')['rows']);
        $t->same([], $catalog->execute('PRAGMA index_list(wp_options)')['rows']);
    },
    'schema ddl reparse current next70 quoted and temporary view trigger names normalize' => static function (TestRunner $t) use ($baseRecords, $byName): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            array_slice($baseRecords(), 0, 3),
            [
                'CREATE TEMP VIEW "wp active options" AS SELECT option_name FROM wp_options',
                'CREATE TEMP TRIGGER `wp active insert` INSTEAD OF INSERT ON "wp active options" BEGIN SELECT new.option_name; END',
            ],
            100,
        );

        $t->same(102, $plan['after_schema_cookie']);
        $t->same('wp active options', $plan['operations'][0]['name']);
        $t->same('wp active insert', $plan['operations'][1]['name']);
        $t->same('wp active options', $plan['operations'][1]['table']);
        $t->same('view', $byName($plan['records'], 'wp active options')?->type);
        $t->same('trigger', $byName($plan['records'], 'wp active insert')?->type);
        $t->same('wp active options', $byName($plan['records'], 'wp active insert')?->tableName);
        $t->same(0, $byName($plan['records'], 'wp active options')?->rootPage);
        $t->same(0, $byName($plan['records'], 'wp active insert')?->rootPage);
    },
    'schema ddl reparse current next70 no-op existing and missing view trigger DDL keeps cookie stable' => static function (TestRunner $t) use ($baseRecords): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords(),
            [
                'CREATE VIEW IF NOT EXISTS wp_autoloaded_options AS SELECT 1',
                'CREATE TRIGGER IF NOT EXISTS wp_options_touch AFTER UPDATE ON wp_options BEGIN SELECT 1; END',
                'DROP VIEW IF EXISTS missing_view',
                'DROP TRIGGER IF EXISTS missing_trigger',
            ],
            110,
        );

        $t->same(110, $plan['after_schema_cookie']);
        $t->same(false, $plan['schema_changed']);
        $t->same(4, count($plan['operations']));
        $t->same('view_already_exists', $plan['operations'][0]['reason']);
        $t->same('trigger_already_exists', $plan['operations'][1]['reason']);
        $t->same('missing_view', $plan['operations'][2]['reason']);
        $t->same('missing_trigger', $plan['operations'][3]['reason']);
        $t->same([], $plan['invalidated_prepared']);
        $t->same(1, $plan['table_count']);
        $t->same(2, $plan['index_count']);
    },
    'schema ddl reparse current next70 rejects unsafe view trigger DDL' => static function (TestRunner $t) use ($baseRecords): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['CREATE TRIGGER bad_trigger AFTER INSERT BEGIN SELECT 1; END']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['CREATE TRIGGER bad_trigger AFTER INSERT ON missing_table BEGIN SELECT 1; END']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['CREATE VIRTUAL TABLE wp_search USING fts5(option_name)']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['CREATE TRIGGER bad_trigger AFTER INSERT ON main.wp_options BEGIN SELECT 1; END']));
    },
];

return $tests;
