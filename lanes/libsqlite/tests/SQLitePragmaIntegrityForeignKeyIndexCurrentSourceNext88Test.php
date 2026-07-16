<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield;
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
    $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT)', 1),
    $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER, meta_key TEXT)', 2),
    $record('table', 'wp_plugins', 'wp_plugins', 4, 'CREATE TABLE wp_plugins(code TEXT COLLATE NOCASE)', 3),
];
$tempRecords = [
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, site_id INTEGER)', 1),
    $record('index', 'wp_option_names_name_u', 'wp_option_names', 6, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE NOCASE)', 2),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 3),
];
$archiveRecords = [
    $record('table', 'wp_option_names', 'wp_option_names', 8, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
    $record('index', 'wp_archive_option_names_name_u', 'wp_option_names', 9, 'CREATE UNIQUE INDEX wp_archive_option_names_name_u ON wp_option_names(name COLLATE NOCASE)', 2),
    $record('table', 'wp_options', 'wp_options', 10, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
];

$catalog = new SQLiteAttachedSchemaCatalog($mainRecords, $tempRecords);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', $archiveRecords);

$schemas = [
    'main' => [
        'tables' => [
            'wp_posts' => [
                ['rowid' => 1, 'ID' => 1, 'post_name' => 'hello-world'],
            ],
            'wp_postmeta' => [
                ['rowid' => 11, 'meta_id' => 11, 'post_id' => 1, 'meta_key' => '_thumbnail_id'],
                ['rowid' => 12, 'meta_id' => 12, 'post_id' => 404, 'meta_key' => '_missing'],
            ],
            'wp_plugins' => [
                ['rowid' => 20, 'code' => 'akismet'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
            ['id' => 2, 'table' => 'wp_postmeta', 'parent' => 'wp_plugins', 'columns' => [['child' => 'meta_key', 'parent' => 'code', 'collation' => 'nocase']]],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl', 'site_id' => 1],
            ],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_id' => 1, 'option_name' => 'SITEURL', 'autoload' => 'yes'],
                ['rowid' => 'temp-2', 'option_id' => 2, 'option_name' => 'missing_temp', 'autoload' => 'yes'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 3, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
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
            ],
        ],
        'foreignKeys' => [
            ['id' => 4, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
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
$shortDatabase = str_repeat("\0", 20);

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

$page = static fn (int $offset = 0, int $limit = 88): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::page(
    $database,
    $schemas,
    $catalog,
    $offset,
    $limit,
    'PRAGMA quick_check',
);
$shortPage = static fn (): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::page(
    $shortDatabase,
    $schemas,
    $catalog,
    0,
    88,
);
$collect = static fn (): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::collect($database, $schemas, $catalog, 'PRAGMA quick_check');

$cases = [
    'blocked status' => [$page, 'status', 'blocked'],
    'default offset' => [$page, 'offset', 0],
    'default limit next88' => [$page, 'limit', 88],
    'total combined rows' => [$page, 'total', 9],
    'page count' => [$page, 'count', 9],
    'complete true' => [$page, 'complete', true],
    'next offset null' => [$page, 'next_offset', null],
    'current index admissions' => [$page, 'current.index_admissions', 4],
    'current index blockers' => [$page, 'current.index_blockers', 1],
    'current foreign key violations' => [$page, 'current.foreign_key_violations', 5],
    'current integrity errors zero' => [$page, 'current.integrity_errors', 0],
    'current schema count' => [$page, 'current.schemas.count', 3],
    'current first schema main' => [$page, 'current.schemas.0', 'main'],
    'current second schema temp' => [$page, 'current.schemas.1', 'temp'],
    'current third schema archive' => [$page, 'current.schemas.2', 'archive'],
    'next ready false' => [$page, 'next.ready', false],
    'next blocker count' => [$page, 'next.blocking.count', 2],
    'next blocker index' => [$page, 'next.blocking.0', 'foreign_key_parent_unique_index'],
    'next blocker fk' => [$page, 'next.blocking.1', 'foreign_key_check'],
    'row0 kind index' => [$page, 'rows.0.kind', 'index_admission'],
    'row0 source index' => [$page, 'rows.0.source', 'index'],
    'row0 schema main' => [$page, 'rows.0.schema', 'main'],
    'row0 target source' => [$page, 'rows.0.target_source', 'catalog-current'],
    'row0 table postmeta' => [$page, 'rows.0.table', 'wp_postmeta'],
    'row0 parent posts' => [$page, 'rows.0.parent', 'wp_posts'],
    'row0 fkid' => [$page, 'rows.0.fkid', 1],
    'row0 rowid primary key' => [$page, 'rows.0.index', 'rowid-primary-key'],
    'row0 status ok' => [$page, 'rows.0.status', 'ok'],
    'row0 schema message' => [$page, 'rows.0.message', 'main.foreign key wp_postmeta->wp_posts parent key covered by rowid-primary-key'],
    'row1 blocked schema main' => [$page, 'rows.1.schema', 'main'],
    'row1 blocked status' => [$page, 'rows.1.status', 'blocked'],
    'row1 blocked parent' => [$page, 'rows.1.parent', 'wp_plugins'],
    'row1 blocked index null' => [$page, 'rows.1.index', null],
    'row1 blocked message' => [$page, 'rows.1.message', 'main.foreign key wp_postmeta->wp_plugins parent key has no matching UNIQUE index'],
    'row2 fk schema main' => [$page, 'rows.2.schema', 'main'],
    'row2 fk source' => [$page, 'rows.2.source', 'foreign_key'],
    'row2 fk rowid' => [$page, 'rows.2.rowid', 12],
    'row2 fk parent posts' => [$page, 'rows.2.parent', 'wp_posts'],
    'row3 fk parent plugins' => [$page, 'rows.3.parent', 'wp_plugins'],
    'row3 fk status violation' => [$page, 'rows.3.status', 'violation'],
    'row4 main plugin second violation' => [$page, 'rows.4.parent', 'wp_plugins'],
    'row5 temp admission schema' => [$page, 'rows.5.schema', 'temp'],
    'row5 temp admission index' => [$page, 'rows.5.index', 'wp_option_names_name_u'],
    'row5 temp admission collation' => [$page, 'rows.5.collations.0', 'NOCASE'],
    'row6 temp fk rowid' => [$page, 'rows.6.rowid', 'temp-2'],
    'row6 temp fk message' => [$page, 'rows.6.message', 'temp.foreign key mismatch in wp_options rowid temp-2 references wp_option_names fkid 3'],
    'row7 archive admission schema' => [$page, 'rows.7.schema', 'archive'],
    'row7 archive admission index' => [$page, 'rows.7.index', 'wp_archive_option_names_name_u'],
    'row8 archive fk rowid' => [$page, 'rows.8.rowid', 'archive-2'],
    'row8 archive fk parent' => [$page, 'rows.8.parent', 'wp_option_names'],
    'offset four count' => [static fn (): array => $page(4, 3), 'count', 3],
    'offset four first schema main tail' => [static fn (): array => $page(4, 3), 'rows.0.schema', 'main'],
    'offset four next offset' => [static fn (): array => $page(4, 3), 'next_offset', 7],
    'offset seven first archive admission' => [static fn (): array => $page(7, 3), 'rows.0.schema', 'archive'],
    'offset seven complete' => [static fn (): array => $page(7, 3), 'complete', true],
    'collect count' => [$collect, 'count', 9],
    'collect first schema' => [$collect, '0.schema', 'main'],
    'collect archive source' => [$collect, '6.target_source', 'catalog-current'],
    'short blocked status' => [$shortPage, 'status', 'blocked'],
    'short integrity errors' => [$shortPage, 'current.integrity_errors', 1],
    'short blocker count' => [$shortPage, 'next.blocking.count', 3],
    'short integrity row kind' => [$shortPage, 'rows.9.kind', 'integrity_check'],
    'short integrity target source' => [$shortPage, 'rows.9.target_source', 'integrity-check'],
    'short integrity message' => [$shortPage, 'rows.9.message', 'SQLite database header requires at least 100 bytes'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity foreignkey index current source next88 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity foreignkey index current source next88 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::page($database, $schemas, $catalog, -1, 88));
};

$tests['pragma integrity foreignkey index current source next88 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::page($database, $schemas, $catalog, 0, 0));
};

$tests['pragma integrity foreignkey index current source next88 rejects unattached schema'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $schemas['ghost'] = ['tables' => [], 'foreignKeys' => []];
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::page($database, $schemas, $catalog));
};

return $tests;
