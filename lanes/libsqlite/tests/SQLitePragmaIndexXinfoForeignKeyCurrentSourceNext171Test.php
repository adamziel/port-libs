<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record171 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords171 = [
    $record171('table', 'WpSites', 'WpSites', 4, 'CREATE TABLE WpSites(Blog_ID INTEGER PRIMARY KEY, Domain TEXT COLLATE NOCASE)', 1),
    $record171('table', 'WpOptionNames', 'WpOptionNames', 5, 'CREATE TABLE WpOptionNames(Name TEXT COLLATE NOCASE, Blog_ID INTEGER, PRIMARY KEY(Name, Blog_ID)) WITHOUT ROWID', 2),
    $record171('table', 'WpDefaults', 'WpDefaults', 6, 'CREATE TABLE WpDefaults(Name TEXT PRIMARY KEY, Enabled INTEGER)', 3),
    $record171('table', 'WpOptions', 'WpOptions', 7, 'CREATE TABLE WpOptions(Option_ID INTEGER PRIMARY KEY, Option_Name TEXT, Blog_ID TEXT, Site_ID TEXT, Fallback_Name TEXT, Autoload TEXT, FOREIGN KEY(Site_ID) REFERENCES WpSites ON UPDATE CASCADE ON DELETE SET NULL MATCH SIMPLE DEFERRABLE INITIALLY DEFERRED, FOREIGN KEY(Option_Name, Blog_ID) REFERENCES WpOptionNames ON UPDATE RESTRICT ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(Fallback_Name) REFERENCES WpDefaults(Name) ON UPDATE NO ACTION ON DELETE SET DEFAULT NOT DEFERRABLE)', 4),
    $record171('index', 'WpOptionNamesLookup', 'WpOptionNames', 8, 'CREATE UNIQUE INDEX WpOptionNamesLookup ON WpOptionNames(Name COLLATE NOCASE, Blog_ID)', 5),
    $record171('index', 'WpDefaultsLookup', 'WpDefaults', 9, 'CREATE UNIQUE INDEX WpDefaultsLookup ON WpDefaults(Name)', 6),
];
$nextRecords171 = [
    $currentRecords171[0],
    $currentRecords171[1],
    $currentRecords171[2],
    $record171('table', 'WpOptions', 'WpOptions', 7, 'CREATE TABLE WpOptions(Option_ID INTEGER PRIMARY KEY, Option_Name TEXT, Blog_ID TEXT, Site_ID TEXT, Fallback_Name TEXT, Autoload TEXT, FOREIGN KEY(Site_ID) REFERENCES WpSites ON UPDATE CASCADE ON DELETE SET NULL MATCH SIMPLE DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY(Option_Name, Blog_ID) REFERENCES WpOptionNames ON UPDATE RESTRICT ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED, FOREIGN KEY(Fallback_Name) REFERENCES WpDefaults(Name) ON UPDATE NO ACTION ON DELETE SET DEFAULT)', 4),
    $currentRecords171[4],
    $currentRecords171[5],
];

$currentTables171 = [
    'wpsites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wpoptionnames' => [
        ['name' => 'siteurl', 'blog_id' => 1],
    ],
    'wpdefaults' => [
        ['rowid' => 1, 'name' => 'siteurl', 'enabled' => 1],
    ],
    'wpoptions' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SITEURL', 'blog_id' => '1', 'site_id' => '1', 'fallback_name' => 'siteurl', 'autoload' => 'yes'],
        ['rowid' => 2, 'option_id' => 2, 'option_name' => 'home', 'blog_id' => '1', 'site_id' => '404', 'fallback_name' => 'missing_default', 'autoload' => 'yes'],
        ['rowid' => 3, 'option_id' => 3, 'option_name' => 'missing', 'blog_id' => '2', 'site_id' => '1', 'fallback_name' => 'siteurl', 'autoload' => 'no'],
    ],
];
$nextTables171 = [
    'WPSITES' => [
        ['ROWID' => 1, 'BLOG_ID' => 1, 'DOMAIN' => 'example.test'],
        ['ROWID' => 404, 'BLOG_ID' => 404, 'DOMAIN' => 'network.example.test'],
    ],
    'WPOPTIONNAMES' => [
        ['NAME' => 'siteurl', 'BLOG_ID' => 1],
        ['NAME' => 'home', 'BLOG_ID' => 1],
        ['NAME' => 'missing', 'BLOG_ID' => 2],
    ],
    'WPDEFAULTS' => [
        ['ROWID' => 1, 'NAME' => 'siteurl', 'ENABLED' => 1],
        ['ROWID' => 2, 'NAME' => 'missing_default', 'ENABLED' => 0],
    ],
    'WPOPTIONS' => $currentTables171['wpoptions'],
];

