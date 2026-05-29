<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record89 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog89 = static function () use ($record89): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record89('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record89('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, new_value text)', 2),
        $record89('trigger', 'options_au', 'wp_options', 0, "CREATE TRIGGER main.options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value WHERE option_id = old.option_id; SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END", 3),
        $record89('trigger', 'options_ad', 'wp_options', 0, "CREATE TRIGGER main.options_ad AFTER DELETE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value) VALUES(old.option_id, old.option_name, NULL); END", 4),
    ], [
        $record89('table', 'wp_option_audit', 'wp_option_audit', 10, 'CREATE TEMP TABLE wp_option_audit(option_id integer, option_name text, new_value text)', 5),
        $record89('trigger', 'temp_options_au', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_au AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); INSERT INTO main.wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); END", 6),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record89('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 7),
        $record89('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, new_value text)', 8),
        $record89('trigger', 'site_options_au', 'wp_options', 0, "CREATE TRIGGER site.site_options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value WHERE blog_id = old.blog_id; END", 9),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record89('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text)', 10),
        $record89('trigger', 'archive_cleanup', 'wp_options', 0, "CREATE TRIGGER archive.archive_cleanup AFTER DELETE ON wp_options BEGIN SELECT old.blog_id FROM wp_options WHERE blog_id = old.blog_id; END", 11),
    ]);

    return $catalog;
};

$mainNext89 = static fn (int $root = 40): array => [
    $record89('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, touched integer)', $root + 1),
    $record89('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, new_value text, source text)', $root + 2),
    $record89('trigger', 'options_au', 'wp_options', 0, "CREATE TRIGGER main.options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value, source = 'main-next' WHERE option_id = old.option_id; SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END", $root + 3),
    $record89('trigger', 'options_ad', 'wp_options', 0, "CREATE TRIGGER main.options_ad AFTER DELETE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value, source) VALUES(old.option_id, old.option_name, NULL, 'main-next'); END", $root + 4),
];

$tempNext89 = static fn (int $root = 50): array => [
    $record89('table', 'wp_option_audit', 'wp_option_audit', $root, 'CREATE TEMP TABLE wp_option_audit(option_id integer, option_name text, new_value text, source text)', $root + 1),
    $record89('trigger', 'temp_options_au', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_au AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value, source) VALUES(new.option_id, new.option_name, new.option_value, 'temp-next'); INSERT INTO main.wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); END", $root + 2),
];

$siteNext89 = static fn (int $root = 60): array => [
    $record89('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', $root + 1),
    $record89('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, new_value text, source text)', $root + 2),
    $record89('trigger', 'site_options_au', 'wp_options', 0, "CREATE TRIGGER site.site_options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value, source = 'site-next' WHERE blog_id = old.blog_id; END", $root + 3),
];

$states89 = static fn (): array => [
    'main' => ['schema_cookie' => 12],
    'temp' => ['schema_cookie' => 4],
    'site' => ['schema_cookie' => 8],
    'archive' => ['schema_cookie' => 5],
];

$plan89 = static fn (array $triggers, array $next = [], ?array $states = null): array => SQLiteAttachTempWalSchemaTriggerPlan::walTriggerCookieCachePlan(
    $catalog89(),
    $triggers,
    $next,
    $states ?? $states89(),
);

