<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempSchemaResolutionPlan;
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
    return new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
            $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, option_name text)', 2),
            $record('index', 'wp_options_name', 'wp_options', 4, 'CREATE INDEX wp_options_name ON wp_options(option_name)', 3),
            $record('trigger', 'wp_options_ai', 'wp_options', 0, "CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'main', new.option_name); END", 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 5),
            $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, temp_name text)', 6),
            $record('index', 'wp_options_temp_name', 'wp_options', 12, 'CREATE INDEX wp_options_temp_name ON wp_options(temp_name)', 7),
            $record('trigger', 'wp_options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, temp_name) VALUES(new.option_id, 'temp', new.temp_name); END", 8),
        ],
    );
};

$loader = static function (string $file, string $schema) use ($record): array {
    if ($schema === 'site') {
        return [
            $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_id integer, option_name text, option_value text)', 20),
            $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, option_name text)', 21),
            $record('table', 'wp_sitemeta', 'wp_sitemeta', 22, 'CREATE TABLE site.wp_sitemeta(meta_id integer, meta_key text, meta_value text)', 22),
            $record('index', 'wp_options_name', 'wp_options', 23, 'CREATE INDEX site.wp_options_name ON wp_options(option_name)', 23),
            $record('trigger', 'wp_options_ai', 'wp_options', 0, "CREATE TRIGGER site.wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, label, option_name) VALUES(new.blog_id, 'site', new.option_name); END", 24),
        ];
    }
    if ($schema === 'archive') {
        return [
            $record('table', 'wp_options', 'wp_options', 30, 'CREATE TABLE archive.wp_options(option_id integer, option_name text, archived_at text)', 30),
            $record('table', 'wp_posts', 'wp_posts', 31, 'CREATE TABLE archive.wp_posts(ID integer, post_title text)', 31),
            $record('index', 'wp_archive_option_name', 'wp_options', 32, 'CREATE INDEX archive.wp_archive_option_name ON wp_options(option_name)', 32),
            $record('trigger', 'wp_options_ad', 'wp_options', 0, "CREATE TRIGGER archive.wp_options_ad AFTER DELETE ON wp_options BEGIN SELECT old.option_id, old.option_name; END", 33),
        ];
    }

    return [];
};

$newTemp = ['option_id' => 501, 'temp_name' => 'plugin_cache', 'option_value' => '{"enabled":true}'];
$newMain = ['option_id' => 7, 'option_name' => 'siteurl', 'option_value' => 'https://main.test', 'autoload' => 'yes'];
$newSite = ['blog_id' => 3, 'option_id' => 9, 'option_name' => 'home', 'option_value' => 'https://site.test'];
$oldArchive = ['option_id' => 44, 'option_name' => '_transient_feed', 'archived_at' => '2026-05-27'];

$probes = [
    ['kind' => 'table', 'name' => 'wp_options'],
    ['kind' => 'table', 'name' => 'main.wp_options'],
    ['kind' => 'table', 'name' => 'temp.wp_options'],
    ['kind' => 'table', 'name' => 'site.wp_options'],
    ['kind' => 'table', 'name' => 'archive.wp_options'],
    ['kind' => 'table', 'name' => 'wp_sitemeta'],
    ['kind' => 'index', 'name' => 'wp_options_name'],
    ['kind' => 'index', 'name' => 'wp_options_temp_name'],
    ['kind' => 'trigger', 'name' => 'wp_options_ai'],
    ['kind' => 'trigger', 'name' => 'main.wp_options_ai'],
    ['kind' => 'trigger', 'name' => 'site.wp_options_ai'],
    ['kind' => 'trigger', 'name' => 'archive.wp_options_ad'],
    ['kind' => 'schema-table', 'name' => 'sqlite_schema'],
    ['kind' => 'schema-table', 'name' => 'sqlite_temp_schema'],
    ['kind' => 'schema-table', 'name' => 'site.sqlite_schema'],
    ['kind' => 'pragma-table-info', 'name' => 'wp_options'],
    ['kind' => 'pragma-index-list', 'name' => 'wp_options'],
    ['kind' => 'yield', 'name' => 'wp_options_ai', 'new' => $newTemp],
    ['kind' => 'yield', 'name' => 'main.wp_options_ai', 'new' => $newMain],
    ['kind' => 'yield', 'name' => 'site.wp_options_ai', 'new' => $newSite],
    ['kind' => 'yield', 'name' => 'archive.wp_options_ad', 'old' => $oldArchive],
];

$trace = static fn (): array => SQLiteAttachTempSchemaResolutionPlan::transitionTrace(
    $catalog(),
    [
        ['label' => 'attach-site', 'sql' => "ATTACH DATABASE '/srv/site.sqlite' AS site"],
        ['label' => 'attach-archive', 'sql' => "ATTACH DATABASE '/srv/archive.sqlite' AS archive"],
        ['label' => 'detach-site', 'sql' => 'DETACH DATABASE site'],
    ],
    $probes,
    $loader,
);

