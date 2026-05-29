<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record90 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current90 = static function () use ($record90): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record90('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record90('table', 'wp_audit', 'wp_audit', 3, 'CREATE TABLE main.wp_audit(option_id integer, option_name text, source text)', 2),
        $record90('trigger', 'options_ai', 'wp_options', 0, "CREATE TRIGGER main.options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'main'); END", 3),
        $record90('trigger', 'options_au', 'wp_options', 0, "CREATE TRIGGER main.options_au AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(old.option_id, new.option_name, 'update'); END", 4),
    ], [
        $record90('table', 'wp_temp_options', 'wp_temp_options', 10, 'CREATE TEMP TABLE wp_temp_options(option_id integer, option_name text, option_value text)', 5),
        $record90('table', 'wp_audit', 'wp_audit', 11, 'CREATE TEMP TABLE wp_audit(option_id integer, option_name text, source text)', 6),
        $record90('trigger', 'temp_bridge_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_bridge_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp'); INSERT INTO main.wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'main'); END", 7),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record90('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 8),
        $record90('table', 'wp_audit', 'wp_audit', 21, 'CREATE TABLE site.wp_audit(blog_id integer, option_name text, source text)', 9),
        $record90('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site'); END", 10),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record90('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text)', 11),
        $record90('trigger', 'archive_cleanup', 'wp_options', 0, "CREATE TRIGGER archive.archive_cleanup AFTER DELETE ON wp_options BEGIN SELECT old.blog_id FROM wp_options WHERE blog_id = old.blog_id; END", 12),
    ]);

    return $catalog;
};

$next90 = static function () use ($record90): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record90('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, migrated integer)', 1),
        $record90('table', 'wp_audit', 'wp_audit', 3, 'CREATE TABLE main.wp_audit(option_id integer, option_name text, source text, migrated integer)', 2),
        $record90('trigger', 'options_ai', 'wp_options', 0, "CREATE TRIGGER main.options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source, migrated) VALUES(new.option_id, new.option_name, 'main-next', 1); END", 3),
        $record90('trigger', 'options_au', 'wp_options', 0, "CREATE TRIGGER main.options_au AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source, migrated) VALUES(old.option_id, new.option_name, 'update', 1); END", 4),
    ], [
        $record90('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, expires integer)', 5),
        $record90('table', 'wp_audit', 'wp_audit', 11, 'CREATE TEMP TABLE wp_audit(option_id integer, option_name text, source text, expires integer)', 6),
        $record90('trigger', 'options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-shadow'); END", 7),
        $record90('trigger', 'temp_bridge_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_bridge_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-next'); INSERT INTO main.wp_audit(option_id, option_name, source, migrated) VALUES(new.option_id, new.option_name, 'main-next', 1); END", 8),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record90('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', 9),
        $record90('table', 'wp_audit', 'wp_audit', 21, 'CREATE TABLE site.wp_audit(blog_id integer, option_name text, source text, migrated integer)', 10),
        $record90('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(blog_id, option_name, source, migrated) VALUES(new.blog_id, new.option_name, 'site-next', 1); END", 11),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record90('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(blog_id integer, option_name text, option_value text)', 12),
        $record90('trigger', 'archive_cleanup', 'wp_options', 0, "CREATE TRIGGER archive.archive_cleanup AFTER DELETE ON wp_options BEGIN SELECT old.blog_id FROM wp_options WHERE blog_id = old.blog_id; END", 13),
    ]);

    return $catalog;
};

$states90 = static fn (): array => [
    'main' => ['schema_cookie' => 20, 'wal_schema_cookie' => 22],
    'temp' => ['schema_cookie' => 4],
    'site' => ['schema_cookie' => 8, 'wal_frames' => [['page' => 1, 'schema_cookie' => 9, 'commit' => true]]],
    'archive' => ['schema_cookie' => 6, 'wal_frames' => [['page' => 2, 'schema_cookie' => 44, 'commit' => true]]],
];

