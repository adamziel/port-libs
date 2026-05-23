<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\DeviceActivity;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\Response;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$activity = new DeviceActivity();
$tracker = new RemoteDownloadProgressTracker([
    'wordpress-media' => ['cdn-peer', 'editor-laptop'],
], $activity);

$bytes = str_repeat('restored media bytes ', 32);
$block = new Block(0, strlen($bytes), hash('sha256', $bytes));
$file = new FileInfo(
    name: 'wp-content/uploads/2026/hero.jpg',
    version: VersionVector::fromCounters([202 => 7]),
    size: strlen($bytes),
    rawBlockSize: strlen($bytes),
    blocks: [$block],
);

$result = $tracker->pullBlock(
    'wordpress-media',
    $file,
    $block,
    ['cdn-peer', 'editor-laptop'],
    static function ($plan) use ($bytes): Response {
        return $plan->deviceId === 'cdn-peer'
            ? new Response($plan->request->id, str_repeat('stale media bytes ', 32))
            : new Response($plan->request->id, $bytes);
    },
    requestId: 8800,
);

echo json_encode([
    'media' => $file->name,
    'successful' => $result->successful(),
    'selectedDevice' => $result->plan?->deviceId,
    'attemptedDevices' => $result->attemptedDeviceIds(),
    'attemptErrors' => $result->errors,
    'bytesPulled' => strlen($result->data),
    'activityAfterPull' => [
        'cdn-peer' => $activity->usage('cdn-peer'),
        'editor-laptop' => $activity->usage('editor-laptop'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