$probe = static fn (array $snapshot, string $kind, string $name): array => $snapshot['probes'][$kind . ':' . $name];
$tests = [];

foreach ([
    'status' => ['status', 'resolved'],
    'step count' => [static fn (array $trace): int => count($trace['steps']), 3],
    'first operation' => ['steps.0.operation', 'attach'],
    'first schema' => ['steps.0.schema', 'site'],
    'second operation' => ['steps.1.operation', 'attach'],
    'second schema' => ['steps.1.schema', 'archive'],
    'third operation' => ['steps.2.operation', 'detach'],
    'third schema' => ['steps.2.schema', 'site'],
    'final search order after detach' => ['final.searchOrder', ['temp', 'main', 'archive']],
] as $name => [$path, $expected]) {
    $tests['attach temp schema resolution current next32 trace ' . $name] = static function (TestRunner $t) use ($trace, $path, $expected): void {
        $result = $trace();
        $actual = is_callable($path) ? $path($result) : array_reduce(explode('.', $path), static fn (mixed $value, string $part): mixed => is_array($value) ? $value[(int) ctype_digit($part) ? (int) $part : $part] : null, $result);
        $t->same($expected, $actual);
    };
}

foreach ([
    'initial unqualified table is temp' => [0, 'before', 'table', 'wp_options', 'schema', 'temp'],
    'initial temp table root' => [0, 'before', 'table', 'wp_options', 'rootPage', 10],
    'initial main qualifier bypasses temp' => [0, 'before', 'table', 'main.wp_options', 'schema', 'main'],
    'initial site table missing' => [0, 'before', 'table', 'site.wp_options', 'status', 'missing'],
    'after site attach search order' => [0, 'after', null, null, 'searchOrder', ['temp', 'main', 'site']],
    'after site attach database seq' => [0, 'after', null, null, 'databaseList.2.name', 'site'],
    'after site attach table resolves qualified' => [0, 'after', 'table', 'site.wp_options', 'schema', 'site'],
    'after site attach sitemeta fallback resolves site' => [0, 'after', 'table', 'wp_sitemeta', 'schema', 'site'],
    'after site attach index unqualified remains main' => [0, 'after', 'index', 'wp_options_name', 'schema', 'main'],
    'after site attach trigger qualified resolves site' => [0, 'after', 'trigger', 'site.wp_options_ai', 'schema', 'site'],
    'after site attach site trigger target' => [0, 'after', 'trigger', 'site.wp_options_ai', 'targetSchema', 'site'],
    'after site attach site yield writes site' => [0, 'after', 'yield', 'site.wp_options_ai', 'writesBySchema', ['site' => 1]],
    'after site attach site yield first schema' => [0, 'after', 'yield', 'site.wp_options_ai', 'firstOperationSchema', 'site'],
    'after archive attach search order' => [1, 'after', null, null, 'searchOrder', ['temp', 'main', 'site', 'archive']],
    'after archive attach database seq' => [1, 'after', null, null, 'databaseList.3.name', 'archive'],
    'after archive attach table qualified resolves' => [1, 'after', 'table', 'archive.wp_options', 'schema', 'archive'],
    'after archive attach site remains first attached fallback' => [1, 'after', 'table', 'wp_sitemeta', 'schema', 'site'],
    'after archive attach archive trigger resolves' => [1, 'after', 'trigger', 'archive.wp_options_ad', 'schema', 'archive'],
    'after archive attach archive trigger target' => [1, 'after', 'trigger', 'archive.wp_options_ad', 'targetSchema', 'archive'],
    'after archive attach archive select yield has no writes' => [1, 'after', 'yield', 'archive.wp_options_ad', 'writesBySchema', []],
    'after archive attach archive select yield status' => [1, 'after', 'yield', 'archive.wp_options_ad', 'status', 'yielded'],
    'after site detach search order' => [2, 'after', null, null, 'searchOrder', ['temp', 'main', 'archive']],
    'after site detach database resequences archive' => [2, 'after', null, null, 'databaseList.2.name', 'archive'],
    'after site detach site table missing' => [2, 'after', 'table', 'site.wp_options', 'status', 'missing'],
    'after site detach sitemeta missing' => [2, 'after', 'table', 'wp_sitemeta', 'status', 'missing'],
    'after site detach archive remains qualified' => [2, 'after', 'table', 'archive.wp_options', 'schema', 'archive'],
    'after site detach archive trigger still resolves' => [2, 'after', 'trigger', 'archive.wp_options_ad', 'schema', 'archive'],
] as $name => [$step, $when, $kind, $target, $field, $expected]) {
    $tests['attach temp schema resolution current next32 transition ' . $name] = static function (TestRunner $t) use ($trace, $probe, $step, $when, $kind, $target, $field, $expected): void {
        $snapshot = $trace()['steps'][$step][$when];
        $source = $kind === null ? $snapshot : $probe($snapshot, $kind, $target);
        $actual = array_reduce(explode('.', $field), static fn (mixed $value, string $part): mixed => is_array($value) ? $value[(int) ctype_digit($part) ? (int) $part : $part] : null, $source);
        $t->same($expected, $actual);
    };
}

