<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record85 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog85 = static function () use ($record85): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record85('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record85('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, new_value text)', 2),
        $record85('trigger', 'options_ai', 'wp_options', 0, "CREATE TRIGGER main.options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); SELECT option_id FROM wp_options WHERE option_id = new.option_id; END", 3),
        $record85('trigger', 'options_au', 'wp_options', 0, "CREATE TRIGGER main.options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value WHERE option_id = old.option_id; SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END", 4),
    ], [
        $record85('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text)', 5),
        $record85('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, option_name text, new_value text)', 6),
        $record85('trigger', 'options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END", 7),
        $record85('trigger', 'bridge_main_ai', 'wp_options', 0, "CREATE TEMP TRIGGER bridge_main_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); INSERT INTO main.wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); END", 8),
    ]);

    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record85('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 9),
        $record85('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, new_value text)', 10),
        $record85('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, option_name, new_value) VALUES(new.blog_id, new.option_name, new.option_value); SELECT blog_id FROM wp_options WHERE blog_id = new.blog_id; END", 11),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record85('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text)', 12),
        $record85('trigger', 'archive_cleanup', 'wp_options', 0, "CREATE TRIGGER archive.archive_cleanup AFTER DELETE ON wp_options BEGIN SELECT old.blog_id FROM wp_options WHERE blog_id = old.blog_id; END", 13),
    ]);

    return $catalog;
};

$mainTriggerRecords = static fn (int $root = 40): array => [
    $record85('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, migrated integer)', $root + 1),
    $record85('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, new_value text, source text)', $root + 2),
    $record85('trigger', 'options_ai', 'wp_options', 0, "CREATE TRIGGER main.options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value, source) VALUES(new.option_id, new.option_name, new.option_value, 'main'); SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END", $root + 3),
    $record85('trigger', 'options_au', 'wp_options', 0, "CREATE TRIGGER main.options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value WHERE option_id = old.option_id; SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END", $root + 4),
];

