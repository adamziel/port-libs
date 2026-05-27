<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityAutoindexYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;

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

$schemaRows = static function (array $indexRows = []): array {
    $sql = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL UNIQUE,
    autoload TEXT NOT NULL,
    option_value TEXT,
    option_hash TEXT GENERATED ALWAYS AS (lower(option_name)) STORED UNIQUE,
    CONSTRAINT autoload_option UNIQUE(autoload, option_name)
)
SQL;

    return array_merge([
        ['table', 'wp_options', 'wp_options', 4, $sql],
    ], $indexRows);
};

$database = static function (
    array $indexRows,
    array $pointerMapMutations = [],
    int $pageCount = 10,
    int $largestRoot = 7,
) use ($headerPage, $putPointerMapEntry, $schemaRows, $pageSize): string {
    $pointerMap = str_repeat("\0", $pageSize);
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $type = in_array($pageNumber, [4, 5, 6, 7], true) ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE;
        $parent = $type === SQLitePointerMapEntry::ROOT_PAGE ? 0 : 4;
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, $type, $parent);
    }
    foreach ($pointerMapMutations as [$pageNumber, $type, $parent]) {
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, $type, $parent);
    }

    $cells = [];
    foreach ($schemaRows($indexRows) as $rowId => $values) {
        $cells[] = SQLiteTableLeafCell::encode($rowId + 1, SQLiteRecord::encode($values));
    }

    $pages = [
        1 => SQLiteTableLeafPage::assemble($cells, $pageSize, 100, $headerPage($pageCount, $largestRoot)),
        2 => $pointerMap,
    ];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = SQLiteTableLeafPage::assemble([], $pageSize);
    }
    ksort($pages);

    return implode('', $pages);
};

$validIndexes = [
    ['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 5, null],
    ['index', 'sqlite_autoindex_wp_options_2', 'wp_options', 6, null],
    ['index', 'sqlite_autoindex_wp_options_3', 'wp_options', 7, null],
];

$valid = $database($validIndexes);
$missing = $database([$validIndexes[0], $validIndexes[2]]);
$unexpected = $database(array_merge($validIndexes, [['index', 'sqlite_autoindex_wp_options_4', 'wp_options', 8, null]]));
$orphan = $database(array_merge($validIndexes, [['index', 'sqlite_autoindex_wp_postmeta_1', 'wp_postmeta', 8, null]]));
$badRoot = $database([
    $validIndexes[0],
    ['index', 'sqlite_autoindex_wp_options_2', 'wp_options', 12, null],
    $validIndexes[2],
], [], 10, 7);
$badPointerType = $database($validIndexes, [[6, SQLitePointerMapEntry::BTREE_PAGE, 4]]);
$badPointerParent = $database($validIndexes, [[7, SQLitePointerMapEntry::ROOT_PAGE, 4]]);
$combined = $database([
    $validIndexes[0],
    ['index', 'sqlite_autoindex_wp_options_3', 'wp_options', 12, null],
    ['index', 'sqlite_autoindex_wp_options_5', 'wp_options', 8, null],
    ['index', 'sqlite_autoindex_wp_postmeta_1', 'wp_postmeta', 9, null],
], [[5, SQLitePointerMapEntry::BTREE_PAGE, 4], [7, SQLitePointerMapEntry::ROOT_PAGE, 3]], 12, 9);

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
    'valid page status' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($valid), 'status', 'ok'],
    'valid count is zero' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($valid), 'count', 0],
    'valid total is zero' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($valid), 'total', 0],
    'valid complete' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($valid), 'complete', true],
    'valid next null' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($valid), 'next_offset', null],
    'quick valid is also zero' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($valid, 0, 10, 'PRAGMA quick_check'), 'total', 0],
    'missing source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($missing), 'rows.0.source', 'missing_autoindex'],
    'missing kind' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($missing), 'rows.0.kind', 'integrity_check'],
    'missing table' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($missing), 'rows.0.table', 'wp_options'],
    'missing index' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($missing), 'rows.0.index', 'sqlite_autoindex_wp_options_2'],
    'missing sequence' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($missing), 'rows.0.sequence', 2],
    'missing root null' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($missing), 'rows.0.rootpage', null],
    'missing pointer map null' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($missing), 'rows.0.pointer_map_page', null],
    'missing message' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($missing), 'rows.0.message', 'sqlite_schema table wp_options missing expected autoindex sqlite_autoindex_wp_options_2'],
    'unexpected source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($unexpected), 'rows.0.source', 'unexpected_autoindex'],
    'unexpected index' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($unexpected), 'rows.0.index', 'sqlite_autoindex_wp_options_4'],
    'unexpected sequence' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($unexpected), 'rows.0.sequence', 4],
    'unexpected rootpage' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($unexpected), 'rows.0.rootpage', 8],
    'unexpected pointer map page' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($unexpected), 'rows.0.pointer_map_page', 2],
    'orphan source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($orphan), 'rows.0.source', 'orphan_autoindex'],
    'orphan table' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($orphan), 'rows.0.table', 'wp_postmeta'],
    'orphan message' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($orphan), 'rows.0.message', 'sqlite_schema autoindex sqlite_autoindex_wp_postmeta_1 references missing table wp_postmeta'],
    'bad root source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($badRoot), 'rows.0.source', 'autoindex_rootpage'],
    'bad rootpage' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($badRoot), 'rows.0.rootpage', 12],
    'bad root message' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($badRoot), 'rows.0.message', 'sqlite_schema autoindex sqlite_autoindex_wp_options_2 rootpage 12 is beyond the database image'],
    'bad pointer type source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($badPointerType), 'rows.0.source', 'autoindex_pointer_map'],
    'bad pointer type root' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($badPointerType), 'rows.0.rootpage', 6],
    'bad pointer type page' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($badPointerType), 'rows.0.pointer_map_page', 2],
    'bad pointer type message' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($badPointerType), 'rows.0.message', 'sqlite_schema autoindex sqlite_autoindex_wp_options_2 rootpage 6 pointer-map type btree-page does not match expected root-page'],
    'bad pointer parent root' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($badPointerParent), 'rows.0.rootpage', 7],
    'bad pointer parent message' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($badPointerParent), 'rows.0.message', 'sqlite_schema autoindex sqlite_autoindex_wp_options_3 rootpage 7 pointer-map parent 4 does not match expected parent 0'],
    'combined first page offset' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 0, 2), 'offset', 0],
    'combined first page limit' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 0, 2), 'limit', 2],
    'combined first page count' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 0, 2), 'count', 2],
    'combined first page total' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 0, 2), 'total', 5],
    'combined first next' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 0, 2), 'next_offset', 2],
    'combined first incomplete' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 0, 2), 'complete', false],
    'combined first row source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 0, 2), 'rows.0.source', 'autoindex_pointer_map'],
    'combined second row source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 0, 2), 'rows.1.source', 'missing_autoindex'],
    'combined second page offset' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 2, 2), 'offset', 2],
    'combined second page first source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 2, 2), 'rows.0.source', 'autoindex_pointer_map'],
    'combined second page second source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 2, 2), 'rows.1.source', 'unexpected_autoindex'],
    'combined tail count' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 4, 2), 'count', 1],
    'combined tail source' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 4, 2), 'rows.0.source', 'orphan_autoindex'],
    'combined tail complete' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 4, 2), 'complete', true],
    'combined tail next null' => [static fn (): array => SQLitePragmaIntegrityAutoindexYield::page($combined, 4, 2), 'next_offset', null],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity autoindex current next51 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity autoindex current next51 collect matches paged rows'] = static function (TestRunner $t) use ($combined): void {
    $t->same(SQLitePragmaIntegrityAutoindexYield::collect($combined), array_merge(
        SQLitePragmaIntegrityAutoindexYield::page($combined, 0, 2)['rows'],
        SQLitePragmaIntegrityAutoindexYield::page($combined, 2, 2)['rows'],
        SQLitePragmaIntegrityAutoindexYield::page($combined, 4, 2)['rows'],
    ));
};

