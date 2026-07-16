<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAnalyzeStatPlanner;

$statRows = [
    ['tbl' => 'wp_options', 'idx' => null, 'stat' => '12000'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name', 'stat' => '12000 1'],
    ['tbl' => 'wp_options', 'idx' => 'wp_options_name_autoload', 'stat' => '12000 1 1'],
];

$indexes = [
    ['name' => 'wp_options_name', 'table' => 'wp_options', 'columns' => ['option_name'], 'unique' => true],
    ['name' => 'wp_options_name_autoload', 'table' => 'wp_options', 'columns' => ['option_name', 'autoload']],
];

$constraints = [
    ['column' => 'option_name', 'operator' => '>=', 'value' => '_transient_'],
    ['column' => 'option_name', 'operator' => '<', 'value' => '_transient_timeout_'],
    ['column' => 'autoload', 'operator' => '=', 'value' => 'no'],
];

$plan = SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, 'wp_options', $constraints);

echo 'application-analyze-stat-planner-current-next75' . PHP_EOL;
echo 'index=' . ($plan['index'] ?? 'table-scan') . PHP_EOL;
echo 'matched=' . implode(',', $plan['matchedColumns']) . PHP_EOL;
echo 'operator=' . $plan['matchedConstraints'][0]['operator'] . PHP_EOL;
echo 'bounds=' . implode('..', $plan['matchedConstraints'][0]['values']) . PHP_EOL;
echo 'estimatedRows=' . $plan['estimatedRows'] . PHP_EOL;
echo 'detail=' . $plan['detail'] . PHP_EOL;
