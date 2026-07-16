<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaForeignKeyIndexIntegrityYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$records = [
    $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 3, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, source TEXT)', 2),
    $record('index', 'wp_option_names_name_u', 'wp_option_names', 4, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE nocase)', 3),
    $record('table', 'wp_terms', 'wp_terms', 5, 'CREATE TABLE wp_terms(slug TEXT COLLATE RTRIM, taxonomy TEXT)', 4),
    $record('index', 'wp_terms_slug_u', 'wp_terms', 6, 'CREATE UNIQUE INDEX wp_terms_slug_u ON wp_terms(slug COLLATE rtrim)', 5),
    $record('table', 'wp_broken_parent', 'wp_broken_parent', 7, 'CREATE TABLE wp_broken_parent(code TEXT COLLATE NOCASE)', 6),
    $record('index', 'wp_broken_parent_code', 'wp_broken_parent', 8, 'CREATE INDEX wp_broken_parent_code ON wp_broken_parent(code COLLATE nocase)', 7),
    $record('table', 'wp_partial_parent', 'wp_partial_parent', 9, 'CREATE TABLE wp_partial_parent(code TEXT)', 8),
    $record('index', 'wp_partial_parent_code_u', 'wp_partial_parent', 10, 'CREATE UNIQUE INDEX wp_partial_parent_code_u ON wp_partial_parent(code) WHERE code IS NOT NULL', 9),
    $record('table', 'wp_collation_parent', 'wp_collation_parent', 11, 'CREATE TABLE wp_collation_parent(code TEXT COLLATE NOCASE)', 10),
    $record('index', 'wp_collation_parent_code_u', 'wp_collation_parent', 12, 'CREATE UNIQUE INDEX wp_collation_parent_code_u ON wp_collation_parent(code COLLATE binary)', 11),
    $record('table', 'wp_options', 'wp_options', 13, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER, option_name TEXT, term_slug TEXT, broken_code TEXT, partial_code TEXT, collated_code TEXT)', 12),
];

$foreignKeys = [
    ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']],
    ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase']]],
    ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_slug', 'parent' => 'slug', 'collation' => 'rtrim']]],
    ['id' => 3, 'table' => 'wp_options', 'parent' => 'wp_broken_parent', 'columns' => ['broken_code' => 'code']],
    ['id' => 4, 'table' => 'wp_options', 'parent' => 'wp_partial_parent', 'columns' => ['partial_code' => 'code']],
    ['id' => 5, 'table' => 'wp_options', 'parent' => 'wp_collation_parent', 'columns' => ['collated_code' => 'code']],
];

$tables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'source' => 'core'],
    ],
    'wp_terms' => [
        ['rowid' => 1, 'slug' => 'news ', 'taxonomy' => 'category'],
    ],
    'wp_broken_parent' => [
        ['rowid' => 1, 'code' => 'legacy'],
    ],
    'wp_partial_parent' => [
        ['rowid' => 1, 'code' => 'partial-ok'],
    ],
    'wp_collation_parent' => [
        ['rowid' => 1, 'code' => 'case-ok'],
    ],
    'wp_options' => [
        ['rowid' => 101, 'option_id' => 101, 'blog_id' => 1, 'option_name' => 'SITEURL', 'term_slug' => 'news', 'broken_code' => 'legacy', 'partial_code' => 'partial-ok', 'collated_code' => 'case-ok'],
        ['rowid' => 102, 'option_id' => 102, 'blog_id' => 404, 'option_name' => 'missing-name', 'term_slug' => null, 'broken_code' => null, 'partial_code' => null, 'collated_code' => null],
        ['rowid' => 103, 'option_id' => 103, 'blog_id' => null, 'option_name' => null, 'term_slug' => 'missing-term', 'broken_code' => null, 'partial_code' => null, 'collated_code' => null],
    ],
];

