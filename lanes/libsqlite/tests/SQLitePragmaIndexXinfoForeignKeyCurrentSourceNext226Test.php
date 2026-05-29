<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record226 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords226 = [
    $record226('table', 'wp_term_relationships_stage', 'wp_term_relationships_stage', 2, "CREATE TABLE wp_term_relationships_stage(
        object_id INTEGER NOT NULL,
        term_taxonomy_id INTEGER NOT NULL,
        site_id INTEGER NOT NULL,
        FOREIGN KEY(term_taxonomy_id) REFERENCES wp_term_taxonomy(term_taxonomy_id) ON DELETE CASCADE,
        FOREIGN KEY(site_id, term_taxonomy_id) REFERENCES wp_network_terms(site_id, term_taxonomy_id) ON UPDATE CASCADE
    )", 1),
    $record226('index', 'wp_term_relationships_stage_fk', 'wp_term_relationships_stage', 3, 'CREATE INDEX wp_term_relationships_stage_fk ON wp_term_relationships_stage(term_taxonomy_id)', 2),
];

$nextRecords226 = [
    $record226('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 4, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, taxonomy TEXT NOT NULL)', 3),
    $record226('table', 'wp_network_terms', 'wp_network_terms', 5, 'CREATE TABLE wp_network_terms(site_id INTEGER NOT NULL, term_taxonomy_id INTEGER NOT NULL, PRIMARY KEY(site_id, term_taxonomy_id))', 4),
    ...$currentRecords226,
];

$unrepairedRecords226 = [
    $record226('table', 'wp_term_taxonomy', 'wp_term_taxonomy', 4, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, taxonomy TEXT NOT NULL)', 3),
    ...$currentRecords226,
];

