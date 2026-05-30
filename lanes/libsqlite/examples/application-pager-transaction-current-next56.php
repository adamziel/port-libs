<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerTransactionStatePlan;

$writes = [
    ['page' => 1, 'bytes' => 512],
    ['page' => 4, 'bytes' => 512, 'spill' => true],
    ['page' => 7, 'bytes' => 512],
];

$plan = SQLitePagerTransactionStatePlan::currentNext(5, 104, $writes, 'commit', 3);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'committed' || $plan['next']['page_count'] !== 7 || $plan['next']['change_counter'] !== 105) {
        fwrite(STDERR, "application-pager-transaction-current-next56 self-test failed\n");
        exit(1);
    }

    echo "application-pager-transaction-current-next56 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pager-transaction-current-next56',
    'table' => 'wp_options',
    'status' => $plan['status'],
    'current_dirty_pages' => $plan['current']['dirty_pages'],
    'spilled_pages' => $plan['current']['spilled_pages'],
    'next_page_count' => $plan['next']['page_count'],
    'next_change_counter' => $plan['next']['change_counter'],
    'journal_action' => $plan['next']['journal_action'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
