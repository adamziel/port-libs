<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record178 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords178 = [
    $record178('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record178('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, locale TEXT, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record178('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record178('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites, option_name TEXT, blog_id TEXT, fallback_name TEXT, orphan_name TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name), FOREIGN KEY(orphan_name) REFERENCES wp_missing_defaults(default_name))', 4),
    $record178('table', 'wp_missing_defaults', 'wp_missing_defaults', 8, 'CREATE TABLE wp_missing_defaults(default_name TEXT, enabled INTEGER)', 5),
    $record178('index', 'wp_option_names_lookup', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 6),
    $record178('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 10, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 7),
    $record178('index', 'wp_options_lookup', 'wp_options', 11, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id, fallback_name)', 8),
];
$nextRecords178 = [
    $currentRecords178[0],
    $currentRecords178[1],
    $currentRecords178[2],
    $currentRecords178[3],
    $record178('table', 'wp_missing_defaults', 'wp_missing_defaults', 8, 'CREATE TABLE wp_missing_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 5),
    $currentRecords178[5],
    $currentRecords178[6],
    $currentRecords178[7],
    $record178('index', 'sqlite_autoindex_wp_missing_defaults_1', 'wp_missing_defaults', 12, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_missing_defaults_1 ON wp_missing_defaults(default_name)', 9),
];

$currentTables178 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US']],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_missing_defaults' => [['rowid' => 1, 'default_name' => 'core_missing', 'enabled' => 0]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'blog_id' => '1', 'fallback_name' => 'siteurl', 'orphan_name' => 'core_missing', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => '404', 'option_name' => 'home', 'blog_id' => '1', 'fallback_name' => 'missing_default', 'orphan_name' => 'plugin_missing', 'autoload' => 'yes'],
    ],
];
$nextTables178 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US'],
        ['name' => 'home', 'blog_id' => 1, 'locale' => 'en_US'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_missing_defaults' => [
        ['rowid' => 1, 'default_name' => 'core_missing', 'enabled' => 0],
        ['rowid' => 2, 'default_name' => 'plugin_missing', 'enabled' => 0],
    ],
    'wp_options' => $currentTables178['wp_options'],
];

