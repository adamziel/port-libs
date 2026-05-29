<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];
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

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$schemaCell = static function (array $values, int $rowId): string {
    return SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
};

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['index', 'wp_options_autoload', 'wp_options', 6, 'CREATE INDEX wp_options_autoload ON wp_options(autoload, option_name)'],
    ['table', 'wp_posts', 'wp_posts', 7, 'CREATE TABLE wp_posts(ID integer primary key, post_title text)'],
    ['index', 'wp_posts_title', 'wp_posts', 8, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)'],
];

$schemaDatabase = static function (
    array $rows,
    int $pageCount,
    int $largestRootPage,
    array $pointerMapEntries,
    array $pageImages = [],
    int $firstFreelist = 0,
    int $freelistCount = 0,
) use ($headerPage, $putPointerMapEntry, $schemaCell, $pageSize): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $rows, array_keys($rows)),
            $pageSize,
            100,
            $headerPage($pageCount, $largestRootPage, $firstFreelist, $freelistCount),
        ),
    ];

    if ($pageCount >= 2) {
        $pointerMap = str_repeat("\0", $pageSize);
        foreach ($pointerMapEntries as $entry) {
            $pointerMap = $putPointerMapEntry($pointerMap, $entry[0], $entry[1], $entry[2]);
        }
        $pages[2] = $pointerMap;
    }

    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = $pageImages[$pageNumber] ?? SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$validDatabase = $schemaDatabase($schemaRows, 8, 8, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);

