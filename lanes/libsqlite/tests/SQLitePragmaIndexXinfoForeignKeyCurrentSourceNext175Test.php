<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record175 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords175 = [
    $record175('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record175('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record175('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record175('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites ON UPDATE CASCADE ON DELETE RESTRICT, option_name TEXT, blog_id TEXT, fallback_name TEXT, autoload TEXT, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names(name, blog_id) ON UPDATE SET DEFAULT ON DELETE CASCADE MATCH custom, CONSTRAINT fk_fallback FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) DEFERRABLE INITIALLY DEFERRED)', 4),
    $record175('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
    $record175('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 9, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 6),
    $record175('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id, fallback_name)', 7),
];
$nextRecords175 = $currentRecords175;

$currentTables175 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'site_id' => '1', 'option_name' => 'siteurl', 'blog_id' => '1', 'fallback_name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'site_id' => '404', 'option_name' => 'home', 'blog_id' => '1', 'fallback_name' => 'missing_default', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'site_id' => '1', 'option_name' => 'plugin_missing', 'blog_id' => '2', 'fallback_name' => 'siteurl', 'autoload' => 'no'],
    ],
];
$nextTables175 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'home', 'blog_id' => 1],
        ['name' => 'plugin_missing', 'blog_id' => 2],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_options' => $currentTables175['wp_options'],
];

