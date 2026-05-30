<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'PortLibs\\LibSqlite\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ],
    'incoming_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'source' => 'update'],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'source' => 'insert'],
    ],
    'option_labels' => [
        ['option_id' => 1, 'label' => 'existing-url'],
        ['option_id' => 4, 'label' => 'new-plugin'],
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT coalesce(i.option_name, c.option_name) AS option_name, coalesce(l.label, 'current-only') AS label
     FROM (wp_options AS c FULL JOIN incoming_options AS i USING(option_id))
     LEFT JOIN option_labels AS l ON l.option_id = coalesce(i.option_id, c.option_id)
     ORDER BY option_name",
    $tables,
);

echo json_encode($rows, JSON_PRETTY_PRINT) . PHP_EOL;
