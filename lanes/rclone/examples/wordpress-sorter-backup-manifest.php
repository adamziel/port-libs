<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;

$entries = [
    new ObjectInfo('wp-content/uploads/2026/05/hero.jpg', 14, hash('sha256', 'hero image')),
    ListDirectory::directory('wp-content/uploads/2026/05/thumbs'),
    new ObjectInfo('exports/site-users.wxr', 34, hash('sha256', '<rss>users</rss>')),
    new ObjectInfo('database/site.sql', 29, hash('sha256', 'insert into wp_posts values')),
    new ObjectInfo('wp-content/uploads/2026/05/gallery.jpg', 17, hash('sha256', 'gallery image')),
    new ObjectInfo('exports/site.wxr', 18, hash('sha256', '<rss>site</rss>')),
];

$restoreOrderKey = static function (ObjectInfo $entry): string {
    $bucket = match (true) {
        str_ends_with($entry->path, '.sql') => '0',
        $entry->path === 'exports/site.wxr' => '1',
        str_ends_with($entry->path, '.wxr') => '2',
        str_starts_with($entry->path, 'wp-content/uploads/') => '3',
        default => '9',
    };

    return $bucket . ':' . $entry->path;
};

$batches = [];
$sorter = new ListSorter(static function (array $entries) use (&$batches): void {
    $batches[] = array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
}, $restoreOrderKey, cutoff: 4);

$sorter->add(array_slice($entries, 0, 3));
$sorter->add(array_slice($entries, 3));
$usedCutoffMode = $sorter->usesExternalSort();
$sorter->send();
$sorter->cleanUp();

$sortedManifest = array_merge(...$batches);

return [
    'sortedManifest' => $sortedManifest,
    'usedCutoffMode' => $usedCutoffMode,
    'batchSizes' => array_map('count', $batches),
    'restoreFirst' => $sortedManifest[0],
];
