<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

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

$plan = new SyncPlan();

$dryRunStats = null;
$dryRunRemoved = $plan->removeDirectory(
    $provider,
    'wp-content/uploads/2024/01',
    dryRun: true,
    stats: $dryRunStats,
);
$plan->removeDirectory(
    $provider,
    'wp-content/uploads/2023/12',
    dryRun: true,
    stats: $dryRunStats,
);

$directoryExists = static function (MemoryProvider $provider, string $path): bool {
    try {
        $provider->directoryInfo($path);

        return true;
    } catch (RuntimeException) {
        return false;
    }
};

$staleLeafExistsAfterDryRun = $directoryExists($provider, 'wp-content/uploads/2024/01');

$applyStats = null;
$removedAfterApply = $plan->removeDirectory(
    $provider,
    'wp-content/uploads/2024/01',
    stats: $applyStats,
);

$missingStats = null;
$missingError = null;
try {
    $plan->removeDirectory($provider, 'wp-content/uploads/2023/12', stats: $missingStats);
} catch (RuntimeException $throwable) {
    $missingError = $throwable->getMessage();
}

return [
    'dryRunRemoved' => $dryRunRemoved?->path,
    'dryRunStats' => $dryRunStats,
    'staleLeafExistsAfterDryRun' => $staleLeafExistsAfterDryRun,
    'removedAfterApply' => $removedAfterApply?->path,
    'applyStats' => $applyStats,
    'staleLeafExistsAfterApply' => $directoryExists($provider, 'wp-content/uploads/2024/01'),
    'missingError' => $missingError,
    'missingStats' => $missingStats,
    'currentUploadBytes' => $provider->get('wp-content/uploads/2026/05/hero.jpg'),
];
