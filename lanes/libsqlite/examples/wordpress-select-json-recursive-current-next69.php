<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $sourceFile) {
    require_once $sourceFile;
}

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;

$options = [
    ['option_id' => 201, 'option_name' => 'wp_nav_seed', 'autoload' => 'yes', 'option_value' => '{"next":[202,203],"rules":[{"slug":"root","priority":30,"enabled":1},{"slug":"landing","priority":18,"enabled":1}]}'],
    ['option_id' => 202, 'option_name' => 'wp_nav_cache', 'autoload' => 'yes', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['next' => [204, 205], 'rules' => [['slug' => 'cache', 'priority' => 50, 'enabled' => 1], ['slug' => 'purge', 'priority' => 12, 'enabled' => 0]]]))],
    ['option_id' => 203, 'option_name' => 'wp_nav_media', 'autoload' => 'no', 'option_value' => '{"next":[205],"rules":[{"slug":"gallery","priority":42,"enabled":1},{"slug":"video","priority":9,"enabled":0}]}'],
    ['option_id' => 204, 'option_name' => 'wp_nav_store', 'autoload' => 'yes', 'option_value' => '{"next":[206],"rules":[{"slug":"cart","priority":38,"enabled":1},{"slug":"checkout","priority":35,"enabled":1}]}'],
    ['option_id' => 205, 'option_name' => 'wp_nav_forms', 'autoload' => 'yes', 'option_value' => '{"next":[206],"rules":[{"slug":"contact","priority":28,"enabled":1},{"slug":"captcha","priority":17,"enabled":1}]}'],
    ['option_id' => 206, 'option_name' => 'wp_nav_leaf', 'autoload' => 'yes', 'option_value' => '{"next":[202],"rules":[{"slug":"sync","priority":60,"enabled":1},{"slug":"cleanup","priority":8,"enabled":0}]}'],
];

$sql = <<<'SQL'
WITH RECURSIVE nav(option_id, depth, parent_name) AS MATERIALIZED (
    VALUES (201, 0, 'seed')
    UNION
    SELECT CAST(edge.atom AS INTEGER), nav.depth + 1, host.option_name
      FROM nav
      JOIN wp_options AS host ON host.option_id = nav.option_id
      JOIN json_each(host.option_value, '$.next') AS edge ON edge.type = 'integer'
     WHERE nav.depth < 4
)
SELECT nav.option_id AS option_id,
       nav.depth AS depth,
       host.option_name AS option_name,
       field.key AS attr,
       field.atom AS atom,
       field.fullkey AS fullkey
  FROM nav
  JOIN wp_options AS host ON host.option_id = nav.option_id
  JOIN json_tree(host.option_value, '$.rules') AS field ON field.type IN ('text', 'integer', 'true', 'false')
 ORDER BY nav.depth, nav.option_id, field.fullkey
SQL;

$plan = SQLiteSelectRecursiveJsonMaterialization::materialize($sql, ['wp_options' => $options], ['option_id', 'attr'], ['fullkey']);
$tape = SQLiteSelectRecursiveJsonMaterialization::recursiveJsonYieldTape($plan, ['option_id', 'depth'], ['attr', 'atom', 'fullkey']);

echo json_encode([
    'materializedRows' => count($plan['rows']),
    'yieldTapeRows' => count($tape),
    'firstCurrentKey' => $tape[0]['currentKey'],
    'firstNextKey' => $tape[0]['nextKey'],
    'firstNextAtoms' => array_column($tape[0]['nextJson'], 'atom'),
    'skippedCycle' => $plan['trace']['skipped'][0] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
