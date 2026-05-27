<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$walHeader = static function (int $saltSeed) use ($pageSize): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x52100000 + $saltSeed, 0x52200000 + $saltSeed);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};
$emptyWal = static fn (int $saltSeed): SQLiteWal => SQLiteWal::parse($walHeader($saltSeed), null, true);
$database = static fn (string $label): string => $page($label . ' page one') . $page($label . ' page two') . $page($label . ' page three');

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TABLE main.sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 1),
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text)', 2),
        $record('trigger', 'main_schema_insert', 'wp_options', 0, "CREATE TRIGGER main_schema_insert AFTER UPDATE ON wp_options BEGIN INSERT INTO sqlite_schema(type, name, tbl_name, rootpage, sql) VALUES('index', 'wp_options_autoload', 'wp_options', 5, new.option_value); INSERT INTO main.wp_options(option_id, option_name, option_value) VALUES(new.option_id, new.option_name, new.option_value); END", 3),
    ], [
        $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TEMP TABLE sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 4),
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text)', 5),
        $record('trigger', 'temp_schema_insert', 'wp_options', 0, "CREATE TEMP TRIGGER temp_schema_insert AFTER UPDATE ON main.wp_options BEGIN INSERT INTO sqlite_schema(type, name, tbl_name, rootpage, sql) VALUES('table', 'scratch_import', 'scratch_import', 11, new.option_value); INSERT INTO main.wp_options(option_id, option_name, option_value) VALUES(new.option_id, new.option_name, new.option_value); END", 6),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TABLE site.sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 7),
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 8),
        $record('trigger', 'site_schema_insert', 'wp_options', 0, "CREATE TRIGGER site_schema_insert AFTER UPDATE ON wp_options BEGIN INSERT INTO site.sqlite_schema(type, name, tbl_name, rootpage, sql) VALUES('index', 'site_home_idx', 'wp_options', 9, new.option_value); END", 9),
    ]);

    return $catalog;
};

$schemaCache = static fn (): array => [
    'main' => ['schema_cookie' => 20, 'tables' => ['sqlite_schema', 'wp_options'], 'file' => '/srv/wp/current.sqlite', 'cache' => 'shared'],
    'temp' => ['schema_cookie' => 3, 'tables' => ['sqlite_schema', 'wp_options'], 'file' => ''],
    'site' => ['schema_cookie' => 7, 'tables' => ['sqlite_schema', 'wp_options'], 'file' => '/srv/wp/site.sqlite', 'cache' => 'shared'],
];

$schemaWal = static function () use ($emptyWal, $database, $page): array {
    return [
        'main' => [
            'wal' => $emptyWal(1),
            'database_bytes' => $database('main before'),
            'database_path' => 'wp-content/database/.ht.sqlite',
            'transactions' => [[
                'pages' => [1 => $page('main schema page next'), 2 => $page('main options next')],
                'database_page_count' => 3,
                'commit' => true,
            ]],
            'watch_pages' => [1, 2],
            'mode' => 'restart',
        ],
        'site' => [
            'wal' => $emptyWal(2),
            'database_bytes' => $database('site before'),
            'database_path' => 'wp-content/database/site.sqlite',
            'transactions' => [[
                'pages' => [1 => $page('site schema page next'), 2 => $page('site options next')],
                'database_page_count' => 3,
                'commit' => true,
            ]],
            'watch_pages' => [1, 2],
            'mode' => 'truncate',
        ],
    ];
};

$new = ['option_id' => 42, 'option_name' => 'active_plugins', 'option_value' => 'CREATE INDEX wp_options_autoload ON wp_options(autoload)'];
$old = ['option_id' => 42, 'option_name' => 'active_plugins', 'option_value' => 'old'];
$plan = static fn (string $trigger, ?array $wal = null, ?array $cache = null, array $tables = ['wp_options', 'main.wp_options', 'site.wp_options']): array => SQLiteAttachTempWalSchemaTriggerPlan::plan(
    $catalog(),
    $trigger,
    $wal ?? $schemaWal(),
    $cache ?? $schemaCache(),
    $tables,
    $new,
    $old,
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
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        return null;
    }

    return $cursor;
};

