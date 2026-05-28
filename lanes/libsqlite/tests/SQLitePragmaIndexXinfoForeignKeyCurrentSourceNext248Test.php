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
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248.php';

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record248 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords248 = [
    $record248('table', 'wp_term_taxonomy_stage', 'wp_term_taxonomy_stage', 2, 'CREATE TABLE wp_term_taxonomy_stage(term_taxonomy_id INTEGER PRIMARY KEY, taxonomy TEXT NOT NULL, slug TEXT NOT NULL)', 1),
    $record248('index', 'wp_term_taxonomy_stage_taxonomy_slug_unique', 'wp_term_taxonomy_stage', 3, 'CREATE UNIQUE INDEX wp_term_taxonomy_stage_taxonomy_slug_unique ON wp_term_taxonomy_stage(taxonomy, slug)', 2),
    $record248('table', 'wp_term_relationships_stage', 'wp_term_relationships_stage', 4, 'CREATE TABLE wp_term_relationships_stage(object_id INTEGER NOT NULL, taxonomy TEXT NOT NULL, slug TEXT NOT NULL, FOREIGN KEY(taxonomy, slug) REFERENCES wp_term_taxonomy_stage(taxonomy, slug) ON DELETE CASCADE)', 3),
    $record248('table', 'wp_termmeta_stage', 'wp_termmeta_stage', 5, 'CREATE TABLE wp_termmeta_stage(meta_id INTEGER PRIMARY KEY, taxonomy TEXT NOT NULL, slug TEXT NOT NULL, FOREIGN KEY(taxonomy, slug) REFERENCES wp_term_taxonomy_stage(taxonomy, slug))', 4),
];

$nextRecords248 = [
    $record248('table', 'wp_term_taxonomy_stage', 'wp_term_taxonomy_stage', 2, 'CREATE TABLE wp_term_taxonomy_stage(term_taxonomy_id INTEGER PRIMARY KEY, taxonomy TEXT NOT NULL, slug TEXT NOT NULL, UNIQUE(taxonomy, slug))', 1),
    $record248('index', 'sqlite_autoindex_wp_term_taxonomy_stage_1', 'wp_term_taxonomy_stage', 3, null, 2),
    $record248('table', 'wp_term_relationships_stage', 'wp_term_relationships_stage', 4, 'CREATE TABLE wp_term_relationships_stage(object_id INTEGER NOT NULL, taxonomy TEXT NOT NULL, slug TEXT NOT NULL, FOREIGN KEY(taxonomy, slug) REFERENCES wp_term_taxonomy_stage(taxonomy, slug) ON DELETE CASCADE)', 3),
    $record248('table', 'wp_termmeta_stage', 'wp_termmeta_stage', 5, 'CREATE TABLE wp_termmeta_stage(meta_id INTEGER PRIMARY KEY, taxonomy TEXT NOT NULL, slug TEXT NOT NULL, FOREIGN KEY(taxonomy, slug) REFERENCES wp_term_taxonomy_stage(taxonomy, slug))', 4),
];

$missingRecords248 = [
    $record248('table', 'parent', 'parent', 2, 'CREATE TABLE parent(taxonomy TEXT NOT NULL, slug TEXT NOT NULL)', 1),
    $record248('table', 'child', 'child', 3, 'CREATE TABLE child(taxonomy TEXT NOT NULL, slug TEXT NOT NULL, FOREIGN KEY(taxonomy, slug) REFERENCES parent(taxonomy, slug))', 2),
];

