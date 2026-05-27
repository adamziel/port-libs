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

$makeAttachedCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(
                option_id INTEGER PRIMARY KEY,
                option_name TEXT NOT NULL,
                option_value TEXT,
                autoload TEXT DEFAULT 'yes',
                updated_at TEXT,
                UNIQUE(option_name)
            )", 1),
            $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
            $record('index', 'wp_options_expr_main', 'wp_options', 4, "CREATE INDEX wp_options_expr_main ON wp_options(lower(option_name) COLLATE nocase DESC, json_extract(option_value, '$.enabled'), autoload)", 3),
            $record('index', 'wp_options_partial_expr_main', 'wp_options', 5, "CREATE INDEX wp_options_partial_expr_main ON wp_options(substr(option_name, 1, 8), updated_at DESC) WHERE autoload = 'yes'", 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)', 1),
            $record('index', 'wp_options_expr_temp', 'wp_options', 7, "CREATE INDEX wp_options_expr_temp ON wp_options(upper(option_name), autoload COLLATE RTRIM DESC)", 2),
        ],
    );

    $catalog->attach('site', '/srv/www/site.sqlite', [
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 8, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT, autoload TEXT)', 1),
        $record('index', 'wp_sitemeta_expr', 'wp_sitemeta', 9, "CREATE INDEX wp_sitemeta_expr ON wp_sitemeta(json_extract(meta_value, '$.plugin') COLLATE nocase, lower(meta_key), autoload DESC)", 2),
        $record('table', 'wp_network_options', 'wp_network_options', 10, 'CREATE TABLE wp_network_options(blog_id INTEGER NOT NULL, option_name TEXT NOT NULL, option_value TEXT, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 3),
        $record('index', 'wp_network_expr', 'wp_network_options', 11, "CREATE INDEX wp_network_expr ON wp_network_options(lower(option_name), option_value COLLATE nocase)", 4),
    ]);

    return $catalog;
};

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === '') {
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

$cursorSnapshot = static function (SQLiteAttachedSchemaCatalog $catalog, string $sql, bool $tableValued = false): array {
    $cursor = $tableValued
        ? $catalog->executeTableValuedPragmaCursor($sql)
        : $catalog->executeSchemaPragmaCursor($sql);
    $first = $cursor->current();
    $second = $cursor->next();
    $third = $cursor->next();
    $afterTwo = $cursor->remainingRows();
    $metadataAfterTwo = $cursor->metadata();
    $cursor->rewind();

    return [
        'metadata' => $cursor->metadata(),
        'rows' => $cursor->rows(),
        'first' => $first,
        'second' => $second,
        'third' => $third,
        'after_two' => $afterTwo,
        'metadata_after_two' => $metadataAfterTwo,
        'rewound' => $cursor->current(),
    ];
};

$tests = [
    'pragma index_xinfo expression current next35 walks expression current and next rows' => static function (TestRunner $t) use ($makeAttachedCatalog): void {
        $cursor = $makeAttachedCatalog()->executeSchemaPragmaCursor('PRAGMA main.index_xinfo(wp_options_expr_main)');

        $t->same(['seqno' => 0, 'cid' => -2, 'name' => null, 'desc' => 1, 'coll' => 'NOCASE', 'key' => 1], $cursor->current());
        $t->same(['seqno' => 1, 'cid' => -2, 'name' => null, 'desc' => 0, 'coll' => 'BINARY', 'key' => 1], $cursor->next());
        $t->same(['seqno' => 2, 'cid' => 3, 'name' => 'autoload', 'desc' => 0, 'coll' => 'BINARY', 'key' => 1], $cursor->next());
        $t->same(['seqno' => 3, 'cid' => -1, 'name' => null, 'desc' => 0, 'coll' => 'BINARY', 'key' => 0], $cursor->next());
        $t->same(null, $cursor->next());
    },
    'pragma index_xinfo expression current next35 rewinds expression cursor without schema drift' => static function (TestRunner $t) use ($makeAttachedCatalog): void {
        $catalog = $makeAttachedCatalog();
        $cursor = $catalog->executeSchemaPragmaCursor('PRAGMA index_xinfo(wp_options_expr_temp)');

        $catalog->detach('site');
        $cursor->next();
        $t->same(['seqno' => 1, 'cid' => 2, 'name' => 'autoload', 'desc' => 1, 'coll' => 'RTRIM', 'key' => 1], $cursor->current());
        $cursor->rewind();
        $t->same('temp', $cursor->metadata()['schema']);
        $t->same(['seqno' => 0, 'cid' => -2, 'name' => null, 'desc' => 0, 'coll' => 'BINARY', 'key' => 1], $cursor->current());
    },
    'pragma index_xinfo expression current next35 table-valued cursor follows attached expression index' => static function (TestRunner $t) use ($makeAttachedCatalog): void {
        $cursor = $makeAttachedCatalog()->executeTableValuedPragmaCursor("pragma_index_xinfo('wp_sitemeta_expr')");

        $t->same('site', $cursor->metadata()['schema']);
        $t->same(-2, $cursor->current()['cid']);
        $t->same('NOCASE', $cursor->current()['coll']);
        $t->same(-2, $cursor->next()['cid']);
        $t->same('autoload', $cursor->next()['name']);
    },
    'pragma index_xinfo expression current next35 without-rowid appends uncovered primary keys' => static function (TestRunner $t) use ($makeAttachedCatalog): void {
        $cursor = $makeAttachedCatalog()->executeSchemaPragmaCursor('PRAGMA site.index_xinfo(wp_network_expr)');

        $t->same([null, 'option_value', 'blog_id', 'option_name'], array_column($cursor->rows(), 'name'));
        $t->same([1, 1, 0, 0], array_column($cursor->rows(), 'key'));
        $t->same([0, 1, 2, 3], array_column($cursor->rows(), 'seqno'));
        $cursor->next();
        $cursor->next();
        $t->same([2, 3], array_column($cursor->remainingRows(), 'seqno'));
    },
];

$schemaCases = [
    'main expression metadata schema' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'metadata.schema', 'main'],
    'main expression metadata row count' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'metadata.row_count', 4],
    'main expression first cid' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'first.cid', -2],
    'main expression first name null' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'first.name', null],
    'main expression first desc from collate term' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'first.desc', 1],
    'main expression first nocase collation' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'first.coll', 'NOCASE'],
    'main json expression second cid' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'second.cid', -2],
    'main json expression second collation' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'second.coll', 'BINARY'],
    'main ordinary third name' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'third.name', 'autoload'],
    'main ordinary third cid' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'third.cid', 3],
    'main remaining after two starts autoload' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'after_two.0.name', 'autoload'],
    'main remaining after two includes rowid auxiliary' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'after_two.1.cid', -1],
    'main metadata after two position' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'metadata_after_two.position', 2],
    'main metadata after two not eof' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'metadata_after_two.eof', false],
    'main rewound first expression cid' => ['PRAGMA main.index_xinfo(wp_options_expr_main)', false, 'rewound.cid', -2],
    'temp unqualified expression resolves temp schema' => ['PRAGMA index_xinfo(wp_options_expr_temp)', false, 'metadata.schema', 'temp'],
    'temp expression row count' => ['PRAGMA index_xinfo(wp_options_expr_temp)', false, 'metadata.row_count', 3],
    'temp expression first cid' => ['PRAGMA index_xinfo(wp_options_expr_temp)', false, 'first.cid', -2],
    'temp expression first key flag' => ['PRAGMA index_xinfo(wp_options_expr_temp)', false, 'first.key', 1],
    'temp ordinary second name' => ['PRAGMA index_xinfo(wp_options_expr_temp)', false, 'second.name', 'autoload'],
    'temp ordinary second rtrim collation' => ['PRAGMA index_xinfo(wp_options_expr_temp)', false, 'second.coll', 'RTRIM'],
    'temp ordinary second desc flag' => ['PRAGMA index_xinfo(wp_options_expr_temp)', false, 'second.desc', 1],
    'temp rowid auxiliary third cid' => ['PRAGMA index_xinfo(wp_options_expr_temp)', false, 'third.cid', -1],
    'partial expression metadata row count' => ['PRAGMA main.index_xinfo(wp_options_partial_expr_main)', false, 'metadata.row_count', 3],
    'partial expression first cid' => ['PRAGMA main.index_xinfo(wp_options_partial_expr_main)', false, 'first.cid', -2],
    'partial ordinary second name' => ['PRAGMA main.index_xinfo(wp_options_partial_expr_main)', false, 'second.name', 'updated_at'],
    'partial ordinary second desc' => ['PRAGMA main.index_xinfo(wp_options_partial_expr_main)', false, 'second.desc', 1],
    'partial rowid auxiliary third key' => ['PRAGMA main.index_xinfo(wp_options_partial_expr_main)', false, 'third.key', 0],
    'site expression metadata schema' => ['PRAGMA index_xinfo(wp_sitemeta_expr)', false, 'metadata.schema', 'site'],
    'site expression row count' => ['PRAGMA index_xinfo(wp_sitemeta_expr)', false, 'metadata.row_count', 4],
    'site json expression first collation' => ['PRAGMA index_xinfo(wp_sitemeta_expr)', false, 'first.coll', 'NOCASE'],
    'site lower expression second cid' => ['PRAGMA index_xinfo(wp_sitemeta_expr)', false, 'second.cid', -2],
    'site autoload third desc' => ['PRAGMA index_xinfo(wp_sitemeta_expr)', false, 'third.desc', 1],
    'site remaining after two has autoload' => ['PRAGMA index_xinfo(wp_sitemeta_expr)', false, 'after_two.0.name', 'autoload'],
    'site remaining after two has rowid' => ['PRAGMA index_xinfo(wp_sitemeta_expr)', false, 'after_two.1.cid', -1],
    'without rowid table-valued schema' => ["pragma_index_xinfo('wp_network_expr','site')", true, 'metadata.schema', 'site'],
    'without rowid table-valued count' => ["pragma_index_xinfo('wp_network_expr','site')", true, 'metadata.row_count', 4],
    'without rowid expression cid' => ["pragma_index_xinfo('wp_network_expr','site')", true, 'first.cid', -2],
    'without rowid ordinary second name' => ["pragma_index_xinfo('wp_network_expr','site')", true, 'second.name', 'option_value'],
    'without rowid ordinary second collation' => ["pragma_index_xinfo('wp_network_expr','site')", true, 'second.coll', 'NOCASE'],
    'without rowid first auxiliary primary key name' => ["pragma_index_xinfo('wp_network_expr','site')", true, 'third.name', 'blog_id'],
    'without rowid after two includes option name auxiliary' => ["pragma_index_xinfo('wp_network_expr','site')", true, 'after_two.1.name', 'option_name'],
    'without rowid after two auxiliary key false' => ["pragma_index_xinfo('wp_network_expr','site')", true, 'after_two.1.key', 0],
    'quoted table-valued expression target row count' => ["pragma_index_xinfo('wp_options_expr_main','main')", true, 'metadata.row_count', 4],
    'quoted table-valued expression target first null name' => ["pragma_index_xinfo('wp_options_expr_main','main')", true, 'first.name', null],
    'quoted table-valued expression target third name' => ["pragma_index_xinfo('wp_options_expr_main','main')", true, 'third.name', 'autoload'],
    'missing expression index defaults main schema' => ['PRAGMA index_xinfo(wp_missing_expr)', false, 'metadata.schema', 'main'],
    'missing expression index has no rows' => ['PRAGMA index_xinfo(wp_missing_expr)', false, 'metadata.row_count', 0],
    'missing expression index current null' => ['PRAGMA index_xinfo(wp_missing_expr)', false, 'first', null],
    'missing expression index metadata eof' => ['PRAGMA index_xinfo(wp_missing_expr)', false, 'metadata.eof', true],
    'missing table-valued expression rows empty' => ["pragma_index_xinfo('wp_missing_expr')", true, 'metadata.row_count', 0],
];

foreach ($schemaCases as $name => [$sql, $tableValued, $path, $expected]) {
    $tests['pragma index_xinfo expression current next35 ' . $name] = static function (TestRunner $t) use ($makeAttachedCatalog, $cursorSnapshot, $valueAt, $sql, $tableValued, $path, $expected): void {
        $snapshot = $cursorSnapshot($makeAttachedCatalog(), $sql, $tableValued);

        $t->same($expected, $valueAt($snapshot, $path));
    };
}

$tests['pragma index_xinfo expression current next35 rejects empty table-valued schema argument'] = static function (TestRunner $t) use ($makeAttachedCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeAttachedCatalog()->executeTableValuedPragmaCursor("pragma_index_xinfo('wp_options_expr_main','')"));
};

return $tests;
