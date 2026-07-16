<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('app_setting_import');
$savepoints->recordPageWrite(1);
$savepoints->recordPageWrite(2);

$savepoints->savepoint('module_settings');
$savepoints->recordPageWrite(5);
$savepoints->recordPageWrite(8);

$savepoints->savepoint('single_setting_row');
$savepoints->recordPageWrite(9);

$beforeRollback = $savepoints->toArray();
$rollbackPlan = $savepoints->rollbackToPlan('module_settings');
$rollbackPreview = $savepoints->rollbackToPageNumbers('module_settings');
$singleSettingRollbackPreview = $savepoints->rollbackToPageNumbers('single_setting_row');
$rollbackWithPlan = $savepoints->rollbackToWithPlan('module_settings');
$afterRollback = $savepoints->toArray();

$savepoints->recordPageWrite(6);
$releasePlan = $savepoints->releasePlan('module_settings');
$releaseWithPlan = $savepoints->releaseWithPlan('module_settings');
$afterRelease = $savepoints->toArray();
$outerReleasePlan = $savepoints->releasePlan('app_setting_import');
$outerReleaseWithPlan = $savepoints->releaseWithPlan('app_setting_import');

$commitPreview = new SQLiteSavepointStack();
$commitPreview->beginTransaction('app_setting_import_commit');
$commitPreview->recordPageWrite(1);
$commitPreview->savepoint('module_settings_commit');
$commitPreview->recordPageWrite(5);
$commitPreview->savepoint('single_setting_row_commit');
$commitPreview->recordPageWrite(7);
$commitPlan = $commitPreview->commitPlan();
$commitWithPlan = $commitPreview->commitWithPlan();

$fullRollbackStack = new SQLiteSavepointStack();
$fullRollbackStack->beginTransaction('app_setting_import_rollback');
$fullRollbackStack->recordPageWrite(1);
$fullRollbackStack->recordPageWrite(2);
$fullRollbackStack->savepoint('module_settings_rollback');
$fullRollbackStack->recordPageWrite(5);
$fullRollbackStack->savepoint('single_setting_row_rollback');
$fullRollbackStack->recordPageWrite(9);
$fullRollbackPlan = $fullRollbackStack->rollbackPlan();
$fullRollbackWithPlan = $fullRollbackStack->rollbackWithPlan();

$walSavepoints = new SQLiteSavepointStack();
$walSavepoints->beginTransaction('app_setting_wal_import');
$walSavepoints->recordWalFrameWrite(1, 1);
$walSavepoints->recordWalFrameWrite(2, 2, true);
$walSavepoints->savepoint('module_settings_wal');
$walSavepoints->recordWalFrameWrite(3, 2);
$walSavepoints->recordWalFrameWrite(4, 5);
$walSavepoints->savepoint('single_setting_row_wal');
$walSavepoints->recordWalFrameWrite(5, 5);
$walSavepoints->recordWalFrameWrite(6, 8, true);
$walRollbackPlan = $walSavepoints->walRollbackToPlan('module_settings_wal');
$walRollbackWithPlan = $walSavepoints->walRollbackToWithPlan('module_settings_wal');
$walAfterRollback = $walSavepoints->walFrameState();
$walSavepoints->recordWalFrameWrite(3, 6, true);
$walReleasePlan = $walSavepoints->releasePlan('module_settings_wal');
$walReleaseWithPlan = $walSavepoints->releaseWithPlan('module_settings_wal');
$walAfterRelease = $walSavepoints->walFrameState();

