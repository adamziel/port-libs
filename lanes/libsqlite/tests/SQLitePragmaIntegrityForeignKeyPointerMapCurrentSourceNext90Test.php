<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegritySourceCursor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$pageSize = 512;
$pageCount = 88;

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$database = static function (bool $validPointerParents = false) use ($pageSize, $pageCount, $putPointerMapEntry): string {
    $header = str_repeat("\0", $pageSize);
    $header = substr_replace($header, "SQLite format 3\0", 0, 16);
    $header = substr_replace($header, pack('n', $pageSize), 16, 2);
    $header[18] = "\x01";
    $header[19] = "\x01";
    $header = substr_replace($header, pack('N', $pageCount), 28, 4);
    $header = substr_replace($header, pack('N', 3), 52, 4);
    $header = substr_replace($header, pack('N', 1), 56, 4);

    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
        $pointerMap = $putPointerMapEntry(
            $pointerMap,
            $pageNumber,
            SQLitePointerMapEntry::BTREE_PAGE,
            $validPointerParents ? 3 : 0,
        );
    }

    $pages = [$header, $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[] = str_repeat("\0", $pageSize);
    }

    return implode('', $pages);
};

$schemas = static function (int $archiveMissing = 5): array {
    $archiveRows = [
        ['rowid' => 'archive-siteurl', 'option_name' => 'legacy_siteurl'],
    ];
    for ($i = 1; $i <= $archiveMissing; $i++) {
        $archiveRows[] = ['rowid' => 'archive-missing-' . $i, 'option_name' => 'missing_' . $i];
    }
    $archiveRows[] = ['rowid' => 'archive-null', 'option_name' => null];

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => [
                    ['rowid' => 1, 'option_name' => 'siteurl'],
                    ['rowid' => 2, 'option_name' => 'main_missing'],
                ],
            ],
            'foreignKeys' => [
                ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
        'archive' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
                'wp_options' => $archiveRows,
            ],
            'foreignKeys' => [
                ['id' => 9, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$record = static fn (string $name, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name,
    $root,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('wp_options', 3),
    $record('wp_option_names', 4),
]);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', [
    $record('wp_options', 5),
    $record('wp_option_names', 6),
]);

$bytes = $database();
$schemaRows = $schemas();
$tableValuedSql = "SELECT * FROM pragma_foreign_key_check('archive.wp_options')";

$page0 = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
    $bytes,
    $schemaRows,
    $tableValuedSql,
    0,
    90,
    'PRAGMA integrity_check',
    null,
    $catalog,
);
$page1 = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
    $bytes,
    $schemaRows,
    $tableValuedSql,
    90,
    90,
    'PRAGMA integrity_check',
    ['source_id' => $page0()['source_id'], 'next_offset' => $page0()['next_offset']],
    $catalog,
);
$quick = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
    $bytes,
    $schemaRows,
    $tableValuedSql,
    0,
    90,
    'PRAGMA quick_check',
    null,
    $catalog,
);
$cleanPointer = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
    $database(true),
    $schemaRows,
    $tableValuedSql,
    0,
    90,
    'PRAGMA integrity_check',
    null,
    $catalog,
);

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
    'page0 status' => [$page0, 'status', 'ok'],
    'page0 offset' => [$page0, 'offset', 0],
    'page0 limit next90' => [$page0, 'limit', 90],
    'page0 count' => [$page0, 'count', 90],
    'page0 total' => [$page0, 'total', 90],
    'page0 complete because total equals limit' => [$page0, 'complete', true],
    'page0 next offset null' => [$page0, 'next_offset', null],
    'page0 cursor next null' => [$page0, 'next', null],
    'page0 pointer count' => [$page0, 'current.pointer_map', 85],
    'page0 foreign key count' => [$page0, 'current.foreign_key', 5],
    'page0 first source pointer' => [$page0, 'rows.0.source', 'pointer_map'],
    'page0 first pointer page' => [$page0, 'rows.0.page', 4],
    'page0 first pointer map page' => [$page0, 'rows.0.pointer_map_page', 2],
    'page0 midpoint pointer page' => [$page0, 'rows.40.page', 44],
    'page0 final pointer source' => [$page0, 'rows.84.source', 'pointer_map'],
    'page0 final pointer page' => [$page0, 'rows.84.page', 88],
    'page0 first fk source' => [$page0, 'rows.85.source', 'foreign_key'],
    'page0 first fk schema' => [$page0, 'rows.85.schema', 'archive'],
    'page0 first fk table' => [$page0, 'rows.85.table', 'wp_options'],
    'page0 first fk rowid' => [$page0, 'rows.85.rowid', 'archive-missing-1'],
    'page0 first fk parent' => [$page0, 'rows.85.parent', 'wp_option_names'],
    'page0 first fk fkid' => [$page0, 'rows.85.fkid', 9],
    'page0 final fk rowid' => [$page0, 'rows.89.rowid', 'archive-missing-5'],
    'page0 source database sha length' => [static fn (): array => ['len' => strlen($page0()['current_source']['database'])], 'len', 64],
    'page0 source schema sha length' => [static fn (): array => ['len' => strlen($page0()['current_source']['schema_hash'])], 'len', 64],
    'page0 source id length' => [static fn (): array => ['len' => strlen($page0()['source_id'])], 'len', 64],
    'page0 source integrity normalized' => [$page0, 'current_source.integrity_sql', 'pragma integrity_check'],
    'page0 source table valued fk normalized' => [$page0, 'current_source.foreign_key_sql', "select * from pragma_foreign_key_check('archive.wp_options')"],
    'quick status' => [$quick, 'status', 'ok'],
    'quick total skips pointer map' => [$quick, 'total', 5],
    'quick pointer count zero' => [$quick, 'current.pointer_map', 0],
    'quick fk count' => [$quick, 'current.foreign_key', 5],
    'quick first source fk' => [$quick, 'rows.0.source', 'foreign_key'],
    'quick source integrity normalized' => [$quick, 'current_source.integrity_sql', 'pragma quick_check'],
    'clean pointer total only fk' => [$cleanPointer, 'total', 5],
    'clean pointer pointer zero' => [$cleanPointer, 'current.pointer_map', 0],
    'clean pointer first fk rowid' => [$cleanPointer, 'rows.0.rowid', 'archive-missing-1'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity fk pointermap current source next90 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity fk pointermap current source next90 stable source resumes table valued page'] = static function (TestRunner $t) use ($bytes, $schemaRows, $tableValuedSql, $catalog): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 0, 44, 'PRAGMA integrity_check', null, $catalog);
    $second = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 44, 44, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => $first['next_offset']], $catalog);
    $third = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 88, 44, 'PRAGMA integrity_check', ['source_id' => $second['source_id'], 'next_offset' => $second['next_offset']], $catalog);

    $t->same(44, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 44], $first['next']);
    $t->same('pointer_map', $second['rows'][0]['source']);
    $t->same(48, $second['rows'][0]['page']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 88], $second['next']);
    $t->same(2, $third['count']);
    $t->same('foreign_key', $third['rows'][0]['source']);
    $t->same('archive-missing-4', $third['rows'][0]['rowid']);
    $t->same(null, $third['next']);
};

