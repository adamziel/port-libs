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
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record245 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords245 = [
    $record245('table', 'wp_terms_stage', 'wp_terms_stage', 2, 'CREATE TABLE wp_terms_stage(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL, slug_norm TEXT GENERATED ALWAYS AS (lower(slug)) STORED UNIQUE, taxonomy TEXT NOT NULL, taxonomy_norm TEXT GENERATED ALWAYS AS (upper(taxonomy)) VIRTUAL, UNIQUE(slug_norm, taxonomy_norm))', 1),
    $record245('index', 'sqlite_autoindex_wp_terms_stage_1', 'wp_terms_stage', 3, null, 2),
    $record245('index', 'sqlite_autoindex_wp_terms_stage_2', 'wp_terms_stage', 4, null, 3),
    $record245('table', 'wp_termmeta_stage', 'wp_termmeta_stage', 5, 'CREATE TABLE wp_termmeta_stage(meta_id INTEGER PRIMARY KEY, term_slug TEXT NOT NULL, term_taxonomy TEXT NOT NULL, FOREIGN KEY(term_slug) REFERENCES wp_terms_stage(slug_norm), FOREIGN KEY(term_slug, term_taxonomy) REFERENCES wp_terms_stage(slug_norm, taxonomy_norm))', 4),
];

$nextRecords245 = [
    $record245('table', 'wp_terms_stage', 'wp_terms_stage', 2, 'CREATE TABLE wp_terms_stage(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE, taxonomy TEXT NOT NULL, UNIQUE(slug, taxonomy))', 1),
    $record245('index', 'sqlite_autoindex_wp_terms_stage_1', 'wp_terms_stage', 3, null, 2),
    $record245('index', 'sqlite_autoindex_wp_terms_stage_2', 'wp_terms_stage', 4, null, 3),
    $record245('table', 'wp_termmeta_stage', 'wp_termmeta_stage', 5, 'CREATE TABLE wp_termmeta_stage(meta_id INTEGER PRIMARY KEY, term_slug TEXT NOT NULL, term_taxonomy TEXT NOT NULL, FOREIGN KEY(term_slug) REFERENCES wp_terms_stage(slug), FOREIGN KEY(term_slug, term_taxonomy) REFERENCES wp_terms_stage(slug, taxonomy))', 4),
];

$missingIndexRecords245 = [
    $record245('table', 'wp_terms_stage', 'wp_terms_stage', 2, 'CREATE TABLE wp_terms_stage(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL, slug_norm TEXT GENERATED ALWAYS AS (lower(slug)) STORED, taxonomy TEXT NOT NULL)', 1),
    $record245('table', 'wp_termmeta_stage', 'wp_termmeta_stage', 3, 'CREATE TABLE wp_termmeta_stage(term_slug TEXT NOT NULL, FOREIGN KEY(term_slug) REFERENCES wp_terms_stage(slug_norm))', 2),
];

$page245 = static fn (
    int $offset = 0,
    int $limit = 260,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245::page(
    $currentRecords245,
    $nextRecords ?? $nextRecords245,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_stage_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_stage)',
    $offset,
    $limit,
    $resume,
);

$valueAt245 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default245 = static fn (): array => $page245();
$currentGenerated245 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245::generatedParentKeyRows($currentRecords245);
$nextGenerated245 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245::generatedParentKeyRows($nextRecords245, 'next');
$missingGenerated245 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245::generatedParentKeyRows($missingIndexRecords245);
$currentPageGenerated245 = static fn (): array => array_values(array_filter(
    $page245()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_generated_key'
        && ($row['phase'] ?? null) === 'current',
));

