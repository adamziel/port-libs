<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalViewTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$walHeader = static function (int $saltSeed) use ($pageSize): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x68100000 + $saltSeed, 0x68200000 + $saltSeed);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};
$emptyWal = static fn (int $saltSeed): SQLiteWal => SQLiteWal::parse($walHeader($saltSeed), null, true);
$database = static fn (string $label): string => $page($label . ' page one') . $page($label . ' page two') . $page($label . ' page three');

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, old_value text, new_value text)', 2),
        $record('trigger', 'main_write_then_read', 'wp_options', 0, "CREATE TRIGGER main_write_then_read AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; SELECT new.option_id, new.option_value FROM wp_options WHERE option_id = new.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'after-read', old.option_value, new.option_value); SELECT new.option_id FROM wp_option_audit WHERE option_id = new.option_id; END", 3),
        $record('trigger', 'main_read_before_write', 'wp_options', 0, "CREATE TRIGGER main_read_before_write AFTER UPDATE ON wp_options BEGIN SELECT old.option_id FROM wp_options WHERE option_id = old.option_id; UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END", 4),
        $record('trigger', 'main_rollback_read', 'wp_options', 0, "CREATE TRIGGER main_rollback_read AFTER DELETE ON wp_options BEGIN DELETE FROM wp_option_audit WHERE option_id = old.option_id; SELECT old.option_id FROM wp_option_audit WHERE option_id = old.option_id; END", 5),
    ], [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 6),
        $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, new_value text)', 7),
        $record('trigger', 'temp_main_bridge_read_after_write', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_bridge_read_after_write AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'temp-shadow', new.option_value); SELECT new.option_id, new.option_value FROM wp_option_audit WHERE option_id = new.option_id; INSERT INTO main.wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal', old.option_value, new.option_value); SELECT new.option_id FROM main.wp_option_audit WHERE option_id = new.option_id; END", 8),
    ]);

    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 9),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, old_value text, new_value text)', 10),
        $record('trigger', 'site_write_then_read', 'wp_options', 0, "CREATE TRIGGER site_write_then_read AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; SELECT new.blog_id, new.option_value FROM wp_options WHERE blog_id = new.blog_id; INSERT INTO wp_option_audit(blog_id, label, old_value, new_value) VALUES(new.blog_id, 'site-after-read', old.option_value, new.option_value); SELECT new.blog_id FROM wp_option_audit WHERE blog_id = new.blog_id; END", 11),
    ]);

    return $catalog;
};

$mainOld = ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'];
$mainNew = ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:11:"plugin.php";}', 'autoload' => 'yes'];
$siteOld = ['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://old.example'];
$siteNew = ['blog_id' => 3, 'option_name' => 'home', 'option_value' => 'https://new.example'];

$schemaWal = static function () use ($emptyWal, $database, $page): array {
    return [
        'main' => [
            'wal' => $emptyWal(1),
            'database_bytes' => $database('main before'),
            'database_path' => 'wp-content/database/.ht.sqlite',
            'transactions' => [[
                'pages' => [2 => $page('main options next image'), 3 => $page('main audit next image')],
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
                'pages' => [2 => $page('site options next image'), 3 => $page('site audit next image')],
                'database_page_count' => 3,
                'commit' => true,
            ]],
            'watch_pages' => [2, 3],
            'mode' => 'truncate',
        ],
    ];
};

$plan = static fn (string $trigger, array $new, ?array $old, ?array $wal = null): array => SQLiteAttachTempWalViewTriggerPlan::plan($catalog(), $trigger, $wal ?? $schemaWal(), $new, $old);
$main = static fn (): array => $plan('main_write_then_read', $mainNew, $mainOld);
$before = static fn (): array => $plan('main_read_before_write', $mainNew, $mainOld);
$rollback = static fn (): array => $plan('main_rollback_read', [], $mainOld, []);
$bridge = static fn (): array => $plan('temp_main_bridge_read_after_write', $mainNew, $mainOld);
$site = static fn (): array => $plan('site.site_write_then_read', $siteNew, $siteOld);

$value = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
            return null;
        }
        $cursor = $cursor[$part];
    }

    return $cursor;
};

