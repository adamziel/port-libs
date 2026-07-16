<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowId,
);

$wpOptionsSql = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL DEFAULT '',
    option_value TEXT DEFAULT '',
    autoload TEXT DEFAULT 'yes',
    option_name_fold TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL,
    option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED NOT NULL,
    option_cache_key TEXT AS (option_name || ':' || autoload),
    option_json_type TEXT AS (json_extract(option_value, '$.type')) STORED,
    option_as_label 'AS TEXT' DEFAULT 'literal-as',
    option_current TEXT DEFAULT CURRENT_TIMESTAMP,
    CHECK(option_name <> 'AS'),
    UNIQUE(option_name)
)
SQL;

$makeCatalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, $wpOptionsSql, 1),
    $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
]);

$makeAttached = static function () use ($record, $wpOptionsSql): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 2, $wpOptionsSql, 1),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    ], [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY, option_value TEXT, temp_fold TEXT AS (lower(option_name)) STORED)', 1),
        $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 5, null, 2),
    ]);

    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, option_value TEXT, archive_key TEXT GENERATED ALWAYS AS (blog_id || ":" || option_name) VIRTUAL)', 1),
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

$tests = [];

foreach ([
    'table-xinfo row count includes visible and generated columns' => ['PRAGMA table_xinfo(wp_options)', 'rows.count', 10],
    'table-info row count omits generated columns only' => ['PRAGMA table_info(wp_options)', 'rows.count', 6],
    'table-info keeps literal as type visible' => ['PRAGMA table_info(wp_options)', 'rows.4.name', 'option_as_label'],
    'table-info keeps current default visible' => ['PRAGMA table_info(wp_options)', 'rows.5.name', 'option_current'],
    'literal as type is not hidden in xinfo' => ['PRAGMA table_xinfo(wp_options)', 'rows.8.hidden', 0],
    'current timestamp default is not hidden in xinfo' => ['PRAGMA table_xinfo(wp_options)', 'rows.9.hidden', 0],
    'verbose virtual generated hidden code' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.hidden', 2],
    'verbose stored generated hidden code' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.hidden', 3],
    'shorthand virtual generated hidden code' => ['PRAGMA table_xinfo(wp_options)', 'rows.6.hidden', 2],
    'shorthand stored generated hidden code' => ['PRAGMA table_xinfo(wp_options)', 'rows.7.hidden', 3],
    'virtual generated column cid preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.cid', 4],
    'stored generated column cid preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.cid', 5],
    'shorthand virtual cid preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.6.cid', 6],
    'shorthand stored cid preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.7.cid', 7],
    'generated virtual type preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.type', 'TEXT'],
    'generated stored type preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.type', 'INTEGER'],
    'generated shorthand text type preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.6.type', 'TEXT'],
    'generated json type preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.7.type', 'TEXT'],
    'stored generated not-null recorded' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.notnull', 1],
    'virtual generated remains nullable' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.notnull', 0],
    'generated default remains null' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.dflt_value', null],
    'stored generated default remains null' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.dflt_value', null],
    'literal as default preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.8.dflt_value', "'literal-as'"],
    'current timestamp default preserved' => ['PRAGMA table_xinfo(wp_options)', 'rows.9.dflt_value', 'CURRENT_TIMESTAMP'],
    'option id primary key visible' => ['PRAGMA table_xinfo(wp_options)', 'rows.0.pk', 1],
    'generated virtual primary key false' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.pk', 0],
    'generated stored primary key false' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.pk', 0],
    'quoted target table-xinfo works' => ['PRAGMA table_xinfo("wp_options")', 'rows.7.name', 'option_json_type'],
    'equals target table-xinfo works' => ['PRAGMA table_xinfo = wp_options', 'rows.6.name', 'option_cache_key'],
    'schema qualified target table-xinfo works' => ['PRAGMA main.table_xinfo(wp_options)', 'schema', 'main'],
    'table-valued table-xinfo row count' => ["pragma_table_xinfo('wp_options')", 'rows.count', 10],
    'table-valued table-xinfo generated name' => ["pragma_table_xinfo('wp_options')", 'rows.4.name', 'option_name_fold'],
    'table-valued table-xinfo stored hidden' => ["pragma_table_xinfo('wp_options')", 'rows.5.hidden', 3],
    'table-valued table-xinfo literal as visible' => ["pragma_table_xinfo('wp_options')", 'rows.8.hidden', 0],
    'table-valued table-info omits verbose generated' => ["pragma_table_info('wp_options')", 'rows.count', 6],
    'table-valued table-info includes literal as visible' => ["pragma_table_info('wp_options')", 'rows.4.name', 'option_as_label'],
    'table-valued table-info includes current visible' => ["pragma_table_info('wp_options')", 'rows.5.name', 'option_current'],
    'table-valued explicit main schema' => ["pragma_table_xinfo('wp_options','main')", 'schema', 'main'],
    'table-valued explicit main stored hidden' => ["pragma_table_xinfo('wp_options','main')", 'rows.7.hidden', 3],
    'missing target returns empty xinfo rows' => ['PRAGMA table_xinfo(missing_options)', 'rows.count', 0],
] as $name => [$sql, $path, $expected]) {
    $tests['pragma table_xinfo generated current next31 ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $method = str_starts_with(strtolower($sql), 'pragma_') ? 'executeTableValuedPragma' : 'execute';

        $t->same($expected, $valueAt($makeCatalog()->{$method}($sql), $path));
    };
}

