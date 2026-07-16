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
    $salt1 = 0x22000000 + $saltSeed;
    $salt2 = 0x44000000 + $saltSeed;
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
            $record('trigger', 'temp_main_bridge', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_bridge AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'temp-shadow', new.option_value); INSERT INTO main.wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal-bridge', old.option_value, new.option_value); SELECT old.option_name, new.option_value; END", 10),
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

$main = static fn (): array => $plan('main_autoloaded_update', $mainNew, $mainOld);
$temp = static fn (): array => $plan('temp_autoloaded_update', $tempNew, $tempOld);
$bridge = static fn (): array => $plan('temp_main_bridge', $mainNew, $mainOld);
$site = static fn (): array => $plan('site.site_autoloaded_update', $siteNew, $siteOld);
$rollback = static fn (): array => $plan('main_plain_delete', [], $mainOld, []);

return [
    'attach temp wal view trigger route current next50 main route count' => static fn (TestRunner $t) => $t->same(3, count($main()['operation_routes'])),
    'attach temp wal view trigger route current next50 main first route wal' => static fn (TestRunner $t) => $t->same('wal', $main()['operation_routes'][0]['journal']),
    'attach temp wal view trigger route current next50 main second route wal' => static fn (TestRunner $t) => $t->same('wal', $main()['operation_routes'][1]['journal']),
    'attach temp wal view trigger route current next50 main select route read' => static fn (TestRunner $t) => $t->same('read', $main()['operation_routes'][2]['journal']),
    'attach temp wal view trigger route current next50 main select stays current' => static fn (TestRunner $t) => $t->same('current', $main()['operation_routes'][2]['reader_boundary']),
    'attach temp wal view trigger route current next50 main update becomes next' => static fn (TestRunner $t) => $t->same('next', $main()['operation_routes'][0]['reader_boundary']),
    'attach temp wal view trigger route current next50 main insert becomes next' => static fn (TestRunner $t) => $t->same('next', $main()['operation_routes'][1]['reader_boundary']),
    'attach temp wal view trigger route current next50 main update commit visible' => static fn (TestRunner $t) => $t->same(true, $main()['operation_routes'][0]['commit_visible']),
    'attach temp wal view trigger route current next50 main select not commit visible' => static fn (TestRunner $t) => $t->same(false, $main()['operation_routes'][2]['commit_visible']),
    'attach temp wal view trigger route current next50 main route frame indexes' => static fn (TestRunner $t) => $t->same([1, 2], $main()['operation_routes'][0]['wal_frame_indexes']),
    'attach temp wal view trigger route current next50 main boundary journal' => static fn (TestRunner $t) => $t->same('wal', $main()['current_next_boundaries']['main']['journal']),
    'attach temp wal view trigger route current next50 main boundary current reader' => static fn (TestRunner $t) => $t->same('database-or-existing-wal', $main()['current_next_boundaries']['main']['current_reader']),
    'attach temp wal view trigger route current next50 main boundary next reader' => static fn (TestRunner $t) => $t->same('appended-wal', $main()['current_next_boundaries']['main']['next_reader']),
    'attach temp wal view trigger route current next50 main boundary watched pages' => static fn (TestRunner $t) => $t->same(2, $main()['current_next_boundaries']['main']['watched_pages']),
    'attach temp wal view trigger route current next50 main boundary frames' => static fn (TestRunner $t) => $t->same([1, 2], $main()['current_next_boundaries']['main']['frame_indexes']),

    'attach temp wal view trigger route current next50 temp route count' => static fn (TestRunner $t) => $t->same(2, count($temp()['operation_routes'])),
    'attach temp wal view trigger route current next50 temp first journal' => static fn (TestRunner $t) => $t->same('temp-rollback', $temp()['operation_routes'][0]['journal']),
    'attach temp wal view trigger route current next50 temp second journal' => static fn (TestRunner $t) => $t->same('temp-rollback', $temp()['operation_routes'][1]['journal']),
    'attach temp wal view trigger route current next50 temp first boundary' => static fn (TestRunner $t) => $t->same('connection-local-next', $temp()['operation_routes'][0]['reader_boundary']),
    'attach temp wal view trigger route current next50 temp second boundary' => static fn (TestRunner $t) => $t->same('connection-local-next', $temp()['operation_routes'][1]['reader_boundary']),
    'attach temp wal view trigger route current next50 temp commit visible' => static fn (TestRunner $t) => $t->same(true, $temp()['operation_routes'][0]['commit_visible']),
    'attach temp wal view trigger route current next50 temp frame indexes empty' => static fn (TestRunner $t) => $t->same([], $temp()['operation_routes'][0]['wal_frame_indexes']),
    'attach temp wal view trigger route current next50 temp boundary journal' => static fn (TestRunner $t) => $t->same('temp-rollback', $temp()['current_next_boundaries']['temp']['journal']),
    'attach temp wal view trigger route current next50 temp boundary current reader' => static fn (TestRunner $t) => $t->same('temp-btree-before-trigger', $temp()['current_next_boundaries']['temp']['current_reader']),
    'attach temp wal view trigger route current next50 temp boundary next reader' => static fn (TestRunner $t) => $t->same('connection-local-temp-btree', $temp()['current_next_boundaries']['temp']['next_reader']),
    'attach temp wal view trigger route current next50 temp operation count' => static fn (TestRunner $t) => $t->same(2, $temp()['current_next_boundaries']['temp']['operation_count']),

    'attach temp wal view trigger route current next50 bridge route count' => static fn (TestRunner $t) => $t->same(3, count($bridge()['operation_routes'])),
    'attach temp wal view trigger route current next50 bridge temp route first' => static fn (TestRunner $t) => $t->same('temp-rollback', $bridge()['operation_routes'][0]['journal']),
    'attach temp wal view trigger route current next50 bridge wal route second' => static fn (TestRunner $t) => $t->same('wal', $bridge()['operation_routes'][1]['journal']),
    'attach temp wal view trigger route current next50 bridge read route third' => static fn (TestRunner $t) => $t->same('read', $bridge()['operation_routes'][2]['journal']),
    'attach temp wal view trigger route current next50 bridge temp schema first' => static fn (TestRunner $t) => $t->same('temp', $bridge()['operation_routes'][0]['schema']),
    'attach temp wal view trigger route current next50 bridge main schema second' => static fn (TestRunner $t) => $t->same('main', $bridge()['operation_routes'][1]['schema']),
    'attach temp wal view trigger route current next50 bridge select schema third' => static fn (TestRunner $t) => $t->same('temp', $bridge()['operation_routes'][2]['schema']),
    'attach temp wal view trigger route current next50 bridge boundary schemas sorted' => static fn (TestRunner $t) => $t->same(['main', 'temp'], array_keys($bridge()['current_next_boundaries'])),
    'attach temp wal view trigger route current next50 bridge main boundary wal' => static fn (TestRunner $t) => $t->same('wal', $bridge()['current_next_boundaries']['main']['journal']),
    'attach temp wal view trigger route current next50 bridge temp boundary rollback' => static fn (TestRunner $t) => $t->same('temp-rollback', $bridge()['current_next_boundaries']['temp']['journal']),
    'attach temp wal view trigger route current next50 bridge write schemas' => static fn (TestRunner $t) => $t->same(['main' => 1, 'temp' => 1], $bridge()['writes_by_schema']),
    'attach temp wal view trigger route current next50 bridge read count' => static fn (TestRunner $t) => $t->same(1, $bridge()['read_count']),
    'attach temp wal view trigger route current next50 bridge main frames' => static fn (TestRunner $t) => $t->same([1, 2], $bridge()['operation_routes'][1]['wal_frame_indexes']),

    'attach temp wal view trigger route current next50 site boundary schema' => static fn (TestRunner $t) => $t->same(['site'], array_keys($site()['current_next_boundaries'])),
    'attach temp wal view trigger route current next50 site route journal' => static fn (TestRunner $t) => $t->same('wal', $site()['operation_routes'][0]['journal']),
    'attach temp wal view trigger route current next50 site second route journal' => static fn (TestRunner $t) => $t->same('wal', $site()['operation_routes'][1]['journal']),
    'attach temp wal view trigger route current next50 site boundary mode frames' => static fn (TestRunner $t) => $t->same([1, 2], $site()['current_next_boundaries']['site']['frame_indexes']),
    'attach temp wal view trigger route current next50 site watched pages' => static fn (TestRunner $t) => $t->same(2, $site()['current_next_boundaries']['site']['watched_pages']),
    'attach temp wal view trigger route current next50 site operation next' => static fn (TestRunner $t) => $t->same('next', $site()['operation_routes'][1]['reader_boundary']),
    'attach temp wal view trigger route current next50 site commit visible' => static fn (TestRunner $t) => $t->same(true, $site()['current_next_boundaries']['site']['commit_visible']),

    'attach temp wal view trigger route current next50 rollback route count' => static fn (TestRunner $t) => $t->same(1, count($rollback()['operation_routes'])),
    'attach temp wal view trigger route current next50 rollback route journal' => static fn (TestRunner $t) => $t->same('rollback', $rollback()['operation_routes'][0]['journal']),
    'attach temp wal view trigger route current next50 rollback boundary current' => static fn (TestRunner $t) => $t->same('database-before-trigger', $rollback()['current_next_boundaries']['main']['current_reader']),
    'attach temp wal view trigger route current next50 rollback boundary next' => static fn (TestRunner $t) => $t->same('rollback-journal-commit', $rollback()['current_next_boundaries']['main']['next_reader']),
    'attach temp wal view trigger route current next50 rollback operation count' => static fn (TestRunner $t) => $t->same(1, $rollback()['current_next_boundaries']['main']['operation_count']),
    'attach temp wal view trigger route current next50 rollback commit visible' => static fn (TestRunner $t) => $t->same(true, $rollback()['operation_routes'][0]['commit_visible']),
    'attach temp wal view trigger route current next50 rollback no wal frames' => static fn (TestRunner $t) => $t->same([], $rollback()['operation_routes'][0]['wal_frame_indexes']),
    'attach temp wal view trigger route current next50 rollback dependencies still include fallback' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-attached-trigger-rollback-journal-routing', $rollback()['dependencies'], true)),
];
