<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record104 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current104 = static function () use ($record104): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record104('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record104('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, source text)', 2),
        $record104('table', 'wp_option_meta', 'wp_option_meta', 4, 'CREATE TABLE main.wp_option_meta(option_id integer, meta_key text)', 3),
        $record104('trigger', 'temp_options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-current'); SELECT meta_key FROM wp_option_meta WHERE option_id = new.option_id; END", 4),
        $record104('trigger', 'main_options_ai', 'wp_options', 0, "CREATE TRIGGER main.main_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'main-current'); END", 5),
        $record104('trigger', 'main_options_meta', 'wp_options', 0, "CREATE TRIGGER main.main_options_meta AFTER UPDATE ON wp_options BEGIN SELECT meta_key FROM wp_option_meta WHERE option_id = new.option_id; END", 6),
    ], [
        $record104('trigger', 'stage_options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER stage_options_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'stage-current'); END", 7),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record104('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 8),
        $record104('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, source text)', 9),
        $record104('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site-current'); END", 10),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record104('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text)', 11),
        $record104('table', 'wp_option_audit', 'wp_option_audit', 31, 'CREATE TABLE archive.wp_option_audit(blog_id integer, option_name text, source text)', 12),
        $record104('trigger', 'archive_options_ai', 'wp_options', 0, "CREATE TRIGGER archive.archive_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO archive.wp_option_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'archive-current'); END", 13),
    ]);

    return $catalog;
};

$next104 = static function () use ($record104): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record104('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record104('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, source text)', 2),
        $record104('table', 'wp_option_meta', 'wp_option_meta', 4, 'CREATE TABLE main.wp_option_meta(option_id integer, meta_key text)', 3),
        $record104('trigger', 'temp_options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-current'); SELECT meta_key FROM wp_option_meta WHERE option_id = new.option_id; END", 4),
        $record104('trigger', 'main_options_ai', 'wp_options', 0, "CREATE TRIGGER main.main_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'main-current'); END", 5),
        $record104('trigger', 'main_options_meta', 'wp_options', 0, "CREATE TRIGGER main.main_options_meta AFTER UPDATE ON wp_options BEGIN SELECT meta_key FROM wp_option_meta WHERE option_id = new.option_id; END", 6),
    ], [
        $record104('table', 'wp_option_audit', 'wp_option_audit', 40, 'CREATE TEMP TABLE wp_option_audit(option_id integer, option_name text, source text, expires integer)', 7),
        $record104('table', 'wp_option_meta', 'wp_option_meta', 41, 'CREATE TEMP TABLE wp_option_meta(option_id integer, meta_key text, expires integer)', 8),
        $record104('trigger', 'stage_options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER stage_options_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'stage-current'); END", 9),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record104('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 10),
        $record104('table', 'wp_option_audit', 'wp_option_audit', 22, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, source text, migrated integer)', 11),
        $record104('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site-current'); END", 12),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record104('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text)', 13),
        $record104('table', 'wp_option_audit', 'wp_option_audit', 31, 'CREATE TABLE archive.wp_option_audit(blog_id integer, option_name text, source text)', 14),
        $record104('trigger', 'archive_options_ai', 'wp_options', 0, "CREATE TRIGGER archive.archive_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO archive.wp_option_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'archive-current'); END", 15),
    ]);

    return $catalog;
};

$states104 = static fn (): array => [
    'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 13],
    'temp' => ['schema_cookie' => 4],
    'site' => ['schema_cookie' => 8, 'wal_frames' => [['page' => 1, 'schema_cookie' => 9, 'commit' => true]]],
    'archive' => ['schema_cookie' => 6],
];

$prepared104 = static fn (): array => [
    ['name' => 'temp_options_ai', 'active' => true],
    ['name' => 'stage_options_ai'],
    ['name' => 'main.main_options_ai'],
    ['name' => 'main.main_options_meta'],
    ['name' => 'site.site_options_ai', 'active' => true],
    ['name' => 'archive.archive_options_ai'],
];

$plan104 = static fn (?array $prepared = null, ?array $states = null): array => SQLiteAttachTempWalSchemaTriggerPlan::triggerBodyDependencyRepreparePlan(
    $current104(),
    $next104(),
    $prepared ?? $prepared104(),
    $states ?? $states104(),
);

