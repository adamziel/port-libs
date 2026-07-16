<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record207 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords207 = [
    $record207('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record207('table', 'wp_plugins', 'wp_plugins', 3, 'CREATE TABLE wp_plugins(plugin_slug TEXT, locale TEXT, active INTEGER)', 2),
    $record207('index', 'wp_plugins_slug_locale_unique', 'wp_plugins', 8, 'CREATE UNIQUE INDEX wp_plugins_slug_locale_unique ON wp_plugins(plugin_slug, locale)', 3),
    $record207('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT NOT NULL, site_id INTEGER NOT NULL, UNIQUE(site_id, post_name))', 4),
    $record207('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 5, null, 5),
    $record207('table', 'wp_option_import', 'wp_option_import', 6, "CREATE TABLE wp_option_import(
        option_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL REFERENCES wp_terms(term_id) ON UPDATE CASCADE ON DELETE RESTRICT,
        plugin_slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        site_id INTEGER NOT NULL,
        post_name TEXT NOT NULL,
        FOREIGN KEY(plugin_slug, locale) REFERENCES wp_plugins(plugin_slug, locale) ON UPDATE CASCADE ON DELETE CASCADE,
        FOREIGN KEY(site_id, post_name) REFERENCES wp_posts(site_id, post_name) ON UPDATE CASCADE ON DELETE SET NULL
    )", 6),
    $record207('index', 'wp_option_import_term_lookup', 'wp_option_import', 7, 'CREATE INDEX wp_option_import_term_lookup ON wp_option_import(term_id)', 7),
    $record207('index', 'wp_option_import_locale_partial', 'wp_option_import', 9, "CREATE INDEX wp_option_import_locale_partial ON wp_option_import(plugin_slug, locale) WHERE locale = 'en_US'", 8),
    $record207('index', 'wp_option_import_post_wrong_order', 'wp_option_import', 10, 'CREATE INDEX wp_option_import_post_wrong_order ON wp_option_import(post_name, site_id)', 9),
];

$nextRecords207 = [
    ...$currentRecords207,
    $record207('index', 'wp_option_import_plugin_fk', 'wp_option_import', 11, 'CREATE INDEX wp_option_import_plugin_fk ON wp_option_import(plugin_slug COLLATE NOCASE, locale COLLATE RTRIM)', 10),
    $record207('index', 'wp_option_import_post_fk', 'wp_option_import', 12, 'CREATE INDEX wp_option_import_post_fk ON wp_option_import(site_id, post_name, option_id)', 11),
];

$badNextRecords207 = [
    ...$currentRecords207,
    $record207('index', 'wp_option_import_post_fk', 'wp_option_import', 12, 'CREATE INDEX wp_option_import_post_fk ON wp_option_import(site_id, post_name, option_id)', 11),
];

$page207 = static fn (
    int $offset = 0,
    int $limit = 50,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page207(
    $currentRecords207,
    $nextRecords ?? $nextRecords207,
    'PRAGMA main.index_xinfo(wp_option_import_term_lookup)',
    'PRAGMA main.foreign_key_list(wp_option_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt207 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default207 = static fn (): array => $page207();
$blocked207 = static fn (): array => $page207(nextRecords: $badNextRecords207);
$childRows207 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyIndexRows207($currentRecords207);
$nextChildRows207 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyIndexRows207($nextRecords207, 'next');

$cases207 = [
    'status ok' => [$default207, 'status', 'ok'],
    'operation marker' => [$default207, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next207'],
    'normalized index sql retained' => [$default207, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo("wp_option_import_term_lookup")'],
    'normalized fk sql retained' => [$default207, 'current_source.foreign_key_sql', 'pragma main.foreign_key_list("wp_option_import")'],
    'child index source current' => [$default207, 'current_source.foreign_key_child_index_source', 'pragma_foreign_key_list_child_groups_plus_pragma_index_xinfo_prefix'],
    'child index source next' => [$default207, 'next_source.foreign_key_child_index_source', 'pragma_foreign_key_list_child_groups_plus_pragma_index_xinfo_prefix'],
    'dependency appended' => [$default207, 'dependencies.5', 'sqlite-pragma-foreign-key-child-index-prefix-coverage'],
    'base rowid alias retained' => [$default207, 'current.foreign_key_parent_rowid_alias.rows', 3],
    'base parent coverage retained' => [$default207, 'current.foreign_key_parent_coverage.rows', 3],
    'current child rows' => [$default207, 'current.foreign_key_child_indexes.rows', 3],
    'current child covered' => [$default207, 'current.foreign_key_child_indexes.covered', 1],
    'current child missing' => [$default207, 'current.foreign_key_child_indexes.missing_child_index', 2],
    'current single column child keys' => [$default207, 'current.foreign_key_child_indexes.single_column', 1],
    'current composite child keys' => [$default207, 'current.foreign_key_child_indexes.composite', 2],
    'current created indexes' => [$default207, 'current.foreign_key_child_indexes.created_index', 1],
    'current autoindexes zero' => [$default207, 'current.foreign_key_child_indexes.autoindex', 0],
    'next child rows' => [$default207, 'next_counts.foreign_key_child_indexes.rows', 3],
    'next child covered' => [$default207, 'next_counts.foreign_key_child_indexes.covered', 3],
    'next child missing' => [$default207, 'next_counts.foreign_key_child_indexes.missing_child_index', 0],
    'next created indexes' => [$default207, 'next_counts.foreign_key_child_indexes.created_index', 3],
    'delta child rows unchanged' => [$default207, 'delta.foreign_key_child_index_rows', 0],
    'delta child covered' => [$default207, 'delta.foreign_key_child_index_covered', 2],
    'delta child missing' => [$default207, 'delta.foreign_key_child_index_missing', -2],
    'delta child repaired true' => [$default207, 'delta.foreign_key_child_index_repaired', true],
    'delta child changed true' => [$default207, 'delta.foreign_key_child_index_changed', true],
    'total includes base and child rows' => [$default207, 'total', 32],
    'count default' => [$default207, 'count', 32],
    'next null complete' => [$default207, 'next', null],
    'next row null complete' => [$default207, 'next_row', null],
    'current source summary term' => [$default207, 'current_source.foreign_key_child_indexes.0', 'current:wp_option_import#0->wp_terms:term_id:wp_option_import_term_lookup:covered'],
    'current source summary plugin missing' => [$default207, 'current_source.foreign_key_child_indexes.1', 'current:wp_option_import#1->wp_plugins:plugin_slug,locale:missing:missing_child_index'],
    'next source summary plugin covered' => [$default207, 'next_source.foreign_key_child_indexes.1', 'next:wp_option_import#1->wp_plugins:plugin_slug,locale:wp_option_import_plugin_fk:covered'],
    'first child row kind' => [$default207, 'rows.26.kind', 'foreign_key_child_index'],
    'first child row phase' => [$default207, 'rows.26.phase', 'current'],
    'first child row status' => [$default207, 'rows.26.status', 'covered'],
    'first child row parent' => [$default207, 'rows.26.parent', 'wp_terms'],
    'first child row index' => [$default207, 'rows.26.child_index', 'wp_option_import_term_lookup'],
    'first child row origin' => [$default207, 'rows.26.child_index_origin', 'c'],
    'first child row unique' => [$default207, 'rows.26.child_index_unique', 0],
    'first child row partial' => [$default207, 'rows.26.child_index_partial', 0],
    'first child row key column' => [$default207, 'rows.26.child_index_key_columns.0', 'term_id'],
    'first child row collation' => [$default207, 'rows.26.child_index_collations.0', 'BINARY'],
    'plugin child row missing because partial is ignored' => [$default207, 'rows.27.status', 'missing_child_index'],
    'plugin child row missing index null' => [$default207, 'rows.27.child_index', null],
    'post child row missing because order is wrong' => [$default207, 'rows.28.status', 'missing_child_index'],
    'post child row child column zero' => [$default207, 'rows.28.child_columns.0', 'site_id'],
    'next term row covered' => [$default207, 'rows.29.status', 'covered'],
    'next plugin row covered' => [$default207, 'rows.30.status', 'covered'],
    'next plugin row index' => [$default207, 'rows.30.child_index', 'wp_option_import_plugin_fk'],
    'next plugin row collation zero' => [$default207, 'rows.30.child_index_collations.0', 'NOCASE'],
    'next plugin row collation one' => [$default207, 'rows.30.child_index_collations.1', 'RTRIM'],
    'next post row covered by prefix' => [$default207, 'rows.31.status', 'covered'],
    'next post row index' => [$default207, 'rows.31.child_index', 'wp_option_import_post_fk'],
    'next post row prefix ignores trailing option id' => [$default207, 'rows.31.child_index_key_columns', ['site_id', 'post_name']],
    'blocked next missing one' => [$blocked207, 'next_counts.foreign_key_child_indexes.missing_child_index', 1],
    'blocked next covered two' => [$blocked207, 'next_counts.foreign_key_child_indexes.covered', 2],
    'blocked repaired false' => [$blocked207, 'delta.foreign_key_child_index_repaired', false],
    'helper first kind' => [$childRows207, '0.kind', 'foreign_key_child_index'],
    'helper first status' => [$childRows207, '0.status', 'covered'],
    'helper partial ignored' => [$childRows207, '1.status', 'missing_child_index'],
    'helper wrong order ignored' => [$childRows207, '2.status', 'missing_child_index'],
    'helper next phase' => [$nextChildRows207, '0.phase', 'next'],
    'helper next plugin covered' => [$nextChildRows207, '1.status', 'covered'],
    'helper next plugin index' => [$nextChildRows207, '1.child_index', 'wp_option_import_plugin_fk'],
    'helper next post index' => [$nextChildRows207, '2.child_index', 'wp_option_import_post_fk'],
];

$tests = [];
foreach ($cases207 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child index current source next207 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt207): void {
        $t->same($expected, $valueAt207($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child index current source next207 paginates child index rows'] = static function (TestRunner $t) use ($page207): void {
    $first = $page207(0, 26);
    $second = $page207(26, 3, $first['next']);
    $third = $page207(29, 3, $second['next']);

    $t->same(26, $first['count']);
    $t->same('foreign_key_child_index', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 26], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('covered', $second['rows'][0]['status']);
    $t->same('missing_child_index', $second['rows'][1]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('wp_option_import_post_fk', $third['rows'][2]['child_index']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child index current source next207 rejects stale cursor'] = static function (TestRunner $t) use ($page207, $badNextRecords207): void {
    $first = $page207(0, 26);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page207(26, 3, $first['next'], $badNextRecords207));
};

$tests['pragma index xinfo foreignkey child index current source next207 rejects stale offset'] = static function (TestRunner $t) use ($page207): void {
    $first = $page207(0, 26);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page207(27, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey child index current source next207 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeyIndexRows207([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey child index current source next207 rejects invalid bounds'] = static function (TestRunner $t) use ($page207): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page207(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page207(0, 0));
};

return $tests;
