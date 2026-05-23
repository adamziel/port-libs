<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ObjectInfo;

$restoreKey = static function (ObjectInfo $entry): string {
    $bucket = match (true) {
        str_ends_with($entry->path, '.sql') => '0',
        $entry->path === 'site-backups/export.wxr' => '1',
        str_ends_with($entry->path, '.wxr') => '2',
        $entry->path === 'site-backups/uploads' => '3',
        default => '9',
    };

    return $bucket . ':' . $entry->path;
};

$manifestBatches = [];
$manifestStats = ListDirectory::dirSortedFn(
    static function (callable $callback): void {
        $callback([
            new ObjectInfo('site-backups/users.wxr', 17, hash('sha256', '<rss>users</rss>')),
            ListDirectory::directory('site-backups/cache'),
            new ObjectInfo('site-backups/cache/object-cache.php', 7, hash('sha256', 'cache')),
        ]);
        $callback([
            ListDirectory::directory('site-backups/uploads'),
            new ObjectInfo('site-backups/export.wxr', 18, hash('sha256', '<rss>site</rss>')),
            new ObjectInfo('site-backups/database.sql', 29, hash('sha256', 'insert into wp_posts values')),
            new ObjectInfo('other-site/export.wxr', 17, hash('sha256', '<rss>other</rss>')),
        ]);
    },
    false,
    'site-backups',
    static function (array $entries) use (&$manifestBatches): void {
        $manifestBatches[] = array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
    },
    $restoreKey,
    static fn (ObjectInfo $entry): bool => str_ends_with($entry->path, '.wxr') || str_ends_with($entry->path, '.sql'),
    static fn (string $remote): bool => $remote !== 'site-backups/cache',
    ['.rclone-ignore'],
    cutoff: 3,
);

$cacheBatches = [];
$cacheStats = ListDirectory::dirSortedFn(
    static function (callable $callback): void {
        $callback([
            new ObjectInfo('site-backups/cache/.rclone-ignore', 1, hash('sha256', '-')),
            new ObjectInfo('site-backups/cache/object-cache.php', 7, hash('sha256', 'cache')),
        ]);
    },
    false,
    'site-backups/cache',
    static function (array $entries) use (&$cacheBatches): void {
        $cacheBatches[] = array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
    },
    null,
    static fn (ObjectInfo $entry): bool => true,
    static fn (string $remote): bool => true,
    ['.rclone-ignore'],
);

return [
    'manifest' => array_merge(...$manifestBatches),
    'batchSizes' => array_map('count', $manifestBatches),
    'manifestStats' => $manifestStats,
    'cacheManifest' => array_merge(...$cacheBatches),
    'cacheStats' => $cacheStats,
];
