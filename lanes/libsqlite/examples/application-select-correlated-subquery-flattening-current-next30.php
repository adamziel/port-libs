<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
        ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no'],
    ],
    'import_option_meta' => [
        ['option_id' => 1, 'meta_key' => 'source_url'],
        ['option_id' => 2, 'meta_key' => 'source_url'],
        ['option_id' => 3, 'meta_key' => 'source_title'],
    ],
    'import_stage' => [
        ['option_id' => 1, 'status' => 'ready'],
        ['option_id' => 2, 'status' => 'ready'],
        ['option_id' => 3, 'status' => 'review'],
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT option_name
       FROM wp_options
      WHERE EXISTS (
            SELECT 1
              FROM (
                    SELECT option_id
                      FROM import_option_meta
                     WHERE option_id = wp_options.option_id
                    UNION
                    SELECT option_id
                      FROM import_stage
                     WHERE option_id = wp_options.option_id
                       AND status = 'ready'
                   ) AS imported
             WHERE imported.option_id = wp_options.option_id
      )
      ORDER BY option_id",
    $tables,
);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
