<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrity;
use PortLibs\LibSqlite\SQLitePragmaIntegrityForeignKeyYield;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;

$makeDatabase = static function (): string {
    $pageSize = 512;
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x09";
    $page[19] = "\x09";
    $page = substr_replace($page, pack('N', 9), 56, 4);

    return $page;
};

$schemas = static function (): array {
    $mainChildren = [];
    for ($i = 1; $i <= 35; $i++) {
        $mainChildren[] = ['rowid' => $i, 'parent_id' => 1000 + $i];
    }

    $tempChildren = [];
    for ($i = 1; $i <= 8; $i++) {
        $tempChildren[] = ['rowid' => 'temp-' . $i, 'slug' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_parent' => [['rowid' => 1, 'id' => 1]],
                'wp_child' => $mainChildren,
            ],
            'foreignKeys' => [
                ['id' => 7, 'table' => 'wp_child', 'parent' => 'wp_parent', 'columns' => [['child' => 'parent_id', 'parent' => 'id', 'affinity' => 'integer']]],
            ],
        ],
        'temp' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'slug' => 'siteurl']],
                'wp_options' => $tempChildren,
            ],
            'foreignKeys' => [
                ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'slug', 'parent' => 'slug', 'collation' => 'nocase']]],
            ],
        ],
    ];
};

$database = $makeDatabase();
$schemasValue = $schemas();
$page0 = static fn (): array => SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 0, 32);
$page1 = static fn (): array => SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 32, 32);
$page2 = static fn (): array => SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 64, 32);
$quickPage = static fn (): array => SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 0, 32, 'PRAGMA quick_check(1)');
$collect = static fn (): array => SQLitePragmaIntegrityForeignKeyYield::collect($database, $schemasValue);
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
    'page0 limit' => [$page0, 'limit', 32],
    'page0 count is current next32' => [$page0, 'count', 32],
    'page0 total includes integrity and foreign keys' => [$page0, 'total', 46],
    'page0 next offset' => [$page0, 'next_offset', 32],
    'page0 incomplete' => [$page0, 'complete', false],
    'page0 first kind integrity' => [$page0, 'rows.0.kind', 'integrity_check'],
    'page0 first message write version' => [$page0, 'rows.0.message', 'invalid schema write version 9'],
    'page0 second message read version' => [$page0, 'rows.1.message', 'invalid schema read version 9'],
    'page0 third message encoding' => [$page0, 'rows.2.message', 'invalid text encoding 9'],
    'page0 first fk kind after integrity rows' => [$page0, 'rows.3.kind', 'foreign_key_check'],
    'page0 first fk schema temp first' => [$page0, 'rows.3.schema', 'temp'],
    'page0 first fk table' => [$page0, 'rows.3.table', 'wp_options'],
    'page0 first fk rowid' => [$page0, 'rows.3.rowid', 'temp-1'],
    'page0 first fk parent' => [$page0, 'rows.3.parent', 'wp_option_names'],
    'page0 first fk fkid' => [$page0, 'rows.3.fkid', 2],
    'page0 first fk message' => [$page0, 'rows.3.message', 'foreign key mismatch in temp.wp_options rowid temp-1 references wp_option_names fkid 2'],
    'page0 last row remains main before boundary' => [$page0, 'rows.31.schema', 'main'],
    'page0 last rowid' => [$page0, 'rows.31.rowid', 21],
    'page1 status' => [$page1, 'status', 'ok'],
    'page1 offset' => [$page1, 'offset', 32],
    'page1 limit' => [$page1, 'limit', 32],
    'page1 count remaining' => [$page1, 'count', 14],
    'page1 total stable' => [$page1, 'total', 46],
    'page1 complete' => [$page1, 'complete', true],
    'page1 next offset null' => [$page1, 'next_offset', null],
    'page1 first rowid continues' => [$page1, 'rows.0.rowid', 22],
    'page1 first schema main' => [$page1, 'rows.0.schema', 'main'],
    'page1 first table main child' => [$page1, 'rows.0.table', 'wp_child'],
    'page1 first fkid main' => [$page1, 'rows.0.fkid', 7],
    'page1 last rowid' => [$page1, 'rows.13.rowid', 35],
    'page1 last parent' => [$page1, 'rows.13.parent', 'wp_parent'],
    'page1 last message' => [$page1, 'rows.13.message', 'foreign key mismatch in main.wp_child rowid 35 references wp_parent fkid 7'],
    'page2 empty count' => [$page2, 'count', 0],
    'page2 empty total stable' => [$page2, 'total', 46],
    'page2 complete' => [$page2, 'complete', true],
    'page2 next offset null' => [$page2, 'next_offset', null],
    'quick page count is current next32' => [$quickPage, 'count', 32],
    'quick page total uses limited integrity errors' => [$quickPage, 'total', 44],
    'quick page first kind' => [$quickPage, 'rows.0.kind', 'quick_check'],
    'quick page first message' => [$quickPage, 'rows.0.message', 'invalid schema write version 9'],
    'quick page first fk starts after one quick row' => [$quickPage, 'rows.1.kind', 'foreign_key_check'],
    'quick page first fk schema temp' => [$quickPage, 'rows.1.schema', 'temp'],
    'quick page first fk rowid' => [$quickPage, 'rows.1.rowid', 'temp-1'],
    'quick page last rowid shifts by quick limit' => [$quickPage, 'rows.31.rowid', 23],
    'collect count' => [$collect, 'count', 46],
    'collect first kind' => [$collect, '0.kind', 'integrity_check'],
    'collect fourth kind' => [$collect, '3.kind', 'foreign_key_check'],
    'collect temp precedes main' => [$collect, '3.schema', 'temp'],
    'collect final schema' => [$collect, '45.schema', 'main'],
    'collect final rowid' => [$collect, '45.rowid', 35],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity foreign key yield current next32 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity foreign key yield current next32 empty schemas returns integrity only'] = static function (TestRunner $t) use ($database): void {
    $page = SQLitePragmaIntegrityForeignKeyYield::page($database, [], 0, 32);
    $t->same(['total' => 3, 'count' => 3, 'next_offset' => null], ['total' => $page['total'], 'count' => $page['count'], 'next_offset' => $page['next_offset']]);
};

