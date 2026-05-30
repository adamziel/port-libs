<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$makeCatalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(
            option_id INTEGER PRIMARY KEY,
            option_name TEXT NOT NULL DEFAULT '',
            option_value TEXT,
            autoload TEXT DEFAULT 'yes',
            option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED,
            UNIQUE(option_name)
        )",
        1,
    ),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    new SQLiteSchemaRecord('index', 'wp_options_name_expr', 'wp_options', 4, 'CREATE INDEX wp_options_name_expr ON wp_options(lower(option_name) COLLATE nocase DESC, autoload, length(option_value)) WHERE autoload = \'yes\'', 3),
    new SQLiteSchemaRecord('index', 'wp_options_json_expr', 'wp_options', 5, 'CREATE INDEX wp_options_json_expr ON wp_options(json_extract(option_value, \'$.plugin.enabled\'), option_name COLLATE RTRIM DESC)', 4),
    new SQLiteSchemaRecord('index', 'wp_options_generated', 'wp_options', 6, 'CREATE UNIQUE INDEX wp_options_generated ON wp_options(option_value_len DESC) WHERE option_value_len IS NOT NULL', 5),
    new SQLiteSchemaRecord(
        'table',
        'wp_network_options',
        'wp_network_options',
        7,
        'CREATE TABLE wp_network_options(blog_id INTEGER NOT NULL, option_name TEXT NOT NULL, option_value TEXT, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID',
        6,
    ),
    new SQLiteSchemaRecord('index', 'wp_network_option_value_expr', 'wp_network_options', 8, 'CREATE INDEX wp_network_option_value_expr ON wp_network_options(substr(option_name, 1, 4), option_value COLLATE nocase)', 7),
]);

$tests = [
    'index xinfo executes expression pragma for application expression index' => static function (TestRunner $t) use ($makeCatalog): void {
        $result = $makeCatalog()->execute('PRAGMA index_xinfo(wp_options_name_expr)');

        $t->same('ok', $result['status']);
        $t->same('index_xinfo', $result['pragma']);
        $t->same('wp_options_name_expr', $result['target']);
        $t->same(4, count($result['rows']));
    },
    'index xinfo accepts schema qualified pragma target' => static function (TestRunner $t) use ($makeCatalog): void {
        $result = $makeCatalog()->execute('PRAGMA main.index_xinfo(wp_options_name_expr)');

        $t->same('main', $result['schema']);
        $t->same('index_xinfo', $result['pragma']);
    },
    'index xinfo accepts equals syntax target' => static function (TestRunner $t) use ($makeCatalog): void {
        $result = $makeCatalog()->execute('PRAGMA index_xinfo = wp_options_name_expr');

        $t->same('wp_options_name_expr', $result['target']);
        $t->same(4, count($result['rows']));
    },
    'index xinfo accepts quoted expression index target' => static function (TestRunner $t) use ($makeCatalog): void {
        $result = $makeCatalog()->execute('PRAGMA index_xinfo("wp_options_name_expr")');

        $t->same(4, count($result['rows']));
    },
    'index xinfo missing index returns empty rows' => static function (TestRunner $t) use ($makeCatalog): void {
        $t->same([], $makeCatalog()->execute('PRAGMA index_xinfo(missing_index)')['rows']);
    },
];

