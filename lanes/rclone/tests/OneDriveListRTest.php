<?php

declare(strict_types=1);

use PortLibs\Rclone\HashType;
use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ObjectInfo;
use PortLibs\Rclone\OneDriveListR;

/**
 * @return array{entries: list<ObjectInfo>, batches: list<list<string>>, stats: array<string, int>}
 */
function rclone_onedrive_listr_collect(callable $listR, string $dir = ''): array
{
    $entries = [];
    $batches = [];
    $stats = ListDirectory::listRecursiveDirect(
        $listR,
        true,
        $dir,
        ListDirectory::LIST_ALL,
        static function (array $batch) use (&$entries, &$batches): null {
            $batches[] = rclone_onedrive_listr_names($batch);
            array_push($entries, ...$batch);

            return null;
        },
    );

    return [
        'entries' => $entries,
        'batches' => $batches,
        'stats' => $stats,
    ];
}

/**
 * @param list<ObjectInfo> $entries
 * @return list<string>
 */
function rclone_onedrive_listr_names(array $entries): array
{
    return array_map(
        static fn (ObjectInfo $entry): string => $entry->path . (ListDirectory::isDirectory($entry) ? '/' : ''),
        $entries,
    );
}

return [
    'onedrive ListR skips duplicate deleted and outside-root delta items while listing shared folders' => static function (TestRunner $t): void {
        $delta = [
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
                'file' => [
                    'mimeType' => 'application/rss+xml',
                    'hashes' => ['sha1Hash' => strtoupper(hash('sha1', '<rss>site</rss>'))],
                ],
                'fileSystemInfo' => ['lastModifiedDateTime' => '2026-05-23T08:00:00Z'],
            ],
            [
                'id' => 'export',
                'name' => 'duplicate-export.wxr',
                'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
                'file' => [],
            ],
            [
                'id' => 'deleted',
                'name' => 'deleted.wxr',
                'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
                'deleted' => [],
            ],
            [
                'id' => 'outside',
                'name' => 'outside.sql',
                'parentReference' => ['driveId' => 'site-drive', 'id' => 'other-root'],
                'file' => [],
            ],
            [
                'id' => 'shared-local',
                'name' => 'ignored-local-name',
                'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
                'remoteItem' => [
                    'id' => 'shared-folder',
                    'name' => 'shared-review',
                    'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-parent'],
                    'folder' => ['childCount' => 2],
                    'createdBy' => ['user' => ['id' => 'owner-user', 'displayName' => 'Site Owner']],
                ],
                'shared' => [
                    'owner' => ['user' => ['id' => 'owner-account']],
                    'sharedBy' => ['user' => ['id' => 'reviewer-account']],
                    'scope' => 'users',
                    'sharedDateTime' => '2026-05-23T08:15:30Z',
                ],
            ],
            [
                'id' => 'database',
                'name' => 'database.sql',
                'size' => 29,
                'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
                'file' => [],
            ],
        ];

        $listR = OneDriveListR::fromDelta(
            $delta,
            'site-drive#root',
            ['site-backups' => 'site-drive#backups'],
            [
                'site-backups/shared-review' => [
                    [
                        'id' => 'users',
                        'name' => 'users.wxr',
                        'size' => 17,
                        'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-folder'],
                        'file' => ['mimeType' => 'application/rss+xml'],
                    ],
                    [
                        'id' => 'uploads',
                        'name' => 'uploads',
                        'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-folder'],
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

        $collected = rclone_onedrive_listr_collect($listR, 'site-backups');

        $t->same([[
            'site-backups/export.wxr',
            'site-backups/shared-review/',
            'site-backups/shared-review/users.wxr',
            'site-backups/shared-review/uploads/',
            'site-backups/shared-review/uploads/hero.jpg',
            'site-backups/database.sql',
        ]], $collected['batches']);
        $t->same('site-drive#export', $collected['entries'][0]->id);
        $t->same(hash('sha1', '<rss>site</rss>'), $collected['entries'][0]->hashes[HashType::SHA1]);
        $t->same('owner-drive#shared-folder', $collected['entries'][1]->id);
        $t->same('owner-account', $collected['entries'][1]->metadata['shared-owner-id']);
        $t->same('reviewer-account', $collected['entries'][1]->metadata['shared-by-id']);
        $t->same([
            'listed' => 6,
            'batches' => 1,
            'sent' => 6,
            'synthesized' => 0,
            'syntheticBatches' => 0,
        ], $collected['stats']);
    },
    'onedrive ListR relies on cached parent directories and skips children seen before parents' => static function (TestRunner $t): void {
        $listR = OneDriveListR::fromDelta([
            [
                'id' => 'early',
                'name' => 'before-parent.txt',
                'parentReference' => ['driveId' => 'drive', 'id' => 'late'],
                'file' => [],
            ],
            [
                'id' => 'late',
                'name' => 'late',
                'parentReference' => ['driveId' => 'drive', 'id' => 'root'],
                'folder' => ['childCount' => 1],
            ],
            [
                'id' => 'after',
                'name' => 'after-parent.txt',
                'parentReference' => ['driveId' => 'drive', 'id' => 'late'],
                'file' => [],
            ],
        ], 'drive#root');

        $collected = rclone_onedrive_listr_collect($listR);

        $t->same([['late/', 'late/after-parent.txt']], $collected['batches']);
        $t->same('drive#late', $collected['entries'][0]->id);
        $t->same('drive#late', $collected['entries'][1]->parentId);
    },
    'onedrive ListR hides OneNote packages unless exposure is requested' => static function (TestRunner $t): void {
        $delta = [[
            'id' => 'notes',
            'name' => 'site-notes.one',
            'size' => 2048,
            'parentReference' => ['driveId' => 'drive', 'id' => 'root'],
            'package' => ['type' => 'oneNote'],
        ]];

        $hidden = rclone_onedrive_listr_collect(OneDriveListR::fromDelta($delta, 'drive#root'));
        $shown = rclone_onedrive_listr_collect(OneDriveListR::fromDelta($delta, 'drive#root', exposeOneNoteFiles: true));

        $t->same([], $hidden['entries']);
        $t->same(['site-notes.one'], rclone_onedrive_listr_names($shown['entries']));
        $t->same('oneNote', $shown['entries'][0]->metadata['package-type']);
        $t->same('drive#notes', $shown['entries'][0]->id);
    },
    'onedrive ListR caches document library directories for later scoped calls' => static function (TestRunner $t): void {
        $listR = OneDriveListR::fromDelta([
            [
                'id' => 'backups',
                'name' => 'site-backups',
                'parentReference' => [
                    'driveId' => 'library-drive',
                    'id' => 'root',
                    'driveType' => OneDriveListR::DRIVE_TYPE_SHAREPOINT,
                ],
                'folder' => ['childCount' => 1],
            ],
            [
                'id' => 'wxr',
                'name' => 'export.wxr',
                'size' => 18,
                'parentReference' => [
                    'driveId' => 'library-drive',
                    'id' => 'backups',
                    'driveType' => OneDriveListR::DRIVE_TYPE_SHAREPOINT,
                ],
                'file' => ['mimeType' => 'application/rss+xml'],
            ],
        ], 'library-drive#root');

        $root = rclone_onedrive_listr_collect($listR);
        $scoped = rclone_onedrive_listr_collect($listR, 'site-backups');

        $t->same(['site-backups/', 'site-backups/export.wxr'], rclone_onedrive_listr_names($root['entries']));
        $t->same(['site-backups/export.wxr'], rclone_onedrive_listr_names($scoped['entries']));
        $t->same('library-drive#wxr', $scoped['entries'][0]->id);
        $t->same('library-drive#backups', $scoped['entries'][0]->parentId);
    },
    'wordpress onedrive shared ListR restore example includes shared review artifacts' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-shared-listr-restore.php';

        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/shared-review/',
            'site-backups/shared-review/users.wxr',
            'site-backups/shared-review/uploads/',
            'site-backups/shared-review/uploads/hero.jpg',
        ], $example['manifest']);
        $t->same('owner-drive#shared-review-root', $example['sharedRootId']);
        $t->same(true, $example['duplicateSkipped']);
        $t->same(true, $example['deletedSkipped']);
        $t->same(true, $example['outsideRootSkipped']);
        $t->same(true, $example['sharedFolderListedConventionally']);
        $t->same('listR', $example['source']);
    },
];
