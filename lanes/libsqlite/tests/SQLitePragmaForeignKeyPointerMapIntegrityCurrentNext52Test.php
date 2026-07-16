<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyPointerMapIntegrityYield;

$pageSize = 512;
$pageCount = 63;

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$makeDatabase = static function () use ($pageSize, $pageCount, $putPointerMapEntry): string {
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

$schemas = static function (): array {
    $mainChildren = [];
    for ($i = 1; $i <= 9; $i++) {
        $mainChildren[] = ['rowid' => $i, 'post_id' => 100 + $i];
    }

    $tempChildren = [];
    for ($i = 1; $i <= 5; $i++) {
        $tempChildren[] = ['rowid' => 'autoload-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_posts' => [['rowid' => 1, 'ID' => 1]],
                'wp_postmeta' => $mainChildren,
            ],
            'foreignKeys' => [
                ['id' => 4, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID', 'affinity' => 'integer']]],
            ],
        ],
        'temp' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $tempChildren,
            ],
            'foreignKeys' => [
                ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase']]],
            ],
        ],
    ];
};

$database = $makeDatabase();
$schemasValue = $schemas();
$page0 = static fn (): array => SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, $schemasValue, 0, 52);
$page1 = static fn (): array => SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, $schemasValue, 52, 52);
$small = static fn (): array => SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, $schemasValue, 49, 6);
$quick = static fn (): array => SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, $schemasValue, 0, 52, 'PRAGMA quick_check');
$collect = static fn (): array => SQLitePragmaForeignKeyPointerMapIntegrityYield::collect($database, $schemasValue);

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
    'page0 limit current next52' => [$page0, 'limit', 52],
    'page0 count current next52' => [$page0, 'count', 52],
    'page0 total stable' => [$page0, 'total', 74],
    'page0 next offset' => [$page0, 'next_offset', 52],
    'page0 incomplete' => [$page0, 'complete', false],
    'page0 current pointer rows' => [$page0, 'current.integrity_pointer_map', 60],
    'page0 current foreign key rows' => [$page0, 'current.foreign_key_violations', 14],
    'page0 first kind' => [$page0, 'rows.0.kind', 'integrity_check'],
    'page0 first source' => [$page0, 'rows.0.source', 'pointer_map'],
    'page0 first page' => [$page0, 'rows.0.page', 4],
    'page0 first pointer map page' => [$page0, 'rows.0.pointer_map_page', 2],
    'page0 first schema null' => [$page0, 'rows.0.schema', null],
    'page0 first rowid null' => [$page0, 'rows.0.rowid', null],
    'page0 first message' => [$page0, 'rows.0.message', 'pointer-map parent page 0 for btree-page page 4 is not valid'],
    'page0 second page' => [$page0, 'rows.1.page', 5],
    'page0 fifth page' => [$page0, 'rows.4.page', 8],
    'page0 tenth page' => [$page0, 'rows.9.page', 13],
    'page0 twentieth page' => [$page0, 'rows.19.page', 23],
    'page0 thirtieth page' => [$page0, 'rows.29.page', 33],
    'page0 fortieth page' => [$page0, 'rows.39.page', 43],
    'page0 fiftieth page' => [$page0, 'rows.49.page', 53],
    'page0 last page before boundary' => [$page0, 'rows.51.page', 55],
    'page0 last pointer map page before boundary' => [$page0, 'rows.51.pointer_map_page', 2],
    'page1 status' => [$page1, 'status', 'ok'],
    'page1 offset' => [$page1, 'offset', 52],
    'page1 limit' => [$page1, 'limit', 52],
    'page1 count remaining' => [$page1, 'count', 22],
    'page1 total stable' => [$page1, 'total', 74],
    'page1 complete' => [$page1, 'complete', true],
    'page1 next offset null' => [$page1, 'next_offset', null],
    'page1 first page continues' => [$page1, 'rows.0.page', 56],
    'page1 eighth pointer row page' => [$page1, 'rows.7.page', 63],
    'page1 eighth pointer row source' => [$page1, 'rows.7.source', 'pointer_map'],
    'page1 first fk source' => [$page1, 'rows.8.source', 'foreign_key'],
    'page1 first fk kind' => [$page1, 'rows.8.kind', 'foreign_key_check'],
    'page1 first fk schema temp' => [$page1, 'rows.8.schema', 'temp'],
    'page1 first fk table' => [$page1, 'rows.8.table', 'wp_options'],
    'page1 first fk rowid' => [$page1, 'rows.8.rowid', 'autoload-1'],
    'page1 first fk parent' => [$page1, 'rows.8.parent', 'wp_option_names'],
    'page1 first fk fkid' => [$page1, 'rows.8.fkid', 2],
    'page1 first fk page null' => [$page1, 'rows.8.page', null],
    'page1 first fk pointer map null' => [$page1, 'rows.8.pointer_map_page', null],
    'page1 first fk message' => [$page1, 'rows.8.message', 'foreign key mismatch in temp.wp_options rowid autoload-1 references wp_option_names fkid 2'],
    'page1 last temp fk rowid' => [$page1, 'rows.12.rowid', 'autoload-5'],
    'page1 first main fk schema' => [$page1, 'rows.13.schema', 'main'],
    'page1 first main fk table' => [$page1, 'rows.13.table', 'wp_postmeta'],
    'page1 first main fk rowid' => [$page1, 'rows.13.rowid', 1],
    'page1 first main fk fkid' => [$page1, 'rows.13.fkid', 4],
    'page1 final row source' => [$page1, 'rows.21.source', 'foreign_key'],
    'page1 final row schema' => [$page1, 'rows.21.schema', 'main'],
    'page1 final rowid' => [$page1, 'rows.21.rowid', 9],
    'page1 final parent' => [$page1, 'rows.21.parent', 'wp_posts'],
    'page1 final message' => [$page1, 'rows.21.message', 'foreign key mismatch in main.wp_postmeta rowid 9 references wp_posts fkid 4'],
    'small offset' => [$small, 'offset', 49],
    'small count' => [$small, 'count', 6],
    'small next offset' => [$small, 'next_offset', 55],
    'small first page' => [$small, 'rows.0.page', 53],
    'small fourth page crosses current next52 boundary' => [$small, 'rows.3.page', 56],
    'small last page' => [$small, 'rows.5.page', 58],
    'quick status' => [$quick, 'status', 'ok'],
    'quick count only foreign keys' => [$quick, 'count', 14],
    'quick total only foreign keys' => [$quick, 'total', 14],
    'quick complete' => [$quick, 'complete', true],
    'quick pointer current zero' => [$quick, 'current.integrity_pointer_map', 0],
    'quick fk current remains counted' => [$quick, 'current.foreign_key_violations', 14],
    'quick first source foreign key' => [$quick, 'rows.0.source', 'foreign_key'],
    'quick first row temp first' => [$quick, 'rows.0.rowid', 'autoload-1'],
    'quick final row main' => [$quick, 'rows.13.rowid', 9],
    'collect count' => [$collect, 'count', 74],
    'collect first source' => [$collect, '0.source', 'pointer_map'],
    'collect last pointer page before fk' => [$collect, '59.page', 63],
    'collect first fk source' => [$collect, '60.source', 'foreign_key'],
    'collect first fk rowid' => [$collect, '60.rowid', 'autoload-1'],
    'collect final schema' => [$collect, '73.schema', 'main'],
    'collect final rowid' => [$collect, '73.rowid', 9],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma foreignkey pointermap integrity current next52 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma foreignkey pointermap integrity current next52 collect matches page concatenation'] = static function (TestRunner $t) use ($collect, $page0, $page1): void {
    $t->same($collect(), array_merge($page0()['rows'], $page1()['rows']));
};

