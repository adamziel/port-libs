<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record219 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords219 = [
    $record219('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(blog_id INTEGER NOT NULL, slug TEXT NOT NULL, locale TEXT NOT NULL, name TEXT)', 1),
    $record219('index', 'wp_terms_slug_blog_unique', 'wp_terms', 3, 'CREATE UNIQUE INDEX wp_terms_slug_blog_unique ON wp_terms(slug, blog_id)', 2),
    $record219('index', 'wp_terms_locale_slug_unique', 'wp_terms', 4, 'CREATE UNIQUE INDEX wp_terms_locale_slug_unique ON wp_terms(locale, slug)', 3),
    $record219('table', 'wp_termmeta_stage', 'wp_termmeta_stage', 5, "CREATE TABLE wp_termmeta_stage(
        meta_id INTEGER PRIMARY KEY,
        blog_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        locale TEXT NOT NULL,
        meta_key TEXT NOT NULL,
        FOREIGN KEY(blog_id, slug) REFERENCES wp_terms(blog_id, slug) ON UPDATE CASCADE,
        FOREIGN KEY(locale, slug) REFERENCES wp_terms(locale, slug) ON DELETE CASCADE
    )", 4),
    $record219('index', 'wp_termmeta_stage_slug_blog', 'wp_termmeta_stage', 6, 'CREATE INDEX wp_termmeta_stage_slug_blog ON wp_termmeta_stage(slug, blog_id)', 5),
];

$nextRecords219 = [
    $currentRecords219[0],
    $record219('index', 'wp_terms_blog_slug_unique', 'wp_terms', 7, 'CREATE UNIQUE INDEX wp_terms_blog_slug_unique ON wp_terms(blog_id, slug)', 6),
    $currentRecords219[2],
    $currentRecords219[3],
    $currentRecords219[4],
];

$unrepairedRecords219 = [
    $currentRecords219[0],
    $currentRecords219[1],
    $record219('index', 'wp_terms_slug_locale_unique', 'wp_terms', 8, 'CREATE UNIQUE INDEX wp_terms_slug_locale_unique ON wp_terms(slug, locale)', 6),
    $currentRecords219[3],
    $currentRecords219[4],
];

