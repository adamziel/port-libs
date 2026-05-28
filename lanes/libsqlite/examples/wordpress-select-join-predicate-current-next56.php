<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl'],
        ['option_id' => 2, 'option_name' => 'home'],
    ],
    'incoming_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'source' => 'copied'],
        ['option_id' => 3, 'option_name' => 'active_plugins', 'source' => 'insert'],
    ],
    'option_events' => [
        ['option_id' => 1, 'event' => 'update'],
        ['option_id' => 3, 'event' => 'insert'],
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT i.option_name AS name, e.event AS event
     FROM wp_options AS c
     RIGHT JOIN incoming_options AS i USING(option_id)
     JOIN option_events AS e USING(option_id)
     WHERE i.source = 'insert'",
    $tables,
);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
