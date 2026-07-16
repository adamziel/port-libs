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

$catalogFactory = static function (bool $archiveUnique = true) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_name TEXT)', 1),
            $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER, meta_key TEXT)', 2),
            $record('table', 'wp_plugins', 'wp_plugins', 4, 'CREATE TABLE wp_plugins(code TEXT COLLATE NOCASE)', 3),
        ],
        [
            $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
            $record('index', 'wp_option_names_name_u', 'wp_option_names', 6, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE NOCASE)', 2),
            $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
        ],
    );
    $catalog->attach('archive', '/tmp/wp-archive.sqlite', [
        $record('table', 'wp_option_names', 'wp_option_names', 8, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
        $record('index', $archiveUnique ? 'wp_archive_option_names_name_u' : 'wp_archive_option_names_name_idx', 'wp_option_names', 9, $archiveUnique ? 'CREATE UNIQUE INDEX wp_archive_option_names_name_u ON wp_option_names(name COLLATE NOCASE)' : 'CREATE INDEX wp_archive_option_names_name_idx ON wp_option_names(name COLLATE NOCASE)', 2),
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
    ]);

    return $catalog;
};

$schemasFactory = static function (int $extraArchiveRows = 0): array {
    $archiveOptions = [
        ['rowid' => 'archive-1', 'option_id' => 1, 'option_name' => 'legacy_siteurl'],
        ['rowid' => 'archive-2', 'option_id' => 2, 'option_name' => 'missing_archive'],
    ];
    for ($i = 1; $i <= $extraArchiveRows; $i++) {
        $archiveOptions[] = ['rowid' => 'archive-extra-' . $i, 'option_id' => 20 + $i, 'option_name' => 'missing_extra_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_posts' => [['rowid' => 1, 'ID' => 1, 'post_name' => 'hello-world']],
                'wp_postmeta' => [
                    ['rowid' => 11, 'meta_id' => 11, 'post_id' => 1, 'meta_key' => 'akismet'],
                    ['rowid' => 12, 'meta_id' => 12, 'post_id' => 404, 'meta_key' => '_missing'],
                    ['rowid' => 13, 'meta_id' => 13, 'post_id' => 1, 'meta_key' => 'missing_plugin'],
                ],
                'wp_plugins' => [['rowid' => 20, 'code' => 'akismet']],
            ],
            'foreignKeys' => [
                ['id' => 1, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
                ['id' => 2, 'table' => 'wp_postmeta', 'parent' => 'wp_plugins', 'columns' => [['child' => 'meta_key', 'parent' => 'code', 'collation' => 'nocase']]],
            ],
        ],
        'temp' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => [
                    ['rowid' => 'temp-1', 'option_id' => 1, 'option_name' => 'SITEURL'],
                    ['rowid' => 'temp-2', 'option_id' => 2, 'option_name' => 'missing_temp'],
                ],
            ],
            'foreignKeys' => [
                ['id' => 3, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
            ],
        ],
        'archive' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
                'wp_options' => $archiveOptions,
            ],
            'foreignKeys' => [
                ['id' => 4, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
            ],
        ],
    ];
};

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);
$shortDatabase = str_repeat("\0", 20);
$mutatedDatabase = $database;
$mutatedDatabase[40] = "\x01";

$catalog = $catalogFactory();
$schemas = $schemasFactory();

