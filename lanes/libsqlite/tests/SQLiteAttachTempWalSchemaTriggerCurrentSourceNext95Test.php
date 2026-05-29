<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record95 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current95 = static function () use ($record95): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record95('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record95('table', 'wp_audit', 'wp_audit', 3, 'CREATE TABLE main.wp_audit(option_id integer, option_name text, source text)', 2),
        $record95('trigger', 'main_options_ai', 'wp_options', 0, "CREATE TRIGGER main.main_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'main'); END", 3),
    ], [
        $record95('table', 'wp_audit', 'wp_audit', 10, 'CREATE TEMP TABLE wp_audit(option_id integer, option_name text, source text)', 4),
        $record95('trigger', 'temp_bridge_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_bridge_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp'); INSERT INTO site.wp_audit(blog_id, option_name, source) VALUES(new.option_id, new.option_name, 'site'); END", 5),
        $record95('trigger', 'temp_main_audit_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_audit_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO main.wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'main-qualified'); END", 6),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record95('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 7),
        $record95('table', 'wp_audit', 'wp_audit', 21, 'CREATE TABLE site.wp_audit(blog_id integer, option_name text, source text)', 8),
        $record95('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site'); END", 9),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record95('table', 'wp_audit', 'wp_audit', 30, 'CREATE TABLE archive.wp_audit(blog_id integer, option_name text, source text)', 10),
        $record95('trigger', 'archive_cleanup', 'wp_audit', 0, "CREATE TRIGGER archive.archive_cleanup AFTER DELETE ON wp_audit BEGIN SELECT old.blog_id FROM archive.wp_audit WHERE blog_id = old.blog_id; END", 11),
    ]);

    return $catalog;
};

$next95 = static function () use ($record95): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record95('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, migrated integer)', 1),
        $record95('table', 'wp_audit', 'wp_audit', 3, 'CREATE TABLE main.wp_audit(option_id integer, option_name text, source text, migrated integer)', 2),
        $record95('trigger', 'main_options_ai', 'wp_options', 0, "CREATE TRIGGER main.main_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source, migrated) VALUES(new.option_id, new.option_name, 'main-next', 1); END", 3),
    ], [
        $record95('trigger', 'temp_bridge_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_bridge_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-next'); INSERT INTO site.wp_audit(blog_id, option_name, source) VALUES(new.option_id, new.option_name, 'site'); END", 4),
        $record95('trigger', 'temp_main_audit_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_audit_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO main.wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'main-qualified'); END", 5),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record95('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 6),
        $record95('table', 'wp_audit', 'wp_audit', 21, 'CREATE TABLE site.wp_audit(blog_id integer, option_name text, source text)', 7),
        $record95('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site'); END", 8),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record95('table', 'wp_audit', 'wp_audit', 30, 'CREATE TABLE archive.wp_audit(blog_id integer, option_name text, source text)', 9),
        $record95('trigger', 'archive_cleanup', 'wp_audit', 0, "CREATE TRIGGER archive.archive_cleanup AFTER DELETE ON wp_audit BEGIN SELECT old.blog_id FROM archive.wp_audit WHERE blog_id = old.blog_id; END", 10),
    ]);

    return $catalog;
};

$states95 = static fn (): array => [
    'main' => ['schema_cookie' => 30, 'wal_schema_cookie' => 31],
    'temp' => ['schema_cookie' => 7],
    'site' => ['schema_cookie' => 12, 'wal_frames' => [['page' => 1, 'schema_cookie' => 13, 'commit' => true]]],
    'archive' => ['schema_cookie' => 5],
];

$prepared95 = [
    ['name' => 'temp_bridge_ai', 'active' => true],
    ['name' => 'temp_main_audit_ai'],
    ['name' => 'main.main_options_ai'],
    ['name' => 'site.site_options_ai', 'active' => true],
    ['name' => 'archive.archive_cleanup'],
];

$plan95 = static fn (?array $prepared = null, ?array $states = null): array => SQLiteAttachTempWalSchemaTriggerPlan::triggerDependencyCookiePlan(
    $current95(),
    $next95(),
    $prepared ?? $prepared95,
    $states ?? $states95(),
);

