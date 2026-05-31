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
                option_hash TEXT GENERATED ALWAYS AS (lower(option_name)) STORED
            ) STRICT", 1),
            $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE UNIQUE INDEX wp_options_name_main ON wp_options(option_name)', 2),
            $record('view', 'wp_autoloaded_options', 'wp_autoloaded_options', null, "CREATE VIEW wp_autoloaded_options AS SELECT option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
            $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)', 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY, option_value TEXT, transient_timeout INTEGER) WITHOUT ROWID', 1),
            $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 6, null, 2),
        ],
    );
    $catalog->attach('network', '/srv/www/network.sqlite', [
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 7, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT)', 1),
        $record('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, option_value TEXT, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID, STRICT', 2),
        $record('view', 'wp_network_autoloaded', 'wp_network_autoloaded', null, "CREATE VIEW wp_network_autoloaded AS SELECT blog_id, option_name FROM wp_options", 3),
    ]);
    $catalog->attach('archive', '/srv/www/archive.sqlite', [
        $record('table', 'wp archived options', 'wp archived options', 9, 'CREATE TABLE "wp archived options"(option_name TEXT, option_value TEXT)', 1),
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

$cases = [
    'database-list status ok' => ["pragma_database_list()", 'status', 'ok'],
    'database-list pragma name' => ["pragma_database_list()", 'pragma', 'database_list'],
    'database-list schema main' => ["pragma_database_list()", 'schema', 'main'],
    'database-list target empty' => ["pragma_database_list()", 'target', ''],
    'database-list row count' => ["pragma_database_list()", 'rows.count', 4],
    'database-list main seq' => ["pragma_database_list()", 'rows.0.seq', 0],
    'database-list main name' => ["pragma_database_list()", 'rows.0.name', 'main'],
    'database-list temp seq' => ["pragma_database_list()", 'rows.1.seq', 1],
    'database-list temp file' => ["pragma_database_list()", 'rows.1.file', ''],
    'database-list first attached name' => ["pragma_database_list()", 'rows.2.name', 'network'],
    'database-list first attached file' => ["pragma_database_list()", 'rows.2.file', '/srv/www/network.sqlite'],
    'database-list second attached seq' => ["pragma_database_list()", 'rows.3.seq', 3],
    'database-list case insensitive' => ["PRAGMA_DATABASE_LIST()", 'pragma', 'database_list'],
    'database-list trailing semicolon' => ["pragma_database_list();", 'rows.2.name', 'network'],
    'table-list status ok' => ["pragma_table_list()", 'status', 'ok'],
    'table-list pragma name' => ["pragma_table_list()", 'pragma', 'table_list'],
    'table-list schema reports main for all-schema scan' => ["pragma_table_list()", 'schema', 'main'],
    'table-list target empty' => ["pragma_table_list()", 'target', ''],
    'table-list row count' => ["pragma_table_list()", 'rows.count', 8],
    'table-list temp first schema' => ["pragma_table_list()", 'rows.0.schema', 'temp'],
    'table-list temp first name' => ["pragma_table_list()", 'rows.0.name', 'wp_options'],
    'table-list temp without rowid' => ["pragma_table_list()", 'rows.0.wr', 1],
    'table-list temp strict false' => ["pragma_table_list()", 'rows.0.strict', 0],
    'table-list temp ncol' => ["pragma_table_list()", 'rows.0.ncol', 3],
    'table-list main first schema' => ["pragma_table_list()", 'rows.1.schema', 'main'],
    'table-list main first name' => ["pragma_table_list()", 'rows.1.name', 'wp_options'],
    'table-list main generated column counted' => ["pragma_table_list()", 'rows.1.ncol', 5],
    'table-list main strict true' => ["pragma_table_list()", 'rows.1.strict', 1],
    'table-list main without-rowid false' => ["pragma_table_list()", 'rows.1.wr', 0],
    'table-list main view type' => ["pragma_table_list()", 'rows.2.type', 'view'],
    'table-list main view ncol matches native view projection' => ["pragma_table_list()", 'rows.2.ncol', 2],
    'table-list main second table name' => ["pragma_table_list()", 'rows.3.name', 'wp_posts'],
    'table-list network table schema' => ["pragma_table_list()", 'rows.4.schema', 'network'],
    'table-list network table name' => ["pragma_table_list()", 'rows.4.name', 'wp_sitemeta'],
    'table-list network without-rowid strict table' => ["pragma_table_list()", 'rows.5.name', 'wp_options'],
    'table-list network without-rowid flag' => ["pragma_table_list()", 'rows.5.wr', 1],
    'table-list network strict flag' => ["pragma_table_list()", 'rows.5.strict', 1],
    'table-list network primary key columns counted' => ["pragma_table_list()", 'rows.5.ncol', 3],
    'table-list network view schema' => ["pragma_table_list()", 'rows.6.schema', 'network'],
    'table-list archive quoted table schema' => ["pragma_table_list()", 'rows.7.schema', 'archive'],
    'table-list archive quoted table name' => ["pragma_table_list()", 'rows.7.name', 'wp archived options'],
    'table-list filters duplicate table name' => ["pragma_table_list('wp_options')", 'rows.count', 3],
    'table-list filtered temp first' => ["pragma_table_list('wp_options')", 'rows.0.schema', 'temp'],
    'table-list filtered main second' => ["pragma_table_list('wp_options')", 'rows.1.schema', 'main'],
    'table-list filtered network third' => ["pragma_table_list('wp_options')", 'rows.2.schema', 'network'],
    'table-list filtered preserves target' => ["pragma_table_list('wp_options')", 'target', 'wp_options'],
    'table-list schema-pinned main count' => ["pragma_table_list('wp_options','main')", 'rows.count', 1],
    'table-list schema-pinned main schema' => ["pragma_table_list('wp_options','main')", 'rows.0.schema', 'main'],
    'table-list schema-pinned network strict' => ["pragma_table_list('wp_options','network')", 'rows.0.strict', 1],
    'table-list schema-pinned archive quoted name' => ["pragma_table_list('wp archived options','archive')", 'rows.0.name', 'wp archived options'],
    'table-list missing target empty rows' => ["pragma_table_list('missing_options')", 'rows.count', 0],
    'table-list direct pragma row count' => ['PRAGMA table_list', 'rows.count', 8],
    'table-list direct pragma filter' => ['PRAGMA table_list(wp_options)', 'rows.count', 3],
    'table-list direct pragma equals filter' => ['PRAGMA table_list = wp_options', 'rows.2.schema', 'network'],
    'table-list direct schema-qualified filter' => ['PRAGMA network.table_list(wp_options)', 'rows.0.schema', 'network'],
    'table-list direct schema-qualified all' => ['PRAGMA main.table_list', 'rows.count', 3],
    'table-list direct quoted target' => ['PRAGMA archive.table_list("wp archived options")', 'rows.0.name', 'wp archived options'],
];

$tests = [];
foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['pragma database table list current next28 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $catalog = $makeCatalog();
        $result = str_starts_with(strtolower($sql), 'pragma_')
            ? $catalog->executeTableValuedPragma($sql)
            : $catalog->executeSchemaPragma($sql);

        $t->same($expected, $valueAt($result, $path));
    };
}

$tests['pragma database table list current next28 cursor walks database-list rows'] = static function (TestRunner $t) use ($makeCatalog): void {
    $cursor = $makeCatalog()->executeTableValuedPragmaCursor('pragma_database_list()');

    $t->same('database_list', $cursor->metadata()['pragma']);
    $t->same(4, $cursor->metadata()['row_count']);
    $t->same('main', $cursor->current()['name']);
    $t->same('temp', $cursor->next()['name']);
    $cursor->next();
    $t->same(['network', 'archive'], array_column($cursor->remainingRows(), 'name'));
};

$tests['pragma database table list current next28 cursor freezes table-list rows'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();
    $cursor = $catalog->executeTableValuedPragmaCursor("pragma_table_list('wp_options')");
    $catalog->detach('network');

    $t->same('table_list', $cursor->metadata()['pragma']);
    $t->same(3, $cursor->metadata()['row_count']);
    $t->same('temp', $cursor->current()['schema']);
    $t->same('main', $cursor->next()['schema']);
    $t->same('network', $cursor->next()['schema']);
    $t->same(null, $cursor->next());
};

$tests['pragma database table list current next28 standalone catalog table-list'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLitePragmaSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT) STRICT', 1),
        $record('view', 'wp_option_names', 'wp_option_names', null, 'CREATE VIEW wp_option_names AS SELECT option_name FROM wp_options', 2),
    ]);

    $rows = $catalog->executeTableValuedPragma('pragma_table_list()')['rows'];
    $t->same(2, count($rows));
    $t->same(['main', 'wp_options', 'table', 2, 0, 1], array_values($rows[0]));
    $t->same(['main', 'wp_option_names', 'view', 1, 0, 0], array_values($rows[1]));
    $t->same(1, count($catalog->executeTableValuedPragma("pragma_table_list('wp_options')")['rows']));
};

$tests['pragma database table list current next28 rejects malformed table-valued shapes'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog();

    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma('pragma_database_list(main)'));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_table_list('wp_options','network','extra')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_table_list('wp_options','')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeTableValuedPragma("pragma_table_list('wp_options','missing')"));
    $t->throws(InvalidArgumentException::class, static fn () => $catalog->executeSchemaPragma('PRAGMA missing.table_list'));
};

return $tests;
