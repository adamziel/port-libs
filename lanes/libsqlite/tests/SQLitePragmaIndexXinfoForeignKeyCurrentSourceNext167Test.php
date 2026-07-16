<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record167 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords167 = [
    $record167('table', 'WpSites', 'WpSites', 4, 'CREATE TABLE WpSites(Blog_ID INTEGER PRIMARY KEY, Domain TEXT COLLATE NOCASE)', 1),
    $record167('table', 'WpOptionNames', 'WpOptionNames', 5, 'CREATE TABLE WpOptionNames(Name TEXT COLLATE NOCASE, Blog_ID INTEGER, PRIMARY KEY(Name, Blog_ID)) WITHOUT ROWID', 2),
    $record167('table', 'WpOptions', 'WpOptions', 6, 'CREATE TABLE WpOptions(Option_ID INTEGER PRIMARY KEY, Option_Name TEXT, Blog_ID TEXT, Site_ID TEXT, Autoload TEXT, FOREIGN KEY(Site_ID) REFERENCES WpSites ON UPDATE CASCADE ON DELETE SET NULL MATCH SIMPLE, FOREIGN KEY(Option_Name, Blog_ID) REFERENCES WpOptionNames ON UPDATE RESTRICT ON DELETE CASCADE)', 3),
    $record167('index', 'WpOptionNamesLookup', 'WpOptionNames', 7, 'CREATE UNIQUE INDEX WpOptionNamesLookup ON WpOptionNames(Name COLLATE NOCASE, Blog_ID)', 4),
];
$nextRecords167 = [
    $currentRecords167[0],
    $currentRecords167[1],
    $record167('table', 'WpOptions', 'WpOptions', 6, 'CREATE TABLE WpOptions(Option_ID INTEGER PRIMARY KEY, Option_Name TEXT, Blog_ID TEXT, Site_ID TEXT, Autoload TEXT, FOREIGN KEY(Site_ID) REFERENCES WpSites ON UPDATE CASCADE ON DELETE CASCADE MATCH SIMPLE, FOREIGN KEY(Option_Name, Blog_ID) REFERENCES WpOptionNames ON UPDATE NO ACTION ON DELETE CASCADE)', 3),
    $currentRecords167[3],
];

$currentTables167 = [
    'wpsites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wpoptionnames' => [
        ['name' => 'siteurl', 'blog_id' => 1],
    ],
    'wpoptions' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'home', 'blog_id' => '1', 'site_id' => '404', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '1', 'autoload' => 'no'],
    ],
];
$nextTables167 = [
    'WPSITES' => [
        ['ROWID' => 1, 'BLOG_ID' => 1, 'DOMAIN' => 'example.test'],
        ['ROWID' => 404, 'BLOG_ID' => 404, 'DOMAIN' => 'network.example.test'],
    ],
    'WPOPTIONNAMES' => [
        ['NAME' => 'siteurl', 'BLOG_ID' => 1],
        ['NAME' => 'home', 'BLOG_ID' => 1],
        ['NAME' => 'missing', 'BLOG_ID' => 2],
    ],
    'WPOPTIONS' => $currentTables167['wpoptions'],
];

