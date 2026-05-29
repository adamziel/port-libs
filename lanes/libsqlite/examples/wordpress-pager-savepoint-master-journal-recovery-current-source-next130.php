<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNextPlan;

$pageSize = 512;
$main = '/srv/wp-content/database/wp-options-next130.sqlite';
$stats = '/srv/wp-content/database/wp-options-next130-stats.sqlite';
$master = '/srv/wp-content/database/wp-options-next130.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');

$plan = SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNextPlan::currentSourceNext(
    $pageSize,
    $master,
    'plugin-import-next130',
    [
        $main . '-journal',
        '/srv/wp-content/database/old-site.sqlite-journal',
    ],
    [
        $main . '-journal',
        $stats . '-journal',
    ],
    [
        $main => [
            1 => $page('wp next130 stale header'),
            2 => $page('wp next130 stale active_plugins option'),
            3 => $page('wp next130 stale autoload index'),
        ],
        $stats => [
            1 => $page('wp next130 stale stats header'),
            2 => $page('wp next130 stale stats option audit'),
        ],
    ],
    [
        $main => [
            1 => $page('wp next130 recovered header'),
            2 => $page('wp next130 recovered active_plugins option'),
            3 => $page('wp next130 recovered autoload index'),
        ],
        $stats => [
            1 => $page('wp next130 recovered stats header'),
            2 => $page('wp next130 recovered stats option audit'),
        ],
    ],
    [
        $main => [
            2 => $page('wp next130 recovered active_plugins option'),
            3 => $page('wp next130 recovered autoload index'),
        ],
        $stats => [
            2 => $page('wp next130 recovered stats option audit'),
        ],
    ],
    [
        $main => [
            2 => $page('wp next130 dirty failed active_plugins update'),
            3 => $page('wp next130 dirty failed autoload index'),
        ],
        $stats => [
            2 => $page('wp next130 dirty failed stats audit'),
        ],
    ],
    [
        $main => [
            2 => $page('wp next130 retry active_plugins option'),
            4 => $page('wp next130 retry plugin payload overflow'),
        ],
        $stats => [
            2 => $page('wp next130 retry stats option audit'),
        ],
    ],
    true
);

if (($argv[1] ?? '') === '--self-test') {
    $checks = [
        $plan['status'] === 'pager_savepoint_master_journal_recovery_current_source_next130',
        $plan['current_source_verified'] === true,
        $plan['stale_cached_members'] === ['/srv/wp-content/database/old-site.sqlite-journal'],
        $plan['new_current_members'] === [$stats . '-journal'],
        str_contains($plan['rollback_database_bytes'][$main], 'wp next130 recovered active_plugins option'),
        !str_contains($plan['rollback_database_bytes'][$main], 'wp next130 dirty failed active_plugins update'),
        str_contains($plan['final_database_bytes'][$main], 'wp next130 retry active_plugins option'),
        str_contains($plan['final_database_bytes'][$stats], 'wp next130 retry stats option audit'),
    ];

    foreach ($checks as $passed) {
        if (!$passed) {
            fwrite(STDERR, "wordpress-pager-savepoint-master-journal-recovery-current-source-next130 self-test failed\n");
            exit(1);
        }
    }

    echo "wordpress-pager-savepoint-master-journal-recovery-current-source-next130 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'stale_cached_members' => $plan['stale_cached_members'],
    'new_current_members' => $plan['new_current_members'],
    'main_retry_pages' => $plan['retry_write_page_numbers'][$main],
    'stats_retry_pages' => $plan['retry_write_page_numbers'][$stats],
    'operation_count' => count($plan['operations']),
], JSON_PRETTY_PRINT) . PHP_EOL;
