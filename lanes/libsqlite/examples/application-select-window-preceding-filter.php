<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_size' => 10],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'option_size' => 20],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'no', 'option_size' => 30],
    ['option_id' => 4, 'option_name' => 'cron', 'autoload' => 'no', 'option_size' => 40],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'option_size' => 50],
];

$sql = <<<'SQL'
SELECT option_id,
       option_name,
       sum(option_size) FILTER (WHERE autoload = 'yes')
           OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW EXCLUDE CURRENT ROW) AS prior_autoload_bytes
FROM wp_options
ORDER BY option_id
SQL;

echo json_encode([
    'scenario' => 'application-select-window-preceding-filter',
    'sql' => $sql,
    'rows' => SQLiteSelectSql::execute($sql, ['wp_options' => $rows]),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
