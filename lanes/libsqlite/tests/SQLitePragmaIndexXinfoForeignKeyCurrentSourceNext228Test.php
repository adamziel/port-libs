<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record228 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords228 = [
    $record228('table', 'wp_posts_parent', 'wp_posts_parent', 2, 'CREATE TABLE wp_posts_parent(site_id INTEGER NOT NULL, post_name TEXT COLLATE NOCASE NOT NULL, post_type TEXT COLLATE RTRIM NOT NULL)', 1),
    $record228('index', 'wp_posts_parent_name_desc_unique', 'wp_posts_parent', 3, 'CREATE UNIQUE INDEX wp_posts_parent_name_desc_unique ON wp_posts_parent(post_name COLLATE NOCASE DESC)', 2),
    $record228('index', 'wp_posts_parent_site_name_desc_unique', 'wp_posts_parent', 4, 'CREATE UNIQUE INDEX wp_posts_parent_site_name_desc_unique ON wp_posts_parent(site_id DESC, post_name COLLATE NOCASE DESC)', 3),
    $record228('index', 'wp_posts_parent_type_desc_unique', 'wp_posts_parent', 5, 'CREATE UNIQUE INDEX wp_posts_parent_type_desc_unique ON wp_posts_parent(post_type COLLATE RTRIM DESC)', 4),
    $record228('table', 'wp_import_postmeta', 'wp_import_postmeta', 6, "CREATE TABLE wp_import_postmeta(
        meta_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        post_name TEXT NOT NULL,
        post_type TEXT NOT NULL,
        meta_key TEXT NOT NULL,
        FOREIGN KEY(post_name) REFERENCES wp_posts_parent(post_name) ON UPDATE CASCADE,
        FOREIGN KEY(site_id, post_name) REFERENCES wp_posts_parent(site_id, post_name) ON DELETE CASCADE,
        FOREIGN KEY(post_type) REFERENCES wp_posts_parent(post_type) ON DELETE RESTRICT
    )", 5),
];

$nextRecords228 = [
    $currentRecords228[0],
    $record228('index', 'wp_posts_parent_name_asc_unique', 'wp_posts_parent', 7, 'CREATE UNIQUE INDEX wp_posts_parent_name_asc_unique ON wp_posts_parent(post_name COLLATE NOCASE ASC)', 6),
    $record228('index', 'wp_posts_parent_site_name_asc_unique', 'wp_posts_parent', 8, 'CREATE UNIQUE INDEX wp_posts_parent_site_name_asc_unique ON wp_posts_parent(site_id ASC, post_name COLLATE NOCASE ASC)', 7),
    $record228('index', 'wp_posts_parent_type_asc_unique', 'wp_posts_parent', 9, 'CREATE UNIQUE INDEX wp_posts_parent_type_asc_unique ON wp_posts_parent(post_type COLLATE RTRIM ASC)', 8),
    $currentRecords228[4],
];

$missingNextRecords228 = [
    $currentRecords228[0],
    $record228('index', 'wp_posts_parent_site_only_unique', 'wp_posts_parent', 10, 'CREATE UNIQUE INDEX wp_posts_parent_site_only_unique ON wp_posts_parent(site_id DESC)', 6),
    $currentRecords228[4],
];

