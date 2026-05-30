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

$makeCatalog = static fn (): SQLiteAttachedSchemaCatalog => new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
        $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
    ],
);

$loader = static function (string $file, string $schema) use ($record): array {
    return match ($schema) {
        'site' => [
            $record('table', 'wp_sitemeta', 'wp_sitemeta', 10, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
            $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 11, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
        ],
        'archive' => [
            $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE wp_options(option_name TEXT, archived_at TEXT)'),
            $record('table', 'wp_posts', 'wp_posts', 21, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)'),
        ],
        'plugin-cache' => [
            $record('table', 'wp_plugin_cache', 'wp_plugin_cache', 30, 'CREATE TABLE wp_plugin_cache(cache_key TEXT, cache_value TEXT)'),
        ],
        'quoted' => [
            $record('table', 'wp_quoted', 'wp_quoted', 40, 'CREATE TABLE wp_quoted(option_name TEXT)'),
        ],
        default => [
            $record('table', 'wp_loaded_' . str_replace('-', '_', $schema), 'wp_loaded_' . str_replace('-', '_', $schema), 50),
        ],
    };
};

$tests = [
    'attach detach schema current next18 attaches SQL database with loaded records' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $result = $catalog->executeAttachDetachSql("ATTACH DATABASE '/srv/site.sqlite' AS site", $loader);

        $t->same('ok', $result['status']);
        $t->same('attach', $result['operation']);
        $t->same('site', $result['schema']);
        $t->same('/srv/site.sqlite', $result['file']);
        $t->same('site', $catalog->resolveTable('site.wp_sitemeta')['schema']);
        $t->same(10, $catalog->resolveTable('site.wp_sitemeta')['record']->rootPage);
    },
    'attach detach schema current next18 database list includes SQL attached schema' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $result = $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);

        $t->same(3, count($result['database_list']));
        $t->same(['seq' => 2, 'name' => 'site', 'file' => '/srv/site.sqlite'], $result['database_list'][2]);
    },
    'attach detach schema current next18 unqualified lookup sees attached table after main temp miss' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);

        $t->same('site', $catalog->resolveTable('wp_sitemeta')['schema']);
        $t->same(10, $catalog->resolveTable('wp_sitemeta')['record']->rootPage);
    },
    'attach detach schema current next18 temp still shadows attached table after attach SQL' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);

        $t->same('temp', $catalog->resolveTable('wp_options')['schema']);
        $t->same(4, $catalog->resolveTable('wp_options')['record']->rootPage);
        $t->same(20, $catalog->resolveTable('archive.wp_options')['record']->rootPage);
    },
    'attach detach schema current next18 attach order controls unqualified attached conflicts' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/a.sqlite' AS first", static fn (): array => [$record('table', 'shared_posts', 'shared_posts', 12)]);
        $catalog->executeAttachDetachSql("ATTACH '/srv/b.sqlite' AS second", static fn (): array => [$record('table', 'shared_posts', 'shared_posts', 13)]);

        $t->same(['temp', 'main', 'first', 'second'], $catalog->searchOrder());
        $t->same('first', $catalog->resolveTable('shared_posts')['schema']);
        $t->same(12, $catalog->resolveTable('shared_posts')['record']->rootPage);
    },
    'attach detach schema current next18 detach SQL removes current attached winner' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/a.sqlite' AS first", static fn (): array => [$record('table', 'shared_posts', 'shared_posts', 12)]);
        $catalog->executeAttachDetachSql("ATTACH '/srv/b.sqlite' AS second", static fn (): array => [$record('table', 'shared_posts', 'shared_posts', 13)]);
        $result = $catalog->executeAttachDetachSql('DETACH DATABASE first');

        $t->same('detach', $result['operation']);
        $t->same('first', $result['schema']);
        $t->same(['temp', 'main', 'second'], $catalog->searchOrder());
        $t->same('second', $catalog->resolveTable('shared_posts')['schema']);
    },
    'attach detach schema current next18 detach SQL resequences pragma database list' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $catalog->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);
        $result = $catalog->executeAttachDetachSql('DETACH site');

        $t->same(3, count($result['database_list']));
        $t->same(['seq' => 2, 'name' => 'archive', 'file' => '/srv/archive.sqlite'], $result['database_list'][2]);
    },
    'attach detach schema current next18 pragma database list reflects SQL attach detach' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $catalog->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);
        $catalog->executeAttachDetachSql('DETACH archive');
        $pragma = $catalog->executeSchemaPragma('PRAGMA database_list');

        $t->same('database_list', $pragma['pragma']);
        $t->same(3, count($pragma['rows']));
        $t->same('site', $pragma['rows'][2]['name']);
    },
    'attach detach schema current next18 schema qualified pragma uses attached current record' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $result = $catalog->executeSchemaPragma('PRAGMA site.table_info(wp_sitemeta)');

        $t->same('site', $result['schema']);
        $t->same('wp_sitemeta', $result['target']);
        $t->same('meta_key', $result['rows'][0]['name']);
    },
    'attach detach schema current next18 unqualified pragma resolves attached current table' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $result = $catalog->executeSchemaPragma('PRAGMA table_info(wp_sitemeta)');

        $t->same('site', $result['schema']);
        $t->same(2, count($result['rows']));
    },
    'attach detach schema current next18 unqualified index pragma resolves attached current index' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $result = $catalog->executeSchemaPragma('PRAGMA index_info(wp_sitemeta_key)');

        $t->same('site', $result['schema']);
        $t->same('meta_key', $result['rows'][0]['name']);
    },
    'attach detach schema current next18 detach removes attached pragma current source' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $catalog->executeAttachDetachSql('DETACH site');
        $result = $catalog->executeSchemaPragma('PRAGMA table_info(wp_sitemeta)');

        $t->same('main', $result['schema']);
        $t->same([], $result['rows']);
    },
    'attach detach schema current next18 double quoted schema identifier is normalized' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/plugin.sqlite' AS \"Plugin-Cache\"", $loader);

        $t->same('plugin-cache', $catalog->resolveTable('"plugin-cache".wp_plugin_cache')['schema']);
        $t->same(30, $catalog->resolveTable('plugin-cache.wp_plugin_cache')['record']->rootPage);
    },
    'attach detach schema current next18 bracket quoted schema identifier is normalized' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/plugin.sqlite' AS [Plugin-Cache]", $loader);

        $t->same('plugin-cache', $catalog->databaseList()[2]['name']);
        $t->same('plugin-cache', $catalog->resolveTable('[plugin-cache].wp_plugin_cache')['schema']);
    },
    'attach detach schema current next18 backtick quoted detach identifier removes schema' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/plugin.sqlite' AS `Plugin-Cache`", $loader);
        $catalog->executeAttachDetachSql('DETACH `Plugin-Cache`');

        $t->same(['temp', 'main'], $catalog->searchOrder());
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->schemaRecords('plugin-cache'));
    },
    'attach detach schema current next18 single quoted file expression unescapes apostrophes' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $result = $catalog->executeAttachDetachSql("ATTACH '/srv/wp/site''s.sqlite' AS quoted");

        $t->same("/srv/wp/site's.sqlite", $result['file']);
        $t->same("/srv/wp/site's.sqlite", $catalog->databaseList()[2]['file']);
    },
    'attach detach schema current next18 single quoted file expression keeps embedded as token' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $result = $catalog->executeAttachDetachSql("ATTACH '/srv/wp/as archive/site.sqlite' AS archive");

        $t->same('/srv/wp/as archive/site.sqlite', $result['file']);
        $t->same('archive', $result['schema']);
    },
    'attach detach schema current next18 double quoted file expression unescapes quotes' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $result = $catalog->executeAttachDetachSql('ATTACH "/srv/wp/site""quoted.sqlite" AS quoted');

        $t->same('/srv/wp/site"quoted.sqlite', $result['file']);
        $t->same('/srv/wp/site"quoted.sqlite', $catalog->databaseList()[2]['file']);
    },
    'attach detach schema current next18 double quoted file expression keeps embedded as token' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $result = $catalog->executeAttachDetachSql('ATTACH "/srv/wp/as archive/site.sqlite" AS archive');

        $t->same('/srv/wp/as archive/site.sqlite', $result['file']);
        $t->same('archive', $result['schema']);
    },
    'attach detach schema current next18 bare bounded path token attaches' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $result = $catalog->executeAttachDetachSql('ATTACH /srv/wp/cache-01.sqlite AS cache01');

        $t->same('/srv/wp/cache-01.sqlite', $result['file']);
        $t->same('cache01', $result['schema']);
    },
    'attach detach schema current next18 loader receives normalized file and schema' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $seen = [];
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS \"Site\"", static function (string $file, string $schema) use (&$seen, $record): array {
            $seen = [$file, $schema];

            return [$record('table', 'wp_seen', 'wp_seen', 60)];
        });

        $t->same(['/srv/site.sqlite', 'site'], $seen);
        $t->same(60, $catalog->resolveTable('site.wp_seen')['record']->rootPage);
    },
    'attach detach schema current next18 default attach has empty schema records' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/empty.sqlite' AS emptydb");

        $t->same([], $catalog->schemaRecords('emptydb'));
        $t->same(null, $catalog->resolveTable('emptydb.wp_options'));
    },
    'attach detach schema current next18 attached main table lookup remains explicit after SQL attach' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", $loader);

        $t->same(2, $catalog->resolveTable('main.wp_options')['record']->rootPage);
        $t->same(4, $catalog->resolveTable('temp.wp_options')['record']->rootPage);
        $t->same(20, $catalog->resolveTable('archive.wp_options')['record']->rootPage);
    },
    'attach detach schema current next18 attached index lookup survives temp table shadowing' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);

        $t->same('site', $catalog->resolveIndex('wp_sitemeta_key')['schema']);
        $t->same(11, $catalog->resolveIndex('site.wp_sitemeta_key')['record']->rootPage);
    },
    'attach detach schema current next18 sqlite schema alias remains main after SQL attach' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);

        $t->same('main', $catalog->resolveTable('sqlite_schema')['schema']);
        $t->same('site', $catalog->resolveTable('site.sqlite_schema')['schema']);
    },
    'attach detach schema current next18 sqlite temp schema alias remains temp after SQL attach' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);

        $t->same('temp', $catalog->resolveTable('sqlite_temp_schema')['schema']);
        $t->same('temp', $catalog->resolveTable('temp.sqlite_master')['schema']);
    },
    'attach detach schema current next18 detach preserves main and temp database list heads' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $catalog->executeAttachDetachSql('DETACH site');

        $t->same([['seq' => 0, 'name' => 'main', 'file' => null], ['seq' => 1, 'name' => 'temp', 'file' => '']], $catalog->databaseList());
    },
    'attach detach schema current next18 reattach schema after detach uses new file' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site-a.sqlite' AS site", $loader);
        $catalog->executeAttachDetachSql('DETACH site');
        $catalog->executeAttachDetachSql("ATTACH '/srv/site-b.sqlite' AS site", $loader);

        $t->same('/srv/site-b.sqlite', $catalog->databaseList()[2]['file']);
        $t->same(10, $catalog->resolveTable('site.wp_sitemeta')['record']->rootPage);
    },
    'attach detach schema current next18 duplicate SQL attach raises' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site");

        $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeAttachDetachSql("ATTACH '/srv/site2.sqlite' AS site"));
    },
    'attach detach schema current next18 SQL attach rejects main schema' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql("ATTACH '/srv/main.sqlite' AS main"));
    },
    'attach detach schema current next18 SQL attach rejects temp schema' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql("ATTACH '/srv/temp.sqlite' AS temp"));
    },
    'attach detach schema current next18 SQL detach rejects main schema' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql('DETACH main'));
    },
    'attach detach schema current next18 SQL detach rejects temp schema' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql('DETACH DATABASE temp'));
    },
    'attach detach schema current next18 SQL detach rejects missing schema' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql('DETACH missing'));
    },
    'attach detach schema current next18 SQL attach rejects empty schema name' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS ''"));
    },
    'attach detach schema current next18 SQL attach rejects empty file expression' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql("ATTACH '' AS site"));
    },
    'attach detach schema current next18 SQL attach rejects unbounded file expression' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql('ATTACH concat("/srv/", "site.sqlite") AS site'));
    },
    'attach detach schema current next18 SQL executor rejects non attach detach statement' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql('PRAGMA database_list'));
    },
    'attach detach schema current next18 SQL attach rejects missing as separator' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql("ATTACH '/srv/site.sqlite' site"));
    },
    'attach detach schema current next18 SQL attach rejects trailing schema tokens' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site trailing"));
    },
    'attach detach schema current next18 SQL attach accepts trailing semicolon whitespace' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $result = $catalog->executeAttachDetachSql(" ATTACH DATABASE '/srv/site.sqlite' AS site ; \n", $loader);

        $t->same('site', $result['schema']);
        $t->same('site', $catalog->resolveTable('wp_sitemeta')['schema']);
    },
    'attach detach schema current next18 SQL detach accepts trailing semicolon whitespace' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", $loader);
        $result = $catalog->executeAttachDetachSql(" DETACH DATABASE site ; \n");

        $t->same('detach', $result['operation']);
        $t->same(['temp', 'main'], $catalog->searchOrder());
    },
    'attach detach schema current next18 attach SQL preserves case folded schema in list' => static function (TestRunner $t) use ($makeCatalog, $loader): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS Site", $loader);

        $t->same('site', $catalog->databaseList()[2]['name']);
        $t->same('site', $catalog->resolveTable('SITE.wp_sitemeta')['schema']);
    },
    'attach detach schema current next18 detach current source falls through to later attachment' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();
        $catalog->executeAttachDetachSql("ATTACH '/srv/site.sqlite' AS site", static fn (): array => [$record('table', 'network_options', 'network_options', 70)]);
        $catalog->executeAttachDetachSql("ATTACH '/srv/archive.sqlite' AS archive", static fn (): array => [$record('table', 'network_options', 'network_options', 71)]);
        $catalog->executeAttachDetachSql('DETACH site');

        $t->same('archive', $catalog->resolveTable('network_options')['schema']);
        $t->same(71, $catalog->resolveTable('network_options')['record']->rootPage);
    },
];

return $tests;
