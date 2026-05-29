<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, source text, old_value text, new_value text)', 2),
        $record('trigger', 'options_after_update', 'wp_options', 0, "CREATE TRIGGER options_after_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value) VALUES(new.option_id, 'main', old.option_value, new.option_value); END", 3),
        $record('trigger', 'options_after_delete', 'wp_options', 0, "CREATE TRIGGER options_after_delete AFTER DELETE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value) VALUES(old.option_id, 'main-delete', old.option_value, NULL); END", 4),
    ], [
        $record('table', 'wp_option_audit', 'wp_option_audit', 10, 'CREATE TEMP TABLE wp_option_audit(option_id integer, source text, old_value text, new_value text)', 5),
        $record('trigger', 'temp_options_after_update', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_after_update AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value) VALUES(new.option_id, 'temp', old.option_value, new.option_value); END", 6),
    ]);
    $catalog->attach('site', '/srv/wp/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE wp_options(blog_id integer, option_name text, option_value text)', 7),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE wp_option_audit(blog_id integer, source text, old_value text, new_value text)', 8),
        $record('trigger', 'site_options_after_update', 'wp_options', 0, "CREATE TRIGGER site_options_after_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, source, old_value, new_value) VALUES(new.blog_id, 'site', old.option_value, new.option_value); END", 9),
    ]);

    return $catalog;
};

$mainNext = static fn (int $root) => [
    $record('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text, touched integer)', 30 + $root),
    $record('table', 'wp_option_audit', 'wp_option_audit', $root + 1, 'CREATE TABLE wp_option_audit(option_id integer, source text, old_value text, new_value text, request_id text)', 31 + $root),
    $record('trigger', 'options_after_update', 'wp_options', 0, "CREATE TRIGGER options_after_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value, request_id) VALUES(new.option_id, 'main-next', old.option_value, new.option_value, 'next'); END", 32 + $root),
    $record('trigger', 'options_after_delete', 'wp_options', 0, "CREATE TRIGGER options_after_delete AFTER DELETE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value, request_id) VALUES(old.option_id, 'main-delete-next', old.option_value, NULL, 'next'); END", 33 + $root),
];
$tempNext = static fn (int $root) => [
    $record('table', 'wp_option_audit', 'wp_option_audit', $root, 'CREATE TEMP TABLE wp_option_audit(option_id integer, source text, old_value text, new_value text, request_id text)', 40 + $root),
    $record('trigger', 'temp_options_after_update', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_after_update AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value, request_id) VALUES(new.option_id, 'temp-next', old.option_value, new.option_value, 'next'); END", 41 + $root),
];
$siteNext = static fn (int $root) => [
    $record('table', 'wp_options', 'wp_options', $root, 'CREATE TABLE wp_options(blog_id integer, option_name text, option_value text, migrated integer)', 50 + $root),
    $record('table', 'wp_option_audit', 'wp_option_audit', $root + 1, 'CREATE TABLE wp_option_audit(blog_id integer, source text, old_value text, new_value text, request_id text)', 51 + $root),
    $record('trigger', 'site_options_after_update', 'wp_options', 0, "CREATE TRIGGER site_options_after_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, source, old_value, new_value, request_id) VALUES(new.blog_id, 'site-next', old.option_value, new.option_value, 'next'); END", 52 + $root),
];