$page228 = static fn (
    int $offset = 0,
    int $limit = 120,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page228(
    $currentRecords228,
    $nextRecords ?? $nextRecords228,
    'PRAGMA main.index_xinfo(wp_posts_parent_site_name_desc_unique)',
    'PRAGMA main.foreign_key_list(wp_import_postmeta)',
    $offset,
    $limit,
    $resume,
);

$valueAt228 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default228 = static fn (): array => $page228();
$blocked228 = static fn (): array => $page228(nextRecords: $missingNextRecords228);
$currentSort228 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeySortOrderRows228($currentRecords228);
$nextSort228 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeySortOrderRows228($nextRecords228, 'next');

$cases228 = [
    'status ok' => [$default228, 'status', 'ok'],
    'operation marker' => [$default228, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next228'],
    'source id length' => [static fn (): array => ['len' => strlen($page228()['source_id'])], 'len', 64],
    'offset default' => [$default228, 'offset', 0],
    'limit default' => [$default228, 'limit', 120],
    'dependency appended' => [$default228, 'dependencies.9', 'sqlite-pragma-foreign-key-parent-sort-order-desc-compatible'],
    'base collation retained' => [$default228, 'current.foreign_key_parent_key_collation.rows', 4],
    'sort source current' => [$default228, 'current_source.foreign_key_parent_key_sort_order_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_flags'],
    'sort source next' => [$default228, 'next_source.foreign_key_parent_key_sort_order_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_desc_flags'],
    'current rows' => [$default228, 'current.foreign_key_parent_key_sort_order.rows', 4],
    'current ok rows' => [$default228, 'current.foreign_key_parent_key_sort_order.ok', 4],
    'current missing rows zero' => [$default228, 'current.foreign_key_parent_key_sort_order.missing_parent_unique_index', 0],
    'current desc columns' => [$default228, 'current.foreign_key_parent_key_sort_order.desc_columns', 4],
    'current asc columns zero' => [$default228, 'current.foreign_key_parent_key_sort_order.asc_columns', 0],
    'current composite column count' => [$default228, 'current.foreign_key_parent_key_sort_order.composite_columns', 1],
    'next rows' => [$default228, 'next_counts.foreign_key_parent_key_sort_order.rows', 4],
    'next ok rows' => [$default228, 'next_counts.foreign_key_parent_key_sort_order.ok', 4],
    'next desc columns zero' => [$default228, 'next_counts.foreign_key_parent_key_sort_order.desc_columns', 0],
    'next asc columns' => [$default228, 'next_counts.foreign_key_parent_key_sort_order.asc_columns', 4],
    'delta rows unchanged' => [$default228, 'delta.foreign_key_parent_key_sort_order_rows', 0],
    'delta desc negative' => [$default228, 'delta.foreign_key_parent_key_sort_order_desc_columns', -4],
    'delta repaired false' => [$default228, 'delta.foreign_key_parent_key_sort_order_repaired', false],
    'delta changed true' => [$default228, 'delta.foreign_key_parent_key_sort_order_changed', true],
    'complete no next page' => [$default228, 'next', null],
    'current summary first desc' => [$default228, 'current_source.foreign_key_parent_key_sort_order.0', 'current:wp_import_postmeta#0.0:post_name->wp_posts_parent.post_name:wp_posts_parent_name_desc_unique:DESC:ok'],
    'current summary composite first desc' => [$default228, 'current_source.foreign_key_parent_key_sort_order.1', 'current:wp_import_postmeta#1.0:site_id->wp_posts_parent.site_id:wp_posts_parent_site_name_desc_unique:DESC:ok'],
    'current summary composite second desc' => [$default228, 'current_source.foreign_key_parent_key_sort_order.2', 'current:wp_import_postmeta#1.1:post_name->wp_posts_parent.post_name:wp_posts_parent_site_name_desc_unique:DESC:ok'],
    'next summary first asc' => [$default228, 'next_source.foreign_key_parent_key_sort_order.0', 'next:wp_import_postmeta#0.0:post_name->wp_posts_parent.post_name:wp_posts_parent_name_asc_unique:ASC:ok'],
    'blocked missing rows' => [$blocked228, 'next_counts.foreign_key_parent_key_sort_order.missing_parent_unique_index', 4],
    'blocked ok zero' => [$blocked228, 'next_counts.foreign_key_parent_key_sort_order.ok', 0],
    'blocked repaired false' => [$blocked228, 'delta.foreign_key_parent_key_sort_order_repaired', false],
    'helper current first kind' => [$currentSort228, '0.kind', 'foreign_key_parent_key_sort_order'],
    'helper current first status' => [$currentSort228, '0.status', 'ok'],
    'helper current first index' => [$currentSort228, '0.parent_unique_index', 'wp_posts_parent_name_desc_unique'],
    'helper current first desc true' => [$currentSort228, '0.index_column_desc', true],
    'helper current first ignored true' => [$currentSort228, '0.sort_order_ignored_for_fk', true],
    'helper current first desc column' => [$currentSort228, '0.desc_columns.0', 'post_name'],
    'helper current composite first desc column' => [$currentSort228, '1.desc_columns.0', 'site_id'],
    'helper current composite second desc column' => [$currentSort228, '2.desc_columns.1', 'post_name'],
    'helper current path rtrim desc' => [$currentSort228, '3.index_column_collation', 'RTRIM'],
    'helper next first phase' => [$nextSort228, '0.phase', 'next'],
    'helper next first asc false' => [$nextSort228, '0.index_column_desc', false],
    'helper next first desc columns empty' => [$nextSort228, '0.desc_columns', []],
];

$tests = [];
foreach ($cases228 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent sort order current source next228 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt228): void {
        $t->same($expected, $valueAt228($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent sort order current source next228 paginates sort rows'] = static function (TestRunner $t) use ($page228): void {
    $full = $page228();
    $baseCount = $full['total'] - 8;
    $first = $page228(0, $baseCount);
    $second = $page228($baseCount, 4, $first['next']);
    $third = $page228($baseCount + 4, 4, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_parent_key_sort_order', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same(true, $second['rows'][0]['index_column_desc']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(false, $third['rows'][3]['index_column_desc']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent sort order current source next228 treats desc unique as valid parent key'] = static function (TestRunner $t) use ($currentSort228): void {
    $rows = $currentSort228();

    $t->same(4, count($rows));
    $t->same(['ok'], array_values(array_unique(array_column($rows, 'status'))));
    $t->same([true], array_values(array_unique(array_column($rows, 'sort_order_ignored_for_fk'))));
};

$tests['pragma index xinfo foreignkey parent sort order current source next228 reports missing unique index separately'] = static function (TestRunner $t) use ($record228): void {
    $records = [
        $record228('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT COLLATE NOCASE)', 1),
        $record228('index', 'parent_other_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_other_unique ON parent(rowid DESC)', 2),
        $record228('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeySortOrderRows228($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['parent_unique_index']);
};

$tests['pragma index xinfo foreignkey parent sort order current source next228 accepts mixed asc desc composite key'] = static function (TestRunner $t) use ($record228): void {
    $records = [
        $record228('table', 'parent', 'parent', 2, 'CREATE TABLE parent(site_id INTEGER, slug TEXT COLLATE NOCASE)', 1),
        $record228('index', 'parent_site_slug_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_site_slug_unique ON parent(site_id ASC, slug COLLATE NOCASE DESC)', 2),
        $record228('table', 'child', 'child', 4, 'CREATE TABLE child(site_id INTEGER, slug TEXT, FOREIGN KEY(site_id, slug) REFERENCES parent(site_id, slug))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeySortOrderRows228($records);
    $t->same(2, count($rows));
    $t->same(false, $rows[0]['index_column_desc']);
    $t->same(true, $rows[1]['index_column_desc']);
    $t->same(['slug'], $rows[0]['desc_columns']);
};

$tests['pragma index xinfo foreignkey parent sort order current source next228 rejects stale cursor'] = static function (TestRunner $t) use ($page228, $missingNextRecords228): void {
    $full = $page228();
    $first = $page228(0, $full['total'] - 8);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page228($full['total'] - 8, 4, $first['next'], $missingNextRecords228));
};

$tests['pragma index xinfo foreignkey parent sort order current source next228 rejects stale offset'] = static function (TestRunner $t) use ($page228): void {
    $full = $page228();
    $first = $page228(0, $full['total'] - 8);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page228($full['total'] - 7, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey parent sort order current source next228 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentKeySortOrderRows228([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey parent sort order current source next228 rejects invalid bounds'] = static function (TestRunner $t) use ($page228): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page228(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page228(0, 0));
};

return $tests;
