<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegritySourceCursor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$pageSize = 512;
$pageCount = 76;

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$database = static function () use ($pageSize, $pageCount, $putPointerMapEntry): string {
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
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 0);
    }

    $pages = [$header, $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[] = str_repeat("\0", $pageSize);
    }

    return implode('', $pages);
};

$schemas = [
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
            'wp_options' => [
                ['rowid' => 'archive-siteurl', 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-missing-1', 'option_name' => 'missing_1'],
                ['rowid' => 'archive-missing-2', 'option_name' => 'missing_2'],
                ['rowid' => 'archive-missing-3', 'option_name' => 'missing_3'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 9, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

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
$foreignKeySql = "SELECT * FROM pragma_foreign_key_check('archive.wp_options')";
$page = static fn (int $offset, int $limit, ?array $cursor = null): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
    $bytes,
    $schemas,
    $foreignKeySql,
    $offset,
    $limit,
    'PRAGMA integrity_check',
    $cursor,
    $catalog,
);
$pragmaPage = static fn (int $offset, int $limit, ?array $cursor = null): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
    $bytes,
    $schemas,
    'PRAGMA foreign_key_check(archive.wp_options)',
    $offset,
    $limit,
    'PRAGMA integrity_check',
    $cursor,
    $catalog,
);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'first status' => [static fn (): array => $page(0, 37), 'status', 'ok'],
    'first offset' => [static fn (): array => $page(0, 37), 'offset', 0],
    'first limit' => [static fn (): array => $page(0, 37), 'limit', 37],
    'first count' => [static fn (): array => $page(0, 37), 'count', 37],
    'first total' => [static fn (): array => $page(0, 37), 'total', 76],
    'first next offset' => [static fn (): array => $page(0, 37), 'next_offset', 37],
    'first next token offset' => [static fn (): array => $page(0, 37), 'next.offset', 37],
    'first pointer count' => [static fn (): array => $page(0, 37), 'current.pointer_map', 73],
    'first fk count' => [static fn (): array => $page(0, 37), 'current.foreign_key', 3],
    'first source kind' => [static fn (): array => $page(0, 37), 'rows.0.source', 'pointer_map'],
    'first page number' => [static fn (): array => $page(0, 37), 'rows.0.page', 4],
    'first pointer map page' => [static fn (): array => $page(0, 37), 'rows.0.pointer_map_page', 2],
    'first last page number' => [static fn (): array => $page(0, 37), 'rows.36.page', 40],
    'second offset' => [static fn (): array => $page(37, 37, $page(0, 37)['next']), 'offset', 37],
    'second count' => [static fn (): array => $page(37, 37, $page(0, 37)['next']), 'count', 37],
    'second first page' => [static fn (): array => $page(37, 37, $page(0, 37)['next']), 'rows.0.page', 41],
    'second last source' => [static fn (): array => $page(37, 37, $page(0, 37)['next']), 'rows.36.source', 'foreign_key'],
    'second first fk rowid' => [static fn (): array => $page(37, 37, $page(0, 37)['next']), 'rows.36.rowid', 'archive-missing-1'],
    'second next offset' => [static fn (): array => $page(37, 37, $page(0, 37)['next']), 'next.offset', 74],
    'third count' => [static fn (): array => $page(74, 37, $page(37, 37, $page(0, 37)['next'])['next']), 'count', 2],
    'third complete' => [static fn (): array => $page(74, 37, $page(37, 37, $page(0, 37)['next'])['next']), 'complete', true],
    'third first fk rowid' => [static fn (): array => $page(74, 37, $page(37, 37, $page(0, 37)['next'])['next']), 'rows.0.rowid', 'archive-missing-2'],
    'third final fk rowid' => [static fn (): array => $page(74, 37, $page(37, 37, $page(0, 37)['next'])['next']), 'rows.1.rowid', 'archive-missing-3'],
    'third next null' => [static fn (): array => $page(74, 37, $page(37, 37, $page(0, 37)['next'])['next']), 'next', null],
    'statement pragma first total' => [static fn (): array => $pragmaPage(0, 37), 'total', 76],
    'statement pragma next offset' => [static fn (): array => $pragmaPage(0, 37), 'next.offset', 37],
    'statement pragma resume page' => [static fn (): array => $pragmaPage(37, 37, $pragmaPage(0, 37)['next']), 'rows.0.page', 41],
    'source database hash length' => [static fn (): array => ['length' => strlen($page(0, 37)['current_source']['database'])], 'length', 64],
    'source schema hash length' => [static fn (): array => ['length' => strlen($page(0, 37)['current_source']['schema_hash'])], 'length', 64],
    'source id length' => [static fn (): array => ['length' => strlen($page(0, 37)['source_id'])], 'length', 64],
    'source integrity normalized' => [static fn (): array => $page(0, 37), 'current_source.integrity_sql', 'pragma integrity_check'],
    'source fk normalized' => [static fn (): array => $page(0, 37), 'current_source.foreign_key_sql', "select * from pragma_foreign_key_check('archive.wp_options')"],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity pointermap foreignkey current source next94 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity pointermap foreignkey current source next94 rejects emitted next token at wrong offset'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 37);
    $t->throws(InvalidArgumentException::class, static fn () => $page(38, 37, $first['next']));
};

$tests['pragma integrity pointermap foreignkey current source next94 rejects statement next token at wrong offset'] = static function (TestRunner $t) use ($pragmaPage): void {
    $first = $pragmaPage(0, 37);
    $t->throws(InvalidArgumentException::class, static fn () => $pragmaPage(38, 37, $first['next']));
};

$tests['pragma integrity pointermap foreignkey current source next94 keeps legacy next_offset token compatibility'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 37);
    $second = $page(37, 37, ['source_id' => $first['source_id'], 'next_offset' => 37]);
    $t->same(37, $second['offset']);
    $t->same(41, $second['rows'][0]['page']);
    $t->same(74, $second['next']['offset']);
};

$tests['pragma integrity pointermap foreignkey current source next94 rejects legacy next_offset token at wrong offset'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 37);
    $t->throws(InvalidArgumentException::class, static fn () => $page(38, 37, ['source_id' => $first['source_id'], 'next_offset' => 37]));
};

$tests['pragma integrity pointermap foreignkey current source next94 accepts source-only cursor for manual seek'] = static function (TestRunner $t) use ($page): void {
    $first = $page(0, 37);
    $manual = $page(74, 37, ['source_id' => $first['source_id']]);
    $t->same(74, $manual['offset']);
    $t->same('archive-missing-2', $manual['rows'][0]['rowid']);
};

return $tests;
