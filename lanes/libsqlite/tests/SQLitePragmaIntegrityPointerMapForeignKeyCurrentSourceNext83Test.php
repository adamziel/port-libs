<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegritySourceCursor;

$pageSize = 512;
$pageCount = 70;

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

$schemas = static function (int $missingOptions = 8): array {
    $optionRows = [];
    for ($i = 1; $i <= $missingOptions; $i++) {
        $optionRows[] = ['rowid' => 'option-' . $i, 'option_name' => 'missing_' . $i, 'autoload' => 'yes'];
    }
    $optionRows[] = ['rowid' => 'option-null', 'option_name' => null, 'autoload' => 'yes'];

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [
                    ['rowid' => 1, 'name' => 'siteurl'],
                ],
                'wp_options' => $optionRows,
            ],
            'foreignKeys' => [
                ['id' => 13, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$bytes = $database();
$schemaRows = $schemas();

$page0 = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
    $bytes,
    $schemaRows,
    'PRAGMA main.foreign_key_check(wp_options)',
    0,
    83,
);
$page1 = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
    $bytes,
    $schemaRows,
    'PRAGMA main.foreign_key_check(wp_options)',
    83,
    83,
    'PRAGMA integrity_check',
    ['source_id' => $page0()['source_id'], 'next_offset' => $page0()['next_offset']],
);
$quick = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
    $bytes,
    $schemaRows,
    'PRAGMA main.foreign_key_check(wp_options)',
    0,
    83,
    'PRAGMA quick_check',
);
$cleanPointer = static fn (): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
    $database(true),
    $schemaRows,
    'PRAGMA main.foreign_key_check(wp_options)',
    0,
    83,
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
    'page0 limit current source next83' => [$page0, 'limit', 83],
    'page0 count' => [$page0, 'count', 75],
    'page0 total' => [$page0, 'total', 75],
    'page0 complete' => [$page0, 'complete', true],
    'page0 next offset null' => [$page0, 'next_offset', null],
    'page0 cursor next null' => [$page0, 'next', null],
    'page0 pointer count' => [$page0, 'current.pointer_map', 67],
    'page0 foreign key count' => [$page0, 'current.foreign_key', 8],
    'page0 first source pointer' => [$page0, 'rows.0.source', 'pointer_map'],
    'page0 first pointer page' => [$page0, 'rows.0.page', 4],
    'page0 first pointer map page' => [$page0, 'rows.0.pointer_map_page', 2],
    'page0 midpoint pointer page' => [$page0, 'rows.40.page', 44],
    'page0 last pointer source' => [$page0, 'rows.66.source', 'pointer_map'],
    'page0 last pointer page' => [$page0, 'rows.66.page', 70],
    'page0 first fk source' => [$page0, 'rows.67.source', 'foreign_key'],
    'page0 first fk schema' => [$page0, 'rows.67.schema', 'main'],
    'page0 first fk table' => [$page0, 'rows.67.table', 'wp_options'],
    'page0 first fk rowid' => [$page0, 'rows.67.rowid', 'option-1'],
    'page0 first fk parent' => [$page0, 'rows.67.parent', 'wp_option_names'],
    'page0 first fk fkid' => [$page0, 'rows.67.fkid', 13],
    'page0 first fk message' => [$page0, 'rows.67.message', 'foreign key mismatch in main.wp_options rowid option-1 references wp_option_names fkid 13'],
    'page0 final fk rowid' => [$page0, 'rows.74.rowid', 'option-8'],
    'page0 source database sha length' => [static fn (): array => ['len' => strlen($page0()['current_source']['database'])], 'len', 64],
    'page0 source schema sha length' => [static fn (): array => ['len' => strlen($page0()['current_source']['schema_hash'])], 'len', 64],
    'page0 source id length' => [static fn (): array => ['len' => strlen($page0()['source_id'])], 'len', 64],
    'page0 source integrity normalized' => [$page0, 'current_source.integrity_sql', 'pragma integrity_check'],
    'page0 source fk normalized' => [$page0, 'current_source.foreign_key_sql', 'pragma main.foreign_key_check(wp_options)'],
    'quick status' => [$quick, 'status', 'ok'],
    'quick total skips pointer map' => [$quick, 'total', 8],
    'quick pointer count zero' => [$quick, 'current.pointer_map', 0],
    'quick fk count' => [$quick, 'current.foreign_key', 8],
    'quick first source' => [$quick, 'rows.0.source', 'foreign_key'],
    'quick source integrity normalized' => [$quick, 'current_source.integrity_sql', 'pragma quick_check'],
    'clean pointer total only fk' => [$cleanPointer, 'total', 8],
    'clean pointer pointer zero' => [$cleanPointer, 'current.pointer_map', 0],
    'clean pointer first fk rowid' => [$cleanPointer, 'rows.0.rowid', 'option-1'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity pointermap fk current source next83 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity pointermap fk current source next83 stable source resumes exact next page'] = static function (TestRunner $t) use ($bytes, $schemaRows): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 0, 40);
    $second = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 40, 40, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => $first['next_offset']]);

    $t->same(40, $first['count']);
    $t->same(40, $first['next']['offset']);
    $t->same(35, $second['count']);
    $t->same(75, $second['total']);
    $t->same(null, $second['next']);
    $t->same(true, $second['complete']);
    $t->same('pointer_map', $second['rows'][0]['source']);
};

