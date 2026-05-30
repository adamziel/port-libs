<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeAggregateOrderByCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'priority' => 20],
    ['rowid' => 2, 'autoload' => 'yes', 'option_name' => 'home', 'priority' => 30],
    ['rowid' => 3, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'priority' => 10],
    ['rowid' => 4, 'autoload' => 'no', 'option_name' => 'transient_timeout_feed', 'priority' => 5],
    ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'transient_feed', 'priority' => null],
];

$cursor = new SQLiteVdbeAggregateOrderByCursor(
    $options,
    ['autoload'],
    [['column' => 'priority', 'direction' => 'DESC', 'nulls' => 'LAST']]
);

echo json_encode($cursor->drainSummaries('option_name'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
