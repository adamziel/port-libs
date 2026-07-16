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

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(
                option_id INTEGER PRIMARY KEY,
                option_name TEXT NOT NULL,
                option_value TEXT,
                autoload TEXT DEFAULT 'yes',
                option_name_fold TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL
            )", 1),
            $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE UNIQUE INDEX wp_options_name_main ON wp_options(option_name COLLATE NOCASE DESC)', 2),
            $record('index', 'wp_options_autoload_main', 'wp_options', 4, "CREATE INDEX wp_options_autoload_main ON wp_options(autoload, option_name) WHERE autoload = 'yes'", 3),
            $record('table', 'wp_posts', 'wp_posts', 5, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)', 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY, option_value TEXT, transient_timeout INTEGER)', 1),
            $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 7, null, 2),
            $record('index', 'wp_options_name_temp', 'wp_options', 8, 'CREATE INDEX wp_options_name_temp ON wp_options(option_name)', 3),
        ],
    );

    $catalog->attach('network', '/srv/www/network.sqlite', [
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 8, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT)', 1),
        $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 9, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key COLLATE RTRIM)', 2),
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, option_value TEXT, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 3),
        $record('index', 'wp_options_site_name', 'wp_options', 11, 'CREATE INDEX wp_options_site_name ON wp_options(blog_id, option_name)', 4),
    ]);
    $catalog->attach('archive', '/srv/www/archive.sqlite', [
        $record('table', 'wp archived options', 'wp archived options', 12, 'CREATE TABLE "wp archived options"(option_name TEXT, option_value TEXT)', 1),
        $record('index', 'wp archived options name', 'wp archived options', 13, 'CREATE INDEX "wp archived options name" ON "wp archived options"(option_name)', 2),
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
    'table-info status ok' => ["pragma_table_info('wp_options')", 'status', 'ok'],
    'table-info pragma name' => ["pragma_table_info('wp_options')", 'pragma', 'table_info'],
    'table-info unqualified follows temp schema' => ["pragma_table_info('wp_options')", 'schema', 'temp'],
    'table-info target preserved' => ["pragma_table_info('wp_options')", 'target', 'wp_options'],
    'table-info temp row count' => ["pragma_table_info('wp_options')", 'rows.count', 3],
    'table-info temp first column' => ["pragma_table_info('wp_options')", 'rows.0.name', 'option_name'],
    'table-info temp primary key' => ["pragma_table_info('wp_options')", 'rows.0.pk', 1],
    'table-info temp second nullable' => ["pragma_table_info('wp_options')", 'rows.1.notnull', 0],
    'table-info temp transient column' => ["pragma_table_info('wp_options')", 'rows.2.name', 'transient_timeout'],
    'table-info two-arg main pins schema' => ["pragma_table_info('wp_options','main')", 'schema', 'main'],
    'table-info main row count omits generated column' => ["pragma_table_info('wp_options','main')", 'rows.count', 4],
    'table-info main first cid' => ["pragma_table_info('wp_options','main')", 'rows.0.cid', 0],
    'table-info main autoload default' => ["pragma_table_info('wp_options','main')", 'rows.3.dflt_value', "'yes'"],
    'table-xinfo main includes generated column' => ["pragma_table_xinfo('wp_options','main')", 'rows.count', 5],
    'table-xinfo generated hidden code' => ["pragma_table_xinfo('wp_options','main')", 'rows.4.hidden', 2],
    'table-xinfo generated column name' => ["pragma_table_xinfo('wp_options','main')", 'rows.4.name', 'option_name_fold'],
    'table-xinfo generated cid preserved' => ["pragma_table_xinfo('wp_options','main')", 'rows.4.cid', 4],
    'index-list unqualified follows temp table' => ["pragma_index_list('wp_options')", 'schema', 'temp'],
    'index-list temp row count' => ["pragma_index_list('wp_options')", 'rows.count', 2],
    'index-list temp primary-key autoindex origin' => ["pragma_index_list('wp_options')", 'rows.0.origin', 'pk'],
    'index-list temp unique flag' => ["pragma_index_list('wp_options')", 'rows.0.unique', 1],
    'index-list main row count' => ["pragma_index_list('wp_options','main')", 'rows.count', 2],
    'index-list main first explicit name' => ["pragma_index_list('wp_options','main')", 'rows.0.name', 'wp_options_name_main'],
    'index-list main first unique' => ["pragma_index_list('wp_options','main')", 'rows.0.unique', 1],
    'index-list main second partial' => ["pragma_index_list('wp_options','main')", 'rows.1.partial', 1],
    'index-list main second origin' => ["pragma_index_list('wp_options','main')", 'rows.1.origin', 'c'],
    'index-info unqualified temp index schema' => ["pragma_index_info('wp_options_name_temp')", 'schema', 'temp'],
    'index-info temp first name' => ["pragma_index_info('wp_options_name_temp')", 'rows.0.name', 'option_name'],
    'index-info temp first cid' => ["pragma_index_info('wp_options_name_temp')", 'rows.0.cid', 0],
    'index-info main pins schema' => ["pragma_index_info('wp_options_name_main','main')", 'schema', 'main'],
    'index-info main first cid' => ["pragma_index_info('wp_options_name_main','main')", 'rows.0.cid', 1],
    'index-info main first seqno' => ["pragma_index_info('wp_options_name_main','main')", 'rows.0.seqno', 0],
    'index-xinfo main row count includes rowid' => ["pragma_index_xinfo('wp_options_name_main','main')", 'rows.count', 2],
    'index-xinfo main desc flag' => ["pragma_index_xinfo('wp_options_name_main','main')", 'rows.0.desc', 1],
    'index-xinfo main collation' => ["pragma_index_xinfo('wp_options_name_main','main')", 'rows.0.coll', 'NOCASE'],
    'index-xinfo main key flag' => ["pragma_index_xinfo('wp_options_name_main','main')", 'rows.0.key', 1],
    'index-xinfo main auxiliary rowid cid' => ["pragma_index_xinfo('wp_options_name_main','main')", 'rows.1.cid', -1],
    'attached table-info resolves network table' => ["pragma_table_info('wp_sitemeta')", 'schema', 'network'],
    'attached table-info network third column' => ["pragma_table_info('wp_sitemeta')", 'rows.2.name', 'meta_value'],
    'attached index-info resolves network index' => ["pragma_index_info('wp_sitemeta_key')", 'schema', 'network'],
    'attached index-xinfo network collation' => ["pragma_index_xinfo('wp_sitemeta_key')", 'rows.0.coll', 'RTRIM'],
    'attached index-xinfo network auxiliary rowid' => ["pragma_index_xinfo('wp_sitemeta_key')", 'rows.1.cid', -1],
    'without-rowid index-xinfo keeps primary key auxiliary' => ["pragma_index_xinfo('wp_options_site_name','network')", 'rows.count', 2],
    'without-rowid index-xinfo first key' => ["pragma_index_xinfo('wp_options_site_name','network')", 'rows.0.key', 1],
    'without-rowid index-list network row count' => ["pragma_index_list('wp_options','network')", 'rows.count', 1],
    'quoted table target with spaces resolves archive' => ["pragma_table_info('wp archived options')", 'schema', 'archive'],
    'quoted table target with spaces row count' => ["pragma_table_info('wp archived options')", 'rows.count', 2],
    'quoted index target with spaces resolves archive' => ["pragma_index_info('wp archived options name')", 'schema', 'archive'],
    'quoted index target with spaces column' => ["pragma_index_info('wp archived options name')", 'rows.0.name', 'option_name'],
    'missing table defaults main empty rowset' => ["pragma_table_info('missing_options')", 'schema', 'main'],
    'missing table rows empty' => ["pragma_table_info('missing_options')", 'rows.count', 0],
    'missing index defaults main empty rowset' => ["pragma_index_xinfo('missing_index')", 'schema', 'main'],
    'missing index rows empty' => ["pragma_index_xinfo('missing_index')", 'rows.count', 0],
    'function name case insensitive' => ["PRAGMA_INDEX_INFO('wp_options_name_main','main')", 'pragma', 'index_info'],
    'bare target accepted' => ['pragma_table_info(wp_options)', 'target', 'wp_options'],
    'trailing semicolon accepted' => ["pragma_index_info('wp_options_name_main','main');", 'schema', 'main'],
];

$tests = [];
foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['pragma index table valued current next21 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($makeCatalog()->executeTableValuedPragma($sql), $path));
    };
}

