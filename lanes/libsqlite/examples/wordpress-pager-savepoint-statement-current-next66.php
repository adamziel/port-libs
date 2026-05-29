<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static fn (string $label): string => str_pad($label, 64, '.');

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordPageImageWrite(1, $page('before-db-header'));
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);

$stack->savepoint('plugin-batch');
$stack->beginStatementJournal('insert-active-plugin');
$stack->recordStatementPageImageWrite('insert-active-plugin', 3, $page('before-active-plugins'));
$stack->recordStatementWalFrameWrite('insert-active-plugin', 3, 3);

$stack->savepoint('single-option');
$stack->beginStatementJournal('insert-plugin-setting');
$stack->recordStatementPageImageWrite('insert-plugin-setting', 4, $page('before-plugin-setting'));
$stack->recordStatementWalFrameWrite('insert-plugin-setting', 4, 4);
$stack->recordStatementWalFrameWrite('insert-plugin-setting', 5, 5, true);

$plan = $stack->rollbackToCurrentAndBeginStatementJournal(
    'plugin-batch',
    'retry-plugin-setting',
    6,
    $page('before-retry-setting'),
    64,
    true
);

$summary = [
    'scenario' => 'wordpress pager savepoint statement current next66',
    'savepoint' => $plan['savepoint'],
    'statement' => $plan['statement'],
    'discarded_statement_journals' => $plan['discarded_statement_journals'],
    'discarded_wal_frames' => array_column($plan['discarded_wal_frames'], 'frame_index'),
    'statement_after_rollback_count' => count($plan['statement_journals_after_rollback']),
    'next_statement' => $plan['statement_journals_after_next'][0],
    'pending_pages_after_next' => $plan['pending_page_numbers_after_next'],
    'pending_wal_after_next' => $plan['pending_wal_frame_indexes_after_next'],
    'rollback_statement_restored_pages' => $plan['rollback_statement_restored_pages'],
    'current_savepoint_active_after' => $plan['current_savepoint_active_after'],
    'transaction_active_after' => $plan['transaction_active_after'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['discarded_statement_journals'] === ['insert-active-plugin', 'insert-plugin-setting']);
    assert($summary['discarded_wal_frames'] === [3, 4, 5]);
    assert($summary['next_statement']['name'] === 'retry-plugin-setting');
    assert($summary['next_statement']['wal_start_frame'] === 2);
    assert($summary['next_statement']['page_numbers'] === [6]);
    assert($summary['pending_pages_after_next'] === [1, 2, 6]);
    assert($summary['pending_wal_after_next'] === [1, 2, 3]);
    echo "wordpress-pager-savepoint-statement-current-next66 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
