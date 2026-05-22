<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\HashType;
use PortLibs\Rclone\SyncPlan;

return [
    'copy file maps upstream same-object no-op and idempotent copies' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('file1', 'file1 contents', ['modTime' => '2026-05-22T01:00:00Z']);

        $plan = new SyncPlan();
        $first = $plan->copyFile($remote, $local, 'sub/file2', 'file1');
        $second = $plan->copyFile($remote, $local, 'sub/file2', 'file1');
        $sameObject = $plan->copyFile($remote, $remote, 'sub/file2', 'sub/file2');

        $t->same('sub/file2', $first['copied']?->path);
        $t->same([], array_filter([$second['copied']?->path]));
        $t->same(true, $sameObject['skipped']);
        $t->same('file1 contents', $local->get('file1'));
        $t->same(['sub/file2'], array_map(static fn ($info) => $info->path, $remote->list()));
        $t->same('2026-05-22T01:00:00Z', $remote->info('sub/file2')->modTime);
    },
    'move file copies to destination then deletes source like upstream' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('file1', 'file1 contents', ['modTime' => '2026-05-22T01:00:00Z']);

        $plan = new SyncPlan();
        $moved = $plan->moveFile($remote, $local, 'sub/file2', 'file1');

        $t->same('sub/file2', $moved['moved']?->path);
        $t->same('file1', $moved['deletedSource']?->path);
        $t->throws(RuntimeException::class, static fn () => $local->get('file1'));
        $t->same('file1 contents', $remote->get('sub/file2'));

        $local->put('file1', 'file1 contents updated', ['modTime' => '2026-05-22T02:00:00Z']);
        $moved = $plan->moveFile($remote, $local, 'sub/file2', 'file1');

        $t->same('sub/file2', $moved['moved']?->path);
        $t->same('file1', $moved['deletedSource']?->path);
        $t->throws(RuntimeException::class, static fn () => $local->get('file1'));
        $t->same('file1 contents updated', $remote->get('sub/file2'));

        $sameObject = $plan->moveFile($remote, $remote, 'sub/file2', 'sub/file2');
        $t->same(true, $sameObject['skipped']);
        $t->same('file1 contents updated', $remote->get('sub/file2'));
    },
    'move file with ignore existing leaves modified source untouched' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('file1', 'file1 contents', ['modTime' => '2026-05-22T01:00:00Z']);

        $plan = new SyncPlan();
        $moved = $plan->moveFile($remote, $local, 'file1', 'file1');
        $t->same('file1', $moved['deletedSource']?->path);
        $t->same('file1 contents', $remote->get('file1'));

        $local->put('file1', 'file1 modified', ['modTime' => '2026-05-22T02:00:00Z']);
        $skipped = $plan->moveFile($remote, $local, 'file1', 'file1', ['ignoreExisting' => true]);

        $t->same(true, $skipped['skipped']);
        $t->same(null, $skipped['deletedSource']);
        $t->same('file1 modified', $local->get('file1'));
        $t->same('file1 contents', $remote->get('file1'));
    },
    'case insensitive move file changes only casing through temporary rename' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(true);
        $remote->put('sub/file2', 'file1 contents', ['modTime' => '2026-05-22T01:00:00Z']);

        $result = (new SyncPlan())->moveFile($remote, $remote, 'sub/File2', 'sub/file2');

        $t->same(true, $result['caseInsensitiveMove']);
        $t->same('sub/File2', $result['moved']?->path);
        $t->same('file1 contents', $remote->get('sub/file2'));
        $t->same('sub/File2', $remote->info('sub/file2')->path);
        $t->same(['sub/File2'], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'move file archives overwritten destination into backup dir' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('dst/file1', 'file1 contents', ['modTime' => '2026-05-22T01:00:00Z']);
        $remote->put('dst/file1', 'file1 contents old', ['modTime' => '2026-05-21T01:00:00Z']);

        $result = (new SyncPlan())->moveFile($remote, $local, 'dst/file1', 'dst/file1', [
            'backupPrefix' => 'backup',
        ]);

        $t->same('backup/dst/file1', $result['backup']?->path);
        $t->same('dst/file1', $result['moved']?->path);
        $t->same('dst/file1', $result['deletedSource']?->path);
        $t->same('file1 contents old', $remote->get('backup/dst/file1'));
        $t->same('file1 contents', $remote->get('dst/file1'));
        $t->throws(RuntimeException::class, static fn () => $local->get('dst/file1'));
    },
    'copy file partial upload failures clean temporary objects' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider(serverSideMove: true);
        $local->put('exports/site.wxr', '<rss>fresh export</rss>', ['modTime' => '2026-05-22T01:00:00Z']);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>', ['modTime' => '2026-05-21T01:00:00Z']);

        $error = null;
        try {
            (new SyncPlan())->copyFile($remote, $local, 'exports/site.wxr', 'exports/site.wxr', [
                'partialUploads' => true,
                'partialSuffix' => '.partial',
                'simulatePartialTransferError' => true,
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('failed to copy: simulated partial transfer error', $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same(['exports/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));
        $t->same('exports/site.wxr', (new SyncPlan())->copyFile($remote, $local, 'exports/site.wxr', 'exports/site.wxr', [
            'partialUploads' => true,
        ])['copied']?->path);
        $t->same('<rss>fresh export</rss>', $remote->get('exports/site.wxr'));
    },
    'remove existing returns noop cleanup for missing files and requires direct move' => static function (TestRunner $t): void {
        $plan = new SyncPlan();
        $copyOnly = new MemoryProvider(serverSideMove: false, serverSideCopy: true);

        $missing = $plan->removeExisting($copyOnly, 'exports/missing.wxr', 'TEST', '.12345678');
        $t->same(false, $missing['existed']);
        $t->same(null, $missing['savedPath']);
        $operationError = null;
        $missing['cleanup']($operationError);
        $t->same(null, $operationError);

        $copyOnly->put('exports/site.wxr', '<rss>previous export</rss>');
        try {
            $plan->removeExisting($copyOnly, 'exports/site.wxr', 'TEST', '.12345678');
            throw new RuntimeException('Expected direct move requirement error');
        } catch (RuntimeException $throwable) {
            $t->same("TEST: destination file exists already and can't rename", $throwable->getMessage());
        }
    },
    'remove existing deletes saved object after successful replacement' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');
        $remote->put('database/site.sql', 'insert into wp_posts values (...)');

        $handle = (new SyncPlan())->removeExisting($remote, 'exports/site.wxr', 'TEST', '.12345678');
        $t->same(true, $handle['existed']);
        $t->same('exports/site.wxr.12345678', $handle['savedPath']);
        $t->same(['database/site.sql', 'exports/site.wxr.12345678'], array_map(static fn ($info) => $info->path, $remote->list()));
        $t->throws(RuntimeException::class, static fn () => $remote->get('exports/site.wxr'));

        $remote->put('exports/site.wxr', '<rss>fresh export</rss>');
        $operationError = null;
        $handle['cleanup']($operationError);

        $t->same(null, $operationError);
        $t->same(['database/site.sql', 'exports/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));
        $t->same('<rss>fresh export</rss>', $remote->get('exports/site.wxr'));
    },
    'remove existing restores saved object after failed operation and truncates long names' => static function (TestRunner $t): void {
        $longLeaf = 'site-export-' . str_repeat('segment-', 14) . 'final.wxr';
        $path = 'exports/' . $longLeaf;
        $remote = new MemoryProvider(serverSideMove: true);
        $remote->put($path, '<rss>previous long export</rss>');

        $handle = (new SyncPlan())->removeExisting($remote, $path, 'TEST', '.12345678');

        $t->same(true, $handle['existed']);
        $t->same(strlen($path), strlen($handle['savedPath'] ?? ''));
        $t->true(str_ends_with($handle['savedPath'] ?? '', '.12345678'));
        $t->true(!str_ends_with($handle['savedPath'] ?? '', $longLeaf . '.12345678'));
        $t->throws(RuntimeException::class, static fn () => $remote->get($path));

        $operationError = new RuntimeException('BOOM');
        $handle['cleanup']($operationError);

        $t->same('BOOM', $operationError->getMessage());
        $t->same('<rss>previous long export</rss>', $remote->get($path));
        $t->same([$path], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'remove existing reports cleanup delete failures without hiding success path' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $handle = (new SyncPlan())->removeExisting($remote, 'exports/site.wxr', 'TEST', '.12345678');
        $remote->put('exports/site.wxr', '<rss>fresh export</rss>');
        $remote->delete($handle['savedPath']);

        $operationError = null;
        $handle['cleanup']($operationError);

        $t->same('TEST: failed to remove renamed existing file: Object not found: exports/site.wxr.12345678', $operationError?->getMessage());
        $t->same('<rss>fresh export</rss>', $remote->get('exports/site.wxr'));
    },
    'server side copy replace removes existing destination then deletes saved object' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>', ['modTime' => '2026-05-22T02:00:00Z']);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>', ['modTime' => '2026-05-21T02:00:00Z']);

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
            'temporarySuffix' => '.copytmp',
        ]);

        $t->same('exports/site.wxr.copytmp', $result['savedPath']);
        $t->same('exports/site.wxr', $result['copied']->path);
        $t->same('<rss>fresh export</rss>', $remote->get('exports/site.wxr'));
        $t->same('2026-05-22T02:00:00Z', $remote->info('exports/site.wxr')->modTime);
        $t->same(['exports/site.wxr', 'library/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));
        $t->throws(RuntimeException::class, static fn () => $remote->get('exports/site.wxr.copytmp'));
    },
    'server side copy replace restores existing destination after copy failure' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>');
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'temporarySuffix' => '.copytmp',
                'simulateCopyError' => 'provider copy failed',
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('provider copy failed', $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
        $t->same(['exports/site.wxr', 'library/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'server side copy replace rejects same remote case folded paths' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('exports/site.wxr', '<rss>portable export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'exports/site.wxr', 'EXPORTS/SITE.WXR', [
                'guardCaseFoldSameRemote' => true,
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('can\'t copy "exports/site.wxr" -> "EXPORTS/SITE.WXR" as are same name when lowercase', $error?->getMessage());
        $t->same('<rss>portable export</rss>', $remote->get('exports/site.wxr'));
        $t->same(['exports/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'server side copy precreated destination handle is not visible after failure' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'precreateDestination' => true,
                'simulateCopyError' => 'provider copy failed',
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('provider copy failed', $error?->getMessage());
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
        $t->same(['library/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));
        $t->throws(RuntimeException::class, static fn () => $remote->get('exports/site.wxr'));

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
            'precreateDestination' => true,
        ]);
        $t->same('exports/site.wxr', $result['precreatedPath']);
        $t->same('<rss>fresh export</rss>', $remote->get('exports/site.wxr'));
    },
    'dropbox server side copy uses relocation result metadata' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>', [
            'modTime' => '2026-05-22T02:00:00Z',
            'metadata' => ['wp-artifact' => 'wxr'],
            'id' => 'id:source',
        ]);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
            'provider' => 'dropbox',
            'temporarySuffix' => '.copytmp',
            'apiResult' => [
                'id' => 'id:dropbox-copy',
                'clientModified' => '2026-05-22T02:03:04Z',
                'contentHash' => 'ABCDEF0123456789',
                'mimeType' => 'application/rss+xml',
                'metadata' => ['dropbox_rev' => 'rev-copy'],
            ],
        ]);

        $info = $remote->info('exports/site.wxr');
        $t->same('id:dropbox-copy', $result['copied']->id);
        $t->same('id:dropbox-copy', $info->id);
        $t->same('2026-05-22T02:03:04Z', $info->modTime);
        $t->same('application/rss+xml', $info->mimeType);
        $t->same('abcdef0123456789', $info->metadata['dropbox_content_hash']);
        $t->same('rev-copy', $info->metadata['dropbox_rev']);
        $t->same(['dropbox:relocation-result-metadata'], $result['metadataRefresh']);
    },
    'onedrive server side copy resets source modtime and add-only permission metadata' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(
            supportedHashes: new HashSet(HashType::SHA1, HashType::CRC32),
            serverSideMove: true,
            serverSideCopy: true,
        );
        $remote->put('library/site.wxr', '<rss>fresh export</rss>', [
            'modTime' => '2026-05-22T02:00:00Z',
            'metadata' => [
                'description' => 'portable export',
                'permissions' => '[{"roles":["read"]}]',
            ],
            'id' => 'onedrive-source',
        ]);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
            'provider' => 'onedrive',
            'apiResult' => [
                'id' => 'onedrive-copy',
                'mimeType' => 'application/rss+xml',
                'hashes' => [
                    'sha1Hash' => strtoupper(hash('sha1', '<rss>fresh export</rss>')),
                    'crc32Hash' => strtoupper(hash('crc32b', '<rss>fresh export</rss>')),
                ],
            ],
        ]);

        $info = $remote->info('exports/site.wxr');
        $t->same('onedrive-copy', $result['copied']->id);
        $t->same('2026-05-22T02:00:00Z', $info->modTime);
        $t->same('add-only', $info->metadata['onedrive_permissions_mode']);
        $t->same('portable export', $info->metadata['description']);
        $t->same(hash('sha1', '<rss>fresh export</rss>'), $remote->hashes('exports/site.wxr', new HashSet(HashType::SHA1))[HashType::SHA1]);
        $t->same(hash('crc32b', '<rss>fresh export</rss>'), $remote->hashes('exports/site.wxr', new HashSet(HashType::CRC32))[HashType::CRC32]);
        $t->same([
            'onedrive:async-copy-job',
            'onedrive:set-source-modtime',
            'onedrive:metadata-permissions-add-only',
        ], $result['metadataRefresh']);
    },
    'yandex server side copy refreshes object metadata from custom rclone modtime' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(
            supportedHashes: new HashSet(HashType::MD5),
            serverSideMove: true,
            serverSideCopy: true,
        );
        $remote->put('library/site.wxr', '<rss>fresh export</rss>', [
            'modTime' => '2026-05-22T02:00:00Z',
            'mimeType' => 'application/octet-stream',
        ]);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
            'provider' => 'yandex',
            'apiResult' => [
                'customProperties' => ['rclone_modified' => '2026-05-22T03:04:05Z'],
                'modified' => '2026-05-20T00:00:00Z',
                'md5' => strtoupper(hash('md5', '<rss>fresh export</rss>')),
                'mimeType' => 'application/rss+xml',
            ],
        ]);

        $info = $remote->info('exports/site.wxr');
        $t->same('2026-05-22T03:04:05Z', $info->modTime);
        $t->same('application/rss+xml', $info->mimeType);
        $t->same(hash('md5', '<rss>fresh export</rss>'), $remote->hashes('exports/site.wxr', new HashSet(HashType::MD5))[HashType::MD5]);
        $t->same(['yandex:new-object-metadata-read'], $result['metadataRefresh']);
    },
    'sugarsync server side copy records copied object location after metadata read' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(
            supportedHashes: new HashSet(),
            serverSideMove: true,
            serverSideCopy: true,
        );
        $remote->put('library/site.wxr', '<rss>fresh export</rss>', [
            'modTime' => '2026-05-22T02:00:00Z',
            'metadata' => ['wp-artifact' => 'wxr'],
            'id' => 'sugar-source',
        ]);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
            'provider' => 'sugarsync',
            'apiResult' => [
                'location' => 'https://api.sugarsync.com/file/copied-site-wxr',
                'lastModified' => '2026-05-22T04:00:00Z',
            ],
        ]);

        $info = $remote->info('exports/site.wxr');
        $t->same('https://api.sugarsync.com/file/copied-site-wxr', $result['copied']->id);
        $t->same('https://api.sugarsync.com/file/copied-site-wxr', $info->id);
        $t->same('2026-05-22T04:00:00Z', $info->modTime);
        $t->same(['wp-artifact' => 'wxr'], $info->metadata);
        $t->same([], $remote->hashes('exports/site.wxr'));
        $t->same(['sugarsync:metadata-read-after-copy'], $result['metadataRefresh']);
    },
    'dropbox server side copy restores destination when relocation result is not a file' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>');
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'provider' => 'dropbox',
                'apiResult' => ['metadataType' => 'folder'],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('is not a regular file', $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
        $t->same(['exports/site.wxr', 'library/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'onedrive server side copy access denied becomes cant copy and restores destination' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>');
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'provider' => 'onedrive',
                'providerError' => ['kind' => 'async-access-denied'],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same(MemoryProvider::ERROR_CANT_COPY, $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
    },
    'yandex server side copy wraps async failure and restores destination' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>');
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'provider' => 'yandex',
                'providerError' => ['kind' => 'async-failure'],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('couldn\'t copy file: async operation returned "failure"', $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
    },
    'sugarsync server side copy extracts provider html errors and restores destination' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>');
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'provider' => 'sugarsync',
                'providerError' => [
                    'kind' => 'html-error',
                    'status' => 409,
                    'statusText' => '409 Conflict',
                    'message' => 'Can not copy file.',
                ],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('HTTP error 409 (409 Conflict): Can not copy file.', $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
    },
    'single file wordpress upload repair uses move ignore-existing and partial cleanup boundaries' => static function (TestRunner $t): void {
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $local = new MemoryProvider();
        $remote = new MemoryProvider(true);
        $local->put('wp-content/uploads/2026/05/hero-renamed.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
        $local->put('exports/site.wxr', $tree['exports/site.wxr']);
        $remote->put('wp-content/uploads/2026/05/Hero.JPG', $tree['wp-content/uploads/2026/05/hero.jpg']);
        $remote->put('exports/site.wxr', '<rss>remote recovery export</rss>');

        $plan = new SyncPlan();
        $caseMove = $plan->moveFile(
            $remote,
            $remote,
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/Hero.JPG',
        );
        $ignored = $plan->moveFile($remote, $local, 'exports/site.wxr', 'exports/site.wxr', [
            'ignoreExisting' => true,
        ]);

        $t->same(true, $caseMove['caseInsensitiveMove']);
        $t->same('wp-content/uploads/2026/05/hero.jpg', $remote->info('wp-content/uploads/2026/05/Hero.JPG')->path);
        $t->same(true, $ignored['skipped']);
        $t->same($tree['exports/site.wxr'], $local->get('exports/site.wxr'));
        $t->same('<rss>remote recovery export</rss>', $remote->get('exports/site.wxr'));
    },
    'track renames can use copy delete providers without direct move support' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider(serverSideMove: false, serverSideCopy: true);
        $source->put('yaml', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);
        $target->put('yam', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);

        $result = (new SyncPlan())->syncWithTrackRenames($source, $target);

        $t->same(true, $result['trackRenamesEnabled']);
        $t->same(null, $result['disabledReason']);
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same([], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same([], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $target->list()));
        $t->same('Yam Content', $target->get('yaml'));
        $t->throws(RuntimeException::class, static fn () => $target->get('yam'));
    },
    'track renames falls back to copy delete when direct move reports cant move' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider(
            serverSideMove: true,
            serverSideCopy: false,
            serverSideMoveError: MemoryProvider::ERROR_CANT_MOVE,
        );
        $source->put('yaml', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);
        $target->put('yam', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);

        $result = (new SyncPlan())->syncWithTrackRenames($source, $target);

        $t->same(true, $result['trackRenamesEnabled']);
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same([], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same([], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $target->list()));
    },
    'track renames failed server side move uploads source then deletes stale target' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider(serverSideMoveError: 'provider move failed');
        $source->put('yaml', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);
        $target->put('yam', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);

        $result = (new SyncPlan())->syncWithTrackRenames($source, $target);

        $t->same(true, $result['trackRenamesEnabled']);
        $t->same([], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same(['yam'], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $target->list()));
        $t->same('Yam Content', $target->get('yaml'));
    },
    'directory move uses provider dir move when available' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('wp-content/uploads/2026/05', [
            'modTime' => '2026-05-22T00:00:00Z',
            'metadata' => ['wp-scope' => 'uploads-month'],
        ]);
        $provider->put('wp-content/uploads/2026/05/hero.jpg', 'image bytes');

        $result = (new SyncPlan())->moveDirectory($provider, 'wp-content/uploads', 'archive/uploads');

        $t->same(true, $result['usedDirMove']);
        $t->same(null, $result['fallbackReason']);
        $t->same(['archive/uploads'], array_map(static fn ($info) => $info->path, $result['moved']));
        $t->same('image bytes', $provider->get('archive/uploads/2026/05/hero.jpg'));
        $t->same(['wp-scope' => 'uploads-month'], $provider->directoryInfo('archive/uploads/2026/05')->metadata);
        $t->throws(RuntimeException::class, static fn () => $provider->directoryInfo('wp-content/uploads'));
    },
    'directory move falls back to object moves when dir move is unavailable' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(
            serverSideMove: false,
            serverSideCopy: true,
            serverSideDirMove: false,
        );
        $provider->mkdir('wp-content/uploads/2026/05', [
            'modTime' => '2026-05-22T00:00:00Z',
            'metadata' => ['wp-scope' => 'uploads-month'],
        ]);
        $provider->put('wp-content/uploads/2026/05/hero.jpg', 'image bytes');
        $provider->put('wp-content/uploads/2026/05/hero.webp', 'webp bytes');

        $result = (new SyncPlan())->moveDirectory($provider, 'wp-content/uploads', 'archive/uploads');

        $t->same(false, $result['usedDirMove']);
        $t->same(MemoryProvider::ERROR_CANT_DIR_MOVE, $result['fallbackReason']);
        $t->same([
            'archive/uploads/2026/05/hero.jpg',
            'archive/uploads/2026/05/hero.webp',
        ], array_map(static fn ($info) => $info->path, $result['moved']));
        $t->same('image bytes', $provider->get('archive/uploads/2026/05/hero.jpg'));
        $t->same('webp bytes', $provider->get('archive/uploads/2026/05/hero.webp'));
        $t->same('2026-05-22T00:00:00Z', $provider->directoryInfo('archive/uploads/2026/05')->modTime);
        $t->throws(RuntimeException::class, static fn () => $provider->get('wp-content/uploads/2026/05/hero.jpg'));
        $t->throws(RuntimeException::class, static fn () => $provider->directoryInfo('wp-content/uploads'));
    },
    'directory move fatal provider errors do not fall back' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(serverSideDirMoveError: 'dir move failed permanently');
        $provider->put('wp-content/uploads/2026/05/hero.jpg', 'image bytes');

        $t->throws(
            RuntimeException::class,
            static fn () => (new SyncPlan())->moveDirectory($provider, 'wp-content/uploads', 'archive/uploads'),
        );
        $t->same('image bytes', $provider->get('wp-content/uploads/2026/05/hero.jpg'));
    },
];
