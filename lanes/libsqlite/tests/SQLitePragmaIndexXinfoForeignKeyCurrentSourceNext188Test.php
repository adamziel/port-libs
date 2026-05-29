<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record188 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords188 = [
    $record188('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record188('table', 'wp_plugin_slugs', 'wp_plugin_slugs', 5, 'CREATE TABLE wp_plugin_slugs(slug TEXT, locale TEXT, active INTEGER)', 2),
    $record188('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record188('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id INTEGER REFERENCES wp_sites, plugin_slug TEXT, locale TEXT, fallback_name TEXT, autoload TEXT, FOREIGN KEY(plugin_slug, locale) REFERENCES wp_plugin_slugs(slug, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name))', 4),
    $record188('index', 'wp_plugin_slugs_active_unique', 'wp_plugin_slugs', 8, 'CREATE UNIQUE INDEX wp_plugin_slugs_active_unique ON wp_plugin_slugs(slug, locale) WHERE active = 1', 5),
    $record188('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 9, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 6),
    $record188('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(plugin_slug, locale, fallback_name)', 7),
];
$nextRecords188 = $currentRecords188;
$nextRecords188[] = $record188('index', 'wp_plugin_slugs_full_unique', 'wp_plugin_slugs', 11, 'CREATE UNIQUE INDEX wp_plugin_slugs_full_unique ON wp_plugin_slugs(slug, locale)', 8);

$currentTables188 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_plugin_slugs' => [
        ['rowid' => 1, 'slug' => 'akismet', 'locale' => 'en_US', 'active' => 1],
        ['rowid' => 2, 'slug' => 'hello-dolly', 'locale' => 'en_US', 'active' => 0],
    ],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'akismet', 'enabled' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => 1, 'plugin_slug' => 'akismet', 'locale' => 'en_US', 'fallback_name' => 'akismet', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => 1, 'plugin_slug' => 'hello-dolly', 'locale' => 'en_US', 'fallback_name' => null, 'autoload' => 'no'],
        ['rowid' => 3, 'option_id' => 3, 'site_id' => null, 'plugin_slug' => null, 'locale' => null, 'fallback_name' => null, 'autoload' => 'yes'],
    ],
];
$nextTables188 = $currentTables188;

