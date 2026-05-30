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
  CHECK(json_extract(option_value, '$.plugin.channel') NOT IN ('nightly','dev','blocked')),
  CHECK(json_extract(option_value, '$.plugin.rank') NOT BETWEEN 51 AND 99),
  CHECK(json_extract(option_value, '$.plugin.min_wp') NOT BETWEEN 6.8 AND 7.9)
)
SQL;

$plan = SQLiteJsonbCheckCurrentNextPlan::plan($schema, [
    ['option_id' => 401, 'option_name' => 'plugin_alpha_settings', 'option_value' => $jsonb(['plugin' => ['channel' => 'stable', 'rank' => 25, 'min_wp' => 6.5]]), 'autoload' => 'yes'],
    ['option_id' => 402, 'option_name' => 'plugin_beta_settings', 'option_value' => $jsonb(['plugin' => ['channel' => 'beta', 'rank' => 50, 'min_wp' => 6.7]]), 'autoload' => 'yes'],
], [
    ['op' => 'UPDATE', 'rowid' => 401, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'beta'],
        ['function' => 'jsonb_set', 'path' => '$.plugin.rank', 'value' => 45],
    ]],
    ['op' => 'UPDATE', 'rowid' => 402, 'mutations' => [
        ['function' => 'jsonb_set', 'path' => '$.plugin.channel', 'value' => 'nightly'],
    ]],
    ['op' => 'INSERT', 'row' => ['option_id' => 403, 'option_name' => 'plugin_future_settings', 'option_value' => $jsonb(['plugin' => ['channel' => 'stable', 'rank' => 10, 'min_wp' => 7.0]]), 'autoload' => 'no']],
    ['op' => 'INSERT', 'row' => ['option_id' => 404, 'option_name' => 'plugin_safe_settings', 'option_value' => $jsonb(['plugin' => ['channel' => 'stable', 'rank' => 100, 'min_wp' => 8.0]]), 'autoload' => 'yes']],
]);

echo json_encode([
    'scenario' => 'application-jsonb-check-current-next69',
    'changes' => $plan['changes'],
    'rejectedChanges' => $plan['rejectedChanges'],
    'acceptedRowids' => array_column($plan['accepted'], 'rowid'),
    'rejectedRowids' => array_column($plan['rejected'], 'rowid'),
    'failedChecks' => array_map(static fn (array $row): string => $row['checks'][array_search(false, array_column($row['checks'], 'ok'), true)]['sql'], $plan['rejected']),
    'applicationUse' => 'Preflight copied wp_options JSONB plugin settings with SQLite NOT IN and NOT BETWEEN CHECK guards before import rows are admitted.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
