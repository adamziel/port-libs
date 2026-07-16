<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteMultiColumnRangePlan.php';
require_once __DIR__ . '/../src/SQLiteSkipScanCoveringStat4Plan.php';

use PortLibs\LibSqlite\SQLiteSkipScanCoveringStat4Plan;

$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$indexes = [
    [
        'name' => 'idx_blog_autoload_status_name_value_partial_stat4',
        'rootPage' => 950,
        'estimatedRows' => 64000,
        'distinctValues' => ['blog_scope' => 2, 'autoload' => 2],
        'stat4Samples' => [
            ['prefix' => ['main', 'auto'], 'suffix' => '_transient_feed', 'nEq' => 7, 'nLt' => 2, 'nDLt' => 1],
            ['prefix' => ['main', 'auto'], 'suffix' => 'plugin_alpha', 'nEq' => 3, 'nLt' => 9, 'nDLt' => 2],
            ['prefix' => ['main', 'yes'], 'suffix' => '_transient_timeout_feed', 'nEq' => 11, 'nLt' => 17, 'nDLt' => 4],
            ['prefix' => ['main', 'yes'], 'suffix' => 'siteurl', 'nEq' => 1, 'nLt' => 28, 'nDLt' => 5],
            ['prefix' => ['network', 'no'], 'suffix' => '_transient_doing_cron', 'nEq' => 17, 'nLt' => 42, 'nDLt' => 7],
            ['prefix' => ['network', 'no'], 'suffix' => 'widget_recent-posts', 'nEq' => 4, 'nLt' => 59, 'nDLt' => 8],
            ['prefix' => ['network', 'yes'], 'suffix' => '_transient_timeout_theme', 'nEq' => 23, 'nLt' => 82, 'nDLt' => 10],
            ['prefix' => ['network', 'yes'], 'suffix' => 'theme_mods_default', 'nEq' => 6, 'nLt' => 105, 'nDLt' => 11],
        ],
        'sql' => "CREATE INDEX idx_blog_autoload_status_name_value_partial_stat4 ON wp_options(blog_scope, autoload, status, option_name, option_value) WHERE status = 'active' AND option_name >= '_site_'",
    ],
];

$plan = SQLiteSkipScanCoveringStat4Plan::choose(
    $indexes,
    $and($point('status', 'active'), $range('option_name', '>=', '_transient_')),
    [['column' => 'blog_scope'], ['column' => 'autoload'], ['column' => 'status'], ['column' => 'option_name']],
    ['status', 'option_name', 'option_value'],
);

echo json_encode([
    'scenario' => 'application-planner-stat4-skipscan-partial-covering-current-next50',
    'selectedIndex' => $plan['name'] ?? null,
    'detail' => $plan['detail'] ?? null,
    'usesSkipScan' => $plan['usesSkipScan'] ?? null,
    'skippedColumns' => $plan['skippedColumns'] ?? null,
    'partialPredicateImplied' => $plan['partialPredicateImplied'] ?? null,
    'covering' => $plan['covering'] ?? null,
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'loopPrefixes' => array_map(
        static fn (array $loop): array => ['prefix' => $loop['prefix'], 'estimatedRows' => $loop['estimatedRows']],
        $plan['stat4LoopEstimates'] ?? [],
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