$value95 = static function (array $data, string $path): mixed {
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

$cases95 = [
    'status expired' => ['status', 'trigger_current_source_expired'],
    'operation marker' => ['operation', 'attach-temp-wal-schema-trigger-dependency-cookie'],
    'trigger count' => ['trigger_count', 5],
    'active trigger count' => ['active_trigger_count', 2],
    'requires reprepare' => ['requires_reprepare', true],
    'reprepare order' => ['reprepare_triggers', ['temp_bridge_ai', 'temp_main_audit_ai', 'main.main_options_ai', 'site.site_options_ai']],
    'stable order' => ['stable_triggers', ['archive.archive_cleanup']],
    'active current order' => ['active_current_snapshot_triggers', ['temp_bridge_ai', 'site.site_options_ai']],
    'reset schema order' => ['reset_schema_triggers', ['temp_bridge_ai', 'site.site_options_ai']],
    'next step order' => ['next_step_schema_triggers', ['temp_main_audit_ai', 'main.main_options_ai']],
    'dependency moved triggers' => ['dependency_moved_triggers', ['temp_bridge_ai']],
    'cookie expired triggers' => ['cookie_expired_triggers', ['temp_bridge_ai', 'temp_main_audit_ai', 'main.main_options_ai', 'site.site_options_ai']],
    'changed schema order' => ['changed_schemas', ['temp', 'main', 'site']],
    'wal schemas order' => ['wal_schemas', ['main', 'site']],
    'temp schema order' => ['temp_schemas', ['temp']],
    'attached schema order' => ['attached_schemas', ['site']],
    'current main cookie' => ['schema_cookies_current.main', 30],
    'next main cookie' => ['schema_cookies_next.main', 31],
    'next site cookie' => ['schema_cookies_next.site', 13],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-temp-wal-schema-trigger-dependency-cookie'],
    'dependency temp body resolution' => ['dependencies.2', 'sqlite-temp-trigger-body-dependency-resolution'],
    'temp bridge current deps' => ['triggers.temp_bridge_ai.current_body_dependency_schemas', ['temp', 'site']],
    'temp bridge next deps' => ['triggers.temp_bridge_ai.next_body_dependency_schemas', ['main', 'site']],
    'temp bridge all deps' => ['triggers.temp_bridge_ai.dependency_schemas', ['temp', 'main', 'site']],
    'temp bridge moved' => ['triggers.temp_bridge_ai.dependency_moved', true],
    'temp bridge cookie schemas' => ['triggers.temp_bridge_ai.cookie_changed_schemas', ['main', 'site']],
    'temp bridge keeps current source' => ['triggers.temp_bridge_ai.current_source_kept_until_reset', true],
    'temp bridge action' => ['triggers.temp_bridge_ai.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'temp bridge result' => ['triggers.temp_bridge_ai.current_step_result', 'SQLITE_OK'],
    'temp bridge invalidation schemas' => ['triggers.temp_bridge_ai.invalidated_sources', ['temp', 'main', 'site']],
    'temp bridge changed fields' => ['triggers.temp_bridge_ai.changed_fields', ['columns']],
    'temp main audit current deps' => ['triggers.temp_main_audit_ai.current_body_dependency_schemas', ['main']],
    'temp main audit next deps' => ['triggers.temp_main_audit_ai.next_body_dependency_schemas', ['main']],
    'temp main audit no move' => ['triggers.temp_main_audit_ai.dependency_moved', false],
    'temp main audit cookie schema' => ['triggers.temp_main_audit_ai.cookie_changed_schemas', ['main']],
    'temp main audit inactive result' => ['triggers.temp_main_audit_ai.current_step_result', 'SQLITE_SCHEMA'],
    'main trigger deps' => ['triggers.main.main_options_ai.current_body_dependency_schemas', ['main']],
    'main trigger cookie schema' => ['triggers.main.main_options_ai.cookie_changed_schemas', ['main']],
    'main trigger action' => ['triggers.main.main_options_ai.next_step_action', 'sqlite_schema_on_next_step'],
    'site trigger deps' => ['triggers.site.site_options_ai.current_body_dependency_schemas', ['site']],
    'site trigger cookie schema' => ['triggers.site.site_options_ai.cookie_changed_schemas', ['site']],
    'site trigger attached schemas' => ['triggers.site.site_options_ai.attached_schemas', ['site']],
    'site trigger keeps active current' => ['triggers.site.site_options_ai.current_source_kept_until_reset', true],
    'archive stable deps' => ['triggers.archive.archive_cleanup.current_body_dependency_schemas', ['archive']],
    'archive stable moved false' => ['triggers.archive.archive_cleanup.dependency_moved', false],
    'archive stable cookie schemas' => ['triggers.archive.archive_cleanup.cookie_changed_schemas', []],
    'archive stable action' => ['triggers.archive.archive_cleanup.next_step_action', 'reuse_prepared_trigger'],
    'archive stable invalidation empty' => ['triggers.archive.archive_cleanup.invalidated_sources', []],
];

foreach ($cases95 as $name => [$path, $expected]) {
    $tests['attach temp wal schema trigger current source next95 ' . $name] = static function (TestRunner $t) use ($plan95, $value95, $path, $expected): void {
        $t->same($expected, $value95($plan95(), $path));
    };
}

$predicateCases95 = [
    'temp bridge would be missed by raw qualified body text compare' => static fn (): bool => $plan95()['triggers']['temp_bridge_ai']['current_body_dependency_schemas'] !== $plan95()['triggers']['temp_bridge_ai']['next_body_dependency_schemas'],
    'main WAL cookie alone expires qualified TEMP trigger body dependency' => static fn (): bool => $plan95([['name' => 'temp_main_audit_ai']], ['main' => ['schema_cookie' => 30, 'wal_schema_cookie' => 31]])['reprepare_triggers'] === ['temp_main_audit_ai'],
    'site WAL frame alone expires active attached trigger' => static fn (): bool => $plan95([['name' => 'site.site_options_ai', 'active' => true]], ['site' => ['schema_cookie' => 12, 'wal_frames' => [['page' => 1, 'schema_cookie' => 13, 'commit' => true]]]])['reset_schema_triggers'] === ['site.site_options_ai'],
    'archive non page-one WAL frame does not expire archive trigger' => static fn (): bool => $plan95([['name' => 'archive.archive_cleanup']], ['archive' => ['schema_cookie' => 5, 'wal_frames' => [['page' => 2, 'schema_cookie' => 6, 'commit' => true]]]])['stable_triggers'] === ['archive.archive_cleanup'],
    'uncommitted site WAL frame does not expire site trigger' => static fn (): bool => $plan95([['name' => 'site.site_options_ai']], ['site' => ['schema_cookie' => 12, 'wal_frames' => [['page' => 1, 'schema_cookie' => 13, 'commit' => false]]]])['stable_triggers'] === ['site.site_options_ai'],
    'active false dependency move fails before next step' => static fn (): bool => $plan95([['name' => 'temp_bridge_ai']])['next_step_schema_triggers'] === ['temp_bridge_ai'],
    'active true dependency move finishes current source' => static fn (): bool => $plan95([['name' => 'temp_bridge_ai', 'active' => true]])['active_current_snapshot_triggers'] === ['temp_bridge_ai'],
    'stable archive-only plan reports stable status' => static fn (): bool => $plan95([['name' => 'archive.archive_cleanup']], ['archive' => ['schema_cookie' => 5]])['status'] === 'trigger_current_source_stable',
    'missing trigger rejected through next95' => static function () use ($current95, $next95): bool {
        try {
            SQLiteAttachTempWalSchemaTriggerPlan::triggerDependencyCookiePlan($current95(), $next95(), [['name' => 'missing']]);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
    'empty prepared list rejected through next95' => static function () use ($current95, $next95): bool {
        try {
            SQLiteAttachTempWalSchemaTriggerPlan::triggerDependencyCookiePlan($current95(), $next95(), []);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
];

foreach ($predicateCases95 as $name => $predicate) {
    $tests['attach temp wal schema trigger current source next95 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

return $tests;
