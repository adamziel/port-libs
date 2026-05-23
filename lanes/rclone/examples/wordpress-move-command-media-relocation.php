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
$local->put('site-staging/uploads/2026/05/hero.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
$local->put('site-staging/cache/page.html', '<html>cache</html>');
$remote->put('archive/media/hero-renamed.jpg', 'previous media bytes');

$moveStats = null;
$move = $plan->moveCommand(
    $remote,
    $local,
    'wp-content/uploads/2026/05/hero.jpg',
    'archive/media',
    stats: $moveStats,
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
    ],
    $movetoStats,
);

$directoryStats = null;
$directory = $plan->moveCommand(
    $remote,
    $local,
    'site-staging/uploads',
    'archive/uploads',
    [
        'filter' => FilterRuleSet::fromRules([
            '- site-staging/cache/**',
            '+ *',
        ]),
    ],
    $directoryStats,
);

return [
    'moveDestination' => $move['destinationPath'],
    'movetoDestination' => $moveto['destinationPath'],
    'directoryMovedPaths' => array_map(static fn ($info): string => $info->path, $directory['directory']['moved']),
    'backupPath' => $moveto['file']['backup']?->path,
    'cacheMoved' => $remote->pathExists('archive/uploads/cache/page.html'),
    'filesMoved' => $moveStats['filesMoved'] + $movetoStats['filesMoved'] + $directoryStats['filesMoved'],
    'sourceDeletes' => $moveStats['filesDeletedFromSource'] + $movetoStats['filesDeletedFromSource'] + $directoryStats['filesDeletedFromSource'],
];
