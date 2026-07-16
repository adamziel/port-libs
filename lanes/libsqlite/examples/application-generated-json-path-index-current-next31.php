<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteGeneratedJsonPathIndexPlan;

$createTableSql = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT,
  autoload TEXT,
  plugin_enabled INTEGER GENERATED ALWAYS AS (json_extract(option_value, '$.plugin.enabled')) STORED,
  plugin_priority INTEGER AS (json_extract(option_value, '$.plugin.priority')) VIRTUAL
)
SQL;

$plan = SQLiteGeneratedJsonPathIndexPlan::plan($createTableSql, [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => '{"plugin":{"enabled":0,"priority":2}}', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_beta', 'option_value' => '{"plugin":{"enabled":1,"priority":5}}', 'autoload' => 'no'],
], [
    ['name' => 'idx_plugin_enabled', 'rootPage' => 7, 'sql' => 'CREATE INDEX idx_plugin_enabled ON wp_options(plugin_enabled) WHERE plugin_enabled IS NOT NULL'],
    ['name' => 'idx_plugin_priority', 'rootPage' => 8, 'sql' => 'CREATE INDEX idx_plugin_priority ON wp_options(plugin_priority DESC) WHERE plugin_priority IS NOT NULL'],
], [
    ['rowid' => 1, 'mutations' => [
        ['function' => 'json_set', 'path' => '$.plugin.enabled', 'value' => 1],
        ['function' => 'json_set', 'path' => '$.plugin.priority', 'value' => 7],
    ]],
]);

echo json_encode([
    'changes' => $plan['changes'],
    'generated_columns' => $plan['generated_columns'],
    'index_updates' => $plan['index_updates'],
    'updated_value' => $plan['after'][0]['option_value'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
