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
$savepoints->rollbackTo('plugin_settings');
$afterRollback = $savepoints->toArray();

$savepoints->recordPageWrite(6);
$releasePlan = $savepoints->releasePlan('plugin_settings');
$savepoints->release('plugin_settings');
$afterRelease = $savepoints->toArray();
$outerReleasePlan = $savepoints->releasePlan('wp_option_import');

echo json_encode([
    'beforeRollbackToPluginSettings' => $beforeRollback,
    'rollbackToPluginSettingsPlan' => $rollbackPlan,
    'rollbackToPluginSettingsPageNumbers' => $rollbackPreview,
    'rollbackToSingleOptionRowPageNumbers' => $singleOptionRollbackPreview,
    'afterRollbackToPluginSettings' => $afterRollback,
    'releasePluginSettingsPlan' => $releasePlan,
    'afterReleasePluginSettings' => $afterRelease,
    'releaseOuterTransactionPlan' => $outerReleasePlan,
    'pendingPageNumbers' => $savepoints->pendingPageNumbers(),
    'transactionActive' => $savepoints->transactionActive(),
    'wordpressUse' => 'Preview nested SAVEPOINT/ROLLBACK TO/RELEASE plans and page-dirty state for wp_options imports without the SQLite extension, so recovery tooling can explain which database pages would roll back, merge upward, or remain pending after a failed option-row import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
