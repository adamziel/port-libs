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

/**
 * @return array{entries: list<ObjectInfo>, batches: list<list<string>>}
 */
function rclone_onedrive_listr_collect_listp(
    callable $listP,
    string $dir,
    bool $directoriesOnly = false,
    bool $filesOnly = false,
): array {
    $entries = [];
    $batches = [];
    $result = $listP(
        $dir,
        static function (array $batch) use (&$entries, &$batches): null {
            $batches[] = rclone_onedrive_listr_names($batch);
            array_push($entries, ...$batch);

            return null;
        },
        $directoriesOnly,
        $filesOnly,
    );

    if ($result instanceof Throwable) {
        throw $result;
    }
    if ($result !== null) {
        throw new InvalidArgumentException('ListP callable must return null or Throwable');
    }

    return [
        'entries' => $entries,
        'batches' => $batches,
    ];
}

/**
 * @return array<string, mixed>
 */
function rclone_onedrive_listr_delta_file(int $number, string $parentId = 'root'): array
{
    $name = sprintf('asset-%03d.wxr', $number);

    return [
        'id' => sprintf('asset-%03d', $number),
        'name' => $name,
        'size' => $number,
        'parentReference' => ['driveId' => 'drive', 'id' => $parentId],
        'file' => ['mimeType' => 'application/rss+xml'],
    ];
}

/**
 * @return array<string, mixed>
 */
