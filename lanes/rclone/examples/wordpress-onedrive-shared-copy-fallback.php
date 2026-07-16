<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$sharedDrive = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
$siteDrive = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
$plan = new SyncPlan();

$sharedDrive->put('shared/site.wxr', '<rss>fresh shared export</rss>', [
    'modTime' => '2026-05-23T08:00:00Z',
    'mimeType' => 'application/rss+xml',
    'metadata' => ['wp-artifact' => 'shared-wxr'],
    'id' => 'shared-drive-export',
]);
$siteDrive->put('exports/site.wxr', '<rss>previous export</rss>', [
    'metadata' => ['wp-artifact' => 'previous-wxr'],
]);

$stats = null;
$copy = $plan->copyFileWithServerSideFallback(
    $siteDrive,
    $sharedDrive,
    'exports/site.wxr',
    'shared/site.wxr',
    [
        'provider' => 'onedrive',
        'temporarySuffix' => '.wpcopy',
        'apiResult' => [
            'sourceDriveType' => 'personal',
            'destinationDriveType' => 'personal',
            'sourceDriveId' => 'family-shared-drive',
            'destinationDriveId' => 'site-owner-drive',
        ],
        'providerError' => ['kind' => 'async-access-denied'],
    ],
    [],
    $stats,
);

return [
    'fallbackUsed' => $copy['fallbackUsed'],
    'fallbackReason' => $copy['fallbackReason'],
    'copiedPath' => $copy['copied']?->path,
    'copiedBytes' => $siteDrive->get('exports/site.wxr'),
    'copiedModTime' => $siteDrive->info('exports/site.wxr')->modTime,
    'copiedMetadata' => $siteDrive->info('exports/site.wxr')->metadata,
    'sourceStillAvailable' => $sharedDrive->pathExists('shared/site.wxr'),
    'temporaryObjectVisible' => $siteDrive->pathExists('exports/site.wxr.wpcopy'),
    'stats' => $stats,
];
