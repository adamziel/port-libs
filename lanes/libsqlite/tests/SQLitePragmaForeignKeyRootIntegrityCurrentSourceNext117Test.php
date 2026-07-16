<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;

$headerPage = static function (int $pageCount, int $largestRootPage, int $firstFreelist = 0, int $freelistCount = 0) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelist), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);
    if ($offset < 0 || $offset + 5 > $pageSize) {
        throw new RuntimeException('test pointer-map entry offset is out of range');
    }

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));

$schemaDatabase = static function (
    array $schemaRows,
    int $pageCount,
    int $largestRootPage,
    array $pointerMapEntries,
    array $pageImages = [],
    int $firstFreelist = 0,
    int $freelistCount = 0,
) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $headerPage($pageCount, $largestRootPage, $firstFreelist, $freelistCount),
        ),
    ];

    $pointerMap = str_repeat("\0", $pageSize);
    foreach ($pointerMapEntries as $entry) {
        $pointerMap = $putPointerMapEntry($pointerMap, $entry[0], $entry[1], $entry[2]);
    }
    $pages[2] = $pointerMap;

    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = $pageImages[$pageNumber] ?? SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$schemaRows = [
    'table' => ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    'index' => ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    'duplicate' => ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    'view' => ['view', 'wp_active_options', 'wp_active_options', 0, "CREATE VIEW wp_active_options AS SELECT * FROM wp_options WHERE autoload = 'yes'"],
];

$database = $schemaDatabase([
    $schemaRows['table'],
    $schemaRows['index'],
    $schemaRows['duplicate'],
    ['table', 'wp_missing_root', 'wp_missing_root', 9, 'CREATE TABLE wp_missing_root(id integer primary key)'],
    ['table', 'wp_free_root', 'wp_free_root', 6, 'CREATE TABLE wp_free_root(id integer primary key)'],
    $schemaRows['view'],
], 6, 6, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    3 => SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize),
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
], 3, 2);

$cleanDatabase = $schemaDatabase([
    $schemaRows['table'],
    $schemaRows['index'],
    $schemaRows['view'],
], 5, 5, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
], [
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$schemas = static function (int $missing = 10): array {
    $rows = [
        ['rowid' => 1, 'option_name' => 'siteurl'],
    ];
    for ($i = 1; $i <= $missing; $i++) {
        $rows[] = ['rowid' => 'missing-' . $i, 'option_name' => 'missing_' . $i];
    }
    $rows[] = ['rowid' => 'null-option', 'option_name' => null];

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [
                    ['rowid' => 1, 'name' => 'siteurl'],
                ],
                'wp_options' => $rows,
            ],
            'foreignKeys' => [
                ['id' => 17, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$record = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    'CREATE ' . strtoupper($type) . ' ' . $name,
    $root,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4),
    $record('table', 'wp_option_names', 'wp_option_names', 7),
]);
$schema = $schemas();
$sql = 'PRAGMA main.foreign_key_check(wp_options)';

