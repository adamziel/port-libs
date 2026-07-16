<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record187 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords187 = [
    $record187('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record187('table', 'wp_slug_registry', 'wp_slug_registry', 5, 'CREATE TABLE wp_slug_registry(slug TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, active INTEGER)', 2),
    $record187('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT, enabled INTEGER)', 3),
    $record187('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id INTEGER REFERENCES wp_sites(blog_id), slug TEXT, locale TEXT, fallback_name TEXT, option_value TEXT, FOREIGN KEY(slug, locale) REFERENCES wp_slug_registry(slug, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name))', 4),
    $record187('index', 'wp_slug_registry_active_unique', 'wp_slug_registry', 8, 'CREATE UNIQUE INDEX wp_slug_registry_active_unique ON wp_slug_registry(slug COLLATE NOCASE, locale COLLATE RTRIM) WHERE active = 1', 5),
    $record187('index', 'wp_defaults_enabled_unique', 'wp_defaults', 9, 'CREATE UNIQUE INDEX wp_defaults_enabled_unique ON wp_defaults(default_name) WHERE enabled = 1', 6),
    $record187('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(slug, locale, fallback_name)', 7),
];
$nextRecords187 = [
    $currentRecords187[0],
    $currentRecords187[1],
    $currentRecords187[2],
    $currentRecords187[3],
    $currentRecords187[4],
    $record187('index', 'wp_slug_registry_full_unique', 'wp_slug_registry', 11, 'CREATE UNIQUE INDEX wp_slug_registry_full_unique ON wp_slug_registry(slug COLLATE NOCASE, locale COLLATE RTRIM)', 8),
    $currentRecords187[5],
    $record187('index', 'wp_defaults_full_unique', 'wp_defaults', 12, 'CREATE UNIQUE INDEX wp_defaults_full_unique ON wp_defaults(default_name)', 9),
    $currentRecords187[6],
];

$currentTables187 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_slug_registry' => [['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'active' => 1]],
    'wp_defaults' => [['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1]],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => 1, 'slug' => 'home', 'locale' => 'en_US', 'fallback_name' => 'siteurl', 'option_value' => 'https://example.test'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => 1, 'slug' => 'dashboard', 'locale' => 'en_US', 'fallback_name' => 'missing_default', 'option_value' => '1'],
    ],
];
$nextTables187 = [
    'wp_sites' => $currentTables187['wp_sites'],
    'wp_slug_registry' => [
        ['rowid' => 1, 'slug' => 'home', 'locale' => 'en_US', 'active' => 1],
        ['rowid' => 2, 'slug' => 'dashboard', 'locale' => 'en_US', 'active' => 0],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_options' => $currentTables187['wp_options'],
];

$page187 = static fn (
    int $offset = 0,
    int $limit = 187,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog187(
    $currentRecords187,
    $currentTables187,
    $nextRecords ?? $nextRecords187,
    $nextTables ?? $nextTables187,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt187 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default187 = static fn (): array => $page187();
$blocked187 = static fn (): array => $page187(nextRecords: $currentRecords187, nextTables: $currentTables187);
$partialRows187 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::partialParentIndexRows187($currentRecords187);
$nextPartialRows187 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::partialParentIndexRows187($nextRecords187, 'next');
$tableValued187 = static fn (): array => $page187(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases187 = [
    'status ok after full parent indexes added' => [$default187, 'status', 'ok'],
    'limit default' => [$default187, 'limit', 187],
    'total rows include partial parent rows' => [$default187, 'total', 54],
    'count rows include partial parent rows' => [$default187, 'count', 54],
    'complete true' => [$default187, 'complete', true],
    'next null' => [$default187, 'next', null],
    'current partial source' => [$default187, 'current_source.foreign_key_partial_parent_index_source', 'pragma_index_list_partial_unique_parent_candidates'],
    'next partial source' => [$default187, 'next_source.foreign_key_partial_parent_index_source', 'pragma_index_list_partial_unique_parent_candidates'],
    'current partial row count' => [$default187, 'current.foreign_key_partial_parent_index_rows', 3],
    'next partial row count' => [$default187, 'next_counts.foreign_key_partial_parent_index_rows', 3],
    'current partial count rows' => [$default187, 'current.foreign_key_partial_parent_indexes.rows', 3],
    'current partial blockers' => [$default187, 'current.foreign_key_partial_parent_indexes.partial_unique_candidates', 3],
    'current shadowed count zero' => [$default187, 'current.foreign_key_partial_parent_indexes.shadowed_by_full_parent_key', 0],
    'current columns counted' => [$default187, 'current.foreign_key_partial_parent_indexes.columns', 3],
    'next partial blockers repaired' => [$default187, 'next_counts.foreign_key_partial_parent_indexes.partial_unique_candidates', 0],
    'next shadowed partial rows' => [$default187, 'next_counts.foreign_key_partial_parent_indexes.shadowed_by_full_parent_key', 3],
    'delta partial rows unchanged' => [$default187, 'delta.foreign_key_partial_parent_index_rows', 0],
    'delta blockers negative' => [$default187, 'delta.foreign_key_partial_parent_index_blockers', -3],
    'delta repaired true' => [$default187, 'delta.foreign_key_partial_parent_index_repaired', true],
    'delta changed true' => [$default187, 'delta.foreign_key_partial_parent_index_changed', true],
    'delta cleared true' => [$default187, 'delta.cleared', true],
    'next ready true' => [$default187, 'next_state.ready', true],
    'row48 first partial kind' => [$default187, 'rows.48.kind', 'foreign_key_partial_parent_index'],
    'row48 first partial status' => [$default187, 'rows.48.status', 'partial_parent_key'],
    'row48 first partial index' => [$default187, 'rows.48.index', 'wp_slug_registry_active_unique'],
    'row48 where active' => [$default187, 'rows.48.where', 'active = 1'],
    'row48 full parent key null' => [$default187, 'rows.48.full_parent_key', null],
    'row49 locale partial desc zero' => [$default187, 'rows.49.index_desc', 0],
    'row50 defaults partial index' => [$default187, 'rows.50.index', 'wp_defaults_enabled_unique'],
    'row50 defaults where' => [$default187, 'rows.50.where', 'enabled = 1'],
    'row51 next slug shadowed' => [$default187, 'rows.51.status', 'shadowed_by_full_parent_key'],
    'row51 next full parent key' => [$default187, 'rows.51.full_parent_key', 'wp_slug_registry_full_unique'],
    'row52 next locale side' => [$default187, 'rows.52.side', 'next'],
    'row53 next defaults shadowed' => [$default187, 'rows.53.status', 'shadowed_by_full_parent_key'],
    'blocked status remains blocked' => [$blocked187, 'status', 'blocked'],
    'blocked next partial blockers remain' => [$blocked187, 'next_counts.foreign_key_partial_parent_indexes.partial_unique_candidates', 3],
    'blocked repaired false' => [$blocked187, 'delta.foreign_key_partial_parent_index_repaired', false],
    'helper row count first kind' => [$partialRows187, '0.kind', 'foreign_key_partial_parent_index'],
    'helper slug partial status' => [$partialRows187, '0.status', 'partial_parent_key'],
    'helper locale column name' => [$partialRows187, '1.index_name', 'locale'],
    'helper defaults where' => [$partialRows187, '2.where', 'enabled = 1'],
    'helper next shadowed' => [$nextPartialRows187, '0.status', 'shadowed_by_full_parent_key'],
    'helper next full parent key' => [$nextPartialRows187, '0.full_parent_key', 'wp_slug_registry_full_unique'],
    'helper next defaults full parent key' => [$nextPartialRows187, '2.full_parent_key', 'wp_defaults_full_unique'],
    'table valued flag preserved' => [$tableValued187, 'current_source.table_valued_index_xinfo', true],
    'table valued partial rows preserved' => [$tableValued187, 'current.foreign_key_partial_parent_index_rows', 3],
];

$tests = [];
foreach ($cases187 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey partial parent current source next187 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt187): void {
        $t->same($expected, $valueAt187($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey partial parent current source next187 paginates into partial rows'] = static function (TestRunner $t) use ($page187): void {
    $first = $page187(0, 48);
    $second = $page187(48, 3, $first['next']);
    $third = $page187(51, 3, $second['next']);

    $t->same(48, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 48], $first['next']);
    $t->same('foreign_key_partial_parent_index', $second['rows'][0]['kind']);
    $t->same('partial_parent_key', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same('shadowed_by_full_parent_key', $third['rows'][2]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey partial parent current source next187 source changes with full unique repair'] = static function (TestRunner $t) use ($page187, $currentRecords187): void {
    $changed = $page187();
    $same = $page187(nextRecords: $currentRecords187);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_partial_parent_index_changed']);
    $t->same(false, $same['delta']['foreign_key_partial_parent_index_changed']);
};

$tests['pragma index xinfo foreignkey partial parent current source next187 source changes with partial where clause'] = static function (TestRunner $t) use ($page187, $nextRecords187, $record187): void {
    $changed = $nextRecords187;
    $changed[4] = $record187('index', 'wp_slug_registry_active_unique', 'wp_slug_registry', 8, 'CREATE UNIQUE INDEX wp_slug_registry_active_unique ON wp_slug_registry(slug COLLATE NOCASE, locale COLLATE RTRIM) WHERE active <> 0', 5);

    $first = $page187();
    $second = $page187(nextRecords: $changed);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same('active <> 0', $second['rows'][51]['where']);
};

$tests['pragma index xinfo foreignkey partial parent current source next187 rejects stale partial cursor'] = static function (TestRunner $t) use ($page187, $currentRecords187): void {
    $first = $page187(0, 48);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page187(48, 3, $first['next'], nextRecords: $currentRecords187));
};

$tests['pragma index xinfo foreignkey partial parent current source next187 rejects stale offset cursor'] = static function (TestRunner $t) use ($page187): void {
    $first = $page187(0, 48);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page187(49, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey partial parent current source next187 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::partialParentIndexRows187([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey partial parent current source next187 rejects negative offset'] = static function (TestRunner $t) use ($page187): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page187(offset: -1));
};

$tests['pragma index xinfo foreignkey partial parent current source next187 rejects zero limit'] = static function (TestRunner $t) use ($page187): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page187(limit: 0));
};

return $tests;
