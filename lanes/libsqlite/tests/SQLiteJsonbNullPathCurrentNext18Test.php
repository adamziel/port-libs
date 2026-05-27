<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteSelectSql;

$decode = static function (string|SQLiteBlobValue|null $value): mixed {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
};

$jsonbSettings = new SQLiteBlobValue(SQLiteJsonB::encode([
    'plugins' => [
        ['slug' => 'seo', 'enabled' => true, 'priority' => 7],
        ['slug' => 'forms', 'enabled' => false, 'priority' => 3],
    ],
    'meta' => ['source' => 'import'],
]));

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => '{"plugins":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true}],"meta":{"source":"text"}}',
        'maybe_path' => null,
        'target_path' => '$.meta.reviewed',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_settings_jsonb',
        'option_value' => $jsonbSettings,
        'maybe_path' => null,
        'target_path' => '$.plugins[#]',
    ],
];

$scalar = static function (string $sql) use ($rows): mixed {
    $result = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);
    if (count($result) !== 1) {
        throw new RuntimeException('Expected one SQLite SELECT SQL result row');
    }

    return reset($result[0]);
};

return [
    'json set ignores null leading path and applies later text path' => static fn (TestRunner $t) => $t->same(
        ['plugins' => [['slug' => 'seo', 'enabled' => true], ['slug' => 'cache', 'enabled' => true]], 'meta' => ['source' => 'text', 'reviewed' => true]],
        $decode(SQLiteJsonMutation::mutateSqlFunction('json_set', $rows[0]['option_value'], null, 'ignored', '$.meta.reviewed', true)),
    ),
    'json insert ignores null leading path and applies later text path' => static fn (TestRunner $t) => $t->same(
        ['plugins' => [['slug' => 'seo', 'enabled' => true], ['slug' => 'cache', 'enabled' => true]], 'meta' => ['source' => 'text', 'reviewed' => true]],
        $decode(SQLiteJsonMutation::mutateSqlFunction('json_insert', $rows[0]['option_value'], null, 'ignored', '$.meta.reviewed', true)),
    ),
    'json replace ignores null leading path and applies later existing path' => static fn (TestRunner $t) => $t->same(
        ['plugins' => [['slug' => 'seo', 'enabled' => true], ['slug' => 'cache', 'enabled' => true]], 'meta' => ['source' => 'sync']],
        $decode(SQLiteJsonMutation::mutateSqlFunction('json_replace', $rows[0]['option_value'], null, 'ignored', '$.meta.source', 'sync')),
    ),
    'json set ignores null trailing path after earlier mutation' => static fn (TestRunner $t) => $t->same(
        ['plugins' => [['slug' => 'seo', 'enabled' => true], ['slug' => 'cache', 'enabled' => true]], 'meta' => ['source' => 'text', 'reviewed' => true]],
        $decode(SQLiteJsonMutation::mutateSqlFunction('json_set', $rows[0]['option_value'], '$.meta.reviewed', true, null, 'ignored')),
    ),
    'json set with only null path canonicalizes unchanged input' => static fn (TestRunner $t) => $t->same(
        ['plugins' => [['slug' => 'seo', 'enabled' => true], ['slug' => 'cache', 'enabled' => true]], 'meta' => ['source' => 'text']],
        $decode(SQLiteJsonMutation::mutateSqlFunction('json_set', $rows[0]['option_value'], null, 'ignored')),
    ),
    'jsonb set ignores null leading path and applies later append path' => static function (TestRunner $t) use ($jsonbSettings, $decode): void {
        $value = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $jsonbSettings, null, 'ignored', '$.plugins[#]', new SQLiteBlobValue(SQLiteJsonB::encode(['slug' => 'cache', 'enabled' => true, 'priority' => 5])));
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same(['seo', 'forms', 'cache'], array_column($decode($value)['plugins'], 'slug'));
    },
    'jsonb insert ignores null trailing path after earlier append' => static function (TestRunner $t) use ($jsonbSettings, $decode): void {
        $value = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $jsonbSettings, '$.plugins[#]', new SQLiteBlobValue(SQLiteJsonB::encode(['slug' => 'cache', 'enabled' => true])), null, 'ignored');
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same(['seo', 'forms', 'cache'], array_column($decode($value)['plugins'], 'slug'));
    },
    'jsonb replace ignores null path and keeps unchanged blob document' => static function (TestRunner $t) use ($jsonbSettings, $decode): void {
        $value = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $jsonbSettings, null, 'ignored');
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same(['source' => 'import'], $decode($value)['meta']);
    },
    'json remove returns null for null leading path' => static fn (TestRunner $t) => $t->same(
        null,
        SQLiteJsonRemove::removeSqlFunction('json_remove', $rows[0]['option_value'], null, '$.meta.source'),
    ),
    'json remove returns null for null trailing path' => static fn (TestRunner $t) => $t->same(
        null,
        SQLiteJsonRemove::removeSqlFunction('json_remove', $rows[0]['option_value'], '$.meta.source', null),
    ),
    'jsonb remove returns null for null path' => static fn (TestRunner $t) => $t->same(
        null,
        SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonbSettings, null),
    ),
    'select sql json set ignores row null path operand' => static fn (TestRunner $t) => $t->same(
        ['source' => 'text', 'reviewed' => 1],
        $decode($scalar("SELECT json_set(option_value, maybe_path, 'ignored', target_path, 1) AS value FROM wp_options WHERE option_id = 1"))['meta'],
    ),
    'select sql jsonb set ignores row null path operand' => static function (TestRunner $t) use ($scalar, $decode): void {
        $value = $scalar("SELECT jsonb_set(option_value, maybe_path, 'ignored', target_path, jsonb('{\"slug\":\"cache\",\"enabled\":true}')) AS value FROM wp_options WHERE option_id = 2");
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same(['seo', 'forms', 'cache'], array_column($decode($value)['plugins'], 'slug'));
    },
    'select sql json remove null row path returns null' => static fn (TestRunner $t) => $t->same(
        null,
        $scalar("SELECT json_remove(option_value, maybe_path) AS value FROM wp_options WHERE option_id = 1"),
    ),
    'select sql jsonb remove null row path returns null' => static fn (TestRunner $t) => $t->same(
        null,
        $scalar("SELECT jsonb_remove(option_value, maybe_path) AS value FROM wp_options WHERE option_id = 2"),
    ),
    'select sql json replace ignores null path but updates existing later path' => static fn (TestRunner $t) => $t->same(
        'sync',
        $decode($scalar("SELECT json_replace(option_value, maybe_path, 'ignored', '$.meta.source', 'sync') AS value FROM wp_options WHERE option_id = 1"))['meta']['source'],
    ),
    'select sql json insert ignores all null path pairs' => static fn (TestRunner $t) => $t->same(
        ['source' => 'text'],
        $decode($scalar("SELECT json_insert(option_value, maybe_path, 'ignored') AS value FROM wp_options WHERE option_id = 1"))['meta'],
    ),
    'select sql jsonb replace ignores all null path pairs' => static function (TestRunner $t) use ($scalar, $decode): void {
        $value = $scalar("SELECT jsonb_replace(option_value, maybe_path, 'ignored') AS value FROM wp_options WHERE option_id = 2");
        $t->same(true, $value instanceof SQLiteBlobValue);
        $t->same(['source' => 'import'], $decode($value)['meta']);
    },
    'json mutation still rejects non-null non-text paths' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', $rows[0]['option_value'], '$.meta.reviewed', true, 7, 'ignored'),
    ),
    'json remove still rejects non-null non-text paths' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', [$rows[0]['option_value'], 7]),
    ),
];
