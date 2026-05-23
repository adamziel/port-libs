<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$provider = new MemoryProvider(directPurgeError: MemoryProvider::ERROR_CANT_PURGE);
$provider->mkdir('wp-content/uploads/2026');
$provider->mkdir('wp-content/uploads/2026/05');
$provider->mkdir('wp-content/uploads/2026/05/thumbs');
$provider->put('wp-content/uploads/2026/05/hero.jpg', 'current image bytes');
$provider->put('wp-content/uploads/2026/05/thumbs/hero-150x150.jpg', 'thumbnail bytes');
$provider->put('wp-content/uploads/2026/05/thumbs/hero-300x300.jpg', 'large thumbnail bytes');
$provider->put('exports/site.wxr', '<rss version="2.0"></rss>');

$plan = new SyncPlan();
$dryRunStats = null;
$dryRun = $plan->purge(
    $provider,
    'wp-content/uploads/2026/05/thumbs',
    dryRun: true,
    stats: $dryRunStats,
);

$thumbnailStillExistsAfterDryRun = true;
try {
    $provider->get('wp-content/uploads/2026/05/thumbs/hero-150x150.jpg');
} catch (RuntimeException) {
    $thumbnailStillExistsAfterDryRun = false;
}

$applyStats = null;
$applied = $plan->purge(
    $provider,
    'wp-content/uploads/2026/05/thumbs',
    stats: $applyStats,
);

$directoryExists = static function (MemoryProvider $provider, string $path): bool {
    try {
        $provider->directoryInfo($path);

        return true;
    } catch (RuntimeException) {
        return false;
    }
};

return [
    'dryRunUsedDirectPurge' => $dryRun['usedDirectPurge'],
    'dryRunUsedFallback' => $dryRun['usedFallback'],
    'dryRunStats' => $dryRunStats,
    'thumbnailStillExistsAfterDryRun' => $thumbnailStillExistsAfterDryRun,
    'appliedUsedFallback' => $applied['usedFallback'],
    'appliedDirectError' => $applied['directError'],
    'purgedObjects' => array_map(static fn ($info): string => $info->path, $applied['objects']),
    'purgedDirectories' => array_map(static fn ($info): string => $info->path, $applied['directories']),
    'applyStats' => $applyStats,
    'thumbsDirectoryExistsAfterApply' => $directoryExists($provider, 'wp-content/uploads/2026/05/thumbs'),
    'currentUploadBytes' => $provider->get('wp-content/uploads/2026/05/hero.jpg'),
    'exportPreserved' => $provider->get('exports/site.wxr'),
];
