<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveDeltaCursor;

return [
    'onedrive delta cursor extracts resume tokens and builds next request' => static function (TestRunner $t): void {
        $response = [
            '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/drives/site-drive/root/delta?token=after-seed&$select=id',
        ];

        $t->same('after-seed', OneDriveDeltaCursor::startPageToken($response));
        $t->same('', OneDriveDeltaCursor::tokenFromDeltaLink('https://graph.microsoft.com/v1.0/drives/site-drive/root/delta'));
        $t->same([
            'method' => 'GET',
            'rootUrl' => 'https://graph.microsoft.com/v1.0/drives',
            'path' => '/site-drive/root/delta',
            'parameters' => ['token' => ['after-seed']],
        ], OneDriveDeltaCursor::buildDriveDeltaRequest('site-drive', 'after-seed'));
        $t->same([
            'method' => 'GET',
            'rootUrl' => 'https://tenant.example/v2.0/drives',
            'path' => '/tenant-drive/root/delta',
            'parameters' => ['token' => ['latest']],
        ], OneDriveDeltaCursor::buildDriveDeltaRequest('tenant-drive', 'latest', 'https://tenant.example/'));
    },
    'onedrive delta notifications scope changes and skip root outside and invalid entries' => static function (TestRunner $t): void {
        $result = OneDriveDeltaCursor::notifications([
            '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/drives/site-drive/root/delta?token=next-token',
            'value' => [
                [
                    'name' => 'drive-root',
                    'parentReference' => ['id' => '', 'path' => '/drives/site-drive/root:'],
                    'folder' => [],
                ],
                [
                    'name' => 'site-backups',
                    'parentReference' => ['id' => 'root', 'path' => '/drives/site-drive/root:'],
                    'folder' => [],
                ],
                [
                    'name' => 'export.wxr',
                    'parentReference' => ['id' => 'backups', 'path' => '/drives/site-drive/root:/site-backups'],
                    'file' => [],
                ],
                [
                    'name' => 'uploads',
                    'parentReference' => ['id' => 'backups', 'path' => '/drives/site-drive/root:/site-backups'],
                    'folder' => [],
                ],
                [
                    'name' => 'other.sql',
                    'parentReference' => ['id' => 'other', 'path' => '/drives/site-drive/root:/other-site'],
                    'file' => [],
                ],
                [
                    'name' => 'stale.wxr',
                    'parentReference' => ['id' => 'backups', 'path' => '/drives/site-drive/root:/site-backups'],
                    'deleted' => [],
                ],
                [
                    'name' => 'bad.wxr',
                    'parentReference' => ['id' => 'backups', 'path' => '/drives/site-drive/root/site-backups'],
                    'file' => [],
                ],
            ],
        ], 'site-backups');

        $t->same('next-token', $result['nextToken']);
        $t->same([
            ['path' => 'export.wxr', 'type' => OneDriveDeltaCursor::ENTRY_OBJECT],
            ['path' => 'uploads', 'type' => OneDriveDeltaCursor::ENTRY_DIRECTORY],
        ], $result['changes']);
        $t->same(['invalid parent path: /drives/site-drive/root/site-backups'], $result['errors']);
    },
    'onedrive delta notifications support empty roots and remote item names' => static function (TestRunner $t): void {
        $result = OneDriveDeltaCursor::notifications([
            '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/drives/site-drive/root/delta?token=next-empty-root',
            'value' => [[
                'name' => 'local-shared-name',
                'remoteItem' => [
                    'name' => 'users.wxr',
                    'file' => [],
                ],
                'parentReference' => ['id' => 'review', 'path' => '/drives/site-drive/root:/site-backups/shared-review'],
            ]],
        ], '');

        $t->same('next-empty-root', $result['nextToken']);
        $t->same([
            ['path' => 'site-backups/shared-review/users.wxr', 'type' => OneDriveDeltaCursor::ENTRY_OBJECT],
        ], $result['changes']);
        $t->same([], $result['errors']);
    },
    'wordpress onedrive delta resume example reports changed backup artifacts' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-delta-resume-cache.php';

        $t->same('seed-token', $example['startToken']);
        $t->same('/site-drive/root/delta', $example['nextRequest']['path']);
        $t->same(['token' => ['seed-token']], $example['nextRequest']['parameters']);
        $t->same('after-review', $example['nextToken']);
        $t->same([
            'export.wxr',
            'uploads/hero.jpg',
        ], $example['changedObjects']);
        $t->same(['uploads'], $example['changedDirectories']);
        $t->same(true, $example['rootSkipped']);
        $t->same(true, $example['outsideRootSkipped']);
        $t->same(['invalid parent path: /drives/site-drive/root/site-backups'], $example['errors']);
    },
    'onedrive change notify runner pauses drains closes and logs provider failures' => static function (TestRunner $t): void {
        $notified = [];
        $summary = OneDriveDeltaCursor::runChangeNotify(
            ['@odata.deltaLink' => 'https://graph.example/root/delta?token=seed'],
            [
                'seed' => [
                    '@odata.deltaLink' => 'https://graph.example/root/delta?token=after-first',
                    'value' => [[
                        'name' => 'export.wxr',
                        'parentReference' => ['id' => 'backups', 'path' => '/drives/site/root:/site-backups'],
                        'file' => [],
                    ]],
                ],
                'after-first' => 'Graph temporary failure',
            ],
            [0, 30, 45, null, 60],
            'site-backups',
            'site-drive',
            static function (string $path, string $type) use (&$notified): null {
                $notified[] = [$path, $type];

                return null;
            },
        );

        $t->same('seed', $summary['startToken']);
        $t->same('', $summary['finalToken']);
        $t->same([
            ['export.wxr', OneDriveDeltaCursor::ENTRY_OBJECT],
        ], $notified);
        $t->same([
            ['path' => 'export.wxr', 'type' => OneDriveDeltaCursor::ENTRY_OBJECT],
        ], $summary['notified']);
        $t->same([
            OneDriveDeltaCursor::buildDriveDeltaRequest('site-drive', 'seed'),
            OneDriveDeltaCursor::buildDriveDeltaRequest('site-drive', 'after-first'),
        ], $summary['requests']);
        $t->same([
            'polling paused',
            'Change notify listener failure: Graph temporary failure',
        ], $summary['log']);
        $t->same(true, $summary['stopped']);
    },
    'onedrive change notify runner stops on missing start token and callback cancellation' => static function (TestRunner $t): void {
        $missingStart = OneDriveDeltaCursor::runChangeNotify([], [], [30], 'site-backups', 'site-drive', static fn (): null => null);

        $t->same('', $missingStart['startToken']);
        $t->same([], $missingStart['requests']);
        $t->same(['Could not get first deltaLink'], $missingStart['log']);
        $t->same(true, $missingStart['stopped']);

        $summary = OneDriveDeltaCursor::runChangeNotify(
            ['@odata.deltaLink' => 'https://graph.example/root/delta?token=seed'],
            [
                'seed' => [
                    '@odata.deltaLink' => 'https://graph.example/root/delta?token=after-first',
                    'value' => [
                        [
                            'name' => 'one.wxr',
                            'parentReference' => ['id' => 'backups', 'path' => '/drives/site/root:/site-backups'],
                            'file' => [],
                        ],
                        [
                            'name' => 'two.wxr',
                            'parentReference' => ['id' => 'backups', 'path' => '/drives/site/root:/site-backups'],
                            'file' => [],
                        ],
                    ],
                ],
            ],
            [30, 30],
            'site-backups',
            'site-drive',
            static fn (string $path, string $type): bool => $path !== 'one.wxr',
        );

        $t->same('after-first', $summary['finalToken']);
        $t->same([
            ['path' => 'one.wxr', 'type' => OneDriveDeltaCursor::ENTRY_OBJECT],
        ], $summary['notified']);
        $t->same(['Change notify callback cancelled'], $summary['log']);
        $t->same(true, $summary['stopped']);
    },
    'wordpress onedrive change notify watch example reports media import changes' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-change-notify-media-watch.php';

        $t->same('watch-seed', $example['startToken']);
        $t->same('after-scan', $example['finalToken']);
        $t->same([
            'uploads/2026/05/hero.jpg',
            'exports/site.wxr',
        ], $example['queuedImports']);
        $t->same([
            'polling paused',
            'Could not get item full path: invalid parent path: /drives/site/root/wp-content/uploads',
        ], $example['log']);
        $t->same(true, $example['closed']);
    },
];
