<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ObjectInfo;
use PortLibs\Rclone\OneDriveListR;

/**
 * @return array{entries: list<ObjectInfo>, names: list<string>}
 */
function rclone_wordpress_onedrive_collect_listp(callable $listP, string $dir): array
{
    $entries = [];
    $result = $listP(
        $dir,
        static function (array $batch) use (&$entries): null {
            foreach ($batch as $entry) {
                if ($entry instanceof ObjectInfo) {
                    $entries[] = $entry;
                }
            }

            return null;
        },
    );

    if ($result instanceof Throwable) {
        throw $result;
    }

    return [
        'entries' => $entries,
        'names' => array_map(
            static fn (ObjectInfo $entry): string => $entry->path . (ListDirectory::isDirectory($entry) ? '/' : ''),
            $entries,
        ),
    ];
}

$trace = [];
$listP = OneDriveListR::listPFromChildPages(
    [
        'site-backups/shared-review' => [[
            'value' => [
                [
                    'id' => 'review-export',
                    'name' => 'review-export.wxr',
                    'size' => 18,
                    'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                    'file' => ['mimeType' => 'application/rss+xml'],
                    'metadataPermissionsError' => 'Graph permissions denied',
                ],
                [
                    'id' => 'uploads',
                    'name' => 'uploads',
                    'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                    'folder' => ['childCount' => 1],
                ],
                [
                    'id' => 'migration-notes',
                    'name' => 'migration-notes.one',
                    'size' => 2048,
                    'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                    'package' => ['type' => 'oneNote'],
                ],
            ],
        ]],
        'site-backups/shared-review/uploads' => [[
            'value' => [[
                'id' => 'hero',
                'name' => 'hero.jpg',
                'size' => 150,
                'parentReference' => ['driveId' => 'owner-drive', 'id' => 'uploads'],
                'file' => ['mimeType' => 'image/jpeg'],
            ]],
        ]],
    ],
    ['site-backups/shared-review' => 'owner-drive#shared-root'],
    trace: $trace,
);

$parent = rclone_wordpress_onedrive_collect_listp($listP, 'site-backups/shared-review');
$child = rclone_wordpress_onedrive_collect_listp($listP, 'site-backups/shared-review/uploads');

$metadataError = null;
try {
    $parent['entries'][0]->readMetadata();
} catch (Throwable $throwable) {
    $metadataError = $throwable->getMessage();
}

$manifest = array_merge($parent['names'], $child['names']);

return [
    'source' => 'listp-cache-metadata-preflight',
    'manifest' => $manifest,
    'requests' => $trace['requests'],
    'childCacheWorked' => ($trace['requests'][1]['directoryId'] ?? null) === 'owner-drive#uploads',
    'oneNoteHidden' => !in_array('site-backups/shared-review/migration-notes.one', $manifest, true),
    'metadataError' => $metadataError,
    'listedContentType' => $parent['entries'][0]->metadata['content-type'] ?? null,
];
