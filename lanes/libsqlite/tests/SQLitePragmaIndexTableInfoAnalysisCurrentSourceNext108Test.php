<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIndexTableInfoAnalysis;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$makeCatalog = static function (bool $withGenerated = true) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL DEFAULT '', option_value TEXT, autoload TEXT DEFAULT 'yes')", 1),
            $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE INDEX wp_options_name_main ON wp_options(option_name COLLATE NOCASE DESC)', 2),
            $record('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)', 3),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 5, $withGenerated
                ? "CREATE TABLE wp_options(option_name TEXT NOT NULL, option_value TEXT DEFAULT '{}', autoload TEXT, option_name_fold TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL, option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED)"
                : "CREATE TABLE wp_options(option_name TEXT NOT NULL, option_value TEXT DEFAULT '{}', autoload TEXT)", 1),
            $record('index', 'wp_options_name_temp', 'wp_options', 6, 'CREATE INDEX wp_options_name_temp ON wp_options(option_name, length(option_value) COLLATE BINARY DESC)', 2),
        ],
    );
    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, option_value TEXT)', 1),
        $record('index', 'wp_options_archive_name', 'wp_options', 8, 'CREATE INDEX wp_options_archive_name ON wp_options(blog_id, option_name COLLATE RTRIM)', 2),
        $record('table', 'wp_sitemeta', 'wp_sitemeta', 9, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT NOT NULL, meta_value TEXT)', 3),
        $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 10, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)', 4),
    ]);

    return $catalog;
};

$sqls = [
    'PRAGMA table_info(wp_options)',
    'PRAGMA table_xinfo(wp_options)',
    'PRAGMA index_info(wp_options_name_temp)',
    'PRAGMA index_xinfo(wp_options_name_temp)',
    'PRAGMA main.table_info(wp_options)',
    'pragma_index_xinfo("wp_options_archive_name", "archive")',
    'pragma_table_info("wp_sitemeta")',
];

$page = static fn (?array $resume = null, int $offset = 0, int $limit = 20): array => SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($makeCatalog(), $sqls, $offset, $limit, $resume);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$tests = [];

foreach ([
    'status' => ['status', 'ok'],
    'row count' => ['row_count', 7],
    'next offset capped' => ['next_offset', 7],
    'temp table-info schema' => ['analyses.0.schema', 'temp'],
    'temp table-info pragma' => ['analyses.0.pragma', 'table_info'],
    'temp table-info visible columns' => ['analyses.0.visible_columns', 3],
    'temp table-info generated omitted' => ['analyses.0.generated_columns', 0],
    'temp table-info notnull columns' => ['analyses.0.notnull_columns', 1],
    'temp table-info default columns' => ['analyses.0.default_columns', 1],
    'temp table-info row names' => ['analyses.0.row_names', ['option_name', 'option_value', 'autoload']],
    'temp table-xinfo schema' => ['analyses.1.schema', 'temp'],
    'temp table-xinfo visible columns' => ['analyses.1.visible_columns', 3],
    'temp table-xinfo generated count' => ['analyses.1.generated_columns', 2],
    'temp table-xinfo hidden codes' => ['analyses.1.hidden_codes', [0, 0, 0, 2, 3]],
    'temp table-xinfo row names' => ['analyses.1.row_names', ['option_name', 'option_value', 'autoload', 'option_name_fold', 'option_value_len']],
    'temp index-info schema' => ['analyses.2.schema', 'temp'],
    'temp index-info key columns' => ['analyses.2.key_columns', 2],
    'temp index-info expression columns' => ['analyses.2.expression_columns', 1],
    'temp index-info collations default' => ['analyses.2.collations', ['BINARY']],
    'temp index-xinfo schema' => ['analyses.3.schema', 'temp'],
    'temp index-xinfo row count' => ['analyses.3.row_count', 3],
    'temp index-xinfo key columns' => ['analyses.3.key_columns', 2],
    'temp index-xinfo auxiliary columns' => ['analyses.3.auxiliary_columns', 1],
    'temp index-xinfo expression columns' => ['analyses.3.expression_columns', 1],
    'temp index-xinfo rowid auxiliary' => ['analyses.3.rowid_auxiliary', 1],
    'temp index-xinfo descending columns' => ['analyses.3.descending_columns', 1],
    'temp index-xinfo collations' => ['analyses.3.collations', ['BINARY']],
    'temp index-xinfo row names' => ['analyses.3.row_names', ['option_name', null, null]],
    'main table-info schema' => ['analyses.4.schema', 'main'],
    'main table-info row names' => ['analyses.4.row_names', ['option_id', 'option_name', 'option_value', 'autoload']],
    'main table-info primary key count' => ['analyses.4.primary_key_columns', 1],
    'main table-info notnull count' => ['analyses.4.notnull_columns', 2],
    'main table-info default count' => ['analyses.4.default_columns', 2],
    'archive index-xinfo schema' => ['analyses.5.schema', 'archive'],
    'archive index-xinfo row names' => ['analyses.5.row_names', ['blog_id', 'option_name', null]],
    'archive index-xinfo key count' => ['analyses.5.key_columns', 2],
    'archive index-xinfo auxiliary count' => ['analyses.5.auxiliary_columns', 1],
    'archive index-xinfo rtrim collation' => ['analyses.5.collations', ['BINARY', 'RTRIM']],
    'sitemeta table-info schema' => ['analyses.6.schema', 'archive'],
    'sitemeta table-info row names' => ['analyses.6.row_names', ['meta_id', 'meta_key', 'meta_value']],
    'sitemeta table-info primary key count' => ['analyses.6.primary_key_columns', 1],
    'sitemeta table-info notnull count' => ['analyses.6.notnull_columns', 2],
] as $name => [$path, $expected]) {
    $tests['pragma index xinfo tableinfo analysis current source next108 ' . $name] = static function (TestRunner $t) use ($page, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($page(), $path));
    };
}

