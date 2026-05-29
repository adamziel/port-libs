<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record241 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords241 = [
    $record241('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, guid TEXT COLLATE NOCASE NOT NULL)', 1),
    $record241('index', 'wp_posts_guid_unique', 'wp_posts', 3, 'CREATE UNIQUE INDEX wp_posts_guid_unique ON wp_posts(guid COLLATE NOCASE)', 2),
    $record241('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(site_id INTEGER NOT NULL, slug TEXT COLLATE NOCASE NOT NULL, name TEXT, PRIMARY KEY(site_id, slug))', 3),
    $record241('table', 'wp_comment_import', 'wp_comment_import', 5, "CREATE TABLE wp_comment_import(
        comment_id INTEGER PRIMARY KEY,
        owner_id INTEGER REFERENCES wp_posts ON UPDATE CASCADE,
        term_site INTEGER NOT NULL,
        term_slug TEXT COLLATE NOCASE NOT NULL,
        explicit_guid TEXT COLLATE NOCASE REFERENCES wp_posts(guid),
        FOREIGN KEY(term_site, term_slug) REFERENCES wp_terms ON DELETE CASCADE
    )", 4),
];

$nextRecords241 = [
    $currentRecords241[0],
    $currentRecords241[1],
    $currentRecords241[2],
    $record241('table', 'wp_comment_import', 'wp_comment_import', 5, "CREATE TABLE wp_comment_import(
        comment_id INTEGER PRIMARY KEY,
        owner_id INTEGER REFERENCES wp_posts(ID) ON UPDATE CASCADE,
        term_site INTEGER NOT NULL,
        term_slug TEXT COLLATE NOCASE NOT NULL,
        explicit_guid TEXT COLLATE NOCASE REFERENCES wp_posts(guid),
        FOREIGN KEY(term_site, term_slug) REFERENCES wp_terms(site_id, slug) ON DELETE CASCADE
    )", 4),
];

