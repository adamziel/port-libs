<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempMainWalCollationCachePlan;
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
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x24000000 + $saltSeed, 0x55000000 + $saltSeed);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};
$emptyWal = static fn (int $saltSeed): SQLiteWal => SQLiteWal::parse($walHeader($saltSeed), null, true);
$database = static fn (string $label): string => $page($label . ' page one') . $page($label . ' page two') . $page($label . ' page three');

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text COLLATE NOCASE, option_value text COLLATE RTRIM, autoload text)', 1),
            $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, label text COLLATE BINARY, old_value text COLLATE RTRIM, new_value text COLLATE NOCASE)', 2),
            $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
            $record('trigger', 'main_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER main_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal', old.option_value, new.option_value); SELECT new.option_name COLLATE NOCASE, new.option_value COLLATE RTRIM; END", 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text COLLATE WP_LOCALE, option_value text COLLATE NOCASE)', 5),
            $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text COLLATE BINARY, old_value text, new_value text COLLATE WP_LOCALE)', 6),
            $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, temp_name, option_value FROM temp.wp_options', 7),
            $record('trigger', 'temp_autoloaded_update', 'autoloaded_options', 0, "CREATE TEMP TRIGGER temp_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'temp-rollback', old.option_value, new.option_value); SELECT new.temp_name COLLATE WP_LOCALE; END", 8),
            $record('trigger', 'temp_main_bridge', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_bridge AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'temp-shadow', new.option_value); INSERT INTO main.wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal-bridge', old.option_value, new.option_value); END", 9),
        ],
    );

    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text COLLATE WP_SLUG, option_value text COLLATE BINARY)', 10),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, old_value text COLLATE RTRIM, new_value text COLLATE WP_SLUG)', 11),
        $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW site.autoloaded_options AS SELECT blog_id, option_name, option_value FROM wp_options', 12),
        $record('trigger', 'site_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER site_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; INSERT INTO wp_option_audit(blog_id, label, old_value, new_value) VALUES(new.blog_id, 'site-wal', old.option_value, new.option_value); SELECT new.option_name COLLATE WP_SLUG, new.option_value COLLATE BINARY; END", 13),
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
                    2 => $page('main options next image'),
                    3 => $page('main audit next image'),
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
                    2 => $page('site options next image'),
                    3 => $page('site audit next image'),
                ],
                'database_page_count' => 3,
                'commit' => true,
            ]],
            'watch_pages' => [2, 3],
            'mode' => 'truncate',
        ],
    ];
};

$cache = static fn (): array => [
    'temp' => ['schema_cookie' => 3, 'registered_collations' => ['BINARY', 'NOCASE', 'RTRIM', 'WP_LOCALE']],
    'main' => ['schema_cookie' => 20, 'wal_schema_cookie' => 21, 'registered_collations' => ['BINARY', 'NOCASE', 'RTRIM', 'WP_LOCALE']],
    'site' => ['schema_cookie' => 7, 'wal_frames' => [['page' => 1, 'schema_cookie' => 8, 'commit' => true]], 'registered_collations' => ['BINARY', 'NOCASE', 'RTRIM', 'WP_SLUG']],
];

$triggers = ['main_autoloaded_update', 'temp_autoloaded_update', 'temp_main_bridge', 'site.site_autoloaded_update'];
$newRows = [
    'main_autoloaded_update' => $mainNew,
    'temp_autoloaded_update' => $tempNew,
    'temp_main_bridge' => $mainNew,
    'site.site_autoloaded_update' => $siteNew,
];
$oldRows = [
    'main_autoloaded_update' => $mainOld,
    'temp_autoloaded_update' => $tempOld,
    'temp_main_bridge' => $mainOld,
    'site.site_autoloaded_update' => $siteOld,
];

$plan = static fn (?array $nextCache = null, ?array $nextWal = null, array $names = null): array => SQLiteAttachTempMainWalCollationCachePlan::plan(
    $catalog(),
    $names ?? $triggers,
    $nextWal ?? $schemaWal(),
    $nextCache ?? $cache(),
    $newRows,
    $oldRows,
);

