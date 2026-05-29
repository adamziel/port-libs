<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLitePagerHotJournalStatementCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalStatementCacheCurrentSourceNextPlan;

$pageSize = 64;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerHotJournalStatementCacheCurrentSourceNextPlan::plan(
    $pageSize,
    'wal:plugin-import:current',
    'hot-journal:plugin-import:recovered',
    8,
    [
        'active-wp-options-reader' => [
            'state' => 'active',
            'read_only' => true,
            'pages' => [
                2 => ['image' => $page('wp_options active reader root'), 'source_id' => 'wal:plugin-import:current', 'epoch' => 8],
            ],
        ],
        'retry-wp-options-reader' => [
            'state' => 'ready',
            'read_only' => true,
            'pages' => [
                2 => ['image' => $page('wp_options stale reader root'), 'source_id' => 'wal:plugin-import:current', 'epoch' => 8],
                4 => ['image' => $page('wp_options dirty transient stmt'), 'source_id' => 'wal:plugin-import:current', 'epoch' => 8, 'dirty' => true],
            ],
        ],
        'write-active-plugins' => [
            'state' => 'ready',
            'read_only' => false,
            'savepoint' => 'plugin-import',
            'pages' => [
                3 => ['image' => $page('active_plugins stale writer'), 'source_id' => 'wal:plugin-import:old', 'epoch' => 7],
            ],
        ],
    ],
    [
        2 => $page('wp_options recovered hot journal root'),
        3 => $page('active_plugins recovered hot journal'),
    ],
    [
        4 => $page('wp_options statement rollback transient'),
    ],
    [2, 3, 4, 5],
    'active-wp-options-reader'
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'pager_hot_journal_statement_cache_current_source_next104');
    assert($plan['active_current_snapshot_statements'] === ['active-wp-options-reader']);
    assert($plan['expired_statements'] === ['retry-wp-options-reader', 'write-active-plugins']);
    assert($plan['retryable_read_statements'] === ['retry-wp-options-reader']);
    assert($plan['write_statements_blocked_before_retry'] === ['write-active-plugins']);
    assert($plan['retry_reads'][0]['source'] === 'hot-journal-recovery');
    assert($plan['retry_reads'][2]['source'] === 'statement-rollback-before-image');

    echo "wordpress-hot-journal-statement-cache-current-source-next104 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
