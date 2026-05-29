<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record108 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current108 = static function () use ($record108): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record108('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record108('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, source text)', 2),
        $record108('view', 'stable_options', 'stable_options', 0, 'CREATE VIEW main.stable_options AS SELECT option_id, option_name FROM main.wp_options', 3),
        $record108('trigger', 'stable_options_io', 'stable_options', 0, "CREATE TRIGGER main.stable_options_io INSTEAD OF UPDATE ON stable_options BEGIN UPDATE wp_options SET option_name = new.option_name WHERE option_id = old.option_id; END", 4),
    ], [
        $record108('view', 'active_options', 'active_options', 0, "CREATE TEMP VIEW active_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 5),
        $record108('trigger', 'active_options_io', 'active_options', 0, "CREATE TEMP TRIGGER active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-view'); END", 6),
        $record108('view', 'missing_options', 'missing_options', 0, 'CREATE TEMP VIEW missing_options AS SELECT option_id FROM wp_missing_options', 7),
        $record108('trigger', 'missing_options_io', 'missing_options', 0, 'CREATE TEMP TRIGGER missing_options_io INSTEAD OF DELETE ON missing_options BEGIN SELECT old.option_id; END', 8),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record108('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 9),
        $record108('view', 'active_options', 'active_options', 0, 'CREATE VIEW site.active_options AS SELECT blog_id, option_name, option_value FROM wp_options WHERE autoload = "yes"', 10),
        $record108('trigger', 'site_active_options_io', 'active_options', 0, 'CREATE TRIGGER site.site_active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; END', 11),
    ]);

    return $catalog;
};

$next108 = static function () use ($record108): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record108('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record108('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, source text)', 2),
        $record108('view', 'stable_options', 'stable_options', 0, 'CREATE VIEW main.stable_options AS SELECT option_id, option_name FROM main.wp_options', 3),
        $record108('trigger', 'stable_options_io', 'stable_options', 0, "CREATE TRIGGER main.stable_options_io INSTEAD OF UPDATE ON stable_options BEGIN UPDATE wp_options SET option_name = new.option_name WHERE option_id = old.option_id; END", 4),
    ], [
        $record108('table', 'wp_options', 'wp_options', 40, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, autoload text, expires integer)', 5),
        $record108('table', 'wp_missing_options', 'wp_missing_options', 41, 'CREATE TEMP TABLE wp_missing_options(option_id integer)', 6),
        $record108('view', 'active_options', 'active_options', 0, "CREATE TEMP VIEW active_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 7),
        $record108('trigger', 'active_options_io', 'active_options', 0, "CREATE TEMP TRIGGER active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-view'); END", 8),
        $record108('view', 'missing_options', 'missing_options', 0, 'CREATE TEMP VIEW missing_options AS SELECT option_id FROM wp_missing_options', 9),
        $record108('trigger', 'missing_options_io', 'missing_options', 0, 'CREATE TEMP TRIGGER missing_options_io INSTEAD OF DELETE ON missing_options BEGIN SELECT old.option_id; END', 10),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record108('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 11),
        $record108('view', 'active_options', 'active_options', 0, 'CREATE VIEW site.active_options AS SELECT blog_id, option_name, option_value FROM wp_options WHERE autoload = "yes"', 12),
        $record108('trigger', 'site_active_options_io', 'active_options', 0, 'CREATE TRIGGER site.site_active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; END', 13),
    ]);

    return $catalog;
};

$prepared108 = static fn (): array => [
    ['name' => 'active_options_io', 'active' => true, 'statement' => 'UPDATE active_options SET option_value = ? WHERE option_id = ?'],
    ['name' => 'missing_options_io'],
    ['name' => 'main.stable_options_io'],
    ['name' => 'site.site_active_options_io', 'active' => true],
];

$states108 = static fn (): array => [
    'main' => ['schema_cookie' => 21],
    'temp' => ['schema_cookie' => 5],
    'site' => ['schema_cookie' => 8, 'wal_schema_cookie' => 8],
];

$plan108 = static fn (?array $prepared = null, ?array $states = null): array => SQLiteAttachTempWalSchemaTriggerPlan::triggerViewInvalidationCurrentSourceNext(
    $current108(),
    $next108(),
    $prepared ?? $prepared108(),
    $states ?? $states108(),
);

