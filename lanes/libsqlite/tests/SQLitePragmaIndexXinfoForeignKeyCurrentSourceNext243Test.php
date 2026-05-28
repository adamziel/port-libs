<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext243;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record243 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords243 = [
    $record243('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, guid TEXT COLLATE NOCASE NOT NULL, import_score NUMERIC)', 1),
    $record243('index', 'wp_posts_guid_unique', 'wp_posts', 3, 'CREATE UNIQUE INDEX wp_posts_guid_unique ON wp_posts(guid COLLATE NOCASE)', 2),
    $record243('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(site_id INTEGER NOT NULL, slug TEXT COLLATE NOCASE NOT NULL, weight REAL, PRIMARY KEY(site_id, slug))', 3),
    $record243('table', 'wp_comment_import', 'wp_comment_import', 5, "CREATE TABLE wp_comment_import(
        comment_id INTEGER PRIMARY KEY,
        owner_id TEXT REFERENCES wp_posts,
        explicit_guid BLOB REFERENCES wp_posts(guid),
        score_text TEXT REFERENCES wp_posts(import_score),
        term_site TEXT NOT NULL,
        term_slug NUMERIC NOT NULL,
        term_weight INTEGER REFERENCES wp_terms(weight),
        FOREIGN KEY(term_site, term_slug) REFERENCES wp_terms
    )", 4),
];

$nextRecords243 = [
    $currentRecords243[0],
    $currentRecords243[1],
    $currentRecords243[2],
    $record243('table', 'wp_comment_import', 'wp_comment_import', 5, "CREATE TABLE wp_comment_import(
        comment_id INTEGER PRIMARY KEY,
        owner_id INTEGER REFERENCES wp_posts(ID),
        explicit_guid TEXT REFERENCES wp_posts(guid),
        score_text NUMERIC REFERENCES wp_posts(import_score),
        term_site INTEGER NOT NULL,
        term_slug TEXT NOT NULL,
        term_weight REAL REFERENCES wp_terms(weight),
        FOREIGN KEY(term_site, term_slug) REFERENCES wp_terms(site_id, slug)
    )", 4),
];

