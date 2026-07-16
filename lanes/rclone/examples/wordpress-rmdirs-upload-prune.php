<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$provider = new MemoryProvider();
$provider->mkdir('wp-content');
$provider->mkdir('wp-content/uploads');
$provider->mkdir('wp-content/uploads/2024');
$provider->mkdir('wp-content/uploads/2024/01');
$provider->mkdir('wp-content/uploads/2026');
$provider->mkdir('wp-content/uploads/2026/05');
$provider->put('wp-content/uploads/2026/05/hero.jpg', 'current image bytes');
$provider->mkdir('wp-content/cache');
$provider->put('wp-content/cache/index.html', '<html>cache</html>');

$filter = FilterRuleSet::fromRules([
    '+ /wp-content/uploads/**',
    '- *',
]);

$removed = (new SyncPlan())->removeEmptyDirectories(
    $provider,
    'wp-content/uploads',
    leaveRoot: true,
    filter: $filter,
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
    'prunedDirectories' => array_map(static fn ($info): string => $info->path, $removed),
    'uploadRootExists' => $directoryExists($provider, 'wp-content/uploads'),
    'currentMonthExists' => $directoryExists($provider, 'wp-content/uploads/2026/05'),
    'staleMonthExists' => $directoryExists($provider, 'wp-content/uploads/2024'),
    'currentUploadBytes' => $provider->get('wp-content/uploads/2026/05/hero.jpg'),
    'cacheLeftUntouched' => $provider->get('wp-content/cache/index.html'),
];
