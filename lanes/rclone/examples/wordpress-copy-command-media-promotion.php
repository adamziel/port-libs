<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';

$local = new MemoryProvider();
$remote = new MemoryProvider();
$plan = new SyncPlan();

$local->put('wp-content/uploads/2026/05/hero.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
$local->put('wp-content/uploads/2026/05/hero-temp.jpg', 'renamed hero bytes');
$local->mkdir('site-staging/uploads/2026/05', ['metadata' => ['wp-scope' => 'uploads-month']]);
$local->mkdir('site-staging/uploads/empty-review', ['metadata' => ['wp-empty' => '1']]);
$local->put('site-staging/uploads/2026/05/hero.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
$local->put('site-staging/cache/page.html', '<html>cache</html>');
$remote->put('publish/media/hero-renamed.jpg', 'previous media bytes');

$copyStats = null;
$copy = $plan->copyCommand(
    $remote,
    $local,
    'wp-content/uploads/2026/05/hero.jpg',
    'publish/media',
    stats: $copyStats,
);

$copytoStats = null;
$copyto = $plan->copytoCommand(
    $remote,
    $local,
    'wp-content/uploads/2026/05/hero-temp.jpg',
    'publish/media/hero-renamed.jpg',
    [
        'suffix' => '.bak',
        'suffixKeepExtension' => true,
    ],
    $copytoStats,
);

$directoryStats = null;
$directory = $plan->copyCommand(
    $remote,
    $local,
    'site-staging/uploads',
    'publish/uploads',
    [
        'createEmptySrcDirs' => true,
        'filter' => FilterRuleSet::fromRules([
            '- site-staging/cache/**',
            '+ *',
        ]),
    ],
    $directoryStats,
);

return [
    'copyDestination' => $copy['destinationPath'],
    'copytoDestination' => $copyto['destinationPath'],
    'directoryCopiedPaths' => array_map(static fn ($info): string => $info->path, $directory['directory']['copied']),
    'backupPath' => $copyto['file']['backup']?->path,
    'cacheCopied' => $remote->pathExists('publish/uploads/cache/page.html'),
    'sourcePreserved' => $local->pathExists('wp-content/uploads/2026/05/hero.jpg')
        && $local->pathExists('wp-content/uploads/2026/05/hero-temp.jpg')
        && $local->pathExists('site-staging/uploads/2026/05/hero.jpg'),
    'filesCopied' => $copyStats['filesCopied'] + $copytoStats['filesCopied'] + $directoryStats['filesCopied'],
];
