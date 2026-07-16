<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record170 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords170 = [
    $record170('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record170('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record170('table', 'wp_option_groups', 'wp_option_groups', 6, 'CREATE TABLE wp_option_groups(group_id INTEGER PRIMARY KEY, label TEXT)', 3),
    $record170('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, group_id INTEGER, autoload TEXT, FOREIGN KEY(site_id) REFERENCES wp_sites ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names ON UPDATE NO ACTION ON DELETE SET NULL MATCH simple, FOREIGN KEY(group_id) REFERENCES wp_option_groups(group_id) ON DELETE SET DEFAULT NOT DEFERRABLE)', 4),
    $record170('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
];
$nextRecords170 = [
    $record170('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record170('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record170('table', 'wp_option_groups', 'wp_option_groups', 6, 'CREATE TABLE wp_option_groups(group_id INTEGER PRIMARY KEY, label TEXT)', 3),
    $record170('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, group_id INTEGER, autoload TEXT, FOREIGN KEY(site_id) REFERENCES wp_sites ON UPDATE SET NULL ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names ON UPDATE CASCADE ON DELETE NO ACTION MATCH partial, FOREIGN KEY(group_id) REFERENCES wp_option_groups(group_id) ON UPDATE RESTRICT ON DELETE SET DEFAULT)', 4),
    $record170('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
];

$currentTables170 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wp_option_groups' => [['rowid' => 10, 'group_id' => 10, 'label' => 'core']],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'group_id' => 10, 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'home', 'blog_id' => '1', 'site_id' => '404', 'group_id' => 10, 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '1', 'group_id' => 99, 'autoload' => 'no'],
    ],
];
$nextTables170 = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'network.example.test'],
    ],
    'wp_option_names' => [
        ['name' => 'siteurl', 'blog_id' => 1],
        ['name' => 'home', 'blog_id' => 1],
        ['name' => 'missing', 'blog_id' => 2],
    ],
    'wp_option_groups' => [
        ['rowid' => 10, 'group_id' => 10, 'label' => 'core'],
        ['rowid' => 99, 'group_id' => 99, 'label' => 'plugins'],
    ],
    'wp_options' => $currentTables170['wp_options'],
];