$tests['pragma foreignkey pointermap integrity current next52 tail offset returns empty complete page'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $page = SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, $schemasValue, 74, 52);
    $t->same(['count' => 0, 'total' => 74, 'next_offset' => null, 'complete' => true], ['count' => $page['count'], 'total' => $page['total'], 'next_offset' => $page['next_offset'], 'complete' => $page['complete']]);
};

$tests['pragma foreignkey pointermap integrity current next52 exact boundary has no next offset'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $page = SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, $schemasValue, 22, 52);
    $t->same(['count' => 52, 'total' => 74, 'next_offset' => null, 'complete' => true], ['count' => $page['count'], 'total' => $page['total'], 'next_offset' => $page['next_offset'], 'complete' => $page['complete']]);
};

$tests['pragma foreignkey pointermap integrity current next52 clean schemas still report pointer rows'] = static function (TestRunner $t) use ($database): void {
    $page = SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, [], 0, 52);
    $t->same(['total' => 60, 'foreign_key_violations' => 0, 'integrity_pointer_map' => 60], ['total' => $page['total'], 'foreign_key_violations' => $page['current']['foreign_key_violations'], 'integrity_pointer_map' => $page['current']['integrity_pointer_map']]);
};

$tests['pragma foreignkey pointermap integrity current next52 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, $schemasValue, -1, 52));
};

$tests['pragma foreignkey pointermap integrity current next52 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, $schemasValue, 0, 0));
};

$tests['pragma foreignkey pointermap integrity current next52 propagates pragma parser guard'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyPointerMapIntegrityYield::page($database, $schemasValue, 0, 52, 'PRAGMA foreign_key_check'));
};

return $tests;