$tests['pragma index table valued current next21 standalone catalog parity'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLitePragmaSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, autoload TEXT)', 1),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    ]);

    $t->same($catalog->execute('PRAGMA table_info(wp_options)'), $catalog->executeTableValuedPragma("pragma_table_info('wp_options')"));
    $t->same($catalog->execute('PRAGMA index_info(sqlite_autoindex_wp_options_1)'), $catalog->executeTableValuedPragma("pragma_index_info('sqlite_autoindex_wp_options_1')"));
};

$tests['pragma index table valued current next21 cursor walks table-valued rows'] = static function (TestRunner $t) use ($makeCatalog): void {
    $cursor = $makeCatalog()->executeTableValuedPragmaCursor("pragma_table_xinfo('wp_options','main')");

    $t->same('table_xinfo', $cursor->metadata()['pragma']);
    $t->same('main', $cursor->metadata()['schema']);
    $t->same('option_id', $cursor->current()['name']);
    $t->same('option_name', $cursor->next()['name']);
    $t->same(['option_name', 'option_value', 'autoload', 'option_name_fold'], array_column($cursor->remainingRows(), 'name'));
};

$tests['pragma index table valued current next21 cursor freezes current source'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $cursor = $catalog->executeTableValuedPragmaCursor("pragma_index_info('wp_sitemeta_key')");
    $catalog->detach('network');

    $t->same('network', $cursor->metadata()['schema']);
    $t->same('meta_key', $cursor->current()['name']);
    $t->same(null, $cursor->next());
    $t->same(true, $cursor->metadata()['eof']);
};

$tests['pragma index table valued current next21 rejects malformed shapes'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();

    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma('pragma_index_info()'));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_index_info('wp_options_name_main','main','extra')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_index_info('wp_options_name_main', '')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_index_info('wp_options_name_main','missing')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("index_info('wp_options_name_main')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma('pragma_unknown_list()'));
};

return $tests;
