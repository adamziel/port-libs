<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record204 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords204 = [
    $record204('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT NOT NULL, site_id INTEGER NOT NULL, UNIQUE(site_id, post_name))', 1),
    $record204('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 3, null, 2),
    $record204('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, UNIQUE(slug, locale))', 3),
    $record204('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 5, null, 4),
    $record204('table', 'wp_termmeta_import', 'wp_termmeta_import', 6, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_slug TEXT COLLATE NOCASE NOT NULL,
        locale TEXT COLLATE RTRIM NOT NULL,
        site_id INTEGER NOT NULL,
        post_name TEXT NOT NULL,
        meta_key TEXT,
        FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale) ON UPDATE CASCADE ON DELETE RESTRICT,
        FOREIGN KEY(site_id, post_name) REFERENCES wp_posts(site_id, post_name) ON UPDATE CASCADE ON DELETE CASCADE
    )", 5),
    $record204('index', 'wp_termmeta_lookup', 'wp_termmeta_import', 7, 'CREATE INDEX wp_termmeta_lookup ON wp_termmeta_import(site_id DESC, post_name, meta_key)', 6),
];

$nextRecords204 = [
    $currentRecords204[0],
    $currentRecords204[1],
    $currentRecords204[2],
    $currentRecords204[3],
    $currentRecords204[4],
    $currentRecords204[5],
    $record204('index', 'wp_termmeta_fk_terms_lookup', 'wp_termmeta_import', 8, 'CREATE INDEX wp_termmeta_fk_terms_lookup ON wp_termmeta_import(term_slug COLLATE NOCASE, locale COLLATE RTRIM, meta_id)', 7),
];

$uniqueNextRecords204 = [
    $currentRecords204[0],
    $currentRecords204[1],
    $currentRecords204[2],
    $currentRecords204[3],
    $currentRecords204[4],
    $currentRecords204[5],
    $record204('index', 'wp_termmeta_fk_terms_unique', 'wp_termmeta_import', 8, 'CREATE UNIQUE INDEX wp_termmeta_fk_terms_unique ON wp_termmeta_import(term_slug COLLATE NOCASE, locale COLLATE RTRIM)', 7),
];

$badNextRecords204 = [
    $currentRecords204[0],
    $currentRecords204[1],
    $currentRecords204[2],
    $currentRecords204[3],
    $currentRecords204[4],
    $currentRecords204[5],
    $record204('index', 'wp_termmeta_fk_terms_partial', 'wp_termmeta_import', 8, "CREATE INDEX wp_termmeta_fk_terms_partial ON wp_termmeta_import(term_slug COLLATE NOCASE, locale COLLATE RTRIM) WHERE locale <> ''", 7),
    $record204('index', 'wp_termmeta_fk_terms_reversed', 'wp_termmeta_import', 9, 'CREATE INDEX wp_termmeta_fk_terms_reversed ON wp_termmeta_import(locale COLLATE RTRIM, term_slug COLLATE NOCASE)', 8),
    $record204('index', 'wp_termmeta_fk_terms_expr', 'wp_termmeta_import', 10, 'CREATE INDEX wp_termmeta_fk_terms_expr ON wp_termmeta_import(term_slug COLLATE NOCASE, lower(locale))', 9),
];

$page204 = static fn (
    int $offset = 0,
    int $limit = 60,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page204(
    $currentRecords204,
    $nextRecords ?? $nextRecords204,
    'PRAGMA main.index_xinfo(wp_termmeta_lookup)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt204 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default204 = static fn (): array => $page204();
$unique204 = static fn (): array => $page204(nextRecords: $uniqueNextRecords204);
$blocked204 = static fn (): array => $page204(nextRecords: $badNextRecords204);
$childCurrent204 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childIndexRows($currentRecords204);
$childNext204 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childIndexRows($nextRecords204, 'next');

$cases204 = [
    'status ok' => [$default204, 'status', 'ok'],
    'operation marker' => [$default204, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next204'],
    'base source retained' => [$default204, 'current_source.foreign_key_parent_coverage_source', 'pragma_foreign_key_list_parent_groups_plus_pragma_index_list_xinfo'],
    'child source label' => [$default204, 'current_source.foreign_key_child_index_source', 'pragma_foreign_key_list_child_groups_plus_pragma_index_list_xinfo'],
    'next child source label' => [$default204, 'next_source.foreign_key_child_index_source', 'pragma_foreign_key_list_child_groups_plus_pragma_index_list_xinfo'],
    'dependency appended' => [$default204, 'dependencies.4', 'sqlite-pragma-foreign-key-child-index-coverage'],
    'current child rows' => [$default204, 'current.foreign_key_child_index.rows', 2],
    'current covered count' => [$default204, 'current.foreign_key_child_index.covered', 1],
    'current missing count' => [$default204, 'current.foreign_key_child_index.missing_child_index', 1],
    'current non unique count' => [$default204, 'current.foreign_key_child_index.non_unique', 1],
    'current unique count' => [$default204, 'current.foreign_key_child_index.unique', 0],
    'current descending prefix count' => [$default204, 'current.foreign_key_child_index.descending_prefix', 1],
    'next child rows' => [$default204, 'next_counts.foreign_key_child_index.rows', 2],
    'next covered count' => [$default204, 'next_counts.foreign_key_child_index.covered', 2],
    'next missing count' => [$default204, 'next_counts.foreign_key_child_index.missing_child_index', 0],
    'next non unique count' => [$default204, 'next_counts.foreign_key_child_index.non_unique', 2],
    'delta rows unchanged' => [$default204, 'delta.foreign_key_child_index_rows', 0],
    'delta missing repaired' => [$default204, 'delta.foreign_key_child_index_missing', -1],
    'delta covered added' => [$default204, 'delta.foreign_key_child_index_covered', 1],
    'delta repaired true' => [$default204, 'delta.foreign_key_child_index_repaired', true],
    'delta changed true' => [$default204, 'delta.foreign_key_child_index_changed', true],
    'total includes child rows' => [$default204, 'total', 24],
    'current summary missing first' => [$default204, 'current_source.foreign_key_child_index.0', 'current:wp_termmeta_import#0->wp_terms:term_slug,locale:missing:missing_child_index'],
    'next summary repaired first' => [$default204, 'next_source.foreign_key_child_index.0', 'next:wp_termmeta_import#0->wp_terms:term_slug,locale:wp_termmeta_fk_terms_lookup:covered'],
    'first child row kind' => [$default204, 'rows.20.kind', 'foreign_key_child_index'],
    'first child row missing' => [$default204, 'rows.20.status', 'missing_child_index'],
    'first child index null' => [$default204, 'rows.20.child_index', null],
    'first child column term slug' => [$default204, 'rows.20.child_columns.0', 'term_slug'],
    'first child parent column slug' => [$default204, 'rows.20.parent_columns.0', 'slug'],
    'second child row covered' => [$default204, 'rows.21.status', 'covered'],
    'second child index name' => [$default204, 'rows.21.child_index', 'wp_termmeta_lookup'],
    'second child first prefix' => [$default204, 'rows.21.child_index_prefix_columns.0', 'site_id'],
    'second child first desc' => [$default204, 'rows.21.child_index_desc.0', 1],
    'second child non unique' => [$default204, 'rows.21.child_index_unique', 0],
    'next repaired child index' => [$default204, 'rows.22.child_index', 'wp_termmeta_fk_terms_lookup'],
    'next repaired child collation' => [$default204, 'rows.22.child_index_collations.0', 'NOCASE'],
    'next repaired second collation' => [$default204, 'rows.22.child_index_collations.1', 'RTRIM'],
    'next existing child covered' => [$default204, 'rows.23.child_index', 'wp_termmeta_lookup'],
    'unique next count' => [$unique204, 'next_counts.foreign_key_child_index.unique', 1],
    'unique next child index' => [$unique204, 'rows.22.child_index', 'wp_termmeta_fk_terms_unique'],
    'blocked next missing remains' => [$blocked204, 'next_counts.foreign_key_child_index.missing_child_index', 1],
    'blocked repaired false' => [$blocked204, 'delta.foreign_key_child_index_repaired', false],
    'blocked summary still missing' => [$blocked204, 'next_source.foreign_key_child_index.0', 'next:wp_termmeta_import#0->wp_terms:term_slug,locale:missing:missing_child_index'],
    'helper current kind' => [$childCurrent204, '0.kind', 'foreign_key_child_index'],
    'helper current missing' => [$childCurrent204, '0.status', 'missing_child_index'],
    'helper current covered' => [$childCurrent204, '1.status', 'covered'],
    'helper next phase' => [$childNext204, '0.phase', 'next'],
    'helper next covered' => [$childNext204, '0.status', 'covered'],
];

$tests = [];
foreach ($cases204 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child index current source next204 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt204): void {
        $t->same($expected, $valueAt204($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child index current source next204 paginates child rows'] = static function (TestRunner $t) use ($page204): void {
    $first = $page204(0, 20);
    $second = $page204(20, 2, $first['next']);
    $third = $page204(22, 2, $second['next']);

    $t->same(20, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 20], $first['next']);
    $t->same('foreign_key_child_index', $first['next_row']['kind']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('missing_child_index', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child index current source next204 rejects stale cursor'] = static function (TestRunner $t) use ($page204, $badNextRecords204): void {
    $first = $page204(0, 20);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page204(20, 2, $first['next'], $badNextRecords204));
};

$tests['pragma index xinfo foreignkey child index current source next204 rejects stale offset'] = static function (TestRunner $t) use ($page204): void {
    $first = $page204(0, 20);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page204(21, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey child index current source next204 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childIndexRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey child index current source next204 rejects invalid bounds'] = static function (TestRunner $t) use ($page204): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page204(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page204(0, 0));
};

return $tests;