$stable89 = static fn (): array => $plan89([
    ['name' => 'main.options_au', 'source' => 'main', 'active' => true],
    ['name' => 'temp_options_au', 'source' => 'temp'],
    ['name' => 'site.site_options_au', 'source' => 'site'],
]);
$mainWal89 = static fn (): array => $plan89([
    ['name' => 'main.options_au', 'source' => 'main', 'active' => true],
    ['name' => 'temp_options_au', 'source' => 'temp'],
    ['name' => 'site.site_options_au', 'source' => 'site'],
], [], [
    'main' => ['schema_cookie' => 12, 'wal_frames' => [['page' => 1, 'schema_cookie' => 13, 'commit' => true]]],
    'temp' => ['schema_cookie' => 4],
    'site' => ['schema_cookie' => 8],
    'archive' => ['schema_cookie' => 5],
]);
$tempCookie89 = static fn (): array => $plan89([
    ['name' => 'main.options_au', 'source' => 'main'],
    ['name' => 'temp_options_au', 'source' => 'temp', 'active' => true],
    ['name' => 'site.site_options_au', 'source' => 'site'],
], [], [
    'main' => ['schema_cookie' => 12],
    'temp' => ['schema_cookie' => 4, 'wal_schema_cookie' => 5],
    'site' => ['schema_cookie' => 8],
    'archive' => ['schema_cookie' => 5],
]);
$siteRecord89 = static fn (): array => $plan89([
    ['name' => 'main.options_au', 'source' => 'main'],
    ['name' => 'site.site_options_au', 'source' => 'site', 'active' => true],
    ['name' => 'archive.archive_cleanup', 'source' => 'archive'],
], ['site' => $siteNext89(60)]);
$mainRecordAndCookie89 = static fn (): array => $plan89([
    ['name' => 'main.options_au', 'source' => 'main', 'active' => true],
    ['name' => 'main.options_ad', 'source' => 'main'],
    ['name' => 'temp_options_au', 'source' => 'temp'],
], ['main' => $mainNext89(70)], [
    'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 14],
    'temp' => ['schema_cookie' => 4],
    'site' => ['schema_cookie' => 8],
    'archive' => ['schema_cookie' => 5],
]);
$tempRecord89 = static fn (): array => $plan89([
    ['name' => 'temp_options_au', 'source' => 'temp', 'active' => true],
    ['name' => 'main.options_au', 'source' => 'main'],
], ['temp' => $tempNext89(80)]);