$page188 = static fn (
    int $offset = 0,
    int $limit = 188,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog188(
    $currentRecords188,
    $currentTables188,
    $nextRecords ?? $nextRecords188,
    $nextTables188,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt188 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default188 = static fn (): array => $page188();
$blocked188 = static fn (): array => $page188(nextRecords: $currentRecords188);
$partialRows188 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::partialParentKeyRows188($currentRecords188);
$tableValued188 = static fn (): array => $page188(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases188 = [
    'status ok after full unique parent repair' => [$default188, 'status', 'ok'],
    'default limit' => [$default188, 'limit', 188],
    'complete true' => [$default188, 'complete', true],
    'next null' => [$default188, 'next', null],
    'source id length' => [static fn (): array => ['len' => strlen($page188()['source_id'])], 'len', 64],
    'current source partial kind' => [$default188, 'current_source.foreign_key_parent_partial_source', 'pragma_index_xinfo_parent_partial_unique_indexes'],
    'next source partial kind' => [$default188, 'next_source.foreign_key_parent_partial_source', 'pragma_index_xinfo_parent_partial_unique_indexes'],
    'current partial rows' => [$default188, 'current.foreign_key_parent_partial_rows', 4],
    'next partial rows' => [$default188, 'next_counts.foreign_key_parent_partial_rows', 4],
    'current partial count rows' => [$default188, 'current.foreign_key_parent_partial.rows', 4],
    'current partial blocker count' => [$default188, 'current.foreign_key_parent_partial.partial_unique_only', 2],
    'current ok count' => [$default188, 'current.foreign_key_parent_partial.ok', 1],
    'current missing parent key count' => [$default188, 'current.foreign_key_parent_partial.missing_parent_key', 1],
    'current partial index rows' => [$default188, 'current.foreign_key_parent_partial.partial_index_rows', 2],
    'current full index rows' => [$default188, 'current.foreign_key_parent_partial.full_index_rows', 1],
    'next partial blocker cleared' => [$default188, 'next_counts.foreign_key_parent_partial.partial_unique_only', 0],
    'next ok count' => [$default188, 'next_counts.foreign_key_parent_partial.ok', 3],
    'next missing parent key count' => [$default188, 'next_counts.foreign_key_parent_partial.missing_parent_key', 1],
    'next partial index rows cleared' => [$default188, 'next_counts.foreign_key_parent_partial.partial_index_rows', 0],
    'next full index rows' => [$default188, 'next_counts.foreign_key_parent_partial.full_index_rows', 3],
    'delta rows unchanged' => [$default188, 'delta.foreign_key_parent_partial_rows', 0],
    'delta changed true' => [$default188, 'delta.foreign_key_parent_partial_changed', true],
    'delta blocker negative two' => [$default188, 'delta.foreign_key_parent_partial_blocker_delta', -2],
    'delta repaired true' => [$default188, 'delta.foreign_key_parent_partial_repaired', true],
    'next ready true' => [$default188, 'next_state.ready', true],
    'next blocking empty' => [$default188, 'next_state.blocking', []],
    'blocked status' => [$blocked188, 'status', 'blocked'],
    'blocked partial blocker remains' => [$blocked188, 'next_counts.foreign_key_parent_partial.partial_unique_only', 2],
    'blocked next ready false' => [$blocked188, 'next_state.ready', false],
    'blocked reason parent unique' => [$blocked188, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'blocked reason partial unique' => [$blocked188, 'next_state.blocking.1', 'foreign_key_parent_partial_unique_index'],
    'blocked delta repaired false' => [$blocked188, 'delta.foreign_key_parent_partial_repaired', false],
    'blocked partial changed false' => [$blocked188, 'delta.foreign_key_parent_partial_changed', false],
    'helper first kind' => [$partialRows188, '0.kind', 'foreign_key_parent_partial'],
    'helper first table' => [$partialRows188, '0.table', 'wp_options'],
    'helper first status missing rowid parent' => [$partialRows188, '0.status', 'missing_parent_key'],
    'helper second status partial' => [$partialRows188, '1.status', 'partial_unique_only'],
    'helper second index partial' => [$partialRows188, '1.index_partial', 1],
    'helper second matched keys' => [$partialRows188, '1.matched_key_columns', 2],
    'helper second index name' => [$partialRows188, '1.index', 'wp_plugin_slugs_active_unique'],
    'helper third parent column' => [$partialRows188, '2.to', 'locale'],
    'helper third seq one' => [$partialRows188, '2.seq', 1],
    'helper fourth fallback ok' => [$partialRows188, '3.status', 'ok'],
    'helper fourth full unique' => [$partialRows188, '3.index_partial', 0],
    'helper fourth defaults index' => [$partialRows188, '3.index', 'sqlite_autoindex_wp_defaults_1'],
    'table valued preserved' => [$tableValued188, 'current_source.table_valued_index_xinfo', true],
    'table valued partial preserved' => [$tableValued188, 'current.foreign_key_parent_partial.partial_unique_only', 2],
];

$tests = [];
foreach ($cases188 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent partial current source next188 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt188): void {
        $t->same($expected, $valueAt188($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent partial current source next188 paginates into partial rows'] = static function (TestRunner $t) use ($page188): void {
    $first = $page188(0, 46);
    $second = $page188(46, 4, $first['next']);
    $third = $page188(50, 4, $second['next']);

    $t->same(46, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 46], $first['next']);
    $t->same('foreign_key_parent_partial', $second['rows'][0]['kind']);
    $t->same('missing_parent_key', $second['rows'][0]['status']);
    $t->same('partial_unique_only', $second['rows'][1]['status']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent partial current source next188 source changes with full unique index'] = static function (TestRunner $t) use ($page188, $currentRecords188): void {
    $first = $page188();
    $second = $page188(nextRecords: $currentRecords188);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['delta']['foreign_key_parent_partial_changed']);
    $t->same(false, $second['delta']['foreign_key_parent_partial_changed']);
};

$tests['pragma index xinfo foreignkey parent partial current source next188 rejects stale cursor'] = static function (TestRunner $t) use ($page188, $currentRecords188): void {
    $first = $page188(0, 46);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page188(46, 4, $first['next'], nextRecords: $currentRecords188));
};

$tests['pragma index xinfo foreignkey parent partial current source next188 rejects stale offset cursor'] = static function (TestRunner $t) use ($page188): void {
    $first = $page188(0, 46);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page188(47, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey parent partial current source next188 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::partialParentKeyRows188([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey parent partial current source next188 rejects negative offset'] = static function (TestRunner $t) use ($page188): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page188(offset: -1));
};

$tests['pragma index xinfo foreignkey parent partial current source next188 rejects zero limit'] = static function (TestRunner $t) use ($page188): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page188(limit: 0));
};

return $tests;