$tempTriggerRecords = static fn (int $root = 50): array => [
    $record85('table', 'wp_options', 'wp_options', $root, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, expires integer)', $root + 1),
    $record85('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, option_name text, new_value text, source text)', $root + 2),
    $record85('trigger', 'options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value, source) VALUES(new.option_id, new.option_name, new.option_value, 'temp'); SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END", $root + 3),
    $record85('trigger', 'bridge_main_ai', 'wp_options', 0, "CREATE TEMP TRIGGER bridge_main_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value, source) VALUES(new.option_id, new.option_name, new.option_value, 'temp'); INSERT INTO main.wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); END", $root + 4),
];

$siteTriggerRecords = static fn (int $root = 60): array => [
    $record85('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', $root + 1),
    $record85('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, new_value text, source text)', $root + 2),
    $record85('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, option_name, new_value, source) VALUES(new.blog_id, new.option_name, new.option_value, 'site'); SELECT blog_id FROM wp_option_audit WHERE blog_id = new.blog_id; END", $root + 3),
];

$archiveTriggerRecords = static fn (int $root = 70): array => [
    $record85('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', $root + 1),
    $record85('trigger', 'archive_cleanup', 'wp_options', 0, "CREATE TRIGGER archive.archive_cleanup AFTER DELETE ON wp_options BEGIN SELECT old.blog_id FROM wp_options WHERE blog_id = old.blog_id; END", $root + 2),
];

$states85 = static fn (): array => [
    'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 13],
    'temp' => ['schema_cookie' => 4],
    'site' => ['schema_cookie' => 8, 'wal_frames' => [['page' => 1, 'schema_cookie' => 9, 'commit' => true]]],
    'archive' => ['schema_cookie' => 5, 'wal_frames' => [['page' => 2, 'schema_cookie' => 99, 'commit' => true]]],
];

$plan85 = static fn (array $triggers, array $next = [], ?array $states = null): array => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan(
    $catalog85(),
    $triggers,
    $next,
    $states ?? $states85(),
);

$stable85 = static fn (): array => $plan85([
    ['name' => 'main.options_au', 'source' => 'main', 'active' => true],
    ['name' => 'archive.archive_cleanup', 'source' => 'archive'],
]);
$mainChanged85 = static fn (): array => $plan85([
    ['name' => 'main.options_ai', 'source' => 'main', 'active' => true],
    ['name' => 'main.options_au', 'source' => 'main'],
], ['main' => $mainTriggerRecords(40)]);
$tempChanged85 = static fn (): array => $plan85([
    ['name' => 'options_ai', 'source' => 'temp', 'active' => true],
    ['name' => 'main.options_ai', 'source' => 'main'],
    ['name' => 'bridge_main_ai', 'source' => 'temp'],
], ['temp' => $tempTriggerRecords(50)]);
$siteChanged85 = static fn (): array => $plan85([
    ['name' => 'site.site_options_ai', 'source' => 'site', 'active' => true],
    ['name' => 'archive.archive_cleanup', 'source' => 'archive'],
], ['site' => $siteTriggerRecords(60)]);
$unrelatedArchive85 = static fn (): array => $plan85([
    ['name' => 'main.options_ai', 'source' => 'main', 'active' => true],
], ['archive' => $archiveTriggerRecords(70)]);

$value85 = static function (array $data, string $path): mixed {
    $cursor = $data;
    $parts = explode('.', $path);
    while ($parts !== []) {
        if (!is_array($cursor)) {
            return null;
        }
        for ($length = count($parts); $length >= 1; $length--) {
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

$pathCases85 = [
    'stable status' => [$stable85, 'status', 'trigger_cache_stable'],
    'stable operation' => [$stable85, 'operation', 'attach-temp-schema-trigger-cache-reprepare'],
    'stable source main' => [$stable85, 'source_schema', 'main'],
    'stable trigger count' => [$stable85, 'trigger_count', 2],
    'stable active count' => [$stable85, 'active_trigger_count', 1],
    'stable no reprepare' => [$stable85, 'requires_reprepare', false],
    'stable triggers list' => [$stable85, 'stable_triggers', ['main.options_au', 'archive.archive_cleanup']],
    'stable current snapshot empty' => [$stable85, 'active_current_snapshot_triggers', []],
    'stable archive source tracked' => [$stable85, 'source_schemas.archive.archive_cleanup', 'archive'],
    'stable wal sources preserved' => [$stable85, 'wal_schema_cookie_sources', ['main', 'site', 'archive']],
    'stable site page one cookie' => [$stable85, 'schema_cookies_next.site', 9],
    'stable dependency marker' => [$stable85, 'dependencies.0', 'sqlite-attach-temp-schema-trigger-cache-reprepare'],

    'main change status expired' => [$mainChanged85, 'status', 'trigger_cache_expired'],
    'main change active current source' => [$mainChanged85, 'active_current_snapshot_triggers', ['main.options_ai']],
    'main change reset schema list' => [$mainChanged85, 'reset_schema_triggers', ['main.options_ai']],
    'main change inactive next step' => [$mainChanged85, 'next_step_schema_triggers', ['main.options_au']],
    'main change reprepare both' => [$mainChanged85, 'reprepare_triggers', ['main.options_ai', 'main.options_au']],
    'main change active action' => [$mainChanged85, 'triggers.main.options_ai.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'main change active current result ok' => [$mainChanged85, 'triggers.main.options_ai.current_step_result', 'SQLITE_OK'],
    'main change inactive result schema' => [$mainChanged85, 'triggers.main.options_au.current_step_result', 'SQLITE_SCHEMA'],
    'main change audit columns after' => [$mainChanged85, 'triggers.main.options_ai.after.target.columns', ['option_id', 'option_name', 'option_value', 'autoload', 'migrated']],
    'main change body dependency changed' => [$mainChanged85, 'triggers.main.options_ai.body_dependencies_changed', true],

    'temp change active current source' => [$tempChanged85, 'active_current_snapshot_triggers', ['options_ai']],
    'temp change main trigger stable' => [$tempChanged85, 'stable_triggers', ['main.options_ai']],
    'temp change inactive bridge next step' => [$tempChanged85, 'next_step_schema_triggers', ['bridge_main_ai']],
    'temp change unqualified trigger before schema temp' => [$tempChanged85, 'triggers.options_ai.before.schema', 'temp'],
    'temp change unqualified target after temp' => [$tempChanged85, 'triggers.options_ai.after.target.schema', 'temp'],
    'temp change qualified main target stable' => [$tempChanged85, 'triggers.main.options_ai.requires_reprepare', false],
    'temp bridge touches main dependency' => [$tempChanged85, 'triggers.bridge_main_ai.after.body_dependencies.1.schema', 'main'],
    'temp bridge source tracked' => [$tempChanged85, 'source_schemas.bridge_main_ai', 'temp'],

    'site change reprepare site only' => [$siteChanged85, 'reprepare_triggers', ['site.site_options_ai']],
    'site change archive stable' => [$siteChanged85, 'stable_triggers', ['archive.archive_cleanup']],
    'site change active reset' => [$siteChanged85, 'reset_schema_triggers', ['site.site_options_ai']],
    'site change action' => [$siteChanged85, 'triggers.site.site_options_ai.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'site change after target schema' => [$siteChanged85, 'triggers.site.site_options_ai.after.target.schema', 'site'],
    'site change changed schemas' => [$siteChanged85, 'changed_schemas', ['main', 'site']],

    'unrelated archive stable status' => [$unrelatedArchive85, 'status', 'trigger_cache_stable'],
    'unrelated archive no reprepare' => [$unrelatedArchive85, 'requires_reprepare', false],
    'unrelated archive stable active main' => [$unrelatedArchive85, 'stable_triggers', ['main.options_ai']],
    'unrelated archive schema update recorded' => [$unrelatedArchive85, 'schema_record_updates', ['archive']],
    'unrelated archive changed schemas include wal cookies' => [$unrelatedArchive85, 'changed_schemas', ['archive', 'main', 'site']],
];

foreach ($pathCases85 as $name => [$factory, $path, $expected]) {
    $tests['attach temp schema trigger cache reprepare ' . $name] = static function (TestRunner $t) use ($factory, $value85, $path, $expected): void {
        $t->same($expected, $value85($factory(), $path));
    };
}

$predicateCases85 = [
    'main active trigger keeps old source until reset' => static fn (): bool => $mainChanged85()['triggers']['main.options_ai']['current_source_kept_until_reset'] === true,
    'main inactive trigger does not keep source' => static fn (): bool => $mainChanged85()['triggers']['main.options_au']['current_source_kept_until_reset'] === false,
    'main active trigger sql changes' => static fn (): bool => $mainChanged85()['triggers']['main.options_ai']['before']['sql'] !== $mainChanged85()['triggers']['main.options_ai']['after']['sql'],
    'main update trigger target columns change' => static fn (): bool => $mainChanged85()['triggers']['main.options_au']['target_changed'] === true,
    'temp active trigger remains temporary' => static fn (): bool => $tempChanged85()['triggers']['options_ai']['after']['temporary'] === true,
    'temp active trigger changed by temp schema only' => static fn (): bool => $tempChanged85()['triggers']['options_ai']['source_schema'] === 'temp',
    'qualified main trigger ignores temp replacement' => static fn (): bool => $tempChanged85()['triggers']['main.options_ai']['trigger_changed'] === false,
    'temp bridge trigger SQL changes' => static fn (): bool => $tempChanged85()['triggers']['bridge_main_ai']['trigger_changed'] === true,
    'site active trigger source is attached' => static fn (): bool => $siteChanged85()['triggers']['site.site_options_ai']['source_schema'] === 'site',
    'archive unrelated trigger remains unchanged' => static fn (): bool => $siteChanged85()['triggers']['archive.archive_cleanup']['trigger_changed'] === false,
    'unrelated archive replacement does not expire main' => static fn (): bool => $unrelatedArchive85()['triggers']['main.options_ai']['requires_reprepare'] === false,
    'committed page one wal cookie is next source' => static fn (): bool => $stable85()['schema_cookies_next']['site'] === 9,
    'uncommitted page one wal cookie ignored' => static function () use ($plan85, $states85): bool {
        $states = $states85();
        $states['site']['wal_frames'] = [['page' => 1, 'schema_cookie' => 44, 'commit' => false]];
        return $plan85([['name' => 'site.site_options_ai', 'source' => 'site']], [], $states)['schema_cookies_next']['site'] === 8;
    },
    'committed non page one wal cookie ignored' => static function () use ($plan85, $states85): bool {
        $states = $states85();
        $states['archive']['wal_frames'] = [['page' => 2, 'schema_cookie' => 44, 'commit' => true]];
        return $plan85([['name' => 'archive.archive_cleanup', 'source' => 'archive']], [], $states)['schema_cookies_next']['archive'] === 5;
    },
    'explicit wal cookie wins over frame' => static function () use ($plan85, $states85): bool {
        $states = $states85();
        $states['site']['wal_schema_cookie'] = 55;
        return $plan85([['name' => 'site.site_options_ai', 'source' => 'site']], [], $states)['schema_cookies_next']['site'] === 55;
    },
    'quoted attached trigger source is normalized' => static function () use ($catalog85): bool {
        $plan = SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), [['name' => 'site.site_options_ai', 'source' => '"site"']]);
        return $plan['source_schemas']['site.site_options_ai'] === 'site';
    },
];

foreach ($predicateCases85 as $name => $predicate) {
    $tests['attach temp schema trigger cache reprepare ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

$errorCases85 = [
    'rejects empty prepared trigger list' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), []),
    'rejects missing trigger name' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), [['source' => 'main']]),
    'rejects empty trigger name' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), [['name' => '']]),
    'rejects missing trigger' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), [['name' => 'missing_trigger']]),
    'rejects missing source schema' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), [['name' => 'options_ai', 'source' => 'missing']]),
    'rejects replacement missing schema' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), [['name' => 'options_ai']], ['missing' => []]),
    'rejects non integer schema cookie' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), [['name' => 'options_ai']], [], ['main' => ['schema_cookie' => '12']]),
    'rejects non integer wal frame page' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), [['name' => 'options_ai']], [], ['main' => ['schema_cookie' => 12, 'wal_frames' => [['page' => '1']]]]),
    'rejects non integer wal schema cookie' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan($catalog85(), [['name' => 'options_ai']], [], ['main' => ['schema_cookie' => 12, 'wal_schema_cookie' => '13']]),
];

foreach ($errorCases85 as $name => $callback) {
    $tests['attach temp schema trigger cache reprepare ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