$page226 = static fn (
    int $offset = 0,
    int $limit = 100,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page226(
    $currentRecords226,
    $nextRecords ?? $nextRecords226,
    'PRAGMA main.index_xinfo(wp_term_relationships_stage_fk)',
    'PRAGMA main.foreign_key_list(wp_term_relationships_stage)',
    $offset,
    $limit,
    $resume,
);

$valueAt226 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default226 = static fn (): array => $page226();
$unrepaired226 = static fn (): array => $page226(nextRecords: $unrepairedRecords226);
$currentMissing226 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingParentTableRows226($currentRecords226);
$nextMissing226 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingParentTableRows226($nextRecords226, 'next');

$cases226 = [
    'status ok' => [$default226, 'status', 'ok'],
    'operation marker' => [$default226, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next226'],
    'source id length' => [static fn (): array => ['len' => strlen($page226()['source_id'])], 'len', 64],
    'offset default' => [$default226, 'offset', 0],
    'limit default' => [$default226, 'limit', 100],
    'dependency appended' => [$default226, 'dependencies.9', 'sqlite-pragma-foreign-key-parent-table-catalog-resolution'],
    'base permutation retained' => [$default226, 'current.foreign_key_parent_key_permutation.rows', 0],
    'missing parent source current' => [$default226, 'current_source.foreign_key_missing_parent_table_source', 'pragma_foreign_key_list_parent_table_plus_schema_table_catalog'],
    'missing parent source next' => [$default226, 'next_source.foreign_key_missing_parent_table_source', 'pragma_foreign_key_list_parent_table_plus_schema_table_catalog'],
    'current missing rows' => [$default226, 'current.foreign_key_missing_parent_table.rows', 3],
    'current missing blockers' => [$default226, 'current.foreign_key_missing_parent_table.missing_parent_table', 3],
    'current missing foreign keys' => [$default226, 'current.foreign_key_missing_parent_table.foreign_keys', 2],
    'current missing parent tables' => [$default226, 'current.foreign_key_missing_parent_table.parent_tables', 2],
    'next missing rows' => [$default226, 'next_counts.foreign_key_missing_parent_table.rows', 0],
    'next missing blockers' => [$default226, 'next_counts.foreign_key_missing_parent_table.missing_parent_table', 0],
    'next missing foreign keys' => [$default226, 'next_counts.foreign_key_missing_parent_table.foreign_keys', 0],
    'delta rows negative' => [$default226, 'delta.foreign_key_missing_parent_table_rows', -3],
    'delta blockers negative' => [$default226, 'delta.foreign_key_missing_parent_table_blockers', -3],
    'delta repaired true' => [$default226, 'delta.foreign_key_missing_parent_table_repaired', true],
    'delta changed true' => [$default226, 'delta.foreign_key_missing_parent_table_changed', true],
    'current summary first' => [$default226, 'current_source.foreign_key_missing_parent_table.0', 'current:wp_term_relationships_stage#0.0:term_taxonomy_id->wp_term_taxonomy.term_taxonomy_id:missing_parent_table'],
    'current summary second' => [$default226, 'current_source.foreign_key_missing_parent_table.1', 'current:wp_term_relationships_stage#1.0:site_id->wp_network_terms.site_id:missing_parent_table'],
    'current summary third' => [$default226, 'current_source.foreign_key_missing_parent_table.2', 'current:wp_term_relationships_stage#1.1:term_taxonomy_id->wp_network_terms.term_taxonomy_id:missing_parent_table'],
    'next summary empty' => [$default226, 'next_source.foreign_key_missing_parent_table', []],
    'first visible appended row kind' => [$default226, 'rows.31.kind', 'foreign_key_missing_parent_table'],
    'first visible appended row status' => [$default226, 'rows.31.status', 'missing_parent_table'],
    'first visible appended parent' => [$default226, 'rows.31.parent', 'wp_network_terms'],
    'first visible appended from column' => [$default226, 'rows.31.from', 'site_id'],
    'first visible appended to column' => [$default226, 'rows.31.to', 'site_id'],
    'second visible appended parent' => [$default226, 'rows.32.parent', 'wp_network_terms'],
    'second visible appended seq' => [$default226, 'rows.32.seq', 1],
    'second visible appended available table count' => [static fn (): array => ['count' => count($page226()['rows'][32]['available_parent_tables'])], 'count', 1],
    'unrepaired next rows' => [$unrepaired226, 'next_counts.foreign_key_missing_parent_table.rows', 2],
    'unrepaired next blockers' => [$unrepaired226, 'next_counts.foreign_key_missing_parent_table.missing_parent_table', 2],
    'unrepaired repaired false' => [$unrepaired226, 'delta.foreign_key_missing_parent_table_repaired', false],
    'helper current kind' => [$currentMissing226, '0.kind', 'foreign_key_missing_parent_table'],
    'helper current first parent' => [$currentMissing226, '0.parent', 'wp_term_taxonomy'],
    'helper current second table' => [$currentMissing226, '1.table', 'wp_term_relationships_stage'],
    'helper current third to' => [$currentMissing226, '2.to', 'term_taxonomy_id'],
    'helper next empty' => [static fn (): array => ['count' => count($nextMissing226())], 'count', 0],
];

$tests = [];
foreach ($cases226 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey missing parent table current source next226 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt226): void {
        $t->same($expected, $valueAt226($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey missing parent table current source next226 paginates appended rows'] = static function (TestRunner $t) use ($page226): void {
    $first = $page226(0, 30);
    $second = $page226(30, 2, $first['next']);
    $third = $page226(32, 1, $second['next']);

    $t->same(30, $first['count']);
    $t->same('foreign_key_missing_parent_table', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 30], $first['next']);
    $t->same('wp_term_taxonomy', $second['rows'][0]['parent']);
    $t->same('wp_network_terms', $second['rows'][1]['parent']);
    $t->same('term_taxonomy_id', $third['rows'][0]['from']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey missing parent table current source next226 handles implicit parent column rows'] = static function (TestRunner $t) use ($record226): void {
    $records = [
        $record226('table', 'child', 'child', 2, 'CREATE TABLE child(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES missing_parent(id))', 1),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingParentTableRows226($records);
    $t->same(1, count($rows));
    $t->same('id', $rows[0]['to']);
    $t->same('parent_id', $rows[0]['from']);
    $t->same('missing_parent_table', $rows[0]['status']);
};

$tests['pragma index xinfo foreignkey missing parent table current source next226 ignores present parent regardless of case'] = static function (TestRunner $t) use ($record226): void {
    $records = [
        $record226('table', 'Wp_Parents', 'Wp_Parents', 2, 'CREATE TABLE Wp_Parents(id INTEGER PRIMARY KEY)', 1),
        $record226('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES wp_parents(id))', 2),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingParentTableRows226($records));
};

$tests['pragma index xinfo foreignkey missing parent table current source next226 rejects stale cursor'] = static function (TestRunner $t) use ($page226, $unrepairedRecords226): void {
    $first = $page226(0, 31);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page226(31, 1, $first['next'], $unrepairedRecords226));
};

$tests['pragma index xinfo foreignkey missing parent table current source next226 rejects stale offset'] = static function (TestRunner $t) use ($page226): void {
    $first = $page226(0, 31);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page226(32, 1, $first['next']));
};

$tests['pragma index xinfo foreignkey missing parent table current source next226 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::missingParentTableRows226([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey missing parent table current source next226 rejects invalid bounds'] = static function (TestRunner $t) use ($page226): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page226(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page226(0, 0));
};

return $tests;