$page170 = static fn (
    int $offset = 0,
    int $limit = 170,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_option_names_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog170(
    $currentRecords170,
    $currentTables170,
    $nextRecords ?? $nextRecords170,
    $nextTables ?? $nextTables170,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt170 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default170 = static fn (): array => $page170();
$blocked170 = static fn (): array => $page170(nextRecords: $currentRecords170, nextTables: $currentTables170);
$foreignKeys170 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog170($currentRecords170);

$cases170 = [
    'status ok after deferred and immediate repair' => [$default170, 'status', 'ok'],
    'limit default' => [$default170, 'limit', 170],
    'total rows' => [$default170, 'total', 14],
    'count rows' => [$default170, 'count', 14],
    'complete true' => [$default170, 'complete', true],
    'next null' => [$default170, 'next', null],
    'source type current' => [$default170, 'current_source.foreign_key_source', 'pragma_foreign_key_list_deferrable_violations'],
    'source type next' => [$default170, 'next_source.foreign_key_source', 'pragma_foreign_key_list_deferrable_violations'],
    'derived current fks' => [$default170, 'current_source.derived_foreign_keys', 3],
    'derived next fks' => [$default170, 'next_source.derived_foreign_keys', 3],
    'source deferrable count' => [$default170, 'current_source.foreign_key_timing.deferrable', 1],
    'source initially deferred count' => [$default170, 'current_source.foreign_key_timing.initially_deferred', 1],
    'source deferred count' => [$default170, 'current_source.foreign_key_timing.deferred', 1],
    'source immediate count' => [$default170, 'current_source.foreign_key_timing.immediate', 2],
    'next source initially deferred cleared' => [$default170, 'next_source.foreign_key_timing.initially_deferred', 0],
    'current fk violations' => [$default170, 'current.foreign_key_violations', 4],
    'current immediate violations' => [$default170, 'current.immediate_foreign_key_violations', 3],
    'current deferred violations' => [$default170, 'current.deferred_foreign_key_violations', 1],
    'current commit blockers' => [$default170, 'current.commit_blocking_foreign_key_violations', 4],
    'current deferred constraints' => [$default170, 'current.deferred_foreign_key_constraints', 1],
    'current immediate constraints' => [$default170, 'current.immediate_foreign_key_constraints', 2],
    'next immediate violations' => [$default170, 'next_counts.immediate_foreign_key_violations', 0],
    'next deferred violations' => [$default170, 'next_counts.deferred_foreign_key_violations', 0],
    'next commit blockers' => [$default170, 'next_counts.commit_blocking_foreign_key_violations', 0],
    'delta immediate violations' => [$default170, 'delta.immediate_foreign_key_violations', -3],
    'delta deferred violations' => [$default170, 'delta.deferred_foreign_key_violations', -1],
    'delta deferred cleared' => [$default170, 'delta.deferred_cleared', true],
    'delta total blockers' => [$default170, 'delta.total_blockers', -4],
    'next ready' => [$default170, 'next_state.ready', true],
    'next blocking empty' => [$default170, 'next_state.blocking', []],
    'row5 deferred violation timing' => [$default170, 'rows.5.constraint_timing', 'deferred'],
    'row5 deferred flag' => [$default170, 'rows.5.deferred_until_commit', true],
    'row5 action summary' => [$default170, 'rows.5.fk_action_summary', 'CASCADE/RESTRICT/NONE'],
    'row5 on delete' => [$default170, 'rows.5.on_delete', 'RESTRICT'],
    'row6 immediate violation timing' => [$default170, 'rows.6.constraint_timing', 'immediate'],
    'row6 deferred flag' => [$default170, 'rows.6.deferred_until_commit', false],
    'row6 match simple' => [$default170, 'rows.6.match', 'SIMPLE'],
    'row8 set default summary' => [$default170, 'rows.8.fk_action_summary', 'NO ACTION/SET DEFAULT/NONE'],
    'blocked status' => [$blocked170, 'status', 'blocked'],
    'blocked next ready' => [$blocked170, 'next_state.ready', false],
    'blocked reasons split by timing' => [$blocked170, 'next_state.blocking', ['immediate_foreign_key_check', 'deferred_foreign_key_check']],
    'blocked next immediate' => [$blocked170, 'next_counts.immediate_foreign_key_violations', 3],
    'blocked next deferred' => [$blocked170, 'next_counts.deferred_foreign_key_violations', 1],
    'fk0 deferrable' => [$foreignKeys170, '0.deferrable', true],
    'fk0 initially deferred' => [$foreignKeys170, '0.initially_deferred', true],
    'fk1 immediate' => [$foreignKeys170, '1.initially_deferred', false],
    'fk2 not deferrable' => [$foreignKeys170, '2.deferrable', false],
];

$tests = [];
foreach ($cases170 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next170 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt170): void {
        $t->same($expected, $valueAt170($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next170 paginates timing annotated rows'] = static function (TestRunner $t) use ($page170): void {
    $first = $page170(0, 6);
    $second = $page170(6, 6, $first['next']);
    $third = $page170(12, 6, $second['next']);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('foreign_key_check', $first['rows'][5]['kind']);
    $t->same('deferred', $first['rows'][5]['constraint_timing']);
    $t->same('immediate', $second['rows'][0]['constraint_timing']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next170 source changes with deferred DDL'] = static function (TestRunner $t) use ($page170, $currentRecords170): void {
    $first = $page170();
    $second = $page170(nextRecords: $currentRecords170);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(0, $first['next_source']['foreign_key_timing']['initially_deferred']);
    $t->same(1, $second['next_source']['foreign_key_timing']['initially_deferred']);
};

$tests['pragma index xinfo foreignkey current source next170 rejects stale source cursor'] = static function (TestRunner $t) use ($page170, $currentTables170): void {
    $first = $page170(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page170(6, 6, $first['next'], nextTables: $currentTables170));
};

$tests['pragma index xinfo foreignkey current source next170 rejects stale offset cursor'] = static function (TestRunner $t) use ($page170): void {
    $first = $page170(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page170(7, 6, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next170 supports table valued index_xinfo'] = static function (TestRunner $t) use ($page170): void {
    $result = $page170(indexSql: "pragma_index_xinfo('wp_option_names_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same("pragma_index_xinfo('wp_option_names_lookup')", $result['current_source']['index_xinfo_sql']);
};

$tests['pragma index xinfo foreignkey current source next170 rejects invalid record'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog170([['not' => 'record']]));
};

return $tests;