$tests['pragma index xinfo tableinfo analysis current source next108 paginates stable source'] = static function (TestRunner $t) use ($page): void {
    $first = $page(null, 0, 3);
    $second = $page(['source_id' => $first['source_id'], 'next_offset' => 3], 3, 3);
    $third = $page(['source_id' => $second['source_id'], 'next_offset' => 6], 6, 3);

    $t->same(3, count($first['analyses']));
    $t->same(3, count($second['analyses']));
    $t->same(1, count($third['analyses']));
    $t->same('table_info', $first['analyses'][0]['pragma']);
    $t->same('table_info', $third['analyses'][0]['pragma']);
    $t->same($first['source_id'], $third['source_id']);
};

$tests['pragma index xinfo tableinfo analysis current source next108 source changes when schema rows change'] = static function (TestRunner $t) use ($makeCatalog, $sqls): void {
    $withGenerated = SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($makeCatalog(true), $sqls, 0, 20);
    $withoutGenerated = SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($makeCatalog(false), $sqls, 0, 20);

    $t->same(true, $withGenerated['source_id'] !== $withoutGenerated['source_id']);
    $t->same(2, $withGenerated['analyses'][1]['generated_columns']);
    $t->same(0, $withoutGenerated['analyses'][1]['generated_columns']);
};

$tests['pragma index xinfo tableinfo analysis current source next108 rejects stale source cursor'] = static function (TestRunner $t) use ($makeCatalog, $sqls): void {
    $first = SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($makeCatalog(true), $sqls, 0, 2);

    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($makeCatalog(false), $sqls, 2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]));
};

$tests['pragma index xinfo tableinfo analysis current source next108 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(null, 0, 2);

    $t->throws(InvalidArgumentException::class, static fn () => $page(['source_id' => $first['source_id'], 'next_offset' => 2], 3, 2));
};

$tests['pragma index xinfo tableinfo analysis current source next108 accepts source only cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(null, 0, 2);
    $second = $page(['source_id' => $first['source_id']], 4, 2);

    $t->same(['table_info', 'index_xinfo'], array_column($second['analyses'], 'pragma'));
};

$tests['pragma index xinfo tableinfo analysis current source next108 rejects unsupported pragma'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($makeCatalog(), ['PRAGMA foreign_key_list(wp_options)'], 0, 1));
};

$tests['pragma index xinfo tableinfo analysis current source next108 rejects bad offset and limit'] = static function (TestRunner $t) use ($makeCatalog, $sqls): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($makeCatalog(), $sqls, -1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($makeCatalog(), $sqls, 0, 0));
};

$tests['pragma index xinfo tableinfo analysis current source next108 rejects empty statement'] = static function (TestRunner $t) use ($makeCatalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIndexTableInfoAnalysis::currentSourcePage($makeCatalog(), [''], 0, 1));
};

return $tests;
