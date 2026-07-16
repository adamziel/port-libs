<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;

$providerList = static function (string $dir): array {
    return match ($dir) {
        'site-backups' => [
            new ObjectInfo('site-backups/users.wxr', 17, hash('sha256', '<rss>users</rss>')),
            ListDirectory::directory('site-backups/cache'),
            new ObjectInfo('site-backups/cache/object-cache.php', 7, hash('sha256', 'cache')),
            ListDirectory::directory('site-backups/uploads'),
            new ObjectInfo('site-backups/export.wxr', 18, hash('sha256', '<rss>site</rss>')),
            new ObjectInfo('site-backups/database.sql', 29, hash('sha256', 'insert into wp_posts values')),
            new ObjectInfo('other-site/export.wxr', 17, hash('sha256', '<rss>other</rss>')),
        ],
        'site-backups/cache' => [
            new ObjectInfo('site-backups/cache/.rclone-ignore', 1, hash('sha256', '-')),
            new ObjectInfo('site-backups/cache/object-cache.php', 7, hash('sha256', 'cache')),
        ],
        default => throw new RuntimeException("unexpected provider List call for {$dir}"),
    };
};

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

$manifestResult = ListDirectory::dirSortedResult(
    $providerList,
    false,
    'site-backups',
    static fn (ObjectInfo $entry): bool => str_ends_with($entry->path, '.wxr') || str_ends_with($entry->path, '.sql'),
    static fn (string $remote): bool => $remote !== 'site-backups/cache',
    ['.rclone-ignore'],
);

$manifest = array_map(
    static fn (ObjectInfo $entry): string => $entry->path,
    ListSorter::sorted($manifestResult['entries'], $restoreKey),
);

$cacheResult = ListDirectory::dirSortedResult(
    $providerList,
    false,
    'site-backups/cache',
    static fn (ObjectInfo $entry): bool => true,
    static fn (string $remote): bool => true,
    ['.rclone-ignore'],
);

$cacheIncludeAll = ListDirectory::dirSorted(
    $providerList,
    true,
    'site-backups/cache',
    null,
    null,
    ['.rclone-ignore'],
);

return [
    'manifest' => $manifest,
    'listed' => $manifestResult['listed'],
    'excluded' => $manifestResult['excluded'],
    'cacheManifest' => array_map(static fn (ObjectInfo $entry): string => $entry->path, $cacheResult['entries']),
    'cacheListed' => $cacheResult['listed'],
    'cacheExcluded' => $cacheResult['excluded'],
    'cacheIncludeAll' => array_map(static fn (ObjectInfo $entry): string => $entry->path, $cacheIncludeAll),
];
