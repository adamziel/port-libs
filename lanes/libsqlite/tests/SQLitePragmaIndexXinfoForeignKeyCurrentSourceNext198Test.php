<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext198;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record198 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords198 = [
    $record198('table', 'wp_option_scope', 'wp_option_scope', 4, 'CREATE TABLE wp_option_scope(blog_id INTEGER, option_name TEXT, locale TEXT, value TEXT, PRIMARY KEY(blog_id, option_name))', 1),
    $record198('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER, option_name TEXT, locale TEXT, FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_scope(blog_id, option_name))', 2),
    $record198('index', 'wp_options_lookup', 'wp_options', 6, 'CREATE INDEX wp_options_lookup ON wp_options(blog_id, option_name, locale)', 3),
];
$nextRecords198 = [
    $record198('table', 'wp_option_scope', 'wp_option_scope', 4, 'CREATE TABLE wp_option_scope(blog_id INTEGER, option_name TEXT, locale TEXT, value TEXT, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 1),
    $currentRecords198[1],
    $currentRecords198[2],
];
$mismatchedNextRecords198 = [
    $record198('table', 'wp_option_scope', 'wp_option_scope', 4, 'CREATE TABLE wp_option_scope(blog_id INTEGER, option_name TEXT, locale TEXT, value TEXT, PRIMARY KEY(blog_id, option_name, locale)) WITHOUT ROWID', 1),
    $currentRecords198[1],
    $currentRecords198[2],
];
$indexedNextRecords198 = [
    $currentRecords198[0],
    $currentRecords198[1],
    $record198('index', 'wp_option_scope_unique', 'wp_option_scope', 7, 'CREATE UNIQUE INDEX wp_option_scope_unique ON wp_option_scope(blog_id, option_name)', 4),
    $currentRecords198[2],
];

$tables198 = [
    'wp_option_scope' => [
        ['rowid' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'locale' => 'en_US', 'value' => 'https://example.test'],
    ],
    'wp_options' => [
        ['rowid' => 10, 'option_id' => 10, 'blog_id' => 1, 'option_name' => 'siteurl', 'locale' => 'en_US'],
    ],
];

$page198 = static fn (
    int $offset = 0,
    int $limit = 198,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext198::currentNextPageFromCatalog(
    $currentRecords198,
    $tables198,
    $nextRecords ?? $nextRecords198,
    $tables198,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt198 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$firstRow198 = static function (array $page, callable $predicate): array {
    foreach ($page['rows'] as $row) {
        if ($predicate($row)) {
            return $row;
        }
    }

    return [];
};

$default198 = static fn (): array => $page198();
$mismatched198 = static fn (): array => $page198(nextRecords: $mismatchedNextRecords198);
$indexed198 = static fn (): array => $page198(nextRecords: $indexedNextRecords198);
$rows198 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext198::withoutRowidParentKeyRows($nextRecords198, 'next');
$currentRows198 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext198::withoutRowidParentKeyRows($currentRecords198, 'current');
$tableValued198 = static fn (): array => $page198(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);
$decoratedParentKey198 = static fn (): array => $firstRow198(
    $page198(),
    static fn (array $row): bool => ($row['kind'] ?? null) === 'foreign_key_parent_key'
        && ($row['side'] ?? null) === 'next'
        && ($row['without_rowid_parent_key'] ?? false) === true,
);

$cases198 = [
    'status ok after without rowid parent repair' => [$default198, 'status', 'ok'],
    'default limit' => [$default198, 'limit', 198],
    'complete true' => [$default198, 'complete', true],
    'next null' => [$default198, 'next', null],
    'source id length' => [static fn (): array => ['len' => strlen($page198()['source_id'])], 'len', 64],
    'source kind current' => [$default198, 'current_source.foreign_key_without_rowid_parent_source', 'pragma_table_info_without_rowid_primary_key'],
    'source kind next' => [$default198, 'next_source.foreign_key_without_rowid_parent_source', 'pragma_table_info_without_rowid_primary_key'],
    'current without rowid rows zero' => [$default198, 'current.foreign_key_without_rowid_parent_rows', 0],
    'current covered keys zero' => [$default198, 'current.foreign_key_without_rowid_parent.covered_foreign_keys', 0],
    'current covered columns zero' => [$default198, 'current.foreign_key_without_rowid_parent.covered_columns', 0],
    'next without rowid rows two' => [$default198, 'next_counts.foreign_key_without_rowid_parent_rows', 2],
    'next covered keys one' => [$default198, 'next_counts.foreign_key_without_rowid_parent.covered_foreign_keys', 1],
    'next covered columns two' => [$default198, 'next_counts.foreign_key_without_rowid_parent.covered_columns', 2],
    'next composite columns two' => [$default198, 'next_counts.foreign_key_without_rowid_parent.composite_columns', 2],
    'delta rows two' => [$default198, 'delta.foreign_key_without_rowid_parent_rows', 2],
    'delta changed true' => [$default198, 'delta.foreign_key_without_rowid_parent_changed', true],
    'delta repaired true' => [$default198, 'delta.foreign_key_without_rowid_parent_repaired', true],
    'delta regressed false' => [$default198, 'delta.foreign_key_without_rowid_parent_regressed', false],
    'next ready true' => [$default198, 'next_state.ready', true],
    'next blocking empty' => [$default198, 'next_state.blocking', []],
    'base current blocker still visible' => [$default198, 'current.foreign_key_parent_key_columns.missing_parent_key', 2],
    'decorated next parent key index' => [$decoratedParentKey198, 'index', 'without-rowid-primary-key'],
    'decorated next parent key status' => [$decoratedParentKey198, 'status', 'ok'],
    'decorated next parent key marker' => [$decoratedParentKey198, 'without_rowid_parent_key', true],
    'without rowid row first kind' => [$rows198, '0.kind', 'foreign_key_without_rowid_parent_key'],
    'without rowid row first side' => [$rows198, '0.side', 'next'],
    'without rowid row first table' => [$rows198, '0.table', 'wp_options'],
    'without rowid row first parent' => [$rows198, '0.parent', 'wp_option_scope'],
    'without rowid row first from' => [$rows198, '0.from', 'blog_id'],
    'without rowid row first to' => [$rows198, '0.to', 'blog_id'],
    'without rowid row first index' => [$rows198, '0.index', 'without-rowid-primary-key'],
    'without rowid row first cid' => [$rows198, '0.index_cid', 0],
    'without rowid row first seqno' => [$rows198, '0.index_seqno', 1],
    'without rowid row first pk column' => [$rows198, '0.primary_key_columns.0', 'blog_id'],
    'without rowid row second from' => [$rows198, '1.from', 'option_name'],
    'without rowid row second to' => [$rows198, '1.to', 'option_name'],
    'without rowid row second cid' => [$rows198, '1.index_cid', 1],
    'without rowid row second pk column' => [$rows198, '1.primary_key_columns.1', 'option_name'],
    'without rowid current helper empty' => [static fn (): array => ['count' => count($currentRows198())], 'count', 0],
    'mismatched without rowid remains blocked' => [$mismatched198, 'status', 'blocked'],
    'mismatched next rows zero' => [$mismatched198, 'next_counts.foreign_key_without_rowid_parent_rows', 0],
    'mismatched next blocking parent unique' => [$mismatched198, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'indexed next remains ok' => [$indexed198, 'status', 'ok'],
    'indexed next without rowid rows zero' => [$indexed198, 'next_counts.foreign_key_without_rowid_parent_rows', 0],
    'indexed next full parent rows two' => [$indexed198, 'next_counts.foreign_key_parent_key_columns.mapped', 2],
    'table valued preserved' => [$tableValued198, 'current_source.table_valued_index_xinfo', true],
    'table valued without rowid rows' => [$tableValued198, 'next_counts.foreign_key_without_rowid_parent_rows', 2],
];

$tests = [];
foreach ($cases198 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey without rowid parent current source next198 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt198): void {
        $t->same($expected, $valueAt198($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey without rowid parent current source next198 paginates appended rows'] = static function (TestRunner $t) use ($page198): void {
    $first = $page198(0, 26);
    $second = $page198(26, 2, $first['next']);

    $t->same(26, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 26], $first['next']);
    $t->same('foreign_key_without_rowid_parent_key', $second['rows'][0]['kind']);
    $t->same('blog_id', $second['rows'][0]['to']);
    $t->same('foreign_key_without_rowid_parent_key', $second['rows'][1]['kind']);
    $t->same(null, $second['next']);
};

$tests['pragma index xinfo foreignkey without rowid parent current source next198 source changes when without rowid parent appears'] = static function (TestRunner $t) use ($page198, $mismatchedNextRecords198): void {
    $matched = $page198();
    $mismatched = $page198(nextRecords: $mismatchedNextRecords198);

    $t->same(true, $matched['source_id'] !== $mismatched['source_id']);
    $t->same(true, $matched['delta']['foreign_key_without_rowid_parent_changed']);
    $t->same(false, $mismatched['delta']['foreign_key_without_rowid_parent_repaired']);
};

$tests['pragma index xinfo foreignkey without rowid parent current source next198 rejects stale cursor'] = static function (TestRunner $t) use ($page198, $mismatchedNextRecords198): void {
    $first = $page198(0, 26);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page198(26, 2, $first['next'], nextRecords: $mismatchedNextRecords198));
};

$tests['pragma index xinfo foreignkey without rowid parent current source next198 rejects stale offset cursor'] = static function (TestRunner $t) use ($page198): void {
    $first = $page198(0, 26);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page198(27, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey without rowid parent current source next198 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext198::withoutRowidParentKeyRows([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey without rowid parent current source next198 rejects negative offset'] = static function (TestRunner $t) use ($page198): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page198(offset: -1));
};

$tests['pragma index xinfo foreignkey without rowid parent current source next198 rejects zero limit'] = static function (TestRunner $t) use ($page198): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page198(limit: 0));
};

return $tests;
