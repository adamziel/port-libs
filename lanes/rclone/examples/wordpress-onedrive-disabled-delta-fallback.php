<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;

$listRAdvertised = false; // OneDrive only advertises ListR when the delta option is enabled.
$deltaListRCalls = 0;
$listCalls = [];

$providerList = static function (string $dir) use (&$listCalls): array {
    $listCalls[] = $dir;

    return match ($dir) {
        'site-backups' => [
            new ObjectInfo('site-backups/users.wxr', 17, hash('sha256', '<rss>users</rss>')),
            ListDirectory::directory('site-backups/cache'),
            ListDirectory::directory('site-backups/uploads'),
            new ObjectInfo('site-backups/export.wxr', 18, hash('sha256', '<rss>site</rss>')),
            new ObjectInfo('site-backups/database.sql', 29, hash('sha256', 'insert into wp_posts values')),
        ],
        'site-backups/cache' => [
            new ObjectInfo('site-backups/cache/.rclone-ignore', 1, hash('sha256', '-')),
            new ObjectInfo('site-backups/cache/object-cache.php', 7, hash('sha256', 'cache')),
        ],
        'site-backups/uploads' => [
            ListDirectory::directory('site-backups/uploads/2026'),
        ],
        'site-backups/uploads/2026' => [
            ListDirectory::directory('site-backups/uploads/2026/05'),
        ],
        'site-backups/uploads/2026/05' => [
            new ObjectInfo('site-backups/uploads/2026/05/hero.jpg', 15, hash('sha256', 'new image bytes')),
            ListDirectory::directory('site-backups/uploads/2026/05/generated'),
        ],
        'site-backups/uploads/2026/05/generated' => [
            new ObjectInfo('site-backups/uploads/2026/05/generated/hero-150x150.jpg', 9, hash('sha256', 'thumbnail')),
        ],
        default => throw new RuntimeException("unexpected provider List call for {$dir}"),
    };
};

$deltaListR = $listRAdvertised
    ? static function (string $dir, callable $callback) use (&$deltaListRCalls): null {
        $deltaListRCalls++;
        $callback([new ObjectInfo($dir . '/should-not-be-used.wxr', 1, hash('sha256', 'delta'))]);

        return null;
    }
    : null;

$entries = [];
$listing = ListDirectory::listRecursive(
    $providerList,
    $deltaListR,
    false,
    'site-backups',
    4,
    ListDirectory::LIST_ALL,
    static function (array $batch) use (&$entries): null {
        array_push($entries, ...$batch);

        return null;
    },
    static fn (ObjectInfo $entry): bool => str_ends_with($entry->path, '.wxr')
        || str_ends_with($entry->path, '.sql')
        || str_ends_with($entry->path, '.jpg'),
    static fn (string $remote): bool => $remote !== 'site-backups/cache'
        && $remote !== 'site-backups/uploads/2026/05/generated',
    ['.rclone-ignore'],
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
    ListSorter::sorted($entries, $restoreKey),
);

return [
    'source' => $listing['source'],
    'reason' => $listing['reason'],
    'stats' => $listing['stats'],
    'manifest' => $manifest,
    'listRAdvertised' => $listRAdvertised,
    'deltaListRCalls' => $deltaListRCalls,
    'listCalls' => $listCalls,
    'cachePruned' => !in_array('site-backups/cache/object-cache.php', $manifest, true),
    'generatedFilesBoundedOut' => !in_array('site-backups/uploads/2026/05/generated/hero-150x150.jpg', $manifest, true),
];
