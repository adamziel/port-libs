<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;

$providerBatches = [
    [
        new ObjectInfo('site-backups/database.sql', 29, hash('sha256', 'insert into wp_posts values')),
        ListDirectory::directory('site-backups/uploads'),
        ListDirectory::directory('site-backups/uploads/2026'),
        new ObjectInfo('site-backups/uploads/2026/05/hero.jpg', 15, hash('sha256', 'new image bytes')),
    ],
    [
        ListDirectory::directory('site-backups/uploads/2026/05'),
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

$catalog = ListDirectory::getAll(
    static fn (string $dir): array => throw new RuntimeException("unexpected Walk fallback for {$dir}"),
    $directListR,
    true,
    'site-backups',
    -1,
);

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
    ListSorter::sorted(array_merge($catalog['objects'], $catalog['directories']), $restoreKey),
);

return [
    'source' => $catalog['source'],
    'objects' => array_map(static fn (ObjectInfo $entry): string => $entry->path, $catalog['objects']),
    'directories' => array_map(static fn (ObjectInfo $entry): string => $entry->path, $catalog['directories']),
    'manifest' => $manifest,
    'stats' => $catalog['stats'],
];