$value89 = static function (array $data, string $path): mixed {
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

$pathCases89 = [
    'stable status' => [$stable89, 'status', 'trigger_cache_stable'],
    'stable operation' => [$stable89, 'operation', 'attach-wal-temp-trigger-cookie-cache'],
    'stable trigger count' => [$stable89, 'trigger_count', 3],
    'stable active count' => [$stable89, 'active_trigger_count', 1],
    'stable source schemas' => [$stable89, 'source_schemas.temp_options_au', 'temp'],
    'stable no reprepare' => [$stable89, 'requires_reprepare', false],
    'stable list' => [$stable89, 'stable_triggers', ['main.options_au', 'temp_options_au', 'site.site_options_au']],
    'stable action' => [$stable89, 'triggers.main.options_au.next_step_action', 'reuse_prepared_trigger_current_and_next_source'],
    'stable dependency schemas include main' => [$stable89, 'triggers.main.options_au.dependency_schemas', ['main']],
    'stable temp dependency schemas include main temp' => [$stable89, 'triggers.temp_options_au.dependency_schemas', ['main', 'temp']],

    'main wal status expired' => [$mainWal89, 'status', 'trigger_cache_expired'],
    'main wal changed schemas' => [$mainWal89, 'changed_schemas', ['main']],
    'main wal source listed' => [$mainWal89, 'wal_schema_cookie_sources', ['main']],
    'main wal cookie next' => [$mainWal89, 'schema_cookies_next.main', 13],
    'main wal active current snapshot' => [$mainWal89, 'active_current_snapshot_triggers', ['main.options_au']],
    'main wal reset trigger' => [$mainWal89, 'reset_schema_triggers', ['main.options_au']],
    'main wal inactive temp schema before next step' => [$mainWal89, 'next_step_schema_triggers', ['temp_options_au']],
    'main wal reprepare main and temp' => [$mainWal89, 'reprepare_triggers', ['main.options_au', 'temp_options_au']],
    'main wal cookie expired list' => [$mainWal89, 'cookie_expired_triggers', ['main.options_au', 'temp_options_au']],
    'main wal record expired empty' => [$mainWal89, 'record_expired_triggers', []],
    'main wal active current ok' => [$mainWal89, 'triggers.main.options_au.current_step_result', 'SQLITE_OK'],
    'main wal inactive temp schema result' => [$mainWal89, 'triggers.temp_options_au.current_step_result', 'SQLITE_SCHEMA'],
    'main wal temp cookie schema is main' => [$mainWal89, 'triggers.temp_options_au.cookie_changed_schemas', ['main']],
    'main wal site stable' => [$mainWal89, 'triggers.site.site_options_au.requires_reprepare', false],

    'temp cookie changed schemas' => [$tempCookie89, 'changed_schemas', ['temp']],
    'temp cookie active snapshot' => [$tempCookie89, 'active_current_snapshot_triggers', ['temp_options_au']],
    'temp cookie reprepare only temp trigger' => [$tempCookie89, 'reprepare_triggers', ['temp_options_au']],
    'temp cookie main stable' => [$tempCookie89, 'triggers.main.options_au.requires_reprepare', false],
    'temp cookie site stable' => [$tempCookie89, 'triggers.site.site_options_au.requires_reprepare', false],
    'temp cookie changed on trigger' => [$tempCookie89, 'triggers.temp_options_au.cookie_changed_schemas', ['temp']],
    'temp cookie reset action' => [$tempCookie89, 'triggers.temp_options_au.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],

    'site record changed schemas' => [$siteRecord89, 'changed_schemas', ['site']],
    'site record active snapshot' => [$siteRecord89, 'active_current_snapshot_triggers', ['site.site_options_au']],
    'site record expired list' => [$siteRecord89, 'record_expired_triggers', ['site.site_options_au']],
    'site record cookie expired empty' => [$siteRecord89, 'cookie_expired_triggers', []],
    'site record archive stable' => [$siteRecord89, 'stable_triggers', ['main.options_au', 'archive.archive_cleanup']],
    'site record target changed' => [$siteRecord89, 'triggers.site.site_options_au.target_changed', true],
    'site record body dependency names stable' => [$siteRecord89, 'triggers.site.site_options_au.body_dependencies_changed', false],

    'main record cookie reprepare all main dependents' => [$mainRecordAndCookie89, 'reprepare_triggers', ['main.options_au', 'main.options_ad', 'temp_options_au']],
    'main record cookie active snapshot' => [$mainRecordAndCookie89, 'active_current_snapshot_triggers', ['main.options_au']],
    'main record cookie inactive next steps' => [$mainRecordAndCookie89, 'next_step_schema_triggers', ['main.options_ad', 'temp_options_au']],
    'main record cookie expired list' => [$mainRecordAndCookie89, 'cookie_expired_triggers', ['main.options_au', 'main.options_ad', 'temp_options_au']],
    'main record record expired list' => [$mainRecordAndCookie89, 'record_expired_triggers', ['main.options_au', 'main.options_ad', 'temp_options_au']],
    'main record before sql preserved' => [$mainRecordAndCookie89, 'triggers.main.options_au.before.sql', "CREATE TRIGGER main.options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value WHERE option_id = old.option_id; SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END"],
    'main record after sql changed' => [$mainRecordAndCookie89, 'triggers.main.options_au.after.sql', "CREATE TRIGGER main.options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value, source = 'main-next' WHERE option_id = old.option_id; SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END"],

    'temp record reprepare temp only' => [$tempRecord89, 'reprepare_triggers', ['temp_options_au']],
    'temp record main stable' => [$tempRecord89, 'stable_triggers', ['main.options_au']],
    'temp record trigger changed' => [$tempRecord89, 'triggers.temp_options_au.trigger_changed', true],
    'temp record no cookie expiry' => [$tempRecord89, 'triggers.temp_options_au.schema_cookie_changed', false],
    'temp record active keeps source' => [$tempRecord89, 'triggers.temp_options_au.current_source_kept_until_reset', true],
    'dependency marker' => [$stable89, 'dependencies.0', 'sqlite-attach-wal-temp-trigger-cookie-cache'],
    'temp cookie dependency marker' => [$stable89, 'dependencies.3', 'sqlite-temp-schema-cookie-trigger-expiry'],
];

foreach ($pathCases89 as $name => [$factory, $path, $expected]) {
    $tests['attach wal temp trigger cache current source next89 ' . $name] = static function (TestRunner $t) use ($factory, $value89, $path, $expected): void {
        $t->same($expected, $value89($factory(), $path));
    };
}

$predicateCases89 = [
    'active main WAL cookie keeps current source until reset' => static fn (): bool => $mainWal89()['triggers']['main.options_au']['current_source_kept_until_reset'] === true,
    'inactive temp main-cookie dependency fails before next trigger step' => static fn (): bool => $mainWal89()['triggers']['temp_options_au']['next_step_action'] === 'sqlite_schema_before_next_trigger_step',
    'committed non page-one WAL frame is ignored' => static function () use ($plan89): bool {
        $plan = $plan89([['name' => 'main.options_au', 'source' => 'main']], [], ['main' => ['schema_cookie' => 12, 'wal_frames' => [['page' => 2, 'schema_cookie' => 99, 'commit' => true]]]]);
        return $plan['requires_reprepare'] === false && $plan['schema_cookies_next']['main'] === 12;
    },
    'uncommitted page-one WAL frame is ignored' => static function () use ($plan89): bool {
        $plan = $plan89([['name' => 'main.options_au', 'source' => 'main']], [], ['main' => ['schema_cookie' => 12, 'wal_frames' => [['page' => 1, 'schema_cookie' => 99, 'commit' => false]]]]);
        return $plan['requires_reprepare'] === false && $plan['schema_cookies_next']['main'] === 12;
    },
    'explicit WAL schema cookie wins over page-one frame' => static function () use ($plan89): bool {
        $plan = $plan89([['name' => 'main.options_au', 'source' => 'main']], [], ['main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 14, 'wal_frames' => [['page' => 1, 'schema_cookie' => 13, 'commit' => true]]]]);
        return $plan['schema_cookies_next']['main'] === 14 && $plan['requires_reprepare'] === true;
    },
    'quoted source schema is normalized' => static function () use ($catalog89): bool {
        $plan = SQLiteAttachTempWalSchemaTriggerPlan::walTriggerCookieCachePlan($catalog89(), [['name' => 'site.site_options_au', 'source' => '"site"']]);
        return $plan['source_schemas']['site.site_options_au'] === 'site';
    },
    'archive WAL cookie does not expire unrelated main trigger' => static function () use ($plan89): bool {
        $plan = $plan89([['name' => 'main.options_au', 'source' => 'main']], [], [
            'main' => ['schema_cookie' => 12],
            'archive' => ['schema_cookie' => 5, 'wal_schema_cookie' => 6],
        ]);
        return $plan['requires_reprepare'] === false && $plan['changed_schemas'] === ['archive'];
    },
    'temp trigger expires when either temp or main cookie changes' => static function () use ($plan89): bool {
        $plan = $plan89([['name' => 'temp_options_au', 'source' => 'temp']], [], [
            'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 13],
            'temp' => ['schema_cookie' => 4, 'wal_schema_cookie' => 5],
        ]);
        return $plan['triggers']['temp_options_au']['cookie_changed_schemas'] === ['main', 'temp'];
    },
    'site trigger dependency schemas stay attached only' => static fn (): bool => $stable89()['triggers']['site.site_options_au']['dependency_schemas'] === ['site'],
    'main delete trigger without body select depends on main schema' => static fn (): bool => $mainRecordAndCookie89()['triggers']['main.options_ad']['dependency_schemas'] === ['main'],
    'empty list rejected' => static function () use ($catalog89): bool {
        try {
            SQLiteAttachTempWalSchemaTriggerPlan::walTriggerCookieCachePlan($catalog89(), []);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
    'missing trigger name rejected' => static function () use ($catalog89): bool {
        try {
            SQLiteAttachTempWalSchemaTriggerPlan::walTriggerCookieCachePlan($catalog89(), [['source' => 'main']]);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
    'bad WAL cookie rejected' => static function () use ($plan89): bool {
        try {
            $plan89([['name' => 'main.options_au']], [], ['main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 'bad']]);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
];

foreach ($predicateCases89 as $name => $predicate) {
    $tests['attach wal temp trigger cache current source next89 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

return $tests;
