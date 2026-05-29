<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record97 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current97 = static function () use ($record97): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record97('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record97('view', 'active_options', 'active_options', 0, "CREATE VIEW main.active_options AS SELECT option_id, option_name, option_value FROM main.wp_options WHERE autoload = 'yes'", 2),
        $record97('trigger', 'active_options_io', 'active_options', 0, "CREATE TRIGGER main.active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END", 3),
        $record97('view', 'stable_options', 'stable_options', 0, "CREATE VIEW main.stable_options AS SELECT option_id, option_name FROM main.wp_options", 4),
        $record97('trigger', 'stable_options_io', 'stable_options', 0, "CREATE TRIGGER main.stable_options_io INSTEAD OF DELETE ON stable_options BEGIN DELETE FROM wp_options WHERE option_id = old.option_id; END", 5),
    ], [
        $record97('table', 'wp_options_stage', 'wp_options_stage', 10, 'CREATE TEMP TABLE wp_options_stage(option_id integer, option_name text, option_value text)', 6),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record97('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 7),
        $record97('view', 'site_active_options', 'site_active_options', 0, "CREATE VIEW site.site_active_options AS SELECT blog_id, option_name, option_value FROM site.wp_options WHERE autoload = 'yes'", 8),
        $record97('trigger', 'site_active_options_io', 'site_active_options', 0, "CREATE TRIGGER site.site_active_options_io INSTEAD OF UPDATE ON site_active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; END", 9),
    ]);

    return $catalog;
};

$next97 = static function () use ($record97): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record97('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, migrated integer)', 1),
        $record97('view', 'active_options', 'active_options', 0, "CREATE VIEW main.active_options AS SELECT option_id, option_name, option_value FROM main.wp_options WHERE autoload IN ('yes','auto-on')", 2),
        $record97('trigger', 'active_options_io', 'active_options', 0, "CREATE TRIGGER main.active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END", 3),
        $record97('view', 'stable_options', 'stable_options', 0, "CREATE VIEW main.stable_options AS SELECT option_id, option_name FROM main.wp_options", 4),
        $record97('trigger', 'stable_options_io', 'stable_options', 0, "CREATE TRIGGER main.stable_options_io INSTEAD OF DELETE ON stable_options BEGIN DELETE FROM wp_options WHERE option_id = old.option_id; END", 5),
    ], [
        $record97('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, expires integer)', 6),
        $record97('view', 'active_options', 'active_options', 0, 'CREATE TEMP VIEW active_options AS SELECT option_id, option_name, option_value FROM temp.wp_options WHERE expires > 0', 7),
        $record97('trigger', 'active_options_io', 'active_options', 0, "CREATE TEMP TRIGGER active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE temp.wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END", 8),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record97('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text, migrated integer)', 9),
        $record97('view', 'site_active_options', 'site_active_options', 0, "CREATE VIEW site.site_active_options AS SELECT blog_id, option_name, option_value FROM site.wp_options WHERE autoload IN ('yes','network')", 10),
        $record97('trigger', 'site_active_options_io', 'site_active_options', 0, "CREATE TRIGGER site.site_active_options_io INSTEAD OF UPDATE ON site_active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; END", 11),
    ]);

    return $catalog;
};

$prepared97 = [
    ['name' => 'active_options_io', 'active' => true, 'statement' => 'UPDATE active_options SET option_value = ? WHERE option_id = ?'],
    ['name' => 'main.active_options_io'],
    ['name' => 'main.stable_options_io'],
    ['name' => 'site.site_active_options_io', 'active' => true],
];

$states97 = static fn (): array => [
    'main' => ['schema_cookie' => 31, 'wal_schema_cookie' => 32],
    'temp' => ['schema_cookie' => 8],
    'site' => ['schema_cookie' => 11, 'wal_frames' => [['page' => 1, 'schema_cookie' => 12, 'commit' => true]]],
];

$plan97 = static fn (?array $prepared = null, ?array $states = null): array => SQLiteAttachTempWalSchemaTriggerPlan::triggerViewCacheCurrentSourceNext(
    $current97(),
    $next97(),
    $prepared ?? $prepared97,
    $states ?? $states97(),
);

