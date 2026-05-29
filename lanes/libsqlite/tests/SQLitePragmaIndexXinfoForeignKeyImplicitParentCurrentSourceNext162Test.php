<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record162 = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords162 = [
    $record162('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 1),
    $record162('index', 'sqlite_autoindex_wp_option_names_1', 'wp_option_names', 5, null, 2),
    $record162('table', 'wp_sites', 'wp_sites', 6, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 3),
    $record162('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names, FOREIGN KEY(site_id) REFERENCES wp_sites)', 4),
    $record162('index', 'wp_options_fk_lookup', 'wp_options', 8, 'CREATE INDEX wp_options_fk_lookup ON wp_options(site_id, option_name)', 5),
];
$nextRecords162 = $currentRecords162;

$currentTables162 = [
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'home', 'blog_id' => 1],
    ],
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'blog_id' => '1', 'site_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing_plugin', 'blog_id' => '2', 'site_id' => '99', 'autoload' => 'no'],
    ],
];
$nextTables162 = [
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'home', 'blog_id' => 1],
        ['name' => 'missing_plugin', 'blog_id' => 2],
    ],
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 99, 'blog_id' => 99, 'domain' => 'network.example.test'],
    ],
    'wp_options' => $currentTables162['wp_options'],
];

$page162 = static fn (
    int $offset = 0,
    int $limit = 162,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog159(
    $currentRecords162,
    $currentTables162,
    $nextRecords ?? $nextRecords162,
    $nextTables ?? $nextTables162,
    'PRAGMA index_xinfo(wp_options_fk_lookup)',
    $offset,
    $limit,
    $cursor,
);

$foreignKeys162 = static fn (?array $records = null): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog159($records ?? $currentRecords162);

$valueAt162 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default162 = static fn (): array => $page162();
$blocked162 = static fn (): array => $page162(nextTables: $currentTables162);

$cases162 = [
    'status ok after implicit parent repair' => [$default162, 'status', 'ok'],
    'total rows include current and next streams' => [$default162, 'total', 12],
    'all rows returned by default' => [$default162, 'count', 12],
    'current xinfo row count' => [$default162, 'current.index_xinfo', 3],
    'next xinfo row count' => [$default162, 'next_counts.index_xinfo', 3],
    'current admissions count' => [$default162, 'current.index_admissions', 2],
    'next admissions count' => [$default162, 'next_counts.index_admissions', 2],
    'current has no admission blocker' => [$default162, 'current.index_blockers', 0],
    'next has no admission blocker' => [$default162, 'next_counts.index_blockers', 0],
    'current fk violations' => [$default162, 'current.foreign_key_violations', 2],
    'next fk violations clear' => [$default162, 'next_counts.foreign_key_violations', 0],
    'delta fk clears two rows' => [$default162, 'delta.foreign_key_violations', -2],
    'delta total blockers clears two rows' => [$default162, 'delta.total_blockers', -2],
    'delta cleared flag' => [$default162, 'delta.cleared', true],
    'current target index' => [$default162, 'current.target_index', 'wp_options_fk_lookup'],
    'current target schema' => [$default162, 'current.target_schema', 'main'],
    'foreign key table list' => [$default162, 'current.foreign_key_tables', ['wp_options']],
    'parent index list includes implicit composite primary key' => [$default162, 'current.parent_indexes.0', 'sqlite_autoindex_wp_option_names_1'],
    'parent index list includes implicit rowid primary key' => [$default162, 'current.parent_indexes.1', 'rowid-primary-key'],
    'source foreign key extractor current' => [$default162, 'current_source.foreign_key_source', 'pragma_foreign_key_list'],
    'source foreign key extractor next' => [$default162, 'next_source.foreign_key_source', 'pragma_foreign_key_list'],
    'source derived current fks' => [$default162, 'current_source.derived_foreign_keys', 2],
    'source derived next fks' => [$default162, 'next_source.derived_foreign_keys', 2],
    'source normalized index xinfo sql' => [$default162, 'current_source.index_xinfo_sql', 'pragma index_xinfo(wp_options_fk_lookup)'],
    'next state ready' => [$default162, 'next_state.ready', true],
    'next state blocking empty' => [$default162, 'next_state.blocking', []],
    'complete page has no next cursor' => [$default162, 'next', null],
    'row0 is index xinfo' => [$default162, 'rows.0.kind', 'index_xinfo'],
    'row0 first lookup column' => [$default162, 'rows.0.name', 'site_id'],
    'row1 second lookup column' => [$default162, 'rows.1.name', 'option_name'],
    'row2 rowid aux column' => [$default162, 'rows.2.key', 0],
    'row3 implicit composite admission ok' => [$default162, 'rows.3.status', 'ok'],
    'row3 implicit composite parent columns' => [$default162, 'rows.3.columns', ['name', 'blog_id']],
    'row3 implicit composite index' => [$default162, 'rows.3.index', 'sqlite_autoindex_wp_option_names_1'],
    'row4 implicit rowid admission ok' => [$default162, 'rows.4.status', 'ok'],
    'row4 implicit rowid parent column' => [$default162, 'rows.4.columns', ['blog_id']],
    'row4 implicit rowid index' => [$default162, 'rows.4.index', 'rowid-primary-key'],
    'row5 composite violation rowid' => [$default162, 'rows.5.rowid', 2],
    'row5 composite violation parent' => [$default162, 'rows.5.parent', 'wp_option_names'],
    'row6 rowid violation rowid' => [$default162, 'rows.6.rowid', 2],
    'row6 rowid violation parent' => [$default162, 'rows.6.parent', 'wp_sites'],
    'row7 next side begins' => [$default162, 'rows.7.side', 'next'],
    'row10 next composite admission' => [$default162, 'rows.10.index', 'sqlite_autoindex_wp_option_names_1'],
    'row11 next rowid admission' => [$default162, 'rows.11.index', 'rowid-primary-key'],
    'blocked status remains blocked' => [$blocked162, 'status', 'blocked'],
    'blocked next ready false' => [$blocked162, 'next_state.ready', false],
    'blocked next blockers' => [$blocked162, 'next_state.blocking', ['foreign_key_check']],
    'blocked next violations' => [$blocked162, 'next_counts.foreign_key_violations', 2],
    'derived composite parent column 0' => [$foreignKeys162, '0.columns.0.parent', 'name'],
    'derived composite child column 0' => [$foreignKeys162, '0.columns.0.child', 'option_name'],
    'derived composite affinity 0' => [$foreignKeys162, '0.columns.0.affinity', 'text'],
    'derived composite collation 0' => [$foreignKeys162, '0.columns.0.collation', 'binary'],
    'derived composite parent column 1' => [$foreignKeys162, '0.columns.1.parent', 'blog_id'],
    'derived composite child column 1' => [$foreignKeys162, '0.columns.1.child', 'blog_id'],
    'derived composite affinity 1' => [$foreignKeys162, '0.columns.1.affinity', 'integer'],
    'derived rowid parent column' => [$foreignKeys162, '1.columns.0.parent', 'blog_id'],
    'derived rowid affinity' => [$foreignKeys162, '1.columns.0.affinity', 'integer'],
];

$tests = [];
foreach ($cases162 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey implicit parent current source next162 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt162): void {
        $t->same($expected, $valueAt162($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey implicit parent current source next162 paginates implicit parent stream'] = static function (TestRunner $t) use ($page162): void {
    $first = $page162(0, 4);
    $second = $page162(4, 4, $first['next']);
    $third = $page162(8, 4, $second['next']);
    $fourth = $page162(12, 4, $third['next']);

    $t->same(4, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $first['next']);
    $t->same('index_admission', $second['rows'][0]['kind']);
    $t->same('foreign_key_check', $second['rows'][1]['kind']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $fourth['next']);
};

$tests['pragma index xinfo foreignkey implicit parent current source next162 source changes with implicit parent ddl'] = static function (TestRunner $t) use ($page162, $currentRecords162, $record162): void {
    $first = $page162();
    $changed = $currentRecords162;
    $changed[0] = $record162('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT, blog_id NUMERIC, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 1);
    $second = $page162(nextRecords: $changed);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['records'] !== $second['next_source']['records']);
};

$tests['pragma index xinfo foreignkey implicit parent current source next162 rejects stale cursor after implicit parent data changes'] = static function (TestRunner $t) use ($page162, $currentTables162): void {
    $first = $page162(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page162(4, 4, $first['next'], nextTables: $currentTables162));
};

$tests['pragma index xinfo foreignkey implicit parent current source next162 rejects arity mismatch implicit parent'] = static function (TestRunner $t) use ($record162): void {
    $records = [
        $record162('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a TEXT, b TEXT, PRIMARY KEY(a, b)) WITHOUT ROWID', 1),
        $record162('table', 'child', 'child', 3, 'CREATE TABLE child(a TEXT REFERENCES parent)', 2),
    ];

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog159($records));
};

return $tests;
