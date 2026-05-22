<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\RootedMemoryProvider;

$remote = new MemoryProvider();
$remote->put('exports/site.wxr', '<rss version="2.0"></rss>');
$remote->put('wp-content/uploads/2026/05/hero.jpg', 'hero image');
$remote->put('wp-content/uploads/2026/05/gallery.jpg', 'gallery image');

$uploadsMonth = new RootedMemoryProvider($remote, 'wp-content/uploads/2026/05');

$wxrLink = $remote->publicLink('exports/site.wxr', 120);
$uploadsLink = $uploadsMonth->publicLink('', 120);
$repeatWxrLink = $remote->publicLink('exports/site.wxr', 120);

$missingErrored = false;
try {
    $remote->publicLink('exports/missing.wxr', 120);
} catch (RuntimeException) {
    $missingErrored = true;
}

$unlinkResult = $remote->publicLink('exports/site.wxr', 120, true);
$relinkedWxr = $remote->publicLink('exports/site.wxr', 120);

return [
    'wxrLink' => $wxrLink,
    'uploadsLink' => $uploadsLink,
    'repeatWxrLink' => $repeatWxrLink,
    'missingErrored' => $missingErrored,
    'unlinkResult' => $unlinkResult,
    'relinkedWxr' => $relinkedWxr,
    'root' => $uploadsMonth->root(),
];
