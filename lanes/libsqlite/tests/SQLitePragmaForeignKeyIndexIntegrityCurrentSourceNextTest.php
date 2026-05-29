<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaForeignKeyIndexIntegrityCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
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
    ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
    ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_slug', 'parent' => 'slug', 'affinity' => 'text', 'collation' => 'rtrim']]],
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

$currentSource = 'cf317d66c8667530b6b7e4bc41f70f5a0397ef54';
$nextSource = 'pragma-foreignkey-index-integrity-current-source-next131';
$page = static fn (int $offset = 0, int $limit = 131, ?array $cursor = null, ?array $recordsValue = null, ?array $foreignKeysValue = null, ?array $tablesValue = null, string $sql = 'PRAGMA foreign_key_check'): array => SQLitePragmaForeignKeyIndexIntegrityCurrentSourceNext::page(
    $recordsValue ?? $records,
    $foreignKeysValue ?? $foreignKeys,
    $tablesValue ?? $tables,
    $currentSource,
    $nextSource,
    $offset,
    $limit,
    $sql,
    $cursor,
);

$cleanTables = [
    ...$tables,
    'wp_options' => [
        ['rowid' => 201, 'option_id' => 201, 'blog_id' => 1, 'option_name' => 'siteurl', 'term_slug' => 'news '],
    ],
];
$cleanPage = static fn (): array => $page(0, 131, null, null, array_slice($foreignKeys, 0, 3), $cleanTables);

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
    'status blocked' => [$page, 'status', 'blocked'],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'current source retained' => [$page, 'current_source.current', $currentSource],
    'next source retained' => [$page, 'current_source.next', $nextSource],
    'records hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['records_hash'])], 'len', 64],
    'foreign key hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['foreign_key_hash'])], 'len', 64],
    'table hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['table_hash'])], 'len', 64],
    'normalized foreign key sql' => [$page, 'current_source.integrity_sql', 'pragma foreign_key_check'],
    'offset zero' => [$page, 'offset', 0],
    'limit default next131' => [$page, 'limit', 131],
    'count full page' => [$page, 'count', 9],
    'total full page' => [$page, 'total', 9],
    'complete full page' => [$page, 'complete', true],
    'next null on full page' => [$page, 'next', null],
    'index admissions' => [$page, 'current.index_admissions', 6],
    'index blockers' => [$page, 'current.index_blockers', 3],
    'fk violations' => [$page, 'current.foreign_key_violations', 3],
    'row0 kind' => [$page, 'rows.0.kind', 'index_admission'],
    'row0 parent rowid alias' => [$page, 'rows.0.parent', 'wp_sites'],
    'row0 index rowid primary' => [$page, 'rows.0.index', 'rowid-primary-key'],
    'row0 status ok' => [$page, 'rows.0.status', 'ok'],
    'row1 unique index' => [$page, 'rows.1.index', 'wp_option_names_name_u'],
    'row1 collation nocase' => [$page, 'rows.1.collations.0', 'NOCASE'],
    'row2 unique index' => [$page, 'rows.2.index', 'wp_terms_slug_u'],
    'row2 collation rtrim' => [$page, 'rows.2.collations.0', 'RTRIM'],
    'row3 non unique blocked' => [$page, 'rows.3.status', 'blocked'],
    'row3 non unique message' => [$page, 'rows.3.message', 'foreign key wp_options->wp_broken_parent parent key has no matching UNIQUE index'],
    'row4 partial unique blocked' => [$page, 'rows.4.parent', 'wp_partial_parent'],
    'row5 collation mismatch blocked' => [$page, 'rows.5.parent', 'wp_collation_parent'],
    'row5 expected collation' => [$page, 'rows.5.collations.0', 'NOCASE'],
    'row6 violation kind' => [$page, 'rows.6.kind', 'foreign_key_check'],
    'row6 violation rowid' => [$page, 'rows.6.rowid', 102],
    'row6 violation parent' => [$page, 'rows.6.parent', 'wp_sites'],
    'row7 violation parent' => [$page, 'rows.7.parent', 'wp_option_names'],
    'row8 violation rowid' => [$page, 'rows.8.rowid', 103],
    'row8 violation parent' => [$page, 'rows.8.parent', 'wp_terms'],
    'small page count' => [static fn (): array => $page(0, 4), 'count', 4],
    'small page next offset' => [static fn (): array => $page(0, 4), 'next_offset', 4],
    'small page next source id length' => [static fn (): array => ['len' => strlen((string) $page(0, 4)['next']['source_id'])], 'len', 64],
    'small page next ready false' => [static fn (): array => $page(0, 4), 'next.ready', false],
    'small page first blocker' => [static fn (): array => $page(0, 4), 'next.blocking.0', 'foreign_key_parent_unique_index'],
    'small page second blocker' => [static fn (): array => $page(0, 4), 'next.blocking.1', 'foreign_key_check'],
    'offset four starts partial blocker' => [static fn (): array => $page(4, 2), 'rows.0.parent', 'wp_partial_parent'],
    'offset four next offset' => [static fn (): array => $page(4, 2), 'next_offset', 6],
    'offset six starts violations' => [static fn (): array => $page(6, 2), 'rows.0.kind', 'foreign_key_check'],
    'tail complete' => [static fn (): array => $page(8, 9), 'complete', true],
    'tail next null' => [static fn (): array => $page(8, 9), 'next', null],
    'past tail count zero' => [static fn (): array => $page(20, 4), 'count', 0],
    'clean status ok' => [$cleanPage, 'status', 'ok'],
    'clean total admissions only' => [$cleanPage, 'total', 3],
    'clean index blockers zero' => [$cleanPage, 'current.index_blockers', 0],
    'clean violations zero' => [$cleanPage, 'current.foreign_key_violations', 0],
    'integrity sql accepted' => [static fn (): array => $page(0, 131, null, null, null, null, 'PRAGMA integrity_check'), 'current_source.integrity_sql', 'pragma integrity_check'],
    'quick check sql accepted' => [static fn (): array => $page(0, 131, null, null, null, null, 'PRAGMA quick_check'), 'current_source.integrity_sql', 'pragma quick_check'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma foreignkey index integrity current source next131 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma foreignkey index integrity current source next131 resumes stable cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 4);
    $second = $page(4, 4, ['source_id' => $first['source_id'], 'next_offset' => $first['next_offset']]);

    $t->same(4, $second['offset']);
    $t->same($first['source_id'], $second['source_id']);
    $t->same('wp_partial_parent', $second['rows'][0]['parent']);
};