$tests['pragma integrity fk pointermap current source next90 source id changes when database bytes change'] = static function (TestRunner $t) use ($database, $schemaRows, $tableValuedSql, $catalog): void {
    $dirty = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database(false), $schemaRows, $tableValuedSql, 0, 90, 'PRAGMA integrity_check', null, $catalog);
    $clean = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database(true), $schemaRows, $tableValuedSql, 0, 90, 'PRAGMA integrity_check', null, $catalog);

    $t->same(true, $dirty['source_id'] !== $clean['source_id']);
    $t->same(true, $dirty['current_source']['database'] !== $clean['current_source']['database']);
    $t->same($dirty['current_source']['schema_hash'], $clean['current_source']['schema_hash']);
};

$tests['pragma integrity fk pointermap current source next90 source id changes when schemas change'] = static function (TestRunner $t) use ($bytes, $schemas, $tableValuedSql, $catalog): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemas(5), $tableValuedSql, 0, 90, 'PRAGMA integrity_check', null, $catalog);
    $second = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemas(6), $tableValuedSql, 0, 90, 'PRAGMA integrity_check', null, $catalog);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['current_source']['schema_hash'] !== $second['current_source']['schema_hash']);
    $t->same(90, $first['total']);
    $t->same(91, $second['total']);
};

$tests['pragma integrity fk pointermap current source next90 rejects stale database cursor'] = static function (TestRunner $t) use ($database, $schemaRows, $tableValuedSql, $catalog): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database(false), $schemaRows, $tableValuedSql, 0, 44, 'PRAGMA integrity_check', null, $catalog);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($database(true), $schemaRows, $tableValuedSql, 44, 44, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => 44], $catalog));
};

$tests['pragma integrity fk pointermap current source next90 rejects stale schema cursor'] = static function (TestRunner $t) use ($bytes, $schemas, $tableValuedSql, $catalog): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemas(5), $tableValuedSql, 0, 44, 'PRAGMA integrity_check', null, $catalog);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemas(6), $tableValuedSql, 44, 44, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => 44], $catalog));
};

$tests['pragma integrity fk pointermap current source next90 rejects stale table valued sql cursor'] = static function (TestRunner $t) use ($bytes, $schemaRows, $tableValuedSql, $catalog): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 0, 44, 'PRAGMA integrity_check', null, $catalog);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, "SELECT * FROM pragma_foreign_key_check('wp_options')", 44, 44, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => 44], $catalog));
};

$tests['pragma integrity fk pointermap current source next90 rejects stale integrity sql cursor'] = static function (TestRunner $t) use ($bytes, $schemaRows, $tableValuedSql, $catalog): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 0, 44, 'PRAGMA integrity_check', null, $catalog);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 44, 44, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 44], $catalog));
};

$tests['pragma integrity fk pointermap current source next90 rejects stale offset cursor'] = static function (TestRunner $t) use ($bytes, $schemaRows, $tableValuedSql, $catalog): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 0, 44, 'PRAGMA integrity_check', null, $catalog);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 45, 44, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => 44], $catalog));
};

$tests['pragma integrity fk pointermap current source next90 accepts source-only cursor without offset check'] = static function (TestRunner $t) use ($bytes, $schemaRows, $tableValuedSql, $catalog): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 0, 44, 'PRAGMA integrity_check', null, $catalog);
    $second = SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 44, 44, 'PRAGMA integrity_check', ['source_id' => $first['source_id']], $catalog);

    $t->same(44, $second['offset']);
    $t->same('pointer_map', $second['rows'][0]['source']);
    $t->same($first['source_id'], $second['source_id']);
};

$tests['pragma integrity fk pointermap current source next90 rejects negative offset'] = static function (TestRunner $t) use ($bytes, $schemaRows, $tableValuedSql, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, -1, 90, 'PRAGMA integrity_check', null, $catalog));
};

$tests['pragma integrity fk pointermap current source next90 rejects zero limit'] = static function (TestRunner $t) use ($bytes, $schemaRows, $tableValuedSql, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma($bytes, $schemaRows, $tableValuedSql, 0, 0, 'PRAGMA integrity_check', null, $catalog));
};

return $tests;
