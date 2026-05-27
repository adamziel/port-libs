<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalViewTriggerPlan;
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
    $salt1 = 0x12000000 + $saltSeed;
    $salt2 = 0x34000000 + $saltSeed;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};
$emptyWal = static fn (int $saltSeed): SQLiteWal => SQLiteWal::parse($walHeader($saltSeed), null, true);
$database = static fn (string $label): string => $page($label . ' page one') . $page($label . ' page two');

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, old_value text, new_value text)', 2),
            $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
            $record('trigger', 'main_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER main_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal', old.option_value, new.option_value); SELECT new.option_id, new.option_name; END", 4),
            $record('trigger', 'main_plain_delete', 'wp_options', 0, "CREATE TRIGGER main_plain_delete AFTER DELETE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, old_value) VALUES(old.option_id, 'main-rollback', old.option_value); END", 5),
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
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, old_value text, new_value text)', 12),
        $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW site.autoloaded_options AS SELECT blog_id, option_name, option_value FROM wp_options', 13),
        $record('trigger', 'site_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER site_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; INSERT INTO wp_option_audit(blog_id, label, old_value, new_value) VALUES(new.blog_id, 'site-wal', old.option_value, new.option_value); END", 14),
    ]);

    return $catalog;
};

$mainOld = ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}'];
$mainNew = ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:11:"plugin.php";}'];
$tempOld = ['option_id' => 8, 'temp_name' => '_site_transient_update_plugins', 'option_value' => 'stale'];
$tempNew = ['option_id' => 8, 'temp_name' => '_site_transient_update_plugins', 'option_value' => 'fresh'];
$siteOld = ['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://old.example'];
$siteNew = ['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://new.example'];

$schemaWal = static function () use ($emptyWal, $database, $page): array {
    return [
        'main' => [
            'wal' => $emptyWal(1),
            'database_bytes' => $database('main before'),
            'database_path' => 'wp-content/database/.ht.sqlite',
            'transactions' => [[
                'pages' => [
                    2 => $page('main active_plugins wal next image'),
                    3 => $page('main audit wal next image'),
                ],
                'database_page_count' => 3,
                'commit' => true,
            ]],
            'watch_pages' => [2, 3],
            'mode' => 'restart',
        ],
        'site' => [
            'wal' => $emptyWal(2),
            'database_bytes' => $database('site before'),
            'database_path' => 'wp-content/database/site.sqlite',
            'transactions' => [[
                'pages' => [
                    2 => $page('site home wal next image'),
                    4 => $page('site audit wal next image'),
                ],
                'database_page_count' => 4,
                'commit' => true,
            ]],
            'watch_pages' => [2, 4],
            'mode' => 'truncate',
        ],
    ];
};

$plan = static fn (string $trigger, array $new = [], ?array $old = null, ?array $wal = null): array => SQLiteAttachTempWalViewTriggerPlan::plan(
    $catalog(),
    $trigger,
    $wal ?? $schemaWal(),
    $new,
    $old,
);

