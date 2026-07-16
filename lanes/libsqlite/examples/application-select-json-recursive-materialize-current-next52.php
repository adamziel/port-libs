<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'plugin_root_routes', 'autoload' => 'yes', 'option_value' => '{"next":[2,3],"rules":[{"name":"root-cache","priority":7},{"name":"root-seo","priority":5}]}'],
    ['option_id' => 2, 'option_name' => 'plugin_media_routes', 'autoload' => 'yes', 'option_value' => '{"next":[4,5],"rules":[{"name":"media-gallery","priority":9},{"name":"media-video","priority":3}]}'],
    ['option_id' => 3, 'option_name' => 'plugin_forms_routes', 'autoload' => 'no', 'option_value' => '{"next":[5],"rules":[{"name":"forms-contact","priority":4},{"name":"forms-captcha","priority":2}]}'],
    ['option_id' => 4, 'option_name' => 'plugin_shop_routes', 'autoload' => 'yes', 'option_value' => '{"next":[],"rules":[{"name":"shop-cart","priority":8},{"name":"shop-checkout","priority":6}]}'],
    ['option_id' => 5, 'option_name' => 'plugin_cache_routes', 'autoload' => 'yes', 'option_value' => '{"next":[],"rules":[{"name":"cache-page","priority":10},{"name":"cache-object","priority":1}]}'],
];

$sql = "WITH RECURSIVE crawl(option_id, depth, route) AS MATERIALIZED (
            VALUES (1, 0, 'seed')
            UNION
            SELECT CAST(edge.atom AS INTEGER), crawl.depth + 1, host.option_name
              FROM crawl
              JOIN wp_options AS host ON host.option_id = crawl.option_id
              JOIN json_each(host.option_value, '$.next') AS edge ON edge.type = 'integer'
             WHERE crawl.depth < 3
             ORDER BY depth DESC, option_id ASC
             LIMIT 6 OFFSET 1
        )
        SELECT crawl.option_id AS option_id,
               crawl.depth AS depth,
               host.option_name AS option_name,
               rule.key AS attr,
               rule.atom AS atom,
               rule.fullkey AS fullkey
          FROM crawl
          JOIN wp_options AS host ON host.option_id = crawl.option_id
          JOIN json_tree(host.option_value, '$.rules') AS rule ON rule.type IN ('text', 'integer')
         ORDER BY crawl.depth, crawl.option_id, rule.fullkey";

$plan = SQLiteSelectRecursiveJsonMaterialization::materialize($sql, ['wp_options' => $options], ['option_id', 'attr'], ['fullkey']);
$summary = [
    'scenario' => 'wp_options recursive JSON route materialization current/next',
    'rowCount' => count($plan['rows']),
    'recursiveCurrentIds' => array_map(static fn (array $pair): int => (int) $pair['current']['option_id'], $plan['recursiveCurrentNext']),
    'firstPriorityPair' => array_map(
        static fn (?array $row): mixed => $row['atom'] ?? null,
        [
            SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan, ['option_id' => 2, 'attr' => 'priority'])[0]['current'] ?? null,
            SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan, ['option_id' => 2, 'attr' => 'priority'])[0]['next'] ?? null,
        ],
    ),
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['rowCount'] !== 20 || $summary['recursiveCurrentIds'] !== [1, 2, 4, 5, 3, 5]) {
        fwrite(STDERR, 'Unexpected recursive JSON materialization summary: ' . json_encode($summary) . PHP_EOL);
        exit(1);
    }

    echo "application-select-json-recursive-materialize-current-next52 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
