<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;

$options = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha_settings', 'autoload' => 'yes', 'option_value' => '{"next":[2,3],"rules":[{"name":"seo","priority":2,"enabled":true},{"name":"cache","priority":7,"enabled":false}]}'],
    ['option_id' => 2, 'option_name' => 'plugin_beta_settings', 'autoload' => 'yes', 'option_value' => '{"next":[4],"rules":[{"name":"forms","priority":4,"enabled":true},{"name":"media","priority":1,"enabled":false}]}'],
    ['option_id' => 3, 'option_name' => 'plugin_gamma_settings', 'autoload' => 'no', 'option_value' => '{"next":[],"rules":[{"name":"gallery","priority":5,"enabled":true},{"name":"seo","priority":3,"enabled":true}]}'],
    ['option_id' => 4, 'option_name' => 'plugin_delta_settings', 'autoload' => 'no', 'option_value' => '{"next":[5],"rules":[{"name":"cache","priority":9,"enabled":true},{"name":"forms","priority":6,"enabled":false}]}'],
    ['option_id' => 5, 'option_name' => 'plugin_epsilon_settings', 'autoload' => 'yes', 'option_value' => '{"next":[],"rules":[{"name":"media","priority":8,"enabled":true},{"name":"seo","priority":10,"enabled":false}]}'],
];

$sql = "WITH RECURSIVE wanted(option_id, depth, source) AS MATERIALIZED (
            VALUES (1, 0, 'seed')
            UNION ALL
            SELECT CAST(next.atom AS INTEGER), wanted.depth + 1, o.option_name
              FROM wanted
              JOIN wp_options AS o ON o.option_id = wanted.option_id
              JOIN json_each(o.option_value, '$.next') AS next ON next.type = 'integer'
             WHERE wanted.depth < 4
        )
        SELECT wanted.option_id AS option_id,
               wanted.depth AS depth,
               o.option_name AS option_name,
               jt.key AS attr,
               jt.atom AS atom,
               jt.fullkey AS fullkey
          FROM wanted
          JOIN wp_options AS o ON o.option_id = wanted.option_id
          JOIN json_tree(o.option_value, '$.rules') AS jt ON jt.type IN ('text', 'integer', 'true', 'false')
         ORDER BY wanted.depth, wanted.option_id, jt.fullkey";

$plan = SQLiteSelectRecursiveJsonMaterialization::materialize($sql, ['wp_options' => $options], ['option_name', 'attr'], ['fullkey']);
$alphaPriorityPairs = SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan, [
    'option_name' => 'plugin_alpha_settings',
    'attr' => 'priority',
]);

$summary = [
    'scenario' => 'application-select-recursive-materialized-json-current-next48',
    'recursiveOptionIds' => array_column($plan['trace']['rows'], 'option_id'),
    'materializedJsonRows' => count($plan['rows']),
    'indexKeys' => count($plan['indexes']),
    'alphaPriorityCurrentNext' => array_map(
        static fn (array $pair): array => [
            'current' => $pair['current']['atom'],
            'next' => $pair['next']['atom'] ?? null,
            'currentFullkey' => $pair['current']['fullkey'],
            'nextFullkey' => $pair['next']['fullkey'] ?? null,
        ],
        $alphaPriorityPairs,
    ),
    'applicationUse' => 'Copied wp_options JSON import staging can walk recursive option dependencies, materialize reachable json_tree() rule rows, and scan indexed current/next JSON attributes without ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['recursiveOptionIds'] !== [1, 2, 3, 4, 5] || $summary['materializedJsonRows'] !== 30 || $summary['indexKeys'] !== 15) {
        fwrite(STDERR, "application-select-recursive-materialized-json-current-next48 self-test failed\n");
        exit(1);
    }

    echo "application-select-recursive-materialized-json-current-next48 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
