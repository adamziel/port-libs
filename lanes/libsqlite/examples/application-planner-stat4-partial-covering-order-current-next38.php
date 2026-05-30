<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCoveringIndexPlan;

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_cache'],
    ],
];

$plan = SQLiteCoveringIndexPlan::choose([
    [
        'name' => 'wp_options_autoload_plugin_cover_stat4',
        'rootPage' => 381,
        'estimatedRows' => 12000,
        'stat4Samples' => [
            ['values' => ['autoload' => 'yes', 'option_name' => 'plugin_cache_alpha'], 'rows' => 6],
            ['values' => ['autoload' => 'yes', 'option_name' => 'plugin_cache_beta'], 'rows' => 7],
            ['values' => ['autoload' => 'yes', 'option_name' => 'plugin_settings'], 'rows' => 11],
            ['values' => ['autoload' => 'no', 'option_name' => 'plugin_cache_gamma'], 'rows' => 31],
        ],
        'sql' => "CREATE INDEX wp_options_autoload_plugin_cover_stat4 ON wp_options(autoload, option_name, option_id DESC, option_value) WHERE autoload = 'yes' AND option_name >= 'plugin_'",
    ],
    [
        'name' => 'wp_options_name_plain',
        'rootPage' => 382,
        'estimatedRows' => 900,
        'sql' => 'CREATE INDEX wp_options_name_plain ON wp_options(option_name, autoload, option_value)',
    ],
], $predicate, ['autoload', 'option_name', 'option_id', 'option_value'], [
    ['column' => 'option_name'],
    ['column' => 'option_id', 'direction' => 'DESC'],
]);

if ($plan === null) {
    fwrite(STDERR, "no plan\n");
    exit(1);
}

echo json_encode([
    'status' => 'ok',
    'chosen' => $plan['name'],
    'partial' => $plan['partial'],
    'covering' => $plan['covering'],
    'orderBySatisfied' => $plan['orderBySatisfied'],
    'estimatedRowsBeforeStat4' => $plan['estimatedRowsBeforeStat4'],
    'estimatedRows' => $plan['estimatedRows'],
    'stat4Used' => $plan['stat4Used'],
    'stat4MatchedSamples' => $plan['stat4MatchedSamples'],
    'applicationPath' => 'wp_options autoload plugin option_name scan',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
