<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record181 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords181 = [
    $record181('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record181('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, locale TEXT, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record181('table', 'wp_slug_registry', 'wp_slug_registry', 6, 'CREATE TABLE wp_slug_registry(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, title TEXT)', 3),
    $record181('table', 'wp_defaults', 'wp_defaults', 7, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 4),
    $record181('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites, option_name TEXT, blog_id TEXT, slug TEXT, locale TEXT, fallback_name TEXT, orphan_name TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(slug, locale) REFERENCES wp_slug_registry(slug, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name), FOREIGN KEY(orphan_name) REFERENCES wp_missing_defaults(default_name))', 5),
    $record181('table', 'wp_missing_defaults', 'wp_missing_defaults', 9, 'CREATE TABLE wp_missing_defaults(default_name TEXT, enabled INTEGER)', 6),
    $record181('index', 'wp_option_names_lookup', 'wp_option_names', 10, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 7),
    $record181('index', 'wp_slug_registry_lookup', 'wp_slug_registry', 11, 'CREATE UNIQUE INDEX wp_slug_registry_lookup ON wp_slug_registry(slug, locale COLLATE RTRIM)', 8),
    $record181('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 12, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 9),
    $record181('index', 'wp_options_lookup', 'wp_options', 13, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id, fallback_name)', 10),
];
$nextRecords181 = $currentRecords181;
$nextRecords181[7] = $record181('index', 'wp_slug_registry_lookup', 'wp_slug_registry', 11, 'CREATE UNIQUE INDEX wp_slug_registry_lookup ON wp_slug_registry(slug COLLATE NOCASE, locale COLLATE RTRIM)', 8);
$nextRecords181[5] = $record181('table', 'wp_missing_defaults', 'wp_missing_defaults', 9, 'CREATE TABLE wp_missing_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 6);
$nextRecords181[] = $record181('index', 'sqlite_autoindex_wp_missing_defaults_1', 'wp_missing_defaults', 14, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_missing_defaults_1 ON wp_missing_defaults(default_name)', 11);

$currentTables181 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US']],
    'wp_slug_registry' => [['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'title' => 'Home']],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_missing_defaults' => [['rowid' => 1, 'default_name' => 'core_missing', 'enabled' => 0]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'blog_id' => '1', 'slug' => 'home', 'locale' => 'en_US', 'fallback_name' => 'siteurl', 'orphan_name' => 'core_missing', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => '404', 'option_name' => 'home', 'blog_id' => '1', 'slug' => 'dashboard', 'locale' => 'en_US', 'fallback_name' => 'missing_default', 'orphan_name' => 'plugin_missing', 'autoload' => 'yes'],
    ],
];
$nextTables181 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US'],
        ['name' => 'home', 'blog_id' => 1, 'locale' => 'en_US'],
    ],
    'wp_slug_registry' => [
        ['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'title' => 'Home'],
        ['rowid' => 2, 'slug' => 'dashboard', 'locale' => 'en_US', 'title' => 'Dashboard'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_missing_defaults' => [
        ['rowid' => 1, 'default_name' => 'core_missing', 'enabled' => 0],
        ['rowid' => 2, 'default_name' => 'plugin_missing', 'enabled' => 0],
    ],
    'wp_options' => $currentTables181['wp_options'],
];

$page181 = static fn (
    int $offset = 0,
    int $limit = 181,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog181(
    $currentRecords181,
    $currentTables181,
    $nextRecords ?? $nextRecords181,
    $nextTables ?? $nextTables181,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt181 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default181 = static fn (): array => $page181();
$blocked181 = static fn (): array => $page181(nextRecords: $currentRecords181, nextTables: $currentTables181);
$collationRows181 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows181($currentRecords181);

$cases181 = [
    'status ok after collation repair' => [$default181, 'status', 'ok'],
    'limit default' => [$default181, 'limit', 181],
    'total rows include collation rows' => [$default181, 'total', 65],
    'count rows include collation rows' => [$default181, 'count', 65],
    'complete true' => [$default181, 'complete', true],
    'next null' => [$default181, 'next', null],
    'current source collation kind' => [$default181, 'current_source.foreign_key_parent_collation_source', 'pragma_index_xinfo_parent_collation_columns'],
    'next source collation kind' => [$default181, 'next_source.foreign_key_parent_collation_source', 'pragma_index_xinfo_parent_collation_columns'],
    'current collation rows' => [$default181, 'current.foreign_key_parent_collation_rows', 7],
    'next collation rows' => [$default181, 'next_counts.foreign_key_parent_collation_rows', 7],
    'current matched count' => [$default181, 'current.foreign_key_parent_collations.matched', 5],
    'current mismatch count' => [$default181, 'current.foreign_key_parent_collations.mismatched', 1],
    'current missing count' => [$default181, 'current.foreign_key_parent_collations.missing_parent_key', 1],
    'next matched count' => [$default181, 'next_counts.foreign_key_parent_collations.matched', 7],
    'next mismatch cleared' => [$default181, 'next_counts.foreign_key_parent_collations.mismatched', 0],
    'next missing cleared' => [$default181, 'next_counts.foreign_key_parent_collations.missing_parent_key', 0],
    'current nocase parents' => [$default181, 'current.foreign_key_parent_collations.nocase', 2],
    'current rtrim parents' => [$default181, 'current.foreign_key_parent_collations.rtrim', 1],
    'current binary parents' => [$default181, 'current.foreign_key_parent_collations.binary', 4],
    'delta rows unchanged' => [$default181, 'delta.foreign_key_parent_collation_rows', 0],
    'delta changed true' => [$default181, 'delta.foreign_key_parent_collation_changed', true],
    'mismatch delta negative' => [$default181, 'delta.foreign_key_parent_collation_mismatch_delta', -1],
    'delta cleared true' => [$default181, 'delta.cleared', true],
    'next ready true' => [$default181, 'next_state.ready', true],
    'row51 first collation kind' => [$default181, 'rows.51.kind', 'foreign_key_parent_collation'],
    'row51 site rowid status' => [$default181, 'rows.51.status', 'ok'],
    'row51 site parent collation' => [$default181, 'rows.51.parent_collation', 'BINARY'],
    'row52 option name nocase' => [$default181, 'rows.52.parent_collation', 'NOCASE'],
    'row52 option name index nocase' => [$default181, 'rows.52.index_collation', 'NOCASE'],
    'row53 blog id binary' => [$default181, 'rows.53.parent_collation', 'BINARY'],
    'row54 slug mismatch status' => [$default181, 'rows.54.status', 'collation_mismatch'],
    'row54 slug parent nocase' => [$default181, 'rows.54.parent_collation', 'NOCASE'],
    'row54 slug index binary' => [$default181, 'rows.54.index_collation', 'BINARY'],
    'row55 locale rtrim status' => [$default181, 'rows.55.status', 'ok'],
    'row55 locale rtrim index' => [$default181, 'rows.55.index_collation', 'RTRIM'],
    'row56 fallback binary' => [$default181, 'rows.56.parent_collation', 'BINARY'],
    'row57 missing parent key status' => [$default181, 'rows.57.status', 'missing_parent_key'],
    'row58 next site side' => [$default181, 'rows.58.side', 'next'],
    'row61 next repaired slug status' => [$default181, 'rows.61.status', 'ok'],
    'row61 next repaired slug index' => [$default181, 'rows.61.index_collation', 'NOCASE'],
    'row62 next locale status' => [$default181, 'rows.62.status', 'ok'],
    'row64 next missing parent repaired' => [$default181, 'rows.64.status', 'ok'],
    'blocked status remains blocked' => [$blocked181, 'status', 'blocked'],
    'blocked current mismatch remains' => [$blocked181, 'next_counts.foreign_key_parent_collations.mismatched', 1],
    'blocked missing remains' => [$blocked181, 'next_counts.foreign_key_parent_collations.missing_parent_key', 1],
    'helper first kind' => [$collationRows181, '0.kind', 'foreign_key_parent_collation'],
    'helper rowid collation' => [$collationRows181, '0.parent_collation', 'BINARY'],
    'helper slug mismatch' => [$collationRows181, '3.status', 'collation_mismatch'],
    'helper locale rtrim' => [$collationRows181, '4.parent_collation', 'RTRIM'],
];

$tests = [];
foreach ($cases181 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent collation current source next181 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt181): void {
        $t->same($expected, $valueAt181($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent collation current source next181 paginates into collation rows'] = static function (TestRunner $t) use ($page181): void {
    $first = $page181(0, 51);
    $second = $page181(51, 7, $first['next']);
    $third = $page181(58, 7, $second['next']);

    $t->same(51, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 51], $first['next']);
    $t->same('foreign_key_parent_collation', $second['rows'][0]['kind']);
    $t->same('collation_mismatch', $second['rows'][3]['status']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent collation current source next181 table valued index source keeps collation rows'] = static function (TestRunner $t) use ($page181): void {
    $result = $page181(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same(7, $result['current']['foreign_key_parent_collation_rows']);
    $t->same('foreign_key_parent_collation', $result['rows'][51]['kind']);
};

$tests['pragma index xinfo foreignkey parent collation current source next181 source changes with collation ddl'] = static function (TestRunner $t) use ($page181, $nextRecords181, $record181): void {
    $changed = $nextRecords181;
    $changed[2] = $record181('table', 'wp_slug_registry', 'wp_slug_registry', 6, 'CREATE TABLE wp_slug_registry(slug TEXT COLLATE RTRIM, locale TEXT COLLATE RTRIM, title TEXT)', 3);

    $first = $page181();
    $second = $page181(nextRecords: $changed);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $second['delta']['foreign_key_parent_collation_changed']);
};

$tests['pragma index xinfo foreignkey parent collation current source next181 rejects stale cursor'] = static function (TestRunner $t) use ($page181, $currentRecords181): void {
    $first = $page181(0, 51);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page181(51, 7, $first['next'], nextRecords: $currentRecords181));
};

$tests['pragma index xinfo foreignkey parent collation current source next181 rejects stale offset cursor'] = static function (TestRunner $t) use ($page181): void {
    $first = $page181(0, 51);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page181(52, 7, $first['next']));
};

$tests['pragma index xinfo foreignkey parent collation current source next181 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeyCollationRows181([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey parent collation current source next181 rejects negative offset'] = static function (TestRunner $t) use ($page181): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page181(offset: -1));
};

$tests['pragma index xinfo foreignkey parent collation current source next181 rejects zero limit'] = static function (TestRunner $t) use ($page181): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page181(limit: 0));
};

return $tests;