$tests['pragma integrity foreign key yield current next32 clean database and clean foreign keys returns empty'] = static function (TestRunner $t): void {
    $pageSize = 512;
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', 1), 56, 4);
    $schemas = [
        'main' => [
            'tables' => ['parent' => [['id' => 1]], 'child' => [['rowid' => 1, 'parent_id' => 1]]],
            'foreignKeys' => [['table' => 'child', 'parent' => 'parent', 'columns' => [['child' => 'parent_id', 'parent' => 'id', 'affinity' => 'integer']]]],
        ],
    ];

    $page = SQLitePragmaIntegrityForeignKeyYield::page($page, $schemas, 0, 32);
    $t->same(['total' => 0, 'count' => 0, 'complete' => true], ['total' => $page['total'], 'count' => $page['count'], 'complete' => $page['complete']]);
};

$tests['pragma integrity foreign key yield current next32 exact boundary has no next offset'] = static function (TestRunner $t) use ($database): void {
    $children = [];
    for ($i = 1; $i <= 31; $i++) {
        $children[] = ['rowid' => $i, 'parent_id' => $i + 100];
    }
    $schemas = [
        'main' => [
            'tables' => ['parent' => [['id' => 1]], 'child' => $children],
            'foreignKeys' => [['table' => 'child', 'parent' => 'parent', 'columns' => ['parent_id' => 'id']]],
        ],
    ];

    $page = SQLitePragmaIntegrityForeignKeyYield::page($database, $schemas, 0, 32, 'PRAGMA quick_check(1)');
    $t->same(['count' => 32, 'total' => 32, 'next_offset' => null, 'complete' => true], ['count' => $page['count'], 'total' => $page['total'], 'next_offset' => $page['next_offset'], 'complete' => $page['complete']]);
};

$tests['pragma integrity foreign key yield current next32 offset lands inside integrity rows'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $page = SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 1, 2);
    $t->same(['invalid schema read version 9', 'invalid text encoding 9'], array_column($page['rows'], 'message'));
};

$tests['pragma integrity foreign key yield current next32 small page next offset advances by count'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $page = SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 30, 5);
    $t->same(['count' => 5, 'next_offset' => 35, 'complete' => false], ['count' => $page['count'], 'next_offset' => $page['next_offset'], 'complete' => $page['complete']]);
};

$tests['pragma integrity foreign key yield current next32 tail page next offset null'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $page = SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 44, 5);
    $t->same(['count' => 2, 'next_offset' => null, 'complete' => true], ['count' => $page['count'], 'next_offset' => $page['next_offset'], 'complete' => $page['complete']]);
};

$tests['pragma integrity foreign key yield current next32 rejects negative offset'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, -1, 32));
};

$tests['pragma integrity foreign key yield current next32 rejects zero limit'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 0, 0));
};

$tests['pragma integrity foreign key yield current next32 propagates integrity pragma parse guard'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 0, 32, 'PRAGMA table_info(wp_options)'));
};

$tests['pragma integrity foreign key yield current next32 agrees with standalone integrity count'] = static function (TestRunner $t) use ($database): void {
    $standalone = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database);
    $page = SQLitePragmaIntegrityForeignKeyYield::page($database, [], 0, 32);
    $t->same(count($standalone['errors']), $page['total']);
};

$tests['pragma integrity foreign key yield current next32 agrees with standalone foreign key count'] = static function (TestRunner $t) use ($database, $schemasValue): void {
    $foreignKeys = SQLitePragmaForeignKeyIntegrity::executeAllSchemas($schemasValue);
    $page = SQLitePragmaIntegrityForeignKeyYield::page($database, $schemasValue, 0, 64);
    $t->same(count($foreignKeys['rows']), $page['total'] - 3);
};

$tests['pragma integrity foreign key yield current next32 preserves row order across page concatenation'] = static function (TestRunner $t) use ($page0, $page1, $collect): void {
    $stitched = array_merge($page0()['rows'], $page1()['rows']);
    $t->same($collect(), $stitched);
};

$tests['pragma integrity foreign key yield current next32 message set has no ok row'] = static function (TestRunner $t) use ($collect): void {
    $t->same(false, in_array('ok', array_column($collect(), 'message'), true));
};

return $tests;
