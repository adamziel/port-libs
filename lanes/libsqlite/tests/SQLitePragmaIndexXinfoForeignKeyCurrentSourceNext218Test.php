<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record218 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords218 = [
    $record218('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL)', 1),
    $record218('table', 'wp_posts', 'wp_posts', 3, 'CREATE TABLE wp_posts(blog_id INTEGER NOT NULL, post_id INTEGER NOT NULL, PRIMARY KEY(blog_id, post_id)) WITHOUT ROWID', 2),
    $record218('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 4, null, 3),
    $record218('table', 'wp_import_edges', 'wp_import_edges', 5, "CREATE TABLE wp_import_edges(
        edge_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        loose_term INTEGER,
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
        FOREIGN KEY(blog_id, post_id) REFERENCES wp_posts(blog_id, post_id) ON UPDATE RESTRICT DEFERRABLE INITIALLY DEFERRED,
        FOREIGN KEY(loose_term) REFERENCES wp_terms(term_id) ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED
    )", 4),
    $record218('index', 'wp_import_edges_term', 'wp_import_edges', 6, 'CREATE INDEX wp_import_edges_term ON wp_import_edges(term_id)', 5),
    $record218('index', 'wp_import_edges_post', 'wp_import_edges', 7, 'CREATE INDEX wp_import_edges_post ON wp_import_edges(blog_id, post_id)', 6),
];

$nextRecords218 = [
    $currentRecords218[0],
    $currentRecords218[1],
    $currentRecords218[2],
    $record218('table', 'wp_import_edges', 'wp_import_edges', 5, "CREATE TABLE wp_import_edges(
        edge_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        loose_term INTEGER,
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE NO ACTION DEFERRABLE INITIALLY DEFERRED,
        FOREIGN KEY(blog_id, post_id) REFERENCES wp_posts(blog_id, post_id) ON UPDATE NO ACTION DEFERRABLE INITIALLY DEFERRED,
        FOREIGN KEY(loose_term) REFERENCES wp_terms(term_id) ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED
    )", 4),
    $currentRecords218[4],
    $currentRecords218[5],
];

$blockedNextRecords218 = [
    $currentRecords218[0],
    $currentRecords218[1],
    $currentRecords218[2],
    $record218('table', 'wp_import_edges', 'wp_import_edges', 5, "CREATE TABLE wp_import_edges(
        edge_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        loose_term INTEGER,
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE RESTRICT NOT DEFERRABLE,
        FOREIGN KEY(blog_id, post_id) REFERENCES wp_posts(blog_id, post_id) ON UPDATE RESTRICT DEFERRABLE INITIALLY DEFERRED
    )", 4),
    $currentRecords218[4],
    $currentRecords218[5],
];

$page218 = static fn (
    int $offset = 0,
    int $limit = 100,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page218(
    $currentRecords218,
    $nextRecords ?? $nextRecords218,
    'PRAGMA main.index_xinfo(wp_import_edges_post)',
    'PRAGMA main.foreign_key_list(wp_import_edges)',
    $offset,
    $limit,
    $resume,
);

$valueAt218 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default218 = static fn (): array => $page218();
$blocked218 = static fn (): array => $page218(nextRecords: $blockedNextRecords218);
$currentRestrict218 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::restrictTimingRows218($currentRecords218);
$nextRestrict218 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::restrictTimingRows218($nextRecords218, 'next');

$cases218 = [
    'status ok' => [$default218, 'status', 'ok'],
    'operation marker' => [$default218, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next218'],
    'source id length' => [static fn (): array => ['len' => strlen($page218()['source_id'])], 'len', 64],
    'offset default' => [$default218, 'offset', 0],
    'limit default' => [$default218, 'limit', 100],
    'dependency appended' => [$default218, 'dependencies.7', 'sqlite-pragma-foreign-key-restrict-deferral-timing'],
    'source current marker' => [$default218, 'current_source.foreign_key_restrict_timing_source', 'pragma_foreign_key_list_actions_plus_schema_deferral'],
    'source next marker' => [$default218, 'next_source.foreign_key_restrict_timing_source', 'pragma_foreign_key_list_actions_plus_schema_deferral'],
    'base action lookup retained' => [$default218, 'current.foreign_key_child_action_lookup.rows', 4],
    'current restrict rows' => [$default218, 'current.foreign_key_restrict_timing.rows', 3],
    'current restrict immediate' => [$default218, 'current.foreign_key_restrict_timing.restrict_immediate', 3],
    'current initially deferred rows' => [$default218, 'current.foreign_key_restrict_timing.initially_deferred', 3],
    'current delete restrict rows' => [$default218, 'current.foreign_key_restrict_timing.delete_restrict', 1],
    'current update restrict rows' => [$default218, 'current.foreign_key_restrict_timing.update_restrict', 2],
    'current composite restrict columns' => [$default218, 'current.foreign_key_restrict_timing.composite_columns', 1],
    'next restrict rows repaired' => [$default218, 'next_counts.foreign_key_restrict_timing.rows', 0],
    'delta rows negative' => [$default218, 'delta.foreign_key_restrict_timing_rows', -3],
    'delta immediate negative' => [$default218, 'delta.foreign_key_restrict_timing_immediate', -3],
    'delta repaired true' => [$default218, 'delta.foreign_key_restrict_timing_repaired', true],
    'delta changed true' => [$default218, 'delta.foreign_key_restrict_timing_changed', true],
    'total includes restrict rows' => [$default218, 'total', 34],
    'count complete' => [$default218, 'count', 34],
    'next complete null' => [$default218, 'next', null],
    'current summary delete restrict' => [$default218, 'current_source.foreign_key_restrict_timing.0', 'current:wp_import_edges#0.0:term_id->wp_terms.term_id:RESTRICT/NO ACTION:initially-deferred:delete:restrict_immediate'],
    'blocked next rows one composite' => [$blocked218, 'next_counts.foreign_key_restrict_timing.rows', 2],
    'blocked next delete ignored not deferrable' => [$blocked218, 'next_counts.foreign_key_restrict_timing.delete_restrict', 0],
    'blocked next update retained' => [$blocked218, 'next_counts.foreign_key_restrict_timing.update_restrict', 2],
    'blocked repaired false' => [$blocked218, 'delta.foreign_key_restrict_timing_repaired', false],
    'helper current first kind' => [$currentRestrict218, '0.kind', 'foreign_key_restrict_timing'],
    'helper current first status' => [$currentRestrict218, '0.status', 'restrict_immediate'],
    'helper current first deferred until commit false' => [$currentRestrict218, '0.deferred_until_commit', false],
    'helper current first action delete' => [$currentRestrict218, '0.restrict_actions.0', 'delete'],
    'helper current composite seq one' => [$currentRestrict218, '2.seq', 1],
    'helper current composite action update' => [$currentRestrict218, '2.restrict_actions.0', 'update'],
    'helper next repaired empty' => [static fn (): array => ['count' => count($nextRestrict218())], 'count', 0],
];

$tests = [];
foreach ($cases218 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey restrict timing current source next218 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt218): void {
        $t->same($expected, $valueAt218($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey restrict timing current source next218 paginates restrict timing rows'] = static function (TestRunner $t) use ($page218): void {
    $first = $page218(0, 31);
    $second = $page218(31, 2, $first['next']);
    $third = $page218(33, 2, $second['next']);

    $t->same(31, $first['count']);
    $t->same('foreign_key_restrict_timing', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 31], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('restrict_immediate', $second['rows'][1]['status']);
    $t->same(1, $third['count']);
    $t->same('update', $third['rows'][0]['restrict_actions'][0]);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey restrict timing current source next218 ignores deferred cascade without restrict'] = static function (TestRunner $t) use ($currentRestrict218): void {
    $rows = $currentRestrict218();

    $t->same(3, count($rows));
    $t->same(false, in_array('loose_term', array_column($rows, 'from'), true));
};

$tests['pragma index xinfo foreignkey restrict timing current source next218 rejects stale cursor'] = static function (TestRunner $t) use ($page218, $blockedNextRecords218): void {
    $first = $page218(0, 32);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page218(32, 2, $first['next'], $blockedNextRecords218));
};

$tests['pragma index xinfo foreignkey restrict timing current source next218 rejects stale offset'] = static function (TestRunner $t) use ($page218): void {
    $first = $page218(0, 32);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page218(33, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey restrict timing current source next218 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::restrictTimingRows218([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey restrict timing current source next218 rejects invalid bounds'] = static function (TestRunner $t) use ($page218): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page218(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page218(0, 0));
};

return $tests;
