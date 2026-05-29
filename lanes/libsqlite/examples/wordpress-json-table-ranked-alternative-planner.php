<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = '{"rules":[{"name":"seo","priority":2,"enabled":true},{"name":"cache","priority":7,"enabled":false},{"name":"forms","priority":4,"enabled":true}]}';
$baseConstraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
];
$alternatives = [
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
        ['column' => 'atom', 'operator' => '>=', 'value' => 4],
    ],
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'name'],
        ['column' => 'atom', 'operator' => 'LIKE', 'value' => 'c%'],
    ],
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
];

$plan = SQLiteJsonTablePlan::rankedAlternativePlan(
    'json_tree',
    $baseConstraints,
    $alternatives,
    [['column' => 'id', 'direction' => 'ASC']],
);

$summary = [
    'scenario' => 'wordpress-json-table-ranked-alternative-planner',
    'wordpressUse' => 'A copied wp_options diagnostics query can plan OR branches over json_tree() once, rank the cheapest usable JSON-table branch first, and merge duplicate virtual rows deterministically without ext/sqlite.',
    'chosenBranch' => $plan['chosenBranch'],
    'branchOrder' => $plan['branchOrder'],
    'rowFullkeys' => array_values(array_map(
        static fn (array $row): mixed => $row['fullkey'] ?? null,
        $plan['rows'],
    )),
    'estimatedCost' => $plan['estimatedCost'],
    'dependency' => $plan['dependencies'][0],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['chosenBranch'] !== 1) {
        throw new RuntimeException('Expected ranked JSON alternative branch 1 to be selected first');
    }
    if ($summary['branchOrder'] !== [1, 0, 2]) {
        throw new RuntimeException('Expected deterministic JSON alternative branch order');
    }
    if ($summary['rowFullkeys'] !== [
        '$.rules[0]',
        '$.rules[1]',
        '$.rules[1].name',
        '$.rules[1].priority',
        '$.rules[2]',
        '$.rules[2].priority',
    ]) {
        throw new RuntimeException('Expected ranked JSON alternative rows to be merged by id order');
    }
    if ($summary['dependency'] !== 'sqlite-json-table-ranked-alternative-planner-current') {
        throw new RuntimeException('Expected stable ranked alternative dependency marker');
    }

    echo "wordpress-json-table-ranked-alternative-planner self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
