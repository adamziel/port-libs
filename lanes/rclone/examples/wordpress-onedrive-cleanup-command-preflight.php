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

return [
    'source' => 'onedrive-cleanup-command-preflight',
    'deletedVersions' => $cleanup['deletedVersions'],
    'dryRunSkippedVersions' => $dryRun['skippedVersions'],
    'continuedAfterErrorDeletedVersions' => $continuedAfterError['deletedVersions'],
    'continuedAfterErrorLogs' => $continuedAfterError['logs'],
    'secretInputsRead' => false,
];
