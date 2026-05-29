<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record166 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords166 = [
    $record166('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record166('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record166('table', 'wp_option_groups', 'wp_option_groups', 6, 'CREATE TABLE wp_option_groups(group_id INTEGER PRIMARY KEY, label TEXT)', 3),
    $record166('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, group_id INTEGER, autoload TEXT, FOREIGN KEY(site_id) REFERENCES wp_sites ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names ON UPDATE NO ACTION ON DELETE SET NULL MATCH simple, FOREIGN KEY(group_id) REFERENCES wp_option_groups(group_id) ON DELETE SET DEFAULT NOT DEFERRABLE)', 4),
    $record166('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
];
$nextRecords166 = [
    $record166('table', 'wp_sites', 'wp_sites', 4, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record166('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 2),
    $record166('table', 'wp_option_groups', 'wp_option_groups', 6, 'CREATE TABLE wp_option_groups(group_id INTEGER PRIMARY KEY, label TEXT)', 3),
    $record166('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, blog_id TEXT, site_id TEXT, group_id INTEGER, autoload TEXT, FOREIGN KEY(site_id) REFERENCES wp_sites ON UPDATE SET NULL ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(option_name, blog_id) REFERENCES wp_option_names ON UPDATE CASCADE ON DELETE NO ACTION MATCH partial, FOREIGN KEY(group_id) REFERENCES wp_option_groups(group_id) ON UPDATE RESTRICT ON DELETE SET DEFAULT)', 4),
    $record166('index', 'wp_option_names_lookup', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_option_names_lookup ON wp_option_names(name COLLATE NOCASE, blog_id)', 5),
];

$currentTables166 = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['name' => 'siteurl', 'blog_id' => 1]],
    'wp_option_groups' => [['rowid' => 10, 'group_id' => 10, 'label' => 'core']],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'group_id' => 10, 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'home', 'blog_id' => '1', 'site_id' => '404', 'group_id' => 10, 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '1', 'group_id' => 99, 'autoload' => 'no'],
    ],
];
$nextTables166 = [
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
    'wp_options' => $currentTables166['wp_options'],
];

$page166 = static fn (
    int $offset = 0,
    int $limit = 166,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_option_names_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog166(
    $currentRecords166,
    $currentTables166,
    $nextRecords ?? $nextRecords166,
    $nextTables ?? $nextTables166,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt166 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default166 = static fn (): array => $page166();
$blocked166 = static fn (): array => $page166(nextTables: $currentTables166);
$foreignKeys166 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog166($currentRecords166);
$nextForeignKeys166 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog166($nextRecords166);

$cases166 = [
    'status ok after action aware repair' => [$default166, 'status', 'ok'],
    'limit default' => [$default166, 'limit', 166],
    'total rows' => [$default166, 'total', 14],
    'count rows' => [$default166, 'count', 14],
    'complete true' => [$default166, 'complete', true],
    'next null' => [$default166, 'next', null],
    'source type current' => [$default166, 'current_source.foreign_key_source', 'pragma_foreign_key_list_actions'],
    'source type next' => [$default166, 'next_source.foreign_key_source', 'pragma_foreign_key_list_actions'],
    'derived current foreign keys' => [$default166, 'current_source.derived_foreign_keys', 3],
    'derived next foreign keys' => [$default166, 'next_source.derived_foreign_keys', 3],
    'current update action cascade' => [$default166, 'current_source.foreign_key_actions.on_update.CASCADE', 1],
    'current update action no action' => [$default166, 'current_source.foreign_key_actions.on_update.NO ACTION', 2],
    'current delete action restrict' => [$default166, 'current_source.foreign_key_actions.on_delete.RESTRICT', 1],
    'current delete action set null' => [$default166, 'current_source.foreign_key_actions.on_delete.SET NULL', 1],
    'current delete action set default' => [$default166, 'current_source.foreign_key_actions.on_delete.SET DEFAULT', 1],
    'current match simple' => [$default166, 'current_source.foreign_key_actions.match.SIMPLE', 1],
    'current match none' => [$default166, 'current_source.foreign_key_actions.match.NONE', 2],
    'current deferrable count' => [$default166, 'current_source.foreign_key_actions.deferrable', 1],
    'current initially deferred count' => [$default166, 'current_source.foreign_key_actions.initially_deferred', 1],
    'next update action set null' => [$default166, 'next_source.foreign_key_actions.on_update.SET NULL', 1],
    'next update action cascade' => [$default166, 'next_source.foreign_key_actions.on_update.CASCADE', 1],
    'next update action restrict' => [$default166, 'next_source.foreign_key_actions.on_update.RESTRICT', 1],
    'next delete action cascade' => [$default166, 'next_source.foreign_key_actions.on_delete.CASCADE', 1],
    'next delete action no action' => [$default166, 'next_source.foreign_key_actions.on_delete.NO ACTION', 1],
    'next match partial' => [$default166, 'next_source.foreign_key_actions.match.PARTIAL', 1],
    'next deferrable count' => [$default166, 'next_source.foreign_key_actions.deferrable', 1],
    'next initially deferred count' => [$default166, 'next_source.foreign_key_actions.initially_deferred', 0],
    'source id length' => [static fn (): array => ['len' => strlen($page166()['source_id'])], 'len', 64],
    'current foreign key hash length' => [static fn (): array => ['len' => strlen($page166()['current_source']['foreign_keys'])], 'len', 64],
    'next foreign key hash length' => [static fn (): array => ['len' => strlen($page166()['next_source']['foreign_keys'])], 'len', 64],
    'current xinfo rows' => [$default166, 'current.index_xinfo', 2],
    'next xinfo rows' => [$default166, 'next_counts.index_xinfo', 2],
    'current index admissions' => [$default166, 'current.index_admissions', 3],
    'next index admissions' => [$default166, 'next_counts.index_admissions', 3],
    'current fk violations' => [$default166, 'current.foreign_key_violations', 4],
    'next fk violations' => [$default166, 'next_counts.foreign_key_violations', 0],
    'delta fk cleared' => [$default166, 'delta.foreign_key_violations', -4],
    'delta cleared true' => [$default166, 'delta.cleared', true],
    'next ready true' => [$default166, 'next_state.ready', true],
    'blocked status' => [$blocked166, 'status', 'blocked'],
    'blocked next violations' => [$blocked166, 'next_counts.foreign_key_violations', 4],
    'fk0 on update cascade' => [$foreignKeys166, '0.on_update', 'CASCADE'],
    'fk0 on delete restrict' => [$foreignKeys166, '0.on_delete', 'RESTRICT'],
    'fk0 deferrable' => [$foreignKeys166, '0.deferrable', true],
    'fk0 initially deferred' => [$foreignKeys166, '0.initially_deferred', true],
    'fk0 implicit parent column' => [$foreignKeys166, '0.columns.0.parent', 'blog_id'],
    'fk0 pragma row action' => [$foreignKeys166, '0.pragma_rows.0.on_update', 'CASCADE'],
    'fk1 match simple' => [$foreignKeys166, '1.match', 'SIMPLE'],
    'fk1 composite first parent' => [$foreignKeys166, '1.columns.0.parent', 'name'],
    'fk1 composite second parent' => [$foreignKeys166, '1.columns.1.parent', 'blog_id'],
    'fk1 pragma row sequence' => [$foreignKeys166, '1.pragma_rows.1.seq', 1],
    'fk2 on delete set default' => [$foreignKeys166, '2.on_delete', 'SET DEFAULT'],
    'fk2 not deferrable' => [$foreignKeys166, '2.deferrable', false],
    'next fk0 initially immediate' => [$nextForeignKeys166, '0.initially_deferred', false],
    'next fk1 match partial' => [$nextForeignKeys166, '1.match', 'PARTIAL'],
    'next fk2 on update restrict' => [$nextForeignKeys166, '2.on_update', 'RESTRICT'],
];

$tests = [];
foreach ($cases166 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next166 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt166): void {
        $t->same($expected, $valueAt166($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next166 action ddl changes source hash'] = static function (TestRunner $t) use ($page166): void {
    $first = $page166();
    $second = $page166(nextRecords: $GLOBALS['currentRecords166']);

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(true, $first['next_source']['foreign_keys'] !== $second['next_source']['foreign_keys']);
    $t->same(['CASCADE' => 1, 'NO ACTION' => 2], $second['next_source']['foreign_key_actions']['on_update']);
};

$tests['pragma index xinfo foreignkey current source next166 paginates action aware source'] = static function (TestRunner $t) use ($page166): void {
    $first = $page166(0, 5);
    $second = $page166(5, 5, $first['next']);
    $third = $page166(10, 5, $second['next']);

    $t->same(5, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next166 rejects stale action cursor'] = static function (TestRunner $t) use ($page166): void {
    $first = $page166(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page166(5, 5, $first['next'], nextRecords: $GLOBALS['currentRecords166']));
};

$tests['pragma index xinfo foreignkey current source next166 supports table valued index xinfo'] = static function (TestRunner $t) use ($page166): void {
    $result = $page166(indexSql: "pragma_index_xinfo('wp_option_names_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same("pragma_index_xinfo('wp_option_names_lookup')", $result['current_source']['index_xinfo_sql']);
};

$tests['pragma index xinfo foreignkey current source next166 rejects invalid record'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog166([['not' => 'record']]));
};

return $tests;
