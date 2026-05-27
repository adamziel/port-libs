<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowId,
);

$makeCatalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([
    $record(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(
            option_id INTEGER PRIMARY KEY,
            option_name TEXT NOT NULL DEFAULT '',
            option_value TEXT,
            autoload TEXT DEFAULT 'yes',
            option_name_fold TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL,
            option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED,
            UNIQUE(option_name)
        )",
        1,
    ),
    $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    $record('index', 'wp_options_autoload_name', 'wp_options', 4, 'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)', 3),
    $record('index', 'wp_options_large_autoload', 'wp_options', 5, "CREATE UNIQUE INDEX wp_options_large_autoload ON wp_options(option_value_len) WHERE autoload = 'yes'", 4),
]);

$makeAttachedCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT DEFAULT 'yes')", 1),
            $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE INDEX wp_options_name_main ON wp_options(option_name)', 2),
            $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)', 3),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, transient_timeout INTEGER)', 1),
            $record('index', 'wp_options_name_temp', 'wp_options', 6, 'CREATE INDEX wp_options_name_temp ON wp_options(option_name)', 2),
        ],
    );
    $catalog->attach('site', '/srv/www/site.sqlite', [
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 7, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT)', 1),
        $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 8, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)', 2),
        $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, option_value TEXT)', 3),
        $record('index', 'wp_options_site_name', 'wp_options', 10, 'CREATE INDEX wp_options_site_name ON wp_options(blog_id, option_name)', 4),
    ]);

    return $catalog;
};

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$tests = [
    'pragma index table info cursor walks table-info rows in cid order' => static function (TestRunner $t) use ($makeCatalog): void {
        $cursor = $makeCatalog()->executeCursor('PRAGMA table_info(wp_options)');

        $t->same(0, $cursor->key());
        $t->same(true, $cursor->valid());
        $t->same('option_id', $cursor->current()['name']);
        $t->same('option_name', $cursor->next()['name']);
        $t->same(1, $cursor->key());
        $t->same('option_value', $cursor->next()['name']);
        $t->same('autoload', $cursor->next()['name']);
        $t->same(null, $cursor->next());
        $t->same(false, $cursor->valid());
    },
    'pragma index table info cursor rewinds without recomputing rows' => static function (TestRunner $t) use ($makeCatalog): void {
        $cursor = $makeCatalog()->executeCursor('PRAGMA table_info(wp_options)');

        $cursor->next();
        $cursor->next();
        $t->same('option_value', $cursor->current()['name']);
        $cursor->rewind();
        $t->same(0, $cursor->key());
        $t->same('option_id', $cursor->current()['name']);
        $t->same(['option_id', 'option_name', 'option_value', 'autoload'], array_column($cursor->remainingRows(), 'name'));
    },
    'pragma index table info cursor keeps generated xinfo rows visible' => static function (TestRunner $t) use ($makeCatalog): void {
        $cursor = $makeCatalog()->executeCursor('PRAGMA table_xinfo(wp_options)');

        $t->same(6, $cursor->metadata()['row_count']);
        $t->same('option_name_fold', $cursor->rows()[4]['name']);
        $t->same(2, $cursor->rows()[4]['hidden']);
        $t->same('option_value_len', $cursor->rows()[5]['name']);
        $t->same(3, $cursor->rows()[5]['hidden']);
    },
    'pragma index table info cursor walks index-info key columns' => static function (TestRunner $t) use ($makeCatalog): void {
        $cursor = $makeCatalog()->executeCursor('PRAGMA index_info(wp_options_autoload_name)');

        $t->same(['status' => 'ok', 'pragma' => 'index_info', 'schema' => 'main', 'target' => 'wp_options_autoload_name', 'row_count' => 2, 'eof' => false, 'position' => 0], $cursor->metadata());
        $t->same(['seqno' => 0, 'cid' => 3, 'name' => 'autoload'], $cursor->current());
        $t->same(['seqno' => 1, 'cid' => 1, 'name' => 'option_name'], $cursor->next());
        $t->same(null, $cursor->next());
        $t->same(['status' => 'ok', 'pragma' => 'index_info', 'schema' => 'main', 'target' => 'wp_options_autoload_name', 'row_count' => 2, 'eof' => true, 'position' => 2], $cursor->metadata());
    },
    'pragma index table info cursor walks index-xinfo auxiliary rowid' => static function (TestRunner $t) use ($makeCatalog): void {
        $cursor = $makeCatalog()->executeCursor('PRAGMA index_xinfo(wp_options_autoload_name)');

        $t->same(['autoload', 'option_name', null], array_column($cursor->rows(), 'name'));
        $t->same([1, 1, 0], array_column($cursor->rows(), 'key'));
        $t->same(-1, $cursor->rows()[2]['cid']);
        $cursor->next();
        $t->same(['option_name', null], array_column($cursor->remainingRows(), 'name'));
    },
    'pragma index table info cursor empty rowset starts at eof' => static function (TestRunner $t) use ($makeCatalog): void {
        $cursor = $makeCatalog()->executeCursor('PRAGMA index_info(missing_index)');

        $t->same(null, $cursor->current());
        $t->same(null, $cursor->next());
        $t->same(false, $cursor->valid());
        $t->same([], $cursor->remainingRows());
        $t->same(['status' => 'ok', 'pragma' => 'index_info', 'schema' => 'main', 'target' => 'missing_index', 'row_count' => 0, 'eof' => true, 'position' => 1], $cursor->metadata());
        $cursor->rewind();
        $t->same(['status' => 'ok', 'pragma' => 'index_info', 'schema' => 'main', 'target' => 'missing_index', 'row_count' => 0, 'eof' => true, 'position' => 0], $cursor->metadata());
    },
    'pragma index table info cursor preserves current-source schema at open' => static function (TestRunner $t) use ($makeAttachedCatalog): void {
        $catalog = $makeAttachedCatalog();
        $cursor = $catalog->executeSchemaPragmaCursor('PRAGMA table_info(wp_options)');

        $catalog->detach('site');
        $t->same('temp', $cursor->metadata()['schema']);
        $t->same(['option_name', 'option_value', 'transient_timeout'], array_column($cursor->rows(), 'name'));
        $t->same('option_name', $cursor->current()['name']);
        $t->same('option_value', $cursor->next()['name']);
    },
    'pragma index table info cursor supports attached index current-source rows' => static function (TestRunner $t) use ($makeAttachedCatalog): void {
        $cursor = $makeAttachedCatalog()->executeSchemaPragmaCursor('PRAGMA index_info(wp_sitemeta_key)');

        $t->same('site', $cursor->metadata()['schema']);
        $t->same('meta_key', $cursor->current()['name']);
        $t->same(null, $cursor->next());
        $t->same(true, $cursor->metadata()['eof']);
    },
];

