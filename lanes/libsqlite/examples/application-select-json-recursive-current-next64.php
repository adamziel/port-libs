<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;

$tables = [
    'wp_options' => [
        ['option_id' => 101, 'option_name' => 'wp_route_seed', 'autoload' => 'yes', 'option_value' => '{"next":[102],"rules":[{"slug":"cache","priority":30},{"slug":"seo","priority":20}]}'],
        ['option_id' => 102, 'option_name' => 'wp_route_cache', 'autoload' => 'yes', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['next' => [103], 'rules' => [['slug' => 'cache-warm', 'priority' => 50], ['slug' => 'cache-purge', 'priority' => 40]]]))],
        ['option_id' => 103, 'option_name' => 'wp_route_leaf', 'autoload' => 'yes', 'option_value' => '{"next":[],"rules":[{"slug":"sync","priority":60},{"slug":"cleanup","priority":22}]}'],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE route(option_id, depth) AS MATERIALIZED (
    VALUES (101, 0)
    UNION
    SELECT CAST(edge.atom AS INTEGER), route.depth + 1
      FROM route
      JOIN wp_options AS host ON host.option_id = route.option_id
      JOIN json_each(host.option_value, '$.next') AS edge ON edge.type = 'integer'
)
SELECT route.option_id AS option_id,
       route.depth AS depth,
       host.option_name AS option_name,
       rule.key AS attr,
       rule.atom AS atom,
       rule.fullkey AS fullkey
  FROM route
  JOIN wp_options AS host ON host.option_id = route.option_id
  JOIN json_tree(host.option_value, '$.rules') AS rule ON rule.type IN ('text', 'integer')
 ORDER BY route.depth, route.option_id, rule.fullkey
SQL;

$plan = SQLiteSelectRecursiveJsonMaterialization::materialize($sql, $tables, ['option_id', 'attr'], ['fullkey']);
$window = SQLiteSelectRecursiveJsonMaterialization::jsonCurrentNextWindow($plan, ['option_id', 'attr'], ['depth', 'fullkey']);

echo json_encode([
    'rows' => count($plan['rows']),
    'windowRows' => count($window),
    'first' => [
        'option_id' => $window[0]['row']['option_id'],
        'attr' => $window[0]['row']['attr'],
        'atom' => $window[0]['row']['atom'],
        'next' => $window[0]['next']['atom'] ?? null,
    ],
    'dependencies' => array_values(array_filter(
        $plan['dependencies'],
        static fn (string $dependency): bool => str_contains($dependency, 'recursive-current-next'),
    )),
], JSON_PRETTY_PRINT) . "\n";
