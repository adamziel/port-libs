<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
];

$rows = SQLiteSelectSql::execute(
    "SELECT option_name AS name, bytes, sum(bytes) OVER (ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS next_group_bytes, group_concat(option_name) OVER (ORDER BY bytes RANGE BETWEEN CURRENT ROW AND 15 FOLLOWING) AS range_names FROM wp_options ORDER BY bytes, option_id",
    ['wp_options' => $options],
);

echo json_encode([
    'scenario' => 'application-window-groups-range-current-next',
    'sqlShape' => 'SELECT sum()/group_concat() OVER ORDER BY ... GROUPS/RANGE BETWEEN CURRENT ROW AND N FOLLOWING',
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
