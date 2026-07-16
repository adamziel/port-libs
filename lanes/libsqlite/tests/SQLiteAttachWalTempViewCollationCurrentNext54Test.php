<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCollationPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text COLLATE NOCASE, option_value text COLLATE RTRIM, autoload text COLLATE BINARY)', 1),
            $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, audit_name text COLLATE RTRIM, option_name text COLLATE NOCASE, source text COLLATE BINARY)', 2),
            $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options(option_id, option_name, option_value) AS SELECT option_id, option_name COLLATE NOCASE, option_value COLLATE RTRIM FROM wp_options WHERE autoload = 'yes'", 3),
            $record('trigger', 'main_autoloaded_insert', 'autoloaded_options', 0, "CREATE TRIGGER main_autoloaded_insert INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); INSERT INTO wp_option_audit(option_id, audit_name, option_name, source) VALUES(new.option_id, new.option_name, new.option_name, 'main'); SELECT new.option_name COLLATE NOCASE, new.option_value COLLATE RTRIM; END", 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer primary key, temp_name text COLLATE RTRIM, option_value text COLLATE NOCASE)', 5),
            $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, temp_name text COLLATE RTRIM, source text COLLATE BINARY)', 6),
            $record('view', 'autoloaded_options', 'autoloaded_options', null, 'CREATE TEMP VIEW autoloaded_options(option_id, temp_name, option_value) AS SELECT option_id, temp_name COLLATE RTRIM, option_value COLLATE NOCASE FROM temp.wp_options', 7),
            $record('trigger', 'temp_autoloaded_insert', 'autoloaded_options', null, "CREATE TEMP TRIGGER temp_autoloaded_insert INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(option_id, temp_name, option_value) VALUES(new.option_id, new.temp_name, new.option_value); INSERT INTO wp_option_audit(option_id, temp_name, source) VALUES(new.option_id, new.temp_name, 'temp'); SELECT new.temp_name COLLATE RTRIM, new.option_value COLLATE NOCASE; END", 8),
            $record('trigger', 'temp_main_delete_bridge', 'wp_options', null, "CREATE TEMP TRIGGER temp_main_delete_bridge AFTER DELETE ON main.wp_options BEGIN DELETE FROM wp_option_audit WHERE temp_name COLLATE RTRIM = old.option_name COLLATE NOCASE; INSERT INTO main.wp_option_audit(option_id, audit_name, option_name, source) VALUES(old.option_id, old.option_name, old.option_name, 'delete'); END", 9),
        ],
    );

    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text COLLATE NOCASE, option_value text COLLATE BINARY)', 10),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text COLLATE NOCASE, source text COLLATE RTRIM)', 11),
        $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW site.autoloaded_options(blog_id, option_name, option_value) AS SELECT blog_id, option_name COLLATE NOCASE, option_value FROM wp_options', 12),
        $record('trigger', 'site_autoloaded_insert', 'autoloaded_options', 0, "CREATE TRIGGER site_autoloaded_insert INSTEAD OF INSERT ON autoloaded_options BEGIN INSERT INTO wp_options(blog_id, option_name, option_value) VALUES(new.blog_id, new.option_name, new.option_value); INSERT INTO site.wp_option_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site'); SELECT new.option_name COLLATE NOCASE, new.option_value; END", 13),
    ]);
    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options_archive', 'wp_options_archive', 30, 'CREATE TABLE archive.wp_options_archive(option_id integer, option_name text COLLATE RTRIM, archived_at text COLLATE BINARY)', 14),
        $record('trigger', 'archive_cleanup_delete', 'wp_options_archive', 0, "CREATE TRIGGER archive_cleanup_delete AFTER DELETE ON wp_options_archive BEGIN SELECT old.option_name COLLATE RTRIM, old.archived_at; END", 15),
    ]);

    return $catalog;
};

$schemas = static fn (): array => [
    'main' => [
        'schema_cookie' => 12,
        'wal_schema_cookie' => 13,
        'tables' => ['wp_options', 'wp_option_audit', 'autoloaded_options'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options', 'wp_option_audit', 'autoloaded_options'],
        'file' => '',
    ],
    'site' => [
        'schema_cookie' => 6,
        'wal_frames' => [
            ['page' => 2, 'schema_cookie' => 99, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 7, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_option_audit', 'autoloaded_options'],
        'file' => '/srv/site.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 8,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 10, 'commit' => false],
        ],
        'tables' => ['wp_options_archive'],
        'file' => '/srv/archive.sqlite',
    ],
];

$triggers = [
    'main_autoloaded_insert',
    'temp_autoloaded_insert',
    'temp_main_delete_bridge',
    'site.site_autoloaded_insert',
    'archive.archive_cleanup_delete',
];

$plan = static fn (?array $overrideSchemas = null): array => SQLiteAttachWalTempViewCollationPlan::plan($catalog(), $overrideSchemas ?? $schemas(), $triggers);

$value = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        return null;
    }

    return $cursor;
};

