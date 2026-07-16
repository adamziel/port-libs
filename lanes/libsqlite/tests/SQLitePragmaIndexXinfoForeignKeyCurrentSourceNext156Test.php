<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record156 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords156 = [
    $record156('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
    $record156('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 2),
    $record156('index', 'wp_option_names_name', 'wp_option_names', 6, 'CREATE INDEX wp_option_names_name ON wp_option_names(name COLLATE BINARY)', 3),
    $record156('index', 'wp_options_name', 'wp_options', 7, 'CREATE INDEX wp_options_name ON wp_options(option_name)', 4),
];
$nextRecords156 = [
    $record156('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
    $record156('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 2),
    $record156('index', 'wp_option_names_name', 'wp_option_names', 6, 'CREATE UNIQUE INDEX wp_option_names_name ON wp_option_names(name COLLATE NOCASE)', 3),
    $record156('index', 'wp_options_name', 'wp_options', 7, 'CREATE INDEX wp_options_name ON wp_options(option_name)', 4),
];
$foreignKeys156 = [
    ['id' => 156, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
        ['child' => 'option_name', 'parent' => 'name'],
    ]],
];
$currentTables156 = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 'missing-option', 'option_name' => 'missing_siteurl', 'autoload' => 'no'],
    ],
];
$nextTables156 = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl'],
        ['rowid' => 2, 'name' => 'missing_siteurl'],
    ],
    'wp_options' => $currentTables156['wp_options'],
];

