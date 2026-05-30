<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeAggregateOrderCursor;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionRows = [
    ['option_name' => 'siteurl', 'bytes' => 14, 'priority' => 30, 'include' => 1],
    ['option_name' => 'blogname', 'bytes' => 9, 'priority' => 20, 'include' => '1'],
    ['option_name' => '_transient_timeout_feed', 'include' => 0],
    ['option_name' => '_transient_feed', 'priority' => 40, 'include' => null],
    ['option_name' => 'rewrite_rules', 'bytes' => 100, 'priority' => 10, 'include' => true],
    ['option_name' => 'cron', 'bytes' => 3, 'priority' => 20, 'include' => false],
    ['option_name' => 'active_plugins', 'bytes' => 75, 'priority' => 30, 'include' => 2],
];

$cursor = new SQLiteVdbeAggregateOrderCursor(
    $optionRows,
    'option_name',
    ['priority', 'option_name'],
    'include',
    ['NUMERIC', 'TEXT'],
    ['BINARY', 'BINARY'],
    [true, false],
    ['LAST', null],
);

$ordered = [];
while (!$cursor->eof()) {
    $row = $cursor->currentRow();
    $ordered[] = $row['option_name'];
    $cursor->next();
}

$result = [
    'applicationUse' => 'Preview copied wp_options aggregate input rows ordered for group_concat(... ORDER BY ...) FILTER (WHERE include), while filtered-out rows with missing aggregate/order payloads are skipped before current/next iteration.',
    'sqlShape' => 'group_concat(option_name ORDER BY priority DESC, option_name ASC) FILTER (WHERE include)',
    'orderedAggregateInputs' => $ordered,
    'expected' => ['active_plugins', 'siteurl', 'blogname', 'rewrite_rules'],
];

if (in_array('--self-test', $argv, true)) {
    if ($result['orderedAggregateInputs'] !== $result['expected']) {
        fwrite(STDERR, 'Unexpected VDBE aggregate input order: ' . var_export($result['orderedAggregateInputs'], true) . PHP_EOL);
        exit(1);
    }

    echo "PASS application vdbe aggregate filter order current next\n";
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