$walPageSize = 512;
$walSalt1 = 0x71717171;
$walSalt2 = 0x81818181;
$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $walPageSize, 17, $walSalt1, $walSalt2);
$walChecksumSeed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $walChecksumSeed[0], $walChecksumSeed[1]);
$appendWalFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $pageImage) use ($walSalt1, $walSalt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $walSalt1, $walSalt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $pageImage;
};
$walBytes = $appendWalFrame($walBytes, $walChecksumSeed, 1, 0, str_pad('app-settings-root-before-module', $walPageSize, "\0"));
$walBytes = $appendWalFrame($walBytes, $walChecksumSeed, 2, 2, str_pad('load-policy-index-before-module', $walPageSize, "\0"));
$walBytes = $appendWalFrame($walBytes, $walChecksumSeed, 5, 0, str_pad('module-settings-draft-frame', $walPageSize, "\0"));
$walBytes = $appendWalFrame($walBytes, $walChecksumSeed, 8, 0, str_pad('single-setting-draft-frame', $walPageSize, "\0"));
$walBytes = $appendWalFrame($walBytes, $walChecksumSeed, 8, 8, str_pad('single-setting-commit-frame', $walPageSize, "\0"));
$parsedWal = SQLiteWal::parse($walBytes, null, true);
$walByteSavepoints = new SQLiteSavepointStack();
$walByteSavepoints->beginTransaction('app_setting_wal_import');
$walByteSavepoints->recordWalFrameWrite(1, 1);
$walByteSavepoints->recordWalFrameWrite(2, 2, true);
$walByteSavepoints->savepoint('module_settings_wal');
$walByteSavepoints->recordWalFrameWrite(3, 5);
$walByteSavepoints->savepoint('single_setting_row_wal');
$walByteSavepoints->recordWalFrameWrite(4, 8);
$walByteSavepoints->recordWalFrameWrite(5, 8, true);
$walTruncationPlan = $walByteSavepoints->walRollbackToByteTruncationPlan('module_settings_wal', $parsedWal, $walBytes);
$truncatedWalBytes = $walByteSavepoints->walRollbackToWalBytes('module_settings_wal', $parsedWal, $walBytes);

$pageSize = 512;
$pageOneBefore = str_pad('app-settings-before-import', $pageSize, "\0");
$pageTwoBefore = str_pad('load-policy-index-before-import', $pageSize, "\0");
$settingsBeforeModule = str_pad('module-settings-before-batch', $pageSize, "\0");
$singleSettingBeforeRow = str_pad('single-setting-before-row', $pageSize, "\0");
$dirtyDatabase = str_pad('app-settings-dirty-root', $pageSize, "\0")
    . str_pad('load-policy-index-dirty', $pageSize, "\0")
    . str_pad('module-settings-dirty', $pageSize, "\0")
    . str_pad('single-setting-dirty', $pageSize, "\0");

$imageSavepoints = new SQLiteSavepointStack();
$imageSavepoints->beginTransaction('app_setting_image_import');
$imageSavepoints->recordPageImageWrite(1, $pageOneBefore);
$imageSavepoints->recordPageImageWrite(2, $pageTwoBefore);
$imageSavepoints->savepoint('module_settings_image');
$imageSavepoints->recordPageImageWrite(3, $settingsBeforeModule);
$imageSavepoints->savepoint('single_setting_image');
$imageSavepoints->recordPageImageWrite(4, $singleSettingBeforeRow);
$imageRollbackPlan = $imageSavepoints->rollbackToImagePlan('module_settings_image', $pageSize);
$imageRollbackPreview = $imageSavepoints->rollbackToDatabaseImage('module_settings_image', $dirtyDatabase, $pageSize);
$fullImageRollbackPlan = $imageSavepoints->rollbackImagePlan($pageSize);
$fullImageRollbackPreview = $imageSavepoints->rollbackDatabaseImage($dirtyDatabase, $pageSize);