foreach ([
    'attached unqualified follows temp table' => ["pragma_table_xinfo('wp_options')", 'schema', 'temp'],
    'attached temp row count' => ["pragma_table_xinfo('wp_options')", 'rows.count', 3],
    'attached temp generated stored hidden' => ["pragma_table_xinfo('wp_options')", 'rows.2.hidden', 3],
    'attached explicit main row count' => ["pragma_table_xinfo('wp_options','main')", 'rows.count', 10],
    'attached explicit main virtual hidden' => ["pragma_table_xinfo('wp_options','main')", 'rows.4.hidden', 2],
    'attached explicit archive schema' => ["pragma_table_xinfo('wp_options','archive')", 'schema', 'archive'],
    'attached archive generated hidden' => ["pragma_table_xinfo('wp_options','archive')", 'rows.3.hidden', 2],
    'attached archive generated name' => ["pragma_table_xinfo('wp_options','archive')", 'rows.3.name', 'archive_key'],
    'attached table-info temp omits generated' => ["pragma_table_info('wp_options')", 'rows.count', 2],
    'attached table-info archive omits generated' => ["pragma_table_info('wp_options','archive')", 'rows.count', 3],
] as $name => [$sql, $path, $expected]) {
    $tests['pragma table_xinfo generated current next31 ' . $name] = static function (TestRunner $t) use ($makeAttached, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($makeAttached()->executeTableValuedPragma($sql), $path));
    };
}

$tests['pragma table_xinfo generated current next31 cursor current next walks generated metadata'] = static function (TestRunner $t) use ($makeCatalog): void {
    $cursor = $makeCatalog()->executeCursor('PRAGMA table_xinfo(wp_options)');

    $t->same('table_xinfo', $cursor->metadata()['pragma']);
    $t->same('option_id', $cursor->current()['name']);
    $t->same('option_name', $cursor->next()['name']);
    $t->same('option_value', $cursor->next()['name']);
    $t->same('autoload', $cursor->next()['name']);
    $t->same('option_name_fold', $cursor->next()['name']);
    $t->same(2, $cursor->current()['hidden']);
    $t->same('option_value_len', $cursor->next()['name']);
    $t->same(['option_value_len', 'option_cache_key', 'option_json_type', 'option_as_label', 'option_current'], array_column($cursor->remainingRows(), 'name'));
    $t->same([3, 2, 3, 0, 0], array_column($cursor->remainingRows(), 'hidden'));
};

$tests['pragma table_xinfo generated current next31 table-valued cursor freezes current source'] = static function (TestRunner $t) use ($makeAttached): void {
    $catalog = $makeAttached();
    $cursor = $catalog->executeTableValuedPragmaCursor("pragma_table_xinfo('wp_options','archive')");
    $catalog->detach('archive');

    $t->same('archive', $cursor->metadata()['schema']);
    $t->same('blog_id', $cursor->current()['name']);
    $t->same('option_name', $cursor->next()['name']);
    $t->same('option_value', $cursor->next()['name']);
    $t->same('archive_key', $cursor->next()['name']);
    $t->same(2, $cursor->current()['hidden']);
    $t->same(null, $cursor->next());
    $t->same(true, $cursor->metadata()['eof']);
};

return $tests;
