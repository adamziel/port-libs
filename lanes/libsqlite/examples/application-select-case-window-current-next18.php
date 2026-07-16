<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9, 'slot' => 'core'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 9, 'slot' => 'core'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 5, 'slot' => 'site'],
    ['option_id' => 4, 'option_name' => 'cron', 'autoload' => 'no', 'weight' => 4, 'slot' => 'system'],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 4, 'slot' => 'system'],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'weight' => 1, 'slot' => 'theme'],
    ['option_id' => 7, 'option_name' => 'plugin_alpha', 'autoload' => null, 'weight' => 3, 'slot' => 'plugin'],
    ['option_id' => 8, 'option_name' => 'plugin_beta', 'autoload' => null, 'weight' => 8, 'slot' => 'plugin'],
];

$sql = "SELECT option_name, CASE autoload WHEN 'yes' THEN 'autoloaded' WHEN 'no' THEN 'manual' ELSE 'unknown' END AS bucket, row_number() OVER (PARTITION BY CASE autoload WHEN 'yes' THEN 'autoloaded' WHEN 'no' THEN 'manual' ELSE 'unknown' END ORDER BY CASE slot WHEN 'plugin' THEN 0 ELSE 1 END, weight DESC, option_id) AS bucket_rank FROM wp_options ORDER BY bucket, bucket_rank";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows bucketed through parser-level CASE expressions and ranked by native window execution without requiring ext/sqlite.',
    'sql' => $sql,
    'buckets' => array_count_values(array_column($rows, 'bucket')),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
