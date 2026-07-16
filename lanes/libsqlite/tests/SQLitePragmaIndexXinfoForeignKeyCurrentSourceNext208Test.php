<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record208 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords208 = [
    $record208('table', 'wp_blogs', 'wp_blogs', 2, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT NOT NULL)', 1),
    $record208('table', 'wp_locale_keys', 'wp_locale_keys', 3, 'CREATE TABLE wp_locale_keys(blog_id INTEGER NOT NULL, locale TEXT NOT NULL, label TEXT, PRIMARY KEY(blog_id, locale))', 2),
    $record208('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(default_name TEXT PRIMARY KEY, label TEXT)', 3),
    $record208('table', 'wp_options', 'wp_options', 5, "CREATE TABLE wp_options(
        option_id INTEGER PRIMARY KEY,
        blog_id INTEGER REFERENCES wp_blogs,
        locale_blog_id INTEGER NOT NULL,
        locale TEXT NOT NULL,
        option_name TEXT NOT NULL,
        FOREIGN KEY(locale_blog_id, locale) REFERENCES wp_locale_keys,
        FOREIGN KEY(option_name) REFERENCES wp_option_names
    )", 4),
    $record208('index', 'wp_options_lookup', 'wp_options', 6, 'CREATE INDEX wp_options_lookup ON wp_options(blog_id, locale_blog_id, locale, option_name)', 5),
];

$nextRecords208 = [
    $currentRecords208[0],
    $currentRecords208[1],
    $record208('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY, label TEXT)', 3),
    $currentRecords208[3],
    $currentRecords208[4],
];

$changedNextRecords208 = [
    $currentRecords208[0],
    $currentRecords208[1],
    $currentRecords208[2],
    $currentRecords208[3],
    $currentRecords208[4],
];

