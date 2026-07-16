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
    ['setting_id' => 1, 'key_name' => 'feature_cache_settings', 'load_policy' => 'yes', 'key_value' => $jsonb(['feature' => ['channel' => 'stable', 'priority' => 7, 'limits' => ['daily' => 25]]])],
    ['setting_id' => 2, 'key_name' => 'feature_forms_settings', 'load_policy' => 'no', 'key_value' => $jsonb(['feature' => ['channel' => 'beta', 'priority' => 3, 'limits' => ['daily' => 10]]])],
];
$nextRows = [
    ['setting_id' => 1, 'key_name' => 'feature_cache_settings', 'load_policy' => 'yes', 'key_value' => $jsonb(['feature' => ['channel' => 'rc', 'priority' => 8, 'limits' => ['daily' => 30]]])],
    ['setting_id' => 2, 'key_name' => 'feature_forms_settings', 'load_policy' => 'yes', 'key_value' => $jsonb(['feature' => ['channel' => 'beta', 'priority' => 3, 'limits' => ['daily' => 10]]])],
];

$plan = SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan::plan([
    ['name' => 'idx_app_settings_feature_channel', 'rootPage' => 501, 'sql' => "CREATE INDEX idx_app_settings_feature_channel ON app_settings((key_value ->> '$.feature.channel') COLLATE NOCASE, key_name) WHERE load_policy = 'yes'"],
    ['name' => 'idx_app_settings_feature_limits', 'rootPage' => 502, 'sql' => "CREATE INDEX idx_app_settings_feature_limits ON app_settings((key_value -> '$.feature.limits') COLLATE BINARY, key_name)"],
], $currentRows, $nextRows);

echo json_encode([
    'scenario' => 'application-jsonb-generated-index-operator-current-source-next107',
    'applicationUse' => 'Copied app_settings JSONB feature settings can maintain generated expression indexes declared with SQLite JSON -> and ->> operators across current/next import rows, including partial load_policy activation and canonical JSON object keys, without requiring ext/sqlite.',
    'indexes' => array_column($plan['indexes'], 'name'),
    'deleteEntries' => $plan['delete_entries'],
    'insertEntries' => $plan['insert_entries'],
    'changedEntryCount' => $plan['changed_entry_count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
