<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$local = new MemoryProvider();
$remote = new MemoryProvider();
$plan = new SyncPlan();

$local->put('wp-content/uploads/2026/05/hero.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
$local->put('wp-content/uploads/2026/05/hero-temp.jpg', 'renamed hero bytes');
$local->mkdir('site-staging/uploads/empty-review', ['metadata' => ['wp-empty' => '1']]);
$local->put('site-staging/uploads/hero.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
$remote->put('publish/media/hero-renamed.jpg', 'previous publish bytes');
$remote->put('archive/media/hero-renamed.jpg', 'previous archive bytes');

$copytoStats = null;
$copyto = $plan->copytoCommand(
    $remote,
    $local,
    'wp-content/uploads/2026/05/hero-temp.jpg',
    'publish/media/hero-renamed.jpg',
    [
        'suffix' => '.bak',
        'suffixKeepExtension' => true,
        'dryRun' => true,
    ],
    $copytoStats,
);

$movetoStats = null;
$moveto = $plan->movetoCommand(
    $remote,
    $local,
    'wp-content/uploads/2026/05/hero-temp.jpg',
    'archive/media/hero-renamed.jpg',
    [
        'suffix' => '.bak',
        'suffixKeepExtension' => true,
        'dryRun' => true,
    ],
    $movetoStats,
);

$directoryStats = null;
$directory = $plan->copyCommand(
    $remote,
    $local,
    'site-staging/uploads',
    'publish/uploads',
    [
        'createEmptySrcDirs' => true,
        'dryRun' => true,
    ],
    $directoryStats,
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
    'copytoDryRunActions' => $copyto['file']['dryRunActions'],
    'movetoDryRunActions' => $moveto['file']['dryRunActions'],
    'directoryCopyDryRunActions' => $directory['directory']['fileResults'][0]['dryRunActions'],
    'remotePublishPreserved' => $remote->get('publish/media/hero-renamed.jpg') === 'previous publish bytes',
    'remoteArchivePreserved' => $remote->get('archive/media/hero-renamed.jpg') === 'previous archive bytes',
    'sourcePreserved' => $local->pathExists('wp-content/uploads/2026/05/hero-temp.jpg')
        && $local->pathExists('site-staging/uploads/hero.jpg')
        && $directoryExists($local, 'site-staging/uploads/empty-review'),
    'dryRunCreatedBackup' => $remote->pathExists('publish/media/hero-renamed.bak.jpg')
        || $remote->pathExists('archive/media/hero-renamed.bak.jpg'),
    'dryRunSkipped' => $copytoStats['dryRunSkipped'] + $movetoStats['dryRunSkipped'] + $directoryStats['dryRunSkipped'],
];
