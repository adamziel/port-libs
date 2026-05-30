<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUpdateFromSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 4, 'option_name' => 'widget_text', 'option_value' => 'a:1:{}', 'autoload' => 'no', 'blog_id' => 2],
    ],
];

$sql = "WITH incoming(option_name,new_value,new_autoload,blog_id) AS (
    VALUES
        ('siteurl','https://new.example','yes',1),
        ('home','https://new.example','yes',1),
        ('blogname','Ported Site','yes',1),
        ('widget_text','a:2:{}','no',2)
)
UPDATE wp_options AS current
SET option_value = incoming.new_value, autoload = incoming.new_autoload
FROM incoming
WHERE incoming.option_name = current.option_name AND incoming.blog_id = current.blog_id";

$result = SQLiteUpdateFromSql::execute($sql, $tables, [], [['option_name']]);

$orderedSql = "WITH incoming(option_name,new_value,rank) AS (
    VALUES
        ('siteurl','https://ordered.example',1),
        ('home','https://ordered-home.example',2),
        ('blogname','Ordered Site',3)
)
UPDATE wp_options AS current
SET option_value = incoming.new_value
FROM incoming
WHERE incoming.option_name = current.option_name
ORDER BY incoming.rank DESC
LIMIT 2";
$orderedResult = SQLiteUpdateFromSql::execute($orderedSql, $tables, [], [['option_name']]);

echo json_encode([
    'scenario' => 'application-update-from-current-next25',
    'changes' => $result['changes'],
    'updated_option_names' => array_column($result['updated_rows'], 'option_name'),
    'select_sql_preserves_cte' => str_starts_with(SQLiteUpdateFromSql::plan($sql, $tables)['select_sql'], 'WITH incoming'),
    'ordered_limit_changes' => $orderedResult['changes'],
    'ordered_limit_updated_option_names' => array_column($orderedResult['updated_rows'], 'option_name'),
    'ordered_limit_tail' => SQLiteUpdateFromSql::plan($orderedSql, $tables)['order_limit_sql'],
    'final_values' => array_column($result['after'], 'option_value', 'option_name'),
], JSON_PRETTY_PRINT) . "\n";
