<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveCleanupCommand;

return [
    'onedrive cleanup command deletes old versions for each walked object' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review', 'pre-import'],
            ],
            [
                'remote' => 'uploads/image.jpg',
                'versions' => ['current-image'],
            ],
        ]);

        $t->same(2, $flow['walkedObjects']);
        $t->same(2, $flow['versionRequests']);
        $t->same(['exports/site.wxr#old-review', 'exports/site.wxr#pre-import'], $flow['deletedVersions']);
        $t->same([], $flow['skippedVersions']);
        $t->same([], $flow['logs']);
        $t->same(null, $flow['error']);
        $t->same(true, $flow['providerCalled']);
    },
    'onedrive cleanup command dry run records skips without deleting versions' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review', 'pre-import'],
                'deleteErrors' => [
                    'pre-import' => 'would not be observed on dry run',
                ],
            ],
        ], [
            'dryRun' => true,
        ]);

        $t->same(1, $flow['walkedObjects']);
        $t->same([], $flow['deletedVersions']);
        $t->same(['exports/site.wxr#old-review', 'exports/site.wxr#pre-import'], $flow['skippedVersions']);
        $t->same([], $flow['logs']);
        $t->same(null, $flow['error']);
    },
    'onedrive cleanup command logs per object delete errors and continues' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review', 'pre-import'],
                'deleteErrors' => [
                    'old-review' => 'Graph delete denied',
                ],
            ],
            [
                'remote' => 'uploads/image.jpg',
                'versions' => ['current-image', 'old-image'],
            ],
        ]);

        $t->same(2, $flow['walkedObjects']);
        $t->same(2, $flow['versionRequests']);
        $t->same(['uploads/image.jpg#old-image'], $flow['deletedVersions']);
        $t->same(['exports/site.wxr: Failed to remove versions: Graph delete denied'], $flow['logs']);
        $t->same(null, $flow['error']);
    },
    'onedrive cleanup command logs per object list errors and continues' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
                'listError' => 'Graph versions list denied',
            ],
            [
                'remote' => 'uploads/image.jpg',
                'versions' => ['current-image', 'old-image'],
            ],
        ]);

        $t->same(2, $flow['walkedObjects']);
        $t->same(2, $flow['versionRequests']);
        $t->same(['uploads/image.jpg#old-image'], $flow['deletedVersions']);
        $t->same(['exports/site.wxr: Failed to remove versions: Graph versions list denied'], $flow['logs']);
        $t->same(null, $flow['error']);
    },
    'onedrive cleanup command fails traversal and type errors before version cleanup completes' => static function (TestRunner $t): void {
        $walk = OneDriveCleanupCommand::run([], [
            'walkError' => 'failed to list root',
        ]);
        $type = OneDriveCleanupCommand::run([
            [
                'remote' => 'shared-folder',
                'type' => 'directory',
                'versions' => [],
            ],
        ]);

        $t->same('failed to list root', $walk['error']);
        $t->same(0, $walk['walkedObjects']);
        $t->same(false, $walk['providerCalled']);
        $t->same('internal error: not a onedrive object', $type['error']);
        $t->same(0, $type['versionRequests']);
        $t->same(false, $type['providerCalled']);
    },
    'onedrive cleanup command can be disabled when no versions cleanup is off' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'noVersions' => false,
        ]);

        $t->same(0, $flow['walkedObjects']);
        $t->same(0, $flow['versionRequests']);
        $t->same([], $flow['deletedVersions']);
        $t->same(false, $flow['providerCalled']);
    },
    'onedrive cleanup command validates command remote arguments before cleanup work' => static function (TestRunner $t): void {
        $missing = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'remoteArgs' => [],
            'featureAvailable' => false,
            'walkError' => 'would not be reached',
        ]);
        $extra = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'remoteArgs' => ['onedrive:', 'extra:'],
        ]);
        $empty = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'remoteArgs' => [''],
            'featureAvailable' => false,
            'walkError' => 'would not be reached',
        ]);

        $t->same('cleanup command expects exactly one remote argument', $missing['error']);
        $t->same(0, $missing['walkedObjects']);
        $t->same(0, $missing['versionRequests']);
        $t->same(false, $missing['providerCalled']);
        $t->same('cleanup command expects exactly one remote argument', $extra['error']);
        $t->same(false, $extra['providerCalled']);
        $t->same('cleanup command expects exactly one remote argument', $empty['error']);
        $t->same(0, $empty['walkedObjects']);
        $t->same(0, $empty['versionRequests']);
        $t->same(false, $empty['providerCalled']);
    },
    'onedrive cleanup command disabled no versions path does not require cleanup feature' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'noVersions' => false,
            'featureAvailable' => false,
            'walkError' => 'would not be reached',
        ]);

        $t->same(null, $flow['error']);
        $t->same(0, $flow['walkedObjects']);
        $t->same(0, $flow['versionRequests']);
        $t->same([], $flow['deletedVersions']);
        $t->same([], $flow['logs']);
        $t->same(false, $flow['providerCalled']);
    },
    'onedrive cleanup command disabled no versions path ignores dry run and traversal state' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'noVersions' => false,
            'dryRun' => true,
            'walkError' => 'would not be reached',
        ]);

        $t->same(null, $flow['error']);
        $t->same(0, $flow['walkedObjects']);
        $t->same(0, $flow['versionRequests']);
        $t->same([], $flow['skippedVersions']);
        $t->same(false, $flow['providerCalled']);
    },
    'onedrive cleanup command disabled no versions path ignores object type checks' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports',
                'type' => 'directory',
                'versions' => [],
            ],
        ], [
            'noVersions' => false,
        ]);

        $t->same(null, $flow['error']);
        $t->same(0, $flow['walkedObjects']);
        $t->same(0, $flow['versionRequests']);
        $t->same([], $flow['logs']);
        $t->same(false, $flow['providerCalled']);
    },
    'onedrive cleanup command fails masked cleanup feature before dry run' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'dryRun' => true,
            'featureAvailable' => false,
        ]);

        $t->same('cleanup unsupported', $flow['error']);
        $t->same(0, $flow['walkedObjects']);
        $t->same(0, $flow['versionRequests']);
        $t->same([], $flow['skippedVersions']);
        $t->same(false, $flow['providerCalled']);
    },
    'onedrive cleanup command checks masked feature before traversal errors' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'walkError' => 'failed to list root',
            'featureAvailable' => false,
        ]);

        $t->same('cleanup unsupported', $flow['error']);
        $t->same(0, $flow['walkedObjects']);
        $t->same(0, $flow['versionRequests']);
        $t->same([], $flow['deletedVersions']);
        $t->same(false, $flow['providerCalled']);
    },
    'onedrive cleanup command checks masked feature before type errors' => static function (TestRunner $t): void {
        $flow = OneDriveCleanupCommand::run([
            [
                'remote' => 'exports',
                'type' => 'directory',
                'versions' => [],
            ],
        ], [
            'featureAvailable' => false,
        ]);

        $t->same('cleanup unsupported', $flow['error']);
        $t->same(0, $flow['walkedObjects']);
        $t->same(0, $flow['versionRequests']);
        $t->same([], $flow['logs']);
        $t->same(false, $flow['providerCalled']);
    },
    'onedrive rc cleanup uses fs param and bypasses command remote arity' => static function (TestRunner $t): void {
        $missingFs = OneDriveCleanupCommand::runRemoteControl([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'featureAvailable' => false,
            'walkError' => 'would not be reached',
        ]);
        $emptyFs = OneDriveCleanupCommand::runRemoteControl([], [
            'fs' => '',
            'featureAvailable' => false,
        ]);
        $missingFsDisabled = OneDriveCleanupCommand::runRemoteControl([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'noVersions' => false,
            'remoteArgs' => [],
        ]);
        $cleanup = OneDriveCleanupCommand::runRemoteControl([
            [
                'remote' => 'exports/site.wxr',
                'versions' => ['current', 'old-review'],
            ],
        ], [
            'fs' => 'onedrive:',
            'remoteArgs' => [],
        ]);
        $unsupported = OneDriveCleanupCommand::runRemoteControl([], [
            'fs' => 'local:',
            'featureAvailable' => false,
            'remoteArgs' => ['unexpected', 'command', 'args'],
        ]);

        $t->same('rc operations/cleanup requires fs', $missingFs['error']);
        $t->same(false, $missingFs['providerCalled']);
        $t->same('rc operations/cleanup requires fs', $emptyFs['error']);
        $t->same('rc operations/cleanup requires fs', $missingFsDisabled['error']);
        $t->same(false, $missingFsDisabled['providerCalled']);
        $t->same(['exports/site.wxr#old-review'], $cleanup['deletedVersions']);
        $t->same(null, $cleanup['error']);
        $t->same(true, $cleanup['providerCalled']);
        $t->same('cleanup unsupported', $unsupported['error']);
        $t->same(false, $unsupported['providerCalled']);
    },
    'wordpress onedrive cleanup command preflight removes stale wxr versions only' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-cleanup-command-preflight.php';

        $t->same('onedrive-cleanup-command-preflight', $example['source']);
        $t->same(['exports/site.wxr#old-review', 'exports/site.wxr#pre-import'], $example['deletedVersions']);
        $t->same(['exports/site.wxr#old-review', 'exports/site.wxr#pre-import'], $example['dryRunSkippedVersions']);
        $t->same(['uploads/2026/05/import.jpg#superseded'], $example['continuedAfterErrorDeletedVersions']);
        $t->same(['exports/site.wxr: Failed to remove versions: Graph delete denied'], $example['continuedAfterErrorLogs']);
        $t->same(['uploads/2026/05/import.jpg#superseded'], $example['continuedAfterListErrorDeletedVersions']);
        $t->same(['exports/site.wxr: Failed to remove versions: Graph versions list denied'], $example['continuedAfterListErrorLogs']);
        $t->same('cleanup unsupported', $example['featureMaskedError']);
        $t->same(null, $example['disabledNoVersionsError']);
        $t->same(false, $example['disabledNoVersionsProviderCalled']);
        $t->same(null, $example['disabledNoVersionsTypeError']);
        $t->same('internal error: not a onedrive object', $example['enabledTypeError']);
        $t->same(false, $example['enabledTypeErrorProviderCalled']);
        $t->same('cleanup command expects exactly one remote argument', $example['missingRemoteArgError']);
        $t->same(false, $example['missingRemoteArgProviderCalled']);
        $t->same('cleanup command expects exactly one remote argument', $example['extraRemoteArgError']);
        $t->same(false, $example['extraRemoteArgProviderCalled']);
        $t->same('cleanup command expects exactly one remote argument', $example['emptyRemoteArgError']);
        $t->same(false, $example['emptyRemoteArgProviderCalled']);
        $t->same('rc operations/cleanup requires fs', $example['rcMissingFsError']);
        $t->same(false, $example['rcMissingFsProviderCalled']);
        $t->same('rc operations/cleanup requires fs', $example['rcMissingFsDisabledError']);
        $t->same(false, $example['rcMissingFsDisabledProviderCalled']);
        $t->same(['exports/site.wxr#old-review'], $example['rcDeletedVersions']);
        $t->same('cleanup unsupported', $example['rcUnsupportedError']);
        $t->same(false, $example['rcUnsupportedProviderCalled']);
        $t->same(false, $example['secretInputsRead']);
    },
];
