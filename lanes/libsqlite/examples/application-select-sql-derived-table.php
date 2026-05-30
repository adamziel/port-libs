<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test/home', 'autoload' => 'yes', 'bytes' => 29],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Port Libs', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'option_value' => 'plugin-cache', 'autoload' => 'no', 'bytes' => 44],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'option_value' => '{"color":"blue"}', 'autoload' => 'yes', 'bytes' => 16],
];

$rows = SQLiteSelectSql::execute(
    "SELECT picked_name, picked_bytes FROM (SELECT option_name, bytes FROM wp_options WHERE autoload = 'yes' ORDER BY bytes DESC LIMIT 3) AS picked(picked_name, picked_bytes) WHERE picked_bytes >= 16 ORDER BY picked_bytes DESC",
    ['wp_options' => $options],
);

foreach ($rows as $row) {
    echo $row['picked_name'] . ':' . $row['picked_bytes'] . PHP_EOL;
}
