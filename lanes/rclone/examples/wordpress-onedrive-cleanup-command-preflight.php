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
$missingRemoteArgDisabled = OneDriveCleanupCommand::run($objects, [
    'remoteArgs' => [],
    'noVersions' => false,
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$extraRemoteArgDisabled = OneDriveCleanupCommand::run($objects, [
    'remoteArgs' => ['onedrive:', 'backup:'],
    'noVersions' => false,
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$emptyRemoteArgDisabled = OneDriveCleanupCommand::run($objects, [
    'remoteArgs' => [''],
    'noVersions' => false,
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$validRemoteArgDisabled = OneDriveCleanupCommand::run($objects, [
    'remoteArgs' => ['onedrive:exports'],
    'noVersions' => false,
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$validRemoteArgFeatureMasked = OneDriveCleanupCommand::run($objects, [
    'remoteArgs' => ['onedrive:exports'],
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$rcMissingFs = OneDriveCleanupCommand::runRemoteControl($objects, [
    'featureAvailable' => false,
    'walkError' => 'would not be reached',
]);
$rcMissingFsDisabled = OneDriveCleanupCommand::runRemoteControl($objects, [
    'noVersions' => false,
    'remoteArgs' => [],
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
$rcCleanupWithCommandArgs = OneDriveCleanupCommand::runRemoteControl([
    [
        'remote' => 'exports/site.wxr',
        'versions' => ['current', 'old-review'],
    ],
], [
    'fs' => 'onedrive:',
    'remoteArgs' => ['not', 'used', 'by', 'rc'],
]);
$rcDisabledCommandArgs = OneDriveCleanupCommand::runRemoteControl($objects, [
    'fs' => 'onedrive:',
    'noVersions' => false,
    'remoteArgs' => ['not', 'used', 'by', 'rc'],
]);
$rcUnsupported = OneDriveCleanupCommand::runRemoteControl($objects, [
    'fs' => 'local:',
    'featureAvailable' => false,
    'remoteArgs' => ['not', 'used', 'by', 'rc'],
]);

return [
    'source' => 'onedrive-cleanup-command-preflight',
    'deletedVersions' => $cleanup['deletedVersions'],
    'cleanupStoppedAt' => $cleanup['stoppedAt'],
    'dryRunSkippedVersions' => $dryRun['skippedVersions'],
    'continuedAfterErrorDeletedVersions' => $continuedAfterError['deletedVersions'],
    'continuedAfterErrorLogs' => $continuedAfterError['logs'],
    'continuedAfterListErrorDeletedVersions' => $continuedAfterListError['deletedVersions'],
    'continuedAfterListErrorLogs' => $continuedAfterListError['logs'],
    'featureMaskedError' => $featureMasked['error'],
    'featureMaskedStoppedAt' => $featureMasked['stoppedAt'],
    'disabledNoVersionsError' => $disabledNoVersions['error'],
    'disabledNoVersionsStoppedAt' => $disabledNoVersions['stoppedAt'],
    'disabledNoVersionsProviderCalled' => $disabledNoVersions['providerCalled'],
    'disabledNoVersionsTypeError' => $disabledNoVersionsType['error'],
    'enabledTypeError' => $enabledTypeError['error'],
    'enabledTypeStoppedAt' => $enabledTypeError['stoppedAt'],
    'enabledTypeErrorProviderCalled' => $enabledTypeError['providerCalled'],
    'missingRemoteArgError' => $missingRemoteArg['error'],
    'missingRemoteArgStoppedAt' => $missingRemoteArg['stoppedAt'],
    'missingRemoteArgProviderCalled' => $missingRemoteArg['providerCalled'],
    'extraRemoteArgError' => $extraRemoteArg['error'],
    'extraRemoteArgProviderCalled' => $extraRemoteArg['providerCalled'],
    'emptyRemoteArgError' => $emptyRemoteArg['error'],
    'emptyRemoteArgProviderCalled' => $emptyRemoteArg['providerCalled'],
    'missingRemoteArgDisabledError' => $missingRemoteArgDisabled['error'],
    'missingRemoteArgDisabledStoppedAt' => $missingRemoteArgDisabled['stoppedAt'],
    'missingRemoteArgDisabledProviderCalled' => $missingRemoteArgDisabled['providerCalled'],
    'extraRemoteArgDisabledError' => $extraRemoteArgDisabled['error'],
    'extraRemoteArgDisabledProviderCalled' => $extraRemoteArgDisabled['providerCalled'],
    'emptyRemoteArgDisabledError' => $emptyRemoteArgDisabled['error'],
    'emptyRemoteArgDisabledProviderCalled' => $emptyRemoteArgDisabled['providerCalled'],
    'validRemoteArgDisabledError' => $validRemoteArgDisabled['error'],
    'validRemoteArgDisabledStoppedAt' => $validRemoteArgDisabled['stoppedAt'],
    'validRemoteArgDisabledProviderCalled' => $validRemoteArgDisabled['providerCalled'],
    'validRemoteArgFeatureMaskedError' => $validRemoteArgFeatureMasked['error'],
    'validRemoteArgFeatureMaskedProviderCalled' => $validRemoteArgFeatureMasked['providerCalled'],
    'rcMissingFsError' => $rcMissingFs['error'],
    'rcMissingFsStoppedAt' => $rcMissingFs['stoppedAt'],
    'rcMissingFsProviderCalled' => $rcMissingFs['providerCalled'],
    'rcMissingFsDisabledError' => $rcMissingFsDisabled['error'],
    'rcMissingFsDisabledProviderCalled' => $rcMissingFsDisabled['providerCalled'],
    'rcDeletedVersions' => $rcCleanup['deletedVersions'],
    'rcCommandArgsDeletedVersions' => $rcCleanupWithCommandArgs['deletedVersions'],
    'rcCommandArgsError' => $rcCleanupWithCommandArgs['error'],
    'rcCommandArgsProviderCalled' => $rcCleanupWithCommandArgs['providerCalled'],
    'rcDisabledCommandArgsError' => $rcDisabledCommandArgs['error'],
    'rcDisabledCommandArgsStoppedAt' => $rcDisabledCommandArgs['stoppedAt'],
    'rcDisabledCommandArgsProviderCalled' => $rcDisabledCommandArgs['providerCalled'],
    'rcUnsupportedError' => $rcUnsupported['error'],
    'rcUnsupportedProviderCalled' => $rcUnsupported['providerCalled'],
    'secretInputsRead' => false,
];
