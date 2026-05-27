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
        ['option_id' => 1, 'meta_key' => 'source_url', 'rank' => 20],
        ['option_id' => 1, 'meta_key' => 'checksum', 'rank' => 10],
        ['option_id' => 2, 'meta_key' => 'source_url', 'rank' => 30],
        ['option_id' => 3, 'meta_key' => 'source_title', 'rank' => 15],
    ],
    'import_stage' => [
        ['option_id' => 1, 'stage_key' => 'primary', 'priority' => 5],
        ['option_id' => 2, 'stage_key' => 'primary', 'priority' => 15],
        ['option_id' => 4, 'stage_key' => 'cache', 'priority' => 60],
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT option_name,
            (SELECT d.import_key
               FROM (SELECT option_id, meta_key AS import_key, rank
                       FROM import_option_meta
                      WHERE option_id = wp_options.option_id
                     UNION ALL
                     SELECT option_id, stage_key AS import_key, priority AS rank
                       FROM import_stage
                      WHERE option_id = wp_options.option_id
                      ORDER BY rank
                      LIMIT 1) AS d
              WHERE d.option_id = wp_options.option_id) AS first_import_key
       FROM wp_options
      ORDER BY option_id",
    $tables
);

echo json_encode($rows, JSON_PRETTY_PRINT) . PHP_EOL;
