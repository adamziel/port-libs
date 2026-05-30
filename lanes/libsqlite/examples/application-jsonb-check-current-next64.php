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
  CHECK(json_type(option_value, '$.plugin') = 'object'),
  CHECK(json_extract(option_value, '$.plugin.slug') <> ''),
  CHECK(json_extract(option_value, '$.plugin.rank') >= 1 AND json_extract(option_value, '$.plugin.rank') <= 99),
  CHECK(json_extract(option_value, '$.plugin.channel') IN ('stable','beta','nightly')),
  CHECK(json_array_length(option_value, '$.plugin.rules') >= 1),
  CHECK(json_extract(option_value, '$.plugin.version') IS NOT NULL)
)
SQL;

$plan = SQLiteJsonbCheckCurrentNextPlan::plan($schema, [
    ['option_id' => 201, 'option_name' => 'plugin_alpha_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'alpha', 'rank' => 10, 'channel' => 'stable', 'rules' => ['cache'], 'enabled' => true, 'version' => '1.0']]), 'autoload' => 'yes'],
    ['option_id' => 202, 'option_name' => 'plugin_beta_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'beta', 'rank' => 20, 'channel' => 'beta', 'rules' => ['seo'], 'enabled' => false, 'version' => '2.0']]), 'autoload' => 'yes'],
], [
    ['op' => 'UPDATE', 'rowid' => 201, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 15],
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'beta'],
    ]],
    ['op' => 'UPDATE', 'rowid' => 202, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 120],
    ]],
    ['op' => 'INSERT', 'row' => ['option_id' => 203, 'option_name' => 'plugin_delta_settings', 'option_value' => $jsonb(['plugin' => ['slug' => 'delta', 'rank' => 40, 'channel' => 'stable', 'rules' => ['import'], 'enabled' => false, 'version' => '4.0']]), 'autoload' => 'no']],
]);

echo json_encode([
    'scenario' => 'application-jsonb-check-current-next64',
    'changes' => $plan['changes'],
    'rejectedChanges' => $plan['rejectedChanges'],
    'acceptedRowids' => array_column($plan['accepted'], 'rowid'),
    'rejectedRowids' => array_column($plan['rejected'], 'rowid'),
    'failedCheck' => $plan['rejected'][0]['checks'][3]['sql'],
    'applicationUse' => 'Preflight copied wp_options JSONB plugin settings against table CHECK constraints before current/next import rows are admitted to storage and index maintenance.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