foreach ([
    'metadata table-info pragma' => ['PRAGMA table_info(wp_options)', 'metadata.pragma', 'table_info'],
    'metadata table-info row count' => ['PRAGMA table_info(wp_options)', 'metadata.row_count', 4],
    'metadata table-info target' => ['PRAGMA table_info(wp_options)', 'metadata.target', 'wp_options'],
    'metadata table-info starts non-eof' => ['PRAGMA table_info(wp_options)', 'metadata.eof', false],
    'metadata table-xinfo row count' => ['PRAGMA table_xinfo(wp_options)', 'metadata.row_count', 6],
    'metadata table-xinfo pragma' => ['PRAGMA table_xinfo(wp_options)', 'metadata.pragma', 'table_xinfo'],
    'metadata index-info row count' => ['PRAGMA index_info(wp_options_autoload_name)', 'metadata.row_count', 2],
    'metadata index-xinfo row count includes rowid' => ['PRAGMA index_xinfo(wp_options_autoload_name)', 'metadata.row_count', 3],
    'rows table-info first cid' => ['PRAGMA table_info(wp_options)', 'rows.0.cid', 0],
    'rows table-info second name' => ['PRAGMA table_info(wp_options)', 'rows.1.name', 'option_name'],
    'rows table-info default preserved' => ['PRAGMA table_info(wp_options)', 'rows.3.dflt_value', "'yes'"],
    'rows table-info generated omitted' => ['PRAGMA table_info(wp_options)', 'rows.count', 4],
    'rows table-xinfo generated virtual hidden' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.hidden', 2],
    'rows table-xinfo generated stored hidden' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.hidden', 3],
    'rows index-info first seqno' => ['PRAGMA index_info(wp_options_autoload_name)', 'rows.0.seqno', 0],
    'rows index-info first cid' => ['PRAGMA index_info(wp_options_autoload_name)', 'rows.0.cid', 3],
    'rows index-info second cid' => ['PRAGMA index_info(wp_options_autoload_name)', 'rows.1.cid', 1],
    'rows index-xinfo first key flag' => ['PRAGMA index_xinfo(wp_options_autoload_name)', 'rows.0.key', 1],
    'rows index-xinfo auxiliary key flag' => ['PRAGMA index_xinfo(wp_options_autoload_name)', 'rows.2.key', 0],
    'rows index-xinfo auxiliary cid' => ['PRAGMA index_xinfo(wp_options_autoload_name)', 'rows.2.cid', -1],
    'current table-info first name' => ['PRAGMA table_info(wp_options)', 'current.name', 'option_id'],
    'current table-xinfo first hidden' => ['PRAGMA table_xinfo(wp_options)', 'current.hidden', 0],
    'current index-info first name' => ['PRAGMA index_info(wp_options_autoload_name)', 'current.name', 'autoload'],
    'current index-xinfo first coll' => ['PRAGMA index_xinfo(wp_options_autoload_name)', 'current.coll', 'BINARY'],
    'next table-info second name' => ['PRAGMA table_info(wp_options)', 'next.name', 'option_name'],
    'next table-xinfo second cid' => ['PRAGMA table_xinfo(wp_options)', 'next.cid', 1],
    'next index-info second name' => ['PRAGMA index_info(wp_options_autoload_name)', 'next.name', 'option_name'],
    'next index-xinfo second key' => ['PRAGMA index_xinfo(wp_options_autoload_name)', 'next.key', 1],
    'remaining initial table-info names' => ['PRAGMA table_info(wp_options)', 'remaining.names', ['option_id', 'option_name', 'option_value', 'autoload']],
    'remaining after one table-info names' => ['PRAGMA table_info(wp_options)', 'remaining_after_one.names', ['option_name', 'option_value', 'autoload']],
    'remaining initial index-info names' => ['PRAGMA index_info(wp_options_autoload_name)', 'remaining.names', ['autoload', 'option_name']],
    'remaining after one index-xinfo names' => ['PRAGMA index_xinfo(wp_options_autoload_name)', 'remaining_after_one.names', ['option_name', null]],
    'quoted table target current name' => ['PRAGMA table_info("wp_options")', 'current.name', 'option_id'],
    'equals table target current name' => ['PRAGMA table_info = wp_options', 'current.name', 'option_id'],
    'single quoted index target current name' => ["PRAGMA index_info('wp_options_autoload_name')", 'current.name', 'autoload'],
    'partial expression index info cid' => ['PRAGMA index_info(wp_options_large_autoload)', 'current.cid', 5],
    'partial expression index xinfo row count' => ['PRAGMA index_xinfo(wp_options_large_autoload)', 'metadata.row_count', 2],
    'partial expression index xinfo auxiliary cid' => ['PRAGMA index_xinfo(wp_options_large_autoload)', 'rows.1.cid', -1],
    'missing table cursor row count' => ['PRAGMA table_info(missing_options)', 'metadata.row_count', 0],
    'missing table cursor current is null' => ['PRAGMA table_info(missing_options)', 'current', null],
    'missing table cursor next is null' => ['PRAGMA table_info(missing_options)', 'next', null],
    'missing index xinfo cursor row count' => ['PRAGMA index_xinfo(missing_index)', 'metadata.row_count', 0],
    'missing index xinfo remaining empty' => ['PRAGMA index_xinfo(missing_index)', 'remaining.count', 0],
] as $name => [$sql, $path, $expected]) {
    $tests['pragma index table info cursor ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $cursor = $makeCatalog()->executeCursor($sql);
        $value = match (true) {
            str_starts_with($path, 'metadata.') => $cursor->metadata(),
            str_starts_with($path, 'rows.') => $cursor->rows(),
            str_starts_with($path, 'current') => $cursor->current(),
            str_starts_with($path, 'next') => $cursor->next(),
            str_starts_with($path, 'remaining_after_one.') => (static function () use ($cursor): array {
                $cursor->next();

                return ['names' => array_column($cursor->remainingRows(), 'name'), 'count' => count($cursor->remainingRows())];
            })(),
            str_starts_with($path, 'remaining.') => ['names' => array_column($cursor->remainingRows(), 'name'), 'count' => count($cursor->remainingRows())],
            default => throw new RuntimeException("Unsupported cursor assertion path {$path}"),
        };
        $relativePath = preg_replace('/^(metadata|rows|current|next|remaining_after_one|remaining)\.?/', '', $path) ?? '';
        if ($relativePath !== '') {
            $value = $valueAt($value, $relativePath);
        }

        $t->same($expected, $value);
    };
}