$cases = [
    'expression first term seqno' => ['PRAGMA index_xinfo(wp_options_name_expr)', '0.seqno', 0],
    'expression first term cid' => ['PRAGMA index_xinfo(wp_options_name_expr)', '0.cid', -2],
    'expression first term name is null' => ['PRAGMA index_xinfo(wp_options_name_expr)', '0.name', null],
    'expression first term desc flag' => ['PRAGMA index_xinfo(wp_options_name_expr)', '0.desc', 1],
    'expression first term collation' => ['PRAGMA index_xinfo(wp_options_name_expr)', '0.coll', 'NOCASE'],
    'expression first term key flag' => ['PRAGMA index_xinfo(wp_options_name_expr)', '0.key', 1],
    'ordinary second term seqno' => ['PRAGMA index_xinfo(wp_options_name_expr)', '1.seqno', 1],
    'ordinary second term cid' => ['PRAGMA index_xinfo(wp_options_name_expr)', '1.cid', 3],
    'ordinary second term name' => ['PRAGMA index_xinfo(wp_options_name_expr)', '1.name', 'autoload'],
    'ordinary second term asc flag' => ['PRAGMA index_xinfo(wp_options_name_expr)', '1.desc', 0],
    'ordinary second term default collation' => ['PRAGMA index_xinfo(wp_options_name_expr)', '1.coll', 'BINARY'],
    'ordinary second term key flag' => ['PRAGMA index_xinfo(wp_options_name_expr)', '1.key', 1],
    'second expression seqno' => ['PRAGMA index_xinfo(wp_options_name_expr)', '2.seqno', 2],
    'second expression cid' => ['PRAGMA index_xinfo(wp_options_name_expr)', '2.cid', -2],
    'second expression name is null' => ['PRAGMA index_xinfo(wp_options_name_expr)', '2.name', null],
    'second expression default collation' => ['PRAGMA index_xinfo(wp_options_name_expr)', '2.coll', 'BINARY'],
    'rowid auxiliary seqno' => ['PRAGMA index_xinfo(wp_options_name_expr)', '3.seqno', 3],
    'rowid auxiliary cid' => ['PRAGMA index_xinfo(wp_options_name_expr)', '3.cid', -1],
    'rowid auxiliary name is null' => ['PRAGMA index_xinfo(wp_options_name_expr)', '3.name', null],
    'rowid auxiliary key flag' => ['PRAGMA index_xinfo(wp_options_name_expr)', '3.key', 0],
    'json expression row cid' => ['PRAGMA index_xinfo(wp_options_json_expr)', '0.cid', -2],
    'json expression row collation' => ['PRAGMA index_xinfo(wp_options_json_expr)', '0.coll', 'BINARY'],
    'json expression row key flag' => ['PRAGMA index_xinfo(wp_options_json_expr)', '0.key', 1],
    'json ordinary row cid' => ['PRAGMA index_xinfo(wp_options_json_expr)', '1.cid', 1],
    'json ordinary row name' => ['PRAGMA index_xinfo(wp_options_json_expr)', '1.name', 'option_name'],
    'json ordinary row desc flag' => ['PRAGMA index_xinfo(wp_options_json_expr)', '1.desc', 1],
    'json ordinary row rtrim collation' => ['PRAGMA index_xinfo(wp_options_json_expr)', '1.coll', 'RTRIM'],
    'json rowid auxiliary follows keys' => ['PRAGMA index_xinfo(wp_options_json_expr)', '2.key', 0],
    'generated column index cid' => ['PRAGMA index_xinfo(wp_options_generated)', '0.cid', 4],
    'generated column index name' => ['PRAGMA index_xinfo(wp_options_generated)', '0.name', 'option_value_len'],
    'generated column index desc flag' => ['PRAGMA index_xinfo(wp_options_generated)', '0.desc', 1],
    'generated column index key flag' => ['PRAGMA index_xinfo(wp_options_generated)', '0.key', 1],
    'autoindex key seqno' => ['PRAGMA index_xinfo(sqlite_autoindex_wp_options_1)', '0.seqno', 0],
    'autoindex key cid' => ['PRAGMA index_xinfo(sqlite_autoindex_wp_options_1)', '0.cid', 1],
    'autoindex key name' => ['PRAGMA index_xinfo(sqlite_autoindex_wp_options_1)', '0.name', 'option_name'],
    'autoindex key collation' => ['PRAGMA index_xinfo(sqlite_autoindex_wp_options_1)', '0.coll', 'BINARY'],
    'autoindex rowid auxiliary cid' => ['PRAGMA index_xinfo(sqlite_autoindex_wp_options_1)', '1.cid', -1],
    'autoindex rowid auxiliary key' => ['PRAGMA index_xinfo(sqlite_autoindex_wp_options_1)', '1.key', 0],
    'without rowid expression cid' => ['PRAGMA index_xinfo(wp_network_option_value_expr)', '0.cid', -2],
    'without rowid value column cid' => ['PRAGMA index_xinfo(wp_network_option_value_expr)', '1.cid', 2],
    'without rowid value column collation' => ['PRAGMA index_xinfo(wp_network_option_value_expr)', '1.coll', 'NOCASE'],
    'without rowid primary key auxiliary cid' => ['PRAGMA index_xinfo(wp_network_option_value_expr)', '2.cid', 0],
    'without rowid primary key auxiliary name' => ['PRAGMA index_xinfo(wp_network_option_value_expr)', '2.name', 'blog_id'],
    'without rowid primary key auxiliary key' => ['PRAGMA index_xinfo(wp_network_option_value_expr)', '2.key', 0],
    'without rowid expression over primary key still appends primary key auxiliary count' => ['PRAGMA index_xinfo(wp_network_option_value_expr)', 'count', 4],
    'index info keeps legacy helper name for expression compatibility' => ['PRAGMA index_info(wp_options_name_expr)', '0.name', 'lower'],
    'index info keeps expression cid negative' => ['PRAGMA index_info(wp_options_name_expr)', '0.cid', -2],
    'index info omits xinfo rowid auxiliary' => ['PRAGMA index_info(wp_options_name_expr)', 'count', 3],
    'index xinfo table index list still marks partial' => ['PRAGMA index_list(wp_options)', '1.partial', 1],
    'index xinfo table index list preserves unique generated index' => ['PRAGMA index_list(wp_options)', '3.unique', 1],
];

foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['index xinfo corpus ' . $name] = static function (TestRunner $t) use ($makeCatalog, $sql, $path, $expected): void {
        $value = $makeCatalog()->execute($sql);
        foreach (explode('.', $path) as $part) {
            if ($part === 'count') {
                $value = count($value['rows']);
                continue;
            }
            $value = is_numeric($part) ? $value['rows'][(int) $part] : $value[$part];
        }

        $t->same($expected, $value);
    };
}

$tests['index xinfo rejects unsupported malformed pragma shape'] = static function (TestRunner $t): void {
    $catalog = new SQLitePragmaSchemaCatalog([]);

    $t->throws(InvalidArgumentException::class, static fn () => $catalog->execute('PRAGMA index_xinfo(wp_options_name_expr'));
};

return $tests;
