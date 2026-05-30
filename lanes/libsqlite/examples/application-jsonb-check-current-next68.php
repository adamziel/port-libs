<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbCheckCurrentNextPlan;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$schema = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value BLOB,
  autoload TEXT,
  CHECK(json_valid(option_value, 8)),
  CHECK(json_type(option_value, '$.plugin.slug') = 'text'),
  CHECK(json_type(option_value, '$.plugin.description') = 'text'),
  CHECK(json_extract(option_value, '$.plugin.channel') IN ('stable','beta') OR json_extract(option_value, '$.plugin.channel') IS NULL),
  CHECK(json_extract(option_value, '$.plugin.priority') IS NULL OR json_extract(option_value, '$.plugin.priority') <= 10)
)
SQL;

$plan = SQLiteJsonbCheckCurrentNextPlan::plan($schema, [
    ['option_id' => 301, 'option_name' => 'plugin_alpha_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'channel' => 'stable', 'priority' => 5]]), 'autoload' => 'yes'],
    ['option_id' => 302, 'option_name' => 'plugin_beta_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'beta']]), 'autoload' => 'no'],
], [
    ['op' => 'UPDATE', 'rowid' => 301, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.description', 'value' => 'Alpha updated'],
    ]],
    ['op' => 'UPDATE', 'rowid' => 302, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'nightly'],
    ]],
    ['op' => 'INSERT', 'row' => ['option_id' => 303, 'option_name' => 'plugin_delta_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'delta', 'priority' => 11]]), 'autoload' => 'no']],
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
    'applicationUse' => 'Preflight copied wp_options JSONB plugin settings with optional JSON paths before admitting current/next rows to storage.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
