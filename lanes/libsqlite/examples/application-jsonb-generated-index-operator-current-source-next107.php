<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteBlobValue.php';
require_once dirname(__DIR__) . '/src/SQLiteJson5Parser.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonB.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonCanonical.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonInspection.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonPath.php';
require_once dirname(__DIR__) . '/src/SQLiteIndexPredicate.php';
require_once dirname(__DIR__) . '/src/SQLiteIndexColumn.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonExtractIndexExpression.php';
require_once dirname(__DIR__) . '/src/SQLiteCreateIndex.php';
require_once dirname(__DIR__) . '/src/SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$currentRows = [
    ['option_id' => 1, 'option_name' => 'plugin_cache_settings', 'autoload' => 'yes', 'option_value' => $jsonb(['plugin' => ['channel' => 'stable', 'priority' => 7, 'limits' => ['daily' => 25]]])],
    ['option_id' => 2, 'option_name' => 'plugin_forms_settings', 'autoload' => 'no', 'option_value' => $jsonb(['plugin' => ['channel' => 'beta', 'priority' => 3, 'limits' => ['daily' => 10]]])],
];
$nextRows = [
    ['option_id' => 1, 'option_name' => 'plugin_cache_settings', 'autoload' => 'yes', 'option_value' => $jsonb(['plugin' => ['channel' => 'rc', 'priority' => 8, 'limits' => ['daily' => 30]]])],
    ['option_id' => 2, 'option_name' => 'plugin_forms_settings', 'autoload' => 'yes', 'option_value' => $jsonb(['plugin' => ['channel' => 'beta', 'priority' => 3, 'limits' => ['daily' => 10]]])],
];

$plan = SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan::plan([
    ['name' => 'idx_wp_options_plugin_channel', 'rootPage' => 501, 'sql' => "CREATE INDEX idx_wp_options_plugin_channel ON wp_options((option_value ->> '$.plugin.channel') COLLATE NOCASE, option_name) WHERE autoload = 'yes'"],
    ['name' => 'idx_wp_options_plugin_limits', 'rootPage' => 502, 'sql' => "CREATE INDEX idx_wp_options_plugin_limits ON wp_options((option_value -> '$.plugin.limits') COLLATE BINARY, option_name)"],
], $currentRows, $nextRows);

echo json_encode([
    'scenario' => 'application-jsonb-generated-index-operator-current-source-next107',
    'applicationUse' => 'Copied wp_options JSONB plugin settings can maintain generated expression indexes declared with SQLite JSON -> and ->> operators across current/next import rows, including partial autoload activation and canonical JSON object keys, without requiring ext/sqlite.',
    'indexes' => array_column($plan['indexes'], 'name'),
    'deleteEntries' => $plan['delete_entries'],
    'insertEntries' => $plan['insert_entries'],
    'changedEntryCount' => $plan['changed_entry_count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
