<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'flags' => 5, 'mask' => 3, 'option_value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'flags' => 6, 'mask' => 2, 'option_value' => 'https://example.test'],
    ['option_id' => 3, 'option_name' => 'blog_public', 'autoload' => 'yes', 'flags' => 1, 'mask' => 4, 'option_value' => '1'],
    ['option_id' => 4, 'option_name' => 'orphaned_flag', 'autoload' => 'no', 'flags' => null, 'mask' => 7, 'option_value' => null],
];

$rows = SQLiteSelectSql::execute(
    "SELECT option_name AS name, flags & mask AS active_bits, ~flags & 7 AS inactive_bits, flags << 1 AS doubled_flags
     FROM wp_options
     WHERE flags & 1 = 1
     ORDER BY ~flags ASC, name",
    ['wp_options' => $options],
);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