$cases245 = [
    'status ok' => [$default245, 'status', 'ok'],
    'operation marker' => [$default245, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next245'],
    'source id length' => [static fn (): array => ['len' => strlen($page245()['source_id'])], 'len', 64],
    'offset default' => [$default245, 'offset', 0],
    'limit default' => [$default245, 'limit', 260],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-table-xinfo-generated-foreign-key-parent-key', $page245()['dependencies'], true)], 'has', true],
    'base rowid alias retained' => [$default245, 'current.foreign_key_parent_rowid_alias.rows', 0],
    'generated source current' => [$default245, 'current_source.foreign_key_parent_generated_key_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_table_xinfo_hidden_and_pragma_index_xinfo'],
    'generated source next' => [$default245, 'next_source.foreign_key_parent_generated_key_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_table_xinfo_hidden_and_pragma_index_xinfo'],
    'current generated rows' => [$default245, 'current.foreign_key_parent_generated_key.rows', 3],
    'current generated blockers' => [$default245, 'current.foreign_key_parent_generated_key.hidden_parent_key_requires_table_xinfo', 3],
    'current missing unique zero' => [$default245, 'current.foreign_key_parent_generated_key.hidden_parent_key_missing_unique_index', 0],
    'current hidden columns' => [$default245, 'current.foreign_key_parent_generated_key.hidden_columns', 2],
    'current unique indexes' => [$default245, 'current.foreign_key_parent_generated_key.unique_indexes', 2],
    'next generated rows cleared' => [$default245, 'next_counts.foreign_key_parent_generated_key.rows', 0],
    'next blockers cleared' => [$default245, 'next_counts.foreign_key_parent_generated_key.hidden_parent_key_requires_table_xinfo', 0],
    'delta rows decreased' => [$default245, 'delta.foreign_key_parent_generated_key_rows', -3],
    'delta blockers decreased' => [$default245, 'delta.foreign_key_parent_generated_key_blockers', -3],
    'delta repaired true' => [$default245, 'delta.foreign_key_parent_generated_key_repaired', true],
    'delta changed true' => [$default245, 'delta.foreign_key_parent_generated_key_changed', true],
    'current summary single' => [$default245, 'current_source.foreign_key_parent_generated_key.0', 'current:wp_termmeta_stage#0.0:term_slug->wp_terms_stage.slug_norm:hidden=3:index=sqlite_autoindex_wp_terms_stage_1:hidden_parent_key_requires_table_xinfo'],
    'current summary composite first' => [$default245, 'current_source.foreign_key_parent_generated_key.1', 'current:wp_termmeta_stage#1.0:term_slug->wp_terms_stage.slug_norm:hidden=3:index=sqlite_autoindex_wp_terms_stage_2:hidden_parent_key_requires_table_xinfo'],
    'current summary composite second' => [$default245, 'current_source.foreign_key_parent_generated_key.2', 'current:wp_termmeta_stage#1.1:term_taxonomy->wp_terms_stage.taxonomy_norm:hidden=2:index=sqlite_autoindex_wp_terms_stage_2:hidden_parent_key_requires_table_xinfo'],
    'first row kind' => [$currentPageGenerated245, '0.kind', 'foreign_key_parent_generated_key'],
    'first row status' => [$currentPageGenerated245, '0.status', 'hidden_parent_key_requires_table_xinfo'],
    'first row hidden code stored' => [$currentPageGenerated245, '0.hidden_code', 3],
    'first row parent index' => [$currentPageGenerated245, '0.parent_unique_index', 'sqlite_autoindex_wp_terms_stage_1'],
    'first row key columns' => [$currentPageGenerated245, '0.index_key_columns', ['slug_norm']],
    'first row hidden parent columns' => [$currentPageGenerated245, '0.hidden_parent_columns', ['slug_norm']],
    'composite first key columns' => [$currentPageGenerated245, '1.index_key_columns', ['slug_norm', 'taxonomy_norm']],
    'composite first hidden parent columns' => [$currentPageGenerated245, '1.hidden_parent_columns', ['slug_norm', 'taxonomy_norm']],
    'composite second hidden code virtual' => [$currentPageGenerated245, '2.hidden_code', 2],
    'composite second from' => [$currentPageGenerated245, '2.from', 'term_taxonomy'],
    'composite second to' => [$currentPageGenerated245, '2.to', 'taxonomy_norm'],
    'helper current count' => [static fn (): array => ['count' => count($currentGenerated245())], 'count', 3],
    'helper next empty' => [static fn (): array => ['count' => count($nextGenerated245())], 'count', 0],
    'helper current first message' => [$currentGenerated245, '0.message', 'foreign key wp_termmeta_stage->wp_terms_stage parent key slug_norm is visible through PRAGMA table_xinfo and UNIQUE index sqlite_autoindex_wp_terms_stage_1'],
    'helper current second parent columns' => [$currentGenerated245, '1.parent_columns', ['slug_norm', 'taxonomy_norm']],
    'helper missing index status' => [$missingGenerated245, '0.status', 'hidden_parent_key_missing_unique_index'],
    'helper missing index message' => [$missingGenerated245, '0.message', 'foreign key wp_termmeta_stage->wp_terms_stage references generated parent column slug_norm without a UNIQUE parent key'],
];

$tests = [];
foreach ($cases245 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey generated parent key current source next245 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt245): void {
        $t->same($expected, $valueAt245($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey generated parent key current source next245 paginates appended rows'] = static function (TestRunner $t) use ($page245): void {
    $full = $page245();
    $baseCount = $full['total'] - 3;
    $first = $page245(0, $baseCount);
    $second = $page245($baseCount, 2, $first['next']);
    $third = $page245($baseCount + 2, 2, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_parent_generated_key', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('sqlite_autoindex_wp_terms_stage_1', $second['rows'][0]['parent_unique_index']);
    $t->same('sqlite_autoindex_wp_terms_stage_2', $second['rows'][1]['parent_unique_index']);
    $t->same('taxonomy_norm', $third['rows'][0]['to']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey generated parent key current source next245 ignores visible parent columns'] = static function (TestRunner $t) use ($record245): void {
    $records = [
        $record245('table', 'parent', 'parent', 2, 'CREATE TABLE parent(slug TEXT NOT NULL UNIQUE)', 1),
        $record245('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record245('table', 'child', 'child', 4, 'CREATE TABLE child(parent_slug TEXT REFERENCES parent(slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245::generatedParentKeyRows($records));
};

$tests['pragma index xinfo foreignkey generated parent key current source next245 rejects stale cursor'] = static function (TestRunner $t) use ($page245, $currentRecords245): void {
    $full = $page245();
    $first = $page245(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page245($full['total'] - 3, 2, $first['next'], $currentRecords245));
};

$tests['pragma index xinfo foreignkey generated parent key current source next245 rejects stale offset'] = static function (TestRunner $t) use ($page245): void {
    $full = $page245();
    $first = $page245(0, $full['total'] - 3);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page245($full['total'] - 2, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey generated parent key current source next245 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext245::generatedParentKeyRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey generated parent key current source next245 rejects invalid bounds'] = static function (TestRunner $t) use ($page245): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page245(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page245(0, 0));
};

return $tests;
