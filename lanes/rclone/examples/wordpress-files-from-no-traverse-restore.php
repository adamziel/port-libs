<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ObjectInfo;

$provider = new MemoryProvider();
$provider->put('site-backups/database.sql', 'insert into wp_posts values');
$provider->put('site-backups/export.wxr', '<rss>site</rss>');
$provider->put('site-backups/uploads/2026/05/hero.jpg', 'image bytes');
$provider->put('site-backups/cache/object-cache.php', 'cache bytes');

$lookups = [];
$providerListCalls = 0;
$providerListRCalls = 0;

$tree = ListDirectory::newDirTree(
    static function (string $dir) use (&$providerListCalls): array {
        $providerListCalls++;

        throw new RuntimeException("unexpected provider List traversal for {$dir}");
    },
    static function (string $dir, callable $callback) use (&$providerListRCalls): null {
        $providerListRCalls++;
        $callback([new ObjectInfo('site-backups/cache/object-cache.php', 11, hash('sha256', 'cache bytes'))]);

        return null;
    },
    true,
    'site-backups',
    -1,
    noTraverse: true,
    filesFrom: [
        'site-backups/database.sql',
        '/site-backups/uploads/2026/05/hero.jpg',
        'site-backups/export.wxr',
        'site-backups/missing.wxr',
    ],
    newObject: static function (string $remote) use ($provider, &$lookups): ObjectInfo {
        $lookups[] = $remote;

        return $provider->info($remote);
    },
);

$entries = [];
foreach ($tree['tree'] as $batch) {
    array_push($entries, ...$batch);
}

$restoreKey = static function (ObjectInfo $entry): string {
    $bucket = match (true) {
        str_ends_with($entry->path, '.sql') => '0',
        $entry->path === 'site-backups/export.wxr' => '1',
        str_contains($entry->path, '/uploads') => '2',
        default => '9',
    };

    return $bucket . ':' . $entry->path;
};

$manifest = array_map(
    static fn (ObjectInfo $entry): string => $entry->path . (ListDirectory::isDirectory($entry) ? '/' : ''),
    ListSorter::sorted($entries, $restoreKey),
);

return [
    'source' => $tree['source'],
    'manifest' => $manifest,
    'lookups' => $lookups,
    'providerListCalls' => $providerListCalls,
    'providerListRCalls' => $providerListRCalls,
    'listed' => $tree['listed'],
    'requested' => $tree['requested'],
    'missingSkipped' => !in_array('site-backups/missing.wxr', $manifest, true),
];
