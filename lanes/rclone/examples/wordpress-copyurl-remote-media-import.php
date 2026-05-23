<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$provider = new MemoryProvider();
$plan = new SyncPlan();
$downloadHeaders = [
    'User-Agent' => 'WordPress/6.9 migration',
    'X-WP-Import' => 'remote-media',
];
$requestHeaders = [];

$response = [
    'url' => 'https://legacy.example.test/download?id=42',
    'finalUrl' => 'https://cdn.example.test/assets/ignored-name.jpg',
    'headers' => [
        'Content-Disposition' => 'attachment; filename="2026/05/hero.jpg"',
        'Last-Modified' => 'Sat, 23 May 2026 13:00:00 GMT',
    ],
    'body' => 'hero image bytes',
    'onRequest' => static function (array $headers) use (&$requestHeaders): void {
        $requestHeaders = $headers;
    },
];

$command = $plan->copyUrlCommand(
    $provider,
    $response,
    'wp-content/uploads/2026/05',
    [
        'autoFilename' => true,
        'headerFilename' => true,
        'printFilename' => true,
        'downloadHeaders' => $downloadHeaders,
    ],
);

$provider->put('wp-content/uploads/2026/05/existing.jpg', 'existing image bytes');
$noClobberError = null;
try {
    $plan->copyUrl(
        $provider,
        'wp-content/uploads/2026/05/existing.jpg',
        $response + ['body' => 'replacement bytes'],
        noClobber: true,
        downloadHeaders: $downloadHeaders,
    );
} catch (RuntimeException $throwable) {
    $noClobberError = $throwable->getMessage();
}

return [
    'importedPath' => $command['object']->path,
    'importedBytes' => $provider->get($command['object']->path),
    'importedModTime' => $provider->info($command['object']->path)->modTime,
    'downloadHeaders' => $requestHeaders,
    'printedFilename' => $command['printedFilename'],
    'stats' => $command['stats'],
    'noClobberError' => $noClobberError,
    'noClobberPreservedBytes' => $provider->get('wp-content/uploads/2026/05/existing.jpg'),
];