$pathCases = [
    'status ok' => ['status', 'ok'],
    'operation name' => ['operation', 'attach-wal-temp-view-collation-current-next'],
    'source main' => ['source', 'main'],
    'search order temp main site archive' => ['search_order', ['temp', 'main', 'site', 'archive']],
    'current main cookie' => ['schema_cookies_current.main', 12],
    'next main cookie from wal' => ['schema_cookies_next.main', 13],
    'current temp cookie' => ['schema_cookies_current.temp', 4],
    'next temp cookie unchanged' => ['schema_cookies_next.temp', 4],
    'site current cookie' => ['schema_cookies_current.site', 6],
    'site next cookie from committed page one' => ['schema_cookies_next.site', 7],
    'archive uncommitted cookie ignored' => ['schema_cookies_next.archive', 8],
    'changed schemas main site' => ['changed_schemas', ['main', 'site']],
    'wal sources main site archive' => ['wal_schema_cookie_sources', ['main', 'site', 'archive']],
    'database list main file' => ['database_list.1.file', '/srv/wp/current.sqlite'],
    'database list site seq' => ['database_list.2.seq', 2],
    'database list archive seq' => ['database_list.3.seq', 3],
    'reprepare trigger list' => ['reprepare_triggers', ['main.main_autoloaded_insert', 'site.site_autoloaded_insert', 'temp.temp_main_delete_bridge']],
    'stable trigger list' => ['stable_triggers', ['archive.archive_cleanup_delete', 'temp.temp_autoloaded_insert']],
    'collation count binary' => ['collation_counts.BINARY', 10],
    'collation count nocase' => ['collation_counts.NOCASE', 7],
    'collation count rtrim' => ['collation_counts.RTRIM', 7],
    'dependency base marker' => ['dependencies.0', 'attach-wal-temp-view-collation-current-next'],
    'dependency wal marker' => ['dependencies.1', 'sqlite-wal-page-one-schema-cookie'],
    'dependency collation marker' => ['dependencies.2', 'sqlite-temp-view-trigger-collation-resolution'],
    'main trigger schema' => ['trigger_plans.main_autoloaded_insert.trigger_schema', 'main'],
    'main target schema' => ['trigger_plans.main_autoloaded_insert.target_schema', 'main'],
    'main changed dependencies' => ['trigger_plans.main_autoloaded_insert.changed_schema_dependencies', ['main']],
    'main status expired' => ['trigger_plans.main_autoloaded_insert.status', 'expired'],
    'main target option name nocase' => ['trigger_plans.main_autoloaded_insert.target_collations.option_name', 'NOCASE'],
    'main target option value rtrim' => ['trigger_plans.main_autoloaded_insert.target_collations.option_value', 'RTRIM'],
    'main body main count' => ['trigger_plans.main_autoloaded_insert.body_schema_count.main', 2],
    'main body rtrim count' => ['trigger_plans.main_autoloaded_insert.body_collation_count.RTRIM', 2],
    'temp trigger dependencies only temp' => ['trigger_plans.temp_autoloaded_insert.schema_dependencies', ['temp']],
    'temp trigger changed dependencies empty' => ['trigger_plans.temp_autoloaded_insert.changed_schema_dependencies', []],
    'temp trigger stable' => ['trigger_plans.temp_autoloaded_insert.requires_reprepare', false],
    'temp target value nocase' => ['trigger_plans.temp_autoloaded_insert.target_collations.option_value', 'NOCASE'],
    'temp select first rtrim' => ['trigger_plans.temp_autoloaded_insert.select_collations.0.collation', 'RTRIM'],
    'temp select second nocase' => ['trigger_plans.temp_autoloaded_insert.select_collations.1.collation', 'NOCASE'],
    'bridge dependencies include main temp' => ['trigger_plans.temp_main_delete_bridge.schema_dependencies', ['main', 'temp']],
    'bridge changed main only' => ['trigger_plans.temp_main_delete_bridge.changed_schema_dependencies', ['main']],
    'bridge expired' => ['trigger_plans.temp_main_delete_bridge.requires_reprepare', true],
    'bridge target schema main' => ['trigger_plans.temp_main_delete_bridge.target_schema', 'main'],
    'bridge body temp count' => ['trigger_plans.temp_main_delete_bridge.body_schema_count.temp', 1],
    'bridge body main count' => ['trigger_plans.temp_main_delete_bridge.body_schema_count.main', 1],
];

