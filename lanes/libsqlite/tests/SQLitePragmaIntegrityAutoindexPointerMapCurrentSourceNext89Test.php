<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$currentSource = '7b41e2554952baf690549d17891ca56b9a508ae3';
$nextSource = 'pragma-integrity-autoindex-pointermap-current-source-next89';

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

$database = static function (
    array $indexRows,
    array $pointerMapMutations = [],
    int $pageCount = 12,
    int $largestRoot = 9,
) use ($headerPage, $putPointerMapEntry, $pageSize): string {
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
    $schemaRows = array_merge([
        ['table', 'wp_options', 'wp_options', 4, $sql],
    ], $indexRows);

    $pointerMap = str_repeat("\0", $pageSize);
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $isRoot = in_array($pageNumber, [4, 5, 6, 7], true);
        $pointerMap = $putPointerMapEntry(
            $pointerMap,
            $pageNumber,
            $isRoot ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE,
            $isRoot ? 0 : 4,
        );
    }
    foreach ($pointerMapMutations as [$pageNumber, $type, $parent]) {
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, $type, $parent);
    }

    $cells = [];
    foreach ($schemaRows as $rowId => $values) {
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
$combined = $database([
    $validIndexes[0],
    ['index', 'sqlite_autoindex_wp_options_3', 'wp_options', 12, null],
    ['index', 'sqlite_autoindex_wp_options_5', 'wp_options', 8, null],
    ['index', 'sqlite_autoindex_wp_postmeta_1', 'wp_postmeta', 9, null],
], [[5, SQLitePointerMapEntry::BTREE_PAGE, 4], [7, SQLitePointerMapEntry::ROOT_PAGE, 3]], 12, 9);
$badRoot = $database([
    $validIndexes[0],
    ['index', 'sqlite_autoindex_wp_options_2', 'wp_options', 15, null],
    $validIndexes[2],
], [], 12, 7);

$page = static fn (int $offset = 0, int $limit = 89): array => SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page(
    $combined,
    $currentSource,
    $nextSource,
    $offset,
    $limit,
);
$collect = static fn (): array => SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::collect($combined, $currentSource, $nextSource);
$validPage = static fn (): array => SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($valid, $currentSource, $nextSource);
$badRootPage = static fn (): array => SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($badRoot, $currentSource, $nextSource);
$quick = static fn (): array => SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($combined, $currentSource, $nextSource, 0, 89, 'PRAGMA quick_check');

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count' && is_array($value) && !array_key_exists('count', $value)) {
            $value = count($value);
            continue;
        }
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$cases = [
    'valid status ok' => [$validPage, 'status', 'ok'],
    'valid total zero' => [$validPage, 'total', 0],
    'valid current autoindex errors zero' => [$validPage, 'current.autoindex_errors', 0],
    'valid next ready true' => [$validPage, 'next.ready', true],
    'valid next blocking zero' => [$validPage, 'next.blocking.count', 0],
    'status blocked' => [$page, 'status', 'blocked'],
    'default offset' => [$page, 'offset', 0],
    'default limit is next89' => [$page, 'limit', 89],
    'total rows' => [$page, 'total', 5],
    'page count' => [$page, 'count', 5],
    'complete true' => [$page, 'complete', true],
    'next offset null' => [$page, 'next_offset', null],
    'current source retained' => [$page, 'current.source', $currentSource],
    'next source retained' => [$page, 'next.source', $nextSource],
    'current autoindex errors' => [$page, 'current.autoindex_errors', 5],
    'current pointer map errors' => [$page, 'current.pointer_map_errors', 2],
    'current missing autoindexes' => [$page, 'current.missing_autoindexes', 1],
    'current unexpected autoindexes' => [$page, 'current.unexpected_autoindexes', 1],
    'current orphan autoindexes' => [$page, 'current.orphan_autoindexes', 1],
    'current rootpage errors zero' => [$page, 'current.rootpage_errors', 0],
    'next ready false' => [$page, 'next.ready', false],
    'next blocker count' => [$page, 'next.blocking.count', 4],
    'next blocker pointer map first' => [$page, 'next.blocking.0', 'autoindex_pointer_map_integrity'],
    'next blocker missing second' => [$page, 'next.blocking.1', 'missing_autoindex_schema'],
    'next blocker unexpected third' => [$page, 'next.blocking.2', 'unexpected_autoindex_schema'],
    'next blocker orphan fourth' => [$page, 'next.blocking.3', 'orphan_autoindex_schema'],
    'row0 current source' => [$page, 'rows.0.current_source', $currentSource],
    'row0 next source' => [$page, 'rows.0.next_source', $nextSource],
    'row0 source pointer map' => [$page, 'rows.0.source', 'autoindex_pointer_map'],
    'row0 index' => [$page, 'rows.0.index', 'sqlite_autoindex_wp_options_1'],
    'row0 table' => [$page, 'rows.0.table', 'wp_options'],
    'row0 rootpage' => [$page, 'rows.0.rootpage', 5],
    'row0 pointer map page' => [$page, 'rows.0.pointer_map_page', 2],
    'row0 entry page' => [$page, 'rows.0.rootpage_pointer_map_entry_page', 2],
    'row0 pointer type btree' => [$page, 'rows.0.rootpage_pointer_map_type', 'btree-page'],
    'row0 pointer parent' => [$page, 'rows.0.rootpage_pointer_map_parent', 4],
    'row0 next blocker' => [$page, 'rows.0.next_blocker', 'autoindex_pointer_map_integrity'],
    'row1 missing source' => [$page, 'rows.1.source', 'missing_autoindex'],
    'row1 missing index' => [$page, 'rows.1.index', 'sqlite_autoindex_wp_options_2'],
    'row1 missing root null' => [$page, 'rows.1.rootpage', null],
    'row1 pointer type null' => [$page, 'rows.1.rootpage_pointer_map_type', null],
    'row1 next blocker' => [$page, 'rows.1.next_blocker', 'missing_autoindex_schema'],
    'row2 source pointer map' => [$page, 'rows.2.source', 'autoindex_pointer_map'],
    'row2 index' => [$page, 'rows.2.index', 'sqlite_autoindex_wp_options_3'],
    'row2 rootpage' => [$page, 'rows.2.rootpage', 12],
    'row2 pointer type btree' => [$page, 'rows.2.rootpage_pointer_map_type', 'btree-page'],
    'row2 pointer parent' => [$page, 'rows.2.rootpage_pointer_map_parent', 4],
    'row3 unexpected source' => [$page, 'rows.3.source', 'unexpected_autoindex'],
    'row3 sequence' => [$page, 'rows.3.sequence', 5],
    'row3 rootpage' => [$page, 'rows.3.rootpage', 8],
    'row3 pointer type btree' => [$page, 'rows.3.rootpage_pointer_map_type', 'btree-page'],
    'row3 next blocker' => [$page, 'rows.3.next_blocker', 'unexpected_autoindex_schema'],
    'row4 orphan source' => [$page, 'rows.4.source', 'orphan_autoindex'],
    'row4 table' => [$page, 'rows.4.table', 'wp_postmeta'],
    'row4 rootpage' => [$page, 'rows.4.rootpage', 9],
    'row4 pointer parent' => [$page, 'rows.4.rootpage_pointer_map_parent', 4],
    'row4 next blocker' => [$page, 'rows.4.next_blocker', 'orphan_autoindex_schema'],
    'offset two starts row2' => [static fn (): array => $page(2, 2), 'rows.0.index', 'sqlite_autoindex_wp_options_3'],
    'offset two count' => [static fn (): array => $page(2, 2), 'count', 2],
    'offset two next' => [static fn (): array => $page(2, 2), 'next_offset', 4],
    'offset four tail count' => [static fn (): array => $page(4, 2), 'count', 1],
    'offset four complete' => [static fn (): array => $page(4, 2), 'complete', true],
    'collect count' => [$collect, 'count', 5],
    'collect first current source' => [$collect, '0.current_source', $currentSource],
    'collect third pointer type' => [$collect, '2.rootpage_pointer_map_type', 'btree-page'],
    'quick kind retained' => [$quick, 'rows.0.kind', 'quick_check'],
    'bad root status blocked' => [$badRootPage, 'status', 'blocked'],
    'bad root count' => [$badRootPage, 'current.rootpage_errors', 1],
    'bad root blocker' => [$badRootPage, 'next.blocking.0', 'autoindex_rootpage_integrity'],
    'bad root pointer entry null' => [$badRootPage, 'rows.0.rootpage_pointer_map_type', null],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity autoindex pointermap current source next89 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity autoindex pointermap current source next89 collect matches paged rows'] = static function (TestRunner $t) use ($combined, $currentSource, $nextSource): void {
    $t->same(SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::collect($combined, $currentSource, $nextSource), array_merge(
        SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($combined, $currentSource, $nextSource, 0, 2)['rows'],
        SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($combined, $currentSource, $nextSource, 2, 2)['rows'],
        SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($combined, $currentSource, $nextSource, 4, 2)['rows'],
    ));
};

