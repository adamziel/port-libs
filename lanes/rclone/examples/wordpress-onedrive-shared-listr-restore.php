<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;
use PortLibs\Rclone\OneDriveListR;

$deltaItems = [
    [
        'id' => 'backups',
        'name' => 'site-backups',
        'parentReference' => ['driveId' => 'site-drive', 'id' => 'root'],
        'folder' => ['childCount' => 4],
    ],
    [
        'id' => 'export',
        'name' => 'export.wxr',
        'size' => 18,
        'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
        'file' => ['mimeType' => 'application/rss+xml'],
    ],
    [
        'id' => 'export',
        'name' => 'interrupted-export-copy.wxr',
        'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
        'file' => ['mimeType' => 'application/rss+xml'],
    ],
    [
        'id' => 'deleted-cache',
        'name' => 'object-cache.php',
        'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
        'deleted' => [],
    ],
    [
        'id' => 'other-site',
        'name' => 'other-site.sql',
        'parentReference' => ['driveId' => 'site-drive', 'id' => 'other-root'],
        'file' => ['mimeType' => 'application/sql'],
    ],
    [
        'id' => 'shared-local',
        'name' => 'Shared Review',
        'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
        'remoteItem' => [
            'id' => 'shared-review-root',
            'name' => 'shared-review',
            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-parent'],
            'folder' => ['childCount' => 2],
        ],
        'shared' => [
            'owner' => ['user' => ['id' => 'site-owner']],
            'sharedBy' => ['user' => ['id' => 'reviewer']],
            'scope' => 'users',
            'sharedDateTime' => '2026-05-23T08:15:30Z',
        ],
    ],
    [
        'id' => 'database',
        'name' => 'database.sql',
        'size' => 29,
        'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
        'file' => ['mimeType' => 'application/sql'],
    ],
];

$listR = OneDriveListR::fromDelta(
    $deltaItems,
    'site-drive#root',
    ['site-backups' => 'site-drive#backups'],
    [
        'site-backups/shared-review' => [
            [
                'id' => 'users',
                'name' => 'users.wxr',
                'size' => 17,
                'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-review-root'],
                'file' => ['mimeType' => 'application/rss+xml'],
            ],
            [
                'id' => 'uploads',
                'name' => 'uploads',
                'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-review-root'],
                'folder' => ['childCount' => 1],
            ],
        ],
        'site-backups/shared-review/uploads' => [
            [
                'id' => 'hero',
                'name' => 'hero.jpg',
                'size' => 15,
                'parentReference' => ['driveId' => 'owner-drive', 'id' => 'uploads'],
                'file' => ['mimeType' => 'image/jpeg'],
            ],
        ],
    ],
);

$rawEntries = [];
ListDirectory::listRecursiveDirect(
    $listR,
    true,
    'site-backups',
    ListDirectory::LIST_ALL,
    static function (array $batch) use (&$rawEntries): null {
        array_push($rawEntries, ...$batch);

        return null;
    },
);

$result = ListDirectory::dirTreeFromListR(
    $listR,
    true,
    'site-backups',
    -1,
);

$entries = [];
foreach ($result['tree'] as $batch) {
    array_push($entries, ...$batch);
}

$restoreKey = static function (ObjectInfo $entry): string {
    $bucket = match (true) {
        str_ends_with($entry->path, '.sql') => '0',
        $entry->path === 'site-backups/export.wxr' => '1',
        ListDirectory::isDirectory($entry) && $entry->path === 'site-backups/shared-review' => '2',
        str_ends_with($entry->path, '.wxr') => '3',
        str_contains($entry->path, '/uploads') => '4',
        default => '5',
    };

    return $bucket . ':' . $entry->path;
};

$manifest = array_map(
    static fn (ObjectInfo $entry): string => $entry->path . (ListDirectory::isDirectory($entry) ? '/' : ''),
    ListSorter::sorted($entries, $restoreKey),
);

$sharedRoot = null;
foreach ($rawEntries as $entry) {
    if ($entry->path === 'site-backups/shared-review') {
        $sharedRoot = $entry;
        break;
    }
}

return [
    'source' => 'listR',
    'manifest' => $manifest,
    'tree' => $result['tree'],
    'listed' => $result['listed'],
    'batches' => $result['batches'],
    'sharedRootId' => $sharedRoot?->id,
    'sharedRootMetadata' => $sharedRoot?->metadata ?? [],
    'duplicateSkipped' => !in_array('site-backups/interrupted-export-copy.wxr', $manifest, true),
    'deletedSkipped' => !in_array('site-backups/object-cache.php', $manifest, true),
    'outsideRootSkipped' => !in_array('site-backups/other-site.sql', $manifest, true),
    'sharedFolderListedConventionally' => in_array('site-backups/shared-review/uploads/hero.jpg', $manifest, true),
];
