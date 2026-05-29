<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record254 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords254 = [
    $record254('table', 'wp_terms_stage', 'wp_terms_stage', 2, 'CREATE TABLE wp_terms_stage(slug TEXT COLLATE NOCASE, taxonomy TEXT COLLATE RTRIM, term_id INTEGER, UNIQUE(slug, taxonomy), UNIQUE(term_id))', 1),
    $record254('index', 'sqlite_autoindex_wp_terms_stage_1', 'wp_terms_stage', 3, null, 2),
    $record254('index', 'sqlite_autoindex_wp_terms_stage_2', 'wp_terms_stage', 4, null, 3),
    $record254('table', 'wp_term_relationships_stage', 'wp_term_relationships_stage', 5, 'CREATE TABLE wp_term_relationships_stage(object_id INTEGER, term_slug TEXT NOT NULL, taxonomy TEXT NOT NULL, parent_term INTEGER, FOREIGN KEY(term_slug, taxonomy) REFERENCES wp_terms_stage(slug, taxonomy) ON DELETE CASCADE, FOREIGN KEY(parent_term) REFERENCES wp_terms_stage(term_id) ON UPDATE SET NULL)', 4),
    $record254('index', 'wp_term_relationships_stage_fk', 'wp_term_relationships_stage', 6, 'CREATE INDEX wp_term_relationships_stage_fk ON wp_term_relationships_stage(term_slug, taxonomy)', 5),
];

$nextRecords254 = [
    $record254('table', 'wp_terms_stage', 'wp_terms_stage', 2, 'CREATE TABLE wp_terms_stage(slug TEXT COLLATE NOCASE NOT NULL, taxonomy TEXT COLLATE RTRIM NOT NULL, term_id INTEGER NOT NULL, UNIQUE(slug, taxonomy), UNIQUE(term_id))', 1),
    $record254('index', 'sqlite_autoindex_wp_terms_stage_1', 'wp_terms_stage', 3, null, 2),
    $record254('index', 'sqlite_autoindex_wp_terms_stage_2', 'wp_terms_stage', 4, null, 3),
    $currentRecords254[3],
    $currentRecords254[4],
];

$partialRecords254 = [
    $record254('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT, taxonomy TEXT)', 1),
    $record254('index', 'parent_partial_unique', 'parent', 3, "CREATE UNIQUE INDEX parent_partial_unique ON parent(slug, taxonomy) WHERE taxonomy <> ''", 2),
    $record254('table', 'child', 'child', 4, 'CREATE TABLE child(slug TEXT, taxonomy TEXT, FOREIGN KEY(slug, taxonomy) REFERENCES parent(slug, taxonomy))', 3),
];

$page254 = static fn (
    int $offset = 0,
    int $limit = 420,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page254(
    $currentRecords254,
    $nextRecords ?? $nextRecords254,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_stage_1)',
    'PRAGMA main.foreign_key_list(wp_term_relationships_stage)',
    $offset,
    $limit,
    $resume,
);

$valueAt254 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default254 = static fn (): array => $page254();
$currentNullable254 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullableParentKeyRows254($currentRecords254);
$nextNullable254 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullableParentKeyRows254($nextRecords254, 'next');
$partialNullable254 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullableParentKeyRows254($partialRecords254);
$currentParentRows254 = static fn (): array => array_values(array_filter(
    $page254()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_nullable_parent_key'
        && ($row['phase'] ?? null) === 'current',
));

