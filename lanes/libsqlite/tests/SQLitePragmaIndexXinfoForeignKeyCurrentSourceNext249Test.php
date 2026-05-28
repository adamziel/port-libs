<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext156.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext157.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext159.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext161.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext163.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext164.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext165.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext167.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext169.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext171.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext173.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext175.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext177.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext178.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext181.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext182.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext183.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext184.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext185.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext186.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext187.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext188.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext189.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext190.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext191.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext192.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext193.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext194.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext195.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext196.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext200.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext202.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext203.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext205.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext206.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext207.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext208.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext209.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext211.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext212.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext217.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext219.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext220.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext223.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext224.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext227.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext228.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext229.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext230.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext231.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext233.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext236.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext239.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext242.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext243.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext246.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext249.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext249;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record249 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords249 = [
    $record249('table', 'wp_terms_parent', 'wp_terms_parent', 2, 'CREATE TABLE wp_terms_parent(slug TEXT PRIMARY KEY, taxonomy TEXT NOT NULL, term_id INTEGER)', 1),
    $record249('index', 'sqlite_autoindex_wp_terms_parent_1', 'wp_terms_parent', 3, null, 2),
    $record249('table', 'wp_termmeta_generated_child', 'wp_termmeta_generated_child', 4, "CREATE TABLE wp_termmeta_generated_child(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_taxonomy TEXT NOT NULL,
        slug_key TEXT GENERATED ALWAYS AS (lower(raw_slug)) VIRTUAL NOT NULL REFERENCES wp_terms_parent(slug),
        taxonomy_key TEXT GENERATED ALWAYS AS (lower(raw_taxonomy)) STORED,
        FOREIGN KEY(slug_key, taxonomy_key) REFERENCES wp_terms_parent(slug, taxonomy)
    )", 3),
];

$nextRecords249 = [
    $currentRecords249[0],
    $currentRecords249[1],
    $record249('table', 'wp_termmeta_generated_child', 'wp_termmeta_generated_child', 4, "CREATE TABLE wp_termmeta_generated_child(
        meta_id INTEGER PRIMARY KEY,
        raw_slug TEXT NOT NULL,
        raw_taxonomy TEXT NOT NULL,
        slug_key TEXT NOT NULL REFERENCES wp_terms_parent(slug),
        taxonomy_key TEXT NOT NULL,
        FOREIGN KEY(slug_key, taxonomy_key) REFERENCES wp_terms_parent(slug, taxonomy)
    )", 3),
];

$blockedNextRecords249 = $currentRecords249;

$page249 = static fn (
    int $offset = 0,
    int $limit = 360,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext249::page(
    $currentRecords249,
    $nextRecords ?? $nextRecords249,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_parent_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_generated_child)',
    $offset,
    $limit,
    $resume,
);

$valueAt249 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default249 = static fn (): array => $page249();
$blocked249 = static fn (): array => $page249(nextRecords: $blockedNextRecords249);
$currentRows249 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext249::generatedChildColumnRows($currentRecords249);
$nextRows249 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext249::generatedChildColumnRows($nextRecords249, 'next');
$currentPageRows249 = static fn (): array => array_values(array_filter(
    $page249()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_generated_child_column'
        && ($row['phase'] ?? null) === 'current',
));

