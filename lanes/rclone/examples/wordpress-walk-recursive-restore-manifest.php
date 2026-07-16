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
            new ObjectInfo('site-backups/uploads/2026/05/hero.webp', 20, hash('sha256', 'generated webp bytes')),
            ListDirectory::directory('site-backups/uploads/2026/05/generated'),
        ],
        'site-backups/uploads/2026/05/generated' => [
            new ObjectInfo('site-backups/uploads/2026/05/generated/hero-150x150.jpg', 9, hash('sha256', 'thumbnail')),
        ],
        default => throw new RuntimeException("unexpected provider List call for {$dir}"),
    };
};

$restoreKey = static function (ObjectInfo $entry): string {
    $bucket = match (true) {
        str_ends_with($entry->path, '.sql') => '0',
        $entry->path === 'site-backups/export.wxr' => '1',
        str_ends_with($entry->path, '.wxr') => '2',
        str_contains($entry->path, '/uploads/') || str_ends_with($entry->path, '/uploads') => '3',
        default => '9',
    };

    return $bucket . ':' . $entry->path;
};

$entries = [];
$stats = ListDirectory::listRecursiveFallback(
    $providerList,
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
        || str_contains($entry->path, '/uploads/'),
    static fn (string $remote): bool => true,
    ['.rclone-ignore'],
);

$publishableEntries = array_values(array_filter(
    $entries,
    static fn (ObjectInfo $entry): bool => $entry->path !== 'site-backups/cache',
));

$manifest = array_map(
    static fn (ObjectInfo $entry): string => $entry->path . (ListDirectory::isDirectory($entry) ? '/' : ''),
    ListSorter::sorted($publishableEntries, $restoreKey),
);

return [
    'manifest' => $manifest,
    'stats' => $stats,
    'cachePruned' => !in_array('site-backups/cache/object-cache.php', $manifest, true),
    'maxDepthStoppedBeforeGeneratedFiles' => !in_array('site-backups/uploads/2026/05/generated/hero-150x150.jpg', $manifest, true),
];
