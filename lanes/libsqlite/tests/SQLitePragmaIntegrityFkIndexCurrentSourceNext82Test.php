<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrity;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCurrentNextYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$mainRecords = [
    $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY)', 1),
    $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER)', 2),
    $record('index', 'wp_postmeta_post_id', 'wp_postmeta', 4, 'CREATE INDEX wp_postmeta_post_id ON wp_postmeta(post_id)', 3),
];
$tempRecords = [
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 7, null, 3),
];
$archiveRecords = [
    $record('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 9, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 10, null, 3),
];

$catalog = new SQLiteAttachedSchemaCatalog($mainRecords, $tempRecords);
$catalog->attach('archive', '/tmp/archive.sqlite', $archiveRecords);

$schemas = [
    'main' => [
        'tables' => [
            'wp_posts' => [
                ['rowid' => 1, 'ID' => 1],
            ],
            'wp_postmeta' => [
                ['rowid' => 10, 'meta_id' => 10, 'post_id' => 1],
                ['rowid' => 11, 'meta_id' => 11, 'post_id' => 99],
                ['rowid' => 12, 'meta_id' => 12, 'post_id' => 100],
            ],
        ],
        'foreignKeys' => [
            ['id' => 4, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl'],
                ['rowid' => 2, 'name' => 'home'],
            ],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'temp-2', 'option_id' => 2, 'option_name' => 'missing_temp'],
                ['rowid' => 'temp-3', 'option_id' => 3, 'option_name' => 'missing_temp_2'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 7, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'legacy_siteurl'],
            ],
            'wp_options' => [
                ['rowid' => 'archive-1', 'option_id' => 1, 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-2', 'option_id' => 2, 'option_name' => 'missing_archive'],
                ['rowid' => 'archive-3', 'option_id' => 3, 'option_name' => 'missing_archive_2'],
                ['rowid' => 'archive-4', 'option_id' => 4, 'option_name' => null],
            ],
        ],
        'foreignKeys' => [
            ['id' => 9, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
];

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

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

$tempImplicit = static fn (): array => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check(wp_options)', $schemas, $catalog);
$archiveQualified = static fn (): array => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check(archive.wp_options)', $schemas, $catalog);
$archiveQuoted = static fn (): array => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check("archive"."wp_options")', $schemas, $catalog);
$mainQualified = static fn (): array => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check(main.wp_postmeta)', $schemas, $catalog);
$tempExplicit = static fn (): array => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA temp.foreign_key_check(wp_options)', $schemas, $catalog);
$archiveExplicit = static fn (): array => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA archive.foreign_key_check(wp_options)', $schemas, $catalog);
$archivePage = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemas, 'PRAGMA foreign_key_check(archive.wp_options)', 0, 82, 'PRAGMA quick_check', $catalog);
$tempPage = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemas, 'PRAGMA foreign_key_check(wp_options)', 0, 82, 'PRAGMA quick_check', $catalog);
$mainTail = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::pageForForeignKeyPragma($database, $schemas, 'PRAGMA foreign_key_check(main.wp_postmeta)', 1, 82, 'PRAGMA quick_check', $catalog);

$cases = [
    'implicit status' => [$tempImplicit, 'status', 'ok'],
    'implicit schema temp from current catalog' => [$tempImplicit, 'schema', 'temp'],
    'implicit target schema temp' => [$tempImplicit, 'target_schema', 'temp'],
    'implicit target source catalog' => [$tempImplicit, 'target_source', 'catalog-current'],
    'implicit target name' => [$tempImplicit, 'target', 'wp_options'],
    'implicit row count' => [$tempImplicit, 'rows.count', 2],
    'implicit first schema' => [$tempImplicit, 'rows.0.schema', 'temp'],
    'implicit first table' => [$tempImplicit, 'rows.0.table', 'wp_options'],
    'implicit first rowid' => [$tempImplicit, 'rows.0.rowid', 'temp-2'],
    'implicit first parent' => [$tempImplicit, 'rows.0.parent', 'wp_option_names'],
    'implicit first fkid' => [$tempImplicit, 'rows.0.fkid', 7],
    'implicit second rowid' => [$tempImplicit, 'rows.1.rowid', 'temp-3'],
    'archive status' => [$archiveQualified, 'status', 'ok'],
    'archive schema' => [$archiveQualified, 'schema', 'archive'],
    'archive target schema' => [$archiveQualified, 'target_schema', 'archive'],
    'archive target source qualified' => [$archiveQualified, 'target_source', 'qualified-target'],
    'archive target name' => [$archiveQualified, 'target', 'wp_options'],
    'archive row count skips null child' => [$archiveQualified, 'rows.count', 2],
    'archive first schema' => [$archiveQualified, 'rows.0.schema', 'archive'],
    'archive first rowid' => [$archiveQualified, 'rows.0.rowid', 'archive-2'],
    'archive second rowid' => [$archiveQualified, 'rows.1.rowid', 'archive-3'],
    'archive parent' => [$archiveQualified, 'rows.0.parent', 'wp_option_names'],
    'archive fkid' => [$archiveQualified, 'rows.0.fkid', 9],
    'archive quoted schema' => [$archiveQuoted, 'schema', 'archive'],
    'archive quoted target' => [$archiveQuoted, 'target', 'wp_options'],
    'archive quoted source' => [$archiveQuoted, 'target_source', 'qualified-target'],
    'main schema' => [$mainQualified, 'schema', 'main'],
    'main source qualified' => [$mainQualified, 'target_source', 'qualified-target'],
    'main row count' => [$mainQualified, 'rows.count', 2],
    'main first rowid' => [$mainQualified, 'rows.0.rowid', 11],
    'main second rowid' => [$mainQualified, 'rows.1.rowid', 12],
    'main fkid' => [$mainQualified, 'rows.0.fkid', 4],
    'temp explicit schema' => [$tempExplicit, 'schema', 'temp'],
    'temp explicit target source' => [$tempExplicit, 'target_source', 'pragma-schema'],
    'temp explicit rowid' => [$tempExplicit, 'rows.0.rowid', 'temp-2'],
    'archive explicit schema' => [$archiveExplicit, 'schema', 'archive'],
    'archive explicit target source' => [$archiveExplicit, 'target_source', 'pragma-schema'],
    'archive explicit rowid' => [$archiveExplicit, 'rows.0.rowid', 'archive-2'],
    'archive page status' => [$archivePage, 'status', 'ok'],
    'archive page limit next82' => [$archivePage, 'limit', 82],
    'archive page count' => [$archivePage, 'count', 2],
    'archive page total' => [$archivePage, 'total', 2],
    'archive page complete' => [$archivePage, 'complete', true],
    'archive page next offset null' => [$archivePage, 'next_offset', null],
    'archive page fk count' => [$archivePage, 'current.foreign_key', 2],
    'archive page first message' => [$archivePage, 'rows.0.message', 'foreign key mismatch in archive.wp_options rowid archive-2 references wp_option_names fkid 9'],
    'archive page second message' => [$archivePage, 'rows.1.message', 'foreign key mismatch in archive.wp_options rowid archive-3 references wp_option_names fkid 9'],
    'temp page current catalog schema' => [$tempPage, 'rows.0.schema', 'temp'],
    'temp page first rowid' => [$tempPage, 'rows.0.rowid', 'temp-2'],
    'temp page second rowid' => [$tempPage, 'rows.1.rowid', 'temp-3'],
    'main tail offset' => [$mainTail, 'offset', 1],
    'main tail count' => [$mainTail, 'count', 1],
    'main tail rowid' => [$mainTail, 'rows.0.rowid', 12],
    'main tail complete' => [$mainTail, 'complete', true],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity fk index current source next82 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity fk index current source next82 rejects conflicting schemas'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA main.foreign_key_check(archive.wp_options)', $schemas, $catalog));
};

$tests['pragma integrity fk index current source next82 rejects three part target'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check(a.b.c)', $schemas, $catalog));
};

$tests['pragma integrity fk index current source next82 rejects malformed target token'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check(wp-options)', $schemas, $catalog));
};

$tests['pragma integrity fk index current source next82 rejects unterminated quote'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check("archive.wp_options)', $schemas, $catalog));
};

$tests['pragma integrity fk index current source next82 missing catalog falls back to main'] = static function (TestRunner $t) use ($schemas): void {
    $result = SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check(wp_options)', $schemas);
    $t->same(['schema' => 'main', 'rows' => 0, 'source' => 'default'], ['schema' => $result['schema'], 'rows' => count($result['rows']), 'source' => $result['target_source']]);
};

return $tests;