$value108 = static function (array $data, string $path): mixed {
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

$cases108 = [
    'operation marker' => ['operation', 'attach-temp-trigger-view-invalidation-current-source'],
    'dependency marker first' => ['dependencies.0', 'sqlite-attach-temp-trigger-view-invalidation-current-source'],
    'retains view cache dependency' => ['dependencies.1', 'sqlite-attach-temp-wal-trigger-view-cache-reprepare'],
    'status expired' => ['status', 'trigger_view_dependency_expired'],
    'trigger count' => ['trigger_count', 4],
    'active trigger count' => ['active_trigger_count', 2],
    'requires reprepare' => ['requires_reprepare', true],
    'reprepare trigger order' => ['reprepare_triggers', ['active_options_io', 'missing_options_io']],
    'stable trigger order' => ['stable_triggers', ['main.stable_options_io', 'site.site_active_options_io']],
    'expired trigger list' => ['view_dependency_expired_triggers', ['active_options_io', 'missing_options_io']],
    'stable dependency trigger list' => ['view_dependency_stable_triggers', ['main.stable_options_io', 'site.site_active_options_io']],
    'changed schemas include temp main' => ['changed_schemas', ['temp', 'main']],
    'wal schemas include main' => ['wal_schemas', ['main', 'site']],
    'temp schema included' => ['temp_schemas', ['temp']],
    'attached schemas remain stable' => ['attached_schemas', []],
    'active temp trigger kept' => ['active_current_snapshot_triggers', ['active_options_io']],
    'reset temp trigger' => ['reset_schema_triggers', ['active_options_io']],
    'inactive missing trigger next step' => ['next_step_schema_triggers', ['missing_options_io']],
    'temp action reset' => ['triggers.active_options_io.next_step_action', 'finish_current_view_dependency_source_then_sqlite_schema_on_reset'],
    'temp current step ok' => ['triggers.active_options_io.current_step_result', 'SQLITE_OK'],
    'missing action schema' => ['triggers.missing_options_io.next_step_action', 'sqlite_schema_before_next_view_dependency_step'],
    'missing current result schema' => ['triggers.missing_options_io.current_step_result', 'SQLITE_SCHEMA'],
    'temp changed field marker' => ['triggers.active_options_io.changed_fields.0', 'viewDependenciesResolved'],
    'temp view changed field marker' => ['view_caches.active_options_io.changed_fields.0', 'dependency_resolution'],
    'temp view raw sql stable' => ['view_caches.active_options_io.current.sql', "CREATE TEMP VIEW active_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'"],
    'temp view raw dependency current' => ['view_caches.active_options_io.current.dependencies.0', ['schema' => null, 'name' => 'wp_options']],
    'temp dependency current main' => ['view_caches.active_options_io.dependency_resolution.current.0.resolved_schema', 'main'],
    'temp dependency next temp' => ['view_caches.active_options_io.dependency_resolution.next.0.resolved_schema', 'temp'],
    'temp dependency current root' => ['view_caches.active_options_io.dependency_resolution.current.0.resolved_rootpage', 2],
    'temp dependency next root' => ['view_caches.active_options_io.dependency_resolution.next.0.resolved_rootpage', 40],
    'temp dependency changed' => ['view_caches.active_options_io.dependency_resolution.changed', true],
    'temp dependency schemas' => ['view_dependency_schemas.active_options_io', ['temp', 'main']],
    'missing current not found' => ['view_caches.missing_options_io.dependency_resolution.current.0.found', false],
    'missing next found' => ['view_caches.missing_options_io.dependency_resolution.next.0.found', true],
    'missing next temp root' => ['view_caches.missing_options_io.dependency_resolution.next.0.resolved_rootpage', 41],
    'stable main dependency schema' => ['view_caches.main.stable_options_io.dependency_resolution.current.0.resolved_schema', 'main'],
    'stable main dependency unchanged' => ['view_caches.main.stable_options_io.dependency_resolution.changed', false],
    'site dependency schema stable' => ['view_caches.site.site_active_options_io.dependency_resolution.current.0.resolved_schema', 'site'],
    'site dependency unchanged' => ['view_caches.site.site_active_options_io.dependency_resolution.changed', false],
    'site action reuse' => ['triggers.site.site_active_options_io.next_step_action', 'reuse_prepared_trigger'],
    'site current result ok' => ['triggers.site.site_active_options_io.current_step_result', 'SQLITE_OK'],
    'temp source kept until reset' => ['view_caches.active_options_io.current_source_kept_until_reset', true],
    'inactive missing not kept' => ['view_caches.missing_options_io.current_source_kept_until_reset', false],
];

$tests = [];
foreach ($cases108 as $name => [$path, $expected]) {
    $tests['attach temp trigger view invalidation current source next108 ' . $name] = static function (TestRunner $t) use ($plan108, $value108, $path, $expected): void {
        $t->same($expected, $value108($plan108(), $path));
    };
}

$predicates108 = [
    'stable only reports stable status' => static fn (): bool => $plan108([['name' => 'main.stable_options_io']])['status'] === 'trigger_view_dependency_stable',
    'stable only has no reprepare triggers' => static fn (): bool => $plan108([['name' => 'site.site_active_options_io']])['reprepare_triggers'] === [],
    'active flag controls dependency reset action' => static fn (): bool => $plan108([['name' => 'active_options_io', 'active' => false]])['triggers']['active_options_io']['next_step_action'] === 'sqlite_schema_before_next_view_dependency_step',
    'explicit attached view ignores temp shadow' => static fn (): bool => $plan108([['name' => 'site.site_active_options_io']])['view_caches']['site.site_active_options_io']['dependency_resolution']['next'][0]['resolved_schema'] === 'site',
    'temp view dependency source movement alone invalidates' => static function () use ($plan108): bool {
        $plan = $plan108([['name' => 'active_options_io']]);
        return $plan['view_caches']['active_options_io']['changed_fields'] === ['dependency_resolution']
            && $plan['view_caches']['active_options_io']['current']['sql'] === $plan['view_caches']['active_options_io']['next']['sql'];
    },
];

foreach ($predicates108 as $name => $predicate) {
    $tests['attach temp trigger view invalidation current source next108 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

$tests['attach temp trigger view invalidation current source next108 rejects empty prepared list'] = static function (TestRunner $t) use ($current108, $next108): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerViewInvalidationCurrentSourceNext($current108(), $next108(), []));
};

$tests['attach temp trigger view invalidation current source next108 rejects missing trigger'] = static function (TestRunner $t) use ($current108, $next108): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerViewInvalidationCurrentSourceNext($current108(), $next108(), [['name' => 'missing']]));
};

return $tests;