$page208 = static fn (
    int $offset = 0,
    int $limit = 50,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page208(
    $currentRecords208,
    $nextRecords ?? $nextRecords208,
    'PRAGMA main.index_xinfo(wp_options_lookup)',
    'PRAGMA main.foreign_key_list(wp_options)',
    $offset,
    $limit,
    $resume,
);

$valueAt208 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default208 = static fn (): array => $page208();
$unchanged208 = static fn (): array => $page208(nextRecords: $changedNextRecords208);
$implicitRows208 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentKeyRows208($currentRecords208);
$implicitNextRows208 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentKeyRows208($nextRecords208, 'next');

$cases208 = [
    'status ok from base' => [$default208, 'status', 'ok'],
    'operation marker' => [$default208, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next208'],
    'index sql retained' => [$default208, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo("wp_options_lookup")'],
    'fk sql retained' => [$default208, 'current_source.foreign_key_sql', 'pragma main.foreign_key_list("wp_options")'],
    'implicit source current' => [$default208, 'current_source.foreign_key_implicit_parent_key_source', 'pragma_foreign_key_list_omitted_parent_columns_plus_table_info_primary_key'],
    'implicit source next' => [$default208, 'next_source.foreign_key_implicit_parent_key_source', 'pragma_foreign_key_list_omitted_parent_columns_plus_table_info_primary_key'],
    'dependency appended' => [$default208, 'dependencies.5', 'sqlite-pragma-foreign-key-implicit-parent-key-coverage'],
    'current implicit rows count' => [$default208, 'current.foreign_key_implicit_parent_keys.rows', 3],
    'current resolved count' => [$default208, 'current.foreign_key_implicit_parent_keys.implicit_parent_key_resolved', 3],
    'current missing pk count' => [$default208, 'current.foreign_key_implicit_parent_keys.missing_parent_primary_key', 0],
    'current arity mismatch count' => [$default208, 'current.foreign_key_implicit_parent_keys.arity_mismatch', 0],
    'current child columns total' => [$default208, 'current.foreign_key_implicit_parent_keys.child_columns', 4],
    'current parent pk columns total' => [$default208, 'current.foreign_key_implicit_parent_keys.parent_primary_key_columns', 4],
    'next implicit rows count' => [$default208, 'next_counts.foreign_key_implicit_parent_keys.rows', 3],
    'next resolved count' => [$default208, 'next_counts.foreign_key_implicit_parent_keys.implicit_parent_key_resolved', 3],
    'next missing pk repaired' => [$default208, 'next_counts.foreign_key_implicit_parent_keys.missing_parent_primary_key', 0],
    'next arity mismatch zero' => [$default208, 'next_counts.foreign_key_implicit_parent_keys.arity_mismatch', 0],
    'delta rows unchanged' => [$default208, 'delta.foreign_key_implicit_parent_key_rows', 0],
    'delta mismatch zero' => [$default208, 'delta.foreign_key_implicit_parent_key_mismatch_delta', 0],
    'delta repaired false' => [$default208, 'delta.foreign_key_implicit_parent_key_repaired', false],
    'delta changed true' => [$default208, 'delta.foreign_key_implicit_parent_key_changed', true],
    'current source blog summary' => [$default208, 'current_source.foreign_key_implicit_parent_keys.0', 'current:wp_options#0->wp_blogs:child=blog_id:parent_pk=blog_id:implicit_parent_key_resolved'],
    'current source locale summary' => [$default208, 'current_source.foreign_key_implicit_parent_keys.1', 'current:wp_options#1->wp_locale_keys:child=locale_blog_id,locale:parent_pk=blog_id,locale:implicit_parent_key_resolved'],
    'current source option name old pk summary' => [$default208, 'current_source.foreign_key_implicit_parent_keys.2', 'current:wp_options#2->wp_option_names:child=option_name:parent_pk=default_name:implicit_parent_key_resolved'],
    'next source option name repaired summary' => [$default208, 'next_source.foreign_key_implicit_parent_keys.2', 'next:wp_options#2->wp_option_names:child=option_name:parent_pk=name:implicit_parent_key_resolved'],
    'total includes base and implicit rows' => [$default208, 'total', 36],
    'count default' => [$default208, 'count', 36],
    'next null complete' => [$default208, 'next', null],
    'first implicit row kind' => [$default208, 'rows.30.kind', 'foreign_key_implicit_parent_key'],
    'first implicit row phase' => [$default208, 'rows.30.phase', 'current'],
    'first implicit row status' => [$default208, 'rows.30.status', 'implicit_parent_key_resolved'],
    'first implicit parent' => [$default208, 'rows.30.parent', 'wp_blogs'],
    'first implicit child column' => [$default208, 'rows.30.child_columns.0', 'blog_id'],
    'first implicit resolved parent column' => [$default208, 'rows.30.resolved_parent_columns.0', 'blog_id'],
    'composite implicit child second column' => [$default208, 'rows.31.child_columns.1', 'locale'],
    'composite implicit parent second column' => [$default208, 'rows.31.resolved_parent_columns.1', 'locale'],
    'old option name pk status' => [$default208, 'rows.32.status', 'implicit_parent_key_resolved'],
    'old option name pk has one parent column' => [$default208, 'rows.32.parent_primary_key_count', 1],
    'next repaired status' => [$default208, 'rows.35.status', 'implicit_parent_key_resolved'],
    'next repaired parent column' => [$default208, 'rows.35.resolved_parent_columns.0', 'name'],
    'unchanged next source old pk' => [$unchanged208, 'next_source.foreign_key_implicit_parent_keys.2', 'next:wp_options#2->wp_option_names:child=option_name:parent_pk=default_name:implicit_parent_key_resolved'],
    'unchanged changed false' => [$unchanged208, 'delta.foreign_key_implicit_parent_key_changed', false],
    'unchanged mismatch delta zero' => [$unchanged208, 'delta.foreign_key_implicit_parent_key_mismatch_delta', 0],
    'unchanged repaired false' => [$unchanged208, 'delta.foreign_key_implicit_parent_key_repaired', false],
    'helper first kind' => [$implicitRows208, '0.kind', 'foreign_key_implicit_parent_key'],
    'helper first resolved' => [$implicitRows208, '0.status', 'implicit_parent_key_resolved'],
    'helper second composite child count' => [$implicitRows208, '1.child_column_count', 2],
    'helper second parent pk count' => [$implicitRows208, '1.parent_primary_key_count', 2],
    'helper third old pk resolved' => [$implicitRows208, '2.resolved_parent_columns.0', 'default_name'],
    'helper next phase' => [$implicitNextRows208, '0.phase', 'next'],
    'helper next third repaired' => [$implicitNextRows208, '2.status', 'implicit_parent_key_resolved'],
];

$tests = [];
foreach ($cases208 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey implicit parent current source next208 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt208): void {
        $t->same($expected, $valueAt208($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey implicit parent current source next208 paginates implicit rows'] = static function (TestRunner $t) use ($page208): void {
    $first = $page208(0, 30);
    $second = $page208(30, 4, $first['next']);
    $third = $page208(34, 3, $second['next']);

    $t->same(30, $first['count']);
    $t->same('foreign_key_implicit_parent_key', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 30], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('implicit_parent_key_resolved', $second['rows'][0]['status']);
    $t->same('implicit_parent_key_resolved', $second['rows'][2]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('implicit_parent_key_resolved', $third['rows'][1]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey implicit parent current source next208 rejects stale cursor'] = static function (TestRunner $t) use ($page208, $changedNextRecords208): void {
    $first = $page208(0, 30);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page208(30, 4, $first['next'], $changedNextRecords208));
};

$tests['pragma index xinfo foreignkey implicit parent current source next208 rejects stale offset'] = static function (TestRunner $t) use ($page208): void {
    $first = $page208(0, 30);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page208(31, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey implicit parent current source next208 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentKeyRows208([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey implicit parent current source next208 rejects invalid bounds'] = static function (TestRunner $t) use ($page208): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page208(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page208(0, 0));
};

return $tests;
