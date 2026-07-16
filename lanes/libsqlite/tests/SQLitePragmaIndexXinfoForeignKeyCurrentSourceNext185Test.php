<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record185 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records185 = [
    $record185('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record185('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, locale TEXT COLLATE RTRIM, PRIMARY KEY(name, locale)) WITHOUT ROWID', 2),
    $record185('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record185('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id INTEGER REFERENCES wp_sites, option_name TEXT, locale TEXT, fallback_name TEXT, autoload TEXT, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name))', 4),
    $record185('table', 'wp_option_meta', 'wp_option_meta', 8, 'CREATE TABLE wp_option_meta(meta_key TEXT PRIMARY KEY, option_name TEXT, locale TEXT, FOREIGN KEY(option_name, locale) REFERENCES wp_option_names(name, locale)) WITHOUT ROWID', 5),
    $record185('index', 'wp_option_names_lookup', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, locale COLLATE RTRIM)', 6),
    $record185('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 10, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 7),
    $record185('index', 'wp_options_lookup', 'wp_options', 11, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, locale, fallback_name)', 8),
];

$currentTables185 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'locale' => 'en_US'],
        ['name' => 'home', 'locale' => 'en_US'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => 1, 'option_name' => 'siteurl', 'locale' => 'en_US', 'fallback_name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => null, 'option_name' => 'missing-plugin', 'locale' => null, 'fallback_name' => 'siteurl', 'autoload' => 'no'],
        ['rowid' => 3, 'option_id' => 3, 'site_id' => 1, 'option_name' => null, 'locale' => null, 'fallback_name' => null, 'autoload' => 'yes'],
        ['rowid' => 4, 'option_id' => 4, 'site_id' => 1, 'option_name' => 'home', 'locale' => 'en_US', 'fallback_name' => 'missing_default', 'autoload' => 'yes'],
        ['rowid' => 5, 'option_id' => 5, 'site_id' => 1, 'option_name' => 'siteurl', 'locale' => 'en_US', 'fallback_name' => null, 'autoload' => 'no'],
    ],
    'wp_option_meta' => [
        ['meta_key' => '_transient_empty_locale', 'option_name' => 'missing-plugin', 'locale' => null],
        ['meta_key' => '_transient_full_null', 'option_name' => null, 'locale' => null],
        ['meta_key' => '_transient_valid', 'option_name' => 'home', 'locale' => 'en_US'],
    ],
];
$nextTables185 = $currentTables185;
$nextTables185['wp_defaults'][] = ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0];
$nextTables185['wp_options'][] = ['rowid' => 6, 'option_id' => 6, 'site_id' => null, 'option_name' => 'home', 'locale' => 'en_US', 'fallback_name' => null, 'autoload' => 'yes'];

