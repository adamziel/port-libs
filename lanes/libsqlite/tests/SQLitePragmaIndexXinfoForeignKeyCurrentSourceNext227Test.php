<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record227 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords227 = [
    $record227('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(post_id INTEGER NOT NULL, meta_key TEXT NOT NULL, title TEXT, UNIQUE(post_id, meta_key))', 1),
    $record227('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 3, null, 2),
    $record227('table', 'wp_blogs', 'wp_blogs', 4, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT)', 3),
    $record227('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        meta_value TEXT,
        post_id INTEGER NOT NULL,
        meta_key TEXT NOT NULL,
        site_id INTEGER NOT NULL,
        autoload TEXT,
        FOREIGN KEY(post_id, meta_key) REFERENCES wp_posts(post_id, meta_key) ON DELETE CASCADE,
        FOREIGN KEY(site_id) REFERENCES wp_blogs(blog_id) ON DELETE CASCADE
    )", 4),
    $record227('index', 'wp_postmeta_import_value_post_key', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_import_value_post_key ON wp_postmeta_import(meta_value, post_id, meta_key)', 5),
    $record227('index', 'wp_postmeta_import_autoload_site', 'wp_postmeta_import', 7, 'CREATE INDEX wp_postmeta_import_autoload_site ON wp_postmeta_import(autoload COLLATE NOCASE, site_id)', 6),
];

$nextRecords227 = [
    $currentRecords227[0],
    $currentRecords227[1],
    $currentRecords227[2],
    $currentRecords227[3],
    $record227('index', 'wp_postmeta_import_post_key_value', 'wp_postmeta_import', 8, 'CREATE INDEX wp_postmeta_import_post_key_value ON wp_postmeta_import(post_id, meta_key, meta_value)', 7),
    $record227('index', 'wp_postmeta_import_site_autoload', 'wp_postmeta_import', 9, 'CREATE INDEX wp_postmeta_import_site_autoload ON wp_postmeta_import(site_id, autoload COLLATE NOCASE)', 8),
];

$blockedNextRecords227 = [
    $currentRecords227[0],
    $currentRecords227[1],
    $currentRecords227[2],
    $currentRecords227[3],
    $currentRecords227[4],
    $record227('index', 'wp_postmeta_import_language_site', 'wp_postmeta_import', 9, 'CREATE INDEX wp_postmeta_import_language_site ON wp_postmeta_import(meta_key, autoload, site_id)', 8),
];

$partialOnlyRecords227 = [
    $currentRecords227[0],
    $currentRecords227[1],
    $currentRecords227[2],
    $currentRecords227[3],
    $record227('index', 'wp_postmeta_import_partial_suffix', 'wp_postmeta_import', 10, "CREATE INDEX wp_postmeta_import_partial_suffix ON wp_postmeta_import(meta_value, post_id, meta_key) WHERE autoload = 'yes'", 9),
];

$page227 = static fn (
    int $offset = 0,
    int $limit = 100,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page227(
    $currentRecords227,
    $nextRecords ?? $nextRecords227,
    'PRAGMA main.index_xinfo(wp_postmeta_import_value_post_key)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt227 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default227 = static fn (): array => $page227();
$blocked227 = static fn (): array => $page227(nextRecords: $blockedNextRecords227);
$currentSuffix227 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeySuffixRows227($currentRecords227);
$nextSuffix227 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeySuffixRows227($nextRecords227, 'next');
$blockedSuffix227 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeySuffixRows227($blockedNextRecords227, 'next');

$cases227 = [
    'status ok' => [$default227, 'status', 'ok'],
    'operation marker' => [$default227, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next227'],
    'source id length' => [static fn (): array => ['len' => strlen($page227()['source_id'])], 'len', 64],
    'offset default' => [$default227, 'offset', 0],
    'limit default' => [$default227, 'limit', 100],
    'dependency appended' => [$default227, 'dependencies.9', 'sqlite-pragma-foreign-key-child-index-leftmost-prefix'],
    'parent permutation source retained' => [$default227, 'current_source.foreign_key_parent_key_permutation_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_unique_column_order'],
    'suffix source current' => [$default227, 'current_source.foreign_key_child_suffix_index_source', 'pragma_foreign_key_list_child_columns_plus_pragma_index_xinfo_nonleading_terms'],
    'suffix source next' => [$default227, 'next_source.foreign_key_child_suffix_index_source', 'pragma_foreign_key_list_child_columns_plus_pragma_index_xinfo_nonleading_terms'],
    'current suffix rows' => [$default227, 'current.foreign_key_child_suffix_indexes.rows', 3],
    'current suffix blockers' => [$default227, 'current.foreign_key_child_suffix_indexes.suffix_child_index', 3],
    'current suffix foreign keys' => [$default227, 'current.foreign_key_child_suffix_indexes.foreign_keys', 2],
    'current suffix leading terms' => [$default227, 'current.foreign_key_child_suffix_indexes.leading_terms', 3],
    'current suffix max leading terms' => [$default227, 'current.foreign_key_child_suffix_indexes.max_leading_terms', 1],
    'next suffix rows repaired' => [$default227, 'next_counts.foreign_key_child_suffix_indexes.rows', 0],
    'next suffix blockers repaired' => [$default227, 'next_counts.foreign_key_child_suffix_indexes.suffix_child_index', 0],
    'delta suffix rows' => [$default227, 'delta.foreign_key_child_suffix_index_rows', -3],
    'delta suffix blockers' => [$default227, 'delta.foreign_key_child_suffix_index_blockers', -3],
    'delta suffix repaired true' => [$default227, 'delta.foreign_key_child_suffix_index_repaired', true],
    'delta suffix changed true' => [$default227, 'delta.foreign_key_child_suffix_index_changed', true],
    'current summary composite first' => [$default227, 'current_source.foreign_key_child_suffix_indexes.0', 'current:wp_postmeta_import#0.0:post_id->wp_posts.post_id:child=post_id,meta_key:wp_postmeta_import_value_post_key:columns=meta_value,post_id,meta_key:leading=meta_value:expected=0:actual=1:suffix_child_index'],
    'current summary composite second' => [$default227, 'current_source.foreign_key_child_suffix_indexes.1', 'current:wp_postmeta_import#0.1:meta_key->wp_posts.meta_key:child=post_id,meta_key:wp_postmeta_import_value_post_key:columns=meta_value,post_id,meta_key:leading=meta_value:expected=1:actual=2:suffix_child_index'],
    'current summary single' => [$default227, 'current_source.foreign_key_child_suffix_indexes.2', 'current:wp_postmeta_import#1.0:site_id->wp_blogs.blog_id:child=site_id:wp_postmeta_import_autoload_site:columns=autoload,site_id:leading=autoload:expected=0:actual=1:suffix_child_index'],
    'next summary empty' => [$default227, 'next_source.foreign_key_child_suffix_indexes', []],
    'blocked next rows' => [$blocked227, 'next_counts.foreign_key_child_suffix_indexes.rows', 3],
    'blocked next blockers' => [$blocked227, 'next_counts.foreign_key_child_suffix_indexes.suffix_child_index', 3],
    'blocked delta blockers zero' => [$blocked227, 'delta.foreign_key_child_suffix_index_blockers', 0],
    'blocked repaired false' => [$blocked227, 'delta.foreign_key_child_suffix_index_repaired', false],
    'helper current first kind' => [$currentSuffix227, '0.kind', 'foreign_key_child_suffix_index'],
    'helper current first index' => [$currentSuffix227, '0.suffix_child_index', 'wp_postmeta_import_value_post_key'],
    'helper current first leading column' => [$currentSuffix227, '0.leading_columns.0', 'meta_value'],
    'helper current first leading terms' => [$currentSuffix227, '0.leading_terms', 1],
    'helper current first actual position' => [$currentSuffix227, '0.actual_position', 1],
    'helper current second actual position' => [$currentSuffix227, '1.actual_position', 2],
    'helper current single column index' => [$currentSuffix227, '2.suffix_child_index', 'wp_postmeta_import_autoload_site'],
    'helper current single collation' => [$currentSuffix227, '2.suffix_child_index_collations.0', 'NOCASE'],
    'helper current single leading column' => [$currentSuffix227, '2.leading_columns.0', 'autoload'],
    'helper next repaired empty' => [static fn (): array => ['count' => count($nextSuffix227())], 'count', 0],
    'helper blocked row count' => [static fn (): array => ['count' => count($blockedSuffix227())], 'count', 3],
    'helper blocked single actual position' => [$blockedSuffix227, '2.actual_position', 2],
];

$tests = [];
foreach ($cases227 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child suffix current source next227 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt227): void {
        $t->same($expected, $valueAt227($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child suffix current source next227 paginates suffix rows'] = static function (TestRunner $t) use ($page227): void {
    $full = $page227();
    $suffixOffset = $full['total'] - 3;
    $first = $page227(0, $suffixOffset);
    $second = $page227($suffixOffset, 2, $first['next']);
    $third = $page227($suffixOffset + 2, 1, $second['next']);

    $t->same($suffixOffset, $first['count']);
    $t->same('foreign_key_child_suffix_index', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $suffixOffset], $first['next']);
    $t->same('post_id', $second['rows'][0]['from']);
    $t->same(1, $second['rows'][0]['actual_position']);
    $t->same('meta_key', $second['rows'][1]['from']);
    $t->same(2, $second['rows'][1]['actual_position']);
    $t->same('site_id', $third['rows'][0]['from']);
    $t->same(1, $third['rows'][0]['actual_position']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child suffix current source next227 ignores partial suffix indexes'] = static function (TestRunner $t) use ($partialOnlyRecords227): void {
    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeySuffixRows227($partialOnlyRecords227));
};

$tests['pragma index xinfo foreignkey child suffix current source next227 ignores leading prefix indexes'] = static function (TestRunner $t) use ($nextRecords227): void {
    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeySuffixRows227($nextRecords227));
};

$tests['pragma index xinfo foreignkey child suffix current source next227 reports two leading terms'] = static function (TestRunner $t) use ($record227): void {
    $records = [
        $record227('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a TEXT, b TEXT, UNIQUE(a, b))', 1),
        $record227('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record227('table', 'child', 'child', 4, 'CREATE TABLE child(region TEXT, status TEXT, a TEXT, b TEXT, FOREIGN KEY(a, b) REFERENCES parent(a, b))', 3),
        $record227('index', 'child_region_status_ab', 'child', 5, 'CREATE INDEX child_region_status_ab ON child(region, status, a, b)', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeySuffixRows227($records);
    $t->same(2, count($rows));
    $t->same(['region', 'status'], $rows[0]['leading_columns']);
    $t->same(2, $rows[0]['leading_terms']);
    $t->same([2, 3], array_column($rows, 'actual_position'));
};

$tests['pragma index xinfo foreignkey child suffix current source next227 rejects stale cursor'] = static function (TestRunner $t) use ($page227, $blockedNextRecords227): void {
    $first = $page227(0, 25);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page227(25, 1, $first['next'], $blockedNextRecords227));
};

$tests['pragma index xinfo foreignkey child suffix current source next227 rejects stale offset'] = static function (TestRunner $t) use ($page227): void {
    $first = $page227(0, 25);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page227(26, 1, $first['next']));
};

$tests['pragma index xinfo foreignkey child suffix current source next227 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childKeySuffixRows227([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey child suffix current source next227 rejects invalid bounds'] = static function (TestRunner $t) use ($page227): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page227(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page227(0, 0));
};

return $tests;