$tests['pragma foreignkey index integrity current source next131 accepts source only cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 4);
    $second = $page(4, 4, ['source_id' => $first['source_id']]);

    $t->same(4, $second['offset']);
    $t->same($first['source_id'], $second['source_id']);
};

$tests['pragma foreignkey index integrity current source next131 source changes with records'] = static function (TestRunner $t) use ($page, $records, $record): void {
    $first = $page();
    $mutated = $page(0, 131, null, [...$records, $record('index', 'wp_extra', 'wp_sites', 99, 'CREATE INDEX wp_extra ON wp_sites(domain)', 99)]);

    $t->same(true, $first['source_id'] !== $mutated['source_id']);
    $t->same(true, $first['current_source']['records_hash'] !== $mutated['current_source']['records_hash']);
};

$tests['pragma foreignkey index integrity current source next131 source changes with foreign keys'] = static function (TestRunner $t) use ($page, $foreignKeys): void {
    $first = $page();
    $mutatedForeignKeys = [...$foreignKeys, ['id' => 6, 'table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']]];
    $mutated = $page(0, 131, null, null, $mutatedForeignKeys);

    $t->same(true, $first['source_id'] !== $mutated['source_id']);
    $t->same(11, $mutated['total']);
};

$tests['pragma foreignkey index integrity current source next131 source changes with table rows'] = static function (TestRunner $t) use ($page, $tables): void {
    $first = $page();
    $mutatedTables = $tables;
    $mutatedTables['wp_sites'][] = ['rowid' => 2, 'blog_id' => 404, 'domain' => 'staging.example'];
    $mutated = $page(0, 131, null, null, null, $mutatedTables);

    $t->same(true, $first['source_id'] !== $mutated['source_id']);
    $t->same(2, $mutated['current']['foreign_key_violations']);
};

$tests['pragma foreignkey index integrity current source next131 rejects stale record cursor'] = static function (TestRunner $t) use ($page, $records, $record): void {
    $first = $page(0, 4);
    $mutatedRecords = [...$records, $record('index', 'wp_extra', 'wp_sites', 99, 'CREATE INDEX wp_extra ON wp_sites(domain)', 99)];

    $t->throws(InvalidArgumentException::class, static fn () => $page(4, 4, ['source_id' => $first['source_id'], 'next_offset' => 4], $mutatedRecords));
};

$tests['pragma foreignkey index integrity current source next131 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 4);

    $t->throws(InvalidArgumentException::class, static fn () => $page(5, 4, ['source_id' => $first['source_id'], 'next_offset' => 4]));
};

$tests['pragma foreignkey index integrity current source next131 rejects empty source'] = static function (TestRunner $t) use ($records, $foreignKeys, $tables, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIndexIntegrityCurrentSourceNext::page($records, $foreignKeys, $tables, '', $nextSource));
};

$tests['pragma foreignkey index integrity current source next131 rejects unsupported source sql'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 131, null, null, null, null, 'PRAGMA table_info(wp_options)'));
};

$tests['pragma foreignkey index integrity current source next131 rejects negative offset'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(-1, 131));
};

$tests['pragma foreignkey index integrity current source next131 rejects zero limit'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn () => $page(0, 0));
};

return $tests;
