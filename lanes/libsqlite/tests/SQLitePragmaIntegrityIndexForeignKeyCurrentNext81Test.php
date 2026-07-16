<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield;
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
    $record('table', 'wp_plugin_codes', 'wp_plugin_codes', 5, 'CREATE TABLE wp_plugin_codes(code TEXT COLLATE NOCASE)', 4),
    $record('index', 'wp_plugin_codes_code', 'wp_plugin_codes', 6, 'CREATE INDEX wp_plugin_codes_code ON wp_plugin_codes(code COLLATE nocase)', 5),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER, option_name TEXT, plugin_code TEXT)', 6),
];

$foreignKeys = [
    ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']],
    ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
        ['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase'],
    ]],
    ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_plugin_codes', 'columns' => [
        ['child' => 'plugin_code', 'parent' => 'code', 'collation' => 'nocase'],
    ]],
];

$tables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'source' => 'core'],
    ],
    'wp_plugin_codes' => [
        ['rowid' => 1, 'code' => 'akismet'],
    ],
    'wp_options' => [
        ['rowid' => 10, 'option_id' => 10, 'blog_id' => 1, 'option_name' => 'SITEURL', 'plugin_code' => 'akismet'],
        ['rowid' => 11, 'option_id' => 11, 'blog_id' => 404, 'option_name' => 'missing-name', 'plugin_code' => 'missing-code'],
    ],
];

$shortDatabase = str_repeat("\0", 20);
$validHeader = 'SQLite format 3' . "\0" . str_repeat("\0", 512 - 16);
$validHeader = substr_replace($validHeader, pack('n', 512), 16, 2);
$validHeader = substr_replace($validHeader, "\x01\x01", 18, 2);
$validHeader = substr_replace($validHeader, "\x40\x20\x20", 21, 3);
$validHeader = substr_replace($validHeader, pack('N', 1), 28, 4);
$validHeader = substr_replace($validHeader, pack('N', 1), 56, 4);

$page = static fn (int $offset = 0, int $limit = 81): array => SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield::page(
    $shortDatabase,
    $records,
    $foreignKeys,
    $tables,
    $offset,
    $limit,
);
$cleanPage = static fn (): array => SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield::page(
    $validHeader,
    array_slice($records, 0, 4) + [$records[5]],
    array_slice($foreignKeys, 0, 2),
    [
        ...$tables,
        'wp_options' => [
            ['rowid' => 12, 'option_id' => 12, 'blog_id' => 1, 'option_name' => 'siteurl'],
        ],
    ],
);
$collect = static fn (): array => SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield::collect(
    $shortDatabase,
    $records,
    $foreignKeys,
    $tables,
);