$tests['pragma integrity autoindex pointermap current source next89 tail offset returns empty complete page'] = static function (TestRunner $t) use ($combined, $currentSource, $nextSource): void {
    $tail = SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($combined, $currentSource, $nextSource, 5, 89);
    $t->same(['count' => 0, 'total' => 5, 'next_offset' => null, 'complete' => true], ['count' => $tail['count'], 'total' => $tail['total'], 'next_offset' => $tail['next_offset'], 'complete' => $tail['complete']]);
};

$tests['pragma integrity autoindex pointermap current source next89 rejects negative offset'] = static function (TestRunner $t) use ($combined, $currentSource, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($combined, $currentSource, $nextSource, -1, 89));
};

$tests['pragma integrity autoindex pointermap current source next89 rejects zero limit'] = static function (TestRunner $t) use ($combined, $currentSource, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($combined, $currentSource, $nextSource, 0, 0));
};

$tests['pragma integrity autoindex pointermap current source next89 rejects missing current source'] = static function (TestRunner $t) use ($combined, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($combined, '', $nextSource));
};

$tests['pragma integrity autoindex pointermap current source next89 propagates pragma parser guard'] = static function (TestRunner $t) use ($combined, $currentSource, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityAutoindexPointerMapCurrentSourceYield::page($combined, $currentSource, $nextSource, 0, 89, 'PRAGMA table_info(wp_options)'));
};

return $tests;
