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
            $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, autoload TEXT DEFAULT 'yes')"),
            $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE INDEX wp_options_name_main ON wp_options(option_name)'),
            $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)'),
            $record('index', 'wp_posts_title', 'wp_posts', 5, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)'),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, transient_timeout INTEGER)'),
            $record('index', 'wp_options_name_temp', 'wp_options', 7, 'CREATE INDEX wp_options_name_temp ON wp_options(option_name)'),
        ],
    );
    $catalog->attach('site', '/srv/www/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, option_value TEXT)'),
        $record('index', 'wp_options_site_name', 'wp_options', 9, 'CREATE INDEX wp_options_site_name ON wp_options(blog_id, option_name)'),
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 10, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT)'),
        $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 11, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
    ]);
    $catalog->attach('archive', '/srv/www/archive.sqlite', [
        $record('table', 'wp_archive_options', 'wp_archive_options', 12, 'CREATE TABLE wp_archive_options(option_name TEXT, archived_at TEXT)'),
        $record('index', 'wp_archive_options_name', 'wp_archive_options', 13, 'CREATE INDEX wp_archive_options_name ON wp_archive_options(option_name)'),
    ]);

    return $catalog;
};

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'unqualified table-info uses temp current source' => ['PRAGMA table_info(wp_options)', 'schema', 'temp'],
    'unqualified table-info returns temp column count' => ['PRAGMA table_info(wp_options)', 'rows.count', 3],
    'unqualified table-info returns temp value column' => ['PRAGMA table_info(wp_options)', 'rows.1.name', 'option_value'],
    'unqualified table-xinfo uses temp current source' => ['PRAGMA table_xinfo(wp_options)', 'schema', 'temp'],
    'unqualified index-list uses temp current source table' => ['PRAGMA index_list(wp_options)', 'schema', 'temp'],
    'unqualified index-list returns temp index' => ['PRAGMA index_list(wp_options)', 'rows.0.name', 'wp_options_name_temp'],
    'explicit main table-info bypasses temp source' => ['PRAGMA main.table_info(wp_options)', 'schema', 'main'],
    'explicit main table-info returns main autoload column' => ['PRAGMA main.table_info(wp_options)', 'rows.2.name', 'autoload'],
    'explicit site table-info returns attached source' => ['PRAGMA site.table_info(wp_options)', 'schema', 'site'],
    'explicit site table-info returns blog column' => ['PRAGMA site.table_info(wp_options)', 'rows.0.name', 'blog_id'],
    'unqualified attached-only table-info resolves after main' => ['PRAGMA table_info(wp_sitemeta)', 'schema', 'site'],
    'unqualified attached-only table-info returns meta value' => ['PRAGMA table_info(wp_sitemeta)', 'rows.2.name', 'meta_value'],
    'unqualified archive-only table-info resolves attach order' => ['PRAGMA table_info(wp_archive_options)', 'schema', 'archive'],
    'unqualified archive-only table-info returns archived column' => ['PRAGMA table_info(wp_archive_options)', 'rows.1.name', 'archived_at'],
    'unqualified main-only table-info resolves after temp miss' => ['PRAGMA table_info(wp_posts)', 'schema', 'main'],
    'unqualified main-only table-info returns title column' => ['PRAGMA table_info(wp_posts)', 'rows.1.name', 'post_title'],
    'unqualified index-info uses main current index' => ['PRAGMA index_info(wp_options_name_main)', 'schema', 'main'],
    'unqualified index-info returns main option column' => ['PRAGMA index_info(wp_options_name_main)', 'rows.0.name', 'option_name'],
    'unqualified index-info uses temp current index' => ['PRAGMA index_info(wp_options_name_temp)', 'schema', 'temp'],
    'unqualified index-info returns temp option column' => ['PRAGMA index_info(wp_options_name_temp)', 'rows.0.cid', 0],
    'unqualified index-info uses site current index' => ['PRAGMA index_info(wp_sitemeta_key)', 'schema', 'site'],
    'unqualified index-info returns site meta key column' => ['PRAGMA index_info(wp_sitemeta_key)', 'rows.0.name', 'meta_key'],
    'unqualified index-xinfo uses site current index' => ['PRAGMA index_xinfo(wp_options_site_name)', 'schema', 'site'],
    'unqualified index-xinfo returns first site key cid' => ['PRAGMA index_xinfo(wp_options_site_name)', 'rows.0.cid', 0],
    'unqualified index-xinfo returns second site key name' => ['PRAGMA index_xinfo(wp_options_site_name)', 'rows.1.name', 'option_name'],
    'unqualified index-xinfo appends rowid auxiliary' => ['PRAGMA index_xinfo(wp_options_site_name)', 'rows.2.cid', -1],
    'explicit archive index-info stays pinned' => ['PRAGMA archive.index_info(wp_archive_options_name)', 'schema', 'archive'],
    'explicit archive index-info returns option column' => ['PRAGMA archive.index_info(wp_archive_options_name)', 'rows.0.name', 'option_name'],
    'equals syntax current-source resolves temp table' => ['PRAGMA table_info = wp_options', 'schema', 'temp'],
    'single-quoted target current-source resolves temp table' => ["PRAGMA table_info('wp_options')", 'rows.2.name', 'transient_timeout'],
    'bracket target current-source resolves main table' => ['PRAGMA table_info([wp_posts])', 'schema', 'main'],
    'case-insensitive table target resolves temp source' => ['PRAGMA table_info(WP_OPTIONS)', 'schema', 'temp'],
    'case-insensitive index target resolves site source' => ['PRAGMA index_info(WP_SITEMETA_KEY)', 'schema', 'site'],
    'missing table target returns main empty rows' => ['PRAGMA table_info(missing_options)', 'schema', 'main'],
    'missing table target keeps empty rowset' => ['PRAGMA table_info(missing_options)', 'rows.count', 0],
    'missing index target returns main empty rows' => ['PRAGMA index_info(missing_index)', 'schema', 'main'],
    'missing index target keeps empty rowset' => ['PRAGMA index_info(missing_index)', 'rows.count', 0],
];

$tests = [];
foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['pragma schema current-source resolve ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($makeCatalog()->executeSchemaPragma($sql), $path));
    };
}

$tests['pragma schema current-source resolve explicit missing schema raises'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeSchemaPragma('PRAGMA missing.table_info(wp_options)'));
};

$tests['pragma schema current-source resolve foreign key list returns current-source empty rows'] = static function (TestRunner $t) use ($makeCatalog): void {
    $result = $makeCatalog()->executeSchemaPragma('PRAGMA foreign_key_list(wp_options)');
    $t->same('ok', $result['status']);
    $t->same('foreign_key_list', $result['pragma']);
    $t->same('temp', $result['schema']);
    $t->same('wp_options', $result['target']);
    $t->same([], $result['rows']);
};

$tests['pragma schema current-source resolve malformed sql still raises'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->executeSchemaPragma('SELECT * FROM pragma_table_info(wp_options)'));
};

return $tests;
