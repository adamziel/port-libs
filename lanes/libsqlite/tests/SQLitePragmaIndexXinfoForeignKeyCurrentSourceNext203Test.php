<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record203 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords203 = [
    $record203('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, UNIQUE(slug))', 1),
    $record203('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $record203('table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT NOT NULL, site_id INTEGER NOT NULL, UNIQUE(site_id, post_name))', 3),
    $record203('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 5, null, 4),
    $record203('table', 'wp_termmeta_import', 'wp_termmeta_import', 6, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_slug TEXT COLLATE NOCASE NOT NULL,
        locale TEXT COLLATE RTRIM NOT NULL,
        site_id INTEGER NOT NULL,
        post_name TEXT NOT NULL,
        meta_key TEXT,
        FOREIGN KEY(term_slug, locale) REFERENCES wp_terms(slug, locale) ON UPDATE CASCADE ON DELETE RESTRICT,
        FOREIGN KEY(site_id, post_name) REFERENCES wp_posts(site_id, post_name) ON UPDATE CASCADE ON DELETE CASCADE
    )", 5),
    $record203('index', 'wp_termmeta_lookup', 'wp_termmeta_import', 7, 'CREATE INDEX wp_termmeta_lookup ON wp_termmeta_import(term_slug COLLATE NOCASE, locale COLLATE RTRIM, site_id, post_name)', 6),
];

$nextRecords203 = [
    $record203('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL, locale TEXT COLLATE RTRIM NOT NULL, UNIQUE(slug, locale))', 1),
    $record203('index', 'sqlite_autoindex_wp_terms_1', 'wp_terms', 3, null, 2),
    $currentRecords203[2],
    $currentRecords203[3],
    $currentRecords203[4],
    $currentRecords203[5],
];

$explicitNextRecords203 = [
    $currentRecords203[0],
    $currentRecords203[2],
    $currentRecords203[3],
    $currentRecords203[4],
    $currentRecords203[5],
    $record203('index', 'wp_terms_slug_locale_unique', 'wp_terms', 8, 'CREATE UNIQUE INDEX wp_terms_slug_locale_unique ON wp_terms(slug COLLATE NOCASE, locale COLLATE RTRIM)', 7),
];

$badUniqueRecords203 = [
    $currentRecords203[0],
    $currentRecords203[2],
    $currentRecords203[3],
    $currentRecords203[4],
    $currentRecords203[5],
    $record203('index', 'wp_terms_locale_slug_unique', 'wp_terms', 8, 'CREATE UNIQUE INDEX wp_terms_locale_slug_unique ON wp_terms(locale COLLATE RTRIM, slug COLLATE NOCASE)', 7),
    $record203('index', 'wp_terms_slug_locale_partial', 'wp_terms', 9, "CREATE UNIQUE INDEX wp_terms_slug_locale_partial ON wp_terms(slug COLLATE NOCASE, locale COLLATE RTRIM) WHERE locale <> ''", 8),
    $record203('index', 'wp_terms_slug_expr_unique', 'wp_terms', 10, 'CREATE UNIQUE INDEX wp_terms_slug_expr_unique ON wp_terms(slug COLLATE NOCASE, lower(locale))', 9),
];

$page203 = static fn (
    int $offset = 0,
    int $limit = 50,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page203(
    $currentRecords203,
    $nextRecords ?? $nextRecords203,
    'PRAGMA main.index_xinfo(wp_termmeta_lookup)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt203 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default203 = static fn (): array => $page203();
$explicit203 = static fn (): array => $page203(nextRecords: $explicitNextRecords203);
$blocked203 = static fn (): array => $page203(nextRecords: $badUniqueRecords203);
$coverageCurrent203 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentCoverageRows203($currentRecords203);
$coverageNext203 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentCoverageRows203($nextRecords203, 'next');

$cases203 = [
    'status ok' => [$default203, 'status', 'ok'],
    'operation marker' => [$default203, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next203'],
    'normalized index sql retained' => [$default203, 'current_source.index_xinfo_sql', 'pragma main.index_xinfo("wp_termmeta_lookup")'],
    'normalized fk sql retained' => [$default203, 'current_source.foreign_key_sql', 'pragma main.foreign_key_list("wp_termmeta_import")'],
    'coverage source label' => [$default203, 'current_source.foreign_key_parent_coverage_source', 'pragma_foreign_key_list_parent_groups_plus_pragma_index_list_xinfo'],
    'next coverage source label' => [$default203, 'next_source.foreign_key_parent_coverage_source', 'pragma_foreign_key_list_parent_groups_plus_pragma_index_list_xinfo'],
    'dependency appended' => [$default203, 'dependencies.3', 'sqlite-pragma-foreign-key-parent-index-coverage'],
    'current coverage rows' => [$default203, 'current.foreign_key_parent_coverage.rows', 2],
    'current covered count' => [$default203, 'current.foreign_key_parent_coverage.covered', 1],
    'current missing count' => [$default203, 'current.foreign_key_parent_coverage.missing_parent_unique', 1],
    'current autoindex count' => [$default203, 'current.foreign_key_parent_coverage.autoindex', 1],
    'current created index count' => [$default203, 'current.foreign_key_parent_coverage.created_index', 0],
    'next coverage rows' => [$default203, 'next_counts.foreign_key_parent_coverage.rows', 2],
    'next covered count' => [$default203, 'next_counts.foreign_key_parent_coverage.covered', 2],
    'next missing count' => [$default203, 'next_counts.foreign_key_parent_coverage.missing_parent_unique', 0],
    'next autoindex count' => [$default203, 'next_counts.foreign_key_parent_coverage.autoindex', 2],
    'delta coverage rows' => [$default203, 'delta.foreign_key_parent_coverage_rows', 0],
    'delta missing repaired' => [$default203, 'delta.foreign_key_parent_coverage_missing', -1],
    'delta covered added' => [$default203, 'delta.foreign_key_parent_coverage_covered', 1],
    'delta repaired true' => [$default203, 'delta.foreign_key_parent_coverage_repaired', true],
    'delta changed true' => [$default203, 'delta.foreign_key_parent_coverage_changed', true],
    'base index count current retained' => [$default203, 'current.index_xinfo', 5],
    'base fk count current retained' => [$default203, 'current.foreign_key_list', 4],
    'base fk groups current retained' => [$default203, 'current.foreign_key_groups', 2],
    'total includes base and coverage rows' => [$default203, 'total', 22],
    'current coverage summary missing' => [$default203, 'current_source.foreign_key_parent_coverage.0', 'current:wp_termmeta_import#0->wp_terms:slug,locale:missing:missing_parent_unique'],
    'next coverage summary covered' => [$default203, 'next_source.foreign_key_parent_coverage.0', 'next:wp_termmeta_import#0->wp_terms:slug,locale:sqlite_autoindex_wp_terms_1:covered'],
    'first coverage row kind' => [$default203, 'rows.18.kind', 'foreign_key_parent_coverage'],
    'first coverage row table' => [$default203, 'rows.18.table', 'wp_termmeta_import'],
    'first coverage missing status' => [$default203, 'rows.18.status', 'missing_parent_unique'],
    'first coverage parent null' => [$default203, 'rows.18.parent_index', null],
    'first coverage parent first column' => [$default203, 'rows.18.parent_columns.0', 'slug'],
    'first coverage parent second column' => [$default203, 'rows.18.parent_columns.1', 'locale'],
    'second coverage covered status' => [$default203, 'rows.19.status', 'covered'],
    'second coverage parent index' => [$default203, 'rows.19.parent_index', 'sqlite_autoindex_wp_posts_1'],
    'second coverage origin' => [$default203, 'rows.19.parent_index_origin', 'u'],
    'second coverage child column' => [$default203, 'rows.19.child_columns.1', 'post_name'],
    'next repaired coverage status' => [$default203, 'rows.20.status', 'covered'],
    'next repaired parent index' => [$default203, 'rows.20.parent_index', 'sqlite_autoindex_wp_terms_1'],
    'next repaired collation first' => [$default203, 'rows.20.parent_index_collations.0', 'NOCASE'],
    'next repaired collation second' => [$default203, 'rows.20.parent_index_collations.1', 'RTRIM'],
    'next posts still covered' => [$default203, 'rows.21.parent_index', 'sqlite_autoindex_wp_posts_1'],
    'explicit next created index count' => [$explicit203, 'next_counts.foreign_key_parent_coverage.created_index', 1],
    'explicit next parent index' => [$explicit203, 'rows.20.parent_index', 'wp_terms_slug_locale_unique'],
    'explicit next origin' => [$explicit203, 'rows.20.parent_index_origin', 'c'],
    'blocked next missing' => [$blocked203, 'next_counts.foreign_key_parent_coverage.missing_parent_unique', 1],
    'blocked repaired false' => [$blocked203, 'delta.foreign_key_parent_coverage_repaired', false],
    'blocked changed false for terms fk' => [$blocked203, 'next_source.foreign_key_parent_coverage.0', 'next:wp_termmeta_import#0->wp_terms:slug,locale:missing:missing_parent_unique'],
    'helper current rows' => [$coverageCurrent203, '0.kind', 'foreign_key_parent_coverage'],
    'helper current missing' => [$coverageCurrent203, '0.status', 'missing_parent_unique'],
    'helper current covered' => [$coverageCurrent203, '1.status', 'covered'],
    'helper next phase' => [$coverageNext203, '0.phase', 'next'],
    'helper next covered' => [$coverageNext203, '0.status', 'covered'],
];

$tests = [];
foreach ($cases203 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent coverage current source next203 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt203): void {
        $t->same($expected, $valueAt203($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent coverage current source next203 paginates coverage rows'] = static function (TestRunner $t) use ($page203): void {
    $first = $page203(0, 18);
    $second = $page203(18, 2, $first['next']);
    $third = $page203(20, 2, $second['next']);

    $t->same(18, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 18], $first['next']);
    $t->same('foreign_key_parent_coverage', $first['next_row']['kind']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('missing_parent_unique', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent coverage current source next203 rejects stale cursor'] = static function (TestRunner $t) use ($page203, $badUniqueRecords203): void {
    $first = $page203(0, 18);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page203(18, 2, $first['next'], $badUniqueRecords203));
};

$tests['pragma index xinfo foreignkey parent coverage current source next203 rejects stale offset'] = static function (TestRunner $t) use ($page203): void {
    $first = $page203(0, 18);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page203(19, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey parent coverage current source next203 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentCoverageRows203([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey parent coverage current source next203 rejects invalid bounds'] = static function (TestRunner $t) use ($page203): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page203(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page203(0, 0));
};

return $tests;