$tests['pragma integrity pointermap fk current source next83 source id changes when database bytes change'] = static function (TestRunner $t) use ($database, $schemaRows): void {
    $dirty = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($database(false), $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)');
    $clean = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($database(true), $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)');

    $t->same(true, $dirty['source_id'] !== $clean['source_id']);
    $t->same(true, $dirty['current_source']['database'] !== $clean['current_source']['database']);
    $t->same($dirty['current_source']['schema_hash'], $clean['current_source']['schema_hash']);
};

$tests['pragma integrity pointermap fk current source next83 source id changes when schemas change'] = static function (TestRunner $t) use ($bytes, $schemas): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemas(8), 'PRAGMA main.foreign_key_check(wp_options)');
    $second = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemas(9), 'PRAGMA main.foreign_key_check(wp_options)');

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['current_source']['schema_hash'] !== $second['current_source']['schema_hash']);
    $t->same(75, $first['total']);
    $t->same(76, $second['total']);
};

$tests['pragma integrity pointermap fk current source next83 rejects stale database cursor'] = static function (TestRunner $t) use ($database, $schemaRows): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($database(false), $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 0, 40);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($database(true), $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 40, 40, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => 40]));
};

$tests['pragma integrity pointermap fk current source next83 rejects stale schema cursor'] = static function (TestRunner $t) use ($bytes, $schemas): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemas(8), 'PRAGMA main.foreign_key_check(wp_options)', 0, 40);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemas(9), 'PRAGMA main.foreign_key_check(wp_options)', 40, 40, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => 40]));
};

$tests['pragma integrity pointermap fk current source next83 rejects stale pragma cursor'] = static function (TestRunner $t) use ($bytes, $schemaRows): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 0, 40, 'PRAGMA integrity_check');
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 40, 40, 'PRAGMA quick_check', ['source_id' => $first['source_id'], 'next_offset' => 40]));
};

$tests['pragma integrity pointermap fk current source next83 rejects stale offset cursor'] = static function (TestRunner $t) use ($bytes, $schemaRows): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 0, 40);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 41, 40, 'PRAGMA integrity_check', ['source_id' => $first['source_id'], 'next_offset' => 40]));
};

$tests['pragma integrity pointermap fk current source next83 accepts source-only cursor without offset check'] = static function (TestRunner $t) use ($bytes, $schemaRows): void {
    $first = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 0, 40);
    $second = SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 40, 40, 'PRAGMA integrity_check', ['source_id' => $first['source_id']]);

    $t->same(40, $second['offset']);
    $t->same('pointer_map', $second['rows'][0]['source']);
    $t->same($first['source_id'], $second['source_id']);
};

$tests['pragma integrity pointermap fk current source next83 rejects negative offset'] = static function (TestRunner $t) use ($bytes, $schemaRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', -1, 83));
};

$tests['pragma integrity pointermap fk current source next83 rejects zero limit'] = static function (TestRunner $t) use ($bytes, $schemaRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma($bytes, $schemaRows, 'PRAGMA main.foreign_key_check(wp_options)', 0, 0));
};

return $tests;