$page178 = static fn (
    int $offset = 0,
    int $limit = 178,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog178(
    $currentRecords178,
    $currentTables178,
    $nextRecords ?? $nextRecords178,
    $nextTables ?? $nextTables178,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt178 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default178 = static fn (): array => $page178();
$blocked178 = static fn (): array => $page178(nextRecords: $currentRecords178, nextTables: $currentTables178);
$parentRows178 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyRows178($currentRecords178);

$cases178 = [
    'status ok after parent key repair' => [$default178, 'status', 'ok'],
    'limit default' => [$default178, 'limit', 178],
    'total rows include parent keys' => [$default178, 'total', 40],
    'count rows include parent keys' => [$default178, 'count', 40],
    'complete true' => [$default178, 'complete', true],
    'next null' => [$default178, 'next', null],
    'current source parent key kind' => [$default178, 'current_source.foreign_key_parent_key_source', 'pragma_index_xinfo_parent_key_columns'],
    'next source parent key kind' => [$default178, 'next_source.foreign_key_parent_key_source', 'pragma_index_xinfo_parent_key_columns'],
    'current parent key rows' => [$default178, 'current.foreign_key_parent_key_rows', 5],
    'next parent key rows' => [$default178, 'next_counts.foreign_key_parent_key_rows', 5],
    'current mapped parent keys' => [$default178, 'current.foreign_key_parent_key_columns.mapped', 4],
    'current missing parent key' => [$default178, 'current.foreign_key_parent_key_columns.missing_parent_key', 1],
    'next mapped parent keys' => [$default178, 'next_counts.foreign_key_parent_key_columns.mapped', 5],
    'next missing parent key cleared' => [$default178, 'next_counts.foreign_key_parent_key_columns.missing_parent_key', 0],
    'rowid parent key count current' => [$default178, 'current.foreign_key_parent_key_columns.rowid_parent_key', 1],
    'rowid parent key count next' => [$default178, 'next_counts.foreign_key_parent_key_columns.rowid_parent_key', 1],
    'auxiliary columns ignored current' => [$default178, 'current.foreign_key_parent_key_columns.auxiliary_columns_ignored', 1],
    'auxiliary columns ignored next' => [$default178, 'next_counts.foreign_key_parent_key_columns.auxiliary_columns_ignored', 2],
    'delta parent key rows unchanged' => [$default178, 'delta.foreign_key_parent_key_rows', 0],
    'delta parent key changed true' => [$default178, 'delta.foreign_key_parent_key_changed', true],
    'delta cleared true' => [$default178, 'delta.cleared', true],
    'next ready true' => [$default178, 'next_state.ready', true],
    'row30 site parent key kind' => [$default178, 'rows.30.kind', 'foreign_key_parent_key'],
    'row30 site rowid index' => [$default178, 'rows.30.index', 'rowid-primary-key'],
    'row30 site cid' => [$default178, 'rows.30.index_cid', 0],
    'row30 site key flag' => [$default178, 'rows.30.index_key', 1],
    'row31 composite index' => [$default178, 'rows.31.index', 'wp_option_names_lookup'],
    'row31 composite seqno' => [$default178, 'rows.31.index_seqno', 0],
    'row31 composite cid' => [$default178, 'rows.31.index_cid', 0],
    'row31 composite name' => [$default178, 'rows.31.index_name', 'name'],
    'row31 composite coll' => [$default178, 'rows.31.index_coll', 'NOCASE'],
    'row31 composite auxiliary ignored' => [$default178, 'rows.31.auxiliary_columns_ignored', 0],
    'row32 composite seqno' => [$default178, 'rows.32.index_seqno', 1],
    'row32 composite name' => [$default178, 'rows.32.index_name', 'blog_id'],
    'row33 fallback autoindex' => [$default178, 'rows.33.index', 'sqlite_autoindex_wp_defaults_1'],
    'row33 fallback status' => [$default178, 'rows.33.status', 'ok'],
    'row34 missing parent status' => [$default178, 'rows.34.status', 'missing_parent_key'],
    'row34 missing parent index' => [$default178, 'rows.34.index', null],
    'row35 next site side' => [$default178, 'rows.35.side', 'next'],
    'row36 next composite side' => [$default178, 'rows.36.side', 'next'],
    'row38 next fallback side' => [$default178, 'rows.38.side', 'next'],
    'row39 next repaired index' => [$default178, 'rows.39.index', 'sqlite_autoindex_wp_missing_defaults_1'],
    'row39 next status ok' => [$default178, 'rows.39.status', 'ok'],
    'blocked status remains blocked' => [$blocked178, 'status', 'blocked'],
    'blocked missing parent key remains' => [$blocked178, 'next_counts.foreign_key_parent_key_columns.missing_parent_key', 1],
    'helper row count' => [$parentRows178, '0.kind', 'foreign_key_parent_key'],
    'helper rowid first' => [$parentRows178, '0.index', 'rowid-primary-key'],
    'helper composite first' => [$parentRows178, '1.index_name', 'name'],
    'helper composite second' => [$parentRows178, '2.index_name', 'blog_id'],
    'helper missing last' => [$parentRows178, '4.status', 'missing_parent_key'],
];

$tests = [];
foreach ($cases178 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent key current source next178 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt178): void {
        $t->same($expected, $valueAt178($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent key current source next178 paginates into parent key rows'] = static function (TestRunner $t) use ($page178): void {
    $first = $page178(0, 30);
    $second = $page178(30, 5, $first['next']);
    $third = $page178(35, 5, $second['next']);

    $t->same(30, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 30], $first['next']);
    $t->same('foreign_key_parent_key', $second['rows'][0]['kind']);
    $t->same('blog_id', $second['rows'][0]['to']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent key current source next178 table valued index source keeps parent key rows'] = static function (TestRunner $t) use ($page178): void {
    $result = $page178(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same(5, $result['current']['foreign_key_parent_key_rows']);
    $t->same('foreign_key_parent_key', $result['rows'][39]['kind']);
};

$tests['pragma index xinfo foreignkey parent key current source next178 source changes with parent key ddl'] = static function (TestRunner $t) use ($page178, $currentRecords178, $record178): void {
    $nextRecords = $currentRecords178;
    $nextRecords[5] = $record178('index', 'wp_option_names_lookup', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(blog_id, name COLLATE NOCASE)', 6);

    $first = $page178();
    $second = $page178(nextRecords: $nextRecords);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $second['delta']['foreign_key_parent_key_changed']);
};

$tests['pragma index xinfo foreignkey parent key current source next178 rejects stale parent key cursor'] = static function (TestRunner $t) use ($page178, $currentRecords178): void {
    $first = $page178(0, 30);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page178(30, 5, $first['next'], nextRecords: $currentRecords178));
};

$tests['pragma index xinfo foreignkey parent key current source next178 rejects stale offset cursor'] = static function (TestRunner $t) use ($page178): void {
    $first = $page178(0, 30);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page178(31, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey parent key current source next178 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyRows178([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey parent key current source next178 rejects negative offset'] = static function (TestRunner $t) use ($page178): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page178(offset: -1));
};

$tests['pragma index xinfo foreignkey parent key current source next178 rejects zero limit'] = static function (TestRunner $t) use ($page178): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page178(limit: 0));
};

return $tests;
