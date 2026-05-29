<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record195 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords195 = [
    $record195('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record195('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, locale TEXT)', 2),
    $record195('table', 'wp_plugin_defaults', 'wp_plugin_defaults', 6, 'CREATE TABLE wp_plugin_defaults(plugin_slug TEXT, locale TEXT, option_name TEXT, active INTEGER)', 3),
    $record195('table', 'wp_defaults', 'wp_defaults', 7, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 4),
    $record195('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id INTEGER REFERENCES wp_sites(blog_id), option_name TEXT, blog_id INTEGER, plugin_slug TEXT, locale TEXT, fallback_name TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id), FOREIGN KEY(plugin_slug, locale) REFERENCES wp_plugin_defaults(plugin_slug, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name))', 5),
    $record195('index', 'wp_option_names_reversed_unique', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_reversed_unique ON wp_option_names(blog_id, name COLLATE NOCASE)', 6),
    $record195('index', 'wp_plugin_defaults_reversed_partial', 'wp_plugin_defaults', 10, 'CREATE UNIQUE INDEX wp_plugin_defaults_reversed_partial ON wp_plugin_defaults(locale, plugin_slug) WHERE active = 1', 7),
    $record195('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 11, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 8),
    $record195('index', 'wp_options_lookup', 'wp_options', 12, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id, plugin_slug, locale, fallback_name)', 9),
];
$nextRecords195 = [
    $currentRecords195[0],
    $currentRecords195[1],
    $currentRecords195[2],
    $currentRecords195[3],
    $currentRecords195[4],
    $currentRecords195[5],
    $record195('index', 'wp_option_names_exact_unique', 'wp_option_names', 13, 'CREATE UNIQUE INDEX wp_option_names_exact_unique ON wp_option_names(name COLLATE NOCASE, blog_id)', 10),
    $currentRecords195[6],
    $record195('index', 'wp_plugin_defaults_exact_unique', 'wp_plugin_defaults', 14, 'CREATE UNIQUE INDEX wp_plugin_defaults_exact_unique ON wp_plugin_defaults(plugin_slug, locale)', 11),
    $currentRecords195[7],
    $currentRecords195[8],
];

$currentTables195 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'blog_id' => 1, 'locale' => 'en_US'],
    ],
    'wp_plugin_defaults' => [
        ['rowid' => 1, 'plugin_slug' => 'akismet', 'locale' => 'en_US', 'option_name' => 'akismet_api_key', 'active' => 1],
    ],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => 1, 'option_name' => 'siteurl', 'blog_id' => 1, 'plugin_slug' => 'akismet', 'locale' => 'en_US', 'fallback_name' => 'siteurl'],
    ],
];
$nextTables195 = $currentTables195;

