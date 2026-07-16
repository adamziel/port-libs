<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$provider = new MemoryProvider(cleanUp: true);
$provider->put('exports/site.wxr', '<rss version="2.0"></rss>');
$provider->put('wp-content/uploads/2026/05/hero.jpg', 'current image bytes');
$provider->putTrashedObject('exports/site.wxr#version-2026-05-01', '<rss>old version</rss>');
$provider->putTrashedObject('wp-content/uploads/2024/01/retired.jpg', 'retired image bytes');
$provider->mkdirTrashedDirectory('wp-content/uploads/2024/01');
$provider->mkdirTrashedDirectory('wp-content/uploads/2024');

$plan = new SyncPlan();

$dryRunStats = null;
$dryRun = $plan->cleanUp($provider, dryRun: true, stats: $dryRunStats);
$trashObjectsAfterDryRun = array_map(static fn ($info): string => $info->path, $provider->trashedObjects());

$applyStats = null;
$applied = $plan->cleanUp($provider, stats: $applyStats);

return [
    'dryRunProviderCalled' => $dryRun['providerCalled'],
    'dryRunStats' => $dryRunStats,
    'trashObjectsAfterDryRun' => $trashObjectsAfterDryRun,
    'cleanedObjects' => array_map(static fn ($info): string => $info->path, $applied['objects']),
    'cleanedDirectories' => array_map(static fn ($info): string => $info->path, $applied['directories']),
    'applyStats' => $applyStats,
    'trashObjectsAfterApply' => array_map(static fn ($info): string => $info->path, $provider->trashedObjects()),
    'trashDirectoriesAfterApply' => array_map(static fn ($info): string => $info->path, $provider->trashedDirectories()),
    'currentUploadBytes' => $provider->get('wp-content/uploads/2026/05/hero.jpg'),
    'currentExportBytes' => $provider->get('exports/site.wxr'),
];