$pathCases = [
    'main status' => ['main_schema_insert', 'status', 'planned'],
    'main trigger schema' => ['main_schema_insert', 'trigger_schema', 'main'],
    'main target schema' => ['main_schema_insert', 'target_schema', 'main'],
    'main schema write count' => ['main_schema_insert', 'schema_write_count', 1],
    'main schema write schemas' => ['main_schema_insert', 'schema_write_schemas', ['main']],
    'main schema write table' => ['main_schema_insert', 'schema_writes.0.table', 'sqlite_schema'],
    'main schema write kind' => ['main_schema_insert', 'schema_writes.0.kind', 'insert'],
    'main schema write journal' => ['main_schema_insert', 'schema_writes.0.journal', 'wal'],
    'main schema write operation index' => ['main_schema_insert', 'schema_writes.0.operation_index', 0],
    'main wal schema list' => ['main_schema_insert', 'wal_schemas', ['main']],
    'main temp schema list empty' => ['main_schema_insert', 'temp_schemas', []],
    'main rollback schema list empty' => ['main_schema_insert', 'rollback_schemas', []],
    'main schema cookie current' => ['main_schema_insert', 'schema_cache.schema_cookies_current.main', 20],
    'main schema cookie next' => ['main_schema_insert', 'schema_cache.schema_cookies_next.main', 21],
    'main temp cookie unchanged' => ['main_schema_insert', 'schema_cache.schema_cookies_next.temp', 3],
    'main site cookie unchanged' => ['main_schema_insert', 'schema_cache.schema_cookies_next.site', 7],
    'main changed schemas' => ['main_schema_insert', 'reprepare_schemas', ['main']],
    'main requires reprepare' => ['main_schema_insert', 'requires_reprepare', true],
    'main prepared qualified reprepare' => ['main_schema_insert', 'schema_cache.prepared_tables.main.wp_options.requires_reprepare', true],
    'main prepared temp winner not reprepare' => ['main_schema_insert', 'schema_cache.prepared_tables.wp_options.requires_reprepare', false],
    'main shadowed schemas still tracked' => ['main_schema_insert', 'schema_cache.prepared_tables.wp_options.shadowed_schemas', ['main', 'site']],
    'main dependencies include slice' => ['main_schema_insert', 'dependencies.0', 'sqlite-attach-temp-wal-schema-trigger-current-next'],
    'main trigger plan operation count' => ['main_schema_insert', 'trigger_plan.operation_count', 2],
    'main trigger plan writes by schema' => ['main_schema_insert', 'trigger_plan.writes_by_schema', ['main' => 2]],
    'main trigger plan next frame indexes' => ['main_schema_insert', 'trigger_plan.next_reader_frame_indexes.main', [1, 2]],
    'temp trigger schema' => ['temp_schema_insert', 'trigger_schema', 'temp'],
    'temp target schema' => ['temp_schema_insert', 'target_schema', 'main'],
    'temp schema write count' => ['temp_schema_insert', 'schema_write_count', 1],
    'temp schema write schemas' => ['temp_schema_insert', 'schema_write_schemas', ['temp']],
    'temp schema write journal' => ['temp_schema_insert', 'schema_writes.0.journal', 'temp-rollback'],
    'temp wal schemas still main from qualified write' => ['temp_schema_insert', 'wal_schemas', ['main']],
    'temp temp schemas list' => ['temp_schema_insert', 'temp_schemas', ['temp']],
    'temp schema cookie next' => ['temp_schema_insert', 'schema_cache.schema_cookies_next.temp', 4],
    'temp changed schemas' => ['temp_schema_insert', 'reprepare_schemas', ['temp']],
    'temp unqualified prepared reprepare' => ['temp_schema_insert', 'schema_cache.prepared_tables.wp_options.requires_reprepare', true],
    'temp main qualified unchanged' => ['temp_schema_insert', 'schema_cache.prepared_tables.main.wp_options.requires_reprepare', false],
    'site trigger schema' => ['site.site_schema_insert', 'trigger_schema', 'site'],
    'site target schema' => ['site.site_schema_insert', 'target_schema', 'site'],
    'site schema write count' => ['site.site_schema_insert', 'schema_write_count', 1],
    'site schema write schemas' => ['site.site_schema_insert', 'schema_write_schemas', ['site']],
    'site wal schema list' => ['site.site_schema_insert', 'wal_schemas', ['site']],
    'site mode truncate' => ['site.site_schema_insert', 'trigger_plan.wal_plans.site.mode', 'truncate'],
    'site schema cookie next' => ['site.site_schema_insert', 'schema_cache.schema_cookies_next.site', 8],
    'site changed schemas' => ['site.site_schema_insert', 'reprepare_schemas', ['site']],
    'site qualified prepared reprepare' => ['site.site_schema_insert', 'schema_cache.prepared_tables.site.wp_options.requires_reprepare', true],
    'site main qualified unchanged' => ['site.site_schema_insert', 'schema_cache.prepared_tables.main.wp_options.requires_reprepare', false],
];