$page195 = static fn (
    int $offset = 0,
    int $limit = 195,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog195(
    $currentRecords195,
    $currentTables195,
    $nextRecords ?? $nextRecords195,
    $nextTables195,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt195 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default195 = static fn (): array => $page195();
$blocked195 = static fn (): array => $page195(nextRecords: $currentRecords195);
$permutedRows195 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentKeyRows195($currentRecords195);
$nextPermutedRows195 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentKeyRows195($nextRecords195, 'next');
$tableValued195 = static fn (): array => $page195(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases195 = [
    'status ok after exact order repair' => [$default195, 'status', 'ok'],
    'default limit' => [$default195, 'limit', 195],
    'complete true' => [$default195, 'complete', true],
    'next null' => [$default195, 'next', null],
    'source id length' => [static fn (): array => ['len' => strlen($page195()['source_id'])], 'len', 64],
    'current source permuted kind' => [$default195, 'current_source.foreign_key_parent_permuted_source', 'pragma_index_xinfo_permuted_unique_parent_indexes'],
    'next source permuted kind' => [$default195, 'next_source.foreign_key_parent_permuted_source', 'pragma_index_xinfo_permuted_unique_parent_indexes'],
    'current permuted rows' => [$default195, 'current.foreign_key_parent_permuted_rows', 4],
    'next permuted rows' => [$default195, 'next_counts.foreign_key_parent_permuted_rows', 2],
    'current count rows' => [$default195, 'current.foreign_key_parent_permuted.rows', 4],
    'current permuted blockers' => [$default195, 'current.foreign_key_parent_permuted.permuted_unique_only', 2],
    'current partial permuted rows' => [$default195, 'current.foreign_key_parent_permuted.partial_permuted_unique', 2],
    'current reordered terms' => [$default195, 'current.foreign_key_parent_permuted.reordered_terms', 4],
    'next permuted blockers repaired' => [$default195, 'next_counts.foreign_key_parent_permuted.permuted_unique_only', 0],
    'next partial permuted remains diagnostic' => [$default195, 'next_counts.foreign_key_parent_permuted.partial_permuted_unique', 2],
    'delta rows reduced' => [$default195, 'delta.foreign_key_parent_permuted_rows', -2],
    'delta changed true' => [$default195, 'delta.foreign_key_parent_permuted_changed', true],
    'delta blockers negative two' => [$default195, 'delta.foreign_key_parent_permuted_blocker_delta', -2],
    'delta repaired true' => [$default195, 'delta.foreign_key_parent_permuted_repaired', true],
    'next ready true' => [$default195, 'next_state.ready', true],
    'next blocking empty' => [$default195, 'next_state.blocking', []],
    'blocked status' => [$blocked195, 'status', 'blocked'],
    'blocked next blockers remain' => [$blocked195, 'next_counts.foreign_key_parent_permuted.permuted_unique_only', 2],
    'blocked next ready false' => [$blocked195, 'next_state.ready', false],
    'blocked includes unique parent' => [$blocked195, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'blocked includes permuted parent' => [$blocked195, 'next_state.blocking.1', 'foreign_key_parent_permuted_unique_index'],
    'blocked repaired false' => [$blocked195, 'delta.foreign_key_parent_permuted_repaired', false],
    'helper first kind' => [$permutedRows195, '0.kind', 'foreign_key_parent_permuted'],
    'helper first status permuted' => [$permutedRows195, '0.status', 'permuted_unique_only'],
    'helper first index' => [$permutedRows195, '0.index', 'wp_option_names_reversed_unique'],
    'helper first expected option name' => [$permutedRows195, '0.expected_columns.0', 'name'],
    'helper first actual position' => [$permutedRows195, '0.actual_position', 1],
    'helper first expected position' => [$permutedRows195, '0.expected_position', 0],
    'helper second to blog' => [$permutedRows195, '1.to', 'blog_id'],
    'helper second actual position' => [$permutedRows195, '1.actual_position', 0],
    'helper second index first column' => [$permutedRows195, '1.index_key_columns.0', 'blog_id'],
    'helper third partial status' => [$permutedRows195, '2.status', 'partial_permuted_unique'],
    'helper third partial bit' => [$permutedRows195, '2.index_partial', 1],
    'helper third index first locale' => [$permutedRows195, '2.index_key_columns.0', 'locale'],
    'helper fourth plugin slug actual position' => [$permutedRows195, '3.actual_position', 0],
    'helper next first exact index suppresses full permuted blocker' => [$nextPermutedRows195, '0.status', 'partial_permuted_unique'],
    'helper next partial index remains visible' => [$nextPermutedRows195, '0.index', 'wp_plugin_defaults_reversed_partial'],
    'table valued preserved' => [$tableValued195, 'current_source.table_valued_index_xinfo', true],
    'table valued permuted preserved' => [$tableValued195, 'current.foreign_key_parent_permuted.permuted_unique_only', 2],
];

$tests = [];
foreach ($cases195 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent permuted current source next195 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt195): void {
        $t->same($expected, $valueAt195($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent permuted current source next195 paginates into permuted rows'] = static function (TestRunner $t) use ($page195): void {
    $first = $page195(0, 68);
    $second = $page195(68, 4, $first['next']);
    $third = $page195(72, 2, $second['next']);

    $t->same(68, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 68], $first['next']);
    $t->same('foreign_key_parent_permuted', $second['rows'][0]['kind']);
    $t->same('permuted_unique_only', $second['rows'][0]['status']);
    $t->same('partial_permuted_unique', $second['rows'][2]['status']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent permuted current source next195 source changes with exact ordered repair'] = static function (TestRunner $t) use ($page195, $currentRecords195): void {
    $changed = $page195();
    $same = $page195(nextRecords: $currentRecords195);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_parent_permuted_changed']);
    $t->same(false, $same['delta']['foreign_key_parent_permuted_changed']);
};

$tests['pragma index xinfo foreignkey parent permuted current source next195 ignores superset unique indexes'] = static function (TestRunner $t) use ($currentRecords195, $record195): void {
    $records = $currentRecords195;
    $records[5] = $record195('index', 'wp_option_names_superset_unique', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_superset_unique ON wp_option_names(blog_id, name, locale)', 6);
    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentKeyRows195($records);

    $t->same(2, count($rows));
    $t->same('wp_plugin_defaults_reversed_partial', $rows[0]['index']);
};

$tests['pragma index xinfo foreignkey parent permuted current source next195 ignores expression unique indexes'] = static function (TestRunner $t) use ($currentRecords195, $record195): void {
    $records = $currentRecords195;
    $records[5] = $record195('index', 'wp_option_names_expression_unique', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_expression_unique ON wp_option_names(blog_id, lower(name))', 6);
    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentKeyRows195($records);

    $t->same(2, count($rows));
    $t->same('wp_plugin_defaults_reversed_partial', $rows[0]['index']);
};

$tests['pragma index xinfo foreignkey parent permuted current source next195 rejects stale cursor'] = static function (TestRunner $t) use ($page195, $currentRecords195): void {
    $first = $page195(0, 73);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page195(73, 4, $first['next'], nextRecords: $currentRecords195));
};

$tests['pragma index xinfo foreignkey parent permuted current source next195 rejects stale offset cursor'] = static function (TestRunner $t) use ($page195): void {
    $first = $page195(0, 73);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page195(74, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey parent permuted current source next195 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentKeyRows195([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey parent permuted current source next195 rejects negative offset'] = static function (TestRunner $t) use ($page195): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page195(offset: -1));
};

$tests['pragma index xinfo foreignkey parent permuted current source next195 rejects zero limit'] = static function (TestRunner $t) use ($page195): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page195(limit: 0));
};

return $tests;