$page171 = static fn (
    int $offset = 0,
    int $limit = 171,
    ?array $cursor = null,
    ?array $nextRecords = null,
    ?array $nextTables = null,
    string $indexSql = 'PRAGMA index_xinfo(WpOptionNamesLookup)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog171(
    $currentRecords171,
    $currentTables171,
    $nextRecords ?? $nextRecords171,
    $nextTables ?? $nextTables171,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt171 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default171 = static fn (): array => $page171();
$blocked171 = static fn (): array => $page171(nextTables: $currentTables171);
$sameSchema171 = static fn (): array => $page171(nextRecords: $currentRecords171);
$foreignKeys171 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog171($currentRecords171);
$tableValued171 = static fn (): array => $page171(indexSql: "pragma_index_xinfo('WpOptionNamesLookup')", tableValued: true);

$cases171 = [
    'status ok after timing-aware repair' => [$default171, 'status', 'ok'],
    'default limit' => [$default171, 'limit', 171],
    'total rows' => [$default171, 'total', 14],
    'count rows' => [$default171, 'count', 14],
    'complete true' => [$default171, 'complete', true],
    'next null' => [$default171, 'next', null],
    'timing source current' => [$default171, 'current_source.foreign_key_timing_source', 'sqlite_schema_foreign_key_deferrable'],
    'timing source next' => [$default171, 'next_source.foreign_key_timing_source', 'sqlite_schema_foreign_key_deferrable'],
    'action source inherited' => [$default171, 'current_source.foreign_key_action_source', 'pragma_foreign_key_list_actions'],
    'table key source inherited' => [$default171, 'current_source.table_key_source', 'sqlite_schema_casefold'],
    'derived current foreign keys' => [$default171, 'current_source.derived_foreign_keys', 3],
    'current timing summary first' => [$default171, 'current_source.foreign_key_timing.0', 'WpOptions#0->WpSites:timing=deferrable_deferred'],
    'current timing summary second' => [$default171, 'current_source.foreign_key_timing.1', 'WpOptions#1->WpOptionNames:timing=deferrable_immediate'],
    'current timing summary third' => [$default171, 'current_source.foreign_key_timing.2', 'WpOptions#2->WpDefaults:timing=not_deferrable'],
    'next timing summary first' => [$default171, 'next_source.foreign_key_timing.0', 'WpOptions#0->WpSites:timing=deferrable_immediate'],
    'next timing summary second' => [$default171, 'next_source.foreign_key_timing.1', 'WpOptions#1->WpOptionNames:timing=deferrable_deferred'],
    'next timing summary third' => [$default171, 'next_source.foreign_key_timing.2', 'WpOptions#2->WpDefaults:timing=not_deferrable'],
    'current timing deferred count' => [$default171, 'current.foreign_key_timing.timing:deferrable_deferred', 1],
    'current timing immediate count' => [$default171, 'current.foreign_key_timing.timing:deferrable_immediate', 1],
    'current timing not deferrable count' => [$default171, 'current.foreign_key_timing.timing:not_deferrable', 1],
    'current deferrable yes count' => [$default171, 'current.foreign_key_timing.deferrable:yes', 2],
    'current deferrable no count' => [$default171, 'current.foreign_key_timing.deferrable:no', 1],
    'current initially deferred yes count' => [$default171, 'current.foreign_key_timing.initially_deferred:yes', 1],
    'next deferred count' => [$default171, 'next_counts.foreign_key_timing.timing:deferrable_deferred', 1],
    'next immediate count' => [$default171, 'next_counts.foreign_key_timing.timing:deferrable_immediate', 1],
    'next not deferrable count' => [$default171, 'next_counts.foreign_key_timing.timing:not_deferrable', 1],
    'timing change count' => [$default171, 'delta.foreign_key_timing_changes', 4],
    'timing changed true' => [$default171, 'delta.foreign_key_timing_changed', true],
    'same schema timing changed false' => [$sameSchema171, 'delta.foreign_key_timing_changed', false],
    'same schema timing change count zero' => [$sameSchema171, 'delta.foreign_key_timing_changes', 0],
    'current xinfo rows' => [$default171, 'current.index_xinfo', 2],
    'next xinfo rows' => [$default171, 'next_counts.index_xinfo', 2],
    'current admissions' => [$default171, 'current.index_admissions', 3],
    'next admissions' => [$default171, 'next_counts.index_admissions', 3],
    'current fk violations' => [$default171, 'current.foreign_key_violations', 4],
    'next fk violations' => [$default171, 'next_counts.foreign_key_violations', 0],
    'delta violations cleared' => [$default171, 'delta.foreign_key_violations', -4],
    'delta cleared' => [$default171, 'delta.cleared', true],
    'row2 admission deferred' => [$default171, 'rows.2.timing', 'deferrable_deferred'],
    'row2 admission deferrable' => [$default171, 'rows.2.deferrable', true],
    'row2 admission initially deferred' => [$default171, 'rows.2.initially_deferred', true],
    'row3 admission immediate' => [$default171, 'rows.3.timing', 'deferrable_immediate'],
    'row3 admission initially not deferred' => [$default171, 'rows.3.initially_deferred', false],
    'row4 admission not deferrable' => [$default171, 'rows.4.timing', 'not_deferrable'],
    'row4 admission deferrable false' => [$default171, 'rows.4.deferrable', false],
    'row5 violation deferred' => [$default171, 'rows.5.timing', 'deferrable_deferred'],
    'row6 violation immediate' => [$default171, 'rows.6.timing', 'deferrable_immediate'],
    'row7 second composite violation immediate' => [$default171, 'rows.7.timing', 'deferrable_immediate'],
    'row8 violation not deferrable' => [$default171, 'rows.8.timing', 'not_deferrable'],
    'row9 next side' => [$default171, 'rows.9.side', 'next'],
    'row11 next immediate' => [$default171, 'rows.11.timing', 'deferrable_immediate'],
    'row12 next deferred' => [$default171, 'rows.12.timing', 'deferrable_deferred'],
    'row13 next not deferrable' => [$default171, 'rows.13.timing', 'not_deferrable'],
    'blocked status' => [$blocked171, 'status', 'blocked'],
    'blocked next violations' => [$blocked171, 'next_counts.foreign_key_violations', 4],
    'blocked timing preserved' => [$blocked171, 'next_counts.foreign_key_timing.timing:deferrable_deferred', 1],
    'fk0 timing' => [$foreignKeys171, '0.timing', 'deferrable_deferred'],
    'fk0 deferrable' => [$foreignKeys171, '0.deferrable', true],
    'fk0 initially deferred' => [$foreignKeys171, '0.initially_deferred', true],
    'fk1 timing' => [$foreignKeys171, '1.timing', 'deferrable_immediate'],
    'fk1 deferrable' => [$foreignKeys171, '1.deferrable', true],
    'fk1 initially deferred false' => [$foreignKeys171, '1.initially_deferred', false],
    'fk2 timing' => [$foreignKeys171, '2.timing', 'not_deferrable'],
    'fk2 deferrable false' => [$foreignKeys171, '2.deferrable', false],
    'table valued flag' => [$tableValued171, 'current_source.table_valued_index_xinfo', true],
];

$tests = [];
foreach ($cases171 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey current source next171 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt171): void {
        $t->same($expected, $valueAt171($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey current source next171 paginates timing rows'] = static function (TestRunner $t) use ($page171): void {
    $first = $page171(0, 5);
    $second = $page171(5, 5, $first['next']);
    $third = $page171(10, 5, $second['next']);

    $t->same(5, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 5], $first['next']);
    $t->same('foreign_key_check', $second['rows'][0]['kind']);
    $t->same('deferrable_deferred', $second['rows'][0]['timing']);
    $t->same('next', $third['rows'][0]['side']);
    $t->same('deferrable_deferred', $third['rows'][2]['timing']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey current source next171 source changes with timing ddl'] = static function (TestRunner $t) use ($page171, $currentRecords171): void {
    $timingChanged = $page171();
    $sameTiming = $page171(nextRecords: $currentRecords171);

    $t->same(true, $timingChanged['source_id'] !== $sameTiming['source_id']);
    $t->same(true, $timingChanged['current_source']['foreign_key_timing'] !== $timingChanged['next_source']['foreign_key_timing']);
    $t->same(false, $sameTiming['delta']['foreign_key_timing_changed']);
};

$tests['pragma index xinfo foreignkey current source next171 rejects stale source cursor'] = static function (TestRunner $t) use ($page171, $currentTables171): void {
    $first = $page171(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page171(5, 5, $first['next'], nextTables: $currentTables171));
};

$tests['pragma index xinfo foreignkey current source next171 rejects stale offset cursor'] = static function (TestRunner $t) use ($page171): void {
    $first = $page171(0, 5);
    $t->throws(InvalidArgumentException::class, static fn (): array => $page171(6, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey current source next171 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::foreignKeysFromCatalog171([['not' => 'a record']]));
};

return $tests;