function rclone_onedrive_listr_child_file(int $number, string $parentId = 'shared-root', string $driveId = 'owner-drive'): array
{
    $name = sprintf('asset-%03d.wxr', $number);

    return [
        'id' => sprintf('asset-%03d', $number),
        'name' => $name,
        'size' => $number,
        'parentReference' => ['driveId' => $driveId, 'id' => $parentId],
        'file' => ['mimeType' => 'application/rss+xml'],
    ];
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
    'onedrive paged delta ListR follows nextLink and batches across pages' => static function (TestRunner $t): void {
        $firstPage = [];
        for ($i = 1; $i <= 75; $i++) {
            $firstPage[] = rclone_onedrive_listr_delta_file($i);
        }

        $secondPage = [];
        for ($i = 76; $i <= 101; $i++) {
            $secondPage[] = rclone_onedrive_listr_delta_file($i);
        }

        $trace = [];
        $listR = OneDriveListR::fromDeltaPages(
            [
                ['value' => $firstPage, '@odata.nextLink' => 'https://graph.example/delta?page=2'],
                'https://graph.example/delta?page=2' => ['value' => $secondPage],
            ],
            'drive#root',
            listChunk: 75,
            trace: $trace,
        );

        $collected = rclone_onedrive_listr_collect($listR);

        $t->same([100, 1], array_map('count', $collected['batches']));
        $t->same('asset-001.wxr', $collected['batches'][0][0]);
        $t->same('asset-100.wxr', $collected['batches'][0][99]);
        $t->same('asset-101.wxr', $collected['batches'][1][0]);
        $t->same([
            [
                'rootUrl' => null,
                'path' => '/root/delta',
                'parameters' => ['$top' => ['75']],
            ],
            [
                'rootUrl' => 'https://graph.example/delta?page=2',
                'path' => '',
                'parameters' => [],
            ],
        ], $trace['requests']);
        $t->same(['listed' => 101, 'batches' => 2, 'sent' => 101, 'synthesized' => 0, 'syntheticBatches' => 0], $collected['stats']);
    },
    'onedrive paged delta ListR returns provider page errors before final flush' => static function (TestRunner $t): void {
        $firstPage = [];
        for ($i = 1; $i <= 99; $i++) {
            $firstPage[] = rclone_onedrive_listr_delta_file($i);
        }

        $trace = [];
        $listR = OneDriveListR::fromDeltaPages(
            [
                ['value' => $firstPage, '@odata.nextLink' => 'https://graph.example/delta?page=2'],
                'https://graph.example/delta?page=2' => ['error' => 'Graph delta page failed'],
            ],
            'drive#root',
            listChunk: 99,
            trace: $trace,
        );

        $batches = [];
        $message = null;
        try {
            ListDirectory::listRecursiveDirect(
                $listR,
                true,
                '',
                ListDirectory::LIST_ALL,
                static function (array $batch) use (&$batches): null {
                    $batches[] = rclone_onedrive_listr_names($batch);

                    return null;
                },
            );
        } catch (RuntimeException $throwable) {
            $message = $throwable->getMessage();
        }

        $t->same("couldn't list files: Graph delta page failed", $message);
        $t->same([], $batches);
        $t->same([
            [
                'rootUrl' => null,
                'path' => '/root/delta',
                'parameters' => ['$top' => ['99']],
            ],
            [
                'rootUrl' => 'https://graph.example/delta?page=2',
                'path' => '',
                'parameters' => [],
            ],
        ], $trace['requests']);
    },
    'onedrive child ListP follows nextLink and applies directory file filters' => static function (TestRunner $t): void {
        $childPages = [
            'site-backups/shared-review' => [
                [
                    'value' => [
                        [
                            'id' => 'users',
                            'name' => 'users.wxr',
                            'size' => 17,
                            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                            'file' => ['mimeType' => 'application/rss+xml'],
                        ],
                        [
                            'id' => 'uploads',
                            'name' => 'uploads',
                            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                            'folder' => ['childCount' => 1],
                        ],
                        [
                            'id' => 'deleted-cache',
                            'name' => 'deleted-cache.html',
                            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                            'deleted' => [],
                            'file' => [],
                        ],
                    ],
                    '@odata.nextLink' => 'https://graph.example/shared-review/children?page=2',
                ],
                'https://graph.example/shared-review/children?page=2' => [
                    'value' => [
                        [
                            'id' => 'hero',
                            'name' => 'hero.jpg',
                            'size' => 150,
                            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                            'file' => ['mimeType' => 'image/jpeg'],
                        ],
                        [
                            'id' => 'cache',
                            'name' => 'cache',
                            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                            'folder' => ['childCount' => 0],
                        ],
                    ],
                ],
            ],
        ];
        $directoryIds = ['site-backups/shared-review' => 'owner-drive#shared-root'];

        $trace = [];
        $listP = OneDriveListR::listPFromChildPages($childPages, $directoryIds, listChunk: 3, trace: $trace);
        $all = rclone_onedrive_listr_collect_listp($listP, 'site-backups/shared-review');

        $t->same([[
            'site-backups/shared-review/users.wxr',
            'site-backups/shared-review/uploads/',
            'site-backups/shared-review/hero.jpg',
            'site-backups/shared-review/cache/',
        ]], $all['batches']);
        $t->same([
            [
                'rootUrl' => null,
                'path' => '/children',
                'parameters' => ['$top' => ['3']],
                'directoryId' => 'owner-drive#shared-root',
            ],
            [
                'rootUrl' => 'https://graph.example/shared-review/children?page=2',
                'path' => '',
                'parameters' => [],
                'directoryId' => 'owner-drive#shared-root',
            ],
        ], $trace['requests']);

        $filteredListP = OneDriveListR::listPFromChildPages($childPages, $directoryIds, listChunk: 3);
        $directories = rclone_onedrive_listr_collect_listp($filteredListP, 'site-backups/shared-review', directoriesOnly: true);
        $files = rclone_onedrive_listr_collect_listp($filteredListP, 'site-backups/shared-review', filesOnly: true);

        $t->same(['site-backups/shared-review/uploads/', 'site-backups/shared-review/cache/'], rclone_onedrive_listr_names($directories['entries']));
        $t->same(['site-backups/shared-review/users.wxr', 'site-backups/shared-review/hero.jpg'], rclone_onedrive_listr_names($files['entries']));
    },
    'onedrive child listAll stops before nextLink when a page is empty' => static function (TestRunner $t): void {
        $trace = [];
        $listP = OneDriveListR::listPFromChildPages(
            [
                'site-backups/shared-review' => [
                    [
                        'value' => [],
                        '@odata.nextLink' => 'https://graph.example/shared-review/children?page=2',
                    ],
                    'https://graph.example/shared-review/children?page=2' => [
                        'value' => [[
                            'id' => 'late',
                            'name' => 'late.wxr',
                            'size' => 10,
                            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                            'file' => [],
                        ]],
                    ],
                ],
            ],
            ['site-backups/shared-review' => 'owner-drive#shared-root'],
            listChunk: 1,
            trace: $trace,
        );

        $collected = rclone_onedrive_listr_collect_listp($listP, 'site-backups/shared-review');

        $t->same([], $collected['entries']);
        $t->same([0], $trace['pages']);
        $t->same([
            [
                'rootUrl' => null,
                'path' => '/children',
                'parameters' => ['$top' => ['1']],
                'directoryId' => 'owner-drive#shared-root',
            ],
        ], $trace['requests']);
    },
    'onedrive child ListP caches directory IDs for later scoped calls' => static function (TestRunner $t): void {
        $trace = [];
        $listP = OneDriveListR::listPFromChildPages(
            [
                'site-backups/shared-review' => [[
                    'value' => [[
                        'id' => 'uploads',
                        'name' => 'uploads',
                        'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                        'folder' => ['childCount' => 1],
                    ]],
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

        $parent = rclone_onedrive_listr_collect_listp($listP, 'site-backups/shared-review');
        $child = rclone_onedrive_listr_collect_listp($listP, 'site-backups/shared-review/uploads');

        $t->same(['site-backups/shared-review/uploads/'], rclone_onedrive_listr_names($parent['entries']));
        $t->same(['site-backups/shared-review/uploads/hero.jpg'], rclone_onedrive_listr_names($child['entries']));
        $t->same('owner-drive#uploads', $trace['requests'][1]['directoryId']);
    },
    'onedrive child ListP hides OneNote packages unless exposure is requested' => static function (TestRunner $t): void {
        $childPages = [
            'site-backups/shared-review' => [[
                'value' => [
                    [
                        'id' => 'notes',
                        'name' => 'migration-notes.one',
                        'size' => 1024,
                        'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                        'package' => ['type' => 'oneNote'],
                    ],
                    [
                        'id' => 'export',
                        'name' => 'export.wxr',
                        'size' => 18,
                        'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                        'file' => ['mimeType' => 'application/rss+xml'],
                    ],
                ],
            ]],
        ];
        $directoryIds = ['site-backups/shared-review' => 'owner-drive#shared-root'];

        $hidden = rclone_onedrive_listr_collect_listp(
            OneDriveListR::listPFromChildPages($childPages, $directoryIds),
            'site-backups/shared-review',
        );
        $shown = rclone_onedrive_listr_collect_listp(
            OneDriveListR::listPFromChildPages($childPages, $directoryIds, exposeOneNoteFiles: true),
            'site-backups/shared-review',
        );

        $t->same(['site-backups/shared-review/export.wxr'], rclone_onedrive_listr_names($hidden['entries']));
        $t->same([
            'site-backups/shared-review/migration-notes.one',
            'site-backups/shared-review/export.wxr',
        ], rclone_onedrive_listr_names($shown['entries']));
        $t->same('oneNote', $shown['entries'][0]->readMetadata()['package-type']);
    },
    'onedrive child ListP defers permission metadata errors until metadata is read' => static function (TestRunner $t): void {
        $listP = OneDriveListR::listPFromChildPages(
            [
                'site-backups/shared-review' => [[
                    'value' => [[
                        'id' => 'review',
                        'name' => 'review.wxr',
                        'size' => 18,
                        'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                        'file' => ['mimeType' => 'application/rss+xml'],
                        'metadataPermissionsError' => 'Graph permissions failed',
                    ]],
                ]],
            ],
            ['site-backups/shared-review' => 'owner-drive#shared-root'],
        );

        $collected = rclone_onedrive_listr_collect_listp($listP, 'site-backups/shared-review');

        $t->same(['site-backups/shared-review/review.wxr'], rclone_onedrive_listr_names($collected['entries']));
        $t->same('application/rss+xml', $collected['entries'][0]->metadata['content-type']);
        $t->throws(RuntimeException::class, static fn () => $collected['entries'][0]->readMetadata());
        try {
            $collected['entries'][0]->readMetadata();
        } catch (RuntimeException $exception) {
            $t->same('failed to get permissions: Graph permissions failed', $exception->getMessage());
        }
    },
    'onedrive child ListP materializes permissions only in metadata read mode' => static function (TestRunner $t): void {
        $listP = OneDriveListR::listPFromChildPages(
            [
                'site-backups/shared-review' => [[
                    'value' => [
                        [
                            'id' => 'review',
                            'name' => 'review.wxr',
                            'size' => 18,
                            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                            'file' => ['mimeType' => 'application/rss+xml'],
                            'metadataPermissionsMode' => 'read',
                            'metadataPermissions' => [
                                [
                                    'id' => 'perm-user',
                                    'grantedTo' => [
                                        'user' => [
                                            'id' => 'reviewer@example.com',
                                            'displayName' => 'Migration Reviewer',
                                        ],
                                    ],
                                    'roles' => ['read'],
                                ],
                                [
                                    'id' => 'perm-link',
                                    'link' => [
                                        'type' => 'view',
                                        'scope' => 'organization',
                                        'webUrl' => 'https://share.example/review.wxr',
                                    ],
                                    'roles' => ['read'],
                                    'shareId' => 'share-token',
                                ],
                            ],
                        ],
                        [
                            'id' => 'private',
                            'name' => 'private.wxr',
                            'size' => 10,
                            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                            'file' => ['mimeType' => 'application/rss+xml'],
                            'metadataPermissionsMode' => 'off',
                            'metadataPermissions' => [
                                ['id' => 'hidden-permission', 'roles' => ['read']],
                            ],
                        ],
                    ],
                ]],
            ],
            ['site-backups/shared-review' => 'owner-drive#shared-root'],
        );

        $collected = rclone_onedrive_listr_collect_listp($listP, 'site-backups/shared-review');
        $reviewMetadata = $collected['entries'][0]->readMetadata();
        $privateMetadata = $collected['entries'][1]->readMetadata();

        $t->same([
            'site-backups/shared-review/review.wxr',
            'site-backups/shared-review/private.wxr',
        ], rclone_onedrive_listr_names($collected['entries']));
        $t->same(
            '[{"id":"perm-user","grantedTo":{"user":{"id":"reviewer@example.com","displayName":"Migration Reviewer"}},"roles":["read"]},{"id":"perm-link","link":{"type":"view","scope":"organization","webUrl":"https://share.example/review.wxr"},"roles":["read"],"shareId":"share-token"}]',
            $reviewMetadata['permissions'],
        );
        $t->same(false, array_key_exists('permissions', $privateMetadata));
    },
    'onedrive ListR defers permission marshal errors until metadata is read' => static function (TestRunner $t): void {
        $listR = OneDriveListR::fromDelta([
            [
                'id' => 'review',
                'name' => 'review.wxr',
                'size' => 18,
                'parentReference' => ['driveId' => 'drive', 'id' => 'root'],
                'file' => ['mimeType' => 'application/rss+xml'],
                'metadataPermissionsMode' => 'read',
                'metadataPermissionsMarshalError' => 'json: unsupported permission value',
            ],
        ], 'drive#root');

        $collected = rclone_onedrive_listr_collect($listR);

        $t->same(['review.wxr'], rclone_onedrive_listr_names($collected['entries']));
        $t->same('application/rss+xml', $collected['entries'][0]->metadata['content-type']);
        $message = null;
        try {
            $collected['entries'][0]->readMetadata();
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }
        $t->same('failed to marshal permissions: json: unsupported permission value', $message);
    },
    'onedrive child ListP item conversion errors keep flushed batches and suppress pending flush' => static function (TestRunner $t): void {
        $firstPage = [];
        for ($i = 1; $i <= 101; $i++) {
            $firstPage[] = rclone_onedrive_listr_child_file($i);
        }
        $firstPage[] = [
            'id' => 'bad-item',
            'name' => 'bad-item.wxr',
            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
            'file' => [],
            'conversionError' => 'metadata decode failed',
        ];

        $listP = OneDriveListR::listPFromChildPages(
            [
                'site-backups/shared-review' => [[
                    'value' => $firstPage,
                ]],
            ],
            ['site-backups/shared-review' => 'owner-drive#shared-root'],
            listChunk: 102,
        );

        $batches = [];
        $result = $listP(
            'site-backups/shared-review',
            static function (array $batch) use (&$batches): null {
                $batches[] = rclone_onedrive_listr_names($batch);

                return null;
            },
        );

        $t->same("couldn't convert list item: metadata decode failed", $result?->getMessage());
        $t->same([100], array_map('count', $batches));
        $t->same('site-backups/shared-review/asset-001.wxr', $batches[0][0]);
        $t->same('site-backups/shared-review/asset-100.wxr', $batches[0][99]);
    },
    'onedrive ListR shared-folder fallback can recurse through paged child ListP' => static function (TestRunner $t): void {
        $childTrace = [];
        $sharedListP = OneDriveListR::listPFromChildPages(
            [
                'site-backups/shared-review' => [
                    [
                        'value' => [
                            [
                                'id' => 'users',
                                'name' => 'users.wxr',
                                'size' => 17,
                                'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                                'file' => ['mimeType' => 'application/rss+xml'],
                            ],
                            [
                                'id' => 'uploads',
                                'name' => 'uploads',
                                'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                                'folder' => ['childCount' => 1],
                            ],
                        ],
                        '@odata.nextLink' => 'https://graph.example/shared-review/children?page=2',
                    ],
                    'https://graph.example/shared-review/children?page=2' => [
                        'value' => [[
                            'id' => 'readme',
                            'name' => 'review-notes.txt',
                            'size' => 12,
                            'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-root'],
                            'file' => ['mimeType' => 'text/plain'],
                        ]],
                    ],
                ],
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
            [
                'site-backups/shared-review' => 'owner-drive#shared-root',
            ],
            listChunk: 2,
            trace: $childTrace,
        );

        $listR = OneDriveListR::fromDelta(
            [
                [
                    'id' => 'shared-local',
                    'name' => 'ignored-local-name',
                    'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
                    'remoteItem' => [
                        'id' => 'shared-root',
                        'name' => 'shared-review',
                        'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-parent'],
                        'folder' => ['childCount' => 3],
                    ],
                ],
                [
                    'id' => 'export',
                    'name' => 'export.wxr',
                    'size' => 18,
                    'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
                    'file' => ['mimeType' => 'application/rss+xml'],
                ],
            ],
            'site-drive#root',
            ['site-backups' => 'site-drive#backups'],
            ['site-backups/shared-review' => $sharedListP],
        );

        $collected = rclone_onedrive_listr_collect($listR, 'site-backups');

        $t->same([[
            'site-backups/shared-review/',
            'site-backups/shared-review/users.wxr',
            'site-backups/shared-review/uploads/',
            'site-backups/shared-review/uploads/hero.jpg',
            'site-backups/shared-review/review-notes.txt',
            'site-backups/export.wxr',
        ]], $collected['batches']);
        $t->same([
            'owner-drive#shared-root',
            'owner-drive#users',
            'owner-drive#uploads',
            'owner-drive#hero',
            'owner-drive#readme',
            'site-drive#export',
        ], array_map(static fn (ObjectInfo $entry): ?string => $entry->id, $collected['entries']));
        $t->same('https://graph.example/shared-review/children?page=2', $childTrace['requests'][1]['rootUrl']);
        $t->same('owner-drive#uploads', $childTrace['requests'][2]['directoryId']);
    },
    'onedrive child ListP provider errors keep flushed batches and suppress pending flush' => static function (TestRunner $t): void {
        $firstPage = [];
        for ($i = 1; $i <= 101; $i++) {
            $firstPage[] = rclone_onedrive_listr_child_file($i);
        }

        $trace = [];
        $listP = OneDriveListR::listPFromChildPages(
            [
                'site-backups/shared-review' => [
                    [
                        'value' => $firstPage,
                        '@odata.nextLink' => 'https://graph.example/shared-review/children?page=2',
                    ],
                    'https://graph.example/shared-review/children?page=2' => [
                        'error' => 'Graph children failed',
                    ],
                ],
            ],
            ['site-backups/shared-review' => 'owner-drive#shared-root'],
            listChunk: 101,
            trace: $trace,
        );

        $batches = [];
        $result = $listP(
            'site-backups/shared-review',
            static function (array $batch) use (&$batches): null {
                $batches[] = rclone_onedrive_listr_names($batch);

                return null;
            },
        );

        $t->same("couldn't list files: Graph children failed", $result?->getMessage());
        $t->same([100], array_map('count', $batches));
        $t->same('site-backups/shared-review/asset-001.wxr', $batches[0][0]);
        $t->same('site-backups/shared-review/asset-100.wxr', $batches[0][99]);
        $t->same([
            [
                'rootUrl' => null,
                'path' => '/children',
                'parameters' => ['$top' => ['101']],
                'directoryId' => 'owner-drive#shared-root',
            ],
            [
                'rootUrl' => 'https://graph.example/shared-review/children?page=2',
                'path' => '',
                'parameters' => [],
                'directoryId' => 'owner-drive#shared-root',
            ],
        ], $trace['requests']);
    },
    'onedrive shared-folder fallback discards child ListP partials on provider error' => static function (TestRunner $t): void {
        $delta = [];
        for ($i = 1; $i <= 100; $i++) {
            $delta[] = rclone_onedrive_listr_delta_file($i, 'backups');
        }
        $delta[] = [
            'id' => 'shared-local',
            'name' => 'ignored-local-name',
            'parentReference' => ['driveId' => 'drive', 'id' => 'backups'],
            'remoteItem' => [
                'id' => 'shared-root',
                'name' => 'shared-review',
                'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-parent'],
                'folder' => ['childCount' => 100],
            ],
        ];

        $children = [];
        for ($i = 1; $i <= 100; $i++) {
            $children[] = rclone_onedrive_listr_child_file($i);
        }

        $childTrace = [];
        $sharedListP = OneDriveListR::listPFromChildPages(
            [
                'site-backups/shared-review' => [
                    [
                        'value' => $children,
                        '@odata.nextLink' => 'https://graph.example/shared-review/children?page=2',
                    ],
                    'https://graph.example/shared-review/children?page=2' => [
                        'error' => 'Graph shared children failed',
                    ],
                ],
            ],
            ['site-backups/shared-review' => 'owner-drive#shared-root'],
            listChunk: 100,
            trace: $childTrace,
        );

        $listR = OneDriveListR::fromDelta(
            $delta,
            'drive#root',
            ['site-backups' => 'drive#backups'],
            ['site-backups/shared-review' => $sharedListP],
        );

        $batches = [];
        $result = $listR(
            'site-backups',
            static function (array $batch) use (&$batches): null {
                $batches[] = rclone_onedrive_listr_names($batch);

                return null;
            },
        );

        $flat = array_merge(...$batches);
        $t->same("couldn't list files: Graph shared children failed", $result?->getMessage());
        $t->same([100], array_map('count', $batches));
        $t->same('site-backups/asset-001.wxr', $batches[0][0]);
        $t->same('site-backups/asset-100.wxr', $batches[0][99]);
        $t->same(false, in_array('site-backups/shared-review/', $flat, true));
        $t->same(false, in_array('site-backups/shared-review/asset-001.wxr', $flat, true));
        $t->same('https://graph.example/shared-review/children?page=2', $childTrace['requests'][1]['rootUrl']);
    },
    'onedrive shared-folder fallback propagates caller callback errors immediately' => static function (TestRunner $t): void {
        $children = [];
        for ($i = 1; $i <= 99; $i++) {
            $children[] = rclone_onedrive_listr_child_file($i);
        }

        $childTrace = [];
        $sharedListP = OneDriveListR::listPFromChildPages(
            [
                'site-backups/shared-review' => [[
                    'value' => $children,
                ]],
            ],
            ['site-backups/shared-review' => 'owner-drive#shared-root'],
            listChunk: 99,
            trace: $childTrace,
        );

        $listR = OneDriveListR::fromDelta(
            [[
                'id' => 'shared-local',
                'name' => 'ignored-local-name',
                'parentReference' => ['driveId' => 'drive', 'id' => 'backups'],
                'remoteItem' => [
                    'id' => 'shared-root',
                    'name' => 'shared-review',
                    'parentReference' => ['driveId' => 'owner-drive', 'id' => 'shared-parent'],
                    'folder' => ['childCount' => 99],
                ],
            ]],
            'drive#root',
            ['site-backups' => 'drive#backups'],
            ['site-backups/shared-review' => $sharedListP],
        );

        $batches = [];
        $result = $listR(
            'site-backups',
            static function (array $batch) use (&$batches): Throwable {
                $batches[] = rclone_onedrive_listr_names($batch);

                return new RuntimeException('restore manifest consumer stopped');
            },
        );

        $t->same('restore manifest consumer stopped', $result?->getMessage());
        $t->same([100], array_map('count', $batches));
        $t->same('site-backups/shared-review/', $batches[0][0]);
        $t->same('site-backups/shared-review/asset-099.wxr', $batches[0][99]);
        $t->same('owner-drive#shared-root', $childTrace['requests'][0]['directoryId']);
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
    'wordpress onedrive paged delta restore example follows continuation links' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-delta-paginated-restore.php';

        $t->same('listR-delta-pages', $example['source']);
        $t->same([100, 5], $example['batchSizes']);
        $t->same(105, $example['manifestCount']);
        $t->same('site-backups/database.sql', $example['manifest'][0]);
        $t->same('site-backups/export.wxr', $example['manifest'][1]);
        $t->same('site-backups/uploads/image-101.jpg', $example['manifest'][104]);
        $t->same(true, $example['nextLinkFollowed']);
        $t->same([
            [
                'rootUrl' => null,
                'path' => '/root/delta',
                'parameters' => ['$top' => ['75']],
            ],
            [
                'rootUrl' => 'https://graph.example/site-backups/delta?page=2',
                'path' => '',
                'parameters' => [],
            ],
        ], $example['requests']);
    },
    'wordpress onedrive shared ListP pagination example follows child pages' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-shared-listp-pagination.php';

        $t->same('listR-shared-listp-pages', $example['source']);
        $t->same([
            'site-backups/shared-review/database.sql',
            'site-backups/shared-review/export.wxr',
            'site-backups/shared-review/users.wxr',
            'site-backups/shared-review/',
            'site-backups/shared-review/uploads/',
            'site-backups/shared-review/uploads/hero.jpg',
        ], $example['manifest']);
        $t->same(true, $example['nextLinkFollowed']);
        $t->same(true, $example['sharedListPUsed']);
        $t->same(true, $example['deletedSkipped']);
        $t->same('owner-drive#uploads', $example['childRequests'][2]['directoryId']);
    },
    'wordpress onedrive shared ListP error example preserves flushed manifest batch' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-shared-listp-error-preflight.php';

        $t->same('listR-shared-listp-error', $example['source']);
        $t->same("couldn't list files: Graph shared review page failed", $example['error']);
        $t->same([100], $example['deliveredBatchSizes']);
        $t->same(100, $example['manifestCount']);
        $t->same('site-backups/asset-001.wxr', $example['manifest'][0]);
        $t->same('site-backups/asset-100.wxr', $example['manifest'][99]);
        $t->same(true, $example['sharedFolderSuppressed']);
        $t->same(true, $example['childPartialSuppressed']);
        $t->same(true, $example['nextLinkReached']);
    },
    'wordpress onedrive ListP cache metadata example records scoped restore preflight' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-listp-cache-metadata-preflight.php';

        $t->same('listp-cache-metadata-preflight', $example['source']);
        $t->same([
            'site-backups/shared-review/review-export.wxr',
            'site-backups/shared-review/uploads/',
            'site-backups/shared-review/uploads/hero.jpg',
        ], $example['manifest']);
        $t->same(true, $example['childCacheWorked']);
        $t->same(true, $example['oneNoteHidden']);
        $t->same('failed to get permissions: Graph permissions denied', $example['metadataError']);
        $t->same('application/rss+xml', $example['listedContentType']);
    },
    'wordpress onedrive permissions metadata example records read off and marshal modes' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-permissions-metadata-preflight.php';

        $t->same('onedrive-permissions-metadata-preflight', $example['source']);
        $t->same([
            'site-backups/review-export.wxr',
            'site-backups/private-draft.wxr',
            'site-backups/broken-permissions.wxr',
        ], $example['manifest']);
        $t->same(['perm-reviewer', 'perm-share-link'], $example['permissionIds']);
        $t->same('Migration Reviewer', $example['reviewerDisplayName']);
        $t->same(true, $example['noPermissionsWhenOff']);
        $t->same('failed to marshal permissions: json: unsupported permission value', $example['marshalError']);
    },
];
