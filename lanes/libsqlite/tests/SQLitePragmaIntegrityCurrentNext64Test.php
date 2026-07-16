<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCurrentNextYield;

$pageSize = 512;
$pageCount = 75;

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$makeDatabase = static function (bool $validPointers = false) use ($pageSize, $pageCount, $putPointerMapEntry): string {
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
            $validPointers ? 3 : 0,
        );
    }

    $pages = [$header, $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[] = str_repeat("\0", $pageSize);
    }

    return implode('', $pages);
};

$schemas = static function (): array {
    $mainChildren = [];
    for ($i = 1; $i <= 8; $i++) {
        $mainChildren[] = ['rowid' => $i, 'post_id' => 100 + $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_posts' => [['rowid' => 1, 'ID' => 1]],
                'wp_postmeta' => $mainChildren,
            ],
            'foreignKeys' => [
                ['id' => 9, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
            ],
        ],
    ];
};

$database = $makeDatabase();
$schemaRows = $schemas();
$page0 = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::page($database, $schemaRows, 0, 64);
$page1 = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::page($database, $schemaRows, 64, 64);
$small = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::page($database, $schemaRows, 60, 8);
$quick = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::page($database, $schemaRows, 0, 64, 'PRAGMA quick_check');
$collect = static fn (): array => SQLitePragmaIntegrityCurrentNextYield::collect($database, $schemaRows);

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
        $value = $value[(int) $part];
    }

    return $value;
};

$cases = [
    'page0 status' => [$page0, 'status', 'ok'],
    'page0 offset' => [$page0, 'offset', 0],
    'page0 limit current next64' => [$page0, 'limit', 64],
    'page0 count current next64' => [$page0, 'count', 64],
    'page0 total stable' => [$page0, 'total', 80],
    'page0 next offset' => [$page0, 'next_offset', 64],
    'page0 incomplete' => [$page0, 'complete', false],
    'page0 current pointer count' => [$page0, 'current.pointer_map', 72],
    'page0 current foreign key count' => [$page0, 'current.foreign_key', 8],
    'page0 current btree zero' => [$page0, 'current.btree', 0],
    'page0 current schema root zero' => [$page0, 'current.schema_root', 0],
    'page0 current freelist zero' => [$page0, 'current.freelist', 0],
    'page0 first kind' => [$page0, 'rows.0.kind', 'integrity_check'],
    'page0 first source' => [$page0, 'rows.0.source', 'pointer_map'],
    'page0 first page' => [$page0, 'rows.0.page', 4],
    'page0 first pointer map page' => [$page0, 'rows.0.pointer_map_page', 2],
    'page0 first schema null' => [$page0, 'rows.0.schema', null],
    'page0 first table null' => [$page0, 'rows.0.table', null],
    'page0 first fkid null' => [$page0, 'rows.0.fkid', null],
    'page0 first message' => [$page0, 'rows.0.message', 'pointer-map parent page 0 for btree-page page 4 is not valid'],
    'page0 second page' => [$page0, 'rows.1.page', 5],
    'page0 tenth page' => [$page0, 'rows.9.page', 13],
    'page0 twentieth page' => [$page0, 'rows.19.page', 23],
    'page0 thirtieth page' => [$page0, 'rows.29.page', 33],
    'page0 fortieth page' => [$page0, 'rows.39.page', 43],
    'page0 fiftieth page' => [$page0, 'rows.49.page', 53],
    'page0 sixtieth page' => [$page0, 'rows.59.page', 63],
    'page0 boundary page' => [$page0, 'rows.63.page', 67],
    'page0 boundary pointer map page' => [$page0, 'rows.63.pointer_map_page', 2],
    'page1 status' => [$page1, 'status', 'ok'],
    'page1 offset' => [$page1, 'offset', 64],
    'page1 count remaining' => [$page1, 'count', 16],
    'page1 total stable' => [$page1, 'total', 80],
    'page1 complete' => [$page1, 'complete', true],
    'page1 next offset null' => [$page1, 'next_offset', null],
    'page1 first source still pointer' => [$page1, 'rows.0.source', 'pointer_map'],
    'page1 first page continues' => [$page1, 'rows.0.page', 68],
    'page1 eighth pointer page' => [$page1, 'rows.7.page', 75],
    'page1 eighth pointer kind' => [$page1, 'rows.7.kind', 'integrity_check'],
    'page1 first fk source' => [$page1, 'rows.8.source', 'foreign_key'],
    'page1 first fk kind' => [$page1, 'rows.8.kind', 'foreign_key_check'],
    'page1 first fk schema' => [$page1, 'rows.8.schema', 'main'],
    'page1 first fk table' => [$page1, 'rows.8.table', 'wp_postmeta'],
    'page1 first fk rowid' => [$page1, 'rows.8.rowid', 1],
    'page1 first fk parent' => [$page1, 'rows.8.parent', 'wp_posts'],
    'page1 first fk fkid' => [$page1, 'rows.8.fkid', 9],
    'page1 first fk page null' => [$page1, 'rows.8.page', null],
    'page1 first fk pointer map null' => [$page1, 'rows.8.pointer_map_page', null],
    'page1 first fk message' => [$page1, 'rows.8.message', 'foreign key mismatch in main.wp_postmeta rowid 1 references wp_posts fkid 9'],
    'page1 final row source' => [$page1, 'rows.15.source', 'foreign_key'],
    'page1 final rowid' => [$page1, 'rows.15.rowid', 8],
    'page1 final message' => [$page1, 'rows.15.message', 'foreign key mismatch in main.wp_postmeta rowid 8 references wp_posts fkid 9'],
    'small offset' => [$small, 'offset', 60],
    'small count' => [$small, 'count', 8],
    'small next offset' => [$small, 'next_offset', 68],
    'small first page' => [$small, 'rows.0.page', 64],
    'small fourth page crosses next64 boundary' => [$small, 'rows.4.page', 68],
    'small last page' => [$small, 'rows.7.page', 71],
    'quick status' => [$quick, 'status', 'ok'],
    'quick count only foreign keys' => [$quick, 'count', 8],
    'quick total only foreign keys' => [$quick, 'total', 8],
    'quick complete' => [$quick, 'complete', true],
    'quick pointer zero' => [$quick, 'current.pointer_map', 0],
    'quick foreign key count' => [$quick, 'current.foreign_key', 8],
    'quick first source foreign key' => [$quick, 'rows.0.source', 'foreign_key'],
    'quick final rowid' => [$quick, 'rows.7.rowid', 8],
    'collect count' => [$collect, 'count', 80],
    'collect first source' => [$collect, '0.source', 'pointer_map'],
    'collect last pointer page before fk' => [$collect, '71.page', 75],
    'collect first fk source' => [$collect, '72.source', 'foreign_key'],
    'collect final rowid' => [$collect, '79.rowid', 8],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity current next64 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity current next64 collect matches page concatenation'] = static function (TestRunner $t) use ($collect, $page0, $page1): void {
    $t->same($collect(), array_merge($page0()['rows'], $page1()['rows']));
};

