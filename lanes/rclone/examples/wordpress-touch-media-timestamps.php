<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$provider = new MemoryProvider();
$provider->put('wp-content/uploads/2026/05/hero.jpg', 'hero image bytes', [
    'modTime' => '2026-05-20T00:00:00Z',
]);
$provider->put('wp-content/uploads/2026/05/gallery.jpg', 'gallery image bytes', [
    'modTime' => '2026-05-20T00:00:00Z',
]);
$provider->put('exports/site.wxr', '<rss version="2.0"></rss>', [
    'modTime' => '2026-05-20T00:00:00Z',
]);
$provider->put('wp-content/cache/page.html', '<html>cache</html>', [
    'modTime' => '2026-05-20T00:00:00Z',
]);

$filter = FilterRuleSet::fromRules([
    '+ wp-content/uploads/**',
    '- *',
]);

$plan = new SyncPlan();
$options = [
    'timestamp' => '2026-05-23T14:30:00',
    'recursive' => true,
    'filter' => $filter,
];

$dryRunStats = null;
$dryRun = $plan->touchCommand(
    $provider,
    'wp-content',
    $options + ['dryRun' => true],
    $dryRunStats,
);

$applyStats = null;
$applied = $plan->touchCommand($provider, 'wp-content', $options, $applyStats);

$missingStats = null;
$missingRecursive = $plan->touchCommand($provider, 'wp-content/uploads/2024', [
    'timestamp' => '2026-05-23T14:30:00',
    'recursive' => true,
], $missingStats);

return [
    'dryRunTouched' => array_map(static fn ($info): string => $info->path, $dryRun['touched']),
    'dryRunStats' => $dryRunStats,
    'touchedUploads' => array_map(static fn ($info): string => $info->path, $applied['touched']),
    'applyStats' => $applyStats,
    'heroModTime' => $provider->info('wp-content/uploads/2026/05/hero.jpg')->modTime,
    'galleryModTime' => $provider->info('wp-content/uploads/2026/05/gallery.jpg')->modTime,
    'wxrModTime' => $provider->info('exports/site.wxr')->modTime,
    'cacheModTime' => $provider->info('wp-content/cache/page.html')->modTime,
    'missingRecursiveSkipped' => $missingRecursive['skipped'],
    'missingStats' => $missingStats,
];