$page = static fn (): array => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 0, 117, null, $catalog);
$page0 = static fn (): array => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 0, 8, null, $catalog);
$page1 = static fn (): array => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 8, 8, ['source_id' => $page0()['source_id'], 'next_offset' => $page0()['next_offset']], $catalog);
$clean = static fn (): array => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($cleanDatabase, $schema, $sql, 0, 117, null, $catalog);

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
    'status ok' => [$page, 'status', 'ok'],
    'default limit next117' => [$page, 'limit', 117],
    'total combines roots and fk rows' => [$page, 'total', 16],
    'current root count' => [$page, 'current.integrity_root', 6],
    'current fk count' => [$page, 'current.foreign_key', 10],
    'complete true' => [$page, 'complete', true],
    'next null' => [$page, 'next', null],
    'source database hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['database'])], 'len', 64],
    'source schema hash length' => [static fn (): array => ['len' => strlen($page()['current_source']['schema_hash'])], 'len', 64],
    'source catalog hash length' => [static fn (): array => ['len' => strlen((string) $page()['current_source']['catalog_hash'])], 'len', 64],
    'source id length' => [static fn (): array => ['len' => strlen($page()['source_id'])], 'len', 64],
    'source sql normalized' => [$page, 'current_source.foreign_key_sql', 'pragma main.foreign_key_check(wp_options)'],
    'row0 kind root' => [$page, 'rows.0.kind', 'integrity_root'],
    'row0 duplicate source' => [$page, 'rows.0.source', 'duplicate_rootpage'],
    'row0 table name' => [$page, 'rows.0.name', 'wp_options'],
    'row0 rootpage' => [$page, 'rows.0.rootpage', 4],
    'row0 page status duplicate' => [$page, 'rows.0.page_status', 'duplicate'],
    'row1 duplicate index' => [$page, 'rows.1.name', 'wp_options_alias'],
    'row2 pointer conflict source' => [$page, 'rows.2.source', 'pointer_map_conflict'],
    'row2 pointer page' => [$page, 'rows.2.pointer_map_page', 2],
    'row2 page type index leaf' => [$page, 'rows.2.page_type', 'index-leaf'],
    'row3 freelist conflict' => [$page, 'rows.3.source', 'freelist_conflict'],
    'row3 freelist page status' => [$page, 'rows.3.page_status', 'freelist'],
    'row4 largest root mismatch' => [$page, 'rows.4.source', 'largest_root_mismatch'],
    'row5 beyond image' => [$page, 'rows.5.page_status', 'beyond_image'],
    'row6 fk kind' => [$page, 'rows.6.kind', 'foreign_key_check'],
    'row6 fk source' => [$page, 'rows.6.source', 'foreign_key'],
    'row6 fk schema' => [$page, 'rows.6.schema', 'main'],
    'row6 fk table' => [$page, 'rows.6.table', 'wp_options'],
    'row6 fk rowid' => [$page, 'rows.6.rowid', 'missing-1'],
    'row6 fk parent' => [$page, 'rows.6.parent', 'wp_option_names'],
    'row6 fk id' => [$page, 'rows.6.fkid', 17],
    'row6 fk message' => [$page, 'rows.6.message', 'foreign key mismatch in main.wp_options rowid missing-1 references wp_option_names fkid 17'],
    'last fk rowid' => [$page, 'rows.15.rowid', 'missing-10'],
    'page0 count' => [$page0, 'count', 8],
    'page0 next offset' => [$page0, 'next_offset', 8],
    'page0 next cursor offset' => [$page0, 'next.offset', 8],
    'page1 offset' => [$page1, 'offset', 8],
    'page1 first rowid' => [$page1, 'rows.0.rowid', 'missing-3'],
    'page1 count' => [$page1, 'count', 8],
    'page1 complete' => [$page1, 'complete', true],
    'clean total only fk' => [$clean, 'total', 10],
    'clean root count zero' => [$clean, 'current.integrity_root', 0],
    'clean fk count' => [$clean, 'current.foreign_key', 10],
    'clean first source fk' => [$clean, 'rows.0.source', 'foreign_key'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma fk root integrity current source next117 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma fk root integrity current source next117 source changes with database'] = static function (TestRunner $t) use ($database, $cleanDatabase, $schema, $sql, $catalog): void {
    $dirty = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 0, 117, null, $catalog);
    $clean = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($cleanDatabase, $schema, $sql, 0, 117, null, $catalog);

    $t->same(true, $dirty['source_id'] !== $clean['source_id']);
    $t->same(true, $dirty['current_source']['database'] !== $clean['current_source']['database']);
    $t->same($dirty['current_source']['schema_hash'], $clean['current_source']['schema_hash']);
};

$tests['pragma fk root integrity current source next117 source changes with schemas'] = static function (TestRunner $t) use ($database, $schemas, $sql, $catalog): void {
    $first = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schemas(10), $sql, 0, 117, null, $catalog);
    $second = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schemas(11), $sql, 0, 117, null, $catalog);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(16, $first['total']);
    $t->same(17, $second['total']);
};

$tests['pragma fk root integrity current source next117 rejects stale source cursor'] = static function (TestRunner $t) use ($database, $cleanDatabase, $schema, $sql, $catalog): void {
    $first = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 0, 8, null, $catalog);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($cleanDatabase, $schema, $sql, 8, 8, ['source_id' => $first['source_id'], 'next_offset' => 8], $catalog));
};

$tests['pragma fk root integrity current source next117 rejects stale offset cursor'] = static function (TestRunner $t) use ($database, $schema, $sql, $catalog): void {
    $first = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 0, 8, null, $catalog);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 9, 8, ['source_id' => $first['source_id'], 'next_offset' => 8], $catalog));
};

$tests['pragma fk root integrity current source next117 accepts source-only cursor'] = static function (TestRunner $t) use ($database, $schema, $sql, $catalog): void {
    $first = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 0, 8, null, $catalog);
    $second = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 8, 8, ['source_id' => $first['source_id']], $catalog);

    $t->same(8, $second['offset']);
    $t->same($first['source_id'], $second['source_id']);
    $t->same('missing-3', $second['rows'][0]['rowid']);
};

$tests['pragma fk root integrity current source next117 rejects negative offset'] = static function (TestRunner $t) use ($database, $schema, $sql, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, -1, 117, null, $catalog));
};

$tests['pragma fk root integrity current source next117 rejects zero limit'] = static function (TestRunner $t) use ($database, $schema, $sql, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($database, $schema, $sql, 0, 0, null, $catalog));
};

foreach (range(1, 18) as $index) {
    $tests['pragma fk root integrity current source next117 repeated clean fk row ' . $index] = static function (TestRunner $t) use ($cleanDatabase, $schemas, $sql, $catalog, $index): void {
        $result = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page($cleanDatabase, $schemas($index), $sql, 0, 117, null, $catalog);
        $t->same($index, $result['current']['foreign_key']);
        $t->same(0, $result['current']['integrity_root']);
        $t->same('missing-1', $result['rows'][0]['rowid']);
    };
}

return $tests;
