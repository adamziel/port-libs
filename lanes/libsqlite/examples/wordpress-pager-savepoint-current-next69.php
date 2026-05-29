<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp_plugin_import');
$stack->recordPageWrite(1);
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin_batch');
$stack->recordPageImageWrite(3, str_repeat('P', 64));
$stack->recordWalFrameWrite(3, 3);
$stack->savepoint('single_option');
$stack->recordPageImageWrite(4, str_repeat('S', 64));
$stack->recordWalFrameWrite(4, 4);
$stack->recordWalFrameWrite(5, 5, true);

$plan = $stack->rollbackToCurrentAndOpenSavepoint(
    'plugin_batch',
    'retry_single_option',
    6,
    str_repeat('R', 64),
    64,
    true
);

$result = [
    'scenario' => 'wordpress-pager-savepoint-current-next69',
    'wordpressUse' => 'After a copied wp_options plugin import row fails, ROLLBACK TO keeps the plugin batch savepoint open and the next retried option-row savepoint starts beneath that retained current frame with a fresh WAL frame index.',
    'savepoint' => $plan['savepoint'],
    'nextSavepoint' => $plan['next_savepoint'],
    'rollbackToFrame' => $plan['rollback_to_frame'],
    'nextWalFrameIndex' => $plan['next_wal_frame_index'],
    'discardedWalFrames' => array_column($plan['discarded_wal_frames'], 'frame_index'),
    'namesAfterNext' => $plan['names_after_next'],
    'pendingPagesAfterNext' => $plan['pending_page_numbers_after_next'],
    'pendingWalFramesAfterNext' => $plan['pending_wal_frame_indexes_after_next'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $result['nextWalFrameIndex'] !== 3
        || $result['namesAfterNext'] !== ['wp_plugin_import', 'plugin_batch', 'retry_single_option']
        || $result['pendingPagesAfterNext'] !== [1, 2, 6]
    ) {
        fwrite(STDERR, "wordpress-pager-savepoint-current-next69 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pager-savepoint-current-next69 self-test passed\n");
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
