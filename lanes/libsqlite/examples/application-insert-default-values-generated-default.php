<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteInsertDefaultValuesSql;

$schema = <<<'SQL'
CREATE TABLE wp_option_defaults(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL DEFAULT 'blogname',
    option_value TEXT DEFAULT (upper('example site')),
    autoload TEXT NOT NULL DEFAULT 'yes',
    touched_at TEXT DEFAULT CURRENT_TIMESTAMP,
    option_name_lc TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL,
    option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED,
    option_cache_key TEXT AS (option_name || ':' || autoload) VIRTUAL
)
SQL;

$rows = [
    [
        'option_id' => 41,
        'option_name' => 'siteurl',
        'option_value' => 'https://example.test',
        'autoload' => 'yes',
        'touched_at' => '2026-05-26 00:00:00',
        'option_name_lc' => 'siteurl',
        'option_value_len' => 20,
        'option_cache_key' => 'siteurl:yes',
    ],
];

$result = SQLiteInsertDefaultValuesSql::execute(
    'INSERT INTO wp_option_defaults DEFAULT VALUES',
    ['wp_option_defaults' => $rows],
    ['wp_option_defaults' => $schema],
    '2026-05-27 06:30:45',
);

$payload = [
    'applicationUse' => 'Preview INSERT DEFAULT VALUES for copied wp_options-style defaults and generated columns without requiring ext/sqlite.',
    'insertedRow' => $result['inserted_row'],
    'changes' => $result['changes'],
    'afterCount' => count($result['after']),
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['insertedRow']['option_id'] !== 42) {
        throw new RuntimeException('Expected next option_id 42');
    }
    if ($payload['insertedRow']['option_name_lc'] !== 'blogname') {
        throw new RuntimeException('Expected generated lower-case option name');
    }
    if ($payload['insertedRow']['option_cache_key'] !== 'blogname:yes') {
        throw new RuntimeException('Expected generated cache key');
    }
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
