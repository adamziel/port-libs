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

$record239 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords239 = [
    $record239('table', 'wp_terms_parent', 'wp_terms_parent', 2, 'CREATE TABLE wp_terms_parent(term_slug TEXT NOT NULL, taxonomy TEXT NOT NULL, locale TEXT NOT NULL, term_id INTEGER PRIMARY KEY, UNIQUE(term_slug, taxonomy))', 1),
    $record239('index', 'sqlite_autoindex_wp_terms_parent_1', 'wp_terms_parent', 3, null, 2),
    $record239('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, 'CREATE TABLE wp_termmeta_import(meta_id INTEGER PRIMARY KEY, term_slug TEXT NOT NULL, taxonomy TEXT NOT NULL, FOREIGN KEY(term_slug, taxonomy) REFERENCES wp_terms_parent(term_slug, taxonomy))', 3),
];

$nextRecords239 = [
    $record239('table', 'wp_terms_parent', 'wp_terms_parent', 2, 'CREATE TABLE wp_terms_parent(term_slug TEXT NOT NULL, taxonomy TEXT NOT NULL, locale TEXT NOT NULL, term_id INTEGER, PRIMARY KEY(term_slug, taxonomy), UNIQUE(locale)) WITHOUT ROWID', 1),
    $record239('index', 'sqlite_autoindex_wp_terms_parent_1', 'wp_terms_parent', 3, null, 2),
    $record239('index', 'sqlite_autoindex_wp_terms_parent_2', 'wp_terms_parent', 4, null, 3),
    $record239('table', 'wp_termmeta_import', 'wp_termmeta_import', 5, 'CREATE TABLE wp_termmeta_import(meta_id INTEGER PRIMARY KEY, locale TEXT NOT NULL, FOREIGN KEY(locale) REFERENCES wp_terms_parent(locale))', 4),
];

$blockedRecords239 = [
    $record239('table', 'wp_terms_parent', 'wp_terms_parent', 2, 'CREATE TABLE wp_terms_parent(term_slug TEXT NOT NULL, taxonomy TEXT NOT NULL, locale TEXT NOT NULL, term_id INTEGER PRIMARY KEY, UNIQUE(term_slug, taxonomy, locale))', 1),
    $record239('index', 'sqlite_autoindex_wp_terms_parent_1', 'wp_terms_parent', 3, null, 2),
    $currentRecords239[2],
];

$page239 = static fn (
    int $offset = 0,
    int $limit = 180,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page239(
    $currentRecords239,
    $nextRecords ?? $nextRecords239,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_terms_parent_1)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt239 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default239 = static fn (): array => $page239();
$blocked239 = static fn (): array => $page239(nextRecords: $blockedRecords239);
$currentAux239 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentAuxiliaryIndexRows239($currentRecords239);
$nextAux239 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentAuxiliaryIndexRows239($nextRecords239, 'next');
$currentPageAux239 = static fn (): array => array_values(array_filter(
    $page239()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_auxiliary_index' && ($row['phase'] ?? null) === 'current',
));
$nextPageAux239 = static fn (): array => array_values(array_filter(
    $page239()['rows'],
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_auxiliary_index' && ($row['phase'] ?? null) === 'next',
));

$cases239 = [
    'status ok' => [$default239, 'status', 'ok'],
    'operation marker' => [$default239, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next239'],
    'source id length' => [static fn (): array => ['len' => strlen($page239()['source_id'])], 'len', 64],
    'offset default' => [$default239, 'offset', 0],
    'limit default' => [$default239, 'limit', 180],
    'dependency appended' => [$default239, 'dependencies.12', 'sqlite-pragma-index-xinfo-key0-foreign-key-parent-auxiliary'],
    'base quoted retained' => [$default239, 'current.foreign_key_parent_quoted_case.rows', 2],
    'aux source current' => [$default239, 'current_source.foreign_key_parent_auxiliary_index_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_key0_auxiliary_rows'],
    'aux source next' => [$default239, 'next_source.foreign_key_parent_auxiliary_index_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_key0_auxiliary_rows'],
    'current rows' => [$default239, 'current.foreign_key_parent_auxiliary_index.rows', 2],
    'current ignored count per column' => [$default239, 'current.foreign_key_parent_auxiliary_index.auxiliary_rows_ignored', 2],
    'current rowid auxiliary count' => [$default239, 'current.foreign_key_parent_auxiliary_index.rowid_auxiliary', 2],
    'current without rowid auxiliary zero' => [$default239, 'current.foreign_key_parent_auxiliary_index.without_rowid_primary_key_auxiliary', 0],
    'current missing zero' => [$default239, 'current.foreign_key_parent_auxiliary_index.missing_auxiliary_parent_unique_index', 0],
    'next rows' => [$default239, 'next_counts.foreign_key_parent_auxiliary_index.rows', 1],
    'next ignored count' => [$default239, 'next_counts.foreign_key_parent_auxiliary_index.auxiliary_rows_ignored', 2],
    'next rowid auxiliary zero' => [$default239, 'next_counts.foreign_key_parent_auxiliary_index.rowid_auxiliary', 0],
    'next without rowid auxiliary columns' => [$default239, 'next_counts.foreign_key_parent_auxiliary_index.without_rowid_primary_key_auxiliary', 2],
    'delta rows decreased' => [$default239, 'delta.foreign_key_parent_auxiliary_index_rows', -1],
    'delta ignored unchanged' => [$default239, 'delta.foreign_key_parent_auxiliary_index_rows_ignored', 0],
    'delta changed true' => [$default239, 'delta.foreign_key_parent_auxiliary_index_changed', true],
    'delta repaired false' => [$default239, 'delta.foreign_key_parent_auxiliary_index_repaired', false],
    'current summary rowid' => [$default239, 'current_source.foreign_key_parent_auxiliary_index.0', 'current:wp_termmeta_import#0.0:term_slug->wp_terms_parent.term_slug:sqlite_autoindex_wp_terms_parent_1:key=term_slug,taxonomy:aux=rowid:auxiliary_rows_ignored'],
    'next summary without rowid' => [$default239, 'next_source.foreign_key_parent_auxiliary_index.0', 'next:wp_termmeta_import#0.0:locale->wp_terms_parent.locale:sqlite_autoindex_wp_terms_parent_2:key=locale:aux=term_slug,taxonomy:auxiliary_rows_ignored'],
    'current row kind' => [$currentPageAux239, '0.kind', 'foreign_key_parent_auxiliary_index'],
    'current row status' => [$currentPageAux239, '0.status', 'auxiliary_rows_ignored'],
    'current row index' => [$currentPageAux239, '0.parent_unique_index', 'sqlite_autoindex_wp_terms_parent_1'],
    'current row key columns' => [$currentPageAux239, '0.index_key_columns', ['term_slug', 'taxonomy']],
    'current row auxiliary column' => [$currentPageAux239, '0.auxiliary_columns', ['rowid']],
    'current row auxiliary cid' => [$currentPageAux239, '0.auxiliary_cids', [-1]],
    'current second index column' => [$currentPageAux239, '1.index_column', 'taxonomy'],
    'next row phase' => [$nextPageAux239, '0.phase', 'next'],
    'next row auxiliary column' => [$nextPageAux239, '0.auxiliary_columns', ['term_slug', 'taxonomy']],
    'next row auxiliary cid' => [$nextPageAux239, '0.auxiliary_cids', [0, 1]],
    'blocked missing rows' => [$blocked239, 'next_counts.foreign_key_parent_auxiliary_index.missing_auxiliary_parent_unique_index', 2],
    'blocked misclassified rows' => [$blocked239, 'next_counts.foreign_key_parent_auxiliary_index.auxiliary_rows_misclassified', 2],
    'blocked ignored zero' => [$blocked239, 'next_counts.foreign_key_parent_auxiliary_index.auxiliary_rows_ignored', 0],
    'blocked changed true' => [$blocked239, 'delta.foreign_key_parent_auxiliary_index_changed', true],
    'helper current first status' => [$currentAux239, '0.status', 'auxiliary_rows_ignored'],
    'helper current first aux' => [$currentAux239, '0.auxiliary_columns', ['rowid']],
    'helper current second column' => [$currentAux239, '1.index_column', 'taxonomy'],
    'helper next first aux' => [$nextAux239, '0.auxiliary_columns', ['term_slug', 'taxonomy']],
    'helper next first cid' => [$nextAux239, '0.auxiliary_cids', [0, 1]],
];

$tests = [];
foreach ($cases239 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey auxiliary parent index current source next239 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt239): void {
        $t->same($expected, $valueAt239($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey auxiliary parent index current source next239 paginates appended rows'] = static function (TestRunner $t) use ($page239): void {
    $full = $page239();
    $baseCount = $full['total'] - 3;
    $first = $page239(0, $baseCount);
    $second = $page239($baseCount, 2, $first['next']);
    $third = $page239($baseCount + 2, 2, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_parent_auxiliary_index', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same(['rowid'], $second['rows'][0]['auxiliary_columns']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(['term_slug', 'taxonomy'], $third['rows'][0]['auxiliary_columns']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey auxiliary parent index current source next239 ignores rowid table key zero row'] = static function (TestRunner $t) use ($currentAux239): void {
    $rows = $currentAux239();

    $t->same(2, count($rows));
    $t->same('auxiliary_rows_ignored', $rows[0]['status']);
    $t->same(['term_slug', 'taxonomy'], $rows[0]['index_key_columns']);
    $t->same(['rowid'], $rows[0]['auxiliary_columns']);
    $t->same([-1], $rows[0]['auxiliary_cids']);
};

$tests['pragma index xinfo foreignkey auxiliary parent index current source next239 ignores without rowid primary key tail'] = static function (TestRunner $t) use ($nextAux239): void {
    $rows = $nextAux239();

    $t->same(1, count($rows));
    $t->same('auxiliary_rows_ignored', $rows[0]['status']);
    $t->same(['locale'], $rows[0]['index_key_columns']);
    $t->same(['term_slug', 'taxonomy'], $rows[0]['auxiliary_columns']);
    $t->same([0, 1], $rows[0]['auxiliary_cids']);
};

$tests['pragma index xinfo foreignkey auxiliary parent index current source next239 reports wider key as missing auxiliary match'] = static function (TestRunner $t) use ($record239): void {
    $records = [
        $record239('table', 'wp_parent', 'wp_parent', 2, 'CREATE TABLE wp_parent(slug TEXT, taxonomy TEXT, locale TEXT, UNIQUE(slug, taxonomy, locale))', 1),
        $record239('index', 'sqlite_autoindex_wp_parent_1', 'wp_parent', 3, null, 2),
        $record239('table', 'wp_child', 'wp_child', 4, 'CREATE TABLE wp_child(slug TEXT, taxonomy TEXT, FOREIGN KEY(slug, taxonomy) REFERENCES wp_parent(slug, taxonomy))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentAuxiliaryIndexRows239($records);
    $t->same(2, count($rows));
    $t->same('missing_auxiliary_parent_unique_index', $rows[0]['status']);
    $t->same([], $rows[0]['auxiliary_columns']);
};

$tests['pragma index xinfo foreignkey auxiliary parent index current source next239 rejects stale cursor'] = static function (TestRunner $t) use ($page239, $blockedRecords239): void {
    $full = $page239();
    $first = $page239(0, $full['total'] - 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page239($full['total'] - 4, 2, $first['next'], $blockedRecords239));
};

$tests['pragma index xinfo foreignkey auxiliary parent index current source next239 rejects stale offset'] = static function (TestRunner $t) use ($page239): void {
    $full = $page239();
    $first = $page239(0, $full['total'] - 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page239($full['total'] - 3, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey auxiliary parent index current source next239 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentAuxiliaryIndexRows239([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey auxiliary parent index current source next239 rejects invalid bounds'] = static function (TestRunner $t) use ($page239): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page239(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page239(0, 0));
};

return $tests;
