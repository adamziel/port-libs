<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ObjectInfo;
use PortLibs\Rclone\SyncPlan;

$provider = new MemoryProvider();
$provider->put('wp-content/cache/page/index.html', '<html>small cached page</html>');
$provider->put('wp-content/cache/page/rendered-block-fragment.bin', str_repeat('B', 120));
$provider->put('wp-content/uploads/2026/05/hero.jpg', 'current image bytes');
$provider->put('exports/site.wxr', '<rss version="2.0"></rss>');

$filter = FilterRuleSet::fromRules([
    '+ wp-content/cache/**',
    '- *',
]);
$largeCacheOnly = static fn (ObjectInfo $info): bool => $info->size >= 100;

$plan = new SyncPlan();
$dryRunStats = null;
$dryRun = $plan->deleteContents(
    $provider,
    $filter,
    $largeCacheOnly,
    dryRun: true,
    stats: $dryRunStats,
);

$objectExists = static function (MemoryProvider $provider, string $path): bool {
    try {
        $provider->info($path);

        return true;
    } catch (RuntimeException) {
        return false;
    }
};

$largeCacheExistsAfterDryRun = $objectExists($provider, 'wp-content/cache/page/rendered-block-fragment.bin');

$applyStats = null;
$applied = $plan->deleteContents(
    $provider,
    $filter,
    $largeCacheOnly,
    stats: $applyStats,
);

return [
    'dryRunDeleted' => array_map(static fn ($info): string => $info->path, $dryRun['deleted']),
    'dryRunStats' => $dryRunStats,
    'largeCacheExistsAfterDryRun' => $largeCacheExistsAfterDryRun,
    'appliedDeleted' => array_map(static fn ($info): string => $info->path, $applied['deleted']),
    'applyStats' => $applyStats,
    'largeCacheExistsAfterApply' => $objectExists($provider, 'wp-content/cache/page/rendered-block-fragment.bin'),
    'smallCacheBytes' => $provider->get('wp-content/cache/page/index.html'),
    'currentUploadBytes' => $provider->get('wp-content/uploads/2026/05/hero.jpg'),
    'exportPreserved' => $provider->get('exports/site.wxr'),
];
