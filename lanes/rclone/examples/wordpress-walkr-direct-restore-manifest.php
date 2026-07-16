<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;

$providerBatches = [
    [
        new ObjectInfo('site-backups/cache/object-cache.php', 7, hash('sha256', 'cache')),
        new ObjectInfo('site-backups/uploads/2026/05/hero.jpg', 15, hash('sha256', 'image bytes')),
        new ObjectInfo('site-backups/database.sql', 29, hash('sha256', 'insert into wp_posts values')),
    ],
    [
        new ObjectInfo('site-backups/cache/nested/transient.json', 12, hash('sha256', 'cache nested')),
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

$visitedDirs = [];
$entries = [];
$stats = ListDirectory::walkRecursiveTree(
    $directListR,
    true,
    'site-backups',
    -1,
    static function (string $dir, array $batch) use (&$visitedDirs, &$entries): ?string {
        $visitedDirs[] = $dir;
        if ($dir === 'site-backups/cache') {
            return ListDirectory::ERROR_SKIP_DIR;
        }

        array_push($entries, ...$batch);

        return null;
    },
);

$publishableEntries = array_values(array_filter(
    $entries,
    static fn (ObjectInfo $entry): bool => $entry->path !== 'site-backups/cache'
        && !str_starts_with($entry->path, 'site-backups/cache/'),
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
    'visitedDirs' => $visitedDirs,
    'stats' => $stats,
    'cacheSubtreeSkipped' => !in_array('site-backups/cache/nested', $visitedDirs, true)
        && !in_array('site-backups/cache/object-cache.php', $manifest, true)
        && !in_array('site-backups/cache/nested/transient.json', $manifest, true),
];