$value104 = static function (array $data, string $path): mixed {
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

$pathCases104 = [
    'operation marker' => ['operation', 'attach-temp-wal-schema-trigger-body-dependency-reprepare'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-temp-wal-schema-trigger-body-dependency-reprepare'],
    'retains next90 dependency' => ['dependencies.1', 'sqlite-attach-temp-wal-schema-trigger-source-reprepare'],
    'status expired' => ['status', 'trigger_body_dependency_expired'],
    'trigger count' => ['trigger_count', 6],
    'active trigger count' => ['active_trigger_count', 2],
    'requires reprepare' => ['requires_reprepare', true],
    'reprepare trigger order' => ['reprepare_triggers', ['temp_options_ai', 'stage_options_ai', 'site.site_options_ai']],
    'stable trigger order' => ['stable_triggers', ['main.main_options_ai', 'main.main_options_meta', 'archive.archive_options_ai']],
    'body expired triggers' => ['body_dependency_expired_triggers', ['temp_options_ai', 'stage_options_ai', 'site.site_options_ai']],
    'body stable triggers' => ['body_dependency_stable_triggers', ['main.main_options_ai', 'main.main_options_meta', 'archive.archive_options_ai']],
    'changed schemas include body dependencies' => ['changed_schemas', ['temp', 'main', 'site']],
    'wal schemas include main site' => ['wal_schemas', ['main', 'site']],
    'temp schema listed' => ['temp_schemas', ['temp']],
    'attached schema listed' => ['attached_schemas', ['site']],
    'current main cookie' => ['schema_cookies_current.main', 12],
    'next main cookie' => ['schema_cookies_next.main', 13],
    'site next cookie' => ['schema_cookies_next.site', 9],
    'wal cookie sources' => ['wal_schema_cookie_sources', ['main', 'site']],
    'temp active current snapshot' => ['active_current_snapshot_triggers', ['temp_options_ai', 'site.site_options_ai']],
    'temp active reset' => ['reset_schema_triggers', ['temp_options_ai', 'site.site_options_ai']],
    'inactive body dependency next step' => ['next_step_schema_triggers', ['stage_options_ai']],
    'temp body action' => ['triggers.temp_options_ai.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'stage body action' => ['triggers.stage_options_ai.next_step_action', 'sqlite_schema_before_next_trigger_body_step'],
    'stage current result' => ['triggers.stage_options_ai.current_step_result', 'SQLITE_SCHEMA'],
    'temp changed field marker' => ['triggers.temp_options_ai.changed_fields.0', 'bodyDependenciesResolved'],
    'stage changed field marker' => ['triggers.stage_options_ai.changed_fields.0', 'bodyDependenciesResolved'],
    'main trigger unchanged by body dependency' => ['triggers.main.main_options_ai.requires_reprepare', false],
    'main body resolution stable' => ['triggers.main.main_options_ai.body_dependency_resolution.changed', false],
    'main meta dependency stays main' => ['triggers.main.main_options_meta.body_dependency_resolution.current.0.resolved_schema', 'main'],
    'main meta dependency next still main' => ['triggers.main.main_options_meta.body_dependency_resolution.next.0.resolved_schema', 'main'],
    'temp audit current resolves main' => ['triggers.temp_options_ai.body_dependency_resolution.current.0.resolved_schema', 'main'],
    'temp audit next resolves temp' => ['triggers.temp_options_ai.body_dependency_resolution.next.0.resolved_schema', 'temp'],
    'temp meta current resolves main' => ['triggers.temp_options_ai.body_dependency_resolution.current.1.resolved_schema', 'main'],
    'temp meta next resolves temp' => ['triggers.temp_options_ai.body_dependency_resolution.next.1.resolved_schema', 'temp'],
    'temp audit next rootpage' => ['triggers.temp_options_ai.body_dependency_resolution.next.0.resolved_rootpage', 40],
    'stage body schema list' => ['body_dependency_schemas.stage_options_ai', ['temp', 'main']],
    'site body dependency explicit schema' => ['triggers.site.site_options_ai.body_dependency_resolution.current.0.resolved_schema', 'site'],
    'site body dependency root changed' => ['triggers.site.site_options_ai.body_dependency_resolution.changed', true],
    'site active result ok' => ['triggers.site.site_options_ai.current_step_result', 'SQLITE_OK'],
    'archive explicit dependency stable' => ['triggers.archive.archive_options_ai.body_dependency_resolution.changed', false],
    'archive explicit dependency schema' => ['body_dependency_schemas.archive.archive_options_ai', ['archive']],
];

$tests = [];
foreach ($pathCases104 as $name => [$path, $expected]) {
    $tests['attach temp wal schema trigger reprepare current source next104 ' . $name] = static function (TestRunner $t) use ($plan104, $value104, $path, $expected): void {
        $t->same($expected, $value104($plan104(), $path));
    };
}

$predicateCases104 = [
    'active temp body dependency keeps current source until reset' => static fn (): bool => $plan104()['triggers']['temp_options_ai']['current_source_kept_until_reset'] === true,
    'inactive temp body dependency reports schema before next body step' => static fn (): bool => $plan104()['triggers']['stage_options_ai']['next_step_action'] === 'sqlite_schema_before_next_trigger_body_step',
    'non temp trigger ignores temp body shadow for unqualified table' => static fn (): bool => $plan104()['triggers']['main.main_options_meta']['body_dependency_resolution']['changed'] === false,
    'qualified archive dependency does not follow temp shadow' => static fn (): bool => $plan104()['triggers']['archive.archive_options_ai']['body_dependency_resolution']['next'][0]['resolved_schema'] === 'archive',
    'stable only plan reports stable status' => static fn (): bool => $plan104([['name' => 'archive.archive_options_ai']])['status'] === 'trigger_body_dependency_stable',
    'stable only keeps dependency marker' => static fn (): bool => $plan104([['name' => 'archive.archive_options_ai']])['dependencies'][0] === 'sqlite-attach-temp-wal-schema-trigger-body-dependency-reprepare',
    'uncommitted wal schema cookie frame is ignored' => static function () use ($plan104, $states104): bool {
        $states = $states104();
        unset($states['main']['wal_schema_cookie']);
        $states['main']['wal_frames'] = [['page' => 1, 'schema_cookie' => 99, 'commit' => false]];
        $plan = $plan104([['name' => 'main.main_options_ai']], $states);
        return $plan['schema_cookies_next']['main'] === 12 && $plan['requires_reprepare'] === false;
    },
    'committed page one wal cookie is reported without body dependency expiry' => static function () use ($plan104, $states104): bool {
        $states = $states104();
        unset($states['main']['wal_schema_cookie']);
        $states['main']['wal_frames'] = [['page' => 1, 'schema_cookie' => 14, 'commit' => true]];
        $plan = $plan104([['name' => 'main.main_options_ai']], $states);
        return $plan['schema_cookies_next']['main'] === 14 && $plan['reprepare_triggers'] === [];
    },
    'missing current body dependency remains stable when still missing' => static function () use ($record104): bool {
        $current = new SQLiteAttachedSchemaCatalog([
            $record104('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer)', 1),
            $record104('trigger', 'main_missing', 'wp_options', 0, 'CREATE TRIGGER main.main_missing AFTER INSERT ON wp_options BEGIN SELECT * FROM wp_missing; END', 2),
        ]);
        $next = new SQLiteAttachedSchemaCatalog([
            $record104('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer)', 1),
            $record104('trigger', 'main_missing', 'wp_options', 0, 'CREATE TRIGGER main.main_missing AFTER INSERT ON wp_options BEGIN SELECT * FROM wp_missing; END', 2),
        ]);
        $plan = SQLiteAttachTempWalSchemaTriggerPlan::triggerBodyDependencyRepreparePlan($current, $next, [['name' => 'main.main_missing']]);
        return $plan['triggers']['main.main_missing']['body_dependency_resolution']['current'][0]['found'] === false
            && $plan['status'] === 'trigger_body_dependency_stable';
    },
    'missing temp dependency expires when next temp table appears' => static function () use ($record104): bool {
        $current = new SQLiteAttachedSchemaCatalog([
            $record104('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer)', 1),
        ], [
            $record104('trigger', 'temp_missing', 'wp_options', 0, 'CREATE TEMP TRIGGER temp_missing AFTER INSERT ON main.wp_options BEGIN SELECT * FROM wp_missing; END', 2),
        ]);
        $next = new SQLiteAttachedSchemaCatalog([
            $record104('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer)', 1),
        ], [
            $record104('table', 'wp_missing', 'wp_missing', 4, 'CREATE TEMP TABLE wp_missing(option_id integer)', 2),
            $record104('trigger', 'temp_missing', 'wp_options', 0, 'CREATE TEMP TRIGGER temp_missing AFTER INSERT ON main.wp_options BEGIN SELECT * FROM wp_missing; END', 3),
        ]);
        $plan = SQLiteAttachTempWalSchemaTriggerPlan::triggerBodyDependencyRepreparePlan($current, $next, [['name' => 'temp_missing']]);
        return $plan['triggers']['temp_missing']['body_dependency_resolution']['next'][0]['found'] === true
            && $plan['body_dependency_expired_triggers'] === ['temp_missing'];
    },
];

foreach ($predicateCases104 as $name => $predicate) {
    $tests['attach temp wal schema trigger reprepare current source next104 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

$errorCases104 = [
    'rejects empty trigger list' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerBodyDependencyRepreparePlan($current104(), $next104(), []),
    'rejects missing trigger name' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerBodyDependencyRepreparePlan($current104(), $next104(), [['active' => true]]),
    'rejects missing trigger record' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerBodyDependencyRepreparePlan($current104(), $next104(), [['name' => 'missing']]),
    'rejects bad schema cookie' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerBodyDependencyRepreparePlan($current104(), $next104(), [['name' => 'archive.archive_options_ai']], ['main' => ['schema_cookie' => '12']]),
];

foreach ($errorCases104 as $name => $callback) {
    $tests['attach temp wal schema trigger reprepare current source next104 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
