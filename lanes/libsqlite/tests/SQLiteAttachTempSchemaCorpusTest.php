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
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)'),
            $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)'),
            $record('view', 'wp_active_options', 'wp_active_options', null, "CREATE VIEW wp_active_options AS SELECT * FROM wp_options WHERE autoload = 'yes'"),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
            $record('index', 'wp_options_temp_name', 'wp_options', 5, 'CREATE INDEX wp_options_temp_name ON wp_options(option_name)'),
        ],
    );
    $catalog->attach('site', '/srv/www/site-meta.sqlite', [
        $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT)'),
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 7, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
        $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 8, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
    ]);
    $catalog->attach('archive', '/srv/www/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_name TEXT, archived_at TEXT)'),
        $record('table', 'wp_posts', 'wp_posts', 10, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)'),
        $record('index', 'wp_posts_title', 'wp_posts', 11, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)'),
    ]);

    return $catalog;
};

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        if (is_array($value)) {
            $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
            continue;
        }
        $value = $value->{$part};
    }

    return $value;
};

$tests = [
    'attach temp schema corpus resolves temp before main and attached databases' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $table = $catalog->resolveTable('wp_options');
        $index = $catalog->resolveIndex('wp_options_name');
        $tempIndex = $catalog->resolveIndex('wp_options_temp_name');

        $t->same('temp', $table['schema']);
        $t->same(4, $table['record']->rootPage);
        $t->same('main', $index['schema']);
        $t->same(3, $index['record']->rootPage);
        $t->same('temp', $tempIndex['schema']);
        $t->same(5, $tempIndex['record']->rootPage);
    },
    'attach temp schema corpus explicit schema bypasses temp shadowing' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();

        $t->same(2, $catalog->resolveTable('main.wp_options')['record']->rootPage);
        $t->same(4, $catalog->resolveTable('temp.wp_options')['record']->rootPage);
        $t->same(6, $catalog->resolveTable('site.wp_options')['record']->rootPage);
        $t->same(9, $catalog->resolveTable('archive.wp_options')['record']->rootPage);
        $t->same('site', $catalog->resolveTable('site.wp_sitemeta')['schema']);
        $t->same('archive', $catalog->resolveTable('archive.wp_posts')['schema']);
    },
    'attach temp schema corpus unqualified attached names follow attach order' => static function (TestRunner $t) use ($record): void {
        $catalog = new SQLiteAttachedSchemaCatalog([$record('table', 'main_only', 'main_only', 2)]);
        $catalog->attach('first', '/tmp/first.sqlite', [$record('table', 'shared_options', 'shared_options', 3)]);
        $catalog->attach('second', '/tmp/second.sqlite', [$record('table', 'shared_options', 'shared_options', 4)]);

        $t->same(['temp', 'main', 'first', 'second'], $catalog->searchOrder());
        $t->same('first', $catalog->resolveTable('shared_options')['schema']);
        $t->same(3, $catalog->resolveTable('shared_options')['record']->rootPage);

        $catalog->detach('first');
        $t->same(['temp', 'main', 'second'], $catalog->searchOrder());
        $t->same('second', $catalog->resolveTable('shared_options')['schema']);
        $t->same(4, $catalog->resolveTable('shared_options')['record']->rootPage);
    },
    'attach temp schema corpus database list preserves sqlite sequence rows' => static function (TestRunner $t) use ($makeCatalog): void {
        $rows = $makeCatalog()->databaseList();

        $t->same(4, count($rows));
        $t->same(['seq' => 0, 'name' => 'main', 'file' => null], $rows[0]);
        $t->same(['seq' => 1, 'name' => 'temp', 'file' => ''], $rows[1]);
        $t->same(['seq' => 2, 'name' => 'site', 'file' => '/srv/www/site-meta.sqlite'], $rows[2]);
        $t->same(['seq' => 3, 'name' => 'archive', 'file' => '/srv/www/archive.sqlite'], $rows[3]);
    },
    'attach temp schema corpus detach resequences attached database list' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();
        $catalog->detach('site');
        $rows = $catalog->databaseList();

        $t->same(3, count($rows));
        $t->same(['seq' => 0, 'name' => 'main', 'file' => null], $rows[0]);
        $t->same(['seq' => 1, 'name' => 'temp', 'file' => ''], $rows[1]);
        $t->same(['seq' => 2, 'name' => 'archive', 'file' => '/srv/www/archive.sqlite'], $rows[2]);
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->resolveTable('site.wp_sitemeta'));
    },
    'attach temp schema corpus wraps schema pragma catalogs per database' => static function (TestRunner $t) use ($makeCatalog): void {
        $catalog = $makeCatalog();

        $tempInfo = $catalog->pragmaCatalog('temp')->execute('PRAGMA table_info(wp_options)');
        $mainInfo = $catalog->pragmaCatalog('main')->execute('PRAGMA table_info(wp_options)');
        $siteIndexes = $catalog->pragmaCatalog('site')->execute('PRAGMA index_list(wp_sitemeta)');
        $archiveIndex = $catalog->pragmaCatalog('archive')->execute('PRAGMA index_info(wp_posts_title)');

        $t->same(2, count($tempInfo['rows']));
        $t->same('option_value', $tempInfo['rows'][1]['name']);
        $t->same(3, count($mainInfo['rows']));
        $t->same('autoload', $mainInfo['rows'][2]['name']);
        $t->same('wp_sitemeta_key', $siteIndexes['rows'][0]['name']);
        $t->same('post_title', $archiveIndex['rows'][0]['name']);
    },
    'attach temp schema corpus validates attach and detach schema names' => static function (TestRunner $t) use ($makeCatalog, $record): void {
        $catalog = $makeCatalog();

        $t->throws(InvalidArgumentException::class, static fn () => $catalog->attach('main', '/tmp/main.sqlite', []));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->attach('temp', '/tmp/temp.sqlite', []));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->attach('site', '/tmp/site2.sqlite', []));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->detach('main'));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->detach('missing'));

        $catalog->attach('"PluginCache"', '/tmp/plugin-cache.sqlite', [$record('table', 'wp_plugin_cache', 'wp_plugin_cache', 12)]);
        $t->same('plugincache', $catalog->resolveTable('plugincache.wp_plugin_cache')['schema']);
    },
];

