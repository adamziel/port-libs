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
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x59100000 + $saltSeed, 0x59200000 + $saltSeed);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};
$emptyWal = static fn (int $saltSeed): SQLiteWal => SQLiteWal::parse($walHeader($saltSeed), null, true);
$database = static fn (string $label): string => $page($label . ' page one') . $page($label . ' page two') . $page($label . ' page three');

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TABLE main.sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 1),
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(blog_id integer, option_id integer primary key, option_name text, option_value text)', 2),
        $record('trigger', 'temp_alias_schema_flush', 'wp_options', 0, "CREATE TRIGGER temp_alias_schema_flush AFTER UPDATE ON wp_options BEGIN INSERT INTO sqlite_temp_schema(type, name, tbl_name, rootpage, sql) VALUES('table', 'wp_import_shadow', 'wp_import_shadow', 11, new.option_value); INSERT INTO main.sqlite_master(type, name, tbl_name, rootpage, sql) VALUES('index', 'main_option_autoload', 'wp_options', 5, new.option_value); INSERT INTO site.sqlite_master(type, name, tbl_name, rootpage, sql) VALUES('index', 'site_option_autoload', 'wp_options', 6, new.option_value); INSERT INTO site.wp_options(blog_id, option_name, option_value) VALUES(new.blog_id, new.option_name, new.option_value); END", 3),
    ], [
        $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TEMP TABLE sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 4),
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text)', 5),
        $record('trigger', 'temp_bare_schema_flush', 'wp_options', 0, "CREATE TEMP TRIGGER temp_bare_schema_flush AFTER UPDATE ON main.wp_options BEGIN INSERT INTO sqlite_schema(type, name, tbl_name, rootpage, sql) VALUES('table', 'wp_temp_stage', 'wp_temp_stage', 12, new.option_value); INSERT INTO site.sqlite_schema(type, name, tbl_name, rootpage, sql) VALUES('index', 'site_temp_stage_idx', 'wp_options', 7, new.option_value); END", 6),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TABLE site.sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 7),
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 8),
    ]);

    return $catalog;
};

$schemaCache = static fn (): array => [
    'main' => ['schema_cookie' => 40, 'tables' => ['sqlite_schema', 'wp_options'], 'file' => '/srv/wp/current.sqlite', 'cache' => 'shared'],
    'temp' => ['schema_cookie' => 8, 'tables' => ['sqlite_schema', 'wp_options'], 'file' => ''],
    'site' => ['schema_cookie' => 17, 'tables' => ['sqlite_schema', 'wp_options'], 'file' => '/srv/wp/site.sqlite', 'cache' => 'shared'],
];

$schemaWal = static function () use ($emptyWal, $database, $page): array {
    return [
        'main' => [
            'wal' => $emptyWal(1),
            'database_bytes' => $database('main before'),
            'database_path' => 'wp-content/database/.ht.sqlite',
            'transactions' => [[
                'pages' => [1 => $page('main schema alias next'), 2 => $page('main options next')],
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
                'pages' => [1 => $page('site schema alias next'), 2 => $page('site options next')],
                'database_page_count' => 3,
                'commit' => true,
            ]],
            'watch_pages' => [1, 2],
            'mode' => 'truncate',
        ],
    ];
};

