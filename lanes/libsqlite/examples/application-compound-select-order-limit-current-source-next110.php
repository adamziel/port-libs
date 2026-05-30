<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
foreach (glob(__DIR__ . '/../src/*.php') as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteSelectSql;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20, 'priority' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20, 'priority' => 20],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 12, 'priority' => 10],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'bytes' => 64, 'priority' => 40],
];
$stage = [
    ['option_id' => 10, 'option_name' => 'blogdescription', 'autoload' => 'yes', 'bytes' => 18, 'priority' => 15],
    ['option_id' => 11, 'option_name' => 'active_plugins', 'autoload' => 'no', 'bytes' => 80, 'priority' => 50],
    ['option_id' => 12, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'bytes' => 120, 'priority' => 60],
    ['option_id' => 13, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20, 'priority' => 35],
];

$sql = "SELECT option_name AS name, bytes + priority AS import_weight
FROM wp_options
UNION ALL
SELECT option_name AS name, bytes + priority AS import_weight
FROM wp_options_stage
ORDER BY bytes + priority DESC, name
LIMIT 4";

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $current, 'wp_options_stage' => $stage]);

echo json_encode([
    'description' => 'Copied wp_options current/staged rows use compound SELECT final ORDER BY expressions plus LIMIT after UNION ALL to pick the heaviest import actions without ext/sqlite.',
    'sql' => preg_replace('/\s+/', ' ', trim($sql)),
    'names' => array_column($rows, 'name'),
    'weights' => array_column($rows, 'import_weight'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