$page167 = static fn (
    int $offset = 0,
    int $limit = 167,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(WpOptionNamesLookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog167(
    $currentRecords167,
    $currentTables167,
    $nextRecords ?? $nextRecords167,
    $nextTables ?? $nextTables167,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt167 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default167 = static fn (): array => $page167();
$blocked167 = static fn (): array => $page167(nextTables: $currentTables167);
$sameSchema167 = static fn (): array => $page167(nextRecords: $currentRecords167);
$foreignKeys167 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog167($currentRecords167);
$tableValued167 = static fn (): array => $page167(indexSql: "pragma_index_xinfo('WpOptionNamesLookup')", tableValued: true);

$cases167 = [
    'status ok after action-aware repair' => [$default167, 'status', 'ok'],
    'default limit' => [$default167, 'limit', 167],
    'total rows' => [$default167, 'total', 11],
    'count rows' => [$default167, 'count', 11],
    'complete true' => [$default167, 'complete', true],
    'next null' => [$default167, 'next', null],
    'action source current' => [$default167, 'current_source.foreign_key_action_source', 'pragma_foreign_key_list_actions'],
    'action source next' => [$default167, 'next_source.foreign_key_action_source', 'pragma_foreign_key_list_actions'],
    'table key source inherited' => [$default167, 'current_source.table_key_source', 'sqlite_schema_casefold'],
    'column key source inherited' => [$default167, 'next_source.column_key_source', 'pragma_table_xinfo_casefold'],
    'derived current foreign keys' => [$default167, 'current_source.derived_foreign_keys', 2],
    'derived next foreign keys' => [$default167, 'next_source.derived_foreign_keys', 2],
    'current action summary first' => [$default167, 'current_source.foreign_key_actions.0', 'WpOptions#0->WpSites:update=CASCADE,delete=SET NULL,match=SIMPLE'],
    'current action summary second' => [$default167, 'current_source.foreign_key_actions.1', 'WpOptions#1->WpOptionNames:update=RESTRICT,delete=CASCADE,match=NONE'],
    'next action summary first' => [$default167, 'next_source.foreign_key_actions.0', 'WpOptions#0->WpSites:update=CASCADE,delete=CASCADE,match=SIMPLE'],
    'next action summary second' => [$default167, 'next_source.foreign_key_actions.1', 'WpOptions#1->WpOptionNames:update=NO ACTION,delete=CASCADE,match=NONE'],
    'current action count update cascade' => [$default167, 'current.foreign_key_actions.on_update:cascade', 1],
    'current action count update restrict' => [$default167, 'current.foreign_key_actions.on_update:restrict', 1],
    'current action count delete cascade' => [$default167, 'current.foreign_key_actions.on_delete:cascade', 1],
    'current action count delete set null' => [$default167, 'current.foreign_key_actions.on_delete:set null', 1],
    'current action count match simple' => [$default167, 'current.foreign_key_actions.match:simple', 1],
    'current action count match none' => [$default167, 'current.foreign_key_actions.match:none', 1],
    'next action count update no action' => [$default167, 'next_counts.foreign_key_actions.on_update:no action', 1],
    'next action count update cascade' => [$default167, 'next_counts.foreign_key_actions.on_update:cascade', 1],
    'next action count delete cascade' => [$default167, 'next_counts.foreign_key_actions.on_delete:cascade', 2],
    'next action count match simple' => [$default167, 'next_counts.foreign_key_actions.match:simple', 1],
    'action change count' => [$default167, 'delta.foreign_key_action_changes', 4],
    'action changed true' => [$default167, 'delta.foreign_key_action_changed', true],
    'same schema action changed false' => [$sameSchema167, 'delta.foreign_key_action_changed', false],
    'same schema action change count zero' => [$sameSchema167, 'delta.foreign_key_action_changes', 0],
    'current xinfo rows' => [$default167, 'current.index_xinfo', 2],
    'next xinfo rows' => [$default167, 'next_counts.index_xinfo', 2],
    'current admissions' => [$default167, 'current.index_admissions', 2],
    'next admissions' => [$default167, 'next_counts.index_admissions', 2],
    'current fk violations' => [$default167, 'current.foreign_key_violations', 3],
    'next fk violations' => [$default167, 'next_counts.foreign_key_violations', 0],
    'delta violations cleared' => [$default167, 'delta.foreign_key_violations', -3],
    'delta blockers cleared' => [$default167, 'delta.cleared', true],
    'next ready' => [$default167, 'next_state.ready', true],
    'row0 xinfo unchanged' => [$default167, 'rows.0.kind', 'index_xinfo'],
    'row2 admission update cascade' => [$default167, 'rows.2.on_update', 'CASCADE'],
    'row2 admission delete set null' => [$default167, 'rows.2.on_delete', 'SET NULL'],
    'row2 admission match simple' => [$default167, 'rows.2.match', 'SIMPLE'],
    'row3 admission update restrict' => [$default167, 'rows.3.on_update', 'RESTRICT'],
    'row3 admission delete cascade' => [$default167, 'rows.3.on_delete', 'CASCADE'],
    'row3 admission match none' => [$default167, 'rows.3.match', 'NONE'],
    'row4 violation update cascade' => [$default167, 'rows.4.on_update', 'CASCADE'],
    'row4 violation delete set null' => [$default167, 'rows.4.on_delete', 'SET NULL'],
    'row5 violation update restrict' => [$default167, 'rows.5.on_update', 'RESTRICT'],
    'row5 violation delete cascade' => [$default167, 'rows.5.on_delete', 'CASCADE'],
    'row7 next side' => [$default167, 'rows.7.side', 'next'],
    'row9 next update cascade' => [$default167, 'rows.9.on_update', 'CASCADE'],
    'row9 next delete cascade' => [$default167, 'rows.9.on_delete', 'CASCADE'],
    'row10 next update no action' => [$default167, 'rows.10.on_update', 'NO ACTION'],
    'row10 next delete cascade' => [$default167, 'rows.10.on_delete', 'CASCADE'],
    'blocked status' => [$blocked167, 'status', 'blocked'],
    'blocked next violations' => [$blocked167, 'next_counts.foreign_key_violations', 3],
    'blocked next action preserved' => [$blocked167, 'next_counts.foreign_key_actions.on_delete:cascade', 2],
    'fk0 on update' => [$foreignKeys167, '0.on_update', 'CASCADE'],
    'fk0 on delete' => [$foreignKeys167, '0.on_delete', 'SET NULL'],
    'fk0 match simple' => [$foreignKeys167, '0.match', 'SIMPLE'],
    'fk1 on update' => [$foreignKeys167, '1.on_update', 'RESTRICT'],
    'fk1 on delete' => [$foreignKeys167, '1.on_delete', 'CASCADE'],
    'fk1 match none' => [$foreignKeys167, '1.match', 'NONE'],
    'table valued flag' => [$tableValued167, 'current_source.table_valued_index_xinfo', true],
];

$tests = [];
foreach ($cases167 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next167 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt167): void {
        $t->same($expected, $valueAt167($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next167 paginates action rows'] = static function (TestRunner $t) use ($page167): void {
    $first = $page167(0, 4);
    $second = $page167(4, 4, $first['next']);
    $third = $page167(8, 4, $second['next']);

    $t->same(4, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 4], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('SET NULL', $second['rows'][0]['on_delete']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same('CASCADE', $third['rows'][1]['on_delete']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next167 source changes with action ddl'] = static function (TestRunner $t) use ($page167, $currentRecords167): void {
    $actionChanged = $page167();
    $sameActions = $page167(nextRecords: $currentRecords167);

    $t->same(true, $actionChanged['source_id'] !== $sameActions['source_id']);
    $t->same(true, $actionChanged['current_source']['foreign_key_actions'] !== $actionChanged['next_source']['foreign_key_actions']);
    $t->same(false, $sameActions['delta']['foreign_key_action_changed']);
};

$tests['pragma index xinfo foreignkey current source next167 rejects stale source cursor'] = static function (TestRunner $t) use ($page167, $currentTables167): void {
    $first = $page167(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page167(4, 4, $first['next'], nextTables: $currentTables167));
};

$tests['pragma index xinfo foreignkey current source next167 rejects stale offset cursor'] = static function (TestRunner $t) use ($page167): void {
    $first = $page167(0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page167(5, 4, $first['next']));
};

return $tests;
