<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanApiCoordinator;
use PortLibs\Syncthing\FolderScanApiRequestQueue;
use PortLibs\Syncthing\FolderScanCheckpointStore;
use PortLibs\Syncthing\FolderScanScheduler;
use PortLibs\Syncthing\FolderScanService;

return [
    'scan API request queue coalesces pending and running folder scans before coordinator invocation' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_queue_root();
        try {
            syncthing_folder_scan_queue_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $service = new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore());
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $service);
            $queue = new FolderScanApiRequestQueue(new FolderScanApiCoordinator($scheduler), maxPending: 4, maxCompleted: 4);

            $queued = $queue->enqueue([
                'folder' => 'wordpress-media',
                'sub' => ['\\wp-content\\uploads\\2026\\05\\', 'wp-content/uploads/2026/05'],
                'hashBlocks' => 'true',
                'blockSize' => '4',
            ], now: 1000);
            $coalesced = $queue->enqueue([
                'folder' => 'wordpress-media',
                'subdirs' => ['wp-content/uploads/2026/05'],
                'hashBlocks' => true,
                'blockSize' => 4,
            ], now: 1001);

            $t->same(FolderScanApiRequestQueue::HTTP_ACCEPTED, $queued->statusCode);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $coalesced->statusCode);
            $t->same('queued', $queued->body['status']);
            $t->same('coalesced', $coalesced->body['status']);
            $t->same(1, $coalesced->body['requestId']);
            $t->same(1, $queue->toRestStatus()['pendingCount']);
            $t->same(0, $queue->toRestStatus()['runningCount']);
            $t->same(1, $queue->toRestStatus()['pending'][0]['duplicateCount']);
            $t->same(null, $service->checkpoint(1001));

            $running = $queue->startNext(1002);
            $t->same(1, $running['id']);
            $t->same('running', $running['state']);
            $t->same(0, $queue->toRestStatus()['pendingCount']);
            $t->same(1, $queue->toRestStatus()['runningCount']);
            $t->same(null, $service->checkpoint(1002));

            $runningDuplicate = $queue->enqueue([
                'folders' => ['wordpress-media' => ['wp-content/uploads/2026/05']],
                'hashBlocks' => true,
                'blockSize' => 4,
            ], now: 1003);
            $t->same('coalesced', $runningDuplicate->body['status']);
            $t->same(2, $runningDuplicate->body['request']['duplicateCount']);

            $finished = $queue->finishRunning(1, 1004);
            $body = $finished->body;

            $t->same(FolderScanApiCoordinator::HTTP_OK, $finished->statusCode);
            $t->same('completed', $body['status']);
            $t->same(0, $body['queue']['pendingCount']);
            $t->same(0, $body['queue']['runningCount']);
            $t->same(1, $body['queue']['completedCount']);
            $t->same(2, $body['queue']['completed'][0]['duplicateCount']);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $body['queue']['completed'][0]['responseStatusCode']);
            $t->same(['wp-content/uploads/2026/05', 'wp-content/uploads/2026/05/hero.jpg'], $body['response']['body']['result']['folders']['wordpress-media']['completedPaths']);
            $t->true(!str_contains(json_encode($body, JSON_UNESCAPED_SLASHES), $root));
            $t->same(1, $service->checkpoint(1004)?->revision);
        } finally {
            syncthing_folder_scan_queue_rm($root);
        }
    },
    'scan API request queue rejects invalid and over-capacity requests without scanning' => static function (TestRunner $t): void {
        $mediaRoot = syncthing_folder_scan_queue_root();
        $contentRoot = syncthing_folder_scan_queue_root();
        try {
            syncthing_folder_scan_queue_write($mediaRoot, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            syncthing_folder_scan_queue_write($contentRoot, 'wp-content/themes/block/theme.json', '{}');

            $media = new FolderScanService('wordpress-media', new FileInfoScanner($mediaRoot), new FolderScanCheckpointStore());
            $content = new FolderScanService('wordpress-content', new FileInfoScanner($contentRoot), new FolderScanCheckpointStore());
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', $media);
            $scheduler->addFolder('wordpress-content', $content);
            $queue = new FolderScanApiRequestQueue(new FolderScanApiCoordinator($scheduler), maxPending: 1, maxCompleted: 1);

            $accepted = $queue->enqueue(['folder' => 'wordpress-media'], now: 1100);
            $full = $queue->enqueue(['folder' => 'wordpress-content'], now: 1101);
            $invalid = $queue->enqueue(['folder' => 'wordpress-media', 'sub' => '../wp-config.php'], now: 1102);

            $t->same(FolderScanApiRequestQueue::HTTP_ACCEPTED, $accepted->statusCode);
            $t->same(FolderScanApiRequestQueue::HTTP_TOO_MANY_REQUESTS, $full->statusCode);
            $t->same('queue_full', $full->body['error']);
            $t->same(FolderScanApiCoordinator::HTTP_BAD_REQUEST, $invalid->statusCode);
            $t->same('invalid_request', $invalid->body['error']);
            $t->same(1, $queue->toRestStatus()['pendingCount']);
            $t->same(null, $media->checkpoint(1102));
            $t->same(null, $content->checkpoint(1102));
        } finally {
            syncthing_folder_scan_queue_rm($mediaRoot);
            syncthing_folder_scan_queue_rm($contentRoot);
        }
    },
    'scan API request queue records completed failures and allows a fresh scan after completion' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_queue_root();
        try {
            syncthing_folder_scan_queue_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder('wordpress-media', new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore()), running: false);
            $queue = new FolderScanApiRequestQueue(new FolderScanApiCoordinator($scheduler), maxPending: 2, maxCompleted: 2);

            $first = $queue->enqueue(['folder' => 'wordpress-media'], now: 1200);
            $failed = $queue->runNext(1201);

            $t->same(FolderScanApiRequestQueue::HTTP_ACCEPTED, $first->statusCode);
            $t->same(FolderScanApiCoordinator::HTTP_CONFLICT, $failed?->statusCode);
            $t->same('completed', $failed?->body['status']);
            $t->same(FolderScanApiCoordinator::HTTP_CONFLICT, $queue->toRestStatus()['completed'][0]['responseStatusCode']);

            $scheduler->resumeFolder('wordpress-media');
            $second = $queue->enqueue(['folder' => 'wordpress-media'], now: 1202);
            $success = $queue->runNext(1203);

            $t->same(FolderScanApiRequestQueue::HTTP_ACCEPTED, $second->statusCode);
            $t->same(2, $second->body['requestId']);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $success?->statusCode);
            $t->same(2, $queue->toRestStatus()['completedCount']);
            $t->same([1, 2], array_column($queue->toRestStatus()['completed'], 'id'));
        } finally {
            syncthing_folder_scan_queue_rm($root);
        }
    },
    'scan API request queue keeps distinct next delays and resets scheduled status on completion' => static function (TestRunner $t): void {
        $root = syncthing_folder_scan_queue_root();
        try {
            syncthing_folder_scan_queue_write($root, 'wp-content/uploads/2026/05/hero.jpg', 'abcdefgh');
            $scheduler = new FolderScanScheduler();
            $scheduler->addFolder(
                'wordpress-media',
                new FolderScanService('wordpress-media', new FileInfoScanner($root), new FolderScanCheckpointStore()),
            );
            $queue = new FolderScanApiRequestQueue(new FolderScanApiCoordinator($scheduler), maxPending: 4, maxCompleted: 4);

            $first = $queue->enqueue(['folder' => 'wordpress-media', 'next' => '60'], now: 1300);
            $duplicate = $queue->enqueue(['folder' => 'wordpress-media', 'delay' => 60], now: 1301);
            $second = $queue->enqueue(['folder' => 'wordpress-media', 'nextSeconds' => 120], now: 1302);

            $t->same(FolderScanApiRequestQueue::HTTP_ACCEPTED, $first->statusCode);
            $t->same('coalesced', $duplicate->body['status']);
            $t->same(1, $duplicate->body['requestId']);
            $t->same(FolderScanApiRequestQueue::HTTP_ACCEPTED, $second->statusCode);
            $t->same(2, $second->body['requestId']);
            $t->same(2, $queue->toRestStatus()['pendingCount']);
            $t->same(60, $queue->toRestStatus()['pending'][0]['request']['nextSeconds']);
            $t->same(120, $queue->toRestStatus()['pending'][1]['request']['nextSeconds']);

            $finishedFirst = $queue->runNext(1310);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $finishedFirst?->statusCode);
            $t->same(60, $finishedFirst?->body['request']['request']['nextSeconds']);
            $t->same(1370, $scheduler->scheduledScanStatus('wordpress-media', 1310)['scheduledAt'] ?? null);
            $t->same(60, $scheduler->scheduledScanStatus('wordpress-media', 1310)['remainingSeconds'] ?? null);

            $finishedSecond = $queue->runNext(1320);
            $t->same(FolderScanApiCoordinator::HTTP_OK, $finishedSecond?->statusCode);
            $t->same(120, $finishedSecond?->body['request']['request']['nextSeconds']);
            $t->same(1440, $scheduler->scheduledScanStatus('wordpress-media', 1320)['scheduledAt'] ?? null);
            $t->same(2, $queue->toRestStatus()['completedCount']);
        } finally {
            syncthing_folder_scan_queue_rm($root);
        }
    },
];

function syncthing_folder_scan_queue_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-folder-scan-queue-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary folder scan queue root');
    }

    return $root;
}

function syncthing_folder_scan_queue_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create folder scan queue test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write folder scan queue test file');
    }
}

function syncthing_folder_scan_queue_rm(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        syncthing_folder_scan_queue_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