$collect = static fn (): array => SQLitePragmaForeignKeyIndexIntegrityYield::collect($records, $foreignKeys, $tables);
$page = static fn (int $offset = 0, int $limit = 71): array => SQLitePragmaForeignKeyIndexIntegrityYield::page($records, $foreignKeys, $tables, $offset, $limit);
$cleanPage = static fn (): array => SQLitePragmaForeignKeyIndexIntegrityYield::page($records, array_slice($foreignKeys, 0, 3), [
    ...$tables,
    'wp_options' => [
        ['rowid' => 201, 'option_id' => 201, 'blog_id' => 1, 'option_name' => 'siteurl', 'term_slug' => 'news '],
    ],
]);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'page status blocked' => [$page, 'status', 'blocked'],
    'page offset' => [$page, 'offset', 0],
    'page limit current next71' => [$page, 'limit', 71],
    'page count' => [$page, 'count', 9],
    'page total' => [$page, 'total', 9],
    'page complete' => [$page, 'complete', true],
    'page next offset null' => [$page, 'next_offset', null],
    'current index admissions' => [$page, 'current.index_admissions', 6],
    'current index blockers' => [$page, 'current.index_blockers', 3],
    'current foreign key violations' => [$page, 'current.foreign_key_violations', 3],
    'next ready false' => [$page, 'next.ready', false],
    'next blocker count' => [$page, 'next.blocking.count', 2],
    'next first blocker index' => [$page, 'next.blocking.0', 'foreign_key_parent_unique_index'],
    'next second blocker fk' => [$page, 'next.blocking.1', 'foreign_key_check'],
    'row0 kind index' => [$page, 'rows.0.kind', 'index_admission'],
    'row0 table options' => [$page, 'rows.0.table', 'wp_options'],
    'row0 parent sites' => [$page, 'rows.0.parent', 'wp_sites'],
    'row0 fkid rowid alias' => [$page, 'rows.0.fkid', 0],
    'row0 rowid null' => [$page, 'rows.0.rowid', null],
    'row0 rowid index alias' => [$page, 'rows.0.index', 'rowid-primary-key'],
    'row0 columns blog id' => [$page, 'rows.0.columns.0', 'blog_id'],
    'row0 collations binary' => [$page, 'rows.0.collations.0', 'BINARY'],
    'row0 status ok' => [$page, 'rows.0.status', 'ok'],
    'row1 named unique index' => [$page, 'rows.1.index', 'wp_option_names_name_u'],
    'row1 parent name' => [$page, 'rows.1.parent', 'wp_option_names'],
    'row1 column name' => [$page, 'rows.1.columns.0', 'name'],
    'row1 collation nocase' => [$page, 'rows.1.collations.0', 'NOCASE'],
    'row1 message names index' => [$page, 'rows.1.message', 'foreign key wp_options->wp_option_names parent key covered by wp_option_names_name_u'],
    'row2 rtrim unique index' => [$page, 'rows.2.index', 'wp_terms_slug_u'],
    'row2 rtrim collation' => [$page, 'rows.2.collations.0', 'RTRIM'],
    'row2 status ok' => [$page, 'rows.2.status', 'ok'],
    'row3 nonunique blocked index null' => [$page, 'rows.3.index', null],
    'row3 nonunique blocked status' => [$page, 'rows.3.status', 'blocked'],
    'row3 nonunique message' => [$page, 'rows.3.message', 'foreign key wp_options->wp_broken_parent parent key has no matching UNIQUE index'],
    'row4 partial unique blocked' => [$page, 'rows.4.status', 'blocked'],
    'row4 partial parent' => [$page, 'rows.4.parent', 'wp_partial_parent'],
    'row5 collation mismatch blocked' => [$page, 'rows.5.status', 'blocked'],
    'row5 expected nocase retained' => [$page, 'rows.5.collations.0', 'NOCASE'],
    'row6 first violation kind' => [$page, 'rows.6.kind', 'foreign_key_check'],
    'row6 first violation table' => [$page, 'rows.6.table', 'wp_options'],
    'row6 first violation rowid' => [$page, 'rows.6.rowid', 102],
    'row6 first violation parent' => [$page, 'rows.6.parent', 'wp_sites'],
    'row6 first violation fkid' => [$page, 'rows.6.fkid', 0],
    'row6 status violation' => [$page, 'rows.6.status', 'violation'],
    'row6 message' => [$page, 'rows.6.message', 'foreign key mismatch in wp_options rowid 102 references wp_sites fkid 0'],
    'row7 second violation rowid' => [$page, 'rows.7.rowid', 102],
    'row7 second violation parent' => [$page, 'rows.7.parent', 'wp_option_names'],
    'row8 third violation rowid' => [$page, 'rows.8.rowid', 103],
    'row8 third violation parent' => [$page, 'rows.8.parent', 'wp_terms'],
    'collect count' => [$collect, 'count', 9],
    'collect first admission' => [$collect, '0.kind', 'index_admission'],
    'collect last violation' => [$collect, '8.kind', 'foreign_key_check'],
    'offset two current row kind' => [static fn (): array => $page(2, 3), 'rows.0.parent', 'wp_terms'],
    'offset two count' => [static fn (): array => $page(2, 3), 'count', 3],
    'offset two next offset' => [static fn (): array => $page(2, 3), 'next_offset', 5],
    'offset six starts violations' => [static fn (): array => $page(6, 2), 'rows.0.kind', 'foreign_key_check'],
    'offset six incomplete' => [static fn (): array => $page(6, 2), 'complete', false],
    'offset eight complete' => [static fn (): array => $page(8, 2), 'complete', true],
    'offset eight rowid' => [static fn (): array => $page(8, 2), 'rows.0.rowid', 103],
    'clean status ok' => [$cleanPage, 'status', 'ok'],
    'clean next ready' => [$cleanPage, 'next.ready', true],
    'clean blocking empty' => [$cleanPage, 'next.blocking.count', 0],
    'clean total three admissions' => [$cleanPage, 'total', 3],
    'clean no fk violations' => [$cleanPage, 'current.foreign_key_violations', 0],
    'clean no index blockers' => [$cleanPage, 'current.index_blockers', 0],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma foreign key index integrity current next71 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma foreign key index integrity current next71 rejects non schema records'] = static function (TestRunner $t) use ($foreignKeys, $tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIndexIntegrityYield::page([['type' => 'table']], $foreignKeys, $tables));
};

$tests['pragma foreign key index integrity current next71 rejects malformed columns'] = static function (TestRunner $t) use ($records, $tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIndexIntegrityYield::page($records, [
        ['table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => []],
    ], $tables));
};

$tests['pragma foreign key index integrity current next71 rejects negative offset'] = static function (TestRunner $t) use ($records, $foreignKeys, $tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIndexIntegrityYield::page($records, $foreignKeys, $tables, -1, 71));
};

$tests['pragma foreign key index integrity current next71 rejects zero limit'] = static function (TestRunner $t) use ($records, $foreignKeys, $tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIndexIntegrityYield::page($records, $foreignKeys, $tables, 0, 0));
};

return $tests;
