<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record165 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords165 = [
    $record165('table', 'wp_blogs', 'wp_blogs', 4, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY, domain TEXT COLLATE NOCASE)', 1),
    $record165('table', 'wp_option_scope', 'wp_option_scope', 5, 'CREATE TABLE wp_option_scope(site_key TEXT COLLATE NOCASE, locale TEXT, PRIMARY KEY(site_key, locale)) WITHOUT ROWID', 2),
    $record165('table', 'wp_defaults', 'wp_defaults', 6, 'CREATE TABLE wp_defaults(default_name TEXT PRIMARY KEY, enabled INTEGER)', 3),
    $record165('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id TEXT, site_key TEXT, locale TEXT, fallback_name TEXT, option_name TEXT, autoload TEXT, FOREIGN KEY(blog_id) REFERENCES wp_blogs(blog_id) ON UPDATE CASCADE ON DELETE RESTRICT MATCH simple, FOREIGN KEY(site_key, locale) REFERENCES wp_option_scope(site_key, locale) ON UPDATE SET DEFAULT ON DELETE CASCADE MATCH custom, FOREIGN KEY(fallback_name) REFERENCES wp_defaults(default_name) ON UPDATE NO ACTION ON DELETE SET NULL)', 4),
    $record165('index', 'wp_option_scope_lookup', 'wp_option_scope', 8, 'CREATE UNIQUE INDEX wp_option_scope_lookup ON wp_option_scope(site_key COLLATE NOCASE, locale)', 5),
    $record165('index', 'sqlite_autoindex_wp_defaults_1', 'wp_defaults', 9, 'CREATE UNIQUE INDEX sqlite_autoindex_wp_defaults_1 ON wp_defaults(default_name)', 6),
    $record165('index', 'wp_options_lookup', 'wp_options', 10, 'CREATE INDEX wp_options_lookup ON wp_options(option_name, blog_id)', 7),
];
$nextRecords165 = $currentRecords165;

$currentTables165 = [
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_scope' => [
        ['site_key' => 'main', 'locale' => 'en_US'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
    ],
    'wp_options' => [
        ['rowid' => 1, 'option_id' => 1, 'blog_id' => '1', 'site_key' => 'main', 'locale' => 'en_US', 'fallback_name' => 'siteurl', 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'blog_id' => '404', 'site_key' => 'main', 'locale' => 'fr_FR', 'fallback_name' => 'missing_default', 'option_name' => 'home', 'autoload' => 'yes'],
    ],
];
$nextTables165 = [
    'wp_blogs' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
        ['rowid' => 404, 'blog_id' => 404, 'domain' => 'archive.example.test'],
    ],
    'wp_option_scope' => [
        ['site_key' => 'main', 'locale' => 'en_US'],
        ['site_key' => 'main', 'locale' => 'fr_FR'],
    ],
    'wp_defaults' => [
        ['rowid' => 1, 'default_name' => 'siteurl', 'enabled' => 1],
        ['rowid' => 2, 'default_name' => 'missing_default', 'enabled' => 0],
    ],
    'wp_options' => $currentTables165['wp_options'],
];