$new = ['blog_id' => 7, 'option_id' => 42, 'option_name' => 'active_plugins', 'option_value' => 'CREATE INDEX option_autoload ON wp_options(autoload)'];
$old = ['blog_id' => 7, 'option_id' => 42, 'option_name' => 'active_plugins', 'option_value' => 'old'];
$plan = static fn (string $trigger, ?array $wal = null, ?array $cache = null): array => SQLiteAttachTempWalSchemaTriggerPlan::plan(
    $catalog(),
    $trigger,
    $wal ?? $schemaWal(),
    $cache ?? $schemaCache(),
    ['wp_options', 'main.wp_options', 'temp.wp_options', 'site.wp_options'],
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

$cases = [
    'main trigger status' => ['temp_alias_schema_flush', 'status', 'planned'],
    'main trigger schema' => ['temp_alias_schema_flush', 'trigger_schema', 'main'],
    'main target schema' => ['temp_alias_schema_flush', 'target_schema', 'main'],
    'main operation count includes schema aliases' => ['temp_alias_schema_flush', 'trigger_plan.operation_count', 4],
    'main write count includes temp alias and qualified masters' => ['temp_alias_schema_flush', 'schema_write_count', 3],
    'main write schemas order' => ['temp_alias_schema_flush', 'schema_write_schemas', ['temp', 'main', 'site']],
    'main temp alias write schema' => ['temp_alias_schema_flush', 'schema_writes.0.schema', 'temp'],
    'main temp alias canonical table' => ['temp_alias_schema_flush', 'schema_writes.0.table', 'sqlite_schema'],
    'main temp alias journal' => ['temp_alias_schema_flush', 'schema_writes.0.journal', 'temp-rollback'],
    'main temp alias operation index' => ['temp_alias_schema_flush', 'schema_writes.0.operation_index', 0],
    'main master alias schema' => ['temp_alias_schema_flush', 'schema_writes.1.schema', 'main'],
    'main master alias canonical table' => ['temp_alias_schema_flush', 'schema_writes.1.table', 'sqlite_schema'],
    'main master alias journal' => ['temp_alias_schema_flush', 'schema_writes.1.journal', 'wal'],
    'site master alias schema' => ['temp_alias_schema_flush', 'schema_writes.2.schema', 'site'],
    'site master alias canonical table' => ['temp_alias_schema_flush', 'schema_writes.2.table', 'sqlite_schema'],
    'site master alias journal' => ['temp_alias_schema_flush', 'schema_writes.2.journal', 'wal'],
    'main wal schemas include main and site' => ['temp_alias_schema_flush', 'wal_schemas', ['main', 'site']],
    'main temp schemas include temp' => ['temp_alias_schema_flush', 'temp_schemas', ['temp']],
    'main rollback schemas empty' => ['temp_alias_schema_flush', 'rollback_schemas', []],
    'main current temp cookie' => ['temp_alias_schema_flush', 'schema_cache.schema_cookies_current.temp', 8],
    'main next temp cookie bumped' => ['temp_alias_schema_flush', 'schema_cache.schema_cookies_next.temp', 9],
    'main next main cookie bumped' => ['temp_alias_schema_flush', 'schema_cache.schema_cookies_next.main', 41],
    'main next site cookie bumped' => ['temp_alias_schema_flush', 'schema_cache.schema_cookies_next.site', 18],
    'main changed schemas include all alias writes' => ['temp_alias_schema_flush', 'reprepare_schemas', ['temp', 'main', 'site']],
    'main requires reprepare' => ['temp_alias_schema_flush', 'requires_reprepare', true],
    'main unqualified prepared table reprepares from temp alias' => ['temp_alias_schema_flush', 'schema_cache.prepared_tables.wp_options.requires_reprepare', true],
    'main qualified main table reprepares' => ['temp_alias_schema_flush', 'schema_cache.prepared_tables.main.wp_options.requires_reprepare', true],
    'main qualified temp table reprepares' => ['temp_alias_schema_flush', 'schema_cache.prepared_tables.temp.wp_options.requires_reprepare', true],
    'main qualified site table reprepares' => ['temp_alias_schema_flush', 'schema_cache.prepared_tables.site.wp_options.requires_reprepare', true],
    'main temp route boundary' => ['temp_alias_schema_flush', 'trigger_plan.current_next_boundaries.temp.journal', 'temp-rollback'],
    'main site route boundary' => ['temp_alias_schema_flush', 'trigger_plan.current_next_boundaries.site.journal', 'wal'],
    'main site frame indexes' => ['temp_alias_schema_flush', 'trigger_plan.next_reader_frame_indexes.site', [1, 2]],
    'temp trigger schema' => ['temp_bare_schema_flush', 'trigger_schema', 'temp'],
    'temp trigger target schema' => ['temp_bare_schema_flush', 'target_schema', 'main'],
    'temp bare sqlite schema writes temp' => ['temp_bare_schema_flush', 'schema_writes.0.schema', 'temp'],
    'temp bare sqlite schema canonical table' => ['temp_bare_schema_flush', 'schema_writes.0.table', 'sqlite_schema'],
    'temp qualified site schema writes site' => ['temp_bare_schema_flush', 'schema_writes.1.schema', 'site'],
    'temp write schemas' => ['temp_bare_schema_flush', 'schema_write_schemas', ['temp', 'site']],
    'temp wal schemas only site' => ['temp_bare_schema_flush', 'wal_schemas', ['site']],
    'temp temp schemas list' => ['temp_bare_schema_flush', 'temp_schemas', ['temp']],
    'temp main cookie unchanged' => ['temp_bare_schema_flush', 'schema_cache.schema_cookies_next.main', 40],
    'temp temp cookie bumped' => ['temp_bare_schema_flush', 'schema_cache.schema_cookies_next.temp', 9],
    'temp site cookie bumped' => ['temp_bare_schema_flush', 'schema_cache.schema_cookies_next.site', 18],
    'temp changed schemas' => ['temp_bare_schema_flush', 'reprepare_schemas', ['temp', 'site']],
    'temp unqualified prepared table reprepares' => ['temp_bare_schema_flush', 'schema_cache.prepared_tables.wp_options.requires_reprepare', true],
    'temp qualified main table unchanged' => ['temp_bare_schema_flush', 'schema_cache.prepared_tables.main.wp_options.requires_reprepare', false],
    'temp qualified site table reprepares' => ['temp_bare_schema_flush', 'schema_cache.prepared_tables.site.wp_options.requires_reprepare', true],
    'temp source remains main by default' => ['temp_bare_schema_flush', 'schema_cache.source', 'main'],
];

foreach ($cases as $name => [$trigger, $path, $expected]) {
    $tests['attach temp schema wal alias current next59 ' . $name] = static function (TestRunner $t) use ($plan, $value, $trigger, $path, $expected): void {
        $t->same($expected, $value($plan($trigger), $path));
    };
}

$predicates = [
    'temp alias source preserved' => static fn (): bool => str_contains($plan('temp_alias_schema_flush')['schema_writes'][0]['source'], 'sqlite_temp_schema'),
    'main sqlite master source preserved' => static fn (): bool => str_contains($plan('temp_alias_schema_flush')['schema_writes'][1]['source'], 'main.sqlite_master'),
    'site sqlite master source preserved' => static fn (): bool => str_contains($plan('temp_alias_schema_flush')['schema_writes'][2]['source'], 'site.sqlite_master'),
    'dependencies include alias slice' => static fn (): bool => in_array('sqlite-trigger-schema-cookie-reprepare', $plan('temp_alias_schema_flush')['dependencies'], true),
    'dependencies include temp rollback routing' => static fn (): bool => in_array('sqlite-temp-trigger-rollback-journal-routing', $plan('temp_alias_schema_flush')['dependencies'], true),
    'dependencies include wal append routing' => static fn (): bool => in_array('sqlite-wal-append-transaction', $plan('temp_alias_schema_flush')['dependencies'], true),
    'cache dependencies include schema cookie source' => static fn (): bool => in_array('sqlite-wal-page-one-schema-cookie', $plan('temp_alias_schema_flush')['schema_cache']['dependencies'], true),
    'existing wal cookie is bumped for temp alias' => static function () use ($schemaCache, $plan): bool {
        $cache = $schemaCache();
        $cache['temp']['wal_schema_cookie'] = 70;

        return $plan('temp_alias_schema_flush', null, $cache)['schema_cache']['schema_cookies_next']['temp'] === 71;
    },
    'missing temp cache rejects alias write' => static function () use ($schemaCache, $plan): bool {
        $cache = $schemaCache();
        unset($cache['temp']);
        try {
            $plan('temp_alias_schema_flush', null, $cache);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
    'missing site wal falls back for site schema alias write' => static function () use ($schemaWal, $plan): bool {
        $wal = $schemaWal();
        unset($wal['site']);

        return $plan('temp_alias_schema_flush', $wal)['rollback_schemas'] === ['site'];
    },
    'source schema can be attached while aliases stay routed' => static function () use ($catalog, $schemaWal, $schemaCache, $new, $old): bool {
        $result = SQLiteAttachTempWalSchemaTriggerPlan::plan($catalog(), 'temp_alias_schema_flush', $schemaWal(), $schemaCache(), ['site.wp_options'], $new, $old, 'site');

        return $result['schema_cache']['source'] === 'site' && $result['schema_write_schemas'] === ['temp', 'main', 'site'];
    },
    'quoted temp alias resolves through trigger operations' => static function () use ($record, $schemaWal, $schemaCache, $new, $old): bool {
        $catalog = new SQLiteAttachedSchemaCatalog([
            $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TABLE sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 1),
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text)', 2),
            $record('trigger', 'quoted_temp_alias', 'wp_options', 0, "CREATE TRIGGER quoted_temp_alias AFTER UPDATE ON wp_options BEGIN INSERT INTO \"sqlite_temp_master\"(type, name, tbl_name, rootpage, sql) VALUES('table', 'quoted_stage', 'quoted_stage', 13, new.option_value); END", 3),
        ], [
            $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TEMP TABLE sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 4),
        ]);

        $result = SQLiteAttachTempWalSchemaTriggerPlan::plan($catalog, 'quoted_temp_alias', $schemaWal(), $schemaCache(), ['wp_options'], $new, $old);

        return $result['schema_write_schemas'] === ['temp'] && $result['schema_writes'][0]['table'] === 'sqlite_schema';
    },
];

foreach ($predicates as $name => $predicate) {
    $tests['attach temp schema wal alias current next59 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

return $tests;
