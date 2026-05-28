<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$pageSize = 512;
$pageCount = 12;
$currentSource = '4a40be6d2187eb244ca9f64f681c230cd6749701';
$nextSource = 'pragma-integrity-pointermap-index-current-source-next85';

$database = static function () use ($pageSize, $pageCount): string {
    $first = str_repeat("\0", $pageSize);
    $first = substr_replace($first, "SQLite format 3\0", 0, 16);
    $first = substr_replace($first, pack('n', $pageSize), 16, 2);
    $first[18] = "\x01";
    $first[19] = "\x01";
    $first = substr_replace($first, pack('N', $pageCount), 28, 4);
    $first = substr_replace($first, pack('N', 0), 32, 4);
    $first = substr_replace($first, pack('N', 7), 52, 4);
    $first = substr_replace($first, pack('N', 1), 56, 4);

    $pointerMap = str_repeat("\0", $pageSize);
    $put = static function (int $pageNumber, int $type, int $parent) use (&$pointerMap): void {
        $pointerMap = substr_replace($pointerMap, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
    };
    $put(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $put(4, SQLitePointerMapEntry::BTREE_PAGE, 0);
    $put(5, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $put(6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    $put(7, SQLitePointerMapEntry::BTREE_PAGE, 0);
    $put(8, SQLitePointerMapEntry::OVERFLOW_PAGE, 7);
    $put(9, SQLitePointerMapEntry::FREE_PAGE, 0);
    $put(10, SQLitePointerMapEntry::BTREE_PAGE, 0);
    $put(11, SQLitePointerMapEntry::BTREE_PAGE, 0);
    $put(12, SQLitePointerMapEntry::BTREE_PAGE, 0);

    $pages = [$first, $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $page = str_repeat("\0", $pageSize);
        if (in_array($pageNumber, [4, 5, 7], true)) {
            $page[0] = "\x0a";
            $page = substr_replace($page, pack('n', 0), 3, 2);
            $page = substr_replace($page, pack('n', $pageSize), 5, 2);
            $page[7] = "\0";
        }
        $pages[] = $page;
    }

    return implode('', $pages);
};

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$records = [
    $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)', 1),
    $record('index', 'wp_options_autoload_idx', 'wp_options', 4, 'CREATE INDEX wp_options_autoload_idx ON wp_options(autoload, option_id)', 2),
    $record('index', 'wp_options_name_idx', 'wp_options', 7, 'CREATE INDEX wp_options_name_idx ON wp_options(option_name)', 3),
];

$bytes = $database();
$page = static fn (int $offset = 0, int $limit = 85): array => SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield::page(
    $bytes,
    $records,
    $currentSource,
    $nextSource,
    $offset,
    $limit,
);
$quick = static fn (): array => SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield::page($bytes, $records, $currentSource, $nextSource, 0, 85, 'PRAGMA quick_check');
$collect = static fn (): array => SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield::collect($bytes, $records, $currentSource, $nextSource);

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
    'status blocked' => [$page, 'status', 'blocked'],
    'default offset' => [$page, 'offset', 0],
    'default limit is current source next85' => [$page, 'limit', 85],
    'total rows' => [$page, 'total', 9],
    'page count' => [$page, 'count', 9],
    'complete' => [$page, 'complete', true],
    'next offset null' => [$page, 'next_offset', null],
    'current source retained' => [$page, 'current.source', $currentSource],
    'next source retained' => [$page, 'next.source', $nextSource],
    'current pointer map errors' => [$page, 'current.pointer_map_errors', 9],
    'current freelist errors' => [$page, 'current.freelist_errors', 0],
    'current index pointer map errors' => [$page, 'current.index_pointer_map_errors', 5],
    'current index roots' => [$page, 'current.index_roots', 2],
    'next ready false' => [$page, 'next.ready', false],
    'next blocker count' => [$page, 'next.blocking.count', 2],
    'next index blocker first' => [$page, 'next.blocking.0', 'index_pointer_map_integrity'],
    'next pointer map blocker second' => [$page, 'next.blocking.1', 'pointer_map_integrity'],
    'row0 current source' => [$page, 'rows.0.current_source', $currentSource],
    'row0 next source' => [$page, 'rows.0.next_source', $nextSource],
    'row0 source pointer map' => [$page, 'rows.0.source', 'pointer_map'],
    'row0 table root page' => [$page, 'rows.0.table', 'wp_options'],
    'row0 root kind index' => [$page, 'rows.0.root_kind', 'index'],
    'row0 root page' => [$page, 'rows.0.root_page', 4],
    'row0 index autoload root' => [$page, 'rows.0.index', 'wp_options_autoload_idx'],
    'row0 pointer map type btree' => [$page, 'rows.0.pointer_map_type', 'btree-page'],
    'row0 pointer map parent zero' => [$page, 'rows.0.pointer_map_parent', 0],
    'row1 index name root' => [$page, 'rows.1.index', 'wp_options_name_idx'],
    'row1 root kind index' => [$page, 'rows.1.root_kind', 'index'],
    'row1 root page' => [$page, 'rows.1.root_page', 7],
    'row1 pointer type btree' => [$page, 'rows.1.pointer_map_type', 'btree-page'],
    'row1 message parent invalid' => [$page, 'rows.1.message', 'pointer-map parent page 0 for btree-page page 7 is not valid'],
    'row2 free page has no root' => [$page, 'rows.2.index', null],
    'row2 free page' => [$page, 'rows.2.page', 9],
    'row2 free parent' => [$page, 'rows.2.pointer_map_parent', 0],
    'row2 pointer type' => [$page, 'rows.2.pointer_map_type', 'free-page'],
    'row3 non index btree no parent' => [$page, 'rows.3.index', null],
    'row3 page ten' => [$page, 'rows.3.page', 10],
    'row3 pointer type' => [$page, 'rows.3.pointer_map_type', 'btree-page'],
    'row3 pointer parent' => [$page, 'rows.3.pointer_map_parent', 0],
    'row4 non index btree no parent' => [$page, 'rows.4.index', null],
    'row4 page eleven' => [$page, 'rows.4.page', 11],
    'row4 message parent invalid' => [$page, 'rows.4.message', 'pointer-map parent page 0 for btree-page page 11 is not valid'],
    'row5 non index btree no parent' => [$page, 'rows.5.index', null],
    'row5 page twelve' => [$page, 'rows.5.page', 12],
    'row5 pointer type' => [$page, 'rows.5.pointer_map_type', 'btree-page'],
    'row5 pointer parent' => [$page, 'rows.5.pointer_map_parent', 0],
    'row6 autoload root mismatch' => [$page, 'rows.6.index', 'wp_options_autoload_idx'],
    'row6 autoload root mismatch page' => [$page, 'rows.6.page', 4],
    'row6 message root mismatch' => [$page, 'rows.6.message', 'pointer-map type btree-page for page 4 does not match expected root-page'],
    'row7 index child inherited from parent root' => [$page, 'rows.7.index', 'wp_options_autoload_idx'],
    'row7 child page' => [$page, 'rows.7.page', 5],
    'row7 child parent' => [$page, 'rows.7.pointer_map_parent', 4],
    'row8 second index root' => [$page, 'rows.8.index', 'wp_options_name_idx'],
    'row8 second index root page' => [$page, 'rows.8.root_page', 7],
    'offset four starts non index' => [static fn (): array => $page(4, 3), 'rows.0.index', null],
    'offset four count' => [static fn (): array => $page(4, 3), 'count', 3],
    'offset four next offset' => [static fn (): array => $page(4, 3), 'next_offset', 7],
    'offset seven incomplete false' => [static fn (): array => $page(7, 3), 'complete', true],
    'offset seven starts page five' => [static fn (): array => $page(7, 3), 'rows.0.page', 5],
    'quick skips deep pointer map' => [$quick, 'total', 0],
    'quick status ok' => [$quick, 'status', 'ok'],
    'quick ready true' => [$quick, 'next.ready', true],
    'collect count' => [$collect, 'count', 9],
    'collect first source' => [$collect, '0.current_source', $currentSource],
    'collect second index' => [$collect, '1.index', 'wp_options_name_idx'],
    'collect sixth index null' => [$collect, '5.index', null],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity pointermap index current source next85 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity pointermap index current source next85 tail offset returns complete empty page'] = static function (TestRunner $t) use ($bytes, $records, $currentSource, $nextSource): void {
    $tail = SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield::page($bytes, $records, $currentSource, $nextSource, 9, 85);
    $t->same(['count' => 0, 'total' => 9, 'next_offset' => null, 'complete' => true], ['count' => $tail['count'], 'total' => $tail['total'], 'next_offset' => $tail['next_offset'], 'complete' => $tail['complete']]);
};

$tests['pragma integrity pointermap index current source next85 rejects negative offset'] = static function (TestRunner $t) use ($bytes, $records, $currentSource, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield::page($bytes, $records, $currentSource, $nextSource, -1, 85));
};

$tests['pragma integrity pointermap index current source next85 rejects zero limit'] = static function (TestRunner $t) use ($bytes, $records, $currentSource, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield::page($bytes, $records, $currentSource, $nextSource, 0, 0));
};

$tests['pragma integrity pointermap index current source next85 rejects missing current source'] = static function (TestRunner $t) use ($bytes, $records, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield::page($bytes, $records, '', $nextSource));
};

$tests['pragma integrity pointermap index current source next85 propagates pragma parser guard'] = static function (TestRunner $t) use ($bytes, $records, $currentSource, $nextSource): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield::page($bytes, $records, $currentSource, $nextSource, 0, 85, 'PRAGMA index_list(wp_options)'));
};

return $tests;
