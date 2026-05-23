<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;

$providerBatches = [
    [
        new ObjectInfo('site-backups/uploads/2026/05/generated/hero-150x150.jpg', 9, hash('sha256', 'thumbnail')),
        new ObjectInfo('site-backups/cache/object-cache.php', 7, hash('sha256', 'cache')),
        new ObjectInfo('site-backups/database.sql', 29, hash('sha256', 'insert into wp_posts values')),
    ],
    [
        new ObjectInfo('site-backups/uploads/2026/05/hero.jpg', 15, hash('sha256', 'new image bytes')),
        new ObjectInfo('site-backups/cache/.rclone-ignore', 1, hash('sha256', '-')),
        new ObjectInfo('site-backups/users.wxr', 17, hash('sha256', '<rss>users</rss>')),
        new ObjectInfo('site-backups/export.wxr', 18, hash('sha256', '<rss>site</rss>')),
    ],
];

$directListR = static function (string $dir, callable $callback) use ($providerBatches): null {
    foreach ($providerBatches as $batch) {
        $selected = array_values(array_filter(
            $batch,
            static fn (ObjectInfo $entry): bool => $dir === ''
                || $entry->path === $dir
                || str_starts_with($entry->path, $dir . '/'),
        ));
        $callback($selected);
    }

    return null;
};

$result = ListDirectory::dirTreeFromListR(
    $directListR,
    false,
    'site-backups',
    5,
    static fn (ObjectInfo $entry): bool => str_ends_with($entry->path, '.wxr')
        || str_ends_with($entry->path, '.sql')
        || str_contains($entry->path, '/uploads/'),
    static fn (string $remote): bool => true,
    ['.rclone-ignore'],
);

$entries = [];
foreach ($result['tree'] as $batch) {
    array_push($entries, ...$batch);
}

$publishableEntries = array_values(array_filter(
    $entries,
    static fn (ObjectInfo $entry): bool => $entry->path !== 'site-backups/cache',
));

$restoreKey = static function (ObjectInfo $entry): string {
    $bucket = match (true) {
        str_ends_with($entry->path, '.sql') => '0',
        $entry->path === 'site-backups/export.wxr' => '1',
        str_ends_with($entry->path, '.wxr') => '2',
        str_contains($entry->path, '/uploads') => '3',
        default => '9',
    };

    return $bucket . ':' . $entry->path;
};

$manifest = array_map(
    static fn (ObjectInfo $entry): string => $entry->path . (ListDirectory::isDirectory($entry) ? '/' : ''),
    ListSorter::sorted($publishableEntries, $restoreKey),
);

return [
    'manifest' => $manifest,
    'tree' => $result['tree'],
    'treeString' => ListDirectory::formatDirTree($result['tree']),
    'treeEntryCount' => count($entries),
    'listed' => $result['listed'],
    'batches' => $result['batches'],
    'pruned' => $result['pruned'],
    'cachePruned' => !str_contains(ListDirectory::formatDirTree($result['tree']), 'cache/'),
    'generatedDirPreserved' => in_array('site-backups/uploads/2026/05/generated/', $manifest, true),
    'maxDepthStoppedBeforeGeneratedFiles' => !in_array(
        'site-backups/uploads/2026/05/generated/hero-150x150.jpg',
        $manifest,
        true,
    ),
];
