<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wpOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20, 'tier' => 'core'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20, 'tier' => 'core'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 12, 'tier' => 'theme'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'tier' => 'cache'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 12, 'tier' => 'cache'],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => null, 'tier' => 'orphan'],
];

$rows = SQLiteSelectSql::execute(
    'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) > 20 OR autoload IS NULL ORDER BY total DESC',
    ['wp_options' => $wpOptions],
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows through additional HAVING predicate combinations over grouped aggregate SELECT text without requiring ext/sqlite.',
    'sql' => 'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) > 20 OR autoload IS NULL ORDER BY total DESC',
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
