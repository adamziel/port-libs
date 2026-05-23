<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ObjectInfo;
use PortLibs\Rclone\OneDriveListR;

$delta = [];
for ($i = 1; $i <= 100; $i++) {
    $name = sprintf('asset-%03d.wxr', $i);
    $delta[] = [
        'id' => sprintf('asset-%03d', $i),
        'name' => $name,
        'size' => $i,
        'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
        'file' => ['mimeType' => 'application/rss+xml'],
    ];
}

$delta[] = [
    'id' => 'shared-local',
    'name' => 'ignored-local-name',
    'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
    'remoteItem' => [
        'id' => 'shared-root',
        'name' => 'shared-review',
        'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-parent'],
        'folder' => ['childCount' => 101],
    ],
];

$sharedChildren = [];
for ($i = 1; $i <= 101; $i++) {
    $name = sprintf('review-%03d.wxr', $i);
    $sharedChildren[] = [
        'id' => sprintf('review-%03d', $i),
        'name' => $name,
        'size' => $i,
        'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
        'file' => ['mimeType' => 'application/rss+xml'],
    ];
}

$childTrace = [];
$sharedListP = OneDriveListR::listPFromChildPages(
    [
        'site-backups/shared-review' => [
            [
                'value' => $sharedChildren,
                '@odata.nextLink' => 'https://graph.example/shared-review/children?page=2',
            ],
            'https://graph.example/shared-review/children?page=2' => [
                'error' => 'Graph shared review page failed',
            ],
        ],
    ],
    ['site-backups/shared-review' => 'owner-drive#shared-root'],
    listChunk: 101,
    trace: $childTrace,
);

$listR = OneDriveListR::fromDelta(
    $delta,
    'site-drive#root',
    ['site-backups' => 'site-drive#backups'],
    ['site-backups/shared-review' => $sharedListP],
);

$manifest = [];
$batchSizes = [];
$result = $listR(
    'site-backups',
    static function (array $batch) use (&$manifest, &$batchSizes): null {
        $batchSizes[] = count($batch);
        foreach ($batch as $entry) {
            if (!$entry instanceof ObjectInfo) {
                continue;
            }
            $manifest[] = $entry->path;
        }

        return null;
    },
);

$error = $result instanceof Throwable ? $result->getMessage() : null;

return [
    'source' => 'listR-shared-listp-error',
    'childRequests' => $childTrace['requests'],
    'deliveredBatchSizes' => $batchSizes,
    'manifest' => $manifest,
    'manifestCount' => count($manifest),
    'error' => $error,
    'sharedFolderSuppressed' => !in_array('site-backups/shared-review', $manifest, true),
    'childPartialSuppressed' => !in_array('site-backups/shared-review/review-001.wxr', $manifest, true),
    'nextLinkReached' => ($childTrace['requests'][1]['rootUrl'] ?? null) === 'https://graph.example/shared-review/children?page=2',
];
