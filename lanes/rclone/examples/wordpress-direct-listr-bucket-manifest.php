<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;

$providerBatches = [
    [
        new ObjectInfo('site-backups/uploads/2026/05/hero.jpg', 15, hash('sha256', 'new image bytes')),
        new ObjectInfo('site-backups/database.sql', 29, hash('sha256', 'insert into wp_posts values')),
        new ObjectInfo('site-backups/users.wxr', 17, hash('sha256', '<rss>users</rss>')),
    ],
    [
        ListDirectory::directory('site-backups/uploads/2026'),
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

$entries = [];
$batchSizes = [];
$stats = ListDirectory::listRecursiveDirect(
    $directListR,
    true,
    'site-backups',
    ListDirectory::LIST_ALL,
    static function (array $batch) use (&$entries, &$batchSizes): null {
        $batchSizes[] = count($batch);
        array_push($entries, ...$batch);

        return null;
    },
    synthesizeDirs: true,
);

$providerOrdered = array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
$manifest = array_map(
    static fn (ObjectInfo $entry): string => $entry->path . (ListDirectory::isDirectory($entry) ? '/' : ''),
    ListSorter::sorted($entries, $restoreKey),
);

return [
    'manifest' => $manifest,
    'batchSizes' => $batchSizes,
    'stats' => $stats,
    'uploadsParentSynthesized' => in_array('site-backups/uploads', $providerOrdered, true)
        && in_array('site-backups/uploads/2026/05', $providerOrdered, true),
    'providerOrderPreservedBeforePublishSort' => array_slice($providerOrdered, 0, 5) === [
        'site-backups/uploads/2026/05/hero.jpg',
        'site-backups/database.sql',
        'site-backups/users.wxr',
        'site-backups/uploads/2026',
        'site-backups/export.wxr',
    ],
];
