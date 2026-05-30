<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteExpressionIndexPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['column' => 'autoload'], 'right' => 'yes'],
        ['operator' => 'IN', 'left' => ['expression' => 'lower(option_name)'], 'values' => ['siteurl', 'home']],
    ],
];
$prepared = [
    'name' => 'prepared-wp-options-next121',
    'schemaCookie' => 1210,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_active_or_network_next121_old',
        'rootPage' => 12101,
        'sql' => "CREATE INDEX idx_wp_options_lower_active_or_network_next121_old ON wp_options(lower(option_name) COLLATE NOCASE, option_id) WHERE autoload = 'yes' OR blog_id = 0",
    ]],
    'rows' => [
        ['rowid' => 1, 'option_id' => 1, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => 'https://old.example.test'],
    ],
];
$current = [
    'name' => 'current-wp-options-next121',
    'schemaCookie' => 1211,
    'indexes' => [[
        'name' => 'idx_wp_options_lower_active_or_network_next121_current',
        'rootPage' => 12111,
        'sql' => "CREATE INDEX idx_wp_options_lower_active_or_network_next121_current ON wp_options(lower(option_name) COLLATE NOCASE, option_id) WHERE autoload = 'yes' OR blog_id = 0",
    ]],
    'rows' => [
        ['rowid' => 10, 'option_id' => 10, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => 'https://example.test'],
        ['rowid' => 11, 'option_id' => 11, 'option_name' => 'Home', 'autoload' => 'yes', 'blog_id' => 1, 'option_value' => 'https://example.test'],
        ['rowid' => 12, 'option_id' => 12, 'option_name' => 'Network_Plugins', 'autoload' => 'no', 'blog_id' => 0, 'option_value' => 'a:1:{s:12:"hello.php";b:1;}'],
    ],
];

$plan = SQLiteExpressionIndexPartialCurrentSourceNextPlan::materialize(
    $prepared,
    $current,
    $predicate,
    [['expression' => 'lower(option_name)', 'direction' => 'ASC', 'collation' => 'NOCASE']],
);

echo json_encode([
    'scenario' => 'application-expression-index-partial-current-source-next121',
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'selectedIndex' => $plan['selectedPlan']['name'] ?? null,
    'matchedRowids' => $plan['selectedPlan']['matchedRowids'] ?? [],
    'expressionKeys' => $plan['selectedPlan']['expressionKeys'] ?? [],
    'applicationUse' => 'Preview copied wp_options partial expression-index reprepare after schema changes so autoloaded siteurl/home lookups use the current lower(option_name) index without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