echo json_encode([
    'beforeRollbackToModuleSettings' => $beforeRollback,
    'rollbackToModuleSettingsPlan' => $rollbackPlan,
    'rollbackToModuleSettingsPageNumbers' => $rollbackPreview,
    'rollbackToSingleSettingRowPageNumbers' => $singleSettingRollbackPreview,
    'rollbackToModuleSettingsWithPlan' => $rollbackWithPlan,
    'afterRollbackToModuleSettings' => $afterRollback,
    'releaseModuleSettingsPlan' => $releasePlan,
    'releaseModuleSettingsWithPlan' => $releaseWithPlan,
    'afterReleaseModuleSettings' => $afterRelease,
    'releaseOuterTransactionPlan' => $outerReleasePlan,
    'releaseOuterTransactionWithPlan' => $outerReleaseWithPlan,
    'commitPlan' => $commitPlan,
    'commitWithPlan' => $commitWithPlan,
    'commitTransactionActiveAfter' => $commitPreview->transactionActive(),
    'fullRollbackPlan' => $fullRollbackPlan,
    'fullRollbackWithPlan' => $fullRollbackWithPlan,
    'fullRollbackTransactionActiveAfter' => $fullRollbackStack->transactionActive(),
    'walFrameStateBeforeRollback' => [
        [
            'name' => 'app_setting_wal_import',
            'transaction' => true,
            'wal_start_frame' => 0,
            'wal_frame_indexes' => [1, 2],
        ],
        [
            'name' => 'module_settings_wal',
            'transaction' => false,
            'wal_start_frame' => 2,
            'wal_frame_indexes' => [3, 4],
        ],
        [
            'name' => 'single_setting_row_wal',
            'transaction' => false,
            'wal_start_frame' => 4,
            'wal_frame_indexes' => [5, 6],
        ],
    ],
    'walRollbackToModuleSettingsPlan' => $walRollbackPlan,
    'walRollbackToModuleSettingsWithPlan' => $walRollbackWithPlan,
    'walFrameStateAfterRollback' => $walAfterRollback,
    'walReleaseModuleSettingsPlan' => $walReleasePlan,
    'walReleaseModuleSettingsWithPlan' => $walReleaseWithPlan,
    'walFrameStateAfterRelease' => $walAfterRelease,
    'walRollbackToModuleSettingsByteTruncationPlan' => $walTruncationPlan,
    'walRollbackToModuleSettingsTruncatedBytes' => [
        'bytes' => strlen($truncatedWalBytes),
        'frameCount' => SQLiteWal::parse($truncatedWalBytes, null, true)->frameCount(),
        'containsModuleDraftFrame' => str_contains($truncatedWalBytes, 'module-settings-draft-frame'),
        'containsSingleSettingDraftFrame' => str_contains($truncatedWalBytes, 'single-setting-draft-frame'),
        'containsSingleSettingCommitFrame' => str_contains($truncatedWalBytes, 'single-setting-commit-frame'),
    ],
    'pageImageRollbackToModuleSettingsPlan' => $imageRollbackPlan,
    'pageImageRollbackPreview' => [
        'page1Prefix' => rtrim(substr($imageRollbackPreview, 0, 64), "\0"),
        'page2Prefix' => rtrim(substr($imageRollbackPreview, $pageSize, 64), "\0"),
        'page3Prefix' => rtrim(substr($imageRollbackPreview, $pageSize * 2, 64), "\0"),
        'page4Prefix' => rtrim(substr($imageRollbackPreview, $pageSize * 3, 64), "\0"),
        'bytes' => strlen($imageRollbackPreview),
    ],
    'fullTransactionImageRollbackPlan' => $fullImageRollbackPlan,
    'fullTransactionImageRollbackPreview' => [
        'page1Prefix' => rtrim(substr($fullImageRollbackPreview, 0, 64), "\0"),
        'page2Prefix' => rtrim(substr($fullImageRollbackPreview, $pageSize, 64), "\0"),
        'page3Prefix' => rtrim(substr($fullImageRollbackPreview, $pageSize * 2, 64), "\0"),
        'page4Prefix' => rtrim(substr($fullImageRollbackPreview, $pageSize * 3, 64), "\0"),
        'bytes' => strlen($fullImageRollbackPreview),
    ],
    'pendingPageNumbers' => $savepoints->pendingPageNumbers(),
    'transactionActive' => $savepoints->transactionActive(),
    'applicationUse' => 'Preview nested SAVEPOINT/ROLLBACK TO/RELEASE/ROLLBACK plans, page-dirty state, WAL frame truncation boundaries, concrete WAL sidecar byte truncation, bounded savepoint page-image restoration, and full transaction image recovery for app_settings imports without the SQLite extension, so recovery tooling can explain which database pages and WAL frames would roll back, merge upward, or remain pending after a failed setting-row import.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