$page248 = static fn (
    int $offset = 0,
    int $limit = 320,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248::page(
    $currentRecords248,
    $nextRecords ?? $nextRecords248,
    'PRAGMA main.index_xinfo(wp_term_taxonomy_stage_taxonomy_slug_unique)',
    'PRAGMA main.foreign_key_list(wp_term_relationships_stage)',
    $offset,
    $limit,
    $resume,
);

$valueAt248 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default248 = static fn (): array => $page248();
$currentExternal248 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248::externalParentKeyRows($currentRecords248);
$nextExternal248 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248::externalParentKeyRows($nextRecords248, 'next');
$missingExternal248 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248::externalParentKeyRows($missingRecords248);
$currentPageExternal248 = static fn (): array => array_values(array_filter(
    $page248()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_external_unique'
        && ($row['phase'] ?? null) === 'current',
));

$cases248 = [
    'status ok' => [$default248, 'status', 'ok'],
    'operation marker' => [$default248, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next248'],
    'source id length' => [static fn (): array => ['len' => strlen($page248()['source_id'])], 'len', 64],
    'offset default' => [$default248, 'offset', 0],
    'limit default' => [$default248, 'limit', 320],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-index-list-origin-foreign-key-parent-key', $page248()['dependencies'], true)], 'has', true],
    'base generated key retained' => [$default248, 'current.foreign_key_parent_generated_key.rows', 0],
    'external source current' => [$default248, 'current_source.foreign_key_parent_external_unique_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_list_origin_and_pragma_index_xinfo'],
    'external source next' => [$default248, 'next_source.foreign_key_parent_external_unique_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_list_origin_and_pragma_index_xinfo'],
    'current external rows' => [$default248, 'current.foreign_key_parent_external_unique.rows', 2],
    'current external blockers' => [$default248, 'current.foreign_key_parent_external_unique.external_unique_parent_key', 2],
    'current inline zero' => [$default248, 'current.foreign_key_parent_external_unique.inline_unique_parent_key', 0],
    'current created indexes' => [$default248, 'current.foreign_key_parent_external_unique.created_indexes', 2],
    'current autoindexes zero' => [$default248, 'current.foreign_key_parent_external_unique.autoindexes', 0],
    'current drop risks' => [$default248, 'current.foreign_key_parent_external_unique.drop_index_mismatch_risks', 2],
    'next external rows' => [$default248, 'next_counts.foreign_key_parent_external_unique.rows', 2],
    'next blockers cleared' => [$default248, 'next_counts.foreign_key_parent_external_unique.external_unique_parent_key', 0],
    'next inline rows' => [$default248, 'next_counts.foreign_key_parent_external_unique.inline_unique_parent_key', 2],
    'next autoindexes' => [$default248, 'next_counts.foreign_key_parent_external_unique.autoindexes', 2],
    'delta rows stable' => [$default248, 'delta.foreign_key_parent_external_unique_rows', 0],
    'delta blockers decreased' => [$default248, 'delta.foreign_key_parent_external_unique_blockers', -2],
    'delta repaired true' => [$default248, 'delta.foreign_key_parent_external_unique_repaired', true],
    'delta changed true' => [$default248, 'delta.foreign_key_parent_external_unique_changed', true],
    'current summary relationships' => [$default248, 'current_source.foreign_key_parent_external_unique.0', 'current:wp_term_relationships_stage#0->wp_term_taxonomy_stage:parent=taxonomy,slug:index=wp_term_taxonomy_stage_taxonomy_slug_unique:origin=c:external_unique_parent_key'],
    'current summary meta' => [$default248, 'current_source.foreign_key_parent_external_unique.1', 'current:wp_termmeta_stage#0->wp_term_taxonomy_stage:parent=taxonomy,slug:index=wp_term_taxonomy_stage_taxonomy_slug_unique:origin=c:external_unique_parent_key'],
    'next summary relationships' => [$default248, 'next_source.foreign_key_parent_external_unique.0', 'next:wp_term_relationships_stage#0->wp_term_taxonomy_stage:parent=taxonomy,slug:index=sqlite_autoindex_wp_term_taxonomy_stage_1:origin=u:inline_unique_parent_key'],
    'first row kind' => [$currentPageExternal248, '0.kind', 'foreign_key_parent_external_unique'],
    'first row status' => [$currentPageExternal248, '0.status', 'external_unique_parent_key'],
    'first row risk true' => [$currentPageExternal248, '0.drop_index_mismatch_risk', true],
    'first row parent index' => [$currentPageExternal248, '0.parent_index', 'wp_term_taxonomy_stage_taxonomy_slug_unique'],
    'first row origin created' => [$currentPageExternal248, '0.parent_index_origin', 'c'],
    'first row parent columns' => [$currentPageExternal248, '0.parent_columns', ['taxonomy', 'slug']],
    'first row child columns' => [$currentPageExternal248, '0.child_columns', ['taxonomy', 'slug']],
    'first row index columns' => [$currentPageExternal248, '0.parent_index_columns', ['taxonomy', 'slug']],
    'first row collations' => [$currentPageExternal248, '0.parent_index_collations', ['BINARY', 'BINARY']],
    'first row partial zero' => [$currentPageExternal248, '0.parent_index_partial', 0],
    'second row table' => [$currentPageExternal248, '1.table', 'wp_termmeta_stage'],
    'second row message' => [$currentPageExternal248, '1.message', 'foreign key wp_termmeta_stage->wp_term_taxonomy_stage parent key depends on external UNIQUE index wp_term_taxonomy_stage_taxonomy_slug_unique; dropping that index can create a foreign key mismatch'],
    'helper current count' => [static fn (): array => ['count' => count($currentExternal248())], 'count', 2],
    'helper next count' => [static fn (): array => ['count' => count($nextExternal248())], 'count', 2],
    'helper next inline status' => [$nextExternal248, '0.status', 'inline_unique_parent_key'],
    'helper next inline risk false' => [$nextExternal248, '0.drop_index_mismatch_risk', false],
    'helper missing returns empty' => [static fn (): array => ['count' => count($missingExternal248())], 'count', 0],
];

$tests = [];
foreach ($cases248 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey external parent unique current source next248 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt248): void {
        $t->same($expected, $valueAt248($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey external parent unique current source next248 paginates appended rows'] = static function (TestRunner $t) use ($page248): void {
    $full = $page248();
    $baseCount = $full['total'] - 4;
    $first = $page248(0, $baseCount);
    $second = $page248($baseCount, 3, $first['next']);
    $third = $page248($baseCount + 3, 2, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_parent_external_unique', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('external_unique_parent_key', $second['rows'][0]['status']);
    $t->same('external_unique_parent_key', $second['rows'][1]['status']);
    $t->same('inline_unique_parent_key', $second['rows'][2]['status']);
    $t->same('wp_termmeta_stage', $third['rows'][0]['table']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey external parent unique current source next248 ignores partial or missing parent keys'] = static function (TestRunner $t) use ($record248): void {
    $records = [
        $record248('table', 'parent', 'parent', 2, 'CREATE TABLE parent(taxonomy TEXT NOT NULL, slug TEXT NOT NULL)', 1),
        $record248('index', 'parent_partial_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_partial_unique ON parent(taxonomy, slug) WHERE slug IS NOT NULL', 2),
        $record248('table', 'child', 'child', 4, 'CREATE TABLE child(taxonomy TEXT NOT NULL, slug TEXT NOT NULL, FOREIGN KEY(taxonomy, slug) REFERENCES parent(taxonomy, slug))', 3),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248::externalParentKeyRows($records));
};

$tests['pragma index xinfo foreignkey external parent unique current source next248 rejects stale cursor'] = static function (TestRunner $t) use ($page248, $currentRecords248): void {
    $full = $page248();
    $first = $page248(0, $full['total'] - 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page248($full['total'] - 4, 2, $first['next'], $currentRecords248));
};

$tests['pragma index xinfo foreignkey external parent unique current source next248 rejects stale offset'] = static function (TestRunner $t) use ($page248): void {
    $full = $page248();
    $first = $page248(0, $full['total'] - 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page248($full['total'] - 3, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey external parent unique current source next248 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248::externalParentKeyRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey external parent unique current source next248 rejects invalid bounds'] = static function (TestRunner $t) use ($page248): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page248(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page248(0, 0));
};

return $tests;
