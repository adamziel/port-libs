<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'main.wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://main.example', 'autoload' => 'yes'],
    ],
    'temp.wp_options' => [
        ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://temp-import.example', 'autoload' => 'no'],
    ],
    'site.wp_options' => [
        ['blog_id' => 3, 'option_id' => 201, 'option_name' => 'siteurl', 'option_value' => 'https://attached-site.example', 'autoload' => 'yes'],
    ],
];

echo json_encode([
    'applicationUse' => 'Preview copied wp_options SELECT text where TEMP import rows shadow main and attached databases until schema-qualified names pin the current source, without requiring ext/sqlite.',
    'unqualified_siteurl' => SQLiteSelectSql::execute("SELECT option_value FROM wp_options WHERE option_name = 'siteurl'", $tables)[0]['option_value'],
    'main_siteurl' => SQLiteSelectSql::execute("SELECT option_value FROM main.wp_options WHERE option_name = 'siteurl'", $tables)[0]['option_value'],
    'attached_siteurl' => SQLiteSelectSql::execute("SELECT option_value FROM site.wp_options WHERE option_name = 'siteurl'", $tables)[0]['option_value'],
    'temp_to_attached_join' => SQLiteSelectSql::execute("SELECT t.option_value AS temp_value, s.option_value AS site_value FROM temp.wp_options AS t JOIN site.wp_options AS s ON s.option_name = t.option_name", $tables)[0],
], JSON_PRETTY_PRINT) . "\n";
