<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveCleanupCommand;

require_once __DIR__ . '/../src/OneDriveVersionCleaner.php';
require_once __DIR__ . '/../src/OneDriveCleanupCommand.php';

$objects = [
    [
        'remote' => 'exports/site.wxr',
        'versions' => ['current', 'old-review', 'pre-import'],
    ],
];

$cleanup = OneDriveCleanupCommand::run($objects);
$dryRun = OneDriveCleanupCommand::run($objects, [
    'dryRun' => true,
]);
$continuedAfterError = OneDriveCleanupCommand::run([
    [
        'remote' => 'exports/site.wxr',
        'versions' => ['current', 'old-review'],
        'deleteErrors' => [
            'old-review' => 'Graph delete denied',
        ],
    ],
    [
        'remote' => 'uploads/2026/05/import.jpg',
        'versions' => ['current-media', 'superseded'],
    ],
]);
$continuedAfterListError = OneDriveCleanupCommand::run([
    [
        'remote' => 'exports/site.wxr',
        'versions' => ['current', 'old-review'],
        'listError' => 'Graph versions list denied',
    ],
    [
        'remote' => 'uploads/2026/05/import.jpg',
        'versions' => ['current-media', 'superseded'],
    ],
]);
$featureMasked = OneDriveCleanupCommand::run($objects, [
    'dryRun' => true,
    'featureAvailable' => false,
]);
$disabledNoVersions = OneDriveCleanupCommand::run($objects, [
    'noVersions' => false,
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$disabledNoVersionsType = OneDriveCleanupCommand::run([
    [
        'remote' => 'exports',
        'type' => 'directory',
        'versions' => [],
    ],
], [
    'noVersions' => false,
]);
$enabledTypeError = OneDriveCleanupCommand::run([
    [
        'remote' => 'exports',
        'type' => 'directory',
        'versions' => ['current', 'old-review'],
    ],
]);
$missingRemoteArg = OneDriveCleanupCommand::run($objects, [
    'remoteArgs' => [],
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$extraRemoteArg = OneDriveCleanupCommand::run($objects, [
    'remoteArgs' => ['onedrive:', 'backup:'],
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$emptyRemoteArg = OneDriveCleanupCommand::run($objects, [
    'remoteArgs' => [''],
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$rcMissingFs = OneDriveCleanupCommand::runRemoteControl($objects, [
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$rcCleanup = OneDriveCleanupCommand::runRemoteControl([
    [
        'remote' => 'exports/site.wxr',
        'versions' => ['current', 'old-review'],
    ],
], [
    'fs' => 'onedrive:',
    'remoteArgs' => [],
]);
$rcUnsupported = OneDriveCleanupCommand::runRemoteControl($objects, [
    'fs' => 'local:',
    'featureAvailable' => false,
    'remoteArgs' => ['not', 'used', 'by', 'rc'],
]);

return [
    'source' => 'onedrive-cleanup-command-preflight',
    'deletedVersions' => $cleanup['deletedVersions'],
    'dryRunSkippedVersions' => $dryRun['skippedVersions'],
    'continuedAfterErrorDeletedVersions' => $continuedAfterError['deletedVersions'],
    'continuedAfterErrorLogs' => $continuedAfterError['logs'],
    'continuedAfterListErrorDeletedVersions' => $continuedAfterListError['deletedVersions'],
    'continuedAfterListErrorLogs' => $continuedAfterListError['logs'],
    'featureMaskedError' => $featureMasked['error'],
    'disabledNoVersionsError' => $disabledNoVersions['error'],
    'disabledNoVersionsProviderCalled' => $disabledNoVersions['providerCalled'],
    'disabledNoVersionsTypeError' => $disabledNoVersionsType['error'],
    'enabledTypeError' => $enabledTypeError['error'],
    'enabledTypeErrorProviderCalled' => $enabledTypeError['providerCalled'],
    'missingRemoteArgError' => $missingRemoteArg['error'],
    'missingRemoteArgProviderCalled' => $missingRemoteArg['providerCalled'],
    'extraRemoteArgError' => $extraRemoteArg['error'],
    'extraRemoteArgProviderCalled' => $extraRemoteArg['providerCalled'],
    'emptyRemoteArgError' => $emptyRemoteArg['error'],
    'emptyRemoteArgProviderCalled' => $emptyRemoteArg['providerCalled'],
    'rcMissingFsError' => $rcMissingFs['error'],
    'rcMissingFsProviderCalled' => $rcMissingFs['providerCalled'],
    'rcDeletedVersions' => $rcCleanup['deletedVersions'],
    'rcUnsupportedError' => $rcUnsupported['error'],
    'rcUnsupportedProviderCalled' => $rcUnsupported['providerCalled'],
    'secretInputsRead' => false,
];
