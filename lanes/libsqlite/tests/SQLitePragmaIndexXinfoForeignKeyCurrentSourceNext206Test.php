<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record206 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords206 = [
    $record206('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record206('table', 'wp_plugins', 'wp_plugins', 3, 'CREATE TABLE wp_plugins(plugin_slug TEXT, locale TEXT, active INTEGER)', 2),
    $record206('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT NOT NULL, site_id INTEGER NOT NULL, UNIQUE(site_id, post_name))', 3),
    $record206('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 5, null, 4),
    $record206('table', 'wp_option_import', 'wp_option_import', 6, "CREATE TABLE wp_option_import(
        option_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL REFERENCES wp_terms(term_id) ON UPDATE CASCADE ON DELETE RESTRICT,
        plugin_slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        site_id INTEGER NOT NULL,
        post_name TEXT NOT NULL,
        FOREIGN KEY(plugin_slug, locale) REFERENCES wp_plugins(plugin_slug, locale) ON UPDATE CASCADE ON DELETE CASCADE,
        FOREIGN KEY(site_id, post_name) REFERENCES wp_posts(site_id, post_name) ON UPDATE CASCADE ON DELETE SET NULL
    )", 5),
    $record206('index', 'wp_option_import_lookup', 'wp_option_import', 7, 'CREATE INDEX wp_option_import_lookup ON wp_option_import(term_id, plugin_slug, locale, site_id, post_name)', 6),
];

$nextRecords206 = [
    $currentRecords206[0],
    $currentRecords206[1],
    $record206('index', 'wp_plugins_slug_locale_unique', 'wp_plugins', 8, 'CREATE UNIQUE INDEX wp_plugins_slug_locale_unique ON wp_plugins(plugin_slug, locale)', 7),
    $currentRecords206[2],
    $currentRecords206[3],
    $currentRecords206[4],
    $currentRecords206[5],
];

$badNextRecords206 = [
    $record206('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id TEXT PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $currentRecords206[1],
    $currentRecords206[2],
    $currentRecords206[3],
    $currentRecords206[4],
    $currentRecords206[5],
];

$page206 = static fn (
    int $offset = 0,
    int $limit = 50,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page206(
    $currentRecords206,
    $nextRecords ?? $nextRecords206,
    'PRAGMA main.index_xinfo(wp_option_import_lookup)',
    'PRAGMA main.foreign_key_list(wp_option_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt206 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default206 = static fn (): array => $page206();
$blocked206 = static fn (): array => $page206(nextRecords: $badNextRecords206);
$rowidRows206 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidAliasParentRows206($currentRecords206);
$rowidNextRows206 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidAliasParentRows206($nextRecords206, 'next');

$cases206 = [
    'status ok' => [$default206, 'status', 'ok'],
    'operation marker' => [$default206, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next206'],
    'normalized index sql retained' => [$default206, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo("wp_option_import_lookup")'],
    'normalized fk sql retained' => [$default206, 'current_source.foreign_key_sql', 'pragma main.foreign_key_list("wp_option_import")'],
    'rowid source current' => [$default206, 'current_source.foreign_key_parent_rowid_alias_source', 'pragma_foreign_key_list_parent_groups_plus_table_info_integer_primary_key'],
    'rowid source next' => [$default206, 'next_source.foreign_key_parent_rowid_alias_source', 'pragma_foreign_key_list_parent_groups_plus_table_info_integer_primary_key'],
    'dependency appended' => [$default206, 'dependencies.4', 'sqlite-pragma-foreign-key-parent-rowid-alias-coverage'],
    'base coverage retained current rows' => [$default206, 'current.foreign_key_parent_coverage.rows', 3],
    'base coverage current missing includes rowid alias' => [$default206, 'current.foreign_key_parent_coverage.missing_parent_unique', 2],
    'base coverage next missing keeps rowid alias' => [$default206, 'next_counts.foreign_key_parent_coverage.missing_parent_unique', 1],
    'current rowid rows' => [$default206, 'current.foreign_key_parent_rowid_alias.rows', 3],
    'current rowid covered count' => [$default206, 'current.foreign_key_parent_rowid_alias.rowid_alias_parent_key', 1],
    'current rowid missing count' => [$default206, 'current.foreign_key_parent_rowid_alias.missing_parent_key', 2],
    'current rowid single columns' => [$default206, 'current.foreign_key_parent_rowid_alias.single_column', 1],
    'current rowid composite columns' => [$default206, 'current.foreign_key_parent_rowid_alias.composite', 2],
    'next rowid rows' => [$default206, 'next_counts.foreign_key_parent_rowid_alias.rows', 3],
    'next rowid covered count' => [$default206, 'next_counts.foreign_key_parent_rowid_alias.rowid_alias_parent_key', 1],
    'next rowid missing count' => [$default206, 'next_counts.foreign_key_parent_rowid_alias.missing_parent_key', 2],
    'delta rowid rows unchanged' => [$default206, 'delta.foreign_key_parent_rowid_alias_rows', 0],
    'delta rowid covered unchanged' => [$default206, 'delta.foreign_key_parent_rowid_alias_covered', 0],
    'delta rowid missing unchanged' => [$default206, 'delta.foreign_key_parent_rowid_alias_missing', 0],
    'delta rowid repaired false' => [$default206, 'delta.foreign_key_parent_rowid_alias_repaired', false],
    'delta rowid changed false' => [$default206, 'delta.foreign_key_parent_rowid_alias_changed', false],
    'current source rowid summary' => [$default206, 'current_source.foreign_key_parent_rowid_alias.0', 'current:wp_option_import#0->wp_terms:term_id:rowid-alias=term_id:rowid_alias_parent_key'],
    'current source plugin summary' => [$default206, 'current_source.foreign_key_parent_rowid_alias.1', 'current:wp_option_import#1->wp_plugins:plugin_slug,locale:no-rowid-alias:missing_parent_key'],
    'next source rowid summary' => [$default206, 'next_source.foreign_key_parent_rowid_alias.0', 'next:wp_option_import#0->wp_terms:term_id:rowid-alias=term_id:rowid_alias_parent_key'],
    'total includes base and rowid rows' => [$default206, 'total', 34],
    'count default' => [$default206, 'count', 34],
    'next null complete' => [$default206, 'next', null],
    'first rowid row kind' => [$default206, 'rows.28.kind', 'foreign_key_parent_rowid_alias'],
    'first rowid row phase' => [$default206, 'rows.28.phase', 'current'],
    'first rowid row status' => [$default206, 'rows.28.status', 'rowid_alias_parent_key'],
    'first rowid row parent' => [$default206, 'rows.28.parent', 'wp_terms'],
    'first rowid row alias column' => [$default206, 'rows.28.rowid_alias_column', 'term_id'],
    'first rowid row type' => [$default206, 'rows.28.rowid_alias_type', 'INTEGER'],
    'first rowid row pk ordinal' => [$default206, 'rows.28.rowid_alias_pk', 1],
    'first rowid parent column' => [$default206, 'rows.28.parent_columns.0', 'term_id'],
    'first rowid child column' => [$default206, 'rows.28.child_columns.0', 'term_id'],
    'second rowid row plugin missing' => [$default206, 'rows.29.status', 'missing_parent_key'],
    'second rowid row no alias' => [$default206, 'rows.29.rowid_alias_column', null],
    'third rowid row posts missing because composite' => [$default206, 'rows.30.status', 'missing_parent_key'],
    'third rowid row has rowid alias but composite parent' => [$default206, 'rows.30.rowid_alias_column', 'ID'],
    'next first rowid row status' => [$default206, 'rows.31.status', 'rowid_alias_parent_key'],
    'next second rowid row still not rowid alias' => [$default206, 'rows.32.status', 'missing_parent_key'],
    'blocked next rowid covered removed' => [$blocked206, 'next_counts.foreign_key_parent_rowid_alias.rowid_alias_parent_key', 0],
    'blocked next rowid missing all' => [$blocked206, 'next_counts.foreign_key_parent_rowid_alias.missing_parent_key', 3],
    'blocked changed true' => [$blocked206, 'delta.foreign_key_parent_rowid_alias_changed', true],
    'blocked missing increased' => [$blocked206, 'delta.foreign_key_parent_rowid_alias_missing', 1],
    'helper first kind' => [$rowidRows206, '0.kind', 'foreign_key_parent_rowid_alias'],
    'helper first status' => [$rowidRows206, '0.status', 'rowid_alias_parent_key'],
    'helper second missing' => [$rowidRows206, '1.status', 'missing_parent_key'],
    'helper third alias retained' => [$rowidRows206, '2.rowid_alias_column', 'ID'],
    'helper next phase' => [$rowidNextRows206, '0.phase', 'next'],
    'helper next rowid covered' => [$rowidNextRows206, '0.status', 'rowid_alias_parent_key'],
];

$tests = [];
foreach ($cases206 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey rowid alias parent current source next206 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt206): void {
        $t->same($expected, $valueAt206($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey rowid alias parent current source next206 paginates rowid rows'] = static function (TestRunner $t) use ($page206): void {
    $first = $page206(0, 28);
    $second = $page206(28, 3, $first['next']);
    $third = $page206(31, 3, $second['next']);

    $t->same(28, $first['count']);
    $t->same('foreign_key_parent_rowid_alias', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 28], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('rowid_alias_parent_key', $second['rows'][0]['status']);
    $t->same('missing_parent_key', $second['rows'][1]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey rowid alias parent current source next206 rejects stale cursor'] = static function (TestRunner $t) use ($page206, $badNextRecords206): void {
    $first = $page206(0, 28);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page206(28, 3, $first['next'], $badNextRecords206));
};

$tests['pragma index xinfo foreignkey rowid alias parent current source next206 rejects stale offset'] = static function (TestRunner $t) use ($page206): void {
    $first = $page206(0, 28);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page206(29, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey rowid alias parent current source next206 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rowidAliasParentRows206([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey rowid alias parent current source next206 rejects invalid bounds'] = static function (TestRunner $t) use ($page206): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page206(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page206(0, 0));
};

return $tests;