$page241 = static fn (
    int $offset = 0,
    int $limit = 220,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page241(
    $currentRecords241,
    $nextRecords ?? $nextRecords241,
    'PRAGMA main.index_xinfo(wp_posts_guid_unique)',
    'PRAGMA main.foreign_key_list(wp_comment_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt241 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default241 = static fn (): array => $page241();
$currentImplicit241 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentReferenceRows241($currentRecords241);
$nextImplicit241 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentReferenceRows241($nextRecords241, 'next');

$cases241 = [
    'status ok' => [$default241, 'status', 'ok'],
    'operation marker' => [$default241, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next241'],
    'source id length' => [static fn (): array => ['len' => strlen($page241()['source_id'])], 'len', 64],
    'offset default' => [$default241, 'offset', 0],
    'limit default' => [$default241, 'limit', 220],
    'dependency appended' => [$default241, 'dependencies.13', 'sqlite-pragma-foreign-key-implicit-parent-primary-key-resolution'],
    'base desc retained' => [$default241, 'current.foreign_key_parent_descending_key.rows', 4],
    'implicit source current' => [$default241, 'current_source.foreign_key_implicit_parent_reference_source', 'raw_pragma_foreign_key_list_null_to_plus_parent_primary_key_resolution'],
    'implicit source next' => [$default241, 'next_source.foreign_key_implicit_parent_reference_source', 'raw_pragma_foreign_key_list_null_to_plus_parent_primary_key_resolution'],
    'current rows' => [$default241, 'current.foreign_key_implicit_parent_references.rows', 4],
    'current implicit rows' => [$default241, 'current.foreign_key_implicit_parent_references.implicit', 3],
    'current explicit rows' => [$default241, 'current.foreign_key_implicit_parent_references.explicit', 1],
    'current resolved rows' => [$default241, 'current.foreign_key_implicit_parent_references.resolved', 4],
    'current implicit ok rows' => [$default241, 'current.foreign_key_implicit_parent_references.ok_implicit_parent_primary_key', 3],
    'current missing rows zero' => [$default241, 'current.foreign_key_implicit_parent_references.missing_implicit_parent_primary_key', 0],
    'current blocked zero' => [$default241, 'current.foreign_key_implicit_parent_references.blocked', 0],
    'next rows' => [$default241, 'next_counts.foreign_key_implicit_parent_references.rows', 4],
    'next implicit rows zero' => [$default241, 'next_counts.foreign_key_implicit_parent_references.implicit', 0],
    'next explicit rows' => [$default241, 'next_counts.foreign_key_implicit_parent_references.explicit', 4],
    'next implicit ok zero' => [$default241, 'next_counts.foreign_key_implicit_parent_references.ok_implicit_parent_primary_key', 0],
    'next resolved rows' => [$default241, 'next_counts.foreign_key_implicit_parent_references.resolved', 4],
    'delta rows unchanged' => [$default241, 'delta.foreign_key_implicit_parent_reference_rows', 0],
    'delta blockers unchanged' => [$default241, 'delta.foreign_key_implicit_parent_reference_blockers', 0],
    'delta repaired false' => [$default241, 'delta.foreign_key_implicit_parent_reference_repaired', false],
    'delta changed true' => [$default241, 'delta.foreign_key_implicit_parent_reference_changed', true],
    'total includes implicit rows' => [$default241, 'total', 86],
    'count complete' => [$default241, 'count', 86],
    'next complete null' => [$default241, 'next', null],
    'current summary owner implicit' => [$default241, 'current_source.foreign_key_implicit_parent_references.0', 'current:wp_comment_import#0.0:owner_id->wp_posts.raw=:resolved=ID:pk=ID:ok_implicit_parent_primary_key'],
    'current summary explicit guid' => [$default241, 'current_source.foreign_key_implicit_parent_references.1', 'current:wp_comment_import#1.0:explicit_guid->wp_posts.raw=guid:resolved=guid:pk=ID:explicit_parent_column'],
    'current summary composite first' => [$default241, 'current_source.foreign_key_implicit_parent_references.2', 'current:wp_comment_import#2.0:term_site->wp_terms.raw=:resolved=site_id:pk=site_id,slug:ok_implicit_parent_primary_key'],
    'current summary composite second' => [$default241, 'current_source.foreign_key_implicit_parent_references.3', 'current:wp_comment_import#2.1:term_slug->wp_terms.raw=:resolved=slug:pk=site_id,slug:ok_implicit_parent_primary_key'],
    'next summary owner explicit' => [$default241, 'next_source.foreign_key_implicit_parent_references.0', 'next:wp_comment_import#0.0:owner_id->wp_posts.raw=ID:resolved=ID:pk=ID:explicit_parent_column'],
    'first appended kind' => [$default241, 'rows.78.kind', 'foreign_key_implicit_parent_reference'],
    'first appended raw to null' => [$default241, 'rows.78.raw_to', null],
    'first appended resolved to pk' => [$default241, 'rows.78.resolved_to', 'ID'],
    'first appended status' => [$default241, 'rows.78.status', 'ok_implicit_parent_primary_key'],
    'first appended pk complete' => [$default241, 'rows.78.parent_primary_key_complete', true],
    'explicit appended status' => [$default241, 'rows.79.status', 'explicit_parent_column'],
    'explicit appended raw to' => [$default241, 'rows.79.raw_to', 'guid'],
    'composite first resolved' => [$default241, 'rows.80.resolved_to', 'site_id'],
    'composite second resolved' => [$default241, 'rows.81.resolved_to', 'slug'],
    'next first explicit status' => [$default241, 'rows.82.status', 'explicit_parent_column'],
    'next composite second explicit raw' => [$default241, 'rows.85.raw_to', 'slug'],
    'helper current first kind' => [$currentImplicit241, '0.kind', 'foreign_key_implicit_parent_reference'],
    'helper current first implicit' => [$currentImplicit241, '0.implicit_parent_reference', true],
    'helper current first message' => [$currentImplicit241, '0.message', 'foreign key wp_comment_import->wp_posts omits parent columns and resolves to parent PRIMARY KEY ID'],
    'helper current explicit message' => [$currentImplicit241, '1.message', 'foreign key wp_comment_import->wp_posts names explicit parent column guid'],
    'helper current composite pk' => [$currentImplicit241, '2.parent_primary_key.1', 'slug'],
    'helper next first phase' => [$nextImplicit241, '0.phase', 'next'],
    'helper next first explicit' => [$nextImplicit241, '0.implicit_parent_reference', false],
    'helper next composite second resolved' => [$nextImplicit241, '3.resolved_to', 'slug'],
];

$tests = [];
foreach ($cases241 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey implicit parent reference current source next241 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt241): void {
        $t->same($expected, $valueAt241($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey implicit parent reference current source next241 paginates implicit rows'] = static function (TestRunner $t) use ($page241): void {
    $first = $page241(0, 78);
    $second = $page241(78, 4, $first['next']);
    $third = $page241(82, 4, $second['next']);

    $t->same(78, $first['count']);
    $t->same('foreign_key_implicit_parent_reference', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 78], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('ok_implicit_parent_primary_key', $second['rows'][3]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('explicit_parent_column', $third['rows'][3]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey implicit parent reference current source next241 reports missing implicit parent key'] = static function (TestRunner $t) use ($record241): void {
    $records = [
        $record241('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT)', 1),
        $record241('table', 'child', 'child', 3, 'CREATE TABLE child(code TEXT REFERENCES parent)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentReferenceRows241($records);
    $t->same(1, count($rows));
    $t->same('missing_implicit_parent_primary_key', $rows[0]['status']);
    $t->same(null, $rows[0]['resolved_to']);
    $t->same([], $rows[0]['parent_primary_key']);
};

$tests['pragma index xinfo foreignkey implicit parent reference current source next241 accepts table primary key order'] = static function (TestRunner $t) use ($record241): void {
    $records = [
        $record241('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a INTEGER, b INTEGER, PRIMARY KEY(b, a))', 1),
        $record241('table', 'child', 'child', 3, 'CREATE TABLE child(x INTEGER, y INTEGER, FOREIGN KEY(x, y) REFERENCES parent)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentReferenceRows241($records);
    $t->same(2, count($rows));
    $t->same('b', $rows[0]['resolved_to']);
    $t->same('a', $rows[1]['resolved_to']);
    $t->same(['a', 'b'], $rows[0]['parent_primary_key']);
};

$tests['pragma index xinfo foreignkey implicit parent reference current source next241 keeps explicit references out of implicit count'] = static function (TestRunner $t) use ($record241): void {
    $records = [
        $record241('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY, code TEXT UNIQUE)', 1),
        $record241('table', 'child', 'child', 3, 'CREATE TABLE child(code TEXT REFERENCES parent(code))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentReferenceRows241($records);
    $t->same(1, count($rows));
    $t->same('explicit_parent_column', $rows[0]['status']);
    $t->same(false, $rows[0]['implicit_parent_reference']);
    $t->same('code', $rows[0]['resolved_to']);
};

$tests['pragma index xinfo foreignkey implicit parent reference current source next241 rejects stale cursor'] = static function (TestRunner $t) use ($page241, $currentRecords241): void {
    $first = $page241(0, 78);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page241(78, 4, $first['next'], $currentRecords241));
};

$tests['pragma index xinfo foreignkey implicit parent reference current source next241 rejects stale offset'] = static function (TestRunner $t) use ($page241): void {
    $first = $page241(0, 78);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page241(79, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey implicit parent reference current source next241 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::implicitParentReferenceRows241([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey implicit parent reference current source next241 rejects invalid bounds'] = static function (TestRunner $t) use ($page241): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page241(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page241(0, 0));
};

return $tests;
