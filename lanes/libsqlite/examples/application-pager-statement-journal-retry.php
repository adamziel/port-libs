<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static fn (string $label): string => str_pad($label, 64, '.');

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordPageImageWrite(1, $page('before-header'));
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch');
$stack->recordPageImageWrite(3, $page('before-active-plugins'));
$stack->recordWalFrameWrite(3, 3);
$stack->beginStatementJournal('insert-plugin-setting');
$stack->recordStatementPageImageWrite('insert-plugin-setting', 4, $page('before-plugin-setting'));
$stack->recordStatementWalFrameWrite('insert-plugin-setting', 4, 4);
$stack->recordStatementWalFrameWrite('insert-plugin-setting', 5, 5, true);

$plan = $stack->rollbackStatementAndBeginStatementJournal(
    'insert-plugin-setting',
    'retry-plugin-setting',
    6,
    $page('before-retry-plugin-setting'),
    64,
    true
);

$summary = [
    'scenario' => 'application pager statement journal retry',
    'current_statement' => $plan['current_statement'],
    'next_statement' => $plan['next_statement'],
    'savepoint' => $plan['savepoint'],
    'rollback_to_wal_frame' => $plan['rollback_to_wal_frame'],
    'next_wal_frame_index' => $plan['next_wal_frame_index'],
    'rollback_restored_page_numbers' => $plan['rollback_restored_page_numbers'],
    'rollback_discarded_wal_frames' => array_column($plan['rollback_discarded_wal_frames'], 'frame_index'),
    'pending_pages_after_rollback' => $plan['pending_page_numbers_after_rollback'],
    'pending_wal_after_rollback' => $plan['pending_wal_frame_indexes_after_rollback'],
    'next_statement_journal' => $plan['statement_journals_after_next'][0],
    'pending_pages_after_next' => $plan['pending_page_numbers_after_next'],
    'pending_wal_after_next' => $plan['pending_wal_frame_indexes_after_next'],
    'savepoint_active_after' => $plan['savepoint_active_after'],
    'transaction_active_after' => $plan['transaction_active_after'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['rollback_to_wal_frame'] === 3);
    assert($summary['next_wal_frame_index'] === 4);
    assert($summary['rollback_restored_page_numbers'] === [4]);
    assert($summary['rollback_discarded_wal_frames'] === [4, 5]);
    assert($summary['pending_pages_after_rollback'] === [1, 2, 3]);
    assert($summary['pending_wal_after_rollback'] === [1, 2, 3]);
    assert($summary['next_statement_journal']['wal_start_frame'] === 3);
    assert($summary['next_statement_journal']['page_numbers'] === [6]);
    assert($summary['pending_pages_after_next'] === [1, 2, 3, 6]);
    assert($summary['pending_wal_after_next'] === [1, 2, 3, 4]);
    echo "application-pager-statement-journal-retry self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
