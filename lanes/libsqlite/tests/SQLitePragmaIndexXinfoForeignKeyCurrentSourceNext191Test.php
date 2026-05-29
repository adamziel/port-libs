<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record191 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords191 = [
    $record191('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record191('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, locale TEXT, network_id INTEGER)', 2),
    $record191('table', 'wp_plugin_defaults', 'wp_plugin_defaults', 6, 'CREATE TABLE wp_plugin_defaults(plugin_slug TEXT, locale TEXT, option_name TEXT, active INTEGER)', 3),
    $record191('table', 'wp_defaults', 'wp_defaults', 7, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 4),
    $record191('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id INTEGER REFERENCES wp_sites(blog_id), option_name TEXT, blog_id INTEGER, plugin_slug TEXT, locale TEXT, fallback_name TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(plugin_slug, locale) REFERENCES wp_plugin_defaults(plugin_slug, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name))', 5),
    $record191('index', 'wp_option_names_network_unique', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_network_unique ON wp_option_names(name COLLATE NOCASE, blog_id, network_id)', 6),
    $record191('index', 'wp_plugin_defaults_option_unique', 'wp_plugin_defaults', 10, 'CREATE UNIQUE INDEX wp_plugin_defaults_option_unique ON wp_plugin_defaults(plugin_slug, locale, option_name) WHERE active = 1', 7),
    $record191('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 11, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 8),
    $record191('index', 'wp_options_lookup', 'wp_options', 12, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id, plugin_slug, locale, fallback_name)', 9),
];
$nextRecords191 = [
    $currentRecords191[0],
    $currentRecords191[1],
    $currentRecords191[2],
    $currentRecords191[3],
    $currentRecords191[4],
    $currentRecords191[5],
    $record191('index', 'wp_option_names_exact_unique', 'wp_option_names', 13, 'CREATE UNIQUE INDEX wp_option_names_exact_unique ON wp_option_names(name COLLATE NOCASE, blog_id)', 10),
    $currentRecords191[6],
    $record191('index', 'wp_plugin_defaults_exact_unique', 'wp_plugin_defaults', 14, 'CREATE UNIQUE INDEX wp_plugin_defaults_exact_unique ON wp_plugin_defaults(plugin_slug, locale)', 11),
    $currentRecords191[7],
    $currentRecords191[8],
];

$currentTables191 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US', 'network_id' => 1],
    ],
    'wp_plugin_defaults' => [
        ['rowid' => 1, 'plugin_slug' => 'akismet', 'locale' => 'en_US', 'option_name' => 'akismet_api_key', 'active' => 1],
    ],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => 1, 'option_name' => 'siteurl', 'blog_id' => 1, 'plugin_slug' => 'akismet', 'locale' => 'en_US', 'fallback_name' => 'siteurl'],
    ],
];
$nextTables191 = $currentTables191;