$page243 = static fn (
    int $offset = 0,
    int $limit = 260,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext243::page(
    $currentRecords243,
    $nextRecords ?? $nextRecords243,
    'PRAGMA main.index_xinfo(wp_posts_guid_unique)',
    'PRAGMA main.foreign_key_list(wp_comment_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt243 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default243 = static fn (): array => $page243();
$currentAffinity243 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext243::foreignKeyAffinityRows($currentRecords243);
$nextAffinity243 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext243::foreignKeyAffinityRows($nextRecords243, 'next');
$currentPageAffinity243 = static fn (): array => array_values(array_filter(
    $page243()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_affinity' && ($row['phase'] ?? null) === 'current',
));

$cases243 = [
    'status ok' => [$default243, 'status', 'ok'],
    'operation marker' => [$default243, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next243'],
    'source id length' => [static fn (): array => ['len' => strlen($page243()['source_id'])], 'len', 64],
    'offset default' => [$default243, 'offset', 0],
    'limit default' => [$default243, 'limit', 260],
    'base implicit retained' => [$default243, 'current.foreign_key_implicit_parent_references.rows', 6],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-parent-affinity-comparison', $page243()['dependencies'], true)], 'has', true],
    'affinity source current' => [$default243, 'current_source.foreign_key_affinity_source', 'pragma_foreign_key_list_plus_table_info_parent_affinity'],
    'affinity source next' => [$default243, 'next_source.foreign_key_affinity_source', 'pragma_foreign_key_list_plus_table_info_parent_affinity'],
    'current rows' => [$default243, 'current.foreign_key_affinity.rows', 6],
    'current mismatches' => [$default243, 'current.foreign_key_affinity.affinity_mismatch', 6],
    'current matches zero' => [$default243, 'current.foreign_key_affinity.affinity_match', 0],
    'current parent integer rows' => [$default243, 'current.foreign_key_affinity.parent_integer', 2],
    'current parent text rows' => [$default243, 'current.foreign_key_affinity.parent_text', 2],
    'current parent numeric rows' => [$default243, 'current.foreign_key_affinity.parent_numeric', 1],
    'current parent real rows' => [$default243, 'current.foreign_key_affinity.parent_real', 1],
    'next rows' => [$default243, 'next_counts.foreign_key_affinity.rows', 6],
    'next matches' => [$default243, 'next_counts.foreign_key_affinity.affinity_match', 6],
    'next mismatches zero' => [$default243, 'next_counts.foreign_key_affinity.affinity_mismatch', 0],
    'delta rows zero' => [$default243, 'delta.foreign_key_affinity_rows', 0],
    'delta mismatches repaired' => [$default243, 'delta.foreign_key_affinity_mismatches', -6],
    'delta repaired true' => [$default243, 'delta.foreign_key_affinity_repaired', true],
    'delta changed true' => [$default243, 'delta.foreign_key_affinity_changed', true],
    'complete next null' => [$default243, 'next', null],
    'current summary owner' => [$default243, 'current_source.foreign_key_affinity.0', 'current:wp_comment_import#0.0:owner_id->wp_posts.ID:child=TEXT:parent=INTEGER:affinity_mismatch'],
    'current summary guid' => [$default243, 'current_source.foreign_key_affinity.1', 'current:wp_comment_import#1.0:explicit_guid->wp_posts.guid:child=BLOB:parent=TEXT:affinity_mismatch'],
    'current summary score' => [$default243, 'current_source.foreign_key_affinity.2', 'current:wp_comment_import#2.0:score_text->wp_posts.import_score:child=TEXT:parent=NUMERIC:affinity_mismatch'],
    'current summary composite first' => [$default243, 'current_source.foreign_key_affinity.4', 'current:wp_comment_import#4.0:term_site->wp_terms.site_id:child=TEXT:parent=INTEGER:affinity_mismatch'],
    'next summary owner' => [$default243, 'next_source.foreign_key_affinity.0', 'next:wp_comment_import#0.0:owner_id->wp_posts.ID:child=INTEGER:parent=INTEGER:affinity_match'],
    'first appended kind' => [$currentPageAffinity243, '0.kind', 'foreign_key_affinity'],
    'first appended child type' => [$currentPageAffinity243, '0.child_type', 'TEXT'],
    'first appended parent type' => [$currentPageAffinity243, '0.parent_type', 'INTEGER'],
    'first appended parent applies' => [$currentPageAffinity243, '0.parent_affinity_applies', true],
    'first appended status' => [$currentPageAffinity243, '0.status', 'affinity_mismatch'],
    'guid child affinity blob' => [$currentPageAffinity243, '1.child_affinity', 'BLOB'],
    'score parent affinity numeric' => [$currentPageAffinity243, '2.parent_affinity', 'NUMERIC'],
    'weight parent affinity real' => [$currentPageAffinity243, '3.parent_affinity', 'REAL'],
    'composite second parent affinity text' => [$currentPageAffinity243, '5.parent_affinity', 'TEXT'],
    'helper current count' => [static fn (): array => ['count' => count($currentAffinity243())], 'count', 6],
    'helper current first message' => [$currentAffinity243, '0.message', 'foreign key wp_comment_import.owner_id uses child TEXT affinity but parent wp_posts.ID applies INTEGER affinity'],
    'helper current implicit parent to' => [$currentAffinity243, '4.to', 'site_id'],
    'helper current composite second to' => [$currentAffinity243, '5.to', 'slug'],
    'helper next first status' => [$nextAffinity243, '0.status', 'affinity_match'],
    'helper next first message' => [$nextAffinity243, '0.message', 'foreign key wp_comment_import.owner_id uses matching INTEGER parent affinity from wp_posts.ID'],
    'helper next guid child type' => [$nextAffinity243, '1.child_type', 'TEXT'],
    'helper next all count' => [static fn (): array => ['matches' => count(array_filter($nextAffinity243(), static fn (array $row): bool => $row['status'] === 'affinity_match'))], 'matches', 6],
];

$tests = [];
foreach ($cases243 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey affinity current source next243 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt243): void {
        $t->same($expected, $valueAt243($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey affinity current source next243 paginates affinity rows'] = static function (TestRunner $t) use ($page243): void {
    $full = $page243();
    $baseCount = $full['total'] - 12;
    $first = $page243(0, $baseCount);
    $second = $page243($baseCount, 6, $first['next']);
    $third = $page243($baseCount + 6, 6, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_affinity', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('affinity_mismatch', $second['rows'][5]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('affinity_match', $third['rows'][5]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey affinity current source next243 reports missing parent column'] = static function (TestRunner $t) use ($record243): void {
    $records = [
        $record243('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record243('table', 'child', 'child', 3, 'CREATE TABLE child(pid TEXT REFERENCES parent(missing_id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext243::foreignKeyAffinityRows($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_column', $rows[0]['status']);
    $t->same('TEXT', $rows[0]['child_affinity']);
    $t->same('BLOB', $rows[0]['parent_affinity']);
    $t->same(false, $rows[0]['parent_affinity_applies']);
};

$tests['pragma index xinfo foreignkey affinity current source next243 reports missing child column'] = static function (TestRunner $t) use ($record243): void {
    $records = [
        $record243('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record243('table', 'child', 'child', 3, 'CREATE TABLE child(real_id INTEGER, FOREIGN KEY(missing_id) REFERENCES parent(id))', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext243::foreignKeyAffinityRows($records);
    $t->same(1, count($rows));
    $t->same('missing_child_column', $rows[0]['status']);
    $t->same('BLOB', $rows[0]['child_affinity']);
    $t->same('INTEGER', $rows[0]['parent_affinity']);
    $t->same(true, $rows[0]['parent_affinity_applies']);
};

$tests['pragma index xinfo foreignkey affinity current source next243 rejects stale cursor'] = static function (TestRunner $t) use ($page243, $currentRecords243): void {
    $full = $page243();
    $first = $page243(0, $full['total'] - 12);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page243($full['total'] - 12, 6, $first['next'], $currentRecords243));
};

$tests['pragma index xinfo foreignkey affinity current source next243 rejects stale offset'] = static function (TestRunner $t) use ($page243): void {
    $full = $page243();
    $first = $page243(0, $full['total'] - 12);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page243($full['total'] - 11, 6, $first['next']));
};

$tests['pragma index xinfo foreignkey affinity current source next243 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext243::foreignKeyAffinityRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey affinity current source next243 rejects invalid bounds'] = static function (TestRunner $t) use ($page243): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page243(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page243(0, 0));
};

return $tests;
