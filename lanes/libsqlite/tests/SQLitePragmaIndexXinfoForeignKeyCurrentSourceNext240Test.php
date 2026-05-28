<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record240 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords240 = [
    $record240('table', 'wp_parent_posts', 'wp_parent_posts', 2, 'CREATE TABLE wp_parent_posts(ID INTEGER PRIMARY KEY, post_name TEXT NOT NULL, post_type TEXT NOT NULL)', 1),
    $record240('index', 'wp_parent_posts_name_type_unique', 'wp_parent_posts', 3, 'CREATE UNIQUE INDEX wp_parent_posts_name_type_unique ON wp_parent_posts(post_name, post_type)', 2),
    $record240('table', 'wp_parent_terms', 'wp_parent_terms', 4, 'CREATE TABLE wp_parent_terms(site_id INTEGER NOT NULL, term_id INTEGER NOT NULL, slug TEXT NOT NULL, PRIMARY KEY(site_id, term_id))', 3),
    $record240('table', 'wp_import_meta', 'wp_import_meta', 5, "CREATE TABLE wp_import_meta(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        site_id INTEGER NOT NULL,
        term_id INTEGER NOT NULL,
        legacy_parent INTEGER NOT NULL,
        FOREIGN KEY(post_id) REFERENCES wp_parent_posts,
        FOREIGN KEY(site_id, term_id) REFERENCES wp_parent_terms,
        FOREIGN KEY(legacy_parent) REFERENCES wp_parent_terms
    )", 4),
];

$nextRecords240 = [
    $currentRecords240[0],
    $currentRecords240[1],
    $currentRecords240[2],
    $record240('table', 'wp_import_meta', 'wp_import_meta', 5, "CREATE TABLE wp_import_meta(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        site_id INTEGER NOT NULL,
        term_id INTEGER NOT NULL,
        legacy_parent INTEGER NOT NULL,
        FOREIGN KEY(post_id) REFERENCES wp_parent_posts(ID),
        FOREIGN KEY(site_id, term_id) REFERENCES wp_parent_terms(site_id, term_id),
        FOREIGN KEY(legacy_parent) REFERENCES wp_parent_terms(site_id)
    )", 4),
];

$missingNextRecords240 = [
    $record240('table', 'wp_parent_posts', 'wp_parent_posts', 2, 'CREATE TABLE wp_parent_posts(ID INTEGER, post_name TEXT NOT NULL)', 1),
    $currentRecords240[2],
    $currentRecords240[3],
];