$page185 = static fn (
    int $offset = 0,
    int $limit = 185,
    ?array $cursor = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog185(
    $records185,
    $currentTables185,
    $records185,
    $nextTables ?? $nextTables185,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt185 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default185 = static fn (): array => $page185();
$blocked185 = static fn (): array => $page185(nextTables: $currentTables185);
$nullRows185 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullChildKeyRows185($records185, $currentTables185);
$tableValued185 = static fn (): array => $page185(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

$cases185 = [
    'status ok after non null parent repair' => [$default185, 'status', 'ok'],
    'default limit' => [$default185, 'limit', 185],
    'complete true' => [$default185, 'complete', true],
    'next null' => [$default185, 'next', null],
    'current null source' => [$default185, 'current_source.foreign_key_null_child_source', 'pragma_foreign_key_check_null_child_key_exemption'],
    'next null source' => [$default185, 'next_source.foreign_key_null_child_source', 'pragma_foreign_key_check_null_child_key_exemption'],
    'current null rows' => [$default185, 'current.foreign_key_null_child_keys.rows', 7],
    'current composite null rows' => [$default185, 'current.foreign_key_null_child_keys.composite', 4],
    'current partial null rows' => [$default185, 'current.foreign_key_null_child_keys.partial_null', 2],
    'current full null rows' => [$default185, 'current.foreign_key_null_child_keys.full_null', 5],
    'current rowid null rows' => [$default185, 'current.foreign_key_null_child_keys.rowid_tables', 5],
    'current without rowid null rows' => [$default185, 'current.foreign_key_null_child_keys.without_rowid_tables', 2],
    'next null rows' => [$default185, 'next_counts.foreign_key_null_child_keys.rows', 9],
    'next composite null rows' => [$default185, 'next_counts.foreign_key_null_child_keys.composite', 4],
    'next partial null rows' => [$default185, 'next_counts.foreign_key_null_child_keys.partial_null', 2],
    'next full null rows' => [$default185, 'next_counts.foreign_key_null_child_keys.full_null', 7],
    'next rowid null rows' => [$default185, 'next_counts.foreign_key_null_child_keys.rowid_tables', 7],
    'next without rowid null rows' => [$default185, 'next_counts.foreign_key_null_child_keys.without_rowid_tables', 2],
    'current fk violation remains one' => [$default185, 'current.foreign_key_violations', 1],
    'next fk violation cleared' => [$default185, 'next_counts.foreign_key_violations', 0],
    'delta fk cleared' => [$default185, 'delta.foreign_key_violations', -1],
    'delta total blockers cleared' => [$default185, 'delta.total_blockers', -1],
    'delta cleared true' => [$default185, 'delta.cleared', true],
    'delta null rows increased' => [$default185, 'delta.foreign_key_null_child_rows', 2],
    'delta null changed true' => [$default185, 'delta.foreign_key_null_child_changed', true],
    'delta current only none' => [$default185, 'delta.foreign_key_null_child_current_only', 0],
    'delta next only two' => [$default185, 'delta.foreign_key_null_child_next_only', 2],
    'next ready true' => [$default185, 'next_state.ready', true],
    'next blocking empty' => [$default185, 'next_state.blocking', []],
    'source id length' => [static fn (): array => ['len' => strlen($page185()['source_id'])], 'len', 64],
    'current summary first rowid null' => [$default185, 'current_source.foreign_key_null_child_keys.0', 'current:wp_option_meta#0@without-rowid->wp_option_names:null=locale'],
    'current summary full null without rowid' => [$default185, 'current_source.foreign_key_null_child_keys.1', 'current:wp_option_meta#0@without-rowid->wp_option_names:null=option_name|locale'],
    'next summary appends row six fallback null' => [$default185, 'next_source.foreign_key_null_child_keys.8', 'next:wp_options#2@6->wp_defaults:null=fallback_name'],
    'blocked remains blocked' => [$blocked185, 'status', 'blocked'],
    'blocked next fk violation remains' => [$blocked185, 'next_counts.foreign_key_violations', 1],
    'blocked ready false' => [$blocked185, 'next_state.ready', false],
    'blocked reason fk check' => [$blocked185, 'next_state.blocking.0', 'foreign_key_check'],
    'blocked null rows unchanged' => [$blocked185, 'delta.foreign_key_null_child_rows', 0],
    'helper first kind' => [$nullRows185, '0.kind', 'foreign_key_null_child_key'],
    'helper first table' => [$nullRows185, '0.table', 'wp_option_meta'],
    'helper first rowid without rowid' => [$nullRows185, '0.rowid', null],
    'helper first null column' => [$nullRows185, '0.null_child_columns.0', 'locale'],
    'helper first status' => [$nullRows185, '0.status', 'not_checked'],
    'helper second full null option name' => [$nullRows185, '1.null_child_columns.0', 'option_name'],
    'helper second full null locale' => [$nullRows185, '1.null_child_columns.1', 'locale'],
    'helper third rowid two' => [$nullRows185, '2.rowid', 2],
    'helper third site null' => [$nullRows185, '2.null_child_columns.0', 'site_id'],
    'helper fourth partial composite' => [$nullRows185, '3.null_child_columns.0', 'locale'],
    'helper fifth full composite' => [$nullRows185, '4.null_child_columns.1', 'locale'],
    'helper sixth fallback null' => [$nullRows185, '5.null_child_columns.0', 'fallback_name'],
    'table valued preserved' => [$tableValued185, 'current_source.table_valued_index_xinfo', true],
    'table valued null rows preserved' => [$tableValued185, 'current.foreign_key_null_child_keys.rows', 7],
];

$tests = [];
foreach ($cases185 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey null child current source next185 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt185): void {
        $t->same($expected, $valueAt185($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey null child current source next185 paginates into null rows'] = static function (TestRunner $t) use ($page185): void {
    $first = $page185(0, 53);
    $second = $page185(53, 7, $first['next']);
    $third = $page185(60, 9, $second['next']);

    $t->same(53, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 53], $first['next']);
    $t->same('foreign_key_null_child_key', $second['rows'][0]['kind']);
    $t->same('current', $second['rows'][0]['side']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey null child current source next185 source changes with null child rows'] = static function (TestRunner $t) use ($page185, $currentTables185): void {
    $changed = $page185();
    $same = $page185(nextTables: $currentTables185);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['next_source']['foreign_key_null_child_keys'] !== $same['next_source']['foreign_key_null_child_keys']);
    $t->same(false, $same['delta']['foreign_key_null_child_changed']);
};

$tests['pragma index xinfo foreignkey null child current source next185 rejects stale cursor'] = static function (TestRunner $t) use ($page185, $currentTables185): void {
    $first = $page185(0, 53);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page185(53, 7, $first['next'], nextTables: $currentTables185));
};

$tests['pragma index xinfo foreignkey null child current source next185 rejects stale offset cursor'] = static function (TestRunner $t) use ($page185): void {
    $first = $page185(0, 53);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page185(54, 7, $first['next']));
};

$tests['pragma index xinfo foreignkey null child current source next185 rejects malformed records'] = static function (TestRunner $t) use ($currentTables185): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullChildKeyRows185([['bad' => 'record']], $currentTables185));
};

$tests['pragma index xinfo foreignkey null child current source next185 rejects missing child column'] = static function (TestRunner $t) use ($records185, $currentTables185): void {
    $tables = $currentTables185;
    unset($tables['wp_options'][0]['site_id']);

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullChildKeyRows185($records185, $tables));
};

$tests['pragma index xinfo foreignkey null child current source next185 rejects negative offset'] = static function (TestRunner $t) use ($page185): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page185(offset: -1));
};

$tests['pragma index xinfo foreignkey null child current source next185 rejects zero limit'] = static function (TestRunner $t) use ($page185): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page185(limit: 0));
};

return $tests;
