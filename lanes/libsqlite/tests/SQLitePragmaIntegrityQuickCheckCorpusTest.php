<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;

$tests = [];

$page = static function (int $pageSize = 512, int $pageCount = 1): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$database = static function (callable $mutate, int $pageSize = 512, int $pageCount = 1) use ($page): string {
    $pages = [$mutate($page($pageSize, $pageCount), $pageSize)];
    for ($i = 2; $i <= $pageCount; $i++) {
        $pages[] = str_repeat("\0", $pageSize);
    }

    return implode('', $pages);
};

$withPage = static function (string $bytes, int $pageNumber, string $pageImage, int $pageSize = 512): string {
    return substr_replace($bytes, $pageImage, ($pageNumber - 1) * $pageSize, $pageSize);
};

$putPointerMapEntry = static function (string $bytes, int $pageNumber, int $type, int $parent, int $pageSize = 512): string {
    $offset = 5 * ($pageNumber - 3);
    $entry = chr($type) . pack('N', $parent);

    return substr_replace($bytes, $entry, $pageSize + $offset, 5);
};

$validSingle = $database(static fn (string $first): string => $first);
$validFreelist = $withPage(
    $database(static function (string $first): string {
        $first = substr_replace($first, pack('N', 2), 32, 4);

        return substr_replace($first, pack('N', 2), 36, 4);
    }, 512, 3),
    2,
    SQLiteFreelistTrunkPage::assemble(null, [3], 512),
);
$validAutoVacuum = $database(static fn (string $first): string => substr_replace($first, pack('N', 3), 52, 4), 512, 4);
$validAutoVacuum = $putPointerMapEntry($validAutoVacuum, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$validAutoVacuum = $putPointerMapEntry($validAutoVacuum, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);

$cases = [
    'integrity ok returns one ok row' => ['PRAGMA integrity_check', $validSingle, ['ok']],
    'quick ok returns one ok row' => ['PRAGMA quick_check', $validSingle, ['ok']],
    'schema qualified integrity ok' => ['PRAGMA main.integrity_check', $validSingle, ['ok']],
    'parenthesized quick limit ok' => ['PRAGMA quick_check(1)', $validSingle, ['ok']],
    'equals integrity limit ok' => ['PRAGMA integrity_check = 2', $validSingle, ['ok']],
    'valid freelist trunk and leaf count ok' => ['PRAGMA integrity_check', $validFreelist, ['ok']],
    'valid auto vacuum pointer map ok' => ['PRAGMA integrity_check', $validAutoVacuum, ['ok']],
    'quick check skips pointer map walk' => ['PRAGMA quick_check', $putPointerMapEntry($validAutoVacuum, 4, 9, 3), ['ok']],
    'bad magic reports header error' => ['PRAGMA integrity_check', str_pad('not sqlite', 100, "\0"), ['Missing SQLite format 3 magic header']],
    'short header reports header error' => ['PRAGMA integrity_check', str_repeat("\0", 20), ['SQLite database header requires at least 100 bytes']],
    'invalid page size reports header error' => ['PRAGMA integrity_check', substr_replace($validSingle, pack('n', 513), 16, 2), ['Invalid SQLite page size: 513']],
    'short first page reports incomplete page image' => ['PRAGMA integrity_check', substr($validSingle, 0, 400), ['SQLite database reader requires a complete first page image']],
    'header page count mismatch reports file count' => ['PRAGMA integrity_check', substr_replace($validSingle, pack('N', 2), 28, 4), ['database header page count 2 does not match file page count 1']],
    'write version outside sqlite range reports error' => ['PRAGMA integrity_check', substr_replace($validSingle, "\x09", 18, 1), ['invalid schema write version 9']],
    'read version outside sqlite range reports error' => ['PRAGMA integrity_check', substr_replace($validSingle, "\x09", 19, 1), ['invalid schema read version 9']],
    'text encoding outside sqlite range reports error' => ['PRAGMA integrity_check', substr_replace($validSingle, pack('N', 9), 56, 4), ['invalid text encoding 9']],
    'nonzero freelist count without trunk reports error' => ['PRAGMA integrity_check', substr_replace($validSingle, pack('N', 1), 36, 4), ['freelist page count is nonzero but first trunk page is zero']],
    'first freelist trunk beyond image reports error' => ['PRAGMA integrity_check', substr_replace(substr_replace($validSingle, pack('N', 4), 32, 4), pack('N', 1), 36, 4), ['first freelist trunk page 4 is beyond the database image']],
    'largest root page beyond image reports error' => ['PRAGMA integrity_check', substr_replace($validSingle, pack('N', 4), 52, 4), ['largest root btree page 4 is beyond the database image']],
    'freelist leaf outside image reports error' => ['PRAGMA integrity_check', $withPage(substr_replace(substr_replace($database(static fn (string $first): string => $first, 512, 3), pack('N', 2), 32, 4), pack('N', 2), 36, 4), 2, SQLiteFreelistTrunkPage::assemble(null, [4], 512)), ['SQLite freelist leaf page is outside the database image']],
    'freelist leaf duplicate trunk reports error' => ['PRAGMA integrity_check', $withPage(substr_replace(substr_replace($database(static fn (string $first): string => $first, 512, 3), pack('N', 2), 32, 4), pack('N', 2), 36, 4), 2, SQLiteFreelistTrunkPage::assemble(null, [2], 512)), ['SQLite freelist leaf page duplicates its trunk page']],
    'freelist next trunk outside image reports error' => ['PRAGMA integrity_check', $withPage(substr_replace(substr_replace($database(static fn (string $first): string => $first, 512, 3), pack('N', 2), 32, 4), pack('N', 1), 36, 4), 2, SQLiteFreelistTrunkPage::assemble(4, [], 512)), ['SQLite freelist next trunk page is outside the database image']],
    'freelist header count mismatch reports reachable count' => ['PRAGMA integrity_check', $withPage(substr_replace(substr_replace($database(static fn (string $first): string => $first, 512, 3), pack('N', 2), 32, 4), pack('N', 3), 36, 4), 2, SQLiteFreelistTrunkPage::assemble(null, [3], 512)), ['freelist header count 3 does not match reachable freelist page count 2']],
    'freelist loop reports trunk loop' => ['PRAGMA integrity_check', $withPage(substr_replace(substr_replace($database(static fn (string $first): string => $first, 512, 3), pack('N', 2), 32, 4), pack('N', 1), 36, 4), 2, SQLiteFreelistTrunkPage::assemble(2, [], 512)), ['freelist trunk chain loops at page 2']],
    'integrity pointer map invalid type reports error' => ['PRAGMA integrity_check', $putPointerMapEntry($validAutoVacuum, 4, 9, 3), ['Invalid SQLite pointer-map entry type: 9']],
    'integrity pointer map parent outside image reports error' => ['PRAGMA integrity_check', $putPointerMapEntry($validAutoVacuum, 4, SQLitePointerMapEntry::BTREE_PAGE, 99), ['pointer-map parent page 99 for page 4 is beyond the database image']],
    'integrity limit truncates header errors' => ['PRAGMA integrity_check(2)', substr_replace(substr_replace(substr_replace($validSingle, "\x09", 18, 1), "\x09", 19, 1), pack('N', 9), 56, 4), ['invalid schema write version 9', 'invalid schema read version 9']],
    'quick limit truncates header errors' => ['PRAGMA quick_check = 1', substr_replace(substr_replace($validSingle, "\x09", 18, 1), "\x09", 19, 1), ['invalid schema write version 9']],
    'integrity rows use integrity column name' => ['PRAGMA integrity_check', substr_replace($validSingle, "\x09", 18, 1), ['invalid schema write version 9']],
    'quick rows use quick column name' => ['PRAGMA quick_check', substr_replace($validSingle, "\x09", 18, 1), ['invalid schema write version 9']],
    'trailing semicolon accepted' => [" PRAGMA integrity_check ;\n", $validSingle, ['ok']],
    'schema qualified quick limit accepted' => ['PRAGMA temp.quick_check(3)', substr_replace($validSingle, "\x09", 18, 1), ['invalid schema write version 9']],
];

foreach ($cases as $name => [$sql, $bytes, $expected]) {
    $tests['pragma integrity quickcheck corpus ' . $name] = static function (TestRunner $t) use ($sql, $bytes, $expected): void {
        $result = SQLitePragmaIntegrityCheck::execute($sql, $bytes);
        $column = $result['pragma'];
        $t->same($expected, array_map(static fn (array $row): string => $row[$column], $result['rows']));
    };
}

$tests['pragma integrity quickcheck corpus reports parsed pragma metadata'] = static function (TestRunner $t) use ($validSingle): void {
    $result = SQLitePragmaIntegrityCheck::execute('PRAGMA main.quick_check(7)', $validSingle);

    $t->same(['quick_check', 7, []], [$result['pragma'], $result['limit'], $result['errors']]);
};

$tests['pragma integrity quickcheck corpus rejects unsupported pragma shape'] = static function (TestRunner $t) use ($validSingle): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityCheck::execute('PRAGMA table_info(wp_options)', $validSingle));
};

return $tests;