$page175 = static fn (
    int $offset = 0,
    int $limit = 175,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog175(
    $currentRecords175,
    $currentTables175,
    $nextRecords ?? $nextRecords175,
    $nextTables ?? $nextTables175,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt175 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default175 = static fn (): array => $page175();
$blocked175 = static fn (): array => $page175(nextTables: $currentTables175);
$fkRows175 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeyListRows175($currentRecords175);

$cases175 = [
    'status ok after FK repair' => [$default175, 'status', 'ok'],
    'limit default' => [$default175, 'limit', 175],
    'total rows include fk list current and next' => [$default175, 'total', 26],
    'count rows include fk list current and next' => [$default175, 'count', 26],
    'complete true' => [$default175, 'complete', true],
    'next null' => [$default175, 'next', null],
    'current fk list source' => [$default175, 'current_source.foreign_key_list_row_source', 'pragma_foreign_key_list_column_sequences'],
    'next fk list source' => [$default175, 'next_source.foreign_key_list_row_source', 'pragma_foreign_key_list_column_sequences'],
    'current fk list rows' => [$default175, 'current.foreign_key_list_rows', 4],
    'next fk list rows' => [$default175, 'next_counts.foreign_key_list_rows', 4],
    'current fk list column rows' => [$default175, 'current.foreign_key_list_columns.rows', 4],
    'current composite fk columns' => [$default175, 'current.foreign_key_list_columns.composite_columns', 2],
    'current resolved parent columns' => [$default175, 'current.foreign_key_list_columns.implicit_parent_columns', 4],
    'current without rowid child columns' => [$default175, 'current.foreign_key_list_columns.without_rowid_children', 0],
    'delta fk list unchanged' => [$default175, 'delta.foreign_key_list_rows', 0],
    'delta fk list changed false' => [$default175, 'delta.foreign_key_list_changed', false],
    'deferral still present' => [$default175, 'current.foreign_key_deferrals.initially_deferred', 1],
    'actions still present' => [$default175, 'current.foreign_key_actions.on_update:cascade', 1],
    'current fk violations' => [$default175, 'current.foreign_key_violations', 4],
    'next fk violations clear' => [$default175, 'next_counts.foreign_key_violations', 0],
    'delta cleared true' => [$default175, 'delta.cleared', true],
    'next ready true' => [$default175, 'next_state.ready', true],
    'row18 current site fk list' => [$default175, 'rows.18.kind', 'foreign_key_list'],
    'row18 id zero' => [$default175, 'rows.18.id', 0],
    'row18 seq zero' => [$default175, 'rows.18.seq', 0],
    'row18 child column' => [$default175, 'rows.18.from', 'site_id'],
    'row18 implicit parent key' => [$default175, 'rows.18.to', 'blog_id'],
    'row18 parent' => [$default175, 'rows.18.parent', 'wp_sites'],
    'row18 affinity integer' => [$default175, 'rows.18.affinity', 'integer'],
    'row18 collation binary' => [$default175, 'rows.18.collation', 'binary'],
    'row18 action update cascade' => [$default175, 'rows.18.on_update', 'CASCADE'],
    'row18 action delete restrict' => [$default175, 'rows.18.on_delete', 'RESTRICT'],
    'row19 composite seq zero' => [$default175, 'rows.19.seq', 0],
    'row19 composite child option' => [$default175, 'rows.19.from', 'option_name'],
    'row19 composite parent name' => [$default175, 'rows.19.to', 'name'],
    'row19 composite collation nocase' => [$default175, 'rows.19.collation', 'nocase'],
    'row20 composite seq one' => [$default175, 'rows.20.seq', 1],
    'row20 composite child blog' => [$default175, 'rows.20.from', 'blog_id'],
    'row20 composite parent blog' => [$default175, 'rows.20.to', 'blog_id'],
    'row20 composite action' => [$default175, 'rows.20.on_update', 'SET DEFAULT'],
    'row21 deferred fallback child' => [$default175, 'rows.21.from', 'fallback_name'],
    'row21 deferred fallback parent' => [$default175, 'rows.21.to', 'default_name'],
    'row21 deferred action' => [$default175, 'rows.21.on_delete', 'NO ACTION'],
    'row22 next site fk side' => [$default175, 'rows.22.side', 'next'],
    'row23 next composite option side' => [$default175, 'rows.23.side', 'next'],
    'row24 next composite seq one' => [$default175, 'rows.24.seq', 1],
    'row25 next fallback parent' => [$default175, 'rows.25.to', 'default_name'],
    'blocked status' => [$blocked175, 'status', 'blocked'],
    'blocked ready false' => [$blocked175, 'next_state.ready', false],
    'blocked next fk violations' => [$blocked175, 'next_counts.foreign_key_violations', 4],
    'blocked fk list rows unchanged' => [$blocked175, 'next_counts.foreign_key_list_rows', 4],
    'fk row count helper' => [$fkRows175, '0.kind', 'foreign_key_list'],
    'helper site parent' => [$fkRows175, '0.parent', 'wp_sites'],
    'helper composite first child' => [$fkRows175, '1.from', 'option_name'],
    'helper composite second child' => [$fkRows175, '2.from', 'blog_id'],
    'helper fallback child' => [$fkRows175, '3.from', 'fallback_name'],
];

$tests = [];
foreach ($cases175 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next175 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt175): void {
        $t->same($expected, $valueAt175($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next175 paginates through appended fk list rows'] = static function (TestRunner $t) use ($page175): void {
    $first = $page175(0, 20);
    $second = $page175(20, 4, $first['next']);
    $third = $page175(24, 4, $second['next']);

    $t->same(20, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 20], $first['next']);
    $t->same('foreign_key_list', $second['rows'][0]['kind']);
    $t->same('blog_id', $second['rows'][0]['from']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next175 table valued index source keeps fk list rows'] = static function (TestRunner $t) use ($page175): void {
    $result = $page175(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same(4, $result['current']['foreign_key_list_rows']);
    $t->same('foreign_key_list', $result['rows'][23]['kind']);
};

$tests['pragma index xinfo foreignkey current source next175 source changes with fk column ddl'] = static function (TestRunner $t) use ($page175, $record175, $currentRecords175): void {
    $nextRecords = $currentRecords175;
    $nextRecords[3] = $record175('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites, option_name TEXT, blog_id TEXT, fallback_name TEXT, autoload TEXT, FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_names(blog_id, name), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) DEFERRABLE INITIALLY DEFERRED)', 4);

    $first = $page175();
    $second = $page175(nextRecords: $nextRecords);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $second['delta']['foreign_key_list_changed']);
};

$tests['pragma index xinfo foreignkey current source next175 rejects stale fk list cursor'] = static function (TestRunner $t) use ($page175, $record175, $currentRecords175): void {
    $first = $page175(0, 20);
    $nextRecords = $currentRecords175;
    $nextRecords[3] = $record175('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, site_id TEXT REFERENCES wp_sites, option_name TEXT, blog_id TEXT, fallback_name TEXT, autoload TEXT, FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_names(blog_id, name), FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) DEFERRABLE INITIALLY DEFERRED)', 4);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page175(20, 4, $first['next'], nextRecords: $nextRecords));
};

$tests['pragma index xinfo foreignkey current source next175 rejects stale offset cursor'] = static function (TestRunner $t) use ($page175): void {
    $first = $page175(0, 20);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page175(21, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next175 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeyListRows175([['bad' => 'record']]));
};

return $tests;
