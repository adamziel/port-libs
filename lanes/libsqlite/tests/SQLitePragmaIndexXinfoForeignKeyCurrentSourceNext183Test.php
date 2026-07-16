<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record183 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords183 = [
    $record183('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record183('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record183('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record183('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites(blog_id), option_name TEXT, blog_id TEXT, fallback_name TEXT, orphan_name TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name), FOREIGN KEY(orphan_name) REFERENCES wp_defaults(default_name))', 4),
    $record183('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
    $record183('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 9, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 6),
    $record183('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id, autoload)', 7),
    $record183('index', 'wp_options_fallback_lookup', 'wp_options', 11, 'CREATE UNIQUE INDEX wp_options_fallback_lookup ON wp_options(fallback_name, option_id)', 8),
];
$nextRecords183 = [
    $currentRecords183[0],
    $currentRecords183[1],
    $currentRecords183[2],
    $currentRecords183[3],
    $currentRecords183[4],
    $currentRecords183[5],
    $currentRecords183[6],
    $currentRecords183[7],
    $record183('index', 'wp_options_site_lookup', 'wp_options', 12, 'CREATE INDEX wp_options_site_lookup ON wp_options(site_id)', 9),
    $record183('index', 'wp_options_orphan_lookup', 'wp_options', 13, 'CREATE INDEX wp_options_orphan_lookup ON wp_options(orphan_name) WHERE orphan_name IS NOT NULL', 10),
];

$currentTables183 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'blog_id' => '1', 'fallback_name' => 'siteurl', 'orphan_name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => '404', 'option_name' => 'home', 'blog_id' => '1', 'fallback_name' => 'missing_default', 'orphan_name' => 'plugin_missing', 'autoload' => 'yes'],
    ],
];
$nextTables183 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'home', 'blog_id' => 1],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
        ['rowid' => 3, 'default_name' => 'plugin_missing', 'enabled' => 0],
    ],
    'wp_options' => $currentTables183['wp_options'],
];

