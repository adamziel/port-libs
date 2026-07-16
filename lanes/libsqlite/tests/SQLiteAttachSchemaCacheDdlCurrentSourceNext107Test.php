<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record107 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog107 = static function () use ($record107): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record107('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 1),
            $record107('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)', 2),
            $record107('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)', 3),
        ],
        [
            $record107('table', 'wp_temp_options', 'wp_temp_options', 2, 'CREATE TABLE wp_temp_options(option_name TEXT)', 1),
        ],
    );
    $catalog->attach('site', '/srv/wp-content/site.sqlite', [
        $record107('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, autoload TEXT)', 1),
        $record107('index', 'site_options_name', 'wp_options', 9, 'CREATE INDEX site_options_name ON wp_options(option_name)', 2),
        $record107('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, "CREATE VIEW wp_autoloaded_options AS SELECT option_name FROM wp_options WHERE autoload = 'yes'", 3),
    ]);
    $catalog->attach('archive', '/srv/wp-content/archive.sqlite', [
        $record107('table', 'wp_archive_options', 'wp_archive_options', 12, 'CREATE TABLE wp_archive_options(option_name TEXT)', 1),
    ]);

    return $catalog;
};

$tests = [
    'attach schema cache ddl current source next107 attached create shadows prepared miss' => static function (TestRunner $t) use ($catalog107): void {
        $catalog = $catalog107();
        $snapshot = $catalog->schemaCacheResolutionSnapshot(['wp_new_options', 'wp_options'], ['site_new_name'], 'site');

        $plan = $catalog->applySchemaDdlCurrentSource(
            'site',
            [
                'CREATE TABLE wp_new_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)',
                'CREATE INDEX site_new_name ON wp_new_options(option_name)',
            ],
            107,
            $snapshot,
            [
                ['id' => 'site-missing-options', 'schema_cookie' => 107, 'sql' => 'SELECT * FROM wp_new_options'],
                ['id' => 'already-current', 'schema_cookie' => 109, 'sql' => 'SELECT * FROM site.wp_new_options'],
            ],
        );

        $t->same('schema_cache_expired', $plan['status']);
        $t->same('attach-schema-cache-ddl-current-source', $plan['operation']);
        $t->same('site', $plan['schema']);
        $t->same(2, $plan['before_generation']);
        $t->same(3, $plan['after_generation']);
        $t->same(true, $plan['cache_invalidated']);
        $t->same(107, $plan['ddl_plan']['before_schema_cookie']);
        $t->same(109, $plan['ddl_plan']['after_schema_cookie']);
        $t->same(true, $plan['ddl_plan']['schema_changed']);
        $t->same(['site-missing-options'], $plan['ddl_plan']['invalidated_prepared']);
        $t->same('create_table', $plan['ddl_plan']['operations'][0]['kind']);
        $t->same('wp_new_options', $plan['ddl_plan']['operations'][0]['name']);
        $t->same(10, $plan['ddl_plan']['operations'][0]['rootpage']);
        $t->same(4, $plan['ddl_plan']['operations'][0]['rowid']);
        $t->same('create_index', $plan['ddl_plan']['operations'][1]['kind']);
        $t->same('site_new_name', $plan['ddl_plan']['operations'][1]['name']);
        $t->same('wp_new_options', $plan['ddl_plan']['operations'][1]['table']);
        $t->same(11, $plan['ddl_plan']['operations'][1]['rootpage']);
        $t->same(5, $plan['ddl_plan']['operations'][1]['rowid']);
        $t->same(2, $plan['ddl_plan']['table_count']);
        $t->same(2, $plan['ddl_plan']['index_count']);

        $invalid = $plan['invalidation'];
        $t->same(false, $invalid['current']);
        $t->same(true, $invalid['stale']);
        $t->same([], $invalid['added_schemas']);
        $t->same([], $invalid['removed_schemas']);
        $t->same(['wp_new_options'], $invalid['changed_tables']);
        $t->same(['wp_options'], $invalid['unchanged_tables']);
        $t->same(['site_new_name'], $invalid['changed_indexes']);
        $t->same(null, $invalid['table_changes']['wp_new_options']['before']['schema']);
        $t->same('site', $invalid['table_changes']['wp_new_options']['after']['schema']);
        $t->same('wp_new_options', $invalid['table_changes']['wp_new_options']['after']['name']);
        $t->same(10, $invalid['table_changes']['wp_new_options']['after']['rootpage']);
        $t->same('table', $invalid['table_changes']['wp_new_options']['after']['type']);
        $t->same('site', $invalid['index_changes']['site_new_name']['after']['schema']);
        $t->same(11, $invalid['index_changes']['site_new_name']['after']['rootpage']);
        $t->same('index', $invalid['index_changes']['site_new_name']['after']['type']);
        $t->same(4, count($plan['database_list']));
        $t->same('site', $plan['database_list'][2]['name']);
        $t->same(true, in_array('sqlite-attach-schema-cache-ddl-current-source', $plan['dependencies'], true));

        $t->same('site', $catalog->resolveTable('site.wp_new_options')['schema']);
        $t->same('site_new_name', $catalog->resolveIndex('site_new_name')['record']->name);
        $t->same(3, $catalog->lookupCacheStats()['generation']);
    },
    'attach schema cache ddl current source next107 temp drop exposes main winner' => static function (TestRunner $t) use ($catalog107): void {
        $catalog = $catalog107();
        $catalog->replaceSchemaRecords('temp', [
            new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT)', 1),
            new SQLiteSchemaRecord('index', 'temp_options_name', 'wp_options', 3, 'CREATE INDEX temp_options_name ON wp_options(option_name)', 2),
        ]);
        $snapshot = $catalog->schemaCacheResolutionSnapshot(['wp_options'], ['temp_options_name'], 'temp');

        $plan = $catalog->applySchemaDdlCurrentSource('temp', ['DROP TABLE wp_options'], 44, $snapshot);

        $t->same('schema_cache_expired', $plan['status']);
        $t->same('temp', $plan['schema']);
        $t->same(3, $plan['before_generation']);
        $t->same(4, $plan['after_generation']);
        $t->same(45, $plan['ddl_plan']['after_schema_cookie']);
        $t->same('drop_table', $plan['ddl_plan']['operations'][0]['kind']);
        $t->same(['table:wp_options', 'index:temp_options_name'], $plan['ddl_plan']['operations'][0]['removed_records']);
        $t->same([2, 3], $plan['ddl_plan']['operations'][0]['freed_rootpages']);
        $t->same(0, $plan['ddl_plan']['table_count']);
        $t->same(0, $plan['ddl_plan']['index_count']);
        $t->same(['wp_options'], $plan['invalidation']['changed_tables']);
        $t->same(['temp_options_name'], $plan['invalidation']['changed_indexes']);
        $t->same('temp', $plan['invalidation']['table_changes']['wp_options']['before']['schema']);
        $t->same('main', $plan['invalidation']['table_changes']['wp_options']['after']['schema']);
        $t->same(2, $plan['invalidation']['table_changes']['wp_options']['after']['rootpage']);
        $t->same('temp', $plan['invalidation']['index_changes']['temp_options_name']['before']['schema']);
        $t->same(null, $plan['invalidation']['index_changes']['temp_options_name']['after']['schema']);
        $t->same('main', $catalog->resolveTable('wp_options')['schema']);
        $t->same(null, $catalog->resolveIndex('temp_options_name'));
    },
    'attach schema cache ddl current source next107 no-op keeps generation stable' => static function (TestRunner $t) use ($catalog107): void {
        $catalog = $catalog107();
        $snapshot = $catalog->schemaCacheResolutionSnapshot(['wp_options'], ['site_options_name'], 'site');
        $plan = $catalog->applySchemaDdlCurrentSource(
            'site',
            [
                'CREATE TABLE IF NOT EXISTS wp_options(blog_id INTEGER)',
                'CREATE INDEX IF NOT EXISTS site_options_name ON wp_options(option_name)',
                'DROP VIEW IF EXISTS missing_view',
            ],
            64,
            $snapshot,
        );

        $t->same('schema_cache_stable', $plan['status']);
        $t->same(2, $plan['before_generation']);
        $t->same(2, $plan['after_generation']);
        $t->same(false, $plan['cache_invalidated']);
        $t->same(64, $plan['ddl_plan']['after_schema_cookie']);
        $t->same(false, $plan['ddl_plan']['schema_changed']);
        $t->same('table_already_exists', $plan['ddl_plan']['operations'][0]['reason']);
        $t->same('index_already_exists', $plan['ddl_plan']['operations'][1]['reason']);
        $t->same('missing_view', $plan['ddl_plan']['operations'][2]['reason']);
        $t->same(true, $plan['invalidation']['current']);
        $t->same(false, $plan['invalidation']['stale']);
        $t->same([], $plan['invalidation']['changed_tables']);
        $t->same(['wp_options'], $plan['invalidation']['unchanged_tables']);
        $t->same([], $plan['invalidation']['changed_indexes']);
        $t->same(['site_options_name'], $plan['invalidation']['unchanged_indexes']);
    },
    'attach schema cache ddl current source next107 rejects missing schema and unsafe ddl' => static function (TestRunner $t) use ($catalog107): void {
        $catalog = $catalog107();

        $t->throws(InvalidArgumentException::class, static fn () => $catalog->applySchemaDdlCurrentSource('missing', ['CREATE TABLE nope(id INTEGER)'], 1));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->applySchemaDdlCurrentSource('site', ['CREATE INDEX bad ON missing_table(option_name)'], 1));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->applySchemaDdlCurrentSource('site', ['CREATE TRIGGER bad AFTER INSERT ON main.wp_options BEGIN SELECT 1; END'], 1));
    },
];

return $tests;
