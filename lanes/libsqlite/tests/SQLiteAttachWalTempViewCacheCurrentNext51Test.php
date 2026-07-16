<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$walHeader = static function (int $saltSeed) use ($pageSize): string {
    $salt1 = 0x51000000 + $saltSeed;
    $salt2 = 0x51010000 + $saltSeed;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};
$emptyWal = static fn (int $saltSeed): SQLiteWal => SQLiteWal::parse($walHeader($saltSeed), null, true);
$database = static fn (string $label): string => $page($label . ' page one') . $page($label . ' page two') . $page($label . ' page three');

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX main.wp_options_name ON wp_options(option_name)', 2),
            $record('table', 'wp_option_audit', 'wp_option_audit', 4, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, old_value text, new_value text)', 3),
            $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 4),
            $record('trigger', 'main_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER main_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal', old.option_value, new.option_value); SELECT new.option_id, new.option_name; END", 5),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 6),
            $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, old_value text, new_value text)', 7),
            $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, temp_name, option_value FROM temp.wp_options', 8),
            $record('trigger', 'temp_autoloaded_update', 'autoloaded_options', 0, "CREATE TEMP TRIGGER temp_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'temp-rollback', old.option_value, new.option_value); END", 9),
            $record('trigger', 'temp_main_bridge', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_bridge AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'temp-shadow', new.option_value); INSERT INTO main.wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal-bridge', old.option_value, new.option_value); END", 10),
        ],
    );
    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 11),
        $record('index', 'wp_options_name', 'wp_options', 21, 'CREATE INDEX site.wp_options_name ON wp_options(option_name)', 12),
        $record('table', 'wp_option_audit', 'wp_option_audit', 22, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, old_value text, new_value text)', 13),
        $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW site.autoloaded_options AS SELECT blog_id, option_name, option_value FROM wp_options', 14),
        $record('trigger', 'site_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER site_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; INSERT INTO wp_option_audit(blog_id, label, old_value, new_value) VALUES(new.blog_id, 'site-wal', old.option_value, new.option_value); END", 15),
    ]);

    return $catalog;
};

$schemaWal = static function () use ($emptyWal, $database, $page): array {
    return [
        'main' => [
            'wal' => $emptyWal(1),
            'database_bytes' => $database('main before'),
            'database_path' => 'wp-content/database/.ht.sqlite',
            'transactions' => [[
                'pages' => [2 => $page('main option next'), 4 => $page('main audit next')],
                'database_page_count' => 4,
                'commit' => true,
            ]],
            'watch_pages' => [2, 4],
            'mode' => 'restart',
        ],
        'site' => [
            'wal' => $emptyWal(2),
            'database_bytes' => $database('site before'),
            'database_path' => 'wp-content/database/site.sqlite',
            'transactions' => [[
                'pages' => [2 => $page('site option next'), 4 => $page('site audit next')],
                'database_page_count' => 4,
                'commit' => true,
            ]],
            'watch_pages' => [2, 4],
            'mode' => 'truncate',
        ],
    ];
};

$mainOld = ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}'];
$mainNew = ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:11:"plugin.php";}'];
$tempOld = ['option_id' => 8, 'temp_name' => '_site_transient_update_plugins', 'option_value' => 'stale'];
$tempNew = ['option_id' => 8, 'temp_name' => '_site_transient_update_plugins', 'option_value' => 'fresh'];
$siteOld = ['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://old.example'];
$siteNew = ['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://new.example'];

