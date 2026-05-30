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
  CHECK(json_extract(option_value, '$.plugin.channel') = 'stable' OR json_extract(option_value, '$.plugin.channel') = 'beta'),
  CHECK(NOT json_extract(option_value, '$.plugin.deprecated')),
  CHECK(json_extract(option_value, '$.plugin.requires') IS NULL OR json_extract(option_value, '$.plugin.requires') <= 6.7),
  CHECK(NOT (json_extract(option_value, '$.plugin.channel') = 'beta' AND json_extract(option_value, '$.plugin.rank') > 50))
)
SQL;

$plan = SQLiteJsonbCheckCurrentNextPlan::plan($schema, [
    ['option_id' => 301, 'option_name' => 'plugin_alpha_settings', 'option_value' => $jsonb(['plugin' => ['channel' => 'stable', 'rank' => 10, 'deprecated' => false, 'requires' => 6.5]]), 'autoload' => 'yes'],
    ['option_id' => 302, 'option_name' => 'plugin_beta_settings', 'option_value' => $jsonb(['plugin' => ['channel' => 'beta', 'rank' => 40, 'deprecated' => false]]), 'autoload' => 'yes'],
], [
    ['op' => 'UPDATE', 'rowid' => 301, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'beta'],
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 35],
    ]],
    ['op' => 'UPDATE', 'rowid' => 302, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 75],
    ]],
    ['op' => 'INSERT', 'row' => ['option_id' => 303, 'option_name' => 'plugin_release_settings', 'option_value' => $jsonb(['plugin' => ['channel' => 'stable', 'rank' => 15, 'deprecated' => false]]), 'autoload' => 'yes']],
]);

echo json_encode([
    'scenario' => 'application-jsonb-check-current-next67',
    'changes' => $plan['changes'],
    'rejectedChanges' => $plan['rejectedChanges'],
    'acceptedRowids' => array_column($plan['accepted'], 'rowid'),
    'rejectedRowids' => array_column($plan['rejected'], 'rowid'),
    'failedCheck' => $plan['rejected'][0]['checks'][4]['sql'],
    'applicationUse' => 'Preflight copied wp_options JSONB plugin settings with OR/NOT CHECK guards before next import rows are admitted to storage.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
