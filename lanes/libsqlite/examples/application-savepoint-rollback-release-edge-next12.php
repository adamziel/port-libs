<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('Wp_Import');
$stack->recordPageImageWrite(1, $page('before-wp-options-root'));
$stack->recordWalFrameWrite(1, 1);
$stack->savepoint('Plugin_Settings');
$stack->recordPageImageWrite(2, $page('before-plugin-settings'));
$stack->recordWalFrameWrite(2, 2);
$stack->savepoint('plugin_settings');
$stack->recordPageImageWrite(3, $page('before-latest-plugin-settings'));
$stack->recordWalFrameWrite(3, 3, true);
$stack->savepoint('Option_Row');
$stack->recordPageImageWrite(4, $page('before-option-row'));
$stack->recordWalFrameWrite(4, 4);

$rollbackPlan = $stack->rollbackToPlan('PLUGIN_SETTINGS');
$releasePlan = $stack->releasePlan('PLUGIN_SETTINGS');
$stack->releaseWithPlan('PLUGIN_SETTINGS');
$commitPlan = $stack->commitPlan();

$summary = [
    'scenario' => 'application-savepoint-rollback-release-edge-next12',
    'rollback_found_index' => $rollbackPlan['found_index'],
    'rollback_discarded_frames' => $rollbackPlan['discarded_frame_names'],
    'release_found_index' => $releasePlan['found_index'],
    'release_frames' => $releasePlan['released_frame_names'],
    'remaining_savepoints_after_release' => $stack->names(),
    'merged_wal_frames' => $stack->pendingWalFrameIndexes(),
    'commit_pages' => $commitPlan['committed_page_numbers'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['rollback_found_index'] === 2);
    assert($summary['rollback_discarded_frames'] === ['Option_Row']);
    assert($summary['release_found_index'] === 2);
    assert($summary['release_frames'] === ['plugin_settings', 'Option_Row']);
    assert($summary['remaining_savepoints_after_release'] === ['Wp_Import', 'Plugin_Settings']);
    assert($summary['merged_wal_frames'] === [1, 2, 3, 4]);
    assert($summary['commit_pages'] === [1, 2, 3, 4]);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