$page240 = static fn (
    int $offset = 0,
    int $limit = 220,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240::page(
    $currentRecords240,
    $nextRecords ?? $nextRecords240,
    'PRAGMA main.index_xinfo(wp_parent_posts_name_type_unique)',
    'PRAGMA main.foreign_key_list(wp_import_meta)',
    $offset,
    $limit,
    $resume,
);

$valueAt240 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default240 = static fn (): array => $page240();
$currentImplicit240 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240::implicitParentPrimaryKeyRows($currentRecords240);
$nextImplicit240 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240::implicitParentPrimaryKeyRows($nextRecords240, 'next');
$currentPageImplicit240 = static fn (): array => array_values(array_filter(
    $page240()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_implicit_parent_primary_key'
        && ($row['phase'] ?? null) === 'current'
        && array_key_exists('child_key_arity', $row),
));

$cases240 = [
    'status ok' => [$default240, 'status', 'ok'],
    'operation marker' => [$default240, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next240'],
    'source id length' => [static fn (): array => ['len' => strlen($page240()['source_id'])], 'len', 64],
    'offset default' => [$default240, 'offset', 0],
    'limit default' => [$default240, 'limit', 220],
    'base prefix unique retained' => [$default240, 'current.foreign_key_parent_prefix_unique.rows', 4],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-implicit-parent-primary-key', $page240()['dependencies'], true)], 'has', true],
    'implicit source current' => [$default240, 'current_source.foreign_key_implicit_parent_primary_key_source', 'pragma_foreign_key_list_empty_to_plus_parent_table_info_primary_key'],
    'implicit source next' => [$default240, 'next_source.foreign_key_implicit_parent_primary_key_source', 'pragma_foreign_key_list_empty_to_plus_parent_table_info_primary_key'],
    'current implicit rows' => [$default240, 'current.foreign_key_implicit_parent_primary_key.rows', 4],
    'current implicit ok rows' => [$default240, 'current.foreign_key_implicit_parent_primary_key.ok', 3],
    'current implicit blocked rows' => [$default240, 'current.foreign_key_implicit_parent_primary_key.blocked', 1],
    'current arity mismatch' => [$default240, 'current.foreign_key_implicit_parent_primary_key.parent_primary_key_arity_mismatch', 1],
    'current missing pk zero' => [$default240, 'current.foreign_key_implicit_parent_primary_key.missing_parent_primary_key', 0],
    'current implicit columns' => [$default240, 'current.foreign_key_implicit_parent_primary_key.implicit_columns', 3],
    'current composite rows' => [$default240, 'current.foreign_key_implicit_parent_primary_key.composite', 2],
    'next implicit rows zero' => [$default240, 'next_counts.foreign_key_implicit_parent_primary_key.rows', 0],
    'next blocked zero' => [$default240, 'next_counts.foreign_key_implicit_parent_primary_key.blocked', 0],
    'delta rows negative' => [$default240, 'delta.foreign_key_implicit_parent_primary_key_rows', -4],
    'delta blockers negative' => [$default240, 'delta.foreign_key_implicit_parent_primary_key_blockers', -1],
    'delta repaired true' => [$default240, 'delta.foreign_key_implicit_parent_primary_key_repaired', true],
    'delta changed true' => [$default240, 'delta.foreign_key_implicit_parent_primary_key_changed', true],
    'current summary first' => [$default240, 'current_source.foreign_key_implicit_parent_primary_key.0', 'current:wp_import_meta#0.0:post_id->wp_parent_posts.<implicit-pk>:parent_pk=ID:implicit=ID:arity=1/1:ok'],
    'current summary composite first' => [$default240, 'current_source.foreign_key_implicit_parent_primary_key.1', 'current:wp_import_meta#1.0:site_id->wp_parent_terms.<implicit-pk>:parent_pk=site_id,term_id:implicit=site_id:arity=2/2:ok'],
    'current summary mismatch' => [$default240, 'current_source.foreign_key_implicit_parent_primary_key.3', 'current:wp_import_meta#2.0:legacy_parent->wp_parent_terms.<implicit-pk>:parent_pk=site_id,term_id:implicit=site_id:arity=1/2:parent_primary_key_arity_mismatch'],
    'complete no next page' => [$default240, 'next', null],
    'first appended row kind' => [$currentPageImplicit240, '0.kind', 'foreign_key_implicit_parent_primary_key'],
    'first appended implicit column' => [$currentPageImplicit240, '0.implicit_parent_column', 'ID'],
    'first appended status' => [$currentPageImplicit240, '0.status', 'ok'],
    'first appended child arity' => [$currentPageImplicit240, '0.child_key_arity', 1],
    'first appended parent arity' => [$currentPageImplicit240, '0.parent_key_arity', 1],
    'composite first implicit column' => [$currentPageImplicit240, '1.implicit_parent_column', 'site_id'],
    'composite second implicit column' => [$currentPageImplicit240, '2.implicit_parent_column', 'term_id'],
    'mismatch status' => [$currentPageImplicit240, '3.status', 'parent_primary_key_arity_mismatch'],
    'mismatch parent arity' => [$currentPageImplicit240, '3.parent_key_arity', 2],
    'helper current first kind' => [$currentImplicit240, '0.kind', 'foreign_key_implicit_parent_primary_key'],
    'helper current first status' => [$currentImplicit240, '0.status', 'ok'],
    'helper current first to blank' => [$currentImplicit240, '0.to', ''],
    'helper current first pk' => [$currentImplicit240, '0.parent_primary_key_columns.0', 'ID'],
    'helper current composite second pk' => [$currentImplicit240, '2.implicit_parent_column', 'term_id'],
    'helper current mismatch status' => [$currentImplicit240, '3.status', 'parent_primary_key_arity_mismatch'],
    'helper next empty' => [static fn (): array => ['count' => count($nextImplicit240())], 'count', 0],
];

$tests = [];
foreach ($cases240 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey implicit parent primary key current source next240 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt240): void {
        $t->same($expected, $valueAt240($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey implicit parent primary key current source next240 paginates appended rows'] = static function (TestRunner $t) use ($page240): void {
    $full = $page240();
    $baseCount = $full['total'] - 4;
    $first = $page240(0, $baseCount);
    $second = $page240($baseCount, 2, $first['next']);
    $third = $page240($baseCount + 2, 2, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_implicit_parent_primary_key', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('ID', $second['rows'][0]['implicit_parent_column']);
    $t->same('term_id', $third['rows'][0]['implicit_parent_column']);
    $t->same('parent_primary_key_arity_mismatch', $third['rows'][1]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next240 ignores explicit parent columns'] = static function (TestRunner $t) use ($nextImplicit240): void {
    $t->same([], $nextImplicit240());
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next240 rejects stale cursor'] = static function (TestRunner $t) use ($page240, $missingNextRecords240): void {
    $full = $page240();
    $first = $page240(0, $full['total'] - 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page240($full['total'] - 4, 2, $first['next'], $missingNextRecords240));
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next240 rejects stale offset'] = static function (TestRunner $t) use ($page240): void {
    $full = $page240();
    $first = $page240(0, $full['total'] - 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page240($full['total'] - 3, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next240 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240::implicitParentPrimaryKeyRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey implicit parent primary key current source next240 rejects invalid bounds'] = static function (TestRunner $t) use ($page240): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page240(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page240(0, 0));
};

return $tests;