foreach ([
    'unqualified view resolves from main when temp lacks view' => ['wp_active_options', 'schema', 'main'],
    'main view root page remains null' => ['wp_active_options', 'record.rootPage', null],
    'site table schema-qualified root' => ['site.wp_sitemeta', 'record.rootPage', 7],
    'archive table schema-qualified root' => ['archive.wp_posts', 'record.rootPage', 10],
    'bracket quoted schema name resolves' => ['[site].wp_sitemeta', 'schema', 'site'],
    'backtick quoted table name resolves' => ['archive.`wp_posts`', 'record.name', 'wp_posts'],
    'double quoted table name resolves' => ['main."wp_options"', 'record.rootPage', 2],
] as $name => [$objectName, $path, $expected]) {
    $tests['attach temp schema corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $objectName, $path, $expected): void {
        $t->same($expected, $valueAt($makeCatalog()->resolveTable($objectName), $path));
    };
}

foreach ([
    'main index explicit root' => ['main.wp_options_name', 'schema', 'main'],
    'temp index explicit root' => ['temp.wp_options_temp_name', 'record.rootPage', 5],
    'site index explicit root' => ['site.wp_sitemeta_key', 'record.rootPage', 8],
    'archive index explicit root' => ['archive.wp_posts_title', 'schema', 'archive'],
    'missing unqualified index returns null' => ['missing_index', '', null],
] as $name => [$objectName, $path, $expected]) {
    $tests['attach temp schema corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $objectName, $path, $expected): void {
        $actual = $makeCatalog()->resolveIndex($objectName);
        $t->same($expected, $path === '' ? $actual : $valueAt($actual, $path));
    };
}

foreach ([
    'main record count' => ['main', 3],
    'temp record count' => ['temp', 2],
    'site record count' => ['site', 3],
    'archive record count' => ['archive', 3],
] as $name => [$schema, $expected]) {
    $tests['attach temp schema corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $schema, $expected): void {
        $t->same($expected, count($makeCatalog()->schemaRecords($schema)));
    };
}

foreach ([
    'missing explicit schema raises on table lookup' => static fn (SQLiteAttachedSchemaCatalog $catalog): mixed => $catalog->resolveTable('missing.wp_options'),
    'missing explicit schema raises on index lookup' => static fn (SQLiteAttachedSchemaCatalog $catalog): mixed => $catalog->resolveIndex('missing.wp_options_name'),
    'missing schema records raises' => static fn (SQLiteAttachedSchemaCatalog $catalog): mixed => $catalog->schemaRecords('missing'),
] as $name => $callback) {
    $tests['attach temp schema corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $callback): void {
        $t->throws(InvalidArgumentException::class, static fn () => $callback($makeCatalog()));
    };
}

return $tests;