$page219 = static fn (
    int $offset = 0,
    int $limit = 100,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page219(
    $currentRecords219,
    $nextRecords ?? $nextRecords219,
    'PRAGMA main.index_xinfo(wp_terms_slug_blog_unique)',
    'PRAGMA main.foreign_key_list(wp_termmeta_stage)',
    $offset,
    $limit,
    $resume,
);

$valueAt219 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default219 = static fn (): array => $page219();
$unrepaired219 = static fn (): array => $page219(nextRecords: $unrepairedRecords219);
$currentPermutation219 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentUniqueRows219($currentRecords219);
$nextPermutation219 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentUniqueRows219($nextRecords219, 'next');

$cases219 = [
    'status ok' => [$default219, 'status', 'ok'],
    'operation marker' => [$default219, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next219'],
    'source id length' => [static fn (): array => ['len' => strlen($page219()['source_id'])], 'len', 64],
    'offset default' => [$default219, 'offset', 0],
    'limit default' => [$default219, 'limit', 100],
    'dependency appended' => [$default219, 'dependencies.8', 'sqlite-pragma-foreign-key-parent-unique-column-order'],
    'base prefix retained' => [$default219, 'current.foreign_key_parent_key_prefix.rows', 4],
    'permutation source current' => [$default219, 'current_source.foreign_key_parent_key_permutation_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_unique_column_order'],
    'permutation source next' => [$default219, 'next_source.foreign_key_parent_key_permutation_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_unique_column_order'],
    'current permutation rows' => [$default219, 'current.foreign_key_parent_key_permutation.rows', 2],
    'current permutation blockers' => [$default219, 'current.foreign_key_parent_key_permutation.permuted_parent_unique_index', 2],
    'current permutation foreign keys' => [$default219, 'current.foreign_key_parent_key_permutation.foreign_keys', 1],
    'current reordered columns' => [$default219, 'current.foreign_key_parent_key_permutation.reordered_columns', 2],
    'next permutation rows' => [$default219, 'next_counts.foreign_key_parent_key_permutation.rows', 0],
    'next permutation blockers' => [$default219, 'next_counts.foreign_key_parent_key_permutation.permuted_parent_unique_index', 0],
    'next permutation foreign keys' => [$default219, 'next_counts.foreign_key_parent_key_permutation.foreign_keys', 0],
    'delta rows negative' => [$default219, 'delta.foreign_key_parent_key_permutation_rows', -2],
    'delta blockers negative' => [$default219, 'delta.foreign_key_parent_key_permutation_blockers', -2],
    'delta repaired true' => [$default219, 'delta.foreign_key_parent_key_permutation_repaired', true],
    'delta changed true' => [$default219, 'delta.foreign_key_parent_key_permutation_changed', true],
    'current summary first' => [$default219, 'current_source.foreign_key_parent_key_permutation.0', 'current:wp_termmeta_stage#0.0:blog_id->wp_terms.blog_id:parent=blog_id,slug:wp_terms_slug_blog_unique:columns=slug,blog_id:expected=0:actual=1:permuted_parent_unique_index'],
    'current summary second' => [$default219, 'current_source.foreign_key_parent_key_permutation.1', 'current:wp_termmeta_stage#0.1:slug->wp_terms.slug:parent=blog_id,slug:wp_terms_slug_blog_unique:columns=slug,blog_id:expected=1:actual=0:permuted_parent_unique_index'],
    'next summary empty' => [$default219, 'next_source.foreign_key_parent_key_permutation', []],
    'first appended row kind' => [$default219, 'rows.35.kind', 'foreign_key_parent_key_permutation'],
    'first appended row status' => [$default219, 'rows.35.status', 'permuted_parent_unique_index'],
    'first appended index' => [$default219, 'rows.35.permuted_unique_index', 'wp_terms_slug_blog_unique'],
    'first appended expected position' => [$default219, 'rows.35.expected_position', 0],
    'first appended actual position' => [$default219, 'rows.35.actual_position', 1],
    'second appended from column' => [$default219, 'rows.36.from', 'slug'],
    'second appended actual position' => [$default219, 'rows.36.actual_position', 0],
    'unrepaired next rows' => [$unrepaired219, 'next_counts.foreign_key_parent_key_permutation.rows', 4],
    'unrepaired blockers positive' => [$unrepaired219, 'next_counts.foreign_key_parent_key_permutation.permuted_parent_unique_index', 4],
    'unrepaired delta blockers' => [$unrepaired219, 'delta.foreign_key_parent_key_permutation_blockers', 2],
    'unrepaired repaired false' => [$unrepaired219, 'delta.foreign_key_parent_key_permutation_repaired', false],
    'helper current row count' => [$currentPermutation219, '0.kind', 'foreign_key_parent_key_permutation'],
    'helper current first parent columns' => [$currentPermutation219, '0.parent_columns.0', 'blog_id'],
    'helper current first permuted columns' => [$currentPermutation219, '0.permuted_unique_columns.0', 'slug'],
    'helper current first actual position' => [$currentPermutation219, '0.actual_position', 1],
    'helper current second expected position' => [$currentPermutation219, '1.expected_position', 1],
    'helper current second actual position' => [$currentPermutation219, '1.actual_position', 0],
    'helper next empty' => [static fn (): array => ['count' => count($nextPermutation219())], 'count', 0],
];

$tests = [];
foreach ($cases219 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent key permutation current source next219 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt219): void {
        $t->same($expected, $valueAt219($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent key permutation current source next219 paginates appended rows'] = static function (TestRunner $t) use ($page219): void {
    $first = $page219(0, 35);
    $second = $page219(35, 1, $first['next']);
    $third = $page219(36, 1, $second['next']);

    $t->same(35, $first['count']);
    $t->same('foreign_key_parent_key_permutation', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 35], $first['next']);
    $t->same('blog_id', $second['rows'][0]['from']);
    $t->same(1, $second['rows'][0]['actual_position']);
    $t->same('slug', $third['rows'][0]['from']);
    $t->same(0, $third['rows'][0]['actual_position']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent key permutation current source next219 ignores suffix and partial candidates'] = static function (TestRunner $t) use ($record219): void {
    $records = [
        $record219('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a TEXT, b TEXT, active INTEGER)', 1),
        $record219('index', 'parent_extra_prefix', 'parent', 3, 'CREATE UNIQUE INDEX parent_extra_prefix ON parent(active, b, a)', 2),
        $record219('index', 'parent_partial_permuted', 'parent', 4, 'CREATE UNIQUE INDEX parent_partial_permuted ON parent(b, a) WHERE active = 1', 3),
        $record219('table', 'child', 'child', 5, 'CREATE TABLE child(a TEXT, b TEXT, FOREIGN KEY(a, b) REFERENCES parent(a, b))', 4),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentUniqueRows219($records));
};

$tests['pragma index xinfo foreignkey parent key permutation current source next219 reports all rows for a three column permutation'] = static function (TestRunner $t) use ($record219): void {
    $records = [
        $record219('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a TEXT, b TEXT, c TEXT)', 1),
        $record219('index', 'parent_cba_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_cba_unique ON parent(c, b, a)', 2),
        $record219('table', 'child', 'child', 4, 'CREATE TABLE child(a TEXT, b TEXT, c TEXT, FOREIGN KEY(a, b, c) REFERENCES parent(a, b, c))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentUniqueRows219($records);
    $t->same(3, count($rows));
    $t->same([2, 1, 0], array_column($rows, 'actual_position'));
    $t->same('parent_cba_unique', $rows[2]['permuted_unique_index']);
};

$tests['pragma index xinfo foreignkey parent key permutation current source next219 rejects stale cursor'] = static function (TestRunner $t) use ($page219, $unrepairedRecords219): void {
    $first = $page219(0, 32);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page219(32, 1, $first['next'], $unrepairedRecords219));
};

$tests['pragma index xinfo foreignkey parent key permutation current source next219 rejects stale offset'] = static function (TestRunner $t) use ($page219): void {
    $first = $page219(0, 32);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page219(33, 1, $first['next']));
};

$tests['pragma index xinfo foreignkey parent key permutation current source next219 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::permutedParentUniqueRows219([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey parent key permutation current source next219 rejects invalid bounds'] = static function (TestRunner $t) use ($page219): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page219(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page219(0, 0));
};

return $tests;
