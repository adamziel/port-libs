<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteInsertDefaultValuesSql;

$schema = <<<'SQL'
CREATE TABLE app_setting_defaults(
    setting_id INTEGER PRIMARY KEY,
    key_name TEXT NOT NULL DEFAULT 'display_name',
    key_value TEXT DEFAULT (upper('example site')),
    load_policy TEXT NOT NULL DEFAULT 'yes',
    touched_at TEXT DEFAULT CURRENT_TIMESTAMP,
    key_name_lc TEXT GENERATED ALWAYS AS (lower(key_name)) VIRTUAL,
    key_value_len INTEGER GENERATED ALWAYS AS (length(key_value)) STORED,
    setting_cache_key TEXT AS (key_name || ':' || load_policy) VIRTUAL
)
SQL;

$rows = [
    [
        'setting_id' => 41,
        'key_name' => 'service_url',
        'key_value' => 'https://example.test',
        'load_policy' => 'yes',
        'touched_at' => '2026-05-26 00:00:00',
        'key_name_lc' => 'service_url',
        'key_value_len' => 20,
        'setting_cache_key' => 'service_url:yes',
    ],
];

$result = SQLiteInsertDefaultValuesSql::execute(
    'INSERT INTO app_setting_defaults DEFAULT VALUES',
    ['app_setting_defaults' => $rows],
    ['app_setting_defaults' => $schema],
    '2026-05-27 06:30:45',
);

$payload = [
    'applicationUse' => 'Preview INSERT DEFAULT VALUES for copied application settings-style defaults and generated columns without requiring ext/sqlite.',
    'insertedRow' => $result['inserted_row'],
    'changes' => $result['changes'],
    'afterCount' => count($result['after']),
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['insertedRow']['setting_id'] !== 42) {
        throw new RuntimeException('Expected next setting_id 42');
    }
    if ($payload['insertedRow']['key_name_lc'] !== 'display_name') {
        throw new RuntimeException('Expected generated lower-case key name');
    }
    if ($payload['insertedRow']['setting_cache_key'] !== 'display_name:yes') {
        throw new RuntimeException('Expected generated cache key');
    }
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
