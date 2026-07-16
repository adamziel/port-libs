<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
            $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)'),
        ],
        [
            $record('table', 'wp_temp_options', 'wp_temp_options', 4, 'CREATE TEMP TABLE wp_temp_options(option_name TEXT)'),
        ],
    );
};

$siteRecords = static fn (int $base) => [
    $record('table', 'wp_options', 'wp_options', $base, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
    $record('index', 'wp_options_name', 'wp_options', $base + 1, 'CREATE INDEX wp_options_name ON wp_options(option_name)'),
    $record('table', 'wp_sitemeta', 'wp_sitemeta', $base + 2, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
];

$tests = [
    'attach schema cache invalidation current next34 starts empty at generation zero' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();

        $t->same(0, $catalog->schemaGeneration());
        $t->same(['generation' => 0, 'entries' => 0, 'hits' => 0, 'misses' => 0], $catalog->lookupCacheStats());
    },
    'attach schema cache invalidation current next34 table lookup populates cache miss' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();

        $t->same('main', $catalog->resolveTable('wp_options')['schema']);
        $t->same(1, $catalog->lookupCacheStats()['entries']);
        $t->same(1, $catalog->lookupCacheStats()['misses']);
    },
    'attach schema cache invalidation current next34 repeated table lookup is cache hit' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $catalog->resolveTable('wp_options');
        $catalog->resolveTable('wp_options');

        $t->same(1, $catalog->lookupCacheStats()['hits']);
        $t->same(1, $catalog->lookupCacheStats()['misses']);
    },
    'attach schema cache invalidation current next34 index lookup has independent cache entry' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $catalog->resolveTable('wp_options');
        $catalog->resolveIndex('wp_options_name');

        $t->same(2, $catalog->lookupCacheStats()['entries']);
        $t->same('main', $catalog->resolveIndex('wp_options_name')['schema']);
    },
    'attach schema cache invalidation current next34 missing lookup is cached as null' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();

        $t->same(null, $catalog->resolveTable('wp_sitemeta'));
        $t->same(null, $catalog->resolveTable('wp_sitemeta'));
        $t->same(1, $catalog->lookupCacheStats()['hits']);
        $t->same(1, $catalog->lookupCacheStats()['misses']);
    },
    'attach schema cache invalidation current next34 attach clears stale missing table result' => static function (TestRunner $t) use ($makeCatalog, $siteRecords): void {
        $catalog = $makeCatalog();
        $catalog->resolveTable('wp_sitemeta');
        $catalog->attach('site', '/srv/site.sqlite', $siteRecords(20));

        $t->same(1, $catalog->schemaGeneration());
        $t->same(0, $catalog->lookupCacheStats()['entries']);
        $t->same('site', $catalog->resolveTable('wp_sitemeta')['schema']);
        $t->same(22, $catalog->resolveTable('wp_sitemeta')['record']->rootPage);
    },
    'attach schema cache invalidation current next34 attach clears stale missing index result' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->resolveIndex('wp_sitemeta_key');
        $catalog->attach('site', '/srv/site.sqlite', [
            $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 31, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
        ]);

        $t->same('site', $catalog->resolveIndex('wp_sitemeta_key')['schema']);
        $t->same(31, $catalog->resolveIndex('wp_sitemeta_key')['record']->rootPage);
    },
    'attach schema cache invalidation current next34 detach clears stale attached table result' => static function (TestRunner $t) use ($makeCatalog, $siteRecords): void {
        $catalog = $makeCatalog();
        $catalog->attach('site', '/srv/site.sqlite', $siteRecords(40));
        $catalog->resolveTable('wp_sitemeta');
        $catalog->detach('site');

        $t->same(2, $catalog->schemaGeneration());
        $t->same(null, $catalog->resolveTable('wp_sitemeta'));
    },
    'attach schema cache invalidation current next34 detach falls through to later attached winner' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->attach('site', '/srv/site.sqlite', [$record('table', 'network_options', 'network_options', 50)]);
        $catalog->attach('archive', '/srv/archive.sqlite', [$record('table', 'network_options', 'network_options', 60)]);
        $catalog->resolveTable('network_options');
        $catalog->detach('site');

        $t->same('archive', $catalog->resolveTable('network_options')['schema']);
        $t->same(60, $catalog->resolveTable('network_options')['record']->rootPage);
    },
    'attach schema cache invalidation current next34 reattach same schema clears old file records' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->attach('site', '/srv/site-a.sqlite', [$record('table', 'wp_sitemeta', 'wp_sitemeta', 70)]);
        $catalog->resolveTable('wp_sitemeta');
        $catalog->detach('site');
        $catalog->attach('site', '/srv/site-b.sqlite', [$record('table', 'wp_sitemeta', 'wp_sitemeta', 80)]);

        $t->same('/srv/site-b.sqlite', $catalog->databaseList()[2]['file']);
        $t->same(80, $catalog->resolveTable('wp_sitemeta')['record']->rootPage);
    },
    'attach schema cache invalidation current next34 replace main records clears cached main table' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->resolveTable('wp_options');
        $catalog->replaceSchemaRecords('main', [$record('table', 'wp_options', 'wp_options', 90)]);

        $t->same(1, $catalog->schemaGeneration());
        $t->same(90, $catalog->resolveTable('wp_options')['record']->rootPage);
    },
    'attach schema cache invalidation current next34 replace temp records clears cached missing temp table' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->resolveTable('wp_plugin_cache');
        $catalog->replaceSchemaRecords('temp', [$record('table', 'wp_plugin_cache', 'wp_plugin_cache', 91)]);

        $t->same('temp', $catalog->resolveTable('wp_plugin_cache')['schema']);
        $t->same(91, $catalog->resolveTable('wp_plugin_cache')['record']->rootPage);
    },
    'attach schema cache invalidation current next34 replace attached records clears cached attached table' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->attach('site', '/srv/site.sqlite', [$record('table', 'wp_sitemeta', 'wp_sitemeta', 92)]);
        $catalog->resolveTable('wp_sitemeta');
        $catalog->replaceSchemaRecords('site', [$record('table', 'wp_sitemeta', 'wp_sitemeta', 93)]);

        $t->same(2, $catalog->schemaGeneration());
        $t->same(93, $catalog->resolveTable('wp_sitemeta')['record']->rootPage);
    },
    'attach schema cache invalidation current next34 replace attached records clears cached index' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->attach('site', '/srv/site.sqlite', [$record('index', 'wp_sitemeta_key', 'wp_sitemeta', 94)]);
        $catalog->resolveIndex('wp_sitemeta_key');
        $catalog->replaceSchemaRecords('site', [$record('index', 'wp_sitemeta_key', 'wp_sitemeta', 95)]);

        $t->same(95, $catalog->resolveIndex('wp_sitemeta_key')['record']->rootPage);
    },
    'attach schema cache invalidation current next34 qualified lookup cache is invalidated by detach' => static function (TestRunner $t) use ($makeCatalog, $siteRecords): void {
        $catalog = $makeCatalog();
        $catalog->attach('site', '/srv/site.sqlite', $siteRecords(100));
        $catalog->resolveTable('site.wp_sitemeta');
        $catalog->detach('site');

        $t->throws(InvalidArgumentException::class, static fn () => $catalog->resolveTable('site.wp_sitemeta'));
    },
    'attach schema cache invalidation current next34 schema table aliases bypass ordinary cache' => static function (TestRunner $t) use ($makeCatalog, $siteRecords): void {
        $catalog = $makeCatalog();
        $catalog->attach('site', '/srv/site.sqlite', $siteRecords(110));

        $t->same('main', $catalog->resolveTable('sqlite_schema')['schema']);
        $t->same('site', $catalog->resolveTable('site.sqlite_schema')['schema']);
        $t->same(0, $catalog->lookupCacheStats()['entries']);
    },
    'attach schema cache invalidation current next34 pragma current-source sees newly attached table after cached miss' => static function (TestRunner $t) use ($makeCatalog, $siteRecords): void {
        $catalog = $makeCatalog();
        $catalog->executeSchemaPragma('PRAGMA table_info(wp_sitemeta)');
        $catalog->attach('site', '/srv/site.sqlite', $siteRecords(120));
        $result = $catalog->executeSchemaPragma('PRAGMA table_info(wp_sitemeta)');

        $t->same('site', $result['schema']);
        $t->same('meta_key', $result['rows'][0]['name']);
    },
    'attach schema cache invalidation current next34 table-valued pragma sees replacement records' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->executeTableValuedPragma("pragma_table_info('wp_options')");
        $catalog->replaceSchemaRecords('main', [$record('table', 'wp_options', 'wp_options', 130, 'CREATE TABLE wp_options(option_name TEXT, autoload TEXT)')]);
        $result = $catalog->executeTableValuedPragma("pragma_table_info('wp_options')");

        $t->same('main', $result['schema']);
        $t->same('autoload', $result['rows'][1]['name']);
    },
    'attach schema cache invalidation current next34 attach SQL loader result invalidates cached miss' => static function (TestRunner $t) use ($makeCatalog, $siteRecords): void {
        $catalog = $makeCatalog();
        $catalog->resolveTable('wp_sitemeta');
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", static fn () => $siteRecords(140));

        $t->same('site', $catalog->resolveTable('wp_sitemeta')['schema']);
        $t->same(142, $catalog->resolveTable('wp_sitemeta')['record']->rootPage);
    },
    'attach schema cache invalidation current next34 detach SQL invalidates cached attached result' => static function (TestRunner $t) use ($makeCatalog, $siteRecords): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", static fn () => $siteRecords(150));
        $catalog->resolveTable('wp_sitemeta');
        $catalog->executeAttachDetachSql('DETACH site');

        $t->same(null, $catalog->resolveTable('wp_sitemeta'));
        $t->same(['temp', 'main'], $catalog->searchOrder());
    },
    'attach schema cache invalidation current next34 cached table and index entries clear together' => static function (TestRunner $t) use ($makeCatalog, $siteRecords): void {
        $catalog = $makeCatalog();
        $catalog->resolveTable('wp_options');
        $catalog->resolveIndex('wp_options_name');
        $catalog->attach('site', '/srv/site.sqlite', $siteRecords(160));

        $t->same(0, $catalog->lookupCacheStats()['entries']);
        $t->same('main', $catalog->resolveTable('wp_options')['schema']);
    },
];

foreach (range(1, 24) as $number) {
    $tests[sprintf('attach schema cache invalidation current next34 repeated attach detach cycle %02d', $number)] = static function (TestRunner $t) use ($makeCatalog, $record, $number): void {
        $catalog = $makeCatalog();
        $catalog->resolveTable('cycle_table');
        $catalog->attach('cycle', '/srv/cycle-' . $number . '.sqlite', [$record('table', 'cycle_table', 'cycle_table', 200 + $number)]);
        $attached = $catalog->resolveTable('cycle_table');
        $catalog->detach('cycle');

        $t->same('cycle', $attached['schema']);
        $t->same(200 + $number, $attached['record']->rootPage);
        $t->same(null, $catalog->resolveTable('cycle_table'));
        $t->same(2, $catalog->schemaGeneration());
    };
}

return $tests;