$page183 = static fn (
    int $offset = 0,
    int $limit = 183,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog183(
    $currentRecords183,
    $currentTables183,
    $nextRecords ?? $nextRecords183,
    $nextTables ?? $nextTables183,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt183 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default183 = static fn (): array => $page183();
$blocked183 = static fn (): array => $page183(nextRecords: $currentRecords183, nextTables: $currentTables183);
$childRows183 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childIndexRows183($currentRecords183);
$nextChildRows183 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childIndexRows183($nextRecords183, 'next');
$tableValued183 = static fn (): array => $page183(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases183 = [
    'status ok after child indexes added' => [$default183, 'status', 'ok'],
    'default limit' => [$default183, 'limit', 183],
    'total rows include child index rows' => [$default183, 'total', 50],
    'count rows include child index rows' => [$default183, 'count', 50],
    'complete true' => [$default183, 'complete', true],
    'next null' => [$default183, 'next', null],
    'current child index source' => [$default183, 'current_source.foreign_key_child_index_source', 'pragma_index_xinfo_child_key_prefixes'],
    'next child index source' => [$default183, 'next_source.foreign_key_child_index_source', 'pragma_index_xinfo_child_key_prefixes'],
    'current child index row count' => [$default183, 'current.foreign_key_child_index_rows', 5],
    'next child index row count' => [$default183, 'next_counts.foreign_key_child_index_rows', 5],
    'current mapped child indexes' => [$default183, 'current.foreign_key_child_indexes.mapped', 3],
    'current missing child indexes' => [$default183, 'current.foreign_key_child_indexes.missing_child_index', 2],
    'next mapped child indexes' => [$default183, 'next_counts.foreign_key_child_indexes.mapped', 5],
    'next missing child indexes cleared' => [$default183, 'next_counts.foreign_key_child_indexes.missing_child_index', 0],
    'current unique child index row' => [$default183, 'current.foreign_key_child_indexes.unique_index', 1],
    'current nonunique child index rows' => [$default183, 'current.foreign_key_child_indexes.nonunique_index', 2],
    'next partial child index row' => [$default183, 'next_counts.foreign_key_child_indexes.partial_index', 1],
    'current extra key columns counted' => [$default183, 'current.foreign_key_child_indexes.extra_key_columns', 3],
    'next extra key columns counted' => [$default183, 'next_counts.foreign_key_child_indexes.extra_key_columns', 3],
    'current auxiliary columns ignored counted' => [$default183, 'current.foreign_key_child_indexes.auxiliary_columns_ignored', 3],
    'delta child rows unchanged' => [$default183, 'delta.foreign_key_child_index_rows', 0],
    'delta child index changed' => [$default183, 'delta.foreign_key_child_index_changed', true],
    'delta child index repaired' => [$default183, 'delta.foreign_key_child_index_repaired', true],
    'delta cleared true from FK check' => [$default183, 'delta.cleared', true],
    'next ready true' => [$default183, 'next_state.ready', true],
    'row40 first child index kind' => [$default183, 'rows.40.kind', 'foreign_key_child_index'],
    'row40 site missing current' => [$default183, 'rows.40.status', 'missing_child_index'],
    'row40 site index null' => [$default183, 'rows.40.index', null],
    'row41 composite index' => [$default183, 'rows.41.index', 'wp_options_lookup'],
    'row41 composite first name' => [$default183, 'rows.41.index_name', 'option_name'],
    'row41 composite coll' => [$default183, 'rows.41.index_coll', 'BINARY'],
    'row42 composite second seq' => [$default183, 'rows.42.index_seqno', 1],
    'row42 composite extra columns' => [$default183, 'rows.42.extra_key_columns', 1],
    'row43 fallback unique index' => [$default183, 'rows.43.index', 'wp_options_fallback_lookup'],
    'row43 fallback unique flag' => [$default183, 'rows.43.index_unique', 1],
    'row44 orphan missing current' => [$default183, 'rows.44.status', 'missing_child_index'],
    'row45 next site repaired' => [$default183, 'rows.45.index', 'wp_options_site_lookup'],
    'row45 next side' => [$default183, 'rows.45.side', 'next'],
    'row46 next composite kept' => [$default183, 'rows.46.index', 'wp_options_lookup'],
    'row48 fallback next unique index' => [$default183, 'rows.48.index', 'wp_options_fallback_lookup'],
    'row49 partial orphan index' => [$default183, 'rows.49.index', 'wp_options_orphan_lookup'],
    'row49 partial flag' => [$default183, 'rows.49.index_partial', 1],
    'blocked status remains blocked' => [$blocked183, 'status', 'blocked'],
    'blocked next missing child indexes' => [$blocked183, 'next_counts.foreign_key_child_indexes.missing_child_index', 2],
    'blocked repaired false' => [$blocked183, 'delta.foreign_key_child_index_repaired', false],
    'helper current first missing' => [$childRows183, '0.status', 'missing_child_index'],
    'helper current composite first' => [$childRows183, '1.index_name', 'option_name'],
    'helper current composite second' => [$childRows183, '2.index_name', 'blog_id'],
    'helper next repaired site' => [$nextChildRows183, '0.index', 'wp_options_site_lookup'],
    'helper next partial repaired orphan' => [$nextChildRows183, '4.index_partial', 1],
    'table valued flag' => [$tableValued183, 'current_source.table_valued_index_xinfo', true],
    'table valued child source' => [$tableValued183, 'current_source.foreign_key_child_index_source', 'pragma_index_xinfo_child_key_prefixes'],
];

$tests = [];
foreach ($cases183 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child index current source next183 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt183): void {
        $t->same($expected, $valueAt183($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child index current source next183 paginates into child index rows'] = static function (TestRunner $t) use ($page183): void {
    $first = $page183(0, 40);
    $second = $page183(40, 4, $first['next']);
    $third = $page183(44, 6, $second['next']);

    $t->same(40, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 40], $first['next']);
    $t->same('foreign_key_child_index', $second['rows'][0]['kind']);
    $t->same('missing_child_index', $second['rows'][0]['status']);
    $t->same(null, $third['rows'][0]['index']);
    $t->same('wp_options_site_lookup', $third['rows'][1]['index']);
    $t->same('wp_options_orphan_lookup', $third['rows'][5]['index']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child index current source next183 source changes with child index repair'] = static function (TestRunner $t) use ($page183, $currentRecords183): void {
    $changed = $page183();
    $same = $page183(nextRecords: $currentRecords183);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_child_index_changed']);
    $t->same(false, $same['delta']['foreign_key_child_index_changed']);
};

$tests['pragma index xinfo foreignkey child index current source next183 rejects stale child-index cursor'] = static function (TestRunner $t) use ($page183, $currentRecords183): void {
    $first = $page183(0, 40);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page183(40, 4, $first['next'], nextRecords: $currentRecords183));
};

$tests['pragma index xinfo foreignkey child index current source next183 rejects stale offset cursor'] = static function (TestRunner $t) use ($page183): void {
    $first = $page183(0, 40);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page183(41, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey child index current source next183 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childIndexRows183([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey child index current source next183 rejects negative offset'] = static function (TestRunner $t) use ($page183): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page183(offset: -1));
};

$tests['pragma index xinfo foreignkey child index current source next183 rejects zero limit'] = static function (TestRunner $t) use ($page183): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page183(limit: 0));
};

return $tests;