foreach ($pathCases as $name => [$trigger, $path, $expected]) {
    $tests['attach temp wal schema trigger current next52 ' . $name] = static function (TestRunner $t) use ($plan, $value, $trigger, $path, $expected): void {
        $t->same($expected, $value($plan($trigger), $path));
    };
}

$predicateCases = [
    'main schema write source mentions inserted index' => static fn (): bool => str_contains($plan('main_schema_insert')['schema_writes'][0]['source'], 'wp_options_autoload'),
    'main schema cache records wal dependency' => static fn (): bool => $plan('main_schema_insert')['schema_cache']['wal_schema_cookie_sources'] === ['main'],
    'temp schema cache records wal dependency' => static fn (): bool => $plan('temp_schema_insert')['schema_cache']['wal_schema_cookie_sources'] === ['temp'],
    'site schema cache records wal dependency' => static fn (): bool => $plan('site.site_schema_insert')['schema_cache']['wal_schema_cookie_sources'] === ['site'],
    'prepared temp table remains found after main schema write' => static fn (): bool => $plan('main_schema_insert')['schema_cache']['prepared_tables']['wp_options']['found'] === true,
    'prepared site table remains found after site schema write' => static fn (): bool => $plan('site.site_schema_insert')['schema_cache']['prepared_tables']['site.wp_options']['found'] === true,
    'dependency preserves wal append dependency' => static fn (): bool => in_array('sqlite-wal-append-transaction', $plan('main_schema_insert')['dependencies'], true),
    'dependency preserves schema cache dependency' => static fn (): bool => in_array('sqlite-wal-page-one-schema-cookie', $plan('main_schema_insert')['schema_cache']['dependencies'], true),
    'dependency includes schema reprepare dependency' => static fn (): bool => in_array('sqlite-trigger-schema-cookie-reprepare', $plan('main_schema_insert')['dependencies'], true),
    'temp trigger keeps temp rollback dependency' => static fn (): bool => in_array('sqlite-temp-trigger-rollback-journal-routing', $plan('temp_schema_insert')['dependencies'], true),
    'main schema write bumps existing wal schema cookie' => static function () use ($schemaCache, $plan): bool {
        $cache = $schemaCache();
        $cache['main']['wal_schema_cookie'] = 30;
        return $plan('main_schema_insert', null, $cache)['schema_cache']['schema_cookies_next']['main'] === 31;
    },
    'missing schema cache target is rejected' => static function () use ($schemaCache, $plan): bool {
        $cache = $schemaCache();
        unset($cache['site']);
        try {
            $plan('site.site_schema_insert', null, $cache);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
    'missing wal target falls back to rollback schema routing' => static fn (): bool => $plan('site.site_schema_insert', ['main' => []])['rollback_schemas'] === ['site'],
    'source schema can be attached site' => static function () use ($catalog, $schemaWal, $schemaCache, $new, $old): bool {
        $result = SQLiteAttachTempWalSchemaTriggerPlan::plan($catalog(), 'site.site_schema_insert', $schemaWal(), $schemaCache(), ['site.wp_options'], $new, $old, 'site');
        return $result['schema_cache']['source'] === 'site';
    },
];

foreach ($predicateCases as $name => $predicate) {
    $tests['attach temp wal schema trigger current next52 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

return $tests;
