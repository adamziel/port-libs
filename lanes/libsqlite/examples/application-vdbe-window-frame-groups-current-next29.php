<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['option_id' => 1, 'option_name' => 'alpha_cache', 'autoload' => 'yes', 'bytes' => 10],
    ['option_id' => 2, 'option_name' => 'alpha_cache', 'autoload' => 'no', 'bytes' => 10],
    ['option_id' => 3, 'option_name' => 'beta_cache', 'autoload' => 'yes', 'bytes' => 10],
    ['option_id' => 4, 'option_name' => 'cron_lock', 'autoload' => 'no', 'bytes' => 20],
    ['option_id' => 5, 'option_name' => 'cron_lock', 'autoload' => 'yes', 'bytes' => 20],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bytes' => 30],
];

$result = SQLiteSelectSql::execute(
    'SELECT option_id, option_name, sum(bytes) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS peer_bytes FROM wp_options ORDER BY option_id',
    ['wp_options' => $rows],
);

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