$tests['pragma integrity current next64 tail offset returns empty complete page'] = static function (TestRunner $t) use ($database, $schemaRows): void {
    $page = SQLitePragmaIntegrityCurrentNextYield::page($database, $schemaRows, 80, 64);
    $t->same(['count' => 0, 'total' => 80, 'next_offset' => null, 'complete' => true], ['count' => $page['count'], 'total' => $page['total'], 'next_offset' => $page['next_offset'], 'complete' => $page['complete']]);
};

$tests['pragma integrity current next64 clean pointers still report foreign keys'] = static function (TestRunner $t) use ($makeDatabase, $schemaRows): void {
    $page = SQLitePragmaIntegrityCurrentNextYield::page($makeDatabase(true), $schemaRows, 0, 64);
    $t->same(['total' => 8, 'pointer_map' => 0, 'foreign_key' => 8], ['total' => $page['total'], 'pointer_map' => $page['current']['pointer_map'], 'foreign_key' => $page['current']['foreign_key']]);
};

$tests['pragma integrity current next64 accepts integrity without schemas'] = static function (TestRunner $t) use ($database): void {
    $page = SQLitePragmaIntegrityCurrentNextYield::page($database, [], 0, 64);
    $t->same(['count' => 64, 'total' => 72, 'foreign_key' => 0], ['count' => $page['count'], 'total' => $page['total'], 'foreign_key' => $page['current']['foreign_key']]);
};

$tests['pragma integrity current next64 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemaRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCurrentNextYield::page($database, $schemaRows, -1, 64));
};

$tests['pragma integrity current next64 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemaRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCurrentNextYield::page($database, $schemaRows, 0, 0));
};

$tests['pragma integrity current next64 propagates pragma parser guard'] = static function (TestRunner $t) use ($database, $schemaRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCurrentNextYield::page($database, $schemaRows, 0, 64, 'PRAGMA foreign_key_check'));
};

return $tests;