$optionsIndexBeyond = $schemaDatabase([
    $schemaRows[0],
    ['index', 'wp_options_name', 'wp_options', 12, $schemaRows[1][4]],
    $schemaRows[2],
    $schemaRows[3],
], 8, 8, [
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);

$postsRootBeyond = $schemaDatabase([
    $schemaRows[0],
    $schemaRows[1],
    ['table', 'wp_posts', 'wp_posts', 12, $schemaRows[3][4]],
], 8, 8, [
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);

$optionsPointerMismatch = $schemaDatabase($schemaRows, 8, 8, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);

$postsPointerMismatch = $schemaDatabase($schemaRows, 8, 8, [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]);

$freelistHeaderMismatch = $schemaDatabase(
    $schemaRows,
    8,
    8,
    [
        [3, SQLitePointerMapEntry::FREE_PAGE, 0],
        [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
        [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    ],
    [3 => SQLiteFreelistTrunkPage::assemble(null, [], $pageSize)],
    3,
    9,
);

$execute = static fn (string $sql, string $database): array => SQLitePragmaIntegrityCheck::execute($sql, $database);

$cases = [
    'clean scoped integrity reports pragma name' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $validDatabase)['pragma'], 'integrity_check'],
    'clean scoped integrity preserves table ok row' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $validDatabase)['rows'][0]['integrity_check'], 'ok'],
    'clean scoped integrity has no errors' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $validDatabase)['errors'], []],
    'clean scoped quick reports pragma name' => [static fn (): mixed => $execute('PRAGMA quick_check(wp_options)', $validDatabase)['pragma'], 'quick_check'],
    'clean scoped quick preserves table ok row' => [static fn (): mixed => $execute('PRAGMA quick_check(wp_options)', $validDatabase)['rows'][0]['quick_check'], 'ok'],
    'schema qualified scoped target accepted' => [static fn (): mixed => $execute('PRAGMA main.integrity_check(wp_options)', $validDatabase)['rows'][0]['integrity_check'], 'ok'],
    'double quoted scoped target accepted' => [static fn (): mixed => $execute('PRAGMA integrity_check("wp_options")', $validDatabase)['rows'][0]['integrity_check'], 'ok'],
    'single quoted scoped target accepted' => [static fn (): mixed => $execute("PRAGMA integrity_check('wp_options')", $validDatabase)['rows'][0]['integrity_check'], 'ok'],
    'backtick quoted scoped target accepted' => [static fn (): mixed => $execute('PRAGMA integrity_check(`wp_options`)', $validDatabase)['rows'][0]['integrity_check'], 'ok'],
    'bracket quoted scoped target accepted' => [static fn (): mixed => $execute('PRAGMA integrity_check([wp_options])', $validDatabase)['rows'][0]['integrity_check'], 'ok'],
    'trailing semicolon scoped target accepted' => [static fn (): mixed => $execute(" PRAGMA integrity_check(wp_options);\n", $validDatabase)['rows'][0]['integrity_check'], 'ok'],
    'numeric argument remains global limit' => [static fn (): mixed => $execute('PRAGMA integrity_check(2)', $freelistHeaderMismatch)['limit'], 2],
    'numeric argument does not require table named two' => [static fn (): mixed => $execute('PRAGMA integrity_check(2)', $freelistHeaderMismatch)['errors'][0], 'freelist header count 9 does not match reachable freelist page count 1'],
    'equals limit remains global' => [static fn (): mixed => $execute('PRAGMA integrity_check = 2', $freelistHeaderMismatch)['limit'], 2],
    'options index beyond routes through scoped errors' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond)['errors'][0], 'sqlite_schema index wp_options_name rootpage 12 is beyond the database image'],
    'options index beyond uses integrity row column' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond)['rows'][0]['integrity_check'], 'sqlite_schema index wp_options_name rootpage 12 is beyond the database image'],
    'posts root beyond is ignored for scoped options' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $postsRootBeyond)['rows'][0]['integrity_check'], 'ok'],
    'posts root beyond remains global error' => [static fn (): mixed => $execute('PRAGMA integrity_check', $postsRootBeyond)['errors'][0], 'sqlite_schema table wp_posts rootpage 12 is beyond the database image'],
    'options pointer mismatch scoped' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $optionsPointerMismatch)['errors'][0], 'pointer-map type btree-page for page 4 does not match expected root-page'],
    'posts pointer mismatch ignored for options' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $postsPointerMismatch)['rows'][0]['integrity_check'], 'ok'],
    'freelist mismatch ignored for scoped options' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $freelistHeaderMismatch)['rows'][0]['integrity_check'], 'ok'],
    'freelist mismatch remains global error' => [static fn (): mixed => $execute('PRAGMA integrity_check', $freelistHeaderMismatch)['errors'][0], 'freelist header count 9 does not match reachable freelist page count 1'],
    'quick scoped uses quick row column for pointer mismatch' => [static fn (): mixed => $execute('PRAGMA quick_check(wp_options)', $optionsPointerMismatch)['rows'][0]['quick_check'], 'pointer-map type btree-page for page 4 does not match expected root-page'],
    'quick scoped still ignores other table root' => [static fn (): mixed => $execute('PRAGMA quick_check(wp_options)', $postsRootBeyond)['rows'][0]['quick_check'], 'ok'],
    'scoped result keeps executor limit metadata' => [static fn (): mixed => $execute('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond)['limit'], 100],
    'missing scoped target is rejected' => [static function () use ($execute, $validDatabase): mixed {
        $execute('PRAGMA integrity_check(wp_missing)', $validDatabase);
    }, InvalidArgumentException::class],
    'unsupported pragma still rejected' => [static function () use ($execute, $validDatabase): mixed {
        $execute('PRAGMA table_info(wp_options)', $validDatabase);
    }, InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pragma integrity check table scope execute ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && class_exists($expected)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

foreach (range(1, 20) as $index) {
    $sql = match ($index % 5) {
        0 => 'PRAGMA integrity_check([wp_options])',
        1 => 'PRAGMA quick_check(wp_options)',
        2 => 'PRAGMA main.integrity_check("wp_options")',
        3 => "PRAGMA quick_check('wp_options')",
        default => 'PRAGMA integrity_check(`wp_options`)',
    };
    $tests['pragma integrity check table scope execute repeated scoped dispatch ' . $index] = static function (TestRunner $t) use ($execute, $validDatabase, $sql): void {
        $result = $execute($sql, $validDatabase);
        $column = $result['pragma'];

        $t->same('ok', $result['rows'][0][$column]);
    };
}

return $tests;