$mainReplacement = static fn (int $root) => [
    $record('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, migrated integer)', 30 + $root),
    $record('index', 'wp_options_name', 'wp_options', $root + 1, 'CREATE INDEX main.wp_options_name ON wp_options(option_name, autoload)', 31 + $root),
    $record('table', 'wp_option_audit', 'wp_option_audit', $root + 2, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, old_value text, new_value text)', 32 + $root),
    $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 33 + $root),
    $record('trigger', 'main_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER main_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal', old.option_value, new.option_value); SELECT new.option_id, new.option_name; END", 34 + $root),
];
$tempReplacement = static fn (int $root) => [
    $record('table', 'wp_options', 'wp_options', $root, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, expires integer)', 40 + $root),
    $record('table', 'wp_option_audit', 'wp_option_audit', $root + 1, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, old_value text, new_value text)', 41 + $root),
    $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, temp_name, option_value FROM temp.wp_options', 42 + $root),
    $record('trigger', 'temp_autoloaded_update', 'autoloaded_options', 0, "CREATE TEMP TRIGGER temp_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'temp-rollback', old.option_value, new.option_value); END", 43 + $root),
    $record('trigger', 'temp_main_bridge', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_bridge AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'temp-shadow', new.option_value); INSERT INTO main.wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal-bridge', old.option_value, new.option_value); END", 44 + $root),
];
$siteReplacement = static fn (int $root) => [
    $record('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', 50 + $root),
    $record('index', 'wp_options_name', 'wp_options', $root + 1, 'CREATE INDEX site.wp_options_name ON wp_options(option_name, blog_id)', 51 + $root),
    $record('table', 'wp_option_audit', 'wp_option_audit', $root + 2, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, old_value text, new_value text)', 52 + $root),
    $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW site.autoloaded_options AS SELECT blog_id, option_name, option_value FROM wp_options', 53 + $root),
    $record('trigger', 'site_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER site_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; INSERT INTO wp_option_audit(blog_id, label, old_value, new_value) VALUES(new.blog_id, 'site-wal', old.option_value, new.option_value); END", 54 + $root),
];

$plan = static function (
    string $trigger,
    array $new,
    ?array $old,
    array $tables,
    array $indexes,
    array $updates = [],
    ?array $wal = null,
    string $source = 'main',
) use ($catalog, $schemaWal): array {
    return SQLiteAttachWalTempViewCachePlan::plan($catalog(), $trigger, $wal ?? $schemaWal(), $new, $old, $tables, $indexes, $updates, $source);
};

$stable = static fn (): array => $plan('main_autoloaded_update', $mainNew, $mainOld, ['wp_options', 'autoloaded_options', 'site.wp_options'], ['wp_options_name']);
$mainChanged = static fn (): array => $plan('main_autoloaded_update', $mainNew, $mainOld, ['wp_options', 'autoloaded_options', 'site.wp_options'], ['wp_options_name'], ['main' => $mainReplacement(60)]);
$tempChanged = static fn (): array => $plan('temp_autoloaded_update', $tempNew, $tempOld, ['wp_options', 'autoloaded_options', 'main.wp_options'], ['wp_options_name'], ['temp' => $tempReplacement(70)], []);
$siteChanged = static fn (): array => $plan('site.site_autoloaded_update', $siteNew, $siteOld, ['site.wp_options', 'autoloaded_options', 'wp_options'], ['site.wp_options_name'], ['site' => $siteReplacement(80)], null, 'site');
$bridgeChanged = static fn (): array => $plan('temp_main_bridge', $mainNew, $mainOld, ['wp_options', 'main.wp_options', 'temp.wp_options'], ['wp_options_name'], ['main' => $mainReplacement(90), 'temp' => $tempReplacement(100)]);

$tests = [
    'attach wal temp view cache current next51 stable status' => static fn (TestRunner $t) => $t->same('planned', $stable()['status']),
    'attach wal temp view cache current next51 stable trigger schema' => static fn (TestRunner $t) => $t->same('main', $stable()['trigger_schema']),
    'attach wal temp view cache current next51 stable target schema' => static fn (TestRunner $t) => $t->same('main', $stable()['target_schema']),
    'attach wal temp view cache current next51 stable source schema' => static fn (TestRunner $t) => $t->same('main', $stable()['source_schema']),
    'attach wal temp view cache current next51 stable operation count' => static fn (TestRunner $t) => $t->same(3, $stable()['operation_count']),
    'attach wal temp view cache current next51 stable read count' => static fn (TestRunner $t) => $t->same(1, $stable()['read_count']),
    'attach wal temp view cache current next51 stable wal schema count' => static fn (TestRunner $t) => $t->same(1, $stable()['wal_schema_count']),
    'attach wal temp view cache current next51 stable wal schemas' => static fn (TestRunner $t) => $t->same(['main'], $stable()['wal_schemas']),
    'attach wal temp view cache current next51 stable changed schemas from wal only' => static fn (TestRunner $t) => $t->same(['main'], $stable()['changed_schemas']),
    'attach wal temp view cache current next51 stable not stale' => static fn (TestRunner $t) => $t->same(false, $stable()['stale']),
    'attach wal temp view cache current next51 stable no reprepare' => static fn (TestRunner $t) => $t->same(false, $stable()['requires_reprepare']),
    'attach wal temp view cache current next51 stable unchanged tables' => static fn (TestRunner $t) => $t->same(['wp_options', 'autoloaded_options', 'site.wp_options'], $stable()['unchanged_tables']),
    'attach wal temp view cache current next51 stable unchanged indexes' => static fn (TestRunner $t) => $t->same(['wp_options_name'], $stable()['unchanged_indexes']),
    'attach wal temp view cache current next51 stable no changed tables' => static fn (TestRunner $t) => $t->same([], $stable()['changed_tables']),
    'attach wal temp view cache current next51 stable no changed indexes' => static fn (TestRunner $t) => $t->same([], $stable()['changed_indexes']),
    'attach wal temp view cache current next51 stable before generation' => static fn (TestRunner $t) => $t->same(1, $stable()['before']['generation']),
    'attach wal temp view cache current next51 stable next generation unchanged' => static fn (TestRunner $t) => $t->same(1, $stable()['next_generation']),
    'attach wal temp view cache current next51 stable table before main root' => static fn (TestRunner $t) => $t->same(10, $stable()['before']['tables']['wp_options']['rootpage']),
    'attach wal temp view cache current next51 stable qualified site root' => static fn (TestRunner $t) => $t->same(20, $stable()['before']['tables']['site.wp_options']['rootpage']),
    'attach wal temp view cache current next51 stable wal append dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-wal-append-transaction', $stable()['dependencies'], true)),
    'attach wal temp view cache current next51 stable slice dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-attach-wal-temp-view-cache-current-next', $stable()['dependencies'], true)),
    'attach wal temp view cache current next51 stable no reprepare dependency' => static fn (TestRunner $t) => $t->same(false, in_array('sqlite-schema-cache-reprepare-after-wal-trigger', $stable()['dependencies'], true)),

    'attach wal temp view cache current next51 main update stale' => static fn (TestRunner $t) => $t->same(true, $mainChanged()['stale']),
    'attach wal temp view cache current next51 main requires reprepare' => static fn (TestRunner $t) => $t->same(true, $mainChanged()['requires_reprepare']),
    'attach wal temp view cache current next51 main schema updates' => static fn (TestRunner $t) => $t->same(['main'], $mainChanged()['schema_record_updates']),
    'attach wal temp view cache current next51 main changed schemas' => static fn (TestRunner $t) => $t->same(['main'], $mainChanged()['changed_schemas']),
    'attach wal temp view cache current next51 main changed table list' => static fn (TestRunner $t) => $t->same([], $mainChanged()['changed_tables']),
    'attach wal temp view cache current next51 main changed index list' => static fn (TestRunner $t) => $t->same(['wp_options_name'], $mainChanged()['changed_indexes']),
    'attach wal temp view cache current next51 main temp shadow table unchanged' => static fn (TestRunner $t) => $t->same(['wp_options', 'autoloaded_options', 'site.wp_options'], $mainChanged()['unchanged_tables']),
    'attach wal temp view cache current next51 main after view remains root zero' => static fn (TestRunner $t) => $t->same(0, $mainChanged()['invalidation']['table_changes']['autoloaded_options']['after']['rootpage']),
    'attach wal temp view cache current next51 main before index root' => static fn (TestRunner $t) => $t->same(3, $mainChanged()['invalidation']['index_changes']['wp_options_name']['before']['rootpage']),
    'attach wal temp view cache current next51 main after index root' => static fn (TestRunner $t) => $t->same(61, $mainChanged()['invalidation']['index_changes']['wp_options_name']['after']['rootpage']),
    'attach wal temp view cache current next51 main generation advanced' => static fn (TestRunner $t) => $t->same(2, $mainChanged()['next_generation']),
    'attach wal temp view cache current next51 main reprepare dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-schema-cache-reprepare-after-wal-trigger', $mainChanged()['dependencies'], true)),
    'attach wal temp view cache current next51 main next reader remains wal' => static fn (TestRunner $t) => $t->same(['wal', 'wal'], $mainChanged()['trigger_plan']['next_reader_sources']['main']),

    'attach wal temp view cache current next51 temp changed trigger schema' => static fn (TestRunner $t) => $t->same('temp', $tempChanged()['trigger_schema']),
    'attach wal temp view cache current next51 temp write count' => static fn (TestRunner $t) => $t->same(2, $tempChanged()['temp_write_count']),
    'attach wal temp view cache current next51 temp no wal schemas' => static fn (TestRunner $t) => $t->same([], $tempChanged()['wal_schemas']),
    'attach wal temp view cache current next51 temp changed schemas' => static fn (TestRunner $t) => $t->same(['temp'], $tempChanged()['changed_schemas']),
    'attach wal temp view cache current next51 temp changed table shadow' => static fn (TestRunner $t) => $t->same(['wp_options'], $tempChanged()['changed_tables']),
    'attach wal temp view cache current next51 temp qualified main unchanged' => static fn (TestRunner $t) => $t->same(['autoloaded_options', 'main.wp_options'], $tempChanged()['unchanged_tables']),
    'attach wal temp view cache current next51 temp rollback dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-temp-trigger-rollback-journal-routing', $tempChanged()['dependencies'], true)),
    'attach wal temp view cache current next51 temp before root' => static fn (TestRunner $t) => $t->same(10, $tempChanged()['invalidation']['table_changes']['wp_options']['before']['rootpage']),
    'attach wal temp view cache current next51 temp after root' => static fn (TestRunner $t) => $t->same(70, $tempChanged()['invalidation']['table_changes']['wp_options']['after']['rootpage']),
    'attach wal temp view cache current next51 temp generation advanced' => static fn (TestRunner $t) => $t->same(2, $tempChanged()['next_generation']),

    'attach wal temp view cache current next51 site source schema' => static fn (TestRunner $t) => $t->same('site', $siteChanged()['source_schema']),
    'attach wal temp view cache current next51 site wal schema' => static fn (TestRunner $t) => $t->same(['site'], $siteChanged()['wal_schemas']),
    'attach wal temp view cache current next51 site changed schemas' => static fn (TestRunner $t) => $t->same(['site'], $siteChanged()['changed_schemas']),
    'attach wal temp view cache current next51 site changed qualified table' => static fn (TestRunner $t) => $t->same(['site.wp_options'], $siteChanged()['changed_tables']),
    'attach wal temp view cache current next51 site changed qualified index' => static fn (TestRunner $t) => $t->same(['site.wp_options_name'], $siteChanged()['changed_indexes']),
    'attach wal temp view cache current next51 site unqualified remains temp shadow' => static fn (TestRunner $t) => $t->same(['autoloaded_options', 'wp_options'], $siteChanged()['unchanged_tables']),
    'attach wal temp view cache current next51 site before root' => static fn (TestRunner $t) => $t->same(20, $siteChanged()['invalidation']['table_changes']['site.wp_options']['before']['rootpage']),
    'attach wal temp view cache current next51 site after root' => static fn (TestRunner $t) => $t->same(80, $siteChanged()['invalidation']['table_changes']['site.wp_options']['after']['rootpage']),
    'attach wal temp view cache current next51 site truncate wal mode' => static fn (TestRunner $t) => $t->same('truncate', $siteChanged()['trigger_plan']['wal_plans']['site']['mode']),
    'attach wal temp view cache current next51 site next reader frames' => static fn (TestRunner $t) => $t->same([1, 2], $siteChanged()['trigger_plan']['next_reader_frame_indexes']['site']),

    'attach wal temp view cache current next51 bridge trigger temp' => static fn (TestRunner $t) => $t->same('temp', $bridgeChanged()['trigger_schema']),
    'attach wal temp view cache current next51 bridge target main' => static fn (TestRunner $t) => $t->same('main', $bridgeChanged()['target_schema']),
    'attach wal temp view cache current next51 bridge writes temp and main' => static fn (TestRunner $t) => $t->same(['main' => 1, 'temp' => 1], $bridgeChanged()['trigger_plan']['writes_by_schema']),
    'attach wal temp view cache current next51 bridge wal and temp changed schemas' => static fn (TestRunner $t) => $t->same(['main', 'temp'], $bridgeChanged()['changed_schemas']),
    'attach wal temp view cache current next51 bridge changed unqualified temp table' => static fn (TestRunner $t) => $t->same(true, in_array('wp_options', $bridgeChanged()['changed_tables'], true)),
    'attach wal temp view cache current next51 bridge changed qualified main table' => static fn (TestRunner $t) => $t->same(true, in_array('main.wp_options', $bridgeChanged()['changed_tables'], true)),
    'attach wal temp view cache current next51 bridge changed qualified temp table' => static fn (TestRunner $t) => $t->same(true, in_array('temp.wp_options', $bridgeChanged()['changed_tables'], true)),
    'attach wal temp view cache current next51 bridge changed index' => static fn (TestRunner $t) => $t->same(['wp_options_name'], $bridgeChanged()['changed_indexes']),
    'attach wal temp view cache current next51 bridge main wal dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-wal-checkpoint-append-current-next', $bridgeChanged()['dependencies'], true)),
    'attach wal temp view cache current next51 bridge reprepare dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-schema-cache-reprepare-after-wal-trigger', $bridgeChanged()['dependencies'], true)),
    'attach wal temp view cache current next51 rejects missing wal entry' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('main_autoloaded_update', $mainNew, $mainOld, ['wp_options'], [], [], ['main' => ['database_bytes' => 'x', 'database_path' => 'db', 'transactions' => [['pages' => [1 => $page('x')], 'database_page_count' => 1]], 'watch_pages' => [1]]])),
    'attach wal temp view cache current next51 rejects bad source schema' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('main_autoloaded_update', $mainNew, $mainOld, ['wp_options'], [], [], null, 'missing')),
    'attach wal temp view cache current next51 rejects bad replacement schema' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('main_autoloaded_update', $mainNew, $mainOld, ['wp_options'], [], ['missing' => $mainReplacement(120)])),
    'attach wal temp view cache current next51 rejects unresolved trigger' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('missing_trigger', $mainNew, $mainOld, ['wp_options'], [])),
];

return $tests;