$cases = [
    'main status' => [$main, 'status', 'planned'],
    'main operation count' => [$main, 'operation_count', 4],
    'main read count' => [$main, 'read_count', 2],
    'main writes by schema' => [$main, 'writes_by_schema', ['main' => 2]],
    'main first route wal' => [$main, 'operation_routes.0.journal', 'wal'],
    'main first route next' => [$main, 'operation_routes.0.reader_boundary', 'next'],
    'main first route not read after write' => [$main, 'operation_routes.0.read_after_write', false],
    'main first select is read' => [$main, 'operation_routes.1.journal', 'read'],
    'main first select sees next' => [$main, 'operation_routes.1.reader_boundary', 'next'],
    'main first select read after write' => [$main, 'operation_routes.1.read_after_write', true],
    'main first select prior journal wal' => [$main, 'operation_routes.1.prior_write_journal', 'wal'],
    'main first select inherits frames' => [$main, 'operation_routes.1.wal_frame_indexes', [1, 2]],
    'main insert remains wal' => [$main, 'operation_routes.2.journal', 'wal'],
    'main second select sees next' => [$main, 'operation_routes.3.reader_boundary', 'next'],
    'main second select read after write' => [$main, 'operation_routes.3.read_after_write', true],
    'main second select prior journal wal' => [$main, 'operation_routes.3.prior_write_journal', 'wal'],
    'main select operation schema resolved' => [$main, 'operations.1.schema', 'main'],
    'main select operation table resolved' => [$main, 'operations.1.table', 'wp_options'],
    'main select values from new row' => [$main, 'operations.1.values', [7, 'a:1:{i:0;s:11:"plugin.php";}']],
    'main select where predicate' => [$main, 'operations.1.where', ['column' => 'option_id', 'operator' => '=', 'value' => 7]],
    'main boundary is wal' => [$main, 'current_next_boundaries.main.journal', 'wal'],
    'main dependency preserved' => [$main, 'dependencies.0', 'sqlite-attach-temp-wal-view-trigger-current-next'],

    'read before write route count' => [$before, 'operation_count', 2],
    'read before write select journal' => [$before, 'operation_routes.0.journal', 'read'],
    'read before write stays current' => [$before, 'operation_routes.0.reader_boundary', 'current'],
    'read before write not read after write' => [$before, 'operation_routes.0.read_after_write', false],
    'read before write no prior journal' => [$before, 'operation_routes.0.prior_write_journal', null],
    'read before write update next' => [$before, 'operation_routes.1.reader_boundary', 'next'],

    'rollback route count' => [$rollback, 'operation_count', 2],
    'rollback first journal' => [$rollback, 'operation_routes.0.journal', 'rollback'],
    'rollback select journal read' => [$rollback, 'operation_routes.1.journal', 'read'],
    'rollback select sees next' => [$rollback, 'operation_routes.1.reader_boundary', 'next'],
    'rollback select read after write' => [$rollback, 'operation_routes.1.read_after_write', true],
    'rollback select prior journal' => [$rollback, 'operation_routes.1.prior_write_journal', 'rollback'],
    'rollback select no wal frames' => [$rollback, 'operation_routes.1.wal_frame_indexes', []],
    'rollback boundary next' => [$rollback, 'current_next_boundaries.main.next_reader', 'rollback-journal-commit'],

    'bridge route count' => [$bridge, 'operation_count', 4],
    'bridge temp insert journal' => [$bridge, 'operation_routes.0.journal', 'temp-rollback'],
    'bridge temp select sees connection local next' => [$bridge, 'operation_routes.1.reader_boundary', 'connection-local-next'],
    'bridge temp select read after write' => [$bridge, 'operation_routes.1.read_after_write', true],
    'bridge temp select prior journal' => [$bridge, 'operation_routes.1.prior_write_journal', 'temp-rollback'],
    'bridge main insert journal' => [$bridge, 'operation_routes.2.journal', 'wal'],
    'bridge main select sees wal next' => [$bridge, 'operation_routes.3.reader_boundary', 'next'],
    'bridge main select prior journal' => [$bridge, 'operation_routes.3.prior_write_journal', 'wal'],
    'bridge temp select schema' => [$bridge, 'operations.1.schema', 'temp'],
    'bridge main select schema' => [$bridge, 'operations.3.schema', 'main'],
    'bridge boundaries sorted' => [$bridge, 'current_next_boundaries', ['main' => $bridge()['current_next_boundaries']['main'], 'temp' => $bridge()['current_next_boundaries']['temp']]],
    'bridge write schemas' => [$bridge, 'writes_by_schema', ['main' => 1, 'temp' => 1]],

    'site trigger schema' => [$site, 'trigger_schema', 'site'],
    'site writes by schema' => [$site, 'writes_by_schema', ['site' => 2]],
    'site first select sees next' => [$site, 'operation_routes.1.reader_boundary', 'next'],
    'site first select prior journal' => [$site, 'operation_routes.1.prior_write_journal', 'wal'],
    'site first select frames' => [$site, 'operation_routes.1.wal_frame_indexes', [1, 2]],
    'site second select read after write' => [$site, 'operation_routes.3.read_after_write', true],
    'site boundary wal' => [$site, 'current_next_boundaries.site.journal', 'wal'],
    'site boundary next reader' => [$site, 'current_next_boundaries.site.next_reader', 'appended-wal'],
];

$tests = [];
foreach ($cases as $name => [$producer, $path, $expected]) {
    $tests['attach wal temp view trigger current next81 ' . $name] = static function (TestRunner $t) use ($producer, $path, $expected, $value): void {
        $t->same($expected, $value($producer(), $path));
    };
}

$tests['attach wal temp view trigger current next81 missing wal still routes rollback read after write'] = static function (TestRunner $t) use ($plan, $mainNew, $mainOld): void {
    $result = $plan('main_write_then_read', $mainNew, $mainOld, []);
    $t->same('rollback', $result['operation_routes'][0]['journal']);
    $t->same('next', $result['operation_routes'][1]['reader_boundary']);
    $t->same('rollback', $result['operation_routes'][1]['prior_write_journal']);
};

return $tests;