$tests['pragma integrity autoindex current next51 tail offset returns empty complete page'] = static function (TestRunner $t) use ($combined): void {
    $page = SQLitePragmaIntegrityAutoindexYield::page($combined, 5, 2);
    $t->same(['count' => 0, 'total' => 5, 'next_offset' => null, 'complete' => true], ['count' => $page['count'], 'total' => $page['total'], 'next_offset' => $page['next_offset'], 'complete' => $page['complete']]);
};

$tests['pragma integrity autoindex current next51 quick check keeps quick kind'] = static function (TestRunner $t) use ($missing): void {
    $t->same('quick_check', SQLitePragmaIntegrityAutoindexYield::page($missing, 0, 2, 'PRAGMA quick_check')['rows'][0]['kind']);
};

$tests['pragma integrity autoindex current next51 rejects negative offset'] = static function (TestRunner $t) use ($valid): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexYield::page($valid, -1, 2));
};

$tests['pragma integrity autoindex current next51 rejects zero limit'] = static function (TestRunner $t) use ($valid): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexYield::page($valid, 0, 0));
};

$tests['pragma integrity autoindex current next51 propagates pragma parser guard'] = static function (TestRunner $t) use ($valid): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexYield::page($valid, 0, 2, 'PRAGMA table_info(wp_options)'));
};

$tests['pragma integrity autoindex current next51 non auto vacuum omits pointer map page'] = static function (TestRunner $t) use ($database, $validIndexes): void {
    $bytes = substr_replace($database($validIndexes, [[6, SQLitePointerMapEntry::BTREE_PAGE, 4]]), pack('N', 0), 52, 4);
    $t->same([], SQLitePragmaIntegrityAutoindexYield::collect($bytes));
};

$tests['pragma integrity autoindex current next51 positive zero rootpage reports nonpositive'] = static function (TestRunner $t) use ($database, $validIndexes): void {
    $indexes = [$validIndexes[0], ['index', 'sqlite_autoindex_wp_options_2', 'wp_options', 0, null], $validIndexes[2]];
    $row = SQLitePragmaIntegrityAutoindexYield::page($database($indexes))['rows'][0];
    $t->same('sqlite_schema autoindex sqlite_autoindex_wp_options_2 rootpage is not a positive btree page', $row['message']);
};

$tests['pragma integrity autoindex current next51 declared primary key autoindex sequence is expected'] = static function (TestRunner $t) use ($database): void {
    $indexes = [
        ['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 5, null],
        ['index', 'sqlite_autoindex_wp_options_2', 'wp_options', 6, null],
        ['index', 'sqlite_autoindex_wp_options_3', 'wp_options', 7, null],
    ];
    $t->same([], SQLitePragmaIntegrityAutoindexYield::collect($database($indexes)));
};

return $tests;
