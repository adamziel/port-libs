<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityAutoindexYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];
$pageSize = 4096;

$headerPage = static function (int $pageCount, int $largestRoot) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRoot), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values), $pageSize);
$indexPage = static fn (): string => SQLiteIndexLeafPage::assemble([], $pageSize);

$makeDatabase = static function (
    int $autoindexCount = 55,
    ?callable $mutatePointerMap = null,
    ?callable $mutatePages = null,
    ?callable $mutateSchemaRecords = null,
) use ($headerPage, $putPointerMapEntry, $schemaCell, $indexPage, $pageSize): string {
    $pageCount = $autoindexCount + 3;
    $largestRoot = $pageCount;
    $schemaRecords = [
        $schemaCell(['table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, autoload TEXT)'], 1),
    ];
    for ($i = 1; $i <= $autoindexCount; $i++) {
        $schemaRecords[] = $schemaCell(['index', 'sqlite_autoindex_wp_options_' . $i, 'wp_options', $i + 3, null], $i + 1);
    }
    if ($mutateSchemaRecords !== null) {
        $schemaRecords = $mutateSchemaRecords($schemaRecords, $schemaCell);
    }

    $pointerMap = str_repeat("\0", $pageSize);
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, SQLitePointerMapEntry::ROOT_PAGE, 0);
    }
    if ($mutatePointerMap !== null) {
        $pointerMap = $mutatePointerMap($pointerMap, $putPointerMapEntry);
    }

    $pages = [
        1 => SQLiteTableLeafPage::assemble($schemaRecords, $pageSize, 100, $headerPage($pageCount, $largestRoot)),
        2 => $pointerMap,
        3 => SQLiteTableLeafPage::assemble([], $pageSize),
    ];
    for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = $indexPage();
    }
    if ($mutatePages !== null) {
        $pages = $mutatePages($pages);
    }
    ksort($pages);

    return implode('', $pages);
};

$page0 = static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($makeDatabase());
$page1 = static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($makeDatabase(), 50);
$page2 = static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($makeDatabase(), 100);
$collect = static fn (): array => SQLitePragmaIntegrityAutoindexYield::collect($makeDatabase());
$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $part === 'count' ? count($value) : $value[(int) $part];
    }

    return $value;
};

