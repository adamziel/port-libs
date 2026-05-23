<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;
use PortLibs\Rclone\OneDriveListR;

$backupParent = ['driveId' => 'site-drive', 'id' => 'backups'];
$uploadsParent = ['driveId' => 'site-drive', 'id' => 'uploads'];

$pageOne = [
    [
        'id' => 'database',
        'name' => 'database.sql',
        'size' => 29,
        'parentReference' => $backupParent,
        'file' => ['mimeType' => 'application/sql'],
    ],
    [
        'id' => 'export',
        'name' => 'export.wxr',
        'size' => 18,
        'parentReference' => $backupParent,
        'file' => ['mimeType' => 'application/rss+xml'],
    ],
    [
        'id' => 'users',
        'name' => 'users.wxr',
        'size' => 17,
        'parentReference' => $backupParent,
        'file' => ['mimeType' => 'application/rss+xml'],
    ],
    [
        'id' => 'uploads',
        'name' => 'uploads',
        'parentReference' => $backupParent,
        'folder' => ['childCount' => 101],
    ],
];

for ($i = 1; $i <= 71; $i++) {
    $pageOne[] = [
        'id' => sprintf('image-%03d', $i),
        'name' => sprintf('image-%03d.jpg', $i),
        'size' => 1024 + $i,
        'parentReference' => $uploadsParent,
        'file' => ['mimeType' => 'image/jpeg'],
    ];
}

$pageTwo = [];
for ($i = 72; $i <= 101; $i++) {
    $pageTwo[] = [
        'id' => sprintf('image-%03d', $i),
        'name' => sprintf('image-%03d.jpg', $i),
        'size' => 1024 + $i,
        'parentReference' => $uploadsParent,
        'file' => ['mimeType' => 'image/jpeg'],
    ];
}

$trace = [];
$listR = OneDriveListR::fromDeltaPages(
    [
        [
            'value' => $pageOne,
            '@odata.nextLink' => 'https://graph.example/site-backups/delta?page=2',
        ],
        'https://graph.example/site-backups/delta?page=2' => [
            'value' => $pageTwo,
            '@odata.deltaLink' => 'https://graph.example/site-backups/delta?token=after-page-two',
        ],
    ],
    'site-drive#root',
    ['site-backups' => 'site-drive#backups'],
    listChunk: 75,
    trace: $trace,
);

$entries = [];
$batchSizes = [];
ListDirectory::listRecursiveDirect(
    $listR,
    true,
    'site-backups',
    ListDirectory::LIST_ALL,
    static function (array $batch) use (&$entries, &$batchSizes): null {
        $batchSizes[] = count($batch);
        array_push($entries, ...$batch);

        return null;
    },
);

$restoreKey = static function (ObjectInfo $entry): string {
    $bucket = match (true) {
        $entry->path === 'site-backups/database.sql' => '0',
        $entry->path === 'site-backups/export.wxr' => '1',
        $entry->path === 'site-backups/users.wxr' => '2',
        ListDirectory::isDirectory($entry) => '3',
        str_contains($entry->path, '/uploads/') => '4',
        default => '9',
    };

    return $bucket . ':' . $entry->path;
};

$manifest = array_map(
    static fn (ObjectInfo $entry): string => $entry->path . (ListDirectory::isDirectory($entry) ? '/' : ''),
    ListSorter::sorted($entries, $restoreKey),
);

return [
    'source' => 'listR-delta-pages',
    'requests' => $trace['requests'],
    'batchSizes' => $batchSizes,
    'manifest' => $manifest,
    'manifestCount' => count($manifest),
    'nextLinkFollowed' => ($trace['requests'][1]['rootUrl'] ?? null) === 'https://graph.example/site-backups/delta?page=2',
];
