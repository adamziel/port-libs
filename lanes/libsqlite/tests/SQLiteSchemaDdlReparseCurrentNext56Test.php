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
];

$tests = [
    'schema ddl reparse current next56 creates partial index and reparses catalog' => static function (TestRunner $t) use ($baseRecords): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords(),
            ["CREATE UNIQUE INDEX wp_options_autoload_name ON wp_options(autoload, option_name) WHERE autoload = 'yes'"],
            56,
            'main',
            [
                ['id' => 'select-options-by-autoload', 'schema_cookie' => 56, 'sql' => 'SELECT * FROM wp_options WHERE autoload = ?'],
                ['id' => 'stale-before-ddl', 'schema_cookie' => 55, 'sql' => 'SELECT option_name FROM wp_options'],
            ],
        );

        $t->same('ok', $plan['status']);
        $t->same('main', $plan['schema']);
        $t->same(56, $plan['before_schema_cookie']);
        $t->same(57, $plan['after_schema_cookie']);
        $t->same(true, $plan['schema_changed']);
        $t->same(1, count($plan['operations']));
        $t->same('create_index', $plan['operations'][0]['kind']);
        $t->same('wp_options_autoload_name', $plan['operations'][0]['name']);
        $t->same('wp_options', $plan['operations'][0]['table']);
        $t->same(5, $plan['operations'][0]['rootpage']);
        $t->same(4, $plan['operations'][0]['rowid']);
        $t->same(true, $plan['operations'][0]['unique']);
        $t->same(true, $plan['operations'][0]['partial']);
        $t->same(1, $plan['table_count']);
        $t->same(3, $plan['index_count']);
        $t->same(['select-options-by-autoload', 'stale-before-ddl'], $plan['invalidated_prepared']);

        $sample = $plan['pragma_samples']['index_list:wp_options'];
        $t->same('index_list', $sample['pragma']);
        $t->same(3, count($sample['rows']));
        $t->same('sqlite_autoindex_wp_options_1', $sample['rows'][0]['name']);
        $t->same('wp_options_autoload', $sample['rows'][1]['name']);
        $t->same('wp_options_autoload_name', $sample['rows'][2]['name']);
        $t->same(1, $sample['rows'][2]['unique']);
        $t->same(1, $sample['rows'][2]['partial']);
        $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan['dependencies']);
    },
    'schema ddl reparse current next56 drops index and removes stale pragma rows' => static function (TestRunner $t) use ($baseRecords): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['DROP INDEX wp_options_autoload'], 60);

        $t->same(61, $plan['after_schema_cookie']);
        $t->same(1, $plan['index_count']);
        $t->same('drop_index', $plan['operations'][0]['kind']);
        $t->same('wp_options_autoload', $plan['operations'][0]['name']);
        $t->same('wp_options', $plan['operations'][0]['table']);
        $t->same(4, $plan['operations'][0]['freed_rootpage']);
        $t->same(true, $plan['operations'][0]['changed']);

        $catalog = new SQLitePragmaSchemaCatalog($plan['records']);
        $indexList = $catalog->execute('PRAGMA index_list(wp_options)');
        $t->same(1, count($indexList['rows']));
        $t->same('sqlite_autoindex_wp_options_1', $indexList['rows'][0]['name']);
        $t->same([], $catalog->execute('PRAGMA index_info(wp_options_autoload)')['rows']);
    },
    'schema ddl reparse current next56 drops table and dependent indexes together' => static function (TestRunner $t) use ($baseRecords): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['DROP TABLE wp_options'], 71);

        $t->same(72, $plan['after_schema_cookie']);
        $t->same(0, $plan['table_count']);
        $t->same(0, $plan['index_count']);
        $t->same('drop_table', $plan['operations'][0]['kind']);
        $t->same('wp_options', $plan['operations'][0]['name']);
        $t->same(['table:wp_options', 'index:sqlite_autoindex_wp_options_1', 'index:wp_options_autoload'], $plan['operations'][0]['removed_records']);
        $t->same([2, 3, 4], $plan['operations'][0]['freed_rootpages']);

        $catalog = new SQLitePragmaSchemaCatalog($plan['records']);
        $t->same([], $catalog->execute('PRAGMA table_info(wp_options)')['rows']);
        $t->same([], $catalog->execute('PRAGMA index_list(wp_options)')['rows']);
    },
    'schema ddl reparse current next56 renames table and rewrites dependent schema sql' => static function (TestRunner $t) use ($baseRecords): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options RENAME TO wp_site_options'], 80);

        $t->same(81, $plan['after_schema_cookie']);
        $t->same('alter_table_rename', $plan['operations'][0]['kind']);
        $t->same('wp_options', $plan['operations'][0]['old_name']);
        $t->same('wp_site_options', $plan['operations'][0]['new_name']);
        $t->same(1, $plan['table_count']);
        $t->same(2, $plan['index_count']);

        $catalog = new SQLitePragmaSchemaCatalog($plan['records']);
        $t->same([], $catalog->execute('PRAGMA table_info(wp_options)')['rows']);
        $renamed = $catalog->execute('PRAGMA table_info(wp_site_options)');
        $t->same(4, count($renamed['rows']));
        $t->same('option_id', $renamed['rows'][0]['name']);
        $t->same('option_name', $renamed['rows'][1]['name']);
        $t->same('autoload', $renamed['rows'][3]['name']);
        $t->same('wp_site_options', $plan['records'][0]->name);
        $t->same('wp_site_options', $plan['records'][0]->tableName);
        $t->same('wp_site_options', $plan['records'][2]->tableName);
        $t->same('CREATE INDEX wp_options_autoload ON wp_site_options(autoload)', $plan['records'][2]->sql);
    },
    'schema ddl reparse current next56 creates new table with rowid and root page allocation' => static function (TestRunner $t) use ($baseRecords): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords(),
            ['CREATE TABLE wp_optionmeta(meta_id INTEGER PRIMARY KEY, option_id INTEGER NOT NULL, meta_key TEXT, meta_value TEXT)'],
            90,
        );

        $t->same(91, $plan['after_schema_cookie']);
        $t->same(2, $plan['table_count']);
        $t->same(2, $plan['index_count']);
        $t->same('create_table', $plan['operations'][0]['kind']);
        $t->same('wp_optionmeta', $plan['operations'][0]['name']);
        $t->same(5, $plan['operations'][0]['rootpage']);
        $t->same(4, $plan['operations'][0]['rowid']);

        $sample = $plan['pragma_samples']['table_xinfo:wp_optionmeta'];
        $t->same('table_xinfo', $sample['pragma']);
        $t->same(4, count($sample['rows']));
        $t->same('meta_id', $sample['rows'][0]['name']);
        $t->same(1, $sample['rows'][0]['pk']);
        $t->same('option_id', $sample['rows'][1]['name']);
        $t->same(1, $sample['rows'][1]['notnull']);
        $t->same('meta_value', $sample['rows'][3]['name']);
    },
    'schema ddl reparse current next56 applies mixed DDL batch with cookie per changed operation' => static function (TestRunner $t) use ($baseRecords): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords(),
            [
                'CREATE TABLE wp_optionmeta(meta_id INTEGER PRIMARY KEY, option_id INTEGER NOT NULL, meta_key TEXT)',
                'CREATE INDEX wp_optionmeta_option_id ON wp_optionmeta(option_id)',
                'DROP INDEX missing_index',
                'DROP INDEX wp_options_autoload',
            ],
            100,
        );

        $t->same(103, $plan['after_schema_cookie']);
        $t->same(4, count($plan['operations']));
        $t->same(true, $plan['operations'][0]['changed']);
        $t->same(true, $plan['operations'][1]['changed']);
        $t->same(false, $plan['operations'][2]['changed']);
        $t->same('missing_index', $plan['operations'][2]['name']);
        $t->same('missing_index', $plan['operations'][2]['reason']);
        $t->same(true, $plan['operations'][3]['changed']);
        $t->same(2, $plan['table_count']);
        $t->same(2, $plan['index_count']);

        $catalog = new SQLitePragmaSchemaCatalog($plan['records']);
        $t->same(3, count($catalog->execute('PRAGMA table_info(wp_optionmeta)')['rows']));
        $optionMetaIndexes = $catalog->execute('PRAGMA index_list(wp_optionmeta)');
        $t->same(1, count($optionMetaIndexes['rows']));
        $t->same('wp_optionmeta_option_id', $optionMetaIndexes['rows'][0]['name']);
        $t->same(0, $optionMetaIndexes['rows'][0]['unique']);
    },
    'schema ddl reparse current next56 no-op create existing objects keeps cookie stable' => static function (TestRunner $t) use ($baseRecords): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords(),
            [
                'CREATE TABLE IF NOT EXISTS wp_options(option_id INTEGER)',
                'CREATE INDEX IF NOT EXISTS wp_options_autoload ON wp_options(autoload)',
            ],
            110,
        );

        $t->same(110, $plan['after_schema_cookie']);
        $t->same(false, $plan['schema_changed']);
        $t->same(false, $plan['operations'][0]['changed']);
        $t->same('table_already_exists', $plan['operations'][0]['reason']);
        $t->same(false, $plan['operations'][1]['changed']);
        $t->same('index_already_exists', $plan['operations'][1]['reason']);
        $t->same([], $plan['invalidated_prepared']);
        $t->same(1, $plan['table_count']);
        $t->same(2, $plan['index_count']);
    },
    'schema ddl reparse current next56 rejects unsupported or unsafe DDL' => static function (TestRunner $t) use ($baseRecords): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['CREATE INDEX bad ON missing_table(option_name)']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE missing RENAME TO next_missing']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options RENAME TO wp_options']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['CREATE VIRTUAL TABLE wp_options_search USING fts5(option_name)']));
    },
];

return $tests;
