<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$remote = new MemoryProvider();
$remote->put('exports/site.wxr', '<rss>portable export</rss>', [
    'modTime' => '2003-02-03T04:05:06.499999999Z',
    'mimeType' => 'text/plain',
    'metadata' => [
        'mtime' => '2003-02-03T04:05:06.499999999Z',
        'wp-artifact' => 'draft-export',
    ],
]);
$remote->put('wp-content/uploads/2026/05/hero-temp.jpg', 'image bytes', [
    'modTime' => '2003-02-03T04:05:06.499999999Z',
    'metadata' => [
        'mtime' => '2003-02-03T04:05:06.499999999Z',
        'wp-artifact' => 'temporary-upload',
    ],
]);

$metadataSet = [
    'mtime' => '2004-03-03T04:05:06.499999999Z',
    'wp-artifact' => 'migration-handoff',
    'content-type' => 'application/rss+xml',
];

$plan = new SyncPlan();
$copied = $plan->copyFile($remote, $remote, 'handoff/site.wxr', 'exports/site.wxr', [
    'metadataSet' => $metadataSet,
]);
$moved = $plan->moveFile(
    $remote,
    $remote,
    'wp-content/uploads/2026/05/hero.jpg',
    'wp-content/uploads/2026/05/hero-temp.jpg',
    ['metadataSet' => $metadataSet],
);

try {
    $remote->get('wp-content/uploads/2026/05/hero-temp.jpg');
    $temporaryUploadVisible = true;
} catch (RuntimeException) {
    $temporaryUploadVisible = false;
}

return [
    'copiedPath' => $copied['copied']?->path,
    'copiedModTime' => $remote->info('handoff/site.wxr')->modTime,
    'copiedMimeType' => $remote->info('handoff/site.wxr')->mimeType,
    'copiedMetadata' => $remote->info('handoff/site.wxr')->metadata,
    'sourceMetadata' => $remote->info('exports/site.wxr')->metadata,
    'movedPath' => $moved['moved']?->path,
    'movedMetadata' => $remote->info('wp-content/uploads/2026/05/hero.jpg')->metadata,
    'temporaryUploadVisible' => $temporaryUploadVisible,
];
