<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpressionIndexPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$indexes = [
    [
        'name' => 'idx_wp_options_jsonb_channel_cover',
        'rootPage' => 301,
        'estimatedRows' => 6400,
        'sql' => "CREATE INDEX idx_wp_options_jsonb_channel_cover ON wp_options(jsonb_extract(option_value, '$.plugin.channel') COLLATE NOCASE, autoload, option_id DESC, option_name)",
    ],
    [
        'name' => 'idx_wp_options_jsonb_channel_plain',
        'rootPage' => 302,
        'estimatedRows' => 6400,
        'sql' => "CREATE INDEX idx_wp_options_jsonb_channel_plain ON wp_options(jsonb_extract(option_value, '$.plugin.channel'))",
    ],
];

$predicate = [
    'operator' => '=',
    'left' => ['function' => 'jsonb_extract', 'column' => 'option_value', 'path' => '$.plugin.channel'],
    'right' => 'stable',
];

$plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost(
    $indexes,
    $predicate,
    [['column' => 'autoload'], ['column' => 'option_id', 'direction' => 'DESC']],
    ['autoload', 'option_id', 'option_name'],
);

if ($plan === null) {
    fwrite(STDERR, "application-jsonb-path-index-covering-current-next34 failed: no plan\n");
    exit(1);
}

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['name'] === 'idx_wp_options_jsonb_channel_cover');
    assert($plan['path'] === '$.plugin.channel');
    assert($plan['covering'] === true);
    assert($plan['orderBySatisfied'] === true);
    echo "application-jsonb-path-index-covering-current-next34 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-jsonb-path-index-covering-current-next34',
    'selectedIndex' => $plan['name'],
    'path' => $plan['path'],
    'covering' => $plan['covering'],
    'orderBySatisfied' => $plan['orderBySatisfied'],
    'trailingColumns' => $plan['trailingColumns'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
