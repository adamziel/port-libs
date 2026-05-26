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
$savepoints->rollbackTo('plugin_settings');
$afterRollback = $savepoints->toArray();

$savepoints->recordPageWrite(6);
$savepoints->release('plugin_settings');
$afterRelease = $savepoints->toArray();

echo json_encode([
    'beforeRollbackToPluginSettings' => $beforeRollback,
    'afterRollbackToPluginSettings' => $afterRollback,
    'afterReleasePluginSettings' => $afterRelease,
    'pendingPageNumbers' => $savepoints->pendingPageNumbers(),
    'transactionActive' => $savepoints->transactionActive(),
    'wordpressUse' => 'Preview nested SAVEPOINT/ROLLBACK TO/RELEASE page-dirty state for wp_options imports without the SQLite extension, so recovery tooling can explain which database pages would remain pending after a failed option-row import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