$tests = [
    'attach temp wal view trigger current next48 main status' => static fn (TestRunner $t) => $t->same('planned', $plan('main_autoloaded_update', $mainNew, $mainOld)['status']),
    'attach temp wal view trigger current next48 main trigger schema' => static fn (TestRunner $t) => $t->same('main', $plan('main_autoloaded_update', $mainNew, $mainOld)['trigger_schema']),
    'attach temp wal view trigger current next48 main target schema' => static fn (TestRunner $t) => $t->same('main', $plan('main_autoloaded_update', $mainNew, $mainOld)['target_schema']),
    'attach temp wal view trigger current next48 main operation count' => static fn (TestRunner $t) => $t->same(3, $plan('main_autoloaded_update', $mainNew, $mainOld)['operation_count']),
    'attach temp wal view trigger current next48 main read count' => static fn (TestRunner $t) => $t->same(1, $plan('main_autoloaded_update', $mainNew, $mainOld)['read_count']),
    'attach temp wal view trigger current next48 main writes by schema' => static fn (TestRunner $t) => $t->same(['main' => 2], $plan('main_autoloaded_update', $mainNew, $mainOld)['writes_by_schema']),
    'attach temp wal view trigger current next48 main wal schema count' => static fn (TestRunner $t) => $t->same(1, $plan('main_autoloaded_update', $mainNew, $mainOld)['wal_schema_count']),
    'attach temp wal view trigger current next48 main wal schema list' => static fn (TestRunner $t) => $t->same(['main'], $plan('main_autoloaded_update', $mainNew, $mainOld)['wal_schemas']),
    'attach temp wal view trigger current next48 main no temp writes' => static fn (TestRunner $t) => $t->same(0, $plan('main_autoloaded_update', $mainNew, $mainOld)['temp_write_count']),
    'attach temp wal view trigger current next48 main no rollback schemas' => static fn (TestRunner $t) => $t->same(0, $plan('main_autoloaded_update', $mainNew, $mainOld)['rollback_schema_count']),
    'attach temp wal view trigger current next48 main wal planned' => static fn (TestRunner $t) => $t->same('planned', $plan('main_autoloaded_update', $mainNew, $mainOld)['wal_plans']['main']['status']),
    'attach temp wal view trigger current next48 main wal mode restart' => static fn (TestRunner $t) => $t->same('restart', $plan('main_autoloaded_update', $mainNew, $mainOld)['wal_plans']['main']['mode']),
    'attach temp wal view trigger current next48 main next uses wal' => static fn (TestRunner $t) => $t->same(true, $plan('main_autoloaded_update', $mainNew, $mainOld)['wal_plans']['main']['next_uses_appended_wal']),
    'attach temp wal view trigger current next48 main next sources' => static fn (TestRunner $t) => $t->same(['wal', 'wal'], $plan('main_autoloaded_update', $mainNew, $mainOld)['next_reader_sources']['main']),
    'attach temp wal view trigger current next48 main frame indexes' => static fn (TestRunner $t) => $t->same([1, 2], $plan('main_autoloaded_update', $mainNew, $mainOld)['next_reader_frame_indexes']['main']),
    'attach temp wal view trigger current next48 main current from database' => static fn (TestRunner $t) => $t->same(['database', 'missing'], $plan('main_autoloaded_update', $mainNew, $mainOld)['current_reader_sources']['main']),
    'attach temp wal view trigger current next48 main first op update' => static fn (TestRunner $t) => $t->same('update', $plan('main_autoloaded_update', $mainNew, $mainOld)['operations'][0]['kind']),
    'attach temp wal view trigger current next48 main update set' => static fn (TestRunner $t) => $t->same(['option_value' => 'a:1:{i:0;s:11:"plugin.php";}'], $plan('main_autoloaded_update', $mainNew, $mainOld)['operations'][0]['set']),
    'attach temp wal view trigger current next48 main update where' => static fn (TestRunner $t) => $t->same(['column' => 'option_id', 'operator' => '=', 'value' => 7], $plan('main_autoloaded_update', $mainNew, $mainOld)['operations'][0]['where']),
    'attach temp wal view trigger current next48 main audit insert row' => static fn (TestRunner $t) => $t->same(['option_id' => 7, 'label' => 'main-wal', 'old_value' => 'a:0:{}', 'new_value' => 'a:1:{i:0;s:11:"plugin.php";}'], $plan('main_autoloaded_update', $mainNew, $mainOld)['operations'][1]['row']),
    'attach temp wal view trigger current next48 main select values' => static fn (TestRunner $t) => $t->same([7, 'active_plugins'], $plan('main_autoloaded_update', $mainNew, $mainOld)['operations'][2]['values']),
    'attach temp wal view trigger current next48 main dependencies include wal append' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-wal-append-transaction', $plan('main_autoloaded_update', $mainNew, $mainOld)['dependencies'], true)),
    'attach temp wal view trigger current next48 main dependencies include slice' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-attach-temp-wal-view-trigger-current-next', $plan('main_autoloaded_update', $mainNew, $mainOld)['dependencies'], true)),

    'attach temp wal view trigger current next48 temp trigger wins' => static fn (TestRunner $t) => $t->same('temp', $plan('temp_autoloaded_update', $tempNew, $tempOld)['trigger_schema']),
    'attach temp wal view trigger current next48 temp target temp' => static fn (TestRunner $t) => $t->same('temp', $plan('temp_autoloaded_update', $tempNew, $tempOld)['target_schema']),
    'attach temp wal view trigger current next48 temp has no wal schemas' => static fn (TestRunner $t) => $t->same([], $plan('temp_autoloaded_update', $tempNew, $tempOld)['wal_schemas']),
    'attach temp wal view trigger current next48 temp write count' => static fn (TestRunner $t) => $t->same(2, $plan('temp_autoloaded_update', $tempNew, $tempOld)['temp_write_count']),
    'attach temp wal view trigger current next48 temp schemas' => static fn (TestRunner $t) => $t->same(['temp'], $plan('temp_autoloaded_update', $tempNew, $tempOld)['temp_schemas']),
    'attach temp wal view trigger current next48 temp first journal' => static fn (TestRunner $t) => $t->same('temp-rollback', $plan('temp_autoloaded_update', $tempNew, $tempOld)['temp_operations'][0]['journal']),
    'attach temp wal view trigger current next48 temp no rollback schemas' => static fn (TestRunner $t) => $t->same(0, $plan('temp_autoloaded_update', $tempNew, $tempOld)['rollback_schema_count']),
    'attach temp wal view trigger current next48 temp dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-temp-trigger-rollback-journal-routing', $plan('temp_autoloaded_update', $tempNew, $tempOld)['dependencies'], true)),
    'attach temp wal view trigger current next48 temp operation schema' => static fn (TestRunner $t) => $t->same('temp', $plan('temp_autoloaded_update', $tempNew, $tempOld)['operations'][0]['schema']),
    'attach temp wal view trigger current next48 temp audit row' => static fn (TestRunner $t) => $t->same(['option_id' => 8, 'label' => 'temp-rollback', 'old_value' => 'stale', 'new_value' => 'fresh'], $plan('temp_autoloaded_update', $tempNew, $tempOld)['operations'][1]['row']),

    'attach temp wal view trigger current next48 bridge trigger temp' => static fn (TestRunner $t) => $t->same('temp', $plan('temp_main_bridge', $mainNew, $mainOld)['trigger_schema']),
    'attach temp wal view trigger current next48 bridge target main' => static fn (TestRunner $t) => $t->same('main', $plan('temp_main_bridge', $mainNew, $mainOld)['target_schema']),
    'attach temp wal view trigger current next48 bridge split writes' => static fn (TestRunner $t) => $t->same(['main' => 1, 'temp' => 1], $plan('temp_main_bridge', $mainNew, $mainOld)['writes_by_schema']),
    'attach temp wal view trigger current next48 bridge wal only main' => static fn (TestRunner $t) => $t->same(['main'], $plan('temp_main_bridge', $mainNew, $mainOld)['wal_schemas']),
    'attach temp wal view trigger current next48 bridge temp count' => static fn (TestRunner $t) => $t->same(1, $plan('temp_main_bridge', $mainNew, $mainOld)['temp_write_count']),
    'attach temp wal view trigger current next48 bridge main wal frames' => static fn (TestRunner $t) => $t->same([1, 2], $plan('temp_main_bridge', $mainNew, $mainOld)['next_reader_frame_indexes']['main']),
    'attach temp wal view trigger current next48 bridge temp shadow first' => static fn (TestRunner $t) => $t->same('temp', $plan('temp_main_bridge', $mainNew, $mainOld)['operations'][0]['schema']),
    'attach temp wal view trigger current next48 bridge qualified main second' => static fn (TestRunner $t) => $t->same('main', $plan('temp_main_bridge', $mainNew, $mainOld)['operations'][1]['schema']),
    'attach temp wal view trigger current next48 bridge main row' => static fn (TestRunner $t) => $t->same(['option_id' => 7, 'label' => 'main-wal-bridge', 'old_value' => 'a:0:{}', 'new_value' => 'a:1:{i:0;s:11:"plugin.php";}'], $plan('temp_main_bridge', $mainNew, $mainOld)['operations'][1]['row']),

    'attach temp wal view trigger current next48 attached trigger schema' => static fn (TestRunner $t) => $t->same('site', $plan('site.site_autoloaded_update', $siteNew, $siteOld)['trigger_schema']),
    'attach temp wal view trigger current next48 attached target schema' => static fn (TestRunner $t) => $t->same('site', $plan('site.site_autoloaded_update', $siteNew, $siteOld)['target_schema']),
    'attach temp wal view trigger current next48 attached wal schema' => static fn (TestRunner $t) => $t->same(['site'], $plan('site.site_autoloaded_update', $siteNew, $siteOld)['wal_schemas']),
    'attach temp wal view trigger current next48 attached truncate mode' => static fn (TestRunner $t) => $t->same('truncate', $plan('site.site_autoloaded_update', $siteNew, $siteOld)['wal_plans']['site']['mode']),
    'attach temp wal view trigger current next48 attached path' => static fn (TestRunner $t) => $t->same('wp-content/database/site.sqlite-wal', $plan('site.site_autoloaded_update', $siteNew, $siteOld)['wal_plans']['site']['wal_path']),
    'attach temp wal view trigger current next48 attached frame indexes' => static fn (TestRunner $t) => $t->same([1, 2], $plan('site.site_autoloaded_update', $siteNew, $siteOld)['next_reader_frame_indexes']['site']),
    'attach temp wal view trigger current next48 attached current sources' => static fn (TestRunner $t) => $t->same(['database', 'missing'], $plan('site.site_autoloaded_update', $siteNew, $siteOld)['current_reader_sources']['site']),
    'attach temp wal view trigger current next48 attached operation row' => static fn (TestRunner $t) => $t->same(['blog_id' => 3, 'label' => 'site-wal', 'old_value' => 'https://old.example', 'new_value' => 'https://new.example'], $plan('site.site_autoloaded_update', $siteNew, $siteOld)['operations'][1]['row']),

    'attach temp wal view trigger current next48 fallback rollback schema' => static fn (TestRunner $t) => $t->same(['main'], $plan('main_plain_delete', [], $mainOld, [])['rollback_schemas']),
    'attach temp wal view trigger current next48 fallback rollback count' => static fn (TestRunner $t) => $t->same(1, $plan('main_plain_delete', [], $mainOld, [])['rollback_schema_count']),
    'attach temp wal view trigger current next48 fallback no wal' => static fn (TestRunner $t) => $t->same([], $plan('main_plain_delete', [], $mainOld, [])['wal_schemas']),
    'attach temp wal view trigger current next48 fallback dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-attached-trigger-rollback-journal-routing', $plan('main_plain_delete', [], $mainOld, [])['dependencies'], true)),
    'attach temp wal view trigger current next48 fallback row' => static fn (TestRunner $t) => $t->same(['option_id' => 7, 'label' => 'main-rollback', 'old_value' => 'a:0:{}'], $plan('main_plain_delete', [], $mainOld, [])['rollback_operations'][0]['row']),

    'attach temp wal view trigger current next48 rejects missing wal object' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('main_autoloaded_update', $mainNew, $mainOld, ['main' => ['database_bytes' => 'x', 'database_path' => 'db', 'transactions' => [['pages' => [1 => $page('x')], 'database_page_count' => 1]], 'watch_pages' => [1]]])),
    'attach temp wal view trigger current next48 rejects missing database bytes' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('main_autoloaded_update', $mainNew, $mainOld, ['main' => ['wal' => $emptyWal(3), 'database_path' => 'db', 'transactions' => [['pages' => [1 => $page('x')], 'database_page_count' => 1]], 'watch_pages' => [1]]])),
    'attach temp wal view trigger current next48 rejects empty database path' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('main_autoloaded_update', $mainNew, $mainOld, ['main' => ['wal' => $emptyWal(4), 'database_bytes' => $database('bad'), 'database_path' => '', 'transactions' => [['pages' => [1 => $page('x')], 'database_page_count' => 1]], 'watch_pages' => [1]]])),
    'attach temp wal view trigger current next48 rejects empty transactions' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('main_autoloaded_update', $mainNew, $mainOld, ['main' => ['wal' => $emptyWal(5), 'database_bytes' => $database('bad'), 'database_path' => 'db', 'transactions' => [], 'watch_pages' => [1]]])),
    'attach temp wal view trigger current next48 rejects empty watch pages' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('main_autoloaded_update', $mainNew, $mainOld, ['main' => ['wal' => $emptyWal(6), 'database_bytes' => $database('bad'), 'database_path' => 'db', 'transactions' => [['pages' => [1 => $page('x')], 'database_page_count' => 1]], 'watch_pages' => []]])),
    'attach temp wal view trigger current next48 rejects unresolved trigger' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('missing_trigger', $mainNew, $mainOld)),
    'attach temp wal view trigger current next48 rejects missing old row' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan('main_autoloaded_update', $mainNew)),
];

return $tests;
