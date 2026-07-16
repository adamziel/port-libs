<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ObjectInfo;

$entries = [
    ListDirectory::directory('site-backups'),
    ListDirectory::directory('site-backups/uploads'),
    ListDirectory::directory('site-backups/cache'),
    new ObjectInfo('site-backups/export.wxr', 21, hash('sha256', '<rss>export</rss>')),
    new ObjectInfo('site-backups/database.sql', 28, hash('sha256', 'insert into wp_posts values')),
    new ObjectInfo('site-backups/debug.log', 9, hash('sha256', 'debug log')),
    new ObjectInfo('site-backups/cache/object-cache.php', 7, hash('sha256', 'cache')),
    new ObjectInfo('other-site/export.wxr', 17, hash('sha256', '<rss>other</rss>')),
];

$directEntries = ListDirectory::filterAndSortDir(
    $entries,
    false,
    'site-backups',
    static fn (ObjectInfo $entry): bool => str_ends_with($entry->path, '.wxr') || str_ends_with($entry->path, '.sql'),
    static fn (string $remote): bool => $remote !== 'site-backups/cache',
);

$paths = array_map(static fn (ObjectInfo $entry): string => $entry->path, $directEntries);

return [
    'directEntries' => $paths,
    'cachePruned' => !in_array('site-backups/cache', $paths, true),
    'nestedLeakIgnored' => !in_array('site-backups/cache/object-cache.php', $paths, true),
    'entryCount' => count($paths),
];
