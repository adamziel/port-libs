<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullFinalizationResult;
use PortLibs\Syncthing\PullScanner;
use PortLibs\Syncthing\RequestServer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$scheduledBatches = [];
$scanner = new PullScanner(
    static function (array $paths) use (&$scheduledBatches): void {
        $scheduledBatches[] = $paths;
    },
    folderId: 'wordpress-media',
);

$blockedMediaPull = new PullFinalizationResult(
    closed: true,
    finalized: false,
    error: 'checking existing file: file modified but not rescanned; will try again later',
    tempName: RequestServer::temporaryName('wp-content/uploads/2026/local-first-hero.jpg'),
    finalName: 'wp-content/uploads/2026/local-first-hero.jpg',
    scanNames: ['wp-content/uploads/2026/local-first-hero.jpg'],
);
$stalePlaygroundFolder = new FileInfo(
    name: 'wp-content/uploads/2026/playground-export-cache',
    deleted: true,
    type: FileInfo::TYPE_DIRECTORY,
);

$scanner->queueFinalization($blockedMediaPull);
$scanner->queueDeletion($stalePlaygroundFolder);
$scanner->queueDirectory('wp-content/uploads/2026/playground-export-cache/local-crops', 'receive-only-child');
$scanner->queueFile('wp-content/uploads/2026/local-first-hero.jpg', 'duplicate-local-edit');

$pendingBeforePullCloses = [
    'paths' => $scanner->pendingPaths(),
    'types' => $scanner->pendingCountsByType(),
    'items' => $scanner->pendingItems(),
    'scheduledBatches' => $scheduledBatches,
];
$postPullSchedule = $scanner->close();

echo json_encode([
    'folder' => $scanner->folderId(),
    'pendingBeforePullCloses' => $pendingBeforePullCloses,
    'postPullSchedule' => $postPullSchedule->toArray(),
    'scheduledBatches' => $scheduledBatches,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