$value97 = static function (array $data, string $path): mixed {
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

$cases97 = [
    'status expired' => ['status', 'trigger_view_cache_expired'],
    'operation marker' => ['operation', 'attach-temp-wal-trigger-view-cache-current-source-next97'],
    'trigger count' => ['trigger_count', 4],
    'active count' => ['active_trigger_count', 2],
    'requires reprepare' => ['requires_reprepare', true],
    'view cache trigger list' => ['view_cache_triggers', ['active_options_io', 'main.active_options_io', 'main.stable_options_io', 'site.site_active_options_io']],
    'view expired trigger list' => ['view_cache_expired_triggers', ['active_options_io', 'main.active_options_io', 'site.site_active_options_io']],
    'view stable trigger list' => ['view_cache_stable_triggers', ['main.stable_options_io']],
    'reprepare includes view body changes' => ['reprepare_triggers', ['active_options_io', 'main.active_options_io', 'site.site_active_options_io']],
    'stable trigger preserved' => ['stable_triggers', ['main.stable_options_io']],
    'active snapshots include unqualified temp shadow' => ['active_current_snapshot_triggers', ['active_options_io', 'site.site_active_options_io']],
    'next step schema only qualified inactive main view trigger' => ['next_step_schema_triggers', ['main.active_options_io']],
    'reset schema active triggers' => ['reset_schema_triggers', ['active_options_io', 'site.site_active_options_io']],
    'changed schemas include temp main site' => ['changed_schemas', ['temp', 'main', 'site']],
    'wal schemas include main site' => ['wal_schemas', ['main', 'site']],
    'temp schemas include temp' => ['temp_schemas', ['temp']],
    'attached schemas include site' => ['attached_schemas', ['site']],
    'current main cookie' => ['schema_cookies_current.main', 31],
    'next main cookie' => ['schema_cookies_next.main', 32],
    'current site cookie' => ['schema_cookies_current.site', 11],
    'next site cookie from wal page one' => ['schema_cookies_next.site', 12],
    'dependency marker first' => ['dependencies.0', 'sqlite-attach-temp-wal-trigger-view-cache-current-source-next97'],
    'dependency trigger current source' => ['dependencies.1', 'sqlite-attach-temp-wal-schema-trigger-current-source-next90'],
    'unqualified trigger current view schema' => ['triggers.active_options_io.current.targetSchema', 'main'],
    'unqualified trigger next view schema' => ['triggers.active_options_io.next.targetSchema', 'temp'],
    'unqualified view current schema' => ['view_caches.active_options_io.current.schema', 'main'],
    'unqualified view next schema' => ['view_caches.active_options_io.next.schema', 'temp'],
    'unqualified view changed fields' => ['view_caches.active_options_io.changed_fields', ['schema', 'sql', 'dependencies']],
    'unqualified current view dependency main' => ['view_caches.active_options_io.current.dependency_schemas', ['main']],
    'unqualified next view dependency temp' => ['view_caches.active_options_io.next.dependency_schemas', ['temp']],
    'unqualified view keeps current source' => ['view_caches.active_options_io.current_source_kept_until_reset', true],
    'unqualified action reset' => ['triggers.active_options_io.next_step_action', 'finish_current_view_source_then_sqlite_schema_on_reset'],
    'unqualified current step ok' => ['triggers.active_options_io.current_step_result', 'SQLITE_OK'],
    'qualified main view changed fields sql only' => ['view_caches.main.active_options_io.changed_fields', ['sql']],
    'qualified main action before next step' => ['triggers.main.active_options_io.next_step_action', 'sqlite_schema_before_next_view_trigger_step'],
    'qualified main current result schema' => ['triggers.main.active_options_io.current_step_result', 'SQLITE_SCHEMA'],
    'qualified stable view unchanged' => ['view_caches.main.stable_options_io.changed_fields', []],
    'qualified stable no reprepare' => ['view_caches.main.stable_options_io.requires_reprepare', false],
    'qualified stable action reuse' => ['triggers.main.stable_options_io.next_step_action', 'reuse_prepared_trigger'],
    'site view current schema' => ['view_caches.site.site_active_options_io.current.schema', 'site'],
    'site view next schema' => ['view_caches.site.site_active_options_io.next.schema', 'site'],
    'site view changed fields sql' => ['view_caches.site.site_active_options_io.changed_fields', ['sql']],
    'site view dependency schema' => ['view_caches.site.site_active_options_io.current.dependency_schemas', ['site']],
    'site active action reset' => ['triggers.site.site_active_options_io.next_step_action', 'finish_current_view_source_then_sqlite_schema_on_reset'],
    'site active current ok' => ['triggers.site.site_active_options_io.current_step_result', 'SQLITE_OK'],
];

$tests = [];
foreach ($cases97 as $name => [$path, $expected]) {
    $tests['attach temp wal trigger view cache current source next97 ' . $name] = static function (TestRunner $t) use ($plan97, $value97, $path, $expected): void {
        $t->same($expected, $value97($plan97(), $path));
    };
}

$tests['attach temp wal trigger view cache current source next97 stable-only view trigger is reusable'] = static function (TestRunner $t) use ($plan97): void {
    $result = $plan97([['name' => 'main.stable_options_io']]);
    $t->same('trigger_view_cache_stable', $result['status']);
    $t->same(['main.stable_options_io'], $result['stable_triggers']);
    $t->same([], $result['reprepare_triggers']);
};

$tests['attach temp wal trigger view cache current source next97 active flag controls current source retention'] = static function (TestRunner $t) use ($plan97): void {
    $inactive = $plan97([['name' => 'site.site_active_options_io', 'active' => false]]);
    $active = $plan97([['name' => 'site.site_active_options_io', 'active' => true]]);
    $t->same('SQLITE_SCHEMA', $inactive['triggers']['site.site_active_options_io']['current_step_result']);
    $t->same('SQLITE_OK', $active['triggers']['site.site_active_options_io']['current_step_result']);
    $t->same(false, $inactive['view_caches']['site.site_active_options_io']['current_source_kept_until_reset']);
    $t->same(true, $active['view_caches']['site.site_active_options_io']['current_source_kept_until_reset']);
};

$tests['attach temp wal trigger view cache current source next97 explicit wal cookie overrides frame'] = static function (TestRunner $t) use ($plan97, $states97): void {
    $states = $states97();
    $states['site']['wal_schema_cookie'] = 44;
    $t->same(44, $plan97(null, $states)['schema_cookies_next']['site']);
};

$tests['attach temp wal trigger view cache current source next97 rejects empty prepared list'] = static function (TestRunner $t) use ($current97, $next97): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerViewCacheCurrentSourceNext($current97(), $next97(), []));
};

$tests['attach temp wal trigger view cache current source next97 rejects table trigger without view cache only when missing trigger'] = static function (TestRunner $t) use ($current97, $next97): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerViewCacheCurrentSourceNext($current97(), $next97(), [['name' => 'missing_view_trigger']]));
};

return $tests;