$page156 = static fn (
    int $offset = 0,
    int $limit = 156,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_option_names_name)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPage156(
    $currentRecords156,
    $foreignKeys156,
    $currentTables156,
    $nextRecords ?? $nextRecords156,
    $foreignKeys156,
    $nextTables ?? $nextTables156,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt156 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default156 = static fn (): array => $page156();
$blockedNext156 = static fn (): array => $page156(nextRecords: $currentRecords156, nextTables: $currentTables156);
$cases156 = [
    'status ok after next repair' => [$default156, 'status', 'ok'],
    'default limit' => [$default156, 'limit', 156],
    'total rows current plus next' => [$default156, 'total', 7],
    'count rows' => [$default156, 'count', 7],
    'complete true' => [$default156, 'complete', true],
    'next null' => [$default156, 'next', null],
    'next ready true' => [$default156, 'next_state.ready', true],
    'next blocking empty' => [$default156, 'next_state.blocking', []],
    'source id length' => [static fn (): array => ['len' => strlen($page156()['source_id'])], 'len', 64],
    'current source records hash length' => [static fn (): array => ['len' => strlen($page156()['current_source']['records'])], 'len', 64],
    'next source tables hash length' => [static fn (): array => ['len' => strlen($page156()['next_source']['tables'])], 'len', 64],
    'normalized index sql' => [$default156, 'current_source.index_xinfo_sql', 'pragma index_xinfo(wp_option_names_name)'],
    'table valued false' => [$default156, 'current_source.table_valued_index_xinfo', false],
    'current xinfo rows' => [$default156, 'current.index_xinfo', 2],
    'current admissions' => [$default156, 'current.index_admissions', 1],
    'current blockers' => [$default156, 'current.index_blockers', 1],
    'current fk violations' => [$default156, 'current.foreign_key_violations', 1],
    'current total blockers' => [$default156, 'current.total_blockers', 2],
    'current target schema' => [$default156, 'current.target_schema', 'main'],
    'current target index' => [$default156, 'current.target_index', 'wp_option_names_name'],
    'current fk tables' => [$default156, 'current.foreign_key_tables', ['wp_options']],
    'next xinfo rows' => [$default156, 'next_counts.index_xinfo', 2],
    'next admissions' => [$default156, 'next_counts.index_admissions', 1],
    'next blockers clear' => [$default156, 'next_counts.index_blockers', 0],
    'next fk clear' => [$default156, 'next_counts.foreign_key_violations', 0],
    'next total blockers clear' => [$default156, 'next_counts.total_blockers', 0],
    'next parent index' => [$default156, 'next_counts.parent_indexes', ['wp_option_names_name']],
    'delta xinfo stable' => [$default156, 'delta.index_xinfo', 0],
    'delta admissions stable' => [$default156, 'delta.index_admissions', 0],
    'delta blockers cleared' => [$default156, 'delta.index_blockers', -1],
    'delta fk cleared' => [$default156, 'delta.foreign_key_violations', -1],
    'delta total blockers' => [$default156, 'delta.total_blockers', -2],
    'delta cleared true' => [$default156, 'delta.cleared', true],
    'row0 side current' => [$default156, 'rows.0.side', 'current'],
    'row0 kind index xinfo' => [$default156, 'rows.0.kind', 'index_xinfo'],
    'row0 phase index xinfo' => [$default156, 'rows.0.phase', 'index_xinfo'],
    'row0 name' => [$default156, 'rows.0.name', 'name'],
    'row0 collation binary' => [$default156, 'rows.0.coll', 'BINARY'],
    'row2 admission blocked' => [$default156, 'rows.2.kind', 'index_admission'],
    'row2 status blocked' => [$default156, 'rows.2.status', 'blocked'],
    'row2 source' => [$default156, 'rows.2.source', 'foreign_key_parent_index'],
    'row3 fk violation' => [$default156, 'rows.3.kind', 'foreign_key_check'],
    'row3 rowid' => [$default156, 'rows.3.rowid', 'missing-option'],
    'row4 side next' => [$default156, 'rows.4.side', 'next'],
    'row4 collation nocase' => [$default156, 'rows.4.coll', 'NOCASE'],
    'row6 admission ok' => [$default156, 'rows.6.status', 'ok'],
    'row6 admission index' => [$default156, 'rows.6.index', 'wp_option_names_name'],
    'blocked next status' => [$blockedNext156, 'status', 'blocked'],
    'blocked next ready false' => [$blockedNext156, 'next_state.ready', false],
    'blocked next blockers' => [$blockedNext156, 'next_state.blocking', ['foreign_key_parent_unique_index', 'foreign_key_check']],
    'blocked next index blockers' => [$blockedNext156, 'next_counts.index_blockers', 1],
    'blocked next fk violations' => [$blockedNext156, 'next_counts.foreign_key_violations', 1],
];

$tests = [];
foreach ($cases156 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next156 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt156): void {
        $t->same($expected, $valueAt156($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next156 paginates stable current next source'] = static function (TestRunner $t) use ($page156): void {
    $first = $page156(0, 3);
    $second = $page156(3, 3, $first['next']);
    $third = $page156(6, 3, $second['next']);

    $t->same(3, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 3], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('next', $second['rows'][1]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next156 source changes with next records'] = static function (TestRunner $t) use ($page156, $currentRecords156): void {
    $first = $page156();
    $second = $page156(nextRecords: $currentRecords156);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['records'] !== $second['next_source']['records']);
};

$tests['pragma index xinfo foreignkey current source next156 source changes with next tables'] = static function (TestRunner $t) use ($page156, $currentTables156): void {
    $first = $page156();
    $second = $page156(nextTables: $currentTables156);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['tables'] !== $second['next_source']['tables']);
};

$tests['pragma index xinfo foreignkey current source next156 table-valued SQL source'] = static function (TestRunner $t) use ($page156): void {
    $result = $page156(indexSql: "pragma_index_xinfo('wp_option_names_name')", tableValued: true);

    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same("pragma_index_xinfo('wp_option_names_name')", $result['current_source']['index_xinfo_sql']);
    $t->same('ok', $result['status']);
};

$tests['pragma index xinfo foreignkey current source next156 rejects stale source cursor'] = static function (TestRunner $t) use ($page156, $currentRecords156): void {
    $first = $page156(0, 3);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page156(3, 3, $first['next'], nextRecords: $currentRecords156));
};

$tests['pragma index xinfo foreignkey current source next156 rejects stale offset cursor'] = static function (TestRunner $t) use ($page156): void {
    $first = $page156(0, 3);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page156(4, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next156 rejects negative offset'] = static function (TestRunner $t) use ($page156): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page156(-1));
};

$tests['pragma index xinfo foreignkey current source next156 rejects zero limit'] = static function (TestRunner $t) use ($page156): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page156(0, 0));
};

$tests['pragma index xinfo foreignkey current source next156 rejects non xinfo pragma'] = static function (TestRunner $t) use ($page156): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page156(indexSql: 'PRAGMA index_list(wp_option_names)'));
};

return $tests;
