<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointTriggerRollbackPlan;

$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);

$outerRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'level' => 0, 'autoload' => 'yes'],
];
$savepointRows = [
    ...$outerRows,
    ['option_id' => 3, 'option_name' => 'preflight_marker', 'level' => 0, 'autoload' => 'no'],
];
$inputRows = [
    ['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
];
$triggers = [
    [
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'target',
        'action' => 'insert',
        'when' => ['column' => 'level', 'operator' => '=', 'value' => 2],
        'rollback' => true,
    ],
    [
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'target',
        'action' => 'insert',
        'when' => ['column' => 'level', 'operator' => '<', 'value' => 3],
        'insert_row' => [
            'option_id' => 'new_increment.option_id',
            'option_name' => 'concat:new.option_name::child',
            'level' => 'new_increment.level',
            'autoload' => 'new.autoload',
        ],
    ],
];

$summary = SQLiteSavepointTriggerRollbackPlan::insertRows(
    $outerRows,
    $savepointRows,
    $inputRows,
    $triggers,
    ['option_name'],
    'plugin_import',
    [
        'page_size' => 512,
        'savepoint_page_images' => [
            3 => $page('before-option-leaf'),
            4 => $page('before-audit-leaf'),
        ],
        'dirty_pages' => [
            3 => $page('dirty-option-leaf'),
            4 => $page('dirty-audit-leaf'),
            5 => $page('dirty-index-leaf'),
        ],
        'wal_start_frame' => 2,
        'wal_frames' => [
            ['frame_index' => 3, 'page_number' => 3],
            ['frame_index' => 4, 'page_number' => 4],
            ['frame_index' => 5, 'page_number' => 5, 'commit_frame' => true],
        ],
    ]
);

$report = [
    'applicationUse' => 'Preview a copied wp_options plugin import where an AFTER INSERT trigger rolls back only the current savepoint, preserving earlier transaction rows and reporting page/WAL rollback evidence without requiring ext/sqlite.',
    'remainingOptions' => array_column($summary['rows'], 'option_name'),
    'rolledBackToSavepoint' => $summary['rolled_back_to_savepoint'],
    'rollbackScope' => $summary['rollback_scope'],
    'rollbackReason' => $summary['rollback_reason'],
    'rowsRemoved' => $summary['rollback_rows_removed'],
    'restoredPages' => $summary['restored_page_numbers'],
    'rollbackToWalFrame' => $summary['rollback_to_wal_frame'],
    'discardedWalFrames' => array_column($summary['discarded_wal_frames'], 'frame_index'),
    'transactionActiveAfter' => $summary['transaction_active_after'],
    'savepointActiveAfter' => $summary['savepoint_active_after'],
    'dependencies' => $summary['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($report['remainingOptions'] === ['siteurl', 'home', 'preflight_marker']);
    assert($report['rolledBackToSavepoint'] === true);
    assert($report['rollbackScope'] === 'current-savepoint');
    assert($report['rowsRemoved'] === 2);
    assert($report['restoredPages'] === [3, 4, 5]);
    assert($report['rollbackToWalFrame'] === 2);
    assert($report['discardedWalFrames'] === [3, 4, 5]);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