$page165 = static fn (
    int $offset = 0,
    int $limit = 165,
    ?array $cursor = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_options_lookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog165(
    $currentRecords165,
    $currentTables165,
    $nextRecords165,
    $nextTables ?? $nextTables165,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt165 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default165 = static fn (): array => $page165();
$blocked165 = static fn (): array => $page165(nextTables: $currentTables165);
$foreignKeys165 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog165($currentRecords165);

$cases165 = [
    'status ok after action aware repair' => [$default165, 'status', 'ok'],
    'limit default' => [$default165, 'limit', 165],
    'total rows' => [$default165, 'total', 15],
    'count rows' => [$default165, 'count', 15],
    'complete true' => [$default165, 'complete', true],
    'next null' => [$default165, 'next', null],
    'current xinfo rows' => [$default165, 'current.index_xinfo', 3],
    'next xinfo rows' => [$default165, 'next_counts.index_xinfo', 3],
    'current admissions' => [$default165, 'current.index_admissions', 3],
    'next admissions' => [$default165, 'next_counts.index_admissions', 3],
    'current fk violations' => [$default165, 'current.foreign_key_violations', 3],
    'next fk violations clear' => [$default165, 'next_counts.foreign_key_violations', 0],
    'delta fk cleared' => [$default165, 'delta.foreign_key_violations', -3],
    'delta blockers cleared' => [$default165, 'delta.total_blockers', -3],
    'delta cleared true' => [$default165, 'delta.cleared', true],
    'next ready true' => [$default165, 'next_state.ready', true],
    'source fk current' => [$default165, 'current_source.foreign_key_source', 'pragma_foreign_key_list'],
    'source action current' => [$default165, 'current_source.action_source', 'pragma_foreign_key_list_actions'],
    'source action next' => [$default165, 'next_source.action_source', 'pragma_foreign_key_list_actions'],
    'derived foreign key count current' => [$default165, 'current_source.derived_foreign_keys', 3],
    'action summary update cascade' => [$default165, 'current_source.action_summary.on_update.CASCADE', 1],
    'action summary update no action' => [$default165, 'current_source.action_summary.on_update.NO ACTION', 1],
    'action summary update set default' => [$default165, 'current_source.action_summary.on_update.SET DEFAULT', 1],
    'action summary delete cascade' => [$default165, 'current_source.action_summary.on_delete.CASCADE', 1],
    'action summary delete restrict' => [$default165, 'current_source.action_summary.on_delete.RESTRICT', 1],
    'action summary delete set null' => [$default165, 'current_source.action_summary.on_delete.SET NULL', 1],
    'action summary match custom' => [$default165, 'current_source.action_summary.match.CUSTOM', 1],
    'action summary match none' => [$default165, 'current_source.action_summary.match.NONE', 1],
    'action summary match simple' => [$default165, 'current_source.action_summary.match.SIMPLE', 1],
    'next action summary delete set null' => [$default165, 'next_source.action_summary.on_delete.SET NULL', 1],
    'target index' => [$default165, 'current.target_index', 'wp_options_lookup'],
    'parent indexes include rowid' => [$default165, 'current.parent_indexes.0', 'rowid-primary-key'],
    'parent indexes include option scope' => [$default165, 'current.parent_indexes.1', 'wp_option_scope_lookup'],
    'parent indexes include defaults' => [$default165, 'current.parent_indexes.2', 'sqlite_autoindex_wp_defaults_1'],
    'row0 kind xinfo' => [$default165, 'rows.0.kind', 'index_xinfo'],
    'row0 name option' => [$default165, 'rows.0.name', 'option_name'],
    'row3 rowid admission update action' => [$default165, 'rows.3.on_update', 'CASCADE'],
    'row3 rowid admission delete action' => [$default165, 'rows.3.on_delete', 'RESTRICT'],
    'row3 rowid admission match' => [$default165, 'rows.3.match', 'SIMPLE'],
    'row3 action summary' => [$default165, 'rows.3.action_summary', 'CASCADE/RESTRICT/SIMPLE'],
    'row4 composite admission update action' => [$default165, 'rows.4.on_update', 'SET DEFAULT'],
    'row4 composite admission delete action' => [$default165, 'rows.4.on_delete', 'CASCADE'],
    'row4 composite admission match' => [$default165, 'rows.4.match', 'CUSTOM'],
    'row5 default admission update action' => [$default165, 'rows.5.on_update', 'NO ACTION'],
    'row5 default admission delete action' => [$default165, 'rows.5.on_delete', 'SET NULL'],
    'row5 default admission match' => [$default165, 'rows.5.match', 'NONE'],
    'row6 first violation update action' => [$default165, 'rows.6.on_update', 'CASCADE'],
    'row6 first violation parent' => [$default165, 'rows.6.parent', 'wp_blogs'],
    'row7 composite violation action summary' => [$default165, 'rows.7.action_summary', 'SET DEFAULT/CASCADE/CUSTOM'],
    'row8 default violation action summary' => [$default165, 'rows.8.action_summary', 'NO ACTION/SET NULL/NONE'],
    'row9 next side starts' => [$default165, 'rows.9.side', 'next'],
    'row12 next rowid action summary' => [$default165, 'rows.12.action_summary', 'CASCADE/RESTRICT/SIMPLE'],
    'row13 next composite action summary' => [$default165, 'rows.13.action_summary', 'SET DEFAULT/CASCADE/CUSTOM'],
    'row14 next default action summary' => [$default165, 'rows.14.action_summary', 'NO ACTION/SET NULL/NONE'],
    'blocked status' => [$blocked165, 'status', 'blocked'],
    'blocked next ready false' => [$blocked165, 'next_state.ready', false],
    'blocked next blockers' => [$blocked165, 'next_state.blocking', ['foreign_key_check']],
    'blocked next violations' => [$blocked165, 'next_counts.foreign_key_violations', 3],
    'fk0 on update' => [$foreignKeys165, '0.on_update', 'CASCADE'],
    'fk0 on delete' => [$foreignKeys165, '0.on_delete', 'RESTRICT'],
    'fk0 match' => [$foreignKeys165, '0.match', 'SIMPLE'],
    'fk1 on update' => [$foreignKeys165, '1.on_update', 'SET DEFAULT'],
    'fk1 on delete' => [$foreignKeys165, '1.on_delete', 'CASCADE'],
    'fk1 match' => [$foreignKeys165, '1.match', 'CUSTOM'],
    'fk2 on update' => [$foreignKeys165, '2.on_update', 'NO ACTION'],
    'fk2 on delete' => [$foreignKeys165, '2.on_delete', 'SET NULL'],
    'fk2 match default none' => [$foreignKeys165, '2.match', 'NONE'],
];

$tests = [];
foreach ($cases165 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey action current source next165 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt165): void {
        $t->same($expected, $valueAt165($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey action current source next165 paginates action rows'] = static function (TestRunner $t) use ($page165): void {
    $first = $page165(0, 6);
    $second = $page165(6, 6, $first['next']);
    $third = $page165(12, 6, $second['next']);

    $t->same(6, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 6], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('CASCADE/RESTRICT/SIMPLE', $second['rows'][0]['action_summary']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey action current source next165 table-valued index xinfo keeps actions'] = static function (TestRunner $t) use ($page165): void {
    $result = $page165(indexSql: "pragma_index_xinfo('wp_options_lookup')", tableValued: true);

    $t->same('ok', $result['status']);
    $t->same(true, $result['current_source']['table_valued_index_xinfo']);
    $t->same('SET DEFAULT/CASCADE/CUSTOM', $result['rows'][4]['action_summary']);
};

$tests['pragma index xinfo foreignkey action current source next165 rejects stale action cursor'] = static function (TestRunner $t) use ($page165, $currentTables165): void {
    $first = $page165(0, 6);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page165(6, 6, $first['next'], nextTables: $currentTables165));
};

$tests['pragma index xinfo foreignkey action current source next165 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog165([['not' => 'a record']]));
};

return $tests;