foreach ([
    'sqlite_schema bare pins main' => [0, 'before', 'schema-table', 'sqlite_schema', 'schema', 'main'],
    'sqlite_schema bare root is one' => [0, 'before', 'schema-table', 'sqlite_schema', 'rootPage', 1],
    'sqlite_temp_schema bare pins temp' => [0, 'before', 'schema-table', 'sqlite_temp_schema', 'schema', 'temp'],
    'qualified attached sqlite schema resolves after attach' => [0, 'after', 'schema-table', 'site.sqlite_schema', 'schema', 'site'],
    'qualified attached sqlite schema missing after detach' => [2, 'after', 'schema-table', 'site.sqlite_schema', 'status', 'missing'],
    'pragma table info follows temp shadow' => [0, 'before', 'pragma-table-info', 'wp_options', 'schema', 'temp'],
    'pragma table info temp column count' => [0, 'before', 'pragma-table-info', 'wp_options', 'rowCount', 3],
    'pragma index list follows temp table shadow' => [0, 'before', 'pragma-index-list', 'wp_options', 'schema', 'temp'],
    'pragma index list temp row count' => [0, 'before', 'pragma-index-list', 'wp_options', 'rowCount', 1],
    'pragma table info still temp after attaches' => [1, 'after', 'pragma-table-info', 'wp_options', 'schema', 'temp'],
] as $name => [$step, $when, $kind, $target, $field, $expected]) {
    $tests['attach temp schema resolution current next32 schema aliases ' . $name] = static function (TestRunner $t) use ($trace, $probe, $step, $when, $kind, $target, $field, $expected): void {
        $actual = $probe($trace()['steps'][$step][$when], $kind, $target)[$field];
        $t->same($expected, $actual);
    };
}

foreach ([
    'unqualified trigger starts temp' => [0, 'before', 'yield', 'wp_options_ai', 'schema', 'temp'],
    'unqualified trigger target starts temp' => [0, 'before', 'yield', 'wp_options_ai', 'targetSchema', 'temp'],
    'unqualified trigger writes temp' => [0, 'before', 'yield', 'wp_options_ai', 'writesBySchema', ['temp' => 1]],
    'unqualified trigger first operation temp' => [0, 'before', 'yield', 'wp_options_ai', 'firstOperationSchema', 'temp'],
    'main trigger remains qualified main' => [0, 'before', 'yield', 'main.wp_options_ai', 'schema', 'main'],
    'main trigger writes main audit' => [0, 'before', 'yield', 'main.wp_options_ai', 'writesBySchema', ['main' => 1]],
    'main trigger first table' => [0, 'before', 'yield', 'main.wp_options_ai', 'firstOperationTable', 'wp_option_audit'],
    'site yield missing before attach' => [0, 'before', 'yield', 'site.wp_options_ai', 'status', 'missing'],
    'site yield available after attach' => [0, 'after', 'yield', 'site.wp_options_ai', 'status', 'yielded'],
    'site yield missing again after detach' => [2, 'after', 'yield', 'site.wp_options_ai', 'status', 'missing'],
    'archive yield missing before attach' => [1, 'before', 'yield', 'archive.wp_options_ad', 'status', 'missing'],
    'archive yield available after attach' => [1, 'after', 'yield', 'archive.wp_options_ad', 'status', 'yielded'],
] as $name => [$step, $when, $kind, $target, $field, $expected]) {
    $tests['attach temp schema resolution current next32 yield ' . $name] = static function (TestRunner $t) use ($trace, $probe, $step, $when, $kind, $target, $field, $expected): void {
        $actual = $probe($trace()['steps'][$step][$when], $kind, $target)[$field];
        $t->same($expected, $actual);
    };
}

foreach ([
    'invalid empty statement rejected' => static fn (): array => SQLiteAttachTempSchemaResolutionPlan::transitionTrace($catalog(), [['sql' => '']], $probes, $loader),
    'invalid probe kind rejected inside snapshot' => static fn (): array => SQLiteAttachTempSchemaResolutionPlan::snapshot($catalog(), [['kind' => 'view', 'name' => 'wp_options']]),
    'missing probe name rejected' => static fn (): array => SQLiteAttachTempSchemaResolutionPlan::snapshot($catalog(), [['kind' => 'table', 'name' => '']]),
] as $name => $callback) {
    $tests['attach temp schema resolution current next32 guard ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