$cases254 = [
    'status ok' => [$default254, 'status', 'ok'],
    'operation marker' => [$default254, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next254'],
    'source id length' => [static fn (): array => ['len' => strlen($page254()['source_id'])], 'len', 64],
    'offset default' => [$default254, 'offset', 0],
    'limit default' => [$default254, 'limit', 420],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-nullable-parent-key', $page254()['dependencies'], true)], 'has', true],
    'base expression action retained' => [$default254, 'current.foreign_key_child_action_expression_index.rows', 0],
    'nullable source current' => [$default254, 'current_source.foreign_key_nullable_parent_key_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_table_info_notnull_and_pragma_index_xinfo'],
    'nullable source next' => [$default254, 'next_source.foreign_key_nullable_parent_key_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_table_info_notnull_and_pragma_index_xinfo'],
    'current nullable rows' => [$default254, 'current.foreign_key_nullable_parent_key.rows', 3],
    'current nullable blockers' => [$default254, 'current.foreign_key_nullable_parent_key.nullable_parent_key', 3],
    'current not null zero' => [$default254, 'current.foreign_key_nullable_parent_key.not_null_parent_key', 0],
    'current blocked count' => [$default254, 'current.foreign_key_nullable_parent_key.blocked', 3],
    'current foreign keys count' => [$default254, 'current.foreign_key_nullable_parent_key.foreign_keys', 2],
    'current nullable columns count' => [$default254, 'current.foreign_key_nullable_parent_key.nullable_columns', 3],
    'current indexes count' => [$default254, 'current.foreign_key_nullable_parent_key.unique_indexes', 2],
    'current autoindex rows' => [$default254, 'current.foreign_key_nullable_parent_key.autoindexes', 3],
    'next rows stable' => [$default254, 'next_counts.foreign_key_nullable_parent_key.rows', 3],
    'next nullable cleared' => [$default254, 'next_counts.foreign_key_nullable_parent_key.nullable_parent_key', 0],
    'next not null rows' => [$default254, 'next_counts.foreign_key_nullable_parent_key.not_null_parent_key', 3],
    'next blocked zero' => [$default254, 'next_counts.foreign_key_nullable_parent_key.blocked', 0],
    'delta rows stable' => [$default254, 'delta.foreign_key_nullable_parent_key_rows', 0],
    'delta blockers repaired' => [$default254, 'delta.foreign_key_nullable_parent_key_blockers', -3],
    'delta repaired true' => [$default254, 'delta.foreign_key_nullable_parent_key_repaired', true],
    'delta changed true' => [$default254, 'delta.foreign_key_nullable_parent_key_changed', true],
    'current summary slug' => [$default254, 'current_source.foreign_key_nullable_parent_key.0', 'current:wp_term_relationships_stage#0.0:term_slug->wp_terms_stage.slug:parent=slug,taxonomy:index=sqlite_autoindex_wp_terms_stage_1:notnull=0:nullable_parent_key'],
    'current summary taxonomy' => [$default254, 'current_source.foreign_key_nullable_parent_key.1', 'current:wp_term_relationships_stage#0.1:taxonomy->wp_terms_stage.taxonomy:parent=slug,taxonomy:index=sqlite_autoindex_wp_terms_stage_1:notnull=0:nullable_parent_key'],
    'next summary repaired slug' => [$default254, 'next_source.foreign_key_nullable_parent_key.0', 'next:wp_term_relationships_stage#0.0:term_slug->wp_terms_stage.slug:parent=slug,taxonomy:index=sqlite_autoindex_wp_terms_stage_1:notnull=1:not_null_parent_key'],
    'complete no next' => [$default254, 'next', null],
    'first row kind' => [$currentParentRows254, '0.kind', 'foreign_key_nullable_parent_key'],
    'first row status' => [$currentParentRows254, '0.status', 'nullable_parent_key'],
    'first row blocked' => [$currentParentRows254, '0.blocked', true],
    'first row parent index' => [$currentParentRows254, '0.parent_unique_index', 'sqlite_autoindex_wp_terms_stage_1'],
    'first row parent origin' => [$currentParentRows254, '0.parent_index_origin', 'u'],
    'first row parent columns' => [$currentParentRows254, '0.parent_columns', ['slug', 'taxonomy']],
    'first row index columns' => [$currentParentRows254, '0.parent_index_columns', ['slug', 'taxonomy']],
    'first row collations' => [$currentParentRows254, '0.parent_index_collations', ['NOCASE', 'RTRIM']],
    'first row not null false' => [$currentParentRows254, '0.parent_notnull', false],
    'first row pk zero' => [$currentParentRows254, '0.parent_pk', 0],
    'first row message' => [$currentParentRows254, '0.message', 'foreign key wp_term_relationships_stage->wp_terms_stage parent column slug is nullable even though sqlite_autoindex_wp_terms_stage_1 is UNIQUE'],
    'third row single parent column' => [$currentParentRows254, '2.to', 'term_id'],
    'third row single index' => [$currentParentRows254, '2.parent_unique_index', 'sqlite_autoindex_wp_terms_stage_2'],
    'third row child column' => [$currentParentRows254, '2.from', 'parent_term'],
    'helper current count' => [static fn (): array => ['count' => count($currentNullable254())], 'count', 3],
    'helper next count' => [static fn (): array => ['count' => count($nextNullable254())], 'count', 3],
    'helper next repaired status' => [$nextNullable254, '0.status', 'not_null_parent_key'],
    'helper partial ignored' => [static fn (): array => ['count' => count($partialNullable254())], 'count', 0],
];

$tests = [];
foreach ($cases254 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey nullable parent key current source next254 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt254): void {
        $t->same($expected, $valueAt254($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey nullable parent key current source next254 paginates appended rows'] = static function (TestRunner $t) use ($page254): void {
    $full = $page254();
    $baseCount = $full['total'] - 6;
    $first = $page254(0, $baseCount);
    $second = $page254($baseCount, 4, $first['next']);
    $third = $page254($baseCount + 4, 3, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_nullable_parent_key', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('nullable_parent_key', $second['rows'][0]['status']);
    $t->same('nullable_parent_key', $second['rows'][2]['status']);
    $t->same('not_null_parent_key', $second['rows'][3]['status']);
    $t->same('not_null_parent_key', $third['rows'][1]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey nullable parent key current source next254 treats primary key columns as not null'] = static function (TestRunner $t) use ($record254): void {
    $records = [
        $record254('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE)', 1),
        $record254('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record254('table', 'child', 'child', 4, 'CREATE TABLE child(parent_code TEXT, FOREIGN KEY(parent_code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullableParentKeyRows254($records);
    $t->same('not_null_parent_key', $rows[0]['status']);
    $t->same(false, $rows[0]['blocked']);
    $t->same(0, $rows[0]['parent_pk']);
};

$tests['pragma index xinfo foreignkey nullable parent key current source next254 ignores implicit parent references'] = static function (TestRunner $t) use ($record254): void {
    $records = [
        $record254('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a INTEGER, b INTEGER, UNIQUE(a, b))', 1),
        $record254('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record254('table', 'child', 'child', 4, 'CREATE TABLE child(a INTEGER, b INTEGER, FOREIGN KEY(a, b) REFERENCES parent)', 3),
    ];

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullableParentKeyRows254($records));
};

$tests['pragma index xinfo foreignkey nullable parent key current source next254 rejects stale cursor'] = static function (TestRunner $t) use ($page254, $currentRecords254): void {
    $full = $page254();
    $first = $page254(0, $full['total'] - 6);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page254($full['total'] - 6, 2, $first['next'], $currentRecords254));
};

$tests['pragma index xinfo foreignkey nullable parent key current source next254 rejects stale offset'] = static function (TestRunner $t) use ($page254): void {
    $full = $page254();
    $first = $page254(0, $full['total'] - 6);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page254($full['total'] - 5, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey nullable parent key current source next254 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::nullableParentKeyRows254([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey nullable parent key current source next254 rejects invalid bounds'] = static function (TestRunner $t) use ($page254): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page254(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page254(0, 0));
};

return $tests;
