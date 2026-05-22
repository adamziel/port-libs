<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\RootedMemoryProvider;

$remote = new MemoryProvider();
$remote->put('wp-content/uploads/2026/05/hero.jpg', 'hero image');
$remote->put('wp-content/uploads/2026/05/thumbs/hero-150x150.jpg', 'thumbnail image');
$remote->put('wp-content/uploads/2026/06/next.jpg', 'next month image');
$remote->put('exports/site.wxr', '<rss version="2.0"></rss>');

$month = new RootedMemoryProvider($remote, 'wp-content/uploads/2026/05');
$month->put('gallery.jpg', 'gallery image');

$direct = $month->walk('', 1);
$purged = $month->purge('thumbs');

$thumbnailStillExists = true;
try {
    $remote->get('wp-content/uploads/2026/05/thumbs/hero-150x150.jpg');
} catch (RuntimeException) {
    $thumbnailStillExists = false;
}

return [
    'root' => $month->root(),
    'directObjects' => array_map(static fn ($info) => $info->path, $direct['objects']),
    'directDirectories' => array_map(static fn ($info) => $info->path, $direct['directories']),
    'purgedObjects' => array_map(static fn ($info) => $info->path, $purged['objects']),
    'thumbnailStillExists' => $thumbnailStillExists,
    'nextMonthPreserved' => $remote->get('wp-content/uploads/2026/06/next.jpg'),
    'exportPreserved' => $remote->get('exports/site.wxr'),
];
