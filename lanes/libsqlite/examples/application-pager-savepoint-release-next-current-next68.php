<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;

$page = static fn (string $label): string => str_pad($label, 64, '.');

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-plugin-import');
$stack->recordPageImageWrite(1, $page('before-database-header'));
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings');
$stack->beginStatementJournal('insert-active-plugin');
$stack->recordStatementPageImageWrite('insert-active-plugin', 3, $page('before-active-plugin'));
$stack->recordStatementWalFrameWrite('insert-active-plugin', 3, 3);
$stack->savepoint('plugin-option');
$stack->recordPageImageWrite(4, $page('before-plugin-option'));
$stack->recordWalFrameWrite(4, 4);
$stack->recordWalFrameWrite(5, 5, true);

$plan = $stack->rollbackReleaseAndBeginSavepoint(
    'plugin-settings',
    'plugin-settings-retry',
    6,
    $page('before-retry-option'),
    64,
    true
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['names_after_rollback'] === ['wp-plugin-import', 'plugin-settings']);
    assert($plan['names_after_release'] === ['wp-plugin-import']);
    assert($plan['names_after_next'] === ['wp-plugin-import', 'plugin-settings-retry']);
    assert($plan['discarded_wal_frames'][0]['frame_index'] === 3);
    assert($plan['next_wal_frame_index'] === 3);
    assert($plan['pending_page_numbers_after_next'] === [1, 2, 6]);
    assert($plan['released_savepoint_closed'] === true);
    assert($plan['next_savepoint_active_after'] === true);

    echo "application-pager-savepoint-release-next-current-next68 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
