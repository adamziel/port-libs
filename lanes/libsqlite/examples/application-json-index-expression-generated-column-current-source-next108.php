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
  plugin_slug TEXT GENERATED ALWAYS AS (json_extract(option_value, '$.plugin.slug')) STORED,
  plugin_rank INTEGER AS (json_extract(option_value, '$.plugin.rank')) VIRTUAL
)
SQL;

$plan = SQLiteGeneratedJsonPathIndexPlan::btreeYieldPlan($createTableSql, [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'option_value' => '{"plugin":{"slug":"alpha","rank":1}}', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_beta', 'option_value' => '{"plugin":{"slug":"beta","rank":2}}', 'autoload' => 'yes'],
], [
    ['name' => 'idx_expr_plugin_slug', 'rootPage' => 41, 'unique' => true, 'sql' => "CREATE UNIQUE INDEX idx_expr_plugin_slug ON wp_options(json_extract(option_value, '$.plugin.slug') COLLATE NOCASE) WHERE plugin_slug IS NOT NULL"],
    ['name' => 'idx_expr_plugin_rank', 'rootPage' => 42, 'sql' => "CREATE INDEX idx_expr_plugin_rank ON wp_options(json_extract(option_value, '$.plugin.rank') DESC) WHERE plugin_rank IS NOT NULL"],
], [
    ['rowid' => 1, 'column' => 'option_value', 'mutations' => [
        ['function' => 'json_set', 'path' => '$.plugin.slug', 'value' => 'alpha-pro'],
        ['function' => 'json_set', 'path' => '$.plugin.rank', 'value' => 10],
    ]],
]);

echo json_encode([
    'changes' => $plan['changes'],
    'expression_indexes' => array_map(
        static fn (array $update): array => [
            'index' => $update['index'],
            'source' => $update['source'],
            'path' => $update['path'],
            'function' => $update['expressionFunction'],
            'current' => $update['current'],
            'next' => $update['next'],
        ],
        $plan['index_updates'],
    ),
    'btree_action_count' => $plan['btree_action_count'],
    'updated_option_value' => $plan['after'][0]['option_value'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
