<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLitePragmaIntegrityTableScopeYield;
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

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$collect = static fn (string $sql, string $database): array => SQLitePragmaIntegrityTableScopeYield::collect($database, $sql);
$page = static fn (string $sql, string $database, int $offset = 0, int $limit = 32): array => SQLitePragmaIntegrityTableScopeYield::page($database, $sql, $offset, $limit);

$cases = [
    'parse integrity pragma' => [static fn (): mixed => SQLitePragmaIntegrityTableScopeYield::parse('PRAGMA integrity_check(wp_options)')['pragma'], 'integrity_check'],
    'parse quick pragma' => [static fn (): mixed => SQLitePragmaIntegrityTableScopeYield::parse('PRAGMA quick_check(wp_options)')['pragma'], 'quick_check'],
    'parse schema name' => [static fn (): mixed => SQLitePragmaIntegrityTableScopeYield::parse('PRAGMA main.integrity_check(wp_options)')['schema'], 'main'],
    'parse quoted schema name' => [static fn (): mixed => SQLitePragmaIntegrityTableScopeYield::parse('PRAGMA "main".quick_check(wp_options)')['schema'], 'main'],
    'parse bracketed schema name' => [static fn (): mixed => SQLitePragmaIntegrityTableScopeYield::parse('PRAGMA [main].quick_check(wp_options)')['schema'], 'main'],
    'parse backtick schema name' => [static fn (): mixed => SQLitePragmaIntegrityTableScopeYield::parse('PRAGMA `main`.quick_check(wp_options)')['schema'], 'main'],
    'parse quoted target' => [static fn (): mixed => SQLitePragmaIntegrityTableScopeYield::parse('PRAGMA integrity_check("wp options")')['target'], 'wp options'],
    'parse single quoted target' => [static fn (): mixed => SQLitePragmaIntegrityTableScopeYield::parse("PRAGMA integrity_check('wp_options')")['target'], 'wp_options'],
    'valid status ok' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $validDatabase)['status'], 'ok'],
    'valid next ready' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $validDatabase)['next']['ready'], true],
    'valid target root count' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $validDatabase)['current']['root_count'], 3],
    'valid error count' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $validDatabase)['current']['error_count'], 0],
    'valid row ok' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $validDatabase)['rows'][0]['integrity_check'], 'ok'],
    'valid first root table' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $validDatabase)['target_roots'][0]['type'], 'table'],
    'valid first root name' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $validDatabase)['target_roots'][0]['name'], 'wp_options'],
    'valid second root index' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $validDatabase)['target_roots'][1]['name'], 'wp_options_name'],
    'valid third root index' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $validDatabase)['target_roots'][2]['name'], 'wp_options_autoload'],
    'quick valid status ok' => [static fn (): mixed => $collect('PRAGMA quick_check(wp_options)', $validDatabase)['status'], 'ok'],
    'quick valid flag' => [static fn (): mixed => $collect('PRAGMA quick_check(wp_options)', $validDatabase)['quick'], true],
    'quick valid row ok' => [static fn (): mixed => $collect('PRAGMA quick_check(wp_options)', $validDatabase)['rows'][0]['quick_check'], 'ok'],
    'index beyond status blocked' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond)['status'], 'blocked'],
    'index beyond next blocked' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond)['next']['ready'], false],
    'index beyond blocking name' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond)['next']['blocking'][0], 'integrity_check'],
    'index beyond error count' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond)['current']['error_count'], 1],
    'index beyond message' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond)['errors'][0], 'sqlite_schema index wp_options_name rootpage 12 is beyond the database image'],
    'index beyond row message' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond)['rows'][0]['integrity_check'], 'sqlite_schema index wp_options_name rootpage 12 is beyond the database image'],
    'posts root beyond ignored for options' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $postsRootBeyond)['status'], 'ok'],
    'posts root beyond still global error' => [static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $postsRootBeyond)['errors'][0], 'sqlite_schema table wp_posts rootpage 12 is beyond the database image'],
    'options pointer mismatch blocked' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $optionsPointerMismatch)['status'], 'blocked'],
    'options pointer mismatch message' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $optionsPointerMismatch)['errors'][0], 'pointer-map type btree-page for page 4 does not match expected root-page'],
    'posts pointer mismatch ignored' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $postsPointerMismatch)['status'], 'ok'],
    'freelist mismatch ignored by table scope' => [static fn (): mixed => $collect('PRAGMA integrity_check(wp_options)', $freelistHeaderMismatch)['status'], 'ok'],
    'freelist mismatch still global error' => [static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $freelistHeaderMismatch)['errors'][0], 'freelist header count 9 does not match reachable freelist page count 1'],
    'page total includes roots only when clean' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 0, 10)['total'], 3],
    'page first current root' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 0, 2)['current']['name'], 'wp_options'],
    'page second next root' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 0, 2)['next']['name'], 'wp_options_name'],
    'page next offset after first slice' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 0, 2)['next_offset'], 2],
    'page incomplete first slice' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 0, 2)['complete'], false],
    'page second slice current index' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 2, 2)['current']['name'], 'wp_options_autoload'],
    'page second slice complete' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 2, 2)['complete'], true],
    'page blocked total includes error' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond, 0, 10)['total'], 4],
    'page blocked error kind' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond, 3, 2)['current']['kind'], 'integrity_check'],
    'page blocked error rootpage' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond, 3, 2)['current']['rootpage'], 12],
    'page blocked error complete' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $optionsIndexBeyond, 3, 2)['complete'], true],
    'page past tail count zero' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 8, 2)['count'], 0],
    'page past tail current null' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 8, 2)['current'], null],
    'page preserves schema' => [static fn (): mixed => $page('PRAGMA main.integrity_check(wp_options)', $validDatabase, 0, 1)['schema'], 'main'],
    'page preserves quoted schema' => [static fn (): mixed => $page('PRAGMA "main".quick_check(wp_options)', $validDatabase, 0, 1)['schema'], 'main'],
    'page preserves target' => [static fn (): mixed => $page('PRAGMA main.integrity_check(wp_options)', $validDatabase, 0, 1)['target'], 'wp_options'],
    'page preserves pragma' => [static fn (): mixed => $page('PRAGMA main.quick_check(wp_options)', $validDatabase, 0, 1)['pragma'], 'quick_check'],
    'page limit preserved' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 0, 1)['limit'], 1],
    'page count limited' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 0, 1)['count'], 1],
    'page row message' => [static fn (): mixed => $page('PRAGMA integrity_check(wp_options)', $validDatabase, 0, 1)['rows'][0]['message'], 'table wp_options rootpage 4'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pragma integrity table scope current next65 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pragma integrity table scope current next65 rejects malformed pragma'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityTableScopeYield::parse('PRAGMA integrity_check(2)'));
};

$tests['pragma integrity table scope current next65 rejects missing target'] = static function (TestRunner $t) use ($validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityTableScopeYield::collect($validDatabase, 'PRAGMA integrity_check(wp_missing)'));
};

$tests['pragma integrity table scope current next65 rejects negative offset'] = static function (TestRunner $t) use ($validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityTableScopeYield::page($validDatabase, 'PRAGMA integrity_check(wp_options)', -1, 1));
};

$tests['pragma integrity table scope current next65 rejects zero limit'] = static function (TestRunner $t) use ($validDatabase): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityTableScopeYield::page($validDatabase, 'PRAGMA integrity_check(wp_options)', 0, 0));
};

foreach (range(1, 24) as $index) {
    $sql = $index % 2 === 0 ? 'PRAGMA integrity_check(wp_options)' : 'PRAGMA quick_check(wp_options)';
    $tests['pragma integrity table scope current next65 repeated scoped root pagination ' . $index] = static function (TestRunner $t) use ($page, $validDatabase, $sql, $index): void {
        $slice = $page($sql, $validDatabase, ($index - 1) % 3, 1);
        $t->same(1, $slice['count']);
        $t->same('target_root', $slice['current']['kind']);
    };
}

return $tests;