$cases249 = [
    'status ok' => [$default249, 'status', 'ok'],
    'operation marker' => [$default249, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next249'],
    'source id length' => [static fn (): array => ['len' => strlen($page249()['source_id'])], 'len', 64],
    'offset default' => [$default249, 'offset', 0],
    'limit default' => [$default249, 'limit', 360],
    'base generated parent retained' => [$default249, 'current.foreign_key_generated_parent_columns.rows', 0],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-table-xinfo-generated-child-columns', $page249()['dependencies'], true)], 'has', true],
    'child source current' => [$default249, 'current_source.foreign_key_generated_child_column_source', 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo_generated_columns'],
    'child source next' => [$default249, 'next_source.foreign_key_generated_child_column_source', 'pragma_foreign_key_list_child_columns_plus_pragma_table_xinfo_generated_columns'],
    'current child rows' => [$default249, 'current.foreign_key_generated_child_columns.rows', 3],
    'current generated blockers' => [$default249, 'current.foreign_key_generated_child_columns.generated_child_column', 3],
    'current visible generated zero' => [$default249, 'current.foreign_key_generated_child_columns.visible_child_column', 0],
    'current virtual count' => [$default249, 'current.foreign_key_generated_child_columns.virtual', 2],
    'current stored count' => [$default249, 'current.foreign_key_generated_child_columns.stored', 1],
    'current notnull count' => [$default249, 'current.foreign_key_generated_child_columns.notnull', 2],
    'current foreign key groups' => [$default249, 'current.foreign_key_generated_child_columns.foreign_keys', 2],
    'next rows cleared' => [$default249, 'next_counts.foreign_key_generated_child_columns.rows', 0],
    'next blockers cleared' => [$default249, 'next_counts.foreign_key_generated_child_columns.generated_child_column', 0],
    'delta rows decreased' => [$default249, 'delta.foreign_key_generated_child_rows', -3],
    'delta repaired true' => [$default249, 'delta.foreign_key_generated_child_columns_repaired', true],
    'delta changed true' => [$default249, 'delta.foreign_key_generated_child_columns_changed', true],
    'delta virtual decreased' => [$default249, 'delta.foreign_key_generated_child_virtual', -2],
    'delta stored decreased' => [$default249, 'delta.foreign_key_generated_child_stored', -1],
    'complete next null' => [$default249, 'next', null],
    'current summary single' => [$default249, 'current_source.foreign_key_generated_child_columns.0', 'current:wp_termmeta_generated_child#0.0:slug_key->wp_terms_parent.slug:hidden=2:storage=virtual:generated_child_column'],
    'current summary composite first' => [$default249, 'current_source.foreign_key_generated_child_columns.1', 'current:wp_termmeta_generated_child#1.0:slug_key->wp_terms_parent.slug:hidden=2:storage=virtual:generated_child_column'],
    'current summary composite second' => [$default249, 'current_source.foreign_key_generated_child_columns.2', 'current:wp_termmeta_generated_child#1.1:taxonomy_key->wp_terms_parent.taxonomy:hidden=3:storage=stored:generated_child_column'],
    'first row kind' => [$currentPageRows249, '0.kind', 'foreign_key_generated_child_column'],
    'first row status' => [$currentPageRows249, '0.status', 'generated_child_column'],
    'first row hidden' => [$currentPageRows249, '0.child_hidden', 2],
    'first row storage' => [$currentPageRows249, '0.child_generated_storage', 'virtual'],
    'first row notnull' => [$currentPageRows249, '0.child_notnull', 1],
    'first row table info invisible' => [$currentPageRows249, '0.table_info_visible', false],
    'first row parent' => [$currentPageRows249, '0.parent', 'wp_terms_parent'],
    'first row to' => [$currentPageRows249, '0.to', 'slug'],
    'third row storage' => [$currentPageRows249, '2.child_generated_storage', 'stored'],
    'third row nullable' => [$currentPageRows249, '2.child_notnull', 0],
    'third row from' => [$currentPageRows249, '2.from', 'taxonomy_key'],
    'helper current count' => [static fn (): array => ['count' => count($currentRows249())], 'count', 3],
    'helper current first message' => [$currentRows249, '0.message', 'foreign key wp_termmeta_generated_child.slug_key uses a generated child column; PRAGMA table_info omits it but table_xinfo exposes hidden code 2'],
    'helper current second seq' => [$currentRows249, '1.seq', 0],
    'helper current third to' => [$currentRows249, '2.to', 'taxonomy'],
    'helper next empty' => [static fn (): array => ['count' => count($nextRows249())], 'count', 0],
    'blocked next rows remain' => [$blocked249, 'next_counts.foreign_key_generated_child_columns.rows', 3],
    'blocked next blockers remain' => [$blocked249, 'next_counts.foreign_key_generated_child_columns.generated_child_column', 3],
    'blocked repaired false' => [$blocked249, 'delta.foreign_key_generated_child_columns_repaired', false],
];

$tests = [];
foreach ($cases249 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey generated child current source next249 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt249): void {
        $t->same($expected, $valueAt249($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey generated child current source next249 paginates generated rows'] = static function (TestRunner $t) use ($page249): void {
    $full = $page249();
    $baseCount = $full['total'] - 3;
    $first = $page249(0, $baseCount);
    $second = $page249($baseCount, 2, $first['next']);
    $third = $page249($baseCount + 2, 2, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_generated_child_column', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('generated_child_column', $second['rows'][1]['status']);
    $t->same('stored', $third['rows'][0]['child_generated_storage']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey generated child current source next249 ignores visible child columns'] = static function (TestRunner $t) use ($record249): void {
    $records = [
        $record249('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT PRIMARY KEY)', 1),
        $record249('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record249('table', 'child', 'child', 4, 'CREATE TABLE child(slug TEXT REFERENCES parent(slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext249::generatedChildColumnRows($records));
};

$tests['pragma index xinfo foreignkey generated child current source next249 ignores generated non fk columns'] = static function (TestRunner $t) use ($record249): void {
    $records = [
        $record249('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT PRIMARY KEY)', 1),
        $record249('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record249('table', 'child', 'child', 4, 'CREATE TABLE child(raw_slug TEXT, slug_key TEXT AS (lower(raw_slug)), slug TEXT REFERENCES parent(slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext249::generatedChildColumnRows($records));
};

$tests['pragma index xinfo foreignkey generated child current source next249 rejects stale cursor'] = static function (TestRunner $t) use ($page249, $blockedNextRecords249): void {
    $full = $page249();
    $first = $page249(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page249($full['total'] - 3, 2, $first['next'], $blockedNextRecords249));
};

$tests['pragma index xinfo foreignkey generated child current source next249 rejects stale offset'] = static function (TestRunner $t) use ($page249): void {
    $full = $page249();
    $first = $page249(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page249($full['total'] - 2, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey generated child current source next249 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext249::generatedChildColumnRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey generated child current source next249 rejects invalid bounds'] = static function (TestRunner $t) use ($page249): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page249(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page249(0, 0));
};

return $tests;