foreach ([
    'attached table cursor uses temp schema' => ['PRAGMA table_info(wp_options)', 'metadata.schema', 'temp'],
    'attached table cursor temp row count' => ['PRAGMA table_info(wp_options)', 'metadata.row_count', 3],
    'attached table cursor temp first current' => ['PRAGMA table_info(wp_options)', 'current.name', 'option_name'],
    'attached table cursor temp second next' => ['PRAGMA table_info(wp_options)', 'next.name', 'option_value'],
    'explicit main table cursor schema' => ['PRAGMA main.table_info(wp_options)', 'metadata.schema', 'main'],
    'explicit main table cursor row count' => ['PRAGMA main.table_info(wp_options)', 'metadata.row_count', 3],
    'explicit site table cursor schema' => ['PRAGMA site.table_info(wp_options)', 'metadata.schema', 'site'],
    'explicit site table cursor first name' => ['PRAGMA site.table_info(wp_options)', 'current.name', 'blog_id'],
    'attached-only table cursor schema' => ['PRAGMA table_info(wp_sitemeta)', 'metadata.schema', 'site'],
    'attached-only table cursor third name' => ['PRAGMA table_info(wp_sitemeta)', 'rows.2.name', 'meta_value'],
    'main-only table cursor schema' => ['PRAGMA table_info(wp_posts)', 'metadata.schema', 'main'],
    'main-only table cursor second name' => ['PRAGMA table_info(wp_posts)', 'next.name', 'post_title'],
    'temp index cursor schema' => ['PRAGMA index_info(wp_options_name_temp)', 'metadata.schema', 'temp'],
    'temp index cursor current name' => ['PRAGMA index_info(wp_options_name_temp)', 'current.name', 'option_name'],
    'main index cursor schema' => ['PRAGMA index_info(wp_options_name_main)', 'metadata.schema', 'main'],
    'main index cursor current cid' => ['PRAGMA index_info(wp_options_name_main)', 'current.cid', 1],
    'site index cursor schema' => ['PRAGMA index_info(wp_sitemeta_key)', 'metadata.schema', 'site'],
    'site index cursor current name' => ['PRAGMA index_info(wp_sitemeta_key)', 'current.name', 'meta_key'],
    'site compound xinfo cursor schema' => ['PRAGMA index_xinfo(wp_options_site_name)', 'metadata.schema', 'site'],
    'site compound xinfo cursor row count' => ['PRAGMA index_xinfo(wp_options_site_name)', 'metadata.row_count', 3],
    'site compound xinfo cursor second name' => ['PRAGMA index_xinfo(wp_options_site_name)', 'next.name', 'option_name'],
    'site compound xinfo cursor auxiliary cid' => ['PRAGMA index_xinfo(wp_options_site_name)', 'rows.2.cid', -1],
    'missing attached table cursor main schema' => ['PRAGMA table_info(missing_options)', 'metadata.schema', 'main'],
    'missing attached index cursor main schema' => ['PRAGMA index_info(missing_index)', 'metadata.schema', 'main'],
] as $name => [$sql, $path, $expected]) {
    $tests['pragma index table info cursor current-source ' . $name] = static function (TestRunner $t) use ($makeAttachedCatalog, $valueAt, $sql, $path, $expected): void {
        $cursor = $makeAttachedCatalog()->executeSchemaPragmaCursor($sql);
        $value = match (true) {
            str_starts_with($path, 'metadata.') => $cursor->metadata(),
            str_starts_with($path, 'rows.') => $cursor->rows(),
            str_starts_with($path, 'current') => $cursor->current(),
            str_starts_with($path, 'next') => $cursor->next(),
            default => throw new RuntimeException("Unsupported cursor assertion path {$path}"),
        };
        $relativePath = preg_replace('/^(metadata|rows|current|next)\.?/', '', $path) ?? '';
        if ($relativePath !== '') {
            $value = $valueAt($value, $relativePath);
        }

        $t->same($expected, $value);
    };
}

return $tests;
