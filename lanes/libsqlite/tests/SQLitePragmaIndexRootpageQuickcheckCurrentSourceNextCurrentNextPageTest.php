<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize146 = 1024;
$record146 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog146 = static function (bool $archiveShadow = false) use ($record146): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record146('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record146('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 2),
        $record146('index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 3),
        $record146('table', 'wp_posts', 'wp_posts', 7, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_title TEXT)', 4),
        $record146('index', 'wp_posts_title', 'wp_posts', 8, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)', 5),
    ], [
        $record146('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record146('index', 'wp_options_temp_expr', 'wp_options', 10, 'CREATE INDEX wp_options_temp_expr ON wp_options(upper(option_name) COLLATE rtrim, autoload DESC)', 2),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record146('table', 'wp_options', 'wp_options', 11, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
        $record146('index', $archiveShadow ? 'wp_options_value_expr' : 'wp_options_archive_expr', 'wp_options', 12, "CREATE INDEX " . ($archiveShadow ? 'wp_options_value_expr' : 'wp_options_archive_expr') . " ON wp_options(json_extract(option_value, '$.legacy'), option_name COLLATE rtrim DESC)", 2),
    ]);

    return $catalog;
};

$header146 = static function (int $pageCount, int $largestRootPage) use ($pageSize146): string {
    $page = str_repeat("\0", $pageSize146);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize146), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$putPointer146 = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$schemaCell146 = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
