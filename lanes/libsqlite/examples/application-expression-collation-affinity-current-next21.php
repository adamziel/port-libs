<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '10', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'SiteURL', 'option_value' => '010', 'autoload' => 'no'],
    ['option_id' => 3, 'option_name' => 'plugin_alpha', 'option_value' => 'not-a-number', 'autoload' => null],
    ['option_id' => 4, 'option_name' => 'PLUGIN_ALPHA', 'option_value' => '10e0', 'autoload' => null],
];

$caseBuckets = SQLiteSelectSql::execute(
    "SELECT option_id, CASE option_name COLLATE NOCASE WHEN 'SITEURL' THEN 'url' WHEN 'plugin_alpha' THEN 'plugin' ELSE 'other' END AS bucket FROM wp_options ORDER BY option_id",
    ['wp_options' => $options],
);

$numericBuckets = SQLiteSelectSql::execute(
    "SELECT option_id, CASE CAST(option_value AS NUMERIC) WHEN 10 THEN 'ten' ELSE 'other' END AS bucket FROM wp_options ORDER BY option_id",
    ['wp_options' => $options],
);

echo json_encode([
    'scenario' => 'application-expression-collation-affinity-current-next21',
    'caseFoldedBuckets' => array_column($caseBuckets, 'bucket', 'option_id'),
    'numericAffinityBuckets' => array_column($numericBuckets, 'bucket', 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
