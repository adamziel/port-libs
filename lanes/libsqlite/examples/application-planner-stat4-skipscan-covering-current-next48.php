<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteCreateIndex.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteMultiColumnRangePlan.php';
require_once __DIR__ . '/../src/SQLiteSkipScanCoveringStat4Plan.php';

use PortLibs\LibSqlite\SQLiteSkipScanCoveringStat4Plan;

$indexes = [
    [
        'name' => 'idx_autoload_blog_name_value_stat4',
        'rootPage' => 481,
        'estimatedRows' => 24000,
        'distinctValues' => ['autoload' => 3],
        'stat4Samples' => [
            ['prefix' => 'auto', 'suffix' => '_site_transient_timeout', 'nEq' => 3, 'nLt' => 0, 'nDLt' => 0],
            ['prefix' => 'auto', 'suffix' => '_transient_feed', 'nEq' => 9, 'nLt' => 3, 'nDLt' => 1],
            ['prefix' => 'auto', 'suffix' => 'theme_mods_default', 'nEq' => 2, 'nLt' => 12, 'nDLt' => 2],
            ['prefix' => 'no', 'suffix' => '_site_transient_update_plugins', 'nEq' => 5, 'nLt' => 14, 'nDLt' => 3],
            ['prefix' => 'no', 'suffix' => '_transient_doing_cron', 'nEq' => 7, 'nLt' => 19, 'nDLt' => 4],
            ['prefix' => 'no', 'suffix' => 'widget_recent-posts', 'nEq' => 4, 'nLt' => 26, 'nDLt' => 5],
            ['prefix' => 'yes', 'suffix' => '_site_transient_browser', 'nEq' => 2, 'nLt' => 30, 'nDLt' => 6],
            ['prefix' => 'yes', 'suffix' => '_transient_timeout_feed', 'nEq' => 11, 'nLt' => 32, 'nDLt' => 7],
            ['prefix' => 'yes', 'suffix' => 'siteurl', 'nEq' => 1, 'nLt' => 43, 'nDLt' => 8],
        ],
        'sql' => 'CREATE INDEX idx_autoload_blog_name_value_stat4 ON wp_options(autoload, blog_id, option_name, option_value)',
    ],
    [
        'name' => 'idx_autoload_blog_name_stat4_noncover',
        'rootPage' => 482,
        'estimatedRows' => 18000,
        'distinctValues' => ['autoload' => 3],
        'stat4Samples' => [
            ['prefix' => 'auto', 'suffix' => '_transient_feed', 'nEq' => 9, 'nLt' => 3, 'nDLt' => 1],
            ['prefix' => 'no', 'suffix' => '_transient_doing_cron', 'nEq' => 7, 'nLt' => 19, 'nDLt' => 4],
            ['prefix' => 'yes', 'suffix' => '_transient_timeout_feed', 'nEq' => 11, 'nLt' => 32, 'nDLt' => 7],
        ],
        'sql' => 'CREATE INDEX idx_autoload_blog_name_stat4_noncover ON wp_options(autoload, blog_id, option_name)',
    ],
];

$predicate = [
    'operator' => 'AND',
    'terms' => [
        ['operator' => '=', 'left' => ['column' => 'blog_id'], 'right' => 1],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => '_transient_'],
    ],
];

$plan = SQLiteSkipScanCoveringStat4Plan::choose(
    $indexes,
    $predicate,
    [['column' => 'autoload'], ['column' => 'blog_id'], ['column' => 'option_name']],
    ['blog_id', 'option_name', 'option_value'],
);

$summary = [
    'applicationScenario' => 'Copied wp_options transient cleanup can choose a STAT4-estimated covering skip-scan when autoload is unconstrained, blog_id is constrained, and option_value is available from the index payload.',
    'index' => $plan['name'] ?? null,
    'usesSkipScan' => $plan['usesSkipScan'] ?? false,
    'covering' => $plan['covering'] ?? false,
    'deferredTableLookup' => $plan['deferredTableLookup'] ?? true,
    'stat4Used' => $plan['stat4Used'] ?? false,
    'stat4SamplesUsed' => $plan['stat4SamplesUsed'] ?? 0,
    'estimatedRows' => $plan['estimatedRows'] ?? null,
    'estimatedCost' => $plan['estimatedCost'] ?? null,
    'currentNextFirst' => $plan['stat4CurrentNext'][0] ?? null,
    'loopEstimates' => $plan['stat4LoopEstimates'] ?? [],
    'detail' => $plan['detail'] ?? null,
];

if (in_array('--self-test', $argv, true)) {
    if (($summary['index'] !== 'idx_autoload_blog_name_value_stat4')
        || $summary['usesSkipScan'] !== true
        || $summary['covering'] !== true
        || $summary['deferredTableLookup'] !== false
        || $summary['stat4Used'] !== true
        || $summary['estimatedRows'] !== 34
    ) {
        fwrite(STDERR, "application-planner-stat4-skipscan-covering-current-next48 self-test failed\n");
        exit(1);
    }

    echo "application-planner-stat4-skipscan-covering-current-next48 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