$valueAt = static function (mixed $value, string $path): mixed {
foreach (explode('.', $path) as $part) {
        if ($part === 'count' && is_array($value) && !array_key_exists('count', $value)) {
            $value = count($value);
            continue;
        }
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$cases = [
    'blocked status' => [$page, 'status', 'blocked'],
    'default offset' => [$page, 'offset', 0],
    'default limit current next81' => [$page, 'limit', 81],
    'total rows' => [$page, 'total', 7],
    'page count' => [$page, 'count', 7],
    'complete true' => [$page, 'complete', true],
    'next offset null' => [$page, 'next_offset', null],
    'current index admissions' => [$page, 'current.index_admissions', 3],
    'current index blockers' => [$page, 'current.index_blockers', 1],
    'current fk violations' => [$page, 'current.foreign_key_violations', 3],
    'current integrity errors' => [$page, 'current.integrity_errors', 1],
    'next ready false' => [$page, 'next.ready', false],
    'next blocking count' => [$page, 'next.blocking.count', 3],
    'next first blocker index' => [$page, 'next.blocking.0', 'foreign_key_parent_unique_index'],
    'next second blocker fk' => [$page, 'next.blocking.1', 'foreign_key_check'],
    'next third blocker integrity' => [$page, 'next.blocking.2', 'integrity_check'],
    'row0 kind admission' => [$page, 'rows.0.kind', 'index_admission'],
    'row0 source index' => [$page, 'rows.0.source', 'index'],
    'row0 table options' => [$page, 'rows.0.table', 'wp_options'],
    'row0 parent sites' => [$page, 'rows.0.parent', 'wp_sites'],
    'row0 fkid zero' => [$page, 'rows.0.fkid', 0],
    'row0 rowid null' => [$page, 'rows.0.rowid', null],
    'row0 rowid pk index' => [$page, 'rows.0.index', 'rowid-primary-key'],
    'row0 column blog id' => [$page, 'rows.0.columns.0', 'blog_id'],
    'row0 collation binary' => [$page, 'rows.0.collations.0', 'BINARY'],
    'row0 status ok' => [$page, 'rows.0.status', 'ok'],
    'row0 no page' => [$page, 'rows.0.page', null],
    'row1 unique index' => [$page, 'rows.1.index', 'wp_option_names_name_u'],
    'row1 parent names' => [$page, 'rows.1.parent', 'wp_option_names'],
    'row1 collation nocase' => [$page, 'rows.1.collations.0', 'NOCASE'],
    'row1 status ok' => [$page, 'rows.1.status', 'ok'],
    'row2 blocked index null' => [$page, 'rows.2.index', null],
    'row2 blocked parent' => [$page, 'rows.2.parent', 'wp_plugin_codes'],
    'row2 blocked status' => [$page, 'rows.2.status', 'blocked'],
    'row2 blocked message' => [$page, 'rows.2.message', 'foreign key wp_options->wp_plugin_codes parent key has no matching UNIQUE index'],
    'row3 fk kind' => [$page, 'rows.3.kind', 'foreign_key_check'],
    'row3 fk source' => [$page, 'rows.3.source', 'foreign_key'],
    'row3 fk rowid' => [$page, 'rows.3.rowid', 11],
    'row3 fk parent sites' => [$page, 'rows.3.parent', 'wp_sites'],
    'row3 fk status violation' => [$page, 'rows.3.status', 'violation'],
    'row4 fk parent names' => [$page, 'rows.4.parent', 'wp_option_names'],
    'row4 fk fkid' => [$page, 'rows.4.fkid', 1],
    'row5 fk parent plugin' => [$page, 'rows.5.parent', 'wp_plugin_codes'],
    'row5 fk rowid' => [$page, 'rows.5.rowid', 11],
    'row6 integrity kind' => [$page, 'rows.6.kind', 'integrity_check'],
    'row6 integrity source header' => [$page, 'rows.6.source', 'header'],
    'row6 integrity table null' => [$page, 'rows.6.table', null],
    'row6 integrity status error' => [$page, 'rows.6.status', 'error'],
    'row6 integrity message' => [$page, 'rows.6.message', 'SQLite database header requires at least 100 bytes'],
    'row6 integrity index null' => [$page, 'rows.6.index', null],
    'row6 integrity columns empty' => [$page, 'rows.6.columns.count', 0],
    'offset three starts fk' => [static fn (): array => $page(3, 2), 'rows.0.kind', 'foreign_key_check'],
    'offset three count' => [static fn (): array => $page(3, 2), 'count', 2],
    'offset three next offset' => [static fn (): array => $page(3, 2), 'next_offset', 5],
    'offset six complete' => [static fn (): array => $page(6, 2), 'complete', true],
    'offset six starts integrity' => [static fn (): array => $page(6, 2), 'rows.0.source', 'header'],
    'collect count' => [$collect, 'count', 7],
    'collect first source' => [$collect, '0.source', 'index'],
    'collect last source' => [$collect, '6.source', 'header'],
    'clean status still ok' => [$cleanPage, 'status', 'ok'],
    'clean next ready' => [$cleanPage, 'next.ready', true],
    'clean blockers empty' => [$cleanPage, 'next.blocking.count', 0],
    'clean total two admissions' => [$cleanPage, 'total', 2],
    'clean integrity errors zero' => [$cleanPage, 'current.integrity_errors', 0],
    'clean fk violations zero' => [$cleanPage, 'current.foreign_key_violations', 0],
    'clean index blockers zero' => [$cleanPage, 'current.index_blockers', 0],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity index fk current next81 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity index fk current next81 rejects negative offset'] = static function (TestRunner $t) use ($shortDatabase, $records, $foreignKeys, $tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield::page($shortDatabase, $records, $foreignKeys, $tables, -1, 81));
};

$tests['pragma integrity index fk current next81 rejects zero limit'] = static function (TestRunner $t) use ($shortDatabase, $records, $foreignKeys, $tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield::page($shortDatabase, $records, $foreignKeys, $tables, 0, 0));
};

return $tests;