$schemaRows146 = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name, autoload)'],
    ['index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name), autoload DESC)"],
    ['table', 'wp_posts', 'wp_posts', 7, 'CREATE TABLE wp_posts(ID integer primary key, post_title text)'],
    ['index', 'wp_posts_title', 'wp_posts', 8, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)'],
    ['table', 'wp_archive_marker', 'wp_archive_marker', 11, 'CREATE TABLE wp_archive_marker(id integer primary key)'],
    ['index', 'wp_options_archive_expr', 'wp_options', 12, "CREATE INDEX wp_options_archive_expr ON wp_options(json_extract(option_value, '$.legacy'), option_name COLLATE rtrim DESC)"],
];
$database146 = static function (array $pointerMapEntries, int $pageCount = 12, int $largestRootPage = 12, ?array $rows = null) use ($header146, $putPointer146, $schemaCell146, $schemaRows146, $pageSize146): string {
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell146($row, $index + 1), $rows ?? $schemaRows146, array_keys($rows ?? $schemaRows146)),
            $pageSize146,
            100,
            $header146($pageCount, $largestRootPage),
        ),
        2 => str_repeat("\0", $pageSize146),
    ];
    foreach ($pointerMapEntries as $entry) {
        $pages[2] = $putPointer146($pages[2], $entry[0], $entry[1], $entry[2]);
    }
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] ??= in_array($pageNumber, [5, 6, 8, 10, 12], true)
            ? SQLiteIndexLeafPage::assemble([], $pageSize146)
            : SQLiteTableLeafPage::assemble([], $pageSize146);
    }
    ksort($pages);

    return implode('', $pages);
};
$cleanPointers146 = [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [11, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [12, SQLitePointerMapEntry::ROOT_PAGE, 0],
];
$currentPointers146 = [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [11, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [12, SQLitePointerMapEntry::ROOT_PAGE, 0],
];
$blockedNextPointers146 = [
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [9, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [10, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [11, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [12, SQLitePointerMapEntry::ROOT_PAGE, 0],
];
$currentDatabase146 = $database146($currentPointers146);
$nextDatabase146 = $database146($cleanPointers146);
$blockedNextDatabase146 = $database146($blockedNextPointers146);
$limitedNextDatabase146 = $database146($cleanPointers146, 12, 4);
$beyondRows146 = $schemaRows146;
$beyondRows146[2] = ['index', 'wp_options_value_expr', 'wp_options', 14, $schemaRows146[2][4]];
$beyondNextDatabase146 = $database146($cleanPointers146, 12, 12, $beyondRows146);
$mutatedNextDatabase146 = $nextDatabase146;
$mutatedNextDatabase146[48] = "\x03";

$page146 = static fn (
    int $offset = 0,
    int $limit = 146,
    ?array $cursor = null,
    ?string $nextBytes = null,
    ?SQLiteAttachedSchemaCatalog $currentCatalog = null,
    ?SQLiteAttachedSchemaCatalog $nextCatalog = null,
    ?string $sql = null,
    string $quickSql = 'PRAGMA quick_check',
    bool $tableValued = false,
): array => SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext::currentNextPage(
    $currentCatalog ?? $catalog146(),
    $nextCatalog ?? $catalog146(),
    $sql ?? 'PRAGMA main.index_xinfo(wp_options_value_expr)',
    $currentDatabase146,
    $nextBytes ?? $nextDatabase146,
    $offset,
    $limit,
    $quickSql,
    $tableValued,
    $cursor,
);

$valueAt146 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases146 = [
    'status ok after repair' => [static fn (): array => $page146(), 'status', 'ok'],
    'default limit' => [static fn (): array => $page146(), 'limit', 146],
    'total current plus next rows' => [static fn (): array => $page146(), 'total', 10],
    'count current plus next rows' => [static fn (): array => $page146(), 'count', 10],
    'complete true' => [static fn (): array => $page146(), 'complete', true],
    'next null complete' => [static fn (): array => $page146(), 'next', null],
    'source id length' => [static fn (): array => ['len' => strlen($page146()['source_id'])], 'len', 64],
    'current source id length' => [static fn (): array => ['len' => strlen($page146()['current_source']['source_id'])], 'len', 64],
    'next source id length' => [static fn (): array => ['len' => strlen($page146()['next_source']['source_id'])], 'len', 64],
    'current database hash length' => [static fn (): array => ['len' => strlen($page146()['current_source']['database'])], 'len', 64],
    'next database hash length' => [static fn (): array => ['len' => strlen($page146()['next_source']['database'])], 'len', 64],
    'current catalog hash length' => [static fn (): array => ['len' => strlen($page146()['current_source']['catalog'])], 'len', 64],
    'normalized index sql' => [static fn (): array => $page146(), 'current_source.index_xinfo_sql', 'pragma main.index_xinfo(wp_options_value_expr)'],
    'normalized quick sql' => [static fn (): array => $page146(), 'current_source.quick_check_sql', 'pragma quick_check'],
    'source mode retained' => [static fn (): array => $page146(), 'current_source.source_mode', 'index_rootpage_quickcheck_current_source_next129'],
    'current source blocked' => [static fn (): array => $page146(), 'current_source.status', 'blocked'],
    'next source ok' => [static fn (): array => $page146(), 'next_source.status', 'ok'],
    'current target schema' => [static fn (): array => $page146(), 'current.target_schema', 'main'],
    'current target index' => [static fn (): array => $page146(), 'current.target_index', 'wp_options_value_expr'],
    'current target table' => [static fn (): array => $page146(), 'current.target_table', 'wp_options'],
    'current index xinfo count' => [static fn (): array => $page146(), 'current.index_xinfo', 4],
    'current quick count' => [static fn (): array => $page146(), 'current.quick_check', 1],
    'current quick errors' => [static fn (): array => $page146(), 'current.quick_check_errors', 1],
    'current target quick errors' => [static fn (): array => $page146(), 'current.target_quick_check_errors', 1],
    'current non target quick errors' => [static fn (): array => $page146(), 'current.non_target_quick_check_errors', 0],
    'current blockers' => [static fn (): array => $page146(), 'current.total_blockers', 1],
    'current quick first message' => [static fn (): array => $page146(), 'current.quick_messages.0', 'pointer-map type btree-page for page 6 does not match expected root-page'],
    'next index xinfo count' => [static fn (): array => $page146(), 'next_counts.index_xinfo', 4],
    'next quick count' => [static fn (): array => $page146(), 'next_counts.quick_check', 1],
    'next quick errors zero' => [static fn (): array => $page146(), 'next_counts.quick_check_errors', 0],
    'next blockers zero' => [static fn (): array => $page146(), 'next_counts.total_blockers', 0],
    'next ok message' => [static fn (): array => $page146(), 'next_counts.quick_messages.0', 'ok'],
    'delta quick errors' => [static fn (): array => $page146(), 'delta.quick_check_errors', -1],
    'delta target errors' => [static fn (): array => $page146(), 'delta.target_quick_check_errors', -1],
    'delta non target errors' => [static fn (): array => $page146(), 'delta.non_target_quick_check_errors', 0],
    'delta blockers' => [static fn (): array => $page146(), 'delta.total_blockers', -1],
    'delta cleared true' => [static fn (): array => $page146(), 'delta.cleared', true],
    'next state ready' => [static fn (): array => $page146(), 'next_state.ready', true],
    'next state blockers empty' => [static fn (): array => $page146(), 'next_state.blocking', []],
    'row0 side current' => [static fn (): array => $page146(), 'rows.0.side', 'current'],
    'row0 phase index' => [static fn (): array => $page146(), 'rows.0.phase', 'index_xinfo'],
    'row0 expression cid' => [static fn (): array => $page146(), 'rows.0.cid', -2],
    'row1 collation nocase' => [static fn (): array => $page146(), 'rows.1.coll', 'NOCASE'],
    'row2 desc autoload' => [static fn (): array => $page146(), 'rows.2.desc', 1],
    'row4 current quick side' => [static fn (): array => $page146(), 'rows.4.side', 'current'],
    'row4 current quick phase' => [static fn (): array => $page146(), 'rows.4.phase', 'quick_check'],
    'row4 target match true' => [static fn (): array => $page146(), 'rows.4.target_match', true],
    'row5 next starts' => [static fn (): array => $page146(), 'rows.5.side', 'next'],
    'row9 next ok quick' => [static fn (): array => $page146(), 'rows.9.message', 'ok'],
    'blocked next status' => [static fn (): array => $page146(0, 146, null, $blockedNextDatabase146), 'status', 'blocked'],
    'blocked next target blocker first' => [static fn (): array => $page146(0, 146, null, $blockedNextDatabase146), 'next_state.blocking.0', 'target_index_rootpage_quick_check'],
    'blocked next database blocker second' => [static fn (): array => $page146(0, 146, null, $blockedNextDatabase146), 'next_state.blocking.1', 'database_rootpage_quick_check'],
    'blocked next errors' => [static fn (): array => $page146(0, 146, null, $blockedNextDatabase146), 'next_counts.quick_check_errors', 2],
    'limited next blocked' => [static fn (): array => $page146(0, 146, null, $limitedNextDatabase146, null, null, null, 'PRAGMA quick_check(1)'), 'status', 'blocked'],
    'limited next only database blocker' => [static fn (): array => $page146(0, 146, null, $limitedNextDatabase146, null, null, null, 'PRAGMA quick_check(1)'), 'next_state.blocking.0', 'database_rootpage_quick_check'],
    'limited next message' => [static fn (): array => $page146(0, 146, null, $limitedNextDatabase146, null, null, null, 'PRAGMA quick_check(1)'), 'next_counts.quick_messages.0', 'largest root btree page 4 does not match sqlite_schema max rootpage 12'],
    'beyond next blocked' => [static fn (): array => $page146(0, 146, null, $beyondNextDatabase146), 'status', 'blocked'],
    'beyond next target blocker' => [static fn (): array => $page146(0, 146, null, $beyondNextDatabase146), 'next_state.blocking.0', 'target_index_rootpage_quick_check'],
    'beyond next quick message' => [static fn (): array => $page146(0, 146, null, $beyondNextDatabase146), 'next_counts.quick_messages.0', 'sqlite_schema index wp_options_value_expr rootpage 14 is beyond the database image'],
    'temp schema target' => [static fn (): array => $page146(0, 146, null, null, null, null, 'PRAGMA index_xinfo(wp_options_temp_expr)'), 'current.target_schema', 'temp'],
    'temp schema rtrim collation' => [static fn (): array => $page146(0, 146, null, null, null, null, 'PRAGMA index_xinfo(wp_options_temp_expr)'), 'rows.0.coll', 'RTRIM'],
    'archive table valued target' => [static fn (): array => $page146(0, 146, null, null, null, null, "pragma_index_xinfo('wp_options_archive_expr','archive')", 'PRAGMA quick_check', true), 'current.target_schema', 'archive'],
    'archive table valued source flag' => [static fn (): array => $page146(0, 146, null, null, null, null, "pragma_index_xinfo('wp_options_archive_expr','archive')", 'PRAGMA quick_check', true), 'current_source.table_valued', true],
    'archive table valued collation' => [static fn (): array => $page146(0, 146, null, null, null, null, "pragma_index_xinfo('wp_options_archive_expr','archive')", 'PRAGMA quick_check', true), 'rows.1.coll', 'RTRIM'],
    'shadowed catalog source changes' => [static fn (): array => ['changed' => $page146()['source_id'] !== $page146(0, 146, null, null, null, $catalog146(true))['source_id']], 'changed', true],
    'mutated next source changes' => [static fn (): array => ['changed' => $page146()['source_id'] !== $page146(0, 146, null, $mutatedNextDatabase146)['source_id']], 'changed', true],
    'page count four' => [static fn (): array => $page146(0, 4), 'count', 4],
    'page next offset four' => [static fn (): array => $page146(0, 4), 'next.offset', 4],
    'page second offset' => [static fn (): array => $page146(4, 4, $page146(0, 4)['next']), 'offset', 4],
    'page second first quick' => [static fn (): array => $page146(4, 4, $page146(0, 4)['next']), 'rows.0.phase', 'quick_check'],
    'page tail count' => [static fn (): array => $page146(8, 4, $page146(4, 4, $page146(0, 4)['next'])['next']), 'count', 2],
    'page past tail zero' => [static fn (): array => $page146(20, 4), 'count', 0],
];

$tests = [];
foreach ($cases146 as $name => [$factory, $path, $expected]) {
    $tests['pragma index rootpage quickcheck current source next146 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt146): void {
        $t->same($expected, $valueAt146($factory(), $path));
    };
}

$tests['pragma index rootpage quickcheck current source next146 accepts source-only cursor'] = static function (TestRunner $t) use ($page146): void {
    $first = $page146(0, 4);
    $second = $page146(4, 4, ['source_id' => $first['source_id']]);

    $t->same(4, $second['offset']);
    $t->same($first['source_id'], $second['source_id']);
    $t->same('quick_check', $second['rows'][0]['phase']);
};

$tests['pragma index rootpage quickcheck current source next146 rejects stale next cursor'] = static function (TestRunner $t) use ($page146, $blockedNextDatabase146): void {
    $first = $page146(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page146(4, 4, $first['next'], $blockedNextDatabase146));
};

$tests['pragma index rootpage quickcheck current source next146 rejects stale sql cursor'] = static function (TestRunner $t) use ($page146): void {
    $first = $page146(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page146(4, 4, $first['next'], null, null, null, 'PRAGMA index_xinfo(wp_options_name)'));
};

$tests['pragma index rootpage quickcheck current source next146 rejects stale quick cursor'] = static function (TestRunner $t) use ($page146): void {
    $first = $page146(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page146(4, 4, $first['next'], null, null, null, null, 'PRAGMA quick_check(1)'));
};

$tests['pragma index rootpage quickcheck current source next146 rejects stale offset cursor'] = static function (TestRunner $t) use ($page146): void {
    $first = $page146(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page146(5, 4, $first['next']));
};

$tests['pragma index rootpage quickcheck current source next146 rejects integrity check sql'] = static function (TestRunner $t) use ($catalog146, $currentDatabase146, $nextDatabase146): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext::currentNextPage($catalog146(), $catalog146(), 'PRAGMA index_xinfo(wp_options_value_expr)', $currentDatabase146, $nextDatabase146, 0, 146, 'PRAGMA integrity_check'));
};

$tests['pragma index rootpage quickcheck current source next146 rejects negative offset'] = static function (TestRunner $t) use ($page146): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page146(-1, 146));
};

$tests['pragma index rootpage quickcheck current source next146 rejects zero limit'] = static function (TestRunner $t) use ($page146): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => $page146(0, 0));
};

return $tests;