foreach ($pathCases as $name => [$path, $expected]) {
    $tests['attach wal temp view collation current next54 ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$qualifiedTriggerCases = [
    'site trigger schema' => static fn (array $result): mixed => $result['trigger_plans']['site.site_autoloaded_insert']['trigger_schema'],
    'site target schema' => static fn (array $result): mixed => $result['trigger_plans']['site.site_autoloaded_insert']['target_schema'],
    'site changed dependencies' => static fn (array $result): mixed => $result['trigger_plans']['site.site_autoloaded_insert']['changed_schema_dependencies'],
    'site expired' => static fn (array $result): mixed => $result['trigger_plans']['site.site_autoloaded_insert']['status'],
    'site target value binary' => static fn (array $result): mixed => $result['trigger_plans']['site.site_autoloaded_insert']['target_collations']['option_value'],
    'site body rtrim source' => static fn (array $result): mixed => $result['trigger_plans']['site.site_autoloaded_insert']['body_collation_count']['RTRIM'],
    'archive trigger schema' => static fn (array $result): mixed => $result['trigger_plans']['archive.archive_cleanup_delete']['trigger_schema'],
    'archive target schema' => static fn (array $result): mixed => $result['trigger_plans']['archive.archive_cleanup_delete']['target_schema'],
    'archive unchanged dependencies' => static fn (array $result): mixed => $result['trigger_plans']['archive.archive_cleanup_delete']['changed_schema_dependencies'],
    'archive stable' => static fn (array $result): mixed => $result['trigger_plans']['archive.archive_cleanup_delete']['status'],
    'archive target name rtrim' => static fn (array $result): mixed => $result['trigger_plans']['archive.archive_cleanup_delete']['target_collations']['option_name'],
    'archive select date binary fallback' => static fn (array $result): mixed => $result['trigger_plans']['archive.archive_cleanup_delete']['select_collations'][1]['collation'],
];

$qualifiedTriggerExpected = [
    'site trigger schema' => 'site',
    'site target schema' => 'site',
    'site changed dependencies' => ['site'],
    'site expired' => 'expired',
    'site target value binary' => 'BINARY',
    'site body rtrim source' => 1,
    'archive trigger schema' => 'archive',
    'archive target schema' => 'archive',
    'archive unchanged dependencies' => [],
    'archive stable' => 'stable',
    'archive target name rtrim' => 'RTRIM',
    'archive select date binary fallback' => 'BINARY',
];

foreach ($qualifiedTriggerCases as $name => $actual) {
    $tests['attach wal temp view collation current next54 ' . $name] = static function (TestRunner $t) use ($plan, $actual, $qualifiedTriggerExpected, $name): void {
        $t->same($qualifiedTriggerExpected[$name], $actual($plan()));
    };
}

$tests['attach wal temp view collation current next54 temp wal cookie expires temp triggers'] = static function (TestRunner $t) use ($schemas, $plan): void {
    $next = $schemas();
    $next['temp']['wal_schema_cookie'] = 5;
    $result = $plan($next);

    $t->same(['temp', 'main', 'site'], $result['changed_schemas']);
    $t->same(true, $result['trigger_plans']['temp_autoloaded_insert']['requires_reprepare']);
    $t->same(['temp'], $result['trigger_plans']['temp_autoloaded_insert']['changed_schema_dependencies']);
    $t->same(['main', 'temp'], $result['trigger_plans']['temp_main_delete_bridge']['changed_schema_dependencies']);
};

$tests['attach wal temp view collation current next54 committed archive wal expires archive trigger'] = static function (TestRunner $t) use ($schemas, $plan): void {
    $next = $schemas();
    $next['archive']['wal_frames'][0]['commit'] = true;
    $result = $plan($next);

    $t->same(10, $result['schema_cookies_next']['archive']);
    $t->same(true, $result['trigger_plans']['archive.archive_cleanup_delete']['requires_reprepare']);
    $t->same(['archive'], $result['trigger_plans']['archive.archive_cleanup_delete']['changed_schema_dependencies']);
    $t->same(['archive.archive_cleanup_delete', 'main.main_autoloaded_insert', 'site.site_autoloaded_insert', 'temp.temp_main_delete_bridge'], $result['reprepare_triggers']);
};

$tests['attach wal temp view collation current next54 stable wal pages keep all triggers stable'] = static function (TestRunner $t) use ($schemas, $plan): void {
    $next = $schemas();
    unset($next['main']['wal_schema_cookie']);
    $next['site']['wal_frames'] = [['page' => 2, 'schema_cookie' => 99, 'commit' => true]];
    $result = $plan($next);

    $t->same([], $result['changed_schemas']);
    $t->same([], $result['reprepare_triggers']);
    $t->same(['archive.archive_cleanup_delete', 'main.main_autoloaded_insert', 'site.site_autoloaded_insert', 'temp.temp_autoloaded_insert', 'temp.temp_main_delete_bridge'], $result['stable_triggers']);
};

$tests['attach wal temp view collation current next54 source schema normalization accepts attached'] = static function (TestRunner $t) use ($catalog, $schemas, $triggers): void {
    $result = SQLiteAttachWalTempViewCollationPlan::plan($catalog(), $schemas(), $triggers, '"SITE"');

    $t->same('site', $result['source']);
    $t->same(['temp', 'main', 'site', 'archive'], $result['search_order']);
};

$tests['attach wal temp view collation current next54 missing source schema throws'] = static function (TestRunner $t) use ($catalog, $schemas, $triggers): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempViewCollationPlan::plan($catalog(), $schemas(), $triggers, 'missing'));
};

$tests['attach wal temp view collation current next54 missing trigger throws'] = static function (TestRunner $t) use ($catalog, $schemas): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempViewCollationPlan::plan($catalog(), $schemas(), ['missing_trigger']));
};

return $tests;