foreach ([
    'page0 status' => [$page0, 'status', 'ok'],
    'page0 default offset' => [$page0, 'offset', 0],
    'page0 default current next50 limit' => [$page0, 'limit', 50],
    'page0 count is current next50' => [$page0, 'count', 50],
    'page0 total includes all autoindexes' => [$page0, 'total', 55],
    'page0 next offset' => [$page0, 'next_offset', 50],
    'page0 incomplete' => [$page0, 'complete', false],
    'page0 first kind' => [$page0, 'rows.0.kind', 'autoindex'],
    'page0 first name' => [$page0, 'rows.0.name', 'sqlite_autoindex_wp_options_1'],
    'page0 first table' => [$page0, 'rows.0.table', 'wp_options'],
    'page0 first schema rowid' => [$page0, 'rows.0.schema_rowid', 2],
    'page0 first rootpage' => [$page0, 'rows.0.rootpage', 4],
    'page0 first status ok' => [$page0, 'rows.0.status', 'ok'],
    'page0 first message' => [$page0, 'rows.0.message', 'sqlite_schema autoindex sqlite_autoindex_wp_options_1 rootpage 4 ok'],
    'page0 fiftieth name' => [$page0, 'rows.49.name', 'sqlite_autoindex_wp_options_50'],
    'page0 fiftieth rootpage' => [$page0, 'rows.49.rootpage', 53],
    'page1 offset' => [$page1, 'offset', 50],
    'page1 count tail' => [$page1, 'count', 5],
    'page1 total stable' => [$page1, 'total', 55],
    'page1 next offset null' => [$page1, 'next_offset', null],
    'page1 complete' => [$page1, 'complete', true],
    'page1 first tail name' => [$page1, 'rows.0.name', 'sqlite_autoindex_wp_options_51'],
    'page1 first tail rootpage' => [$page1, 'rows.0.rootpage', 54],
    'page1 last tail name' => [$page1, 'rows.4.name', 'sqlite_autoindex_wp_options_55'],
    'page1 last tail rootpage' => [$page1, 'rows.4.rootpage', 58],
    'page2 empty count' => [$page2, 'count', 0],
    'page2 complete' => [$page2, 'complete', true],
    'collect count' => [$collect, 'count', 55],
    'collect first name' => [$collect, '0.name', 'sqlite_autoindex_wp_options_1'],
    'collect last name' => [$collect, '54.name', 'sqlite_autoindex_wp_options_55'],
] as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity autoindex yield current next50 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

foreach ([1, 2, 3, 10, 25, 50, 55] as $index) {
    $tests['pragma integrity autoindex yield current next50 stable autoindex row ' . $index] = static function (TestRunner $t) use ($makeDatabase, $index): void {
        $row = SQLitePragmaIntegrityAutoindexYield::collect($makeDatabase())[$index - 1];
        $t->same([
            'name' => 'sqlite_autoindex_wp_options_' . $index,
            'schema_rowid' => $index + 1,
            'rootpage' => $index + 3,
            'status' => 'ok',
        ], [
            'name' => $row['name'],
            'schema_rowid' => $row['schema_rowid'],
            'rootpage' => $row['rootpage'],
            'status' => $row['status'],
        ]);
    };
}

foreach ([5, 17, 33, 55] as $index) {
    $tests['pragma integrity autoindex yield current next50 pointer-map type mismatch ' . $index] = static function (TestRunner $t) use ($makeDatabase, $index): void {
        $database = $makeDatabase(55, static function (string $pointerMap, callable $put) use ($index): string {
            return $put($pointerMap, $index + 3, SQLitePointerMapEntry::BTREE_PAGE, 3);
        });
        $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[$index - 1];

        $t->same('error', $row['status']);
        $t->same("sqlite_schema autoindex sqlite_autoindex_wp_options_{$index} rootpage " . ($index + 3) . ' pointer-map type btree-page does not match expected root-page', $row['message']);
    };
}

foreach ([4, 6, 58] as $rootPage) {
    $tests['pragma integrity autoindex yield current next50 pointer-map parent mismatch root ' . $rootPage] = static function (TestRunner $t) use ($makeDatabase, $rootPage): void {
        $database = $makeDatabase(55, static function (string $pointerMap, callable $put) use ($rootPage): string {
            return $put($pointerMap, $rootPage, SQLitePointerMapEntry::ROOT_PAGE, 3);
        });
        $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[$rootPage - 4];

        $t->same("sqlite_schema autoindex {$row['name']} rootpage {$rootPage} pointer-map parent 3 does not match expected parent 0", $row['message']);
    };
}

foreach ([7 => 0x0d, 8 => 0x05, 9 => 0x00] as $rootPage => $flag) {
    $tests['pragma integrity autoindex yield current next50 rejects non-index root flag ' . $rootPage] = static function (TestRunner $t) use ($makeDatabase, $rootPage, $flag, $pageSize): void {
        $database = $makeDatabase(55, null, static function (array $pages) use ($rootPage, $flag, $pageSize): array {
            $pages[$rootPage] = str_repeat("\0", $pageSize);
            $pages[$rootPage][0] = chr($flag);
            return $pages;
        });
        $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[$rootPage - 4];

        $t->same('error', $row['status']);
        $t->same(sprintf('sqlite_schema autoindex %s rootpage %d is not an index b-tree page: 0x%02x', $row['name'], $rootPage, $flag), $row['message']);
    };
}

$tests['pragma integrity autoindex yield current next50 ignores explicit index sql'] = static function (TestRunner $t) use ($makeDatabase, $schemaCell): void {
    $database = $makeDatabase(2, null, null, static function (array $records, callable $cell) use ($schemaCell): array {
        $records[] = $schemaCell(['index', 'wp_options_autoload_idx', 'wp_options', 6, 'CREATE INDEX wp_options_autoload_idx ON wp_options(autoload)'], 99);
        return $records;
    });

    $t->same(['sqlite_autoindex_wp_options_1', 'sqlite_autoindex_wp_options_2'], array_column(SQLitePragmaIntegrityAutoindexYield::collect($database), 'name'));
};

$tests['pragma integrity autoindex yield current next50 ignores non-autoindex null sql index'] = static function (TestRunner $t) use ($makeDatabase, $schemaCell): void {
    $database = $makeDatabase(1, null, null, static function (array $records) use ($schemaCell): array {
        $records[] = $schemaCell(['index', 'wp_options_manual_null_sql', 'wp_options', 5, null], 88);
        return $records;
    });

    $t->same(['sqlite_autoindex_wp_options_1'], array_column(SQLitePragmaIntegrityAutoindexYield::collect($database), 'name'));
};

$tests['pragma integrity autoindex yield current next50 reports root beyond image'] = static function (TestRunner $t) use ($makeDatabase, $schemaCell): void {
    $database = $makeDatabase(1, null, null, static fn (array $records): array => [
        $records[0],
        $schemaCell(['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 99, null], 2),
    ]);
    $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[0];

    $t->same('sqlite_schema autoindex sqlite_autoindex_wp_options_1 rootpage 99 is beyond the database image', $row['message']);
};

$tests['pragma integrity autoindex yield current next50 reports negative root'] = static function (TestRunner $t) use ($makeDatabase, $schemaCell): void {
    $database = $makeDatabase(1, null, null, static fn (array $records): array => [
        $records[0],
        $schemaCell(['index', 'sqlite_autoindex_wp_options_1', 'wp_options', -1, null], 2),
    ]);
    $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[0];

    $t->same('sqlite_schema autoindex sqlite_autoindex_wp_options_1 rootpage -1 is negative', $row['message']);
};

$tests['pragma integrity autoindex yield current next50 reports empty root'] = static function (TestRunner $t) use ($makeDatabase, $schemaCell): void {
    $database = $makeDatabase(1, null, null, static fn (array $records): array => [
        $records[0],
        $schemaCell(['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 0, null], 2),
    ]);
    $row = SQLitePragmaIntegrityAutoindexYield::collect($database)[0];

    $t->same('sqlite_schema autoindex sqlite_autoindex_wp_options_1 rootpage is empty', $row['message']);
};

$tests['pragma integrity autoindex yield current next50 rejects negative offset'] = static function (TestRunner $t) use ($makeDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexYield::page($makeDatabase(), -1));
};

$tests['pragma integrity autoindex yield current next50 rejects zero limit'] = static function (TestRunner $t) use ($makeDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexYield::page($makeDatabase(), 0, 0));
};

$tests['pragma integrity autoindex yield current next50 propagates pragma guard'] = static function (TestRunner $t) use ($makeDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexYield::page($makeDatabase(), 0, 50, 'PRAGMA table_info(wp_options)'));
};

$tests['pragma integrity autoindex yield current next50 short database returns database diagnostic'] = static function (TestRunner $t): void {
    $row = SQLitePragmaIntegrityAutoindexYield::collect(str_repeat("\0", 20))[0];

    $t->same(['database', 'error', 'SQLite database header requires at least 100 bytes'], [$row['kind'], $row['status'], $row['message']]);
};

$tests['pragma integrity autoindex yield current next50 custom small limit pages'] = static function (TestRunner $t) use ($makeDatabase): void {
    $page = SQLitePragmaIntegrityAutoindexYield::page($makeDatabase(7), 3, 2);

    $t->same(['count' => 2, 'total' => 7, 'next_offset' => 5, 'first' => 'sqlite_autoindex_wp_options_4'], [
        'count' => $page['count'],
        'total' => $page['total'],
        'next_offset' => $page['next_offset'],
        'first' => $page['rows'][0]['name'],
    ]);
};

return $tests;
