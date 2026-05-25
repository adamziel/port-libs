<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveVersionCleaner;

require_once dirname(__DIR__) . '/src/OneDriveVersionCleaner.php';

$versions = [
    ['id' => 'current-wxr'],
    ['id' => 'previous-review'],
    ['id' => 'pre-import'],
];

$cleanup = OneDriveVersionCleaner::deleteOldVersions('exports/site.wxr', $versions);
$dryRun = OneDriveVersionCleaner::deleteOldVersions('exports/site.wxr', $versions, [
    'dryRun' => true,
]);
$failure = OneDriveVersionCleaner::deleteOldVersions('exports/site.wxr', $versions, [
    'deleteErrors' => [
        'pre-import' => 'Graph delete denied',
    ],
]);

return [
    'source' => 'onedrive-no-versions-cleanup',
    'remote' => $cleanup['remote'],
    'keptVersion' => $cleanup['keptVersion'],
    'deletedVersions' => $cleanup['deletedVersions'],
    'dryRunSkippedVersions' => $dryRun['skippedVersions'],
    'failureDeletedVersions' => $failure['deletedVersions'],
    'deleteError' => $failure['error'],
    'sequence' => $cleanup['sequence'],
    'secretInputsRead' => false,
];
