<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\OneDriveDeltaCursor;

$startPage = [
    '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/drives/site-drive/root/delta?token=seed-token',
];

$startToken = OneDriveDeltaCursor::startPageToken($startPage);
$nextRequest = OneDriveDeltaCursor::buildDriveDeltaRequest('site-drive', $startToken);

$delta = OneDriveDeltaCursor::notifications([
    '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/drives/site-drive/root/delta?token=after-review',
    'value' => [
        [
            'name' => 'site-backups',
            'parentReference' => ['id' => 'root', 'path' => '/drives/site-drive/root:'],
            'folder' => [],
        ],
        [
            'name' => 'export.wxr',
            'parentReference' => ['id' => 'site-backups', 'path' => '/drives/site-drive/root:/site-backups'],
            'file' => ['mimeType' => 'application/rss+xml'],
        ],
        [
            'name' => 'uploads',
            'parentReference' => ['id' => 'site-backups', 'path' => '/drives/site-drive/root:/site-backups'],
            'folder' => ['childCount' => 1],
        ],
        [
            'name' => 'hero.jpg',
            'parentReference' => ['id' => 'uploads', 'path' => '/drives/site-drive/root:/site-backups/uploads'],
            'file' => ['mimeType' => 'image/jpeg'],
        ],
        [
            'name' => 'other-site.sql',
            'parentReference' => ['id' => 'other-site', 'path' => '/drives/site-drive/root:/other-site'],
            'file' => ['mimeType' => 'application/sql'],
        ],
        [
            'name' => 'bad-cache-entry.php',
            'parentReference' => ['id' => 'site-backups', 'path' => '/drives/site-drive/root/site-backups'],
            'file' => ['mimeType' => 'text/x-php'],
        ],
    ],
], 'site-backups');

$changedObjects = [];
$changedDirectories = [];
foreach ($delta['changes'] as $change) {
    if ($change['type'] === OneDriveDeltaCursor::ENTRY_OBJECT) {
        $changedObjects[] = $change['path'];
    } else {
        $changedDirectories[] = $change['path'];
    }
}

return [
    'startToken' => $startToken,
    'nextRequest' => $nextRequest,
    'nextToken' => $delta['nextToken'],
    'changedObjects' => $changedObjects,
    'changedDirectories' => $changedDirectories,
    'rootSkipped' => !in_array('site-backups', $changedDirectories, true),
    'outsideRootSkipped' => !in_array('other-site.sql', $changedObjects, true),
    'errors' => $delta['errors'],
];