$page = static fn (int $offset = 0, int $limit = 99, ?array $cursor = null, string $integritySql = 'PRAGMA quick_check'): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor(
    $database,
    $schemas,
    $catalog,
    $offset,
    $limit,
    $integritySql,
    $cursor,
);
$shortPage = static fn (): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($shortDatabase, $schemas, $catalog, 0, 99);
$archiveExtraPage = static fn (): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemasFactory(2), $catalog, 0, 99, 'PRAGMA quick_check');
$catalogChangedPage = static fn (): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemas, $catalogFactory(false), 0, 99, 'PRAGMA quick_check');
$mutatedDatabasePage = static fn (): array => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($mutatedDatabase, $schemas, $catalog, 0, 99, 'PRAGMA quick_check');

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
    'blocked status' => [$page, 'status', 'blocked'],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'database source length' => [static fn (): array => ['len' => strlen($page()['current_source']['database'])], 'len', 64],
    'catalog source length' => [static fn (): array => ['len' => strlen($page()['current_source']['catalog'])], 'len', 64],
    'schemas source length' => [static fn (): array => ['len' => strlen($page()['current_source']['schemas'])], 'len', 64],
    'integrity source normalized' => [$page, 'current_source.integrity_sql', 'pragma quick_check'],
    'limit default next99' => [$page, 'limit', 99],
    'total combined rows' => [$page, 'total', 9],
    'count combined rows' => [$page, 'count', 9],
    'complete combined rows' => [$page, 'complete', true],
    'next cursor null complete' => [$page, 'next', null],
    'index admissions count' => [$page, 'current.index_admissions', 4],
    'index blockers count' => [$page, 'current.index_blockers', 1],
    'fk violations count' => [$page, 'current.foreign_key_violations', 5],
    'integrity errors zero' => [$page, 'current.integrity_errors', 0],
    'schema main first' => [$page, 'current.schemas.0', 'main'],
    'schema temp second' => [$page, 'current.schemas.1', 'temp'],
    'schema archive third' => [$page, 'current.schemas.2', 'archive'],
    'next state ready false' => [$page, 'next_state.ready', false],
    'next state blocker index' => [$page, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'next state blocker fk' => [$page, 'next_state.blocking.1', 'foreign_key_check'],
    'row0 schema main' => [$page, 'rows.0.schema', 'main'],
    'row0 rowid parent index' => [$page, 'rows.0.index', 'rowid-primary-key'],
    'row0 source index' => [$page, 'rows.0.source', 'index'],
    'row0 target source catalog' => [$page, 'rows.0.target_source', 'catalog-current'],
    'row0 status ok' => [$page, 'rows.0.status', 'ok'],
    'row1 blocked plugin index' => [$page, 'rows.1.status', 'blocked'],
    'row1 blocked plugin message' => [$page, 'rows.1.message', 'main.foreign key wp_postmeta->wp_plugins parent key has no matching UNIQUE index'],
    'row2 fk rowid post missing' => [$page, 'rows.2.rowid', 12],
    'row3 fk plugin missing key' => [$page, 'rows.3.rowid', 12],
    'row4 fk plugin second missing' => [$page, 'rows.4.rowid', 13],
    'row5 temp admission index' => [$page, 'rows.5.index', 'wp_option_names_name_u'],
    'row5 temp collation nocase' => [$page, 'rows.5.collations.0', 'NOCASE'],
    'row6 temp fk rowid' => [$page, 'rows.6.rowid', 'temp-2'],
    'row7 archive admission' => [$page, 'rows.7.index', 'wp_archive_option_names_name_u'],
    'row8 archive fk rowid' => [$page, 'rows.8.rowid', 'archive-2'],
    'short status blocked' => [$shortPage, 'status', 'blocked'],
    'short total includes integrity row' => [$shortPage, 'total', 10],
    'short integrity errors one' => [$shortPage, 'current.integrity_errors', 1],
    'short blocker integrity' => [$shortPage, 'next_state.blocking.2', 'integrity_check'],
    'short final target source' => [$shortPage, 'rows.9.target_source', 'integrity-check'],
    'short final message' => [$shortPage, 'rows.9.message', 'SQLite database header requires at least 100 bytes'],
    'schema mutation changes source' => [static fn (): array => ['changed' => $page()['source_id'] !== $archiveExtraPage()['source_id']], 'changed', true],
    'schema mutation preserves database source' => [static fn (): array => ['same' => $page()['current_source']['database'] === $archiveExtraPage()['current_source']['database']], 'same', true],
    'schema mutation changes schema hash' => [static fn (): array => ['changed' => $page()['current_source']['schemas'] !== $archiveExtraPage()['current_source']['schemas']], 'changed', true],
    'schema mutation adds rows' => [$archiveExtraPage, 'total', 11],
    'catalog mutation changes source' => [static fn (): array => ['changed' => $page()['source_id'] !== $catalogChangedPage()['source_id']], 'changed', true],
    'catalog mutation changes catalog hash' => [static fn (): array => ['changed' => $page()['current_source']['catalog'] !== $catalogChangedPage()['current_source']['catalog']], 'changed', true],
    'catalog mutation adds index blocker' => [$catalogChangedPage, 'current.index_blockers', 2],
    'database mutation changes source' => [static fn (): array => ['changed' => $page()['source_id'] !== $mutatedDatabasePage()['source_id']], 'changed', true],
    'database mutation changes database hash' => [static fn (): array => ['changed' => $page()['current_source']['database'] !== $mutatedDatabasePage()['current_source']['database']], 'changed', true],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity fk index current source next99 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity fk index current source next99 resumes mixed stream with source cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 4);
    $second = $page(4, 4, ['source_id' => $first['source_id'], 'next_offset' => $first['next_offset']]);
    $third = $page(8, 4, ['source_id' => $second['source_id'], 'next_offset' => $second['next_offset']]);

    $t->same(4, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $first['next']);
    $t->same('foreign_key', $second['rows'][0]['source']);
    $t->same(13, $second['rows'][0]['rowid']);
    $t->same('temp', $second['rows'][1]['schema']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 8], $second['next']);
    $t->same(1, $third['count']);
    $t->same('archive-2', $third['rows'][0]['rowid']);
    $t->same(null, $third['next']);
};

$tests['pragma integrity fk index current source next99 accepts cursor offset key'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 5);
    $second = $page(5, 5, ['source_id' => $first['source_id'], 'offset' => 5]);

    $t->same(5, $second['offset']);
    $t->same('temp', $second['rows'][0]['schema']);
    $t->same('archive-2', $second['rows'][3]['rowid']);
};

$tests['pragma integrity fk index current source next99 rejects stale database cursor'] = static function (TestRunner $t) use ($page, $mutatedDatabase, $schemas, $catalog): void {
    $first = $page(0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($mutatedDatabase, $schemas, $catalog, 4, 4, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 4]));
};

$tests['pragma integrity fk index current source next99 rejects stale schema cursor'] = static function (TestRunner $t) use ($page, $database, $schemasFactory, $catalog): void {
    $first = $page(0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemasFactory(2), $catalog, 4, 4, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 4]));
};

$tests['pragma integrity fk index current source next99 rejects stale catalog cursor'] = static function (TestRunner $t) use ($page, $database, $schemas, $catalogFactory): void {
    $first = $page(0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemas, $catalogFactory(false), 4, 4, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 4]));
};

$tests['pragma integrity fk index current source next99 rejects stale integrity sql cursor'] = static function (TestRunner $t) use ($page, $database, $schemas, $catalog): void {
    $first = $page(0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemas, $catalog, 4, 4, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => 4]));
};

$tests['pragma integrity fk index current source next99 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $page(5, 4, ['source_id' => $first['source_id'], 'next_offset' => 4]));
};

$tests['pragma integrity fk index current source next99 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemas, $catalog, -1, 99));
};

$tests['pragma integrity fk index current source next99 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemas, $catalog, 0, 0));
};

return $tests;