$value = static function (array $data, string $path): mixed {
    $cursor = $data;
    $parts = explode('.', $path);
    while ($parts !== []) {
        if (is_array($cursor)) {
            for ($length = count($parts); $length > 0; --$length) {
                $candidate = implode('.', array_slice($parts, 0, $length));
                if (array_key_exists($candidate, $cursor)) {
                    $cursor = $cursor[$candidate];
                    $parts = array_slice($parts, $length);
                    continue 2;
                }
            }
        }

        $part = array_shift($parts);
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$pathCases = [
    'status' => ['status', 'planned'],
    'source' => ['source', 'main'],
    'search order temp' => ['search_order.0', 'temp'],
    'search order main' => ['search_order.1', 'main'],
    'search order site' => ['search_order.2', 'site'],
    'database list site file' => ['database_list.2.file', '/srv/site.sqlite'],
    'trigger count' => ['trigger_count', 4],
    'changed schemas' => ['changed_schemas', ['main', 'site']],
    'current main cookie' => ['schema_cookies_current.main', 20],
    'next main cookie' => ['schema_cookies_next.main', 21],
    'current site cookie' => ['schema_cookies_current.site', 7],
    'next site cookie' => ['schema_cookies_next.site', 8],
    'temp cookie unchanged' => ['schema_states.temp.changed', false],
    'main cache changed' => ['schema_states.main.changed', true],
    'site cache changed' => ['schema_states.site.changed', true],
    'main registered custom locale' => ['schema_states.main.registered_collations.3', 'WP_LOCALE'],
    'site registered slug' => ['schema_states.site.registered_collations.3', 'WP_SLUG'],
    'expired triggers' => ['expired_triggers', ['main_autoloaded_update', 'site.site_autoloaded_update', 'temp_main_bridge']],
    'stable triggers' => ['stable_triggers', ['temp_autoloaded_update']],
    'wal route count' => ['route_counts.wal', 5],
    'read route count' => ['route_counts.read', 3],
    'temp rollback route count' => ['route_counts.temp-rollback', 3],
    'dependency slice' => ['dependencies.0', 'sqlite-attach-temp-main-wal-collation-cache-current-next'],
    'main trigger schema' => ['trigger_plans.main_autoloaded_update.trigger_schema', 'main'],
    'main target schema' => ['trigger_plans.main_autoloaded_update.target_schema', 'main'],
    'main operation count' => ['trigger_plans.main_autoloaded_update.operation_count', 3],
    'main read count' => ['trigger_plans.main_autoloaded_update.read_count', 1],
    'main writes by schema' => ['trigger_plans.main_autoloaded_update.writes_by_schema', ['main' => 2]],
    'main wal schemas' => ['trigger_plans.main_autoloaded_update.wal_schemas', ['main']],
    'main dependencies' => ['trigger_plans.main_autoloaded_update.schema_dependencies', ['main']],
    'main changed dependency' => ['trigger_plans.main_autoloaded_update.changed_schema_dependencies', ['main']],
    'main required collations' => ['trigger_plans.main_autoloaded_update.required_collations', ['BINARY', 'NOCASE', 'RTRIM']],
    'main missing collations' => ['trigger_plans.main_autoloaded_update.missing_collations', []],
    'main status expired' => ['trigger_plans.main_autoloaded_update.status', 'expired'],
    'main route one journal' => ['trigger_plans.main_autoloaded_update.operation_routes.1.journal', 'wal'],
    'main route one frame indexes' => ['trigger_plans.main_autoloaded_update.operation_routes.1.wal_frame_indexes', [1, 2]],
    'temp trigger schema' => ['trigger_plans.temp_autoloaded_update.trigger_schema', 'temp'],
    'temp target schema' => ['trigger_plans.temp_autoloaded_update.target_schema', 'temp'],
    'temp temp schema list' => ['trigger_plans.temp_autoloaded_update.temp_schemas', ['temp']],
    'temp dependencies' => ['trigger_plans.temp_autoloaded_update.schema_dependencies', ['temp']],
    'temp changed dependencies' => ['trigger_plans.temp_autoloaded_update.changed_schema_dependencies', []],
    'temp required collations' => ['trigger_plans.temp_autoloaded_update.required_collations', ['BINARY', 'NOCASE', 'WP_LOCALE']],
    'temp status stable' => ['trigger_plans.temp_autoloaded_update.status', 'stable'],
    'bridge trigger schema' => ['trigger_plans.temp_main_bridge.trigger_schema', 'temp'],
    'bridge target schema' => ['trigger_plans.temp_main_bridge.target_schema', 'main'],
    'bridge writes' => ['trigger_plans.temp_main_bridge.writes_by_schema', ['main' => 1, 'temp' => 1]],
    'bridge dependencies' => ['trigger_plans.temp_main_bridge.schema_dependencies', ['main', 'temp']],
    'bridge changed dependencies' => ['trigger_plans.temp_main_bridge.changed_schema_dependencies', ['main']],
    'bridge status expired' => ['trigger_plans.temp_main_bridge.status', 'expired'],
    'bridge rollback temp route' => ['trigger_plans.temp_main_bridge.operation_routes.0.journal', 'temp-rollback'],
    'bridge wal main route' => ['trigger_plans.temp_main_bridge.operation_routes.1.journal', 'wal'],
    'site trigger schema' => ['trigger_plans.site.site_autoloaded_update.trigger_schema', 'site'],
    'site target schema' => ['trigger_plans.site.site_autoloaded_update.target_schema', 'site'],
    'site wal schemas' => ['trigger_plans.site.site_autoloaded_update.wal_schemas', ['site']],
    'site changed dependency' => ['trigger_plans.site.site_autoloaded_update.changed_schema_dependencies', ['site']],
    'site required collations' => ['trigger_plans.site.site_autoloaded_update.required_collations', ['BINARY', 'RTRIM', 'WP_SLUG']],
    'site status expired' => ['trigger_plans.site.site_autoloaded_update.status', 'expired'],
    'required collation binary' => ['required_collations.0', 'BINARY'],
    'required collation nocase' => ['required_collations.1', 'NOCASE'],
    'required collation rtrim' => ['required_collations.2', 'RTRIM'],
    'required collation locale' => ['required_collations.3', 'WP_LOCALE'],
    'required collation slug' => ['required_collations.4', 'WP_SLUG'],
];

$tests = [];
foreach ($pathCases as $name => [$path, $expected]) {
    $tests['attach temp main wal collation cache current next55 ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$predicateCases = [
    'stable cache leaves every trigger stable' => static function () use ($cache, $plan): bool {
        $next = $cache();
        unset($next['main']['wal_schema_cookie']);
        $next['site']['wal_frames'][0]['commit'] = false;
        return $plan($next)['expired_triggers'] === [];
    },
    'temp schema cookie expires temp trigger' => static function () use ($cache, $plan): bool {
        $next = $cache();
        $next['temp']['wal_schema_cookie'] = 4;
        return $plan($next)['trigger_plans']['temp_autoloaded_update']['changed_schema_dependencies'] === ['temp'];
    },
    'uncommitted site page one frame is ignored' => static function () use ($cache, $plan): bool {
        $next = $cache();
        $next['site']['wal_frames'][0]['commit'] = false;
        return $plan($next)['schema_cookies_next']['site'] === 7;
    },
    'non page one site frame is ignored' => static function () use ($cache, $plan): bool {
        $next = $cache();
        $next['site']['wal_frames'][0]['page'] = 5;
        return $plan($next)['schema_cookies_next']['site'] === 7;
    },
    'explicit wal schema cookie wins over frame' => static function () use ($cache, $plan): bool {
        $next = $cache();
        $next['site']['wal_schema_cookie'] = 11;
        return $plan($next)['schema_cookies_next']['site'] === 11;
    },
    'missing locale collation expires temp trigger' => static function () use ($cache, $plan): bool {
        $next = $cache();
        $next['temp']['registered_collations'] = ['BINARY', 'NOCASE', 'RTRIM'];
        return $plan($next)['trigger_plans']['temp_autoloaded_update']['missing_collations'] === ['temp:WP_LOCALE'];
    },
    'missing slug collation expires site trigger' => static function () use ($cache, $plan): bool {
        $next = $cache();
        $next['site']['registered_collations'] = ['BINARY', 'NOCASE', 'RTRIM'];
        return in_array('site:WP_SLUG', $plan($next)['trigger_plans']['site.site_autoloaded_update']['missing_collations'], true);
    },
    'source schema may be attached' => static function () use ($catalog, $schemaWal, $cache, $triggers, $newRows, $oldRows): bool {
        $result = SQLiteAttachTempMainWalCollationCachePlan::plan($catalog(), $triggers, $schemaWal(), $cache(), $newRows, $oldRows, 'site');
        return $result['source'] === 'site';
    },
];

foreach ($predicateCases as $name => $callback) {
    $tests['attach temp main wal collation cache current next55 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->same(true, $callback());
    };
}

$errorCases = [
    'rejects empty trigger list' => static fn () => $plan(null, null, []),
    'rejects missing source schema' => static fn () => SQLiteAttachTempMainWalCollationCachePlan::plan($catalog(), $triggers, $schemaWal(), $cache(), $newRows, $oldRows, 'missing'),
    'rejects non integer schema cookie' => static function () use ($cache, $plan): array {
        $next = $cache();
        $next['main']['schema_cookie'] = '20';
        return $plan($next);
    },
    'rejects non integer wal schema cookie' => static function () use ($cache, $plan): array {
        $next = $cache();
        $next['main']['wal_schema_cookie'] = '21';
        return $plan($next);
    },
    'rejects non integer frame cookie' => static function () use ($cache, $plan): array {
        $next = $cache();
        $next['site']['wal_frames'][0]['schema_cookie'] = '8';
        return $plan($next);
    },
];

foreach ($errorCases as $name => $callback) {
    $tests['attach temp main wal collation cache current next55 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
