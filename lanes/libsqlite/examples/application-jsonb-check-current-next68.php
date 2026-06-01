<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbCheckCurrentNextPlan;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema = <<<'SQL'
CREATE TABLE app_settings(
  setting_id INTEGER PRIMARY KEY,
  key_name TEXT NOT NULL,
  key_value BLOB,
  load_policy TEXT,
  CHECK(json_valid(key_value, 8)),
  CHECK(json_type(key_value, '$.module.slug') = 'text'),
  CHECK(json_type(key_value, '$.module.description') = 'text'),
  CHECK(json_extract(key_value, '$.module.channel') IN ('stable','beta') OR json_extract(key_value, '$.module.channel') IS NULL),
  CHECK(json_extract(key_value, '$.module.priority') IS NULL OR json_extract(key_value, '$.module.priority') <= 10)
)
SQL;

$plan = SQLiteJsonbCheckCurrentNextPlan::plan($schema, [
    ['setting_id' => 301, 'key_name' => 'module_alpha_settings', 'key_value' => $jsonb(['module' => ['slug' => 'alpha', 'channel' => 'stable', 'priority' => 5]]), 'load_policy' => 'yes'],
    ['setting_id' => 302, 'key_name' => 'module_beta_settings', 'key_value' => $jsonb(['module' => ['slug' => 'beta']]), 'load_policy' => 'no'],
], [
    ['op' => 'UPDATE', 'rowid' => 301, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.description', 'value' => 'Alpha updated'],
    ]],
    ['op' => 'UPDATE', 'rowid' => 302, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.module.channel', 'value' => 'nightly'],
    ]],
    ['op' => 'INSERT', 'row' => ['setting_id' => 303, 'key_name' => 'module_delta_settings', 'key_value' => $jsonb(['module' => ['slug' => 'delta', 'priority' => 11]]), 'load_policy' => 'no']],
]);

$nullableDescriptionTerm = $plan['current'][0]['checks'][2]['terms'][0];
$channelOrTerm = $plan['rejected'][0]['checks'][3]['terms'][0];

echo json_encode([
    'scenario' => 'application-jsonb-check-current-next68',
    'changes' => $plan['changes'],
    'rejectedChanges' => $plan['rejectedChanges'],
    'acceptedRowids' => array_column($plan['accepted'], 'rowid'),
    'rejectedRowids' => array_column($plan['rejected'], 'rowid'),
    'nullableDescriptionResult' => $nullableDescriptionTerm['result'],
    'nullableDescriptionOk' => $nullableDescriptionTerm['ok'],
    'failedChannelOrActual' => $channelOrTerm['actual'],
    'applicationUse' => 'Preflight application settings JSONB module settings with optional JSON paths before admitting current/next rows to storage.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
