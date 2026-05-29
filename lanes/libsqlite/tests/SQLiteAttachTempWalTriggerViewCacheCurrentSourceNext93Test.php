<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record93 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog93 = static function () use ($record93): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record93('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record93('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, new_value text)', 2),
        $record93('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
        $record93('trigger', 'autoloaded_options_io_update', 'autoloaded_options', 0, "CREATE TRIGGER main.autoloaded_options_io_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); END", 4),
    ], [
        $record93('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 5),
        $record93('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, temp_name, option_value FROM temp.wp_options', 6),
        $record93('trigger', 'temp_autoloaded_options_io_update', 'autoloaded_options', 0, "CREATE TEMP TRIGGER temp_autoloaded_options_io_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END", 7),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record93('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 8),
        $record93('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, new_value text)', 9),
        $record93('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW site.autoloaded_options AS SELECT blog_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 10),
        $record93('trigger', 'site_autoloaded_options_io_update', 'autoloaded_options', 0, "CREATE TRIGGER site.site_autoloaded_options_io_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; INSERT INTO wp_option_audit(blog_id, option_name, new_value) VALUES(new.blog_id, new.option_name, new.option_value); END", 11),
    ]);

    return $catalog;
};

$siteNext93 = static fn (int $root): array => [
    $record93('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text, migrated integer)', $root + 1),
    $record93('table', 'wp_option_audit', 'wp_option_audit', $root + 1, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, new_value text, source text)', $root + 2),
    $record93('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW site.autoloaded_options AS SELECT blog_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", $root + 3),
    $record93('trigger', 'site_autoloaded_options_io_update', 'autoloaded_options', 0, "CREATE TRIGGER site.site_autoloaded_options_io_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; INSERT INTO wp_option_audit(blog_id, option_name, new_value, source) VALUES(new.blog_id, new.option_name, new.option_value, 'site-next'); END", $root + 4),
];

$tempNext93 = static fn (int $root): array => [
    $record93('table', 'wp_options', 'wp_options', $root, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text, touched integer)', $root + 1),
    $record93('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE TEMP VIEW autoloaded_options AS SELECT option_id, temp_name, option_value FROM temp.wp_options', $root + 2),
    $record93('trigger', 'temp_autoloaded_options_io_update', 'autoloaded_options', 0, "CREATE TEMP TRIGGER temp_autoloaded_options_io_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END", $root + 3),
];

$triggerPlan93 = static fn (): array => SQLiteAttachTempWalSchemaTriggerPlan::walTriggerCookieCachePlan(
    $catalog93(),
    [
        ['name' => '[site].[site_autoloaded_options_io_update]', 'source' => '[site]', 'active' => true],
        ['name' => '[temp_autoloaded_options_io_update]', 'source' => '[temp]'],
        ['name' => '[main].[autoloaded_options_io_update]', 'source' => '[main]'],
    ],
    ['site' => $siteNext93(50)],
    [
        '[site]' => ['schema_cookie' => 7, 'wal_schema_cookie' => 8],
        '[temp]' => ['schema_cookie' => 3],
        '[main]' => ['schema_cookie' => 5],
    ],
);

$viewPlan93 = static fn (): array => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan(
    $catalog93(),
    [
        ['name' => '[site].[autoloaded_options]', 'source' => '[site]', 'active' => true],
        ['name' => '[autoloaded_options]', 'source' => '[temp]'],
        ['name' => '[main].[autoloaded_options]', 'source' => '[main]'],
    ],
    ['temp' => $tempNext93(70)],
    [
        '[site]' => ['schema_cookie' => 7],
        '[temp]' => ['schema_cookie' => 3, 'wal_schema_cookie' => 4],
        '[main]' => ['schema_cookie' => 5],
    ],
);

$value93 = static function (array $data, string $path): mixed {
    $cursor = $data;
    $parts = explode('.', $path);
    while ($parts !== []) {
        if (!is_array($cursor)) {
            return null;
        }

        for ($length = count($parts); $length >= 1; --$length) {
            $candidate = implode('.', array_slice($parts, 0, $length));
            if (array_key_exists($candidate, $cursor)) {
                $cursor = $cursor[$candidate];
                $parts = array_slice($parts, $length);
                continue 2;
            }
        }

        return null;
    }

    return $cursor;
};

$cases93 = [
    'trigger status expired' => [$triggerPlan93, 'status', 'trigger_cache_expired'],
    'trigger operation stays accepted engine' => [$triggerPlan93, 'operation', 'attach-wal-temp-trigger-cookie-cache'],
    'trigger primary source unquoted' => [$triggerPlan93, 'source_schema', 'site'],
    'trigger count' => [$triggerPlan93, 'trigger_count', 3],
    'trigger active count' => [$triggerPlan93, 'active_trigger_count', 1],
    'trigger site source unquoted' => [$triggerPlan93, 'source_schemas.[site].[site_autoloaded_options_io_update]', 'site'],
    'trigger temp source unquoted' => [$triggerPlan93, 'source_schemas.[temp_autoloaded_options_io_update]', 'temp'],
    'trigger main source unquoted' => [$triggerPlan93, 'source_schemas.[main].[autoloaded_options_io_update]', 'main'],
    'trigger changed schemas sorted' => [$triggerPlan93, 'changed_schemas', ['site']],
    'trigger schema cookie key site' => [$triggerPlan93, 'schema_cookies_current.site', 7],
    'trigger schema cookie next site' => [$triggerPlan93, 'schema_cookies_next.site', 8],
    'trigger schema cookie key temp' => [$triggerPlan93, 'schema_cookies_current.temp', 3],
    'trigger schema cookie key main' => [$triggerPlan93, 'schema_cookies_current.main', 5],
    'trigger wal source unquoted' => [$triggerPlan93, 'wal_schema_cookie_sources', ['site']],
    'trigger reprepare site active' => [$triggerPlan93, 'reprepare_triggers', ['[site].[site_autoloaded_options_io_update]']],
    'trigger stable temp main' => [$triggerPlan93, 'stable_triggers', ['[temp_autoloaded_options_io_update]', '[main].[autoloaded_options_io_update]']],
    'trigger active current snapshot site' => [$triggerPlan93, 'active_current_snapshot_triggers', ['[site].[site_autoloaded_options_io_update]']],
    'trigger reset site' => [$triggerPlan93, 'reset_schema_triggers', ['[site].[site_autoloaded_options_io_update]']],
    'trigger no next-step schema triggers' => [$triggerPlan93, 'next_step_schema_triggers', []],
    'trigger cookie expired site' => [$triggerPlan93, 'cookie_expired_triggers', ['[site].[site_autoloaded_options_io_update]']],
    'trigger record expired site' => [$triggerPlan93, 'record_expired_triggers', ['[site].[site_autoloaded_options_io_update]']],
    'trigger site dependency schema' => [$triggerPlan93, 'triggers.[site].[site_autoloaded_options_io_update].dependency_schemas', ['site']],
    'trigger site cookie changed schema' => [$triggerPlan93, 'triggers.[site].[site_autoloaded_options_io_update].cookie_changed_schemas', ['site']],
    'trigger site current step ok' => [$triggerPlan93, 'triggers.[site].[site_autoloaded_options_io_update].current_step_result', 'SQLITE_OK'],
    'trigger site reset action' => [$triggerPlan93, 'triggers.[site].[site_autoloaded_options_io_update].next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'trigger site current source kept' => [$triggerPlan93, 'triggers.[site].[site_autoloaded_options_io_update].current_source_kept_until_reset', true],
    'trigger site target changed false' => [$triggerPlan93, 'triggers.[site].[site_autoloaded_options_io_update].target_changed', false],
    'trigger site body changed false' => [$triggerPlan93, 'triggers.[site].[site_autoloaded_options_io_update].body_dependencies_changed', false],
    'trigger temp dependency schemas' => [$triggerPlan93, 'triggers.[temp_autoloaded_options_io_update].dependency_schemas', ['temp']],
    'trigger temp reusable action' => [$triggerPlan93, 'triggers.[temp_autoloaded_options_io_update].next_step_action', 'reuse_prepared_trigger_current_and_next_source'],

    'view status expired' => [$viewPlan93, 'status', 'view_cache_expired'],
    'view operation stays accepted engine' => [$viewPlan93, 'operation', 'attach-wal-temp-schema-view-cache-reprepare'],
    'view primary source unquoted' => [$viewPlan93, 'source_schema', 'site'],
    'view count' => [$viewPlan93, 'view_count', 3],
    'view active count' => [$viewPlan93, 'active_view_count', 1],
    'view site source unquoted' => [$viewPlan93, 'source_schemas.[site].[autoloaded_options]', 'site'],
    'view temp source unquoted' => [$viewPlan93, 'source_schemas.[autoloaded_options]', 'temp'],
    'view main source unquoted' => [$viewPlan93, 'source_schemas.[main].[autoloaded_options]', 'main'],
    'view changed schemas sorted' => [$viewPlan93, 'changed_schemas', ['temp']],
    'view temp current cookie' => [$viewPlan93, 'schema_cookies_current.temp', 3],
    'view temp next cookie' => [$viewPlan93, 'schema_cookies_next.temp', 4],
    'view wal source unquoted' => [$viewPlan93, 'wal_schema_cookie_sources', ['temp']],
    'view reprepare temp only' => [$viewPlan93, 'reprepare_views', ['[autoloaded_options]']],
    'view stable site main' => [$viewPlan93, 'stable_views', ['[site].[autoloaded_options]', '[main].[autoloaded_options]']],
    'view active current empty because site stable' => [$viewPlan93, 'active_current_snapshot_views', []],
    'view temp next step schema' => [$viewPlan93, 'next_step_schema_views', ['[autoloaded_options]']],
    'view temp current result schema' => [$viewPlan93, 'views.[autoloaded_options].current_step_result', 'SQLITE_SCHEMA'],
    'view temp next action' => [$viewPlan93, 'views.[autoloaded_options].next_step_action', 'sqlite_schema_on_next_step'],
    'view temp source schema' => [$viewPlan93, 'views.[autoloaded_options].source_schema', 'temp'],
    'view temp dependencies changed' => [$viewPlan93, 'views.[autoloaded_options].dependencies_changed', true],
    'view site source schema' => [$viewPlan93, 'views.[site].[autoloaded_options].source_schema', 'site'],
    'view site reusable action' => [$viewPlan93, 'views.[site].[autoloaded_options].next_step_action', 'reuse_prepared_view'],
    'view main source schema' => [$viewPlan93, 'views.[main].[autoloaded_options].source_schema', 'main'],
    'view dependency marker' => [$viewPlan93, 'dependencies.0', 'sqlite-attach-wal-temp-schema-view-cache-reprepare'],
];

$tests = [];
foreach ($cases93 as $name => [$factory, $path, $expected]) {
    $tests['attach temp wal trigger view cache current source next93 ' . $name] = static function (TestRunner $t) use ($factory, $value93, $path, $expected): void {
        $t->same($expected, $value93($factory(), $path));
    };
}

$tests['attach temp wal trigger view cache current source next93 rejects empty bracket source'] = static function (TestRunner $t) use ($catalog93): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempWalSchemaTriggerPlan::walTriggerCookieCachePlan($catalog93(), [
        ['name' => '[main].[autoloaded_options_io_update]', 'source' => '[]'],
    ]));
};

$tests['attach temp wal trigger view cache current source next93 rejects empty bracket view source'] = static function (TestRunner $t) use ($catalog93): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempViewCachePlan::preparedViewCacheRepreparePlan($catalog93(), [
        ['name' => '[main].[autoloaded_options]', 'source' => '[]'],
    ]));
};

return $tests;