$states = static fn (): array => [
    'main' => ['schema_cookie' => 11, 'wal_schema_cookie' => 12],
    'temp' => ['schema_cookie' => 4],
    'site' => ['schema_cookie' => 7, 'wal_frames' => [['page' => 1, 'schema_cookie' => 8, 'commit' => true]]],
];
$plan = static fn (array $updates = [], array $triggers = ['options_after_update', 'temp_options_after_update', 'site.site_options_after_update'], ?array $schemaStates = null, string $source = 'main'): array => SQLiteAttachWalTempViewCachePlan::triggerProgramCacheCurrentSourceNext(
    $catalog(),
    $triggers,
    $updates,
    $schemaStates ?? $states(),
    $source,
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
    'status planned' => [[], 'status', 'planned'],
    'operation names slice' => [[], 'operation', 'attach-temp-wal-trigger-cache-current-source'],
    'source defaults main' => [[], 'source_schema', 'main'],
    'trigger count' => [[], 'trigger_count', 3],
    'current programs are kept' => [[], 'active_current_programs_kept', true],
    'stable requires no reprepare' => [[], 'requires_reprepare', false],
    'stable no reprepare triggers' => [[], 'reprepare_triggers', []],
    'stable changed schemas from wal cookies' => [[], 'changed_schemas', ['main', 'site']],
    'stable current main cookie' => [[], 'schema_cookies_current.main', 11],
    'stable next main cookie' => [[], 'schema_cookies_next.main', 12],
    'stable next site cookie' => [[], 'schema_cookies_next.site', 8],
    'stable wal sources' => [[], 'wal_schema_cookie_sources', ['main', 'site']],
    'main before trigger schema' => [[], 'triggers.options_after_update.before.trigger.schema', 'main'],
    'main before trigger rowid' => [[], 'triggers.options_after_update.before.trigger.rowid', 3],
    'main before target root' => [[], 'triggers.options_after_update.before.target.rootpage', 2],
    'main before audit dependency stays in main schema' => [[], 'triggers.options_after_update.before.body_dependencies.wp_option_audit.schema', 'main'],
    'temp trigger schema' => [[], 'triggers.temp_options_after_update.before.trigger.schema', 'temp'],
    'temp target remains main table' => [[], 'triggers.temp_options_after_update.before.target.schema', 'main'],
    'site trigger target root' => [[], 'triggers.site.site_options_after_update.before.target.rootpage', 20],
    'stable main trigger unchanged' => [[], 'triggers.options_after_update.trigger_changed', false],
    'stable main target unchanged' => [[], 'triggers.options_after_update.target_changed', false],
    'stable main body unchanged' => [[], 'triggers.options_after_update.body_dependencies_changed', false],

    'main update schema record list' => [['main' => $mainNext(60)], 'schema_record_updates', ['main']],
    'main update requires reprepare' => [['main' => $mainNext(60)], 'requires_reprepare', true],
    'main update reprepare trigger' => [['main' => $mainNext(60)], 'reprepare_triggers', ['options_after_update', 'temp_options_after_update']],
    'main update target changed trigger' => [['main' => $mainNext(60)], 'target_changed_triggers', ['options_after_update', 'temp_options_after_update']],
    'main update body changed trigger' => [['main' => $mainNext(60)], 'body_changed_triggers', ['options_after_update']],
    'main update before sql preserved' => [['main' => $mainNext(60)], 'triggers.options_after_update.before.trigger.sql', "CREATE TRIGGER options_after_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value) VALUES(new.option_id, 'main', old.option_value, new.option_value); END"],
    'main update after sql changed' => [['main' => $mainNext(60)], 'triggers.options_after_update.after.trigger.sql', "CREATE TRIGGER options_after_update AFTER UPDATE ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, source, old_value, new_value, request_id) VALUES(new.option_id, 'main-next', old.option_value, new.option_value, 'next'); END"],
    'main update after target root' => [['main' => $mainNext(60)], 'triggers.options_after_update.after.target.rootpage', 60],
    'main update temp trigger reprepares for main target root' => [['main' => $mainNext(60)], 'triggers.temp_options_after_update.next_requires_reprepare', true],
    'main update site trigger does not reprepare' => [['main' => $mainNext(60)], 'triggers.site.site_options_after_update.next_requires_reprepare', false],

    'temp update reprepare trigger' => [['temp' => $tempNext(70)], 'reprepare_triggers', ['temp_options_after_update']],
    'temp update body changed trigger list' => [['temp' => $tempNext(70)], 'body_changed_triggers', ['temp_options_after_update']],
    'temp update main trigger target unchanged' => [['temp' => $tempNext(70)], 'triggers.options_after_update.target_changed', false],
    'temp update main trigger body ignores temp audit shadow' => [['temp' => $tempNext(70)], 'triggers.options_after_update.body_dependencies_changed', false],
    'temp update temp trigger changed' => [['temp' => $tempNext(70)], 'triggers.temp_options_after_update.trigger_changed', true],
    'temp update temp audit after root' => [['temp' => $tempNext(70)], 'triggers.temp_options_after_update.after.body_dependencies.wp_option_audit.rootpage', 70],
    'temp update site trigger stable' => [['temp' => $tempNext(70)], 'triggers.site.site_options_after_update.next_requires_reprepare', false],

    'site update reprepare only site trigger' => [['site' => $siteNext(80)], 'reprepare_triggers', ['site.site_options_after_update']],
    'site update target changed only site trigger' => [['site' => $siteNext(80)], 'target_changed_triggers', ['site.site_options_after_update']],
    'site update main trigger stable' => [['site' => $siteNext(80)], 'triggers.options_after_update.next_requires_reprepare', false],
    'site update temp trigger stable' => [['site' => $siteNext(80)], 'triggers.temp_options_after_update.next_requires_reprepare', false],
    'site update after target root' => [['site' => $siteNext(80)], 'triggers.site.site_options_after_update.after.target.rootpage', 80],

    'main delete trigger can be tracked' => [['main' => $mainNext(90)], 'triggers.options_after_delete.next_requires_reprepare', true, ['options_after_delete']],
    'missing trigger after update is recorded' => [['main' => [$record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text)', 91)]], 'missing_triggers_next', ['options_after_update'], ['options_after_update']],
    'quoted attached source accepted' => [[], 'source_schema', 'site', ['site.site_options_after_update'], null, '"site"'],
];

foreach ($pathCases as $name => $case) {
    $tests['attach temp wal trigger cache current source ' . $name] = static function (TestRunner $t) use ($plan, $value, $case): void {
        [$updates, $path, $expected] = $case;
        $triggers = $case[3] ?? ['options_after_update', 'temp_options_after_update', 'site.site_options_after_update'];
        $states = $case[4] ?? null;
        $source = $case[5] ?? 'main';
        $t->same($expected, $value($plan($updates, $triggers, $states, $source), $path));
    };
}

$predicateCases = [
    'combined main temp updates reprepare stable order' => static fn (): bool => $plan(['main' => $mainNext(100), 'temp' => $tempNext(110)])['reprepare_triggers'] === ['options_after_update', 'temp_options_after_update'],
    'combined main site updates reprepare temp trigger for main target' => static fn (): bool => $plan(['main' => $mainNext(120), 'site' => $siteNext(130)])['triggers']['temp_options_after_update']['next_requires_reprepare'] === true,
    'current program kept even when next trigger missing' => static fn (): bool => $plan(['main' => [$record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text)', 140)]], ['options_after_update'])['triggers']['options_after_update']['current_program_kept'] === true,
    'uncommitted page one frame does not change cookie' => static function () use ($plan): bool {
        $states = ['main' => ['schema_cookie' => 1, 'wal_frames' => [['page' => 1, 'schema_cookie' => 2, 'commit' => false]]]];
        return $plan([], ['options_after_update'], $states)['schema_cookies_next']['main'] === 1;
    },
    'non page one frame does not change cookie' => static function () use ($plan): bool {
        $states = ['main' => ['schema_cookie' => 1, 'wal_frames' => [['page' => 2, 'schema_cookie' => 9, 'commit' => true]]]];
        return $plan([], ['options_after_update'], $states)['schema_cookies_next']['main'] === 1;
    },
    'explicit wal schema cookie wins' => static function () use ($plan): bool {
        $states = ['main' => ['schema_cookie' => 1, 'wal_schema_cookie' => 5, 'wal_frames' => [['page' => 1, 'schema_cookie' => 2, 'commit' => true]]]];
        return $plan([], ['options_after_update'], $states)['schema_cookies_next']['main'] === 5;
    },
    'empty trigger list is rejected' => static function () use ($plan): bool {
        try {
            $plan([], []);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
    'empty trigger name is rejected' => static function () use ($plan): bool {
        try {
            $plan([], ['']);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
    'bad source schema is rejected' => static function () use ($plan): bool {
        try {
            $plan([], ['options_after_update'], null, 'missing');
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
    'bad replacement schema is rejected' => static function () use ($plan, $mainNext): bool {
        try {
            $plan(['missing' => $mainNext(150)]);
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    },
    'dependencies include trigger cache marker' => static fn (): bool => in_array('sqlite-trigger-program-cache-reprepare', $plan()['dependencies'], true),
    'dependencies include wal cookie marker' => static fn (): bool => in_array('sqlite-wal-page-one-schema-cookie', $plan()['dependencies'], true),
];

foreach ($predicateCases as $name => $predicate) {
    $tests['attach temp wal trigger cache current source ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

return $tests;
