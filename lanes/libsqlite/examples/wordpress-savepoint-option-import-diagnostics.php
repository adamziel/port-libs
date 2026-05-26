<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp_option_import');
$savepoints->recordPageWrite(1);
$savepoints->recordPageWrite(2);

$savepoints->savepoint('plugin_settings');
$savepoints->recordPageWrite(5);
$savepoints->recordPageWrite(8);

$savepoints->savepoint('single_option_row');
$savepoints->recordPageWrite(9);

$beforeRollback = $savepoints->toArray();
$rollbackPlan = $savepoints->rollbackToPlan('plugin_settings');
$rollbackPreview = $savepoints->rollbackToPageNumbers('plugin_settings');
$singleOptionRollbackPreview = $savepoints->rollbackToPageNumbers('single_option_row');
$rollbackWithPlan = $savepoints->rollbackToWithPlan('plugin_settings');
$afterRollback = $savepoints->toArray();

$savepoints->recordPageWrite(6);
$releasePlan = $savepoints->releasePlan('plugin_settings');
$releaseWithPlan = $savepoints->releaseWithPlan('plugin_settings');
$afterRelease = $savepoints->toArray();
$outerReleasePlan = $savepoints->releasePlan('wp_option_import');
$outerReleaseWithPlan = $savepoints->releaseWithPlan('wp_option_import');

$commitPreview = new SQLiteSavepointStack();
$commitPreview->beginTransaction('wp_option_import_commit');
$commitPreview->recordPageWrite(1);
$commitPreview->savepoint('plugin_settings_commit');
$commitPreview->recordPageWrite(5);
$commitPreview->savepoint('single_option_row_commit');
$commitPreview->recordPageWrite(7);
$commitPlan = $commitPreview->commitPlan();
$commitWithPlan = $commitPreview->commitWithPlan();

$fullRollbackStack = new SQLiteSavepointStack();
$fullRollbackStack->beginTransaction('wp_option_import_rollback');
$fullRollbackStack->recordPageWrite(1);
$fullRollbackStack->recordPageWrite(2);
$fullRollbackStack->savepoint('plugin_settings_rollback');
$fullRollbackStack->recordPageWrite(5);
$fullRollbackStack->savepoint('single_option_row_rollback');
$fullRollbackStack->recordPageWrite(9);
$fullRollbackPlan = $fullRollbackStack->rollbackPlan();
$fullRollbackWithPlan = $fullRollbackStack->rollbackWithPlan();

echo json_encode([
    'beforeRollbackToPluginSettings' => $beforeRollback,
    'rollbackToPluginSettingsPlan' => $rollbackPlan,
    'rollbackToPluginSettingsPageNumbers' => $rollbackPreview,
    'rollbackToSingleOptionRowPageNumbers' => $singleOptionRollbackPreview,
    'rollbackToPluginSettingsWithPlan' => $rollbackWithPlan,
    'afterRollbackToPluginSettings' => $afterRollback,
    'releasePluginSettingsPlan' => $releasePlan,
    'releasePluginSettingsWithPlan' => $releaseWithPlan,
    'afterReleasePluginSettings' => $afterRelease,
    'releaseOuterTransactionPlan' => $outerReleasePlan,
    'releaseOuterTransactionWithPlan' => $outerReleaseWithPlan,
    'commitPlan' => $commitPlan,
    'commitWithPlan' => $commitWithPlan,
    'commitTransactionActiveAfter' => $commitPreview->transactionActive(),
    'fullRollbackPlan' => $fullRollbackPlan,
    'fullRollbackWithPlan' => $fullRollbackWithPlan,
    'fullRollbackTransactionActiveAfter' => $fullRollbackStack->transactionActive(),
    'pendingPageNumbers' => $savepoints->pendingPageNumbers(),
    'transactionActive' => $savepoints->transactionActive(),
    'wordpressUse' => 'Preview nested SAVEPOINT/ROLLBACK TO/RELEASE/ROLLBACK plans and page-dirty state for wp_options imports without the SQLite extension, so recovery tooling can explain which database pages would roll back, merge upward, or remain pending after a failed option-row import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