$page191 = static fn (
    int $offset = 0,
    int $limit = 191,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog191(
    $currentRecords191,
    $currentTables191,
    $nextRecords ?? $nextRecords191,
    $nextTables191,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt191 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default191 = static fn (): array => $page191();
$blocked191 = static fn (): array => $page191(nextRecords: $currentRecords191);
$supersetRows191 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::supersetParentKeyRows191($currentRecords191);
$nextSupersetRows191 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::supersetParentKeyRows191($nextRecords191, 'next');
$tableValued191 = static fn (): array => $page191(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases191 = [
    'status ok after exact unique parent repair' => [$default191, 'status', 'ok'],
    'default limit' => [$default191, 'limit', 191],
    'complete true' => [$default191, 'complete', true],
    'next null' => [$default191, 'next', null],
    'source id length' => [static fn (): array => ['len' => strlen($page191()['source_id'])], 'len', 64],
    'current source superset kind' => [$default191, 'current_source.foreign_key_parent_superset_source', 'pragma_index_xinfo_unique_prefix_superset_parent_indexes'],
    'next source superset kind' => [$default191, 'next_source.foreign_key_parent_superset_source', 'pragma_index_xinfo_unique_prefix_superset_parent_indexes'],
    'current superset rows' => [$default191, 'current.foreign_key_parent_superset_rows', 4],
    'next superset rows' => [$default191, 'next_counts.foreign_key_parent_superset_rows', 2],
    'current count rows' => [$default191, 'current.foreign_key_parent_superset.rows', 4],
    'current superset blockers' => [$default191, 'current.foreign_key_parent_superset.superset_unique_only', 2],
    'current partial superset rows' => [$default191, 'current.foreign_key_parent_superset.partial_superset_unique', 2],
    'current extra key columns' => [$default191, 'current.foreign_key_parent_superset.extra_key_columns', 4],
    'next superset blockers repaired' => [$default191, 'next_counts.foreign_key_parent_superset.superset_unique_only', 0],
    'next partial superset remains diagnostic' => [$default191, 'next_counts.foreign_key_parent_superset.partial_superset_unique', 2],
    'delta rows reduced' => [$default191, 'delta.foreign_key_parent_superset_rows', -2],
    'delta changed true' => [$default191, 'delta.foreign_key_parent_superset_changed', true],
    'delta blockers negative two' => [$default191, 'delta.foreign_key_parent_superset_blocker_delta', -2],
    'delta repaired true' => [$default191, 'delta.foreign_key_parent_superset_repaired', true],
    'next ready true' => [$default191, 'next_state.ready', true],
    'next blocking empty' => [$default191, 'next_state.blocking', []],
    'blocked status' => [$blocked191, 'status', 'blocked'],
    'blocked next blockers remain' => [$blocked191, 'next_counts.foreign_key_parent_superset.superset_unique_only', 2],
    'blocked next ready false' => [$blocked191, 'next_state.ready', false],
    'blocked includes unique parent' => [$blocked191, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'blocked includes superset parent' => [$blocked191, 'next_state.blocking.1', 'foreign_key_parent_superset_unique_index'],
    'blocked repaired false' => [$blocked191, 'delta.foreign_key_parent_superset_repaired', false],
    'helper first kind' => [$supersetRows191, '0.kind', 'foreign_key_parent_superset'],
    'helper first status superset' => [$supersetRows191, '0.status', 'superset_unique_only'],
    'helper first index' => [$supersetRows191, '0.index', 'wp_option_names_network_unique'],
    'helper first extra network' => [$supersetRows191, '0.extra_key_columns.0', 'network_id'],
    'helper second to blog' => [$supersetRows191, '1.to', 'blog_id'],
    'helper second matched columns' => [$supersetRows191, '1.matched_key_columns', 2],
    'helper second key columns count' => [static fn (): array => ['count' => count($supersetRows191()[1]['index_key_columns'])], 'count', 3],
    'helper third partial status' => [$supersetRows191, '2.status', 'partial_superset_unique'],
    'helper third partial bit' => [$supersetRows191, '2.index_partial', 1],
    'helper third extra option name' => [$supersetRows191, '2.extra_key_columns.0', 'option_name'],
    'helper fourth plugin locale' => [$supersetRows191, '3.to', 'locale'],
    'helper next first exact index suppresses full superset blocker' => [$nextSupersetRows191, '0.status', 'partial_superset_unique'],
    'helper next partial index remains visible' => [$nextSupersetRows191, '0.index', 'wp_plugin_defaults_option_unique'],
    'table valued preserved' => [$tableValued191, 'current_source.table_valued_index_xinfo', true],
    'table valued superset preserved' => [$tableValued191, 'current.foreign_key_parent_superset.superset_unique_only', 2],
];

$tests = [];
foreach ($cases191 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent superset current source next191 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt191): void {
        $t->same($expected, $valueAt191($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent superset current source next191 paginates into superset rows'] = static function (TestRunner $t) use ($page191): void {
    $first = $page191(0, 68);
    $second = $page191(68, 4, $first['next']);
    $third = $page191(72, 2, $second['next']);

    $t->same(68, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 68], $first['next']);
    $t->same('foreign_key_parent_superset', $second['rows'][0]['kind']);
    $t->same('superset_unique_only', $second['rows'][0]['status']);
    $t->same('partial_superset_unique', $second['rows'][2]['status']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent superset current source next191 source changes with exact unique repair'] = static function (TestRunner $t) use ($page191, $currentRecords191): void {
    $changed = $page191();
    $same = $page191(nextRecords: $currentRecords191);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_parent_superset_changed']);
    $t->same(false, $same['delta']['foreign_key_parent_superset_changed']);
};

$tests['pragma index xinfo foreignkey parent superset current source next191 ignores non prefix unique indexes'] = static function (TestRunner $t) use ($currentRecords191, $record191): void {
    $records = $currentRecords191;
    $records[5] = $record191('index', 'wp_option_names_wrong_order_unique', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_wrong_order_unique ON wp_option_names(blog_id, name, network_id)', 6);
    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::supersetParentKeyRows191($records);

    $t->same(2, count($rows));
    $t->same('wp_plugin_defaults_option_unique', $rows[0]['index']);
};

$tests['pragma index xinfo foreignkey parent superset current source next191 rejects stale cursor'] = static function (TestRunner $t) use ($page191, $currentRecords191): void {
    $first = $page191(0, 68);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page191(68, 4, $first['next'], nextRecords: $currentRecords191));
};

$tests['pragma index xinfo foreignkey parent superset current source next191 rejects stale offset cursor'] = static function (TestRunner $t) use ($page191): void {
    $first = $page191(0, 68);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page191(69, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey parent superset current source next191 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::supersetParentKeyRows191([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey parent superset current source next191 rejects negative offset'] = static function (TestRunner $t) use ($page191): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page191(offset: -1));
};

$tests['pragma index xinfo foreignkey parent superset current source next191 rejects zero limit'] = static function (TestRunner $t) use ($page191): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page191(limit: 0));
};

return $tests;
