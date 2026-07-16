<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record184 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords184 = [
    $record184('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record184('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, locale TEXT, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record184('table', 'wp_slug_registry', 'wp_slug_registry', 6, 'CREATE TABLE wp_slug_registry(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, title TEXT)', 3),
    $record184('table', 'wp_defaults', 'wp_defaults', 7, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 4),
    $record184('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites, option_name TEXT, blog_id TEXT, slug TEXT, locale TEXT, fallback_name TEXT, orphan_name TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(slug, locale) REFERENCES wp_slug_registry(slug, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name), FOREIGN KEY(orphan_name) REFERENCES wp_missing_defaults(default_name))', 5),
    $record184('table', 'wp_missing_defaults', 'wp_missing_defaults', 9, 'CREATE TABLE wp_missing_defaults(default_name TEXT, enabled INTEGER)', 6),
    $record184('index', 'wp_option_names_lookup', 'wp_option_names', 10, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE DESC, blog_id)', 7),
    $record184('index', 'wp_slug_registry_lookup', 'wp_slug_registry', 11, 'CREATE UNIQUE INDEX wp_slug_registry_lookup ON wp_slug_registry(slug DESC, locale COLLATE RTRIM DESC)', 8),
    $record184('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 12, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 9),
    $record184('index', 'wp_options_lookup', 'wp_options', 13, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id, fallback_name)', 10),
];
$nextRecords184 = $currentRecords184;
$nextRecords184[6] = $record184('index', 'wp_option_names_lookup', 'wp_option_names', 10, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 7);
$nextRecords184[7] = $record184('index', 'wp_slug_registry_lookup', 'wp_slug_registry', 11, 'CREATE UNIQUE INDEX wp_slug_registry_lookup ON wp_slug_registry(slug COLLATE NOCASE, locale COLLATE RTRIM)', 8);
$nextRecords184[5] = $record184('table', 'wp_missing_defaults', 'wp_missing_defaults', 9, 'CREATE TABLE wp_missing_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 6);
$nextRecords184[] = $record184('index', 'sqlite_autoindex_wp_missing_defaults_1', 'wp_missing_defaults', 14, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_missing_defaults_1 ON wp_missing_defaults(default_name)', 11);

$currentTables184 = [
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
$nextTables184 = [
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
    'wp_options' => $currentTables184['wp_options'],
];

$page184 = static fn (
    int $offset = 0,
    int $limit = 184,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog184(
    $currentRecords184,
    $currentTables184,
    $nextRecords ?? $nextRecords184,
    $nextTables ?? $nextTables184,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt184 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default184 = static fn (): array => $page184();
$blocked184 = static fn (): array => $page184(nextRecords: $currentRecords184, nextTables: $currentTables184);
$sortRows184 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeySortRows184($currentRecords184);

$cases184 = [
    'status ok after sort repair' => [$default184, 'status', 'ok'],
    'limit default' => [$default184, 'limit', 184],
    'total rows include sort rows' => [$default184, 'total', 79],
    'count rows include sort rows' => [$default184, 'count', 79],
    'complete true' => [$default184, 'complete', true],
    'next null' => [$default184, 'next', null],
    'current source sort kind' => [$default184, 'current_source.foreign_key_parent_sort_source', 'pragma_index_xinfo_parent_key_desc_columns'],
    'next source sort kind' => [$default184, 'next_source.foreign_key_parent_sort_source', 'pragma_index_xinfo_parent_key_desc_columns'],
    'current sort rows' => [$default184, 'current.foreign_key_parent_sort_rows', 7],
    'next sort rows' => [$default184, 'next_counts.foreign_key_parent_sort_rows', 7],
    'current sort count rows' => [$default184, 'current.foreign_key_parent_sort.rows', 7],
    'current asc count' => [$default184, 'current.foreign_key_parent_sort.asc', 3],
    'current desc count' => [$default184, 'current.foreign_key_parent_sort.desc', 3],
    'current missing count' => [$default184, 'current.foreign_key_parent_sort.missing_parent_key', 1],
    'next asc count' => [$default184, 'next_counts.foreign_key_parent_sort.asc', 7],
    'next desc count cleared' => [$default184, 'next_counts.foreign_key_parent_sort.desc', 0],
    'next missing cleared' => [$default184, 'next_counts.foreign_key_parent_sort.missing_parent_key', 0],
    'delta rows unchanged' => [$default184, 'delta.foreign_key_parent_sort_rows', 0],
    'delta sort changed true' => [$default184, 'delta.foreign_key_parent_sort_changed', true],
    'delta desc negative' => [$default184, 'delta.foreign_key_parent_desc_delta', -3],
    'delta collation still changed' => [$default184, 'delta.foreign_key_parent_collation_changed', true],
    'delta cleared true' => [$default184, 'delta.cleared', true],
    'next ready true' => [$default184, 'next_state.ready', true],
    'row65 first sort kind' => [$default184, 'rows.65.kind', 'foreign_key_parent_sort'],
    'row65 site asc' => [$default184, 'rows.65.sort_order', 'ASC'],
    'row65 rowid index' => [$default184, 'rows.65.index', 'rowid-primary-key'],
    'row66 option name desc' => [$default184, 'rows.66.sort_order', 'DESC'],
    'row66 option name desc bit' => [$default184, 'rows.66.index_desc', 1],
    'row67 blog id asc' => [$default184, 'rows.67.sort_order', 'ASC'],
    'row68 slug desc' => [$default184, 'rows.68.sort_order', 'DESC'],
    'row69 locale desc' => [$default184, 'rows.69.sort_order', 'DESC'],
    'row70 fallback asc' => [$default184, 'rows.70.sort_order', 'ASC'],
    'row71 missing parent unmapped' => [$default184, 'rows.71.sort_order', 'unmapped'],
    'row71 missing parent status' => [$default184, 'rows.71.status', 'missing_parent_key'],
    'row72 next site side' => [$default184, 'rows.72.side', 'next'],
    'row73 next option name asc' => [$default184, 'rows.73.sort_order', 'ASC'],
    'row75 next slug asc' => [$default184, 'rows.75.sort_order', 'ASC'],
    'row76 next locale asc' => [$default184, 'rows.76.sort_order', 'ASC'],
    'row78 next missing repaired asc' => [$default184, 'rows.78.sort_order', 'ASC'],
    'blocked status remains blocked' => [$blocked184, 'status', 'blocked'],
    'blocked next desc remains' => [$blocked184, 'next_counts.foreign_key_parent_sort.desc', 3],
    'blocked missing remains' => [$blocked184, 'next_counts.foreign_key_parent_sort.missing_parent_key', 1],
    'helper first kind' => [$sortRows184, '0.kind', 'foreign_key_parent_sort'],
    'helper rowid asc' => [$sortRows184, '0.sort_order', 'ASC'],
    'helper option desc' => [$sortRows184, '1.sort_order', 'DESC'],
    'helper locale desc' => [$sortRows184, '4.sort_order', 'DESC'],
    'helper missing unmapped' => [$sortRows184, '6.sort_order', 'unmapped'],
];

$tests = [];
foreach ($cases184 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent sort current source next184 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt184): void {
        $t->same($expected, $valueAt184($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent sort current source next184 paginates into sort rows'] = static function (TestRunner $t) use ($page184): void {
    $first = $page184(0, 65);
    $second = $page184(65, 7, $first['next']);
    $third = $page184(72, 7, $second['next']);

    $t->same(65, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 65], $first['next']);
    $t->same('foreign_key_parent_sort', $second['rows'][0]['kind']);
    $t->same('DESC', $second['rows'][1]['sort_order']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent sort current source next184 table valued index source keeps sort rows'] = static function (TestRunner $t) use ($page184): void {
    $result = $page184(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same(7, $result['current']['foreign_key_parent_sort_rows']);
    $t->same('foreign_key_parent_sort', $result['rows'][65]['kind']);
};

$tests['pragma index xinfo foreignkey parent sort current source next184 source changes with desc ddl'] = static function (TestRunner $t) use ($page184, $nextRecords184, $record184): void {
    $changed = $nextRecords184;
    $changed[6] = $record184('index', 'wp_option_names_lookup', 'wp_option_names', 10, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE DESC, blog_id)', 7);

    $first = $page184();
    $second = $page184(nextRecords: $changed);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $second['delta']['foreign_key_parent_sort_changed']);
};

$tests['pragma index xinfo foreignkey parent sort current source next184 rejects stale cursor'] = static function (TestRunner $t) use ($page184, $currentRecords184): void {
    $first = $page184(0, 65);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page184(65, 7, $first['next'], nextRecords: $currentRecords184));
};

$tests['pragma index xinfo foreignkey parent sort current source next184 rejects stale offset cursor'] = static function (TestRunner $t) use ($page184): void {
    $first = $page184(0, 65);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page184(66, 7, $first['next']));
};

$tests['pragma index xinfo foreignkey parent sort current source next184 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeySortRows184([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey parent sort current source next184 rejects negative offset'] = static function (TestRunner $t) use ($page184): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page184(offset: -1));
};

$tests['pragma index xinfo foreignkey parent sort current source next184 rejects zero limit'] = static function (TestRunner $t) use ($page184): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page184(limit: 0));
};

return $tests;
