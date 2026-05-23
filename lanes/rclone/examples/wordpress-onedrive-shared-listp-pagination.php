<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;
use PortLibs\Rclone\OneDriveListR;

$sharedRoot = ['driveId' => 'owner-drive', 'id' => 'shared-root'];
$uploadsRoot = ['driveId' => 'owner-drive', 'id' => 'uploads'];

$childTrace = [];
$sharedListP = OneDriveListR::listPFromChildPages(
    [
        'site-backups/shared-review' => [
            [
                'value' => [
                    [
                        'id' => 'database',
                        'name' => 'database.sql',
                        'size' => 29,
                        'parentReference' => $sharedRoot,
                        'file' => ['mimeType' => 'application/sql'],
                    ],
                    [
                        'id' => 'export',
                        'name' => 'export.wxr',
                        'size' => 18,
                        'parentReference' => $sharedRoot,
                        'file' => ['mimeType' => 'application/rss+xml'],
                    ],
                    [
                        'id' => 'uploads',
                        'name' => 'uploads',
                        'parentReference' => $sharedRoot,
                        'folder' => ['childCount' => 1],
                    ],
                    [
                        'id' => 'deleted-cache',
                        'name' => 'deleted-cache.html',
                        'parentReference' => $sharedRoot,
                        'deleted' => [],
                        'file' => ['mimeType' => 'text/html'],
                    ],
                ],
                '@odata.nextLink' => 'https://graph.example/shared-review/children?page=2',
            ],
            'https://graph.example/shared-review/children?page=2' => [
                'value' => [[
                    'id' => 'users',
                    'name' => 'users.wxr',
                    'size' => 17,
                    'parentReference' => $sharedRoot,
                    'file' => ['mimeType' => 'application/rss+xml'],
                ]],
            ],
        ],
        'site-backups/shared-review/uploads' => [[
            'value' => [[
                'id' => 'hero',
                'name' => 'hero.jpg',
                'size' => 150,
                'parentReference' => $uploadsRoot,
                'file' => ['mimeType' => 'image/jpeg'],
            ]],
        ]],
    ],
    [
        'site-backups/shared-review' => 'owner-drive#shared-root',
        'site-backups/shared-review/uploads' => 'owner-drive#uploads',
    ],
    listChunk: 4,
    trace: $childTrace,
);

$listR = OneDriveListR::fromDelta(
    [[
        'id' => 'shared-local',
        'name' => 'ignored-local-name',
        'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
        'remoteItem' => [
            'id' => 'shared-root',
            'name' => 'shared-review',
            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-parent'],
            'folder' => ['childCount' => 4],
        ],
        'shared' => [
            'owner' => ['user' => ['id' => 'owner-account']],
            'sharedBy' => ['user' => ['id' => 'reviewer-account']],
            'scope' => 'users',
        ],
    ]],
    'site-drive#root',
    ['site-backups' => 'site-drive#backups'],
    ['site-backups/shared-review' => $sharedListP],
);

$entries = [];
ListDirectory::listRecursiveDirect(
    $listR,
    true,
    'site-backups',
    ListDirectory::LIST_ALL,
    static function (array $batch) use (&$entries): null {
        array_push($entries, ...$batch);

        return null;
    },
);

$restoreKey = static function (ObjectInfo $entry): string {
    $bucket = match ($entry->path) {
        'site-backups/shared-review/database.sql' => '0',
        'site-backups/shared-review/export.wxr' => '1',
        'site-backups/shared-review/users.wxr' => '2',
        default => ListDirectory::isDirectory($entry) ? '3' : '4',
    };

    return $bucket . ':' . $entry->path;
};

$manifest = array_map(
    static fn (ObjectInfo $entry): string => $entry->path . (ListDirectory::isDirectory($entry) ? '/' : ''),
    ListSorter::sorted($entries, $restoreKey),
);

return [
    'source' => 'listR-shared-listp-pages',
    'childRequests' => $childTrace['requests'],
    'manifest' => $manifest,
    'manifestCount' => count($manifest),
    'nextLinkFollowed' => ($childTrace['requests'][1]['rootUrl'] ?? null) === 'https://graph.example/shared-review/children?page=2',
    'sharedListPUsed' => count($childTrace['requests']) === 3,
    'deletedSkipped' => !in_array('site-backups/shared-review/deleted-cache.html', $manifest, true),
];
