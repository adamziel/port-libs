<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVdbeAggregateOrderCursor;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 30, 'include' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'priority' => 10, 'include' => 1],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'priority' => 20, 'include' => 0],
    ['option_id' => 4, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'priority' => 20, 'include' => 1],
    ['option_id' => 5, 'option_name' => 'network_settings', 'autoload' => 'no', 'priority' => null, 'include' => 1],
];

$cursor = new SQLiteVdbeAggregateOrderCursor(
    $rows,
    'option_name',
    ['priority', 'option_name'],
    'include',
    'CG',
    ['BINARY', 'NOCASE'],
    [],
    ['LAST', null],
);

$summary = [
    'scenario' => 'application-vdbe-aggregate-order-current-next',
    'ordered_group_concat' => $cursor->groupConcat('|'),
    'first_value' => $cursor->currentValue(),
    'ordered_option_ids' => array_column($cursor->remainingRows(), 'option_id'),
    'cursor' => $cursor->summary(),
    'applicationUse' => 'Copied wp_options aggregate previews can feed group_concat(... ORDER BY ... FILTER ...) through a VDBE-style sorted input cursor with stable current/next behavior, NULL placement, affinity, and collation semantics without requiring ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['ordered_group_concat'] !== 'home|plugin_cache|siteurl|network_settings') {
        fwrite(STDERR, 'unexpected aggregate ORDER BY group concat' . PHP_EOL);
        exit(1);
    }
    if ($summary['first_value'] !== 'home') {
        fwrite(STDERR, 'unexpected first aggregate ORDER BY value' . PHP_EOL);
        exit(1);
    }

    echo 'application-vdbe-aggregate-order-current-next self-test passed' . PHP_EOL;
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
