<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;
use PortLibs\LibSqlite\SQLiteUpdateFromSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"cache","enabled":true},{"name":"seo","enabled":false}]}',
        'autoload' => 'no',
        'blog_id' => 1,
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":[{"name":"forms","enabled":true}]}',
        'autoload' => 'no',
        'blog_id' => 1,
    ],
    [
        'option_id' => 3,
        'option_name' => 'theme_gamma_settings',
        'option_value' => '{"rules":[{"name":"theme","enabled":true}]}',
        'autoload' => 'yes',
        'blog_id' => 1,
    ],
];

$sourceSql = <<<'SQL'
WITH RECURSIVE incoming(option_name,new_value,new_autoload,depth,fullkey) AS (
    SELECT o.option_name,
           j.atom || ':' || j.fullkey AS new_value,
           CASE j.atom WHEN 1 THEN 'yes' ELSE 'no' END AS new_autoload,
           0 AS depth,
           j.fullkey AS fullkey
      FROM wp_options AS o
      JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'enabled'
     WHERE o.option_name GLOB 'plugin_*'
    UNION ALL
    SELECT option_name, new_value || ':final', new_autoload, depth + 1, fullkey
      FROM incoming
     WHERE depth < 1
)
SELECT option_name, new_value, new_autoload, depth, fullkey
  FROM incoming
 ORDER BY option_name, depth, fullkey
SQL;

$updateSql = <<<'SQL'
WITH RECURSIVE incoming(option_name,new_value,new_autoload,depth,fullkey) AS (
    SELECT o.option_name,
           j.atom || ':' || j.fullkey AS new_value,
           CASE j.atom WHEN 1 THEN 'yes' ELSE 'no' END AS new_autoload,
           0 AS depth,
           j.fullkey AS fullkey
      FROM wp_options AS o
      JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'enabled'
     WHERE o.option_name GLOB 'plugin_*'
    UNION ALL
    SELECT option_name, new_value || ':final', new_autoload, depth + 1, fullkey
      FROM incoming
     WHERE depth < 1
)
UPDATE wp_options AS current
   SET option_value = incoming.new_value,
       autoload = incoming.new_autoload
  FROM incoming
 WHERE incoming.option_name = current.option_name
SQL;

$sourceRows = SQLiteSelectSql::execute($sourceSql, ['wp_options' => $options]);
$result = SQLiteUpdateFromSql::execute($updateSql, ['wp_options' => $options]);

echo json_encode([
    'scenario' => 'application-json-table-update-from-recursive-current-next36',
    'applicationUse' => 'Copied wp_options plugin JSON settings can seed a recursive CTE from json_tree() and feed UPDATE FROM current-row mutations, preserving duplicate-source last-match behavior, current/next recursive source rows, and autoload derivation without requiring ext/sqlite.',
    'sourceSql' => $sourceSql,
    'sourceRows' => $sourceRows,
    'updateSql' => $updateSql,
    'changes' => $result['changes'],
    'updatedRows' => $result['updated_rows'],
    'after' => $result['after'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