$prepared90 = [
    ['name' => 'options_ai', 'active' => true, 'statement' => 'INSERT INTO wp_options(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'main.options_ai'],
    ['name' => 'main.options_au'],
    ['name' => 'temp_bridge_ai', 'active' => true],
    ['name' => 'site.site_options_ai', 'active' => true],
    ['name' => 'archive.archive_cleanup'],
];

$plan90 = static fn (?array $prepared = null, ?array $states = null): array => SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan(
    $current90(),
    $next90(),
    $prepared ?? $prepared90,
    $states ?? $states90(),
);

$value90 = static function (array $data, string $path): mixed {
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

$cases90 = [
    'status expired' => ['status', 'trigger_current_source_expired'],
    'operation marker' => ['operation', 'attach-temp-wal-schema-trigger-source-reprepare'],
    'trigger count' => ['trigger_count', 6],
    'active trigger count' => ['active_trigger_count', 3],
    'requires reprepare' => ['requires_reprepare', true],
    'reprepare trigger order' => ['reprepare_triggers', ['options_ai', 'main.options_ai', 'main.options_au', 'temp_bridge_ai', 'site.site_options_ai']],
    'stable trigger order' => ['stable_triggers', ['archive.archive_cleanup']],
    'active current snapshot triggers' => ['active_current_snapshot_triggers', ['options_ai', 'temp_bridge_ai', 'site.site_options_ai']],
    'reset schema triggers' => ['reset_schema_triggers', ['options_ai', 'temp_bridge_ai', 'site.site_options_ai']],
    'next step schema triggers' => ['next_step_schema_triggers', ['main.options_ai', 'main.options_au']],
    'changed schemas ordered' => ['changed_schemas', ['temp', 'main', 'site']],
    'wal schemas include committed cookie sources' => ['wal_schemas', ['main', 'archive', 'site']],
    'temp schemas only temp' => ['temp_schemas', ['temp']],
    'attached schemas only site' => ['attached_schemas', ['site']],
    'current main cookie' => ['schema_cookies_current.main', 20],
    'next main cookie' => ['schema_cookies_next.main', 22],
    'current site cookie' => ['schema_cookies_current.site', 8],
    'next site cookie from committed frame' => ['schema_cookies_next.site', 9],
    'archive non page one cookie ignored' => ['schema_cookies_next.archive', 6],
    'wal cookie sources' => ['wal_schema_cookie_sources', ['main', 'site', 'archive']],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-temp-wal-schema-trigger-source-reprepare'],
    'dependency current source reset' => ['dependencies.1', 'sqlite-prepared-trigger-current-source-reset'],
    'dependency temp shadow' => ['dependencies.2', 'sqlite-temp-trigger-shadow-resolution'],
    'dependency wal cookie' => ['dependencies.3', 'sqlite-wal-page-one-schema-cookie'],
    'unqualified current trigger schema main' => ['triggers.options_ai.current.triggerSchema', 'main'],
    'unqualified next trigger schema temp' => ['triggers.options_ai.next.triggerSchema', 'temp'],
    'unqualified target moves to temp' => ['triggers.options_ai.next.targetSchema', 'temp'],
    'unqualified changed fields' => ['triggers.options_ai.changed_fields', ['triggerSchema', 'triggerTemporary', 'targetSchema', 'targetTemporary', 'columns']],
    'unqualified active action' => ['triggers.options_ai.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'unqualified current step ok' => ['triggers.options_ai.current_step_result', 'SQLITE_OK'],
    'unqualified keeps current source until reset' => ['triggers.options_ai.current_source_kept_until_reset', true],
    'unqualified invalidated temp main' => ['triggers.options_ai.invalidated_sources', ['main', 'temp']],
    'unqualified temp schema list' => ['triggers.options_ai.temp_schemas', ['temp']],
    'unqualified wal schema list' => ['triggers.options_ai.wal_schemas', ['main']],
    'qualified main current schema' => ['triggers.main.options_ai.current.triggerSchema', 'main'],
    'qualified main next schema still main' => ['triggers.main.options_ai.next.triggerSchema', 'main'],
    'qualified main result schema' => ['triggers.main.options_ai.current_step_result', 'SQLITE_SCHEMA'],
    'qualified main action next step' => ['triggers.main.options_ai.next_step_action', 'sqlite_schema_on_next_step'],
    'qualified main does not keep current source' => ['triggers.main.options_ai.current_source_kept_until_reset', false],
    'qualified update target columns changed' => ['triggers.main.options_au.changed_fields', ['columns']],
    'qualified update body changed' => ['triggers.main.options_au.changed', true],
    'temp bridge remains temp trigger' => ['triggers.temp_bridge_ai.current.triggerSchema', 'temp'],
    'temp bridge target stays main' => ['triggers.temp_bridge_ai.next.targetSchema', 'main'],
    'temp bridge invalidates main temp' => ['triggers.temp_bridge_ai.invalidated_sources', ['main', 'temp']],
    'temp bridge active action' => ['triggers.temp_bridge_ai.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'site trigger current schema' => ['triggers.site.site_options_ai.current.triggerSchema', 'site'],
    'site trigger changed fields' => ['triggers.site.site_options_ai.changed_fields', ['columns']],
    'site trigger attached schemas' => ['triggers.site.site_options_ai.attached_schemas', ['site']],
    'site trigger active result ok' => ['triggers.site.site_options_ai.current_step_result', 'SQLITE_OK'],
    'archive trigger stable action' => ['triggers.archive.archive_cleanup.next_step_action', 'reuse_prepared_trigger'],
    'archive trigger stable result' => ['triggers.archive.archive_cleanup.current_step_result', 'SQLITE_OK'],
    'archive trigger no invalidation' => ['triggers.archive.archive_cleanup.invalidated_sources', []],
];

$tests = [];
foreach ($cases90 as $name => [$path, $expected]) {
    $tests['attach temp wal schema trigger current source next90 ' . $name] = static function (TestRunner $t) use ($plan90, $value90, $path, $expected): void {
        $t->same($expected, $value90($plan90(), $path));
    };
}

$predicateCases90 = [
    'unqualified trigger shadow changes source' => static fn (): bool => $plan90()['triggers']['options_ai']['current']['triggerSchema'] !== $plan90()['triggers']['options_ai']['next']['triggerSchema'],
    'qualified main ignores temp trigger shadow name' => static fn (): bool => $plan90()['triggers']['main.options_ai']['next']['triggerSchema'] === 'main',
    'site trigger remains attached source' => static fn (): bool => $plan90()['triggers']['site.site_options_ai']['next']['targetSchema'] === 'site',
    'archive cleanup is reusable despite archive non schema wal frame' => static fn (): bool => $plan90()['triggers']['archive.archive_cleanup']['requires_reprepare'] === false,
    'explicit wal cookie wins over committed page one frame' => static function () use ($plan90, $states90): bool {
        $states = $states90();
        $states['site']['wal_schema_cookie'] = 19;
        return $plan90(null, $states)['schema_cookies_next']['site'] === 19;
    },
    'uncommitted page one wal frame ignored' => static function () use ($plan90, $states90): bool {
        $states = $states90();
        $states['site']['wal_frames'] = [['page' => 1, 'schema_cookie' => 19, 'commit' => false]];
        return $plan90(null, $states)['schema_cookies_next']['site'] === 8;
    },
    'active false trigger reports schema on next step' => static fn (): bool => $plan90([['name' => 'main.options_ai', 'active' => false]])['next_step_schema_triggers'] === ['main.options_ai'],
    'active true trigger reports ok until reset' => static fn (): bool => $plan90([['name' => 'main.options_ai', 'active' => true]])['active_current_snapshot_triggers'] === ['main.options_ai'],
    'stable only plan reports stable status' => static fn (): bool => $plan90([['name' => 'archive.archive_cleanup']])['status'] === 'trigger_current_source_stable',
];

foreach ($predicateCases90 as $name => $predicate) {
    $tests['attach temp wal schema trigger current source next90 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

$errorCases90 = [
    'rejects empty prepared trigger list' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan($current90(), $next90(), []),
    'rejects missing trigger name' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan($current90(), $next90(), [['active' => true]]),
    'rejects empty trigger name' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan($current90(), $next90(), [['name' => '']]),
    'rejects missing current trigger' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan($current90(), $next90(), [['name' => 'missing_trigger']]),
    'rejects non integer schema cookie' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan($current90(), $next90(), [['name' => 'archive.archive_cleanup']], ['main' => ['schema_cookie' => '20']]),
    'rejects non integer wal frame page' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan($current90(), $next90(), [['name' => 'archive.archive_cleanup']], ['main' => ['schema_cookie' => 20, 'wal_frames' => [['page' => '1']]]]),
    'rejects non integer wal schema cookie' => static fn () => SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan($current90(), $next90(), [['name' => 'archive.archive_cleanup']], ['main' => ['schema_cookie' => 20, 'wal_schema_cookie' => '22']]),
];

foreach ($errorCases90 as $name => $callback) {
    $tests['attach temp wal schema trigger current source next90 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
