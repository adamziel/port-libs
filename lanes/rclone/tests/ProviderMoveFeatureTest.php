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
    'copy file same-object no-op records upstream match diagnostics without accounting transfers' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('exports/site.wxr', '<rss>portable export</rss>');

        $result = (new SyncPlan())->copyFile($remote, $remote, 'exports/site.wxr', 'exports/site.wxr');

        $t->same(true, $result['skipped']);
        $t->same(null, $result['copied']);
        $t->same([
            'checkingTransfers' => 0,
            'renames' => 0,
            'deletedFiles' => 0,
            'deletedBytes' => 0,
            'serverSideMoves' => 0,
        ], $result['accounting']);
        $t->same([
            [
                'level' => 'debug',
                'path' => 'exports/site.wxr',
                'message' => "don't need to copy/move exports/site.wxr, it is already at target location",
            ],
        ], $result['logEvents']);
        $t->same([
            [
                'type' => 'match',
                'sourcePath' => 'exports/site.wxr',
                'destinationPath' => 'exports/site.wxr',
                'error' => null,
            ],
        ], $result['loggerEvents']);
        $t->same('<rss>portable export</rss>', $remote->get('exports/site.wxr'));
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
    'server side copy and move apply upstream metadata set values' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('exports/site.wxr', '<rss>portable export</rss>', [
            'modTime' => '2003-02-03T04:05:06.499999999Z',
            'mimeType' => 'text/plain',
            'metadata' => [
                'mtime' => '2003-02-03T04:05:06.499999999Z',
                'potato' => 'jersey',
            ],
        ]);
        $remote->put('uploads/hero-old.jpg', 'image bytes', [
            'modTime' => '2003-02-03T04:05:06.499999999Z',
            'metadata' => [
                'mtime' => '2003-02-03T04:05:06.499999999Z',
                'potato' => 'jersey',
            ],
        ]);

        $metadataSet = [
            'mtime' => '2004-03-03T04:05:06.499999999Z',
            'potato' => 'royal',
        ];
        $plan = new SyncPlan();
        $copied = $plan->copyFile($remote, $remote, 'exports/site-copy.wxr', 'exports/site.wxr', [
            'metadataSet' => $metadataSet,
        ]);
        $moved = $plan->moveFile($remote, $remote, 'uploads/hero.jpg', 'uploads/hero-old.jpg', [
            'metadataSet' => $metadataSet,
        ]);

        $t->same('exports/site-copy.wxr', $copied['copied']?->path);
        $t->same('<rss>portable export</rss>', $remote->get('exports/site-copy.wxr'));
        $t->same('2004-03-03T04:05:06.499999999Z', $remote->info('exports/site-copy.wxr')->modTime);
        $t->same($metadataSet, $remote->info('exports/site-copy.wxr')->metadata);
        $t->same('2003-02-03T04:05:06.499999999Z', $remote->info('exports/site.wxr')->modTime);
        $t->same(['mtime' => '2003-02-03T04:05:06.499999999Z', 'potato' => 'jersey'], $remote->info('exports/site.wxr')->metadata);

        $t->same('uploads/hero.jpg', $moved['moved']?->path);
        $t->same(null, $moved['deletedSource']);
        $t->same('image bytes', $remote->get('uploads/hero.jpg'));
        $t->same('2004-03-03T04:05:06.499999999Z', $remote->info('uploads/hero.jpg')->modTime);
        $t->same($metadataSet, $remote->info('uploads/hero.jpg')->metadata);
        $t->throws(RuntimeException::class, static fn () => $remote->get('uploads/hero-old.jpg'));
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
    'move backup dir deletes existing archive path before preserving overwritten metadata' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('uploads/hero.jpg', 'fresh image bytes', [
            'modTime' => '2026-05-23T10:00:00Z',
            'metadata' => ['wp-artifact' => 'fresh-upload'],
        ]);
        $remote->put('publish/hero.jpg', 'published image bytes', [
            'modTime' => '2026-05-22T10:00:00Z',
            'mimeType' => 'image/jpeg',
            'metadata' => ['wp-artifact' => 'published-media', 'alt' => 'front page'],
        ]);
        $remote->put('archive/publish/hero.bak.jpg', 'stale archive bytes', [
            'modTime' => '2026-05-20T10:00:00Z',
            'metadata' => ['wp-artifact' => 'stale-archive'],
        ]);

        $result = (new SyncPlan())->copyFile($remote, $local, 'publish/hero.jpg', 'uploads/hero.jpg', [
            'backupPrefix' => 'archive',
            'suffix' => '.bak',
            'suffixKeepExtension' => true,
        ]);

        $t->same('archive/publish/hero.bak.jpg', $result['backup']?->path);
        $t->same('published image bytes', $remote->get('archive/publish/hero.bak.jpg'));
        $t->same('2026-05-22T10:00:00Z', $remote->info('archive/publish/hero.bak.jpg')->modTime);
        $t->same('image/jpeg', $remote->info('archive/publish/hero.bak.jpg')->mimeType);
        $t->same(
            ['wp-artifact' => 'published-media', 'alt' => 'front page'],
            $remote->info('archive/publish/hero.bak.jpg')->metadata,
        );
        $t->same('fresh image bytes', $remote->get('publish/hero.jpg'));
        $t->same(['archive/publish/hero.bak.jpg', 'publish/hero.jpg'], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'move backup dir reports delete and rename accounting diagnostics' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('uploads/hero.jpg', 'fresh image bytes');
        $remote->put('publish/hero.jpg', 'published image bytes');
        $remote->put('archive/publish/hero.bak.jpg', 'stale archive bytes');

        $result = (new SyncPlan())->copyFile($remote, $local, 'publish/hero.jpg', 'uploads/hero.jpg', [
            'backupPrefix' => 'archive',
            'suffix' => '.bak',
            'suffixKeepExtension' => true,
        ]);

        $expectedAccounting = [
            'checkingTransfers' => 2,
            'renames' => 1,
            'deletedFiles' => 1,
            'deletedBytes' => strlen('stale archive bytes'),
            'serverSideMoves' => 1,
        ];
        $t->same($expectedAccounting, $result['backupAccounting']);
        $t->same($expectedAccounting, $result['accounting']);
        $t->same([
            [
                'level' => 'info',
                'path' => 'archive/publish/hero.bak.jpg',
                'message' => 'Deleted',
            ],
            [
                'level' => 'info',
                'path' => 'publish/hero.jpg',
                'message' => 'Moved (server-side) to: archive/publish/hero.bak.jpg',
            ],
        ], $result['logEvents']);
        $t->same([
            [
                'type' => 'missing-on-dst',
                'sourcePath' => 'publish/hero.jpg',
                'destinationPath' => null,
                'error' => null,
            ],
        ], $result['loggerEvents']);
        $t->same('published image bytes', $remote->get('archive/publish/hero.bak.jpg'));
        $t->same('fresh image bytes', $remote->get('publish/hero.jpg'));
    },
    'move backup dir delete failure leaves destination backup and source untouched' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('uploads/hero.jpg', 'fresh image bytes');
        $remote->put('publish/hero.jpg', 'published image bytes');
        $remote->put('archive/publish/hero.bak.jpg', 'locked archive bytes');
        $remote->setDeleteError('archive/publish/hero.bak.jpg', 'archive retention lock');

        $error = null;
        try {
            (new SyncPlan())->copyFile($remote, $local, 'publish/hero.jpg', 'uploads/hero.jpg', [
                'backupPrefix' => 'archive',
                'suffix' => '.bak',
                'suffixKeepExtension' => true,
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('archive retention lock', $error?->getMessage());
        $t->same('published image bytes', $remote->get('publish/hero.jpg'));
        $t->same('locked archive bytes', $remote->get('archive/publish/hero.bak.jpg'));
        $t->same('fresh image bytes', $local->get('uploads/hero.jpg'));
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
    'copy file max transfer honors hard cautious and soft cutoff modes' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/small.wxr', str_repeat('s', 16));
        $local->put('exports/large.wxr', str_repeat('l', 64));

        $plan = new SyncPlan();
        $small = $plan->copyFile($remote, $local, 'exports/small.wxr', 'exports/small.wxr', [
            'maxTransfer' => 32,
            'cutoffMode' => 'hard',
        ]);
        $t->same('exports/small.wxr', $small['copied']?->path);

        $hardError = null;
        try {
            $plan->copyFile($remote, $local, 'exports/hard-large.wxr', 'exports/large.wxr', [
                'maxTransfer' => 32,
                'cutoffMode' => 'hard',
            ]);
        } catch (RuntimeException $throwable) {
            $hardError = $throwable;
        }

        $cautiousError = null;
        try {
            $plan->copyFile($remote, $local, 'exports/cautious-large.wxr', 'exports/large.wxr', [
                'maxTransfer' => 64,
                'cutoffMode' => 'cautious',
            ]);
        } catch (RuntimeException $throwable) {
            $cautiousError = $throwable;
        }

        $soft = $plan->copyFile($remote, $local, 'exports/soft-large.wxr', 'exports/large.wxr', [
            'maxTransfer' => 32,
            'cutoffMode' => 'soft',
        ]);

        $softAfterLimitError = null;
        try {
            $plan->copyFile($remote, $local, 'exports/soft-after-limit.wxr', 'exports/small.wxr', [
                'maxTransfer' => 32,
                'cutoffMode' => 'soft',
                'bytesTransferredSoFar' => 32,
            ]);
        } catch (RuntimeException $throwable) {
            $softAfterLimitError = $throwable;
        }

        $t->same('max transfer limit reached as set by --max-transfer', $hardError?->getMessage());
        $t->same('max transfer limit reached as set by --max-transfer', $cautiousError?->getMessage());
        $t->same('max transfer limit reached as set by --max-transfer', $softAfterLimitError?->getMessage());
        $t->same('exports/soft-large.wxr', $soft['copied']?->path);
        $t->same(str_repeat('l', 64), $remote->get('exports/soft-large.wxr'));
        $t->throws(RuntimeException::class, static fn () => $remote->get('exports/hard-large.wxr'));
        $t->throws(RuntimeException::class, static fn () => $remote->get('exports/cautious-large.wxr'));
        $t->throws(RuntimeException::class, static fn () => $remote->get('exports/soft-after-limit.wxr'));
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
    'onedrive case folded copy guard runs after remove existing and restores destination' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(caseInsensitive: true, serverSideMove: true, serverSideCopy: true);
        $remote->put('exports/site.wxr', '<rss>portable export</rss>', [
            'modTime' => '2026-05-22T02:00:00Z',
            'metadata' => ['wp-artifact' => 'published-export'],
        ]);

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'exports/site.wxr', 'EXPORTS/SITE.WXR', [
                'provider' => 'onedrive',
                'temporarySuffix' => '.copytmp',
                'guardCaseFoldSameRemote' => true,
                'guardCaseFoldAfterRemoveExisting' => true,
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('can\'t copy "exports/site.wxr" -> "EXPORTS/SITE.WXR" as are same name when lowercase', $error?->getMessage());
        $t->same('<rss>portable export</rss>', $remote->get('exports/site.wxr'));
        $t->same('EXPORTS/SITE.WXR', $remote->info('exports/site.wxr')->path);
        $t->same('2026-05-22T02:00:00Z', $remote->info('exports/site.wxr')->modTime);
        $t->same(['wp-artifact' => 'published-export'], $remote->info('exports/site.wxr')->metadata);
        $t->same(false, $remote->pathExists('EXPORTS/SITE.WXR.copytmp'));
        $t->same(['EXPORTS/SITE.WXR'], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'onedrive case folded copy guard reports remove existing blocker before guard' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(caseInsensitive: true, serverSideMove: false, serverSideCopy: true);
        $remote->put('exports/site.wxr', '<rss>portable export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'exports/site.wxr', 'EXPORTS/SITE.WXR', [
                'provider' => 'onedrive',
                'guardCaseFoldSameRemote' => true,
                'guardCaseFoldAfterRemoveExisting' => true,
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same("server side copy: destination file exists already and can't rename", $error?->getMessage());
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
    'dropbox server side copy maps non downloadable export metadata' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.paper', '<paper>fresh export</paper>', [
            'modTime' => '2026-05-22T02:00:00Z',
            'metadata' => ['wp-artifact' => 'paper-export'],
            'id' => 'id:source-paper',
        ]);
        $remote->put('exports/site.paper', '<paper>previous export</paper>');

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.paper', 'exports/site.paper', [
            'provider' => 'dropbox',
            'temporarySuffix' => '.copytmp',
            'apiResult' => [
                'id' => 'id:dropbox-paper-copy',
                'clientModified' => '2026-05-22T02:03:04Z',
                'isDownloadable' => false,
                'exportInfo' => [
                    'exportAs' => 'markdown',
                    'exportOptions' => ['html', 'markdown'],
                ],
                'exportFormats' => ['html', 'md'],
            ],
        ]);

        $info = $remote->info('exports/site.html');
        $t->same('exports/site.html', $result['copied']->path);
        $t->same('id:dropbox-paper-copy', $info->id);
        $t->same(-1, $info->size);
        $t->same('2026-05-22T02:03:04Z', $info->modTime);
        $t->same('false', $info->metadata['dropbox_is_downloadable']);
        $t->same('exportable', $info->metadata['dropbox_export_type']);
        $t->same('html', $info->metadata['dropbox_export_format']);
        $t->same('html', $info->metadata['dropbox_export_extension']);
        $t->same('exports/site.html', $info->metadata['dropbox_exposed_remote']);
        $t->same([], $info->hashes);
        $t->same(['exports/site.html', 'library/site.paper'], array_map(static fn ($listed) => $listed->path, $remote->list()));
        $t->throws(RuntimeException::class, static fn () => $remote->get('exports/site.paper'));

        $listOnly = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $listOnly->put('library/site.paper', '<paper>fresh export</paper>');
        $listOnlyResult = (new SyncPlan())->serverSideCopyReplace($listOnly, 'library/site.paper', 'exports/site.paper', [
            'provider' => 'dropbox',
            'apiResult' => [
                'isDownloadable' => false,
                'showAllExports' => true,
            ],
        ]);
        $t->same('exports/site.paper', $listOnlyResult['copied']->path);
        $t->same('list-only', $listOnly->info('exports/site.paper')->metadata['dropbox_export_type']);
        $t->same(-1, $listOnly->info('exports/site.paper')->size);
    },
    'dropbox export format config prefers configured extension and rejects unknown formats' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.paper', '<paper>fresh export</paper>', [
            'metadata' => ['wp-artifact' => 'paper-export'],
            'id' => 'id:source-paper',
        ]);

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.paper', 'exports/site.paper', [
            'provider' => 'dropbox',
            'apiResult' => [
                'id' => 'id:dropbox-paper-markdown',
                'isDownloadable' => false,
                'exportInfo' => [
                    'exportAs' => 'html',
                    'exportOptions' => ['html', 'markdown'],
                ],
                'exportFormats' => ['md', 'html'],
            ],
        ]);

        $info = $remote->info('exports/site.md');
        $t->same('exports/site.md', $result['copied']->path);
        $t->same('markdown', $info->metadata['dropbox_export_format']);
        $t->same('md', $info->metadata['dropbox_export_extension']);
        $t->same('exports/site.md', $info->metadata['dropbox_exposed_remote']);
        $t->same(['exports/site.md', 'library/site.paper'], array_map(static fn ($listed) => $listed->path, $remote->list()));

        $invalid = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $invalid->put('library/site.paper', '<paper>fresh export</paper>');
        $invalid->put('exports/site.paper', '<paper>previous export</paper>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($invalid, 'library/site.paper', 'exports/site.paper', [
                'provider' => 'dropbox',
                'temporarySuffix' => '.copytmp',
                'apiResult' => [
                    'isDownloadable' => false,
                    'exportInfo' => [
                        'exportAs' => 'markdown',
                        'exportOptions' => ['html', 'markdown'],
                    ],
                    'exportFormats' => ['pdf'],
                ],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same("dropbox: unknown export format 'pdf'", $error?->getMessage());
        $t->same('<paper>previous export</paper>', $invalid->get('exports/site.paper'));
        $t->same('<paper>fresh export</paper>', $invalid->get('library/site.paper'));
        $t->same(false, $invalid->pathExists('exports/site.paper.copytmp'));
        $t->same(['exports/site.paper', 'library/site.paper'], array_map(static fn ($listed) => $listed->path, $invalid->list()));
    },
    'dropbox skip exports hides copied paper exports from ordinary listings' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.paper', '<paper>fresh export</paper>', [
            'metadata' => ['wp-artifact' => 'paper-export'],
            'id' => 'id:source-paper',
        ]);
        $remote->put('exports/site.paper', '<paper>previous export</paper>');
        $remote->put('exports/readme.txt', 'plain text companion');

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.paper', 'exports/site.paper', [
            'provider' => 'dropbox',
            'temporarySuffix' => '.copytmp',
            'apiResult' => [
                'id' => 'id:hidden-paper-copy',
                'isDownloadable' => false,
                'skipExports' => true,
                'exportInfo' => [
                    'exportAs' => 'markdown',
                    'exportOptions' => ['html', 'markdown'],
                ],
            ],
        ]);

        $t->same('exports/site.paper', $result['copied']->path);
        $t->same('hidden', $result['copied']->metadata['dropbox_export_type']);
        $t->same(-1, $result['copied']->size);
        $t->same(['exports/readme.txt'], array_map(static fn ($info) => $info->path, $remote->list('exports')));
        $t->same(['exports/readme.txt', 'library/site.paper'], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'dropbox list-only exports remain listed but cannot be opened' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.paper', '<paper>fresh export</paper>', [
            'metadata' => ['wp-artifact' => 'paper-export'],
            'id' => 'id:source-paper',
        ]);

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.paper', 'exports/site.paper', [
            'provider' => 'dropbox',
            'apiResult' => [
                'id' => 'id:list-only-paper-copy',
                'isDownloadable' => false,
                'showAllExports' => true,
                'exportInfo' => [
                    'exportAs' => 'markdown',
                    'exportOptions' => ['html', 'markdown'],
                ],
            ],
        ]);

        $t->same('exports/site.paper', $result['copied']->path);
        $t->same('list-only', $remote->info('exports/site.paper')->metadata['dropbox_export_type']);
        $t->same(['exports/site.paper', 'library/site.paper'], array_map(static fn ($info) => $info->path, $remote->list()));
        $t->throws(RuntimeException::class, static fn () => $remote->get('exports/site.paper'));
        $t->throws(RuntimeException::class, static fn () => $remote->readObject('exports/site.paper'));
        $t->throws(RuntimeException::class, static fn () => $remote->openReader('exports/site.paper'));
        $t->same([], $remote->openLog());
    },
    'onedrive server side copy resets source modtime and add-only permission metadata' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(
            supportedHashes: new HashSet(HashType::SHA1, HashType::CRC32, HashType::QUICKXOR),
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
                    'quickXorHash' => 'fZ63/Cfr5wNPmPRzVwMIyoAHOLw=',
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
        $t->same('7d9eb7fc27ebe7034f98f473570308ca800738bc', $remote->hashes('exports/site.wxr', new HashSet(HashType::QUICKXOR))[HashType::QUICKXOR]);
        $t->same([
            'onedrive:async-copy-job',
            'onedrive:set-source-modtime',
            'onedrive:metadata-permissions-add-only',
        ], $result['metadataRefresh']);
    },
    'onedrive server side copy permission write errors honor failok' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>', [
            'modTime' => '2026-05-22T02:00:00Z',
            'metadata' => ['permissions' => '[{"roles":["read"]}]'],
        ]);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'provider' => 'onedrive',
                'apiResult' => ['permissionsWriteError' => 'failed to set permissions'],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('failed to process permissions: failed to set permissions', $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
            'provider' => 'onedrive',
            'apiResult' => [
                'id' => 'onedrive-copy-failok',
                'permissionsWriteError' => 'failed to set permissions',
                'permissionsFailOk' => true,
            ],
        ]);

        $t->same('onedrive-copy-failok', $result['copied']->id);
        $t->same('<rss>fresh export</rss>', $remote->get('exports/site.wxr'));
        $t->same([
            'onedrive:async-copy-job',
            'onedrive:set-source-modtime',
            'onedrive:metadata-permissions-add-only',
            'onedrive:metadata-permissions-failok',
        ], $result['metadataRefresh']);
    },
    'onedrive server side copy exposes remote item shared metadata' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(
            supportedHashes: new HashSet(HashType::SHA1, HashType::QUICKXOR),
            serverSideMove: true,
            serverSideCopy: true,
        );
        $remote->put('library/site.wxr', '<rss>fresh shared export</rss>', [
            'modTime' => '2026-05-22T02:00:00Z',
            'metadata' => ['wp-artifact' => 'shared-source-wxr'],
            'id' => 'source-shared-export',
        ]);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
            'provider' => 'onedrive',
            'apiResult' => [
                'remoteItem' => [
                    'id' => 'remote-copy-id',
                    'parentReference' => ['driveId' => 'shared-drive'],
                    'file' => [
                        'mimeType' => 'application/rss+xml',
                        'hashes' => [
                            'sha1Hash' => strtoupper(hash('sha1', '<rss>fresh shared export</rss>')),
                            'quickXorHash' => 'fZ63/Cfr5wNPmPRzVwMIyoAHOLw=',
                        ],
                    ],
                    'createdBy' => [
                        'user' => [
                            'id' => 'owner-user',
                            'displayName' => 'Site Owner',
                        ],
                    ],
                    'lastModifiedBy' => [
                        'user' => [
                            'id' => 'migration-bot',
                            'displayName' => 'Migration Bot',
                        ],
                    ],
                ],
                'shared' => [
                    'owner' => ['user' => ['id' => 'owner-account']],
                    'sharedBy' => ['user' => ['id' => 'reviewer-account']],
                    'scope' => 'users',
                    'sharedDateTime' => '2026-05-23T08:15:30Z',
                ],
            ],
        ]);

        $info = $remote->info('exports/site.wxr');
        $t->same('shared-drive#remote-copy-id', $result['copied']->id);
        $t->same('shared-drive#remote-copy-id', $info->metadata['id']);
        $t->same('application/rss+xml', $info->mimeType);
        $t->same('application/rss+xml', $info->metadata['content-type']);
        $t->same('owner-user', $info->metadata['created-by-id']);
        $t->same('Site Owner', $info->metadata['created-by-display-name']);
        $t->same('migration-bot', $info->metadata['last-modified-by-id']);
        $t->same('Migration Bot', $info->metadata['last-modified-by-display-name']);
        $t->same('owner-account', $info->metadata['shared-owner-id']);
        $t->same('reviewer-account', $info->metadata['shared-by-id']);
        $t->same('users', $info->metadata['shared-scope']);
        $t->same('2026-05-23T08:15:30Z', $info->metadata['shared-time']);
        $t->same(hash('sha1', '<rss>fresh shared export</rss>'), $remote->hashes('exports/site.wxr', new HashSet(HashType::SHA1))[HashType::SHA1]);
        $t->same('7d9eb7fc27ebe7034f98f473570308ca800738bc', $remote->hashes('exports/site.wxr', new HashSet(HashType::QUICKXOR))[HashType::QUICKXOR]);
        $t->same([
            'onedrive:async-copy-job',
            'onedrive:set-source-modtime',
            'onedrive:remoteitem-shared-metadata',
        ], $result['metadataRefresh']);
    },
    'onedrive remote item package metadata makes onenote copies non-readable' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site-notes.one', 'onenote package bytes', [
            'modTime' => '2026-05-22T02:00:00Z',
            'metadata' => ['wp-artifact' => 'migration-notes'],
            'id' => 'source-notes-package',
        ]);

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site-notes.one', 'exports/site-notes.one', [
            'provider' => 'onedrive',
            'apiResult' => [
                'remoteItem' => [
                    'id' => 'remote-notes-copy',
                    'parentReference' => ['driveId' => 'shared-drive'],
                    'package' => ['type' => 'oneNote'],
                ],
            ],
        ]);

        $info = $remote->info('exports/site-notes.one');
        $openError = null;
        try {
            $remote->openReader('exports/site-notes.one');
        } catch (RuntimeException $throwable) {
            $openError = $throwable->getMessage();
        }
        $updateError = null;
        try {
            $remote->updateObject('exports/site-notes.one', 'replacement bytes');
        } catch (RuntimeException $throwable) {
            $updateError = $throwable->getMessage();
        }

        $t->same('shared-drive#remote-notes-copy', $result['copied']->id);
        $t->same('oneNote', $info->metadata['package-type']);
        $t->same('shared-drive#remote-notes-copy', $info->metadata['id']);
        $t->same([
            'onedrive:async-copy-job',
            'onedrive:set-source-modtime',
            'onedrive:package-metadata',
            'onedrive:remoteitem-shared-metadata',
        ], $result['metadataRefresh']);
        $t->same('can\'t open a OneNote file', $openError);
        $t->same('can\'t upload content to a OneNote file', $updateError);
        $t->same(['exports/site-notes.one', 'library/site-notes.one'], array_map(static fn ($listed) => $listed->path, $remote->list()));
    },
    'onedrive server side copy rejects unsupported cross-drive pairs before remove existing' => static function (TestRunner $t): void {
        $cases = [
            [
                'sourceDriveType' => 'personal',
                'destinationDriveType' => 'business',
            ],
            [
                'sourceDriveType' => 'business',
                'destinationDriveType' => 'business',
                'sourceDriveId' => 'drive-a',
                'destinationDriveId' => 'drive-b',
            ],
        ];

        foreach ($cases as $case) {
            $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
            $remote->put('library/site.wxr', '<rss>fresh export</rss>');
            $remote->put('exports/site.wxr', '<rss>previous export</rss>');

            $error = null;
            try {
                (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                    'provider' => 'onedrive',
                    'temporarySuffix' => '.copytmp',
                    'apiResult' => $case,
                ]);
            } catch (RuntimeException $throwable) {
                $error = $throwable;
            }

            $t->same(MemoryProvider::ERROR_CANT_COPY, $error?->getMessage());
            $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
            $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
            $t->same(false, $remote->pathExists('exports/site.wxr.copytmp'));
            $t->same(['exports/site.wxr', 'library/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));
        }
    },
    'onedrive sharepoint document library copy gates match upstream drive type rules' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('staging/site.wxr', '<rss>fresh sharepoint export</rss>', [
            'modTime' => '2026-05-23T09:00:00Z',
            'metadata' => ['wp-artifact' => 'sharepoint-wxr'],
        ]);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $businessToSharePoint = (new SyncPlan())->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
            'provider' => 'onedrive',
            'temporarySuffix' => '.copytmp',
            'apiResult' => [
                'sourceDriveType' => 'business',
                'sourceDriveId' => 'business-drive-a',
                'destinationDriveType' => 'documentLibrary',
                'destinationDriveId' => 'sharepoint-library-b',
                'id' => 'copied-business-to-library',
            ],
        ]);

        $t->same('copied-business-to-library', $businessToSharePoint['copied']->id);
        $t->same('exports/site.wxr.copytmp', $businessToSharePoint['savedPath']);
        $t->same('<rss>fresh sharepoint export</rss>', $remote->get('exports/site.wxr'));
        $t->same(false, $remote->pathExists('exports/site.wxr.copytmp'));

        $remote->put('exports/site.wxr', '<rss>previous export</rss>');
        $sharePointToBusiness = (new SyncPlan())->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
            'provider' => 'onedrive',
            'temporarySuffix' => '.copytmp',
            'apiResult' => [
                'sourceDriveType' => 'sharepoint',
                'sourceDriveId' => 'sharepoint-library-a',
                'destinationDriveType' => 'business',
                'destinationDriveId' => 'business-drive-b',
                'id' => 'copied-library-to-business',
            ],
        ]);

        $t->same('copied-library-to-business', $sharePointToBusiness['copied']->id);
        $t->same('<rss>fresh sharepoint export</rss>', $remote->get('exports/site.wxr'));
        $t->same(['exports/site.wxr', 'staging/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));

        $blocked = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $blocked->put('staging/site.wxr', '<rss>fresh export</rss>');
        $blocked->put('exports/site.wxr', '<rss>previous export</rss>');
        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($blocked, 'staging/site.wxr', 'exports/site.wxr', [
                'provider' => 'onedrive',
                'temporarySuffix' => '.copytmp',
                'apiResult' => [
                    'sourceDriveType' => 'documentLibrary',
                    'destinationDriveType' => 'personal',
                    'sourceDriveId' => 'sharepoint-library',
                    'destinationDriveId' => 'personal-drive',
                ],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same(MemoryProvider::ERROR_CANT_COPY, $error?->getMessage());
        $t->same('<rss>previous export</rss>', $blocked->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $blocked->get('staging/site.wxr'));
        $t->same(false, $blocked->pathExists('exports/site.wxr.copytmp'));
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
    'yandex server side copy rejects non-file metadata reads and restores destination' => static function (TestRunner $t): void {
        $cases = [
            ['type' => 'dir', 'message' => 'is a directory not a file'],
            ['type' => 'unknown', 'message' => 'is not a regular file'],
        ];

        foreach ($cases as $case) {
            $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
            $remote->put('library/site.wxr', '<rss>fresh export</rss>');
            $remote->put('exports/site.wxr', '<rss>previous export</rss>');

            $error = null;
            try {
                (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                    'provider' => 'yandex',
                    'apiResult' => [
                        'resourceType' => $case['type'],
                        'modified' => '2026-05-22T03:04:05Z',
                    ],
                ]);
            } catch (RuntimeException $throwable) {
                $error = $throwable;
            }

            $t->same($case['message'], $error?->getMessage());
            $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
            $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
        }
    },
    'yandex server side copy rejects invalid copied modtime and restores destination' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>');
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'provider' => 'yandex',
                'apiResult' => [
                    'customProperties' => ['rclone_modified' => 'not-a-time'],
                    'modified' => '2026-05-22T03:04:05Z',
                ],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('failed to parse modtime from "not-a-time": cannot parse as RFC3339Nano', $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
    },
    'yandex set modtime writes rclone custom property and surfaces provider errors' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('exports/site.wxr', '<rss>portable export</rss>', [
            'modTime' => '2026-05-20T00:00:00Z',
            'metadata' => ['wp-artifact' => 'wxr'],
        ]);

        $updated = (new SyncPlan())->yandexSetRcloneModified($remote, 'exports/site.wxr', '2026-05-23T12:34:56.123456789Z');

        $t->same('exports/site.wxr', $updated->path);
        $t->same('2026-05-23T12:34:56.123456789Z', $remote->info('exports/site.wxr')->modTime);
        $t->same([
            'wp-artifact' => 'wxr',
            'rclone_modified' => '2026-05-23T12:34:56.123456789Z',
        ], $remote->info('exports/site.wxr')->metadata);

        $remote->setModTimeError('exports/site.wxr', 'custom properties are locked');
        $error = null;
        try {
            (new SyncPlan())->yandexSetRcloneModified($remote, 'exports/site.wxr', '2026-05-24T00:00:00Z');
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('failed to set custom property rclone_modified: custom properties are locked', $error?->getMessage());
        $t->same('2026-05-23T12:34:56.123456789Z', $remote->info('exports/site.wxr')->modTime);
        $t->same('2026-05-23T12:34:56.123456789Z', $remote->info('exports/site.wxr')->metadata['rclone_modified']);
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
    'dropbox server side copy wraps relocation api errors and restores destination' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $remote->put('library/site.wxr', '<rss>fresh export</rss>');
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'provider' => 'dropbox',
                'providerError' => [
                    'kind' => 'relocation-api',
                    'message' => 'too_many_write_operations',
                ],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('copy failed: too_many_write_operations', $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
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
    'onedrive shared personal copy access denial falls back to streamed copy' => static function (TestRunner $t): void {
        $source = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $destination = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
        $source->put('shared/site.wxr', '<rss>fresh shared export</rss>', [
            'modTime' => '2026-05-23T08:00:00Z',
            'mimeType' => 'application/rss+xml',
            'metadata' => ['wp-artifact' => 'shared-wxr'],
            'id' => 'source-shared-export',
        ]);
        $destination->put('exports/site.wxr', '<rss>previous export</rss>', [
            'metadata' => ['wp-artifact' => 'previous-wxr'],
        ]);

        $stats = null;
        $result = (new SyncPlan())->copyFileWithServerSideFallback(
            $destination,
            $source,
            'exports/site.wxr',
            'shared/site.wxr',
            [
                'provider' => 'onedrive',
                'temporarySuffix' => '.copytmp',
                'apiResult' => [
                    'sourceDriveType' => 'personal',
                    'destinationDriveType' => 'personal',
                    'sourceDriveId' => 'shared-drive',
                    'destinationDriveId' => 'site-owner-drive',
                ],
                'providerError' => ['kind' => 'async-access-denied'],
            ],
            [],
            $stats,
        );

        $t->same(false, $result['serverSide']);
        $t->same(true, $result['fallbackUsed']);
        $t->same(MemoryProvider::ERROR_CANT_COPY, $result['fallbackReason']);
        $t->same('exports/site.wxr', $result['copied']?->path);
        $t->same('exports/site.wxr', $result['manual']['copied']?->path);
        $t->same('<rss>fresh shared export</rss>', $destination->get('exports/site.wxr'));
        $t->same('2026-05-23T08:00:00Z', $destination->info('exports/site.wxr')->modTime);
        $t->same(['wp-artifact' => 'shared-wxr'], $destination->info('exports/site.wxr')->metadata);
        $t->same('<rss>fresh shared export</rss>', $source->get('shared/site.wxr'));
        $t->same(false, $destination->pathExists('exports/site.wxr.copytmp'));
        $t->same([
            'serverSideAttempted' => true,
            'serverSideSucceeded' => false,
            'fallbackUsed' => true,
            'fallbackReason' => MemoryProvider::ERROR_CANT_COPY,
            'manualCopiedPath' => 'exports/site.wxr',
        ], $stats);
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
    'onedrive server side copy maps async job error bodies and restores destination' => static function (TestRunner $t): void {
        $cases = [
            [
                'failure' => ['kind' => 'missing-location'],
                'message' => "didn't receive location header in copy response",
            ],
            [
                'failure' => ['kind' => 'async-status-not-json', 'body' => 'not-json', 'message' => 'invalid character'],
                'message' => 'async status result not JSON: "not-json": invalid character',
            ],
            [
                'failure' => ['kind' => 'async-status', 'status' => 'deleteFailed'],
                'message' => 'exports/site.wxr: async operation returned "deleteFailed"',
            ],
            [
                'failure' => ['kind' => 'async-metadata-read', 'message' => 'Object not found: exports/site.wxr'],
                'message' => 'async operation completed but readMetaData failed: Object not found: exports/site.wxr',
            ],
            [
                'failure' => ['kind' => 'async-timeout', 'duration' => '1m0s'],
                'message' => "async operation didn't complete after 1m0s",
            ],
        ];

        foreach ($cases as $case) {
            $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
            $remote->put('library/site.wxr', '<rss>fresh export</rss>');
            $remote->put('exports/site.wxr', '<rss>previous export</rss>');

            $error = null;
            try {
                (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                    'provider' => 'onedrive',
                    'providerError' => $case['failure'],
                ]);
            } catch (RuntimeException $throwable) {
                $error = $throwable;
            }

            $t->same($case['message'], $error?->getMessage());
            $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
            $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
        }
    },
    'yandex server side copy wraps async parse and timeout errors' => static function (TestRunner $t): void {
        $cases = [
            [
                'failure' => ['kind' => 'async-info-not-json', 'body' => 'accepted-not-json', 'message' => 'invalid character'],
                'message' => 'couldn\'t copy file: async info result not JSON: "accepted-not-json": invalid character',
            ],
            [
                'failure' => ['kind' => 'async-status-not-json', 'body' => 'status-not-json', 'message' => 'invalid character'],
                'message' => 'couldn\'t copy file: async status result not JSON: "status-not-json": invalid character',
            ],
            [
                'failure' => ['kind' => 'async-timeout', 'duration' => '30s'],
                'message' => "couldn't copy file: async operation didn't complete after 30s",
            ],
        ];

        foreach ($cases as $case) {
            $remote = new MemoryProvider(serverSideMove: true, serverSideCopy: true);
            $remote->put('library/site.wxr', '<rss>fresh export</rss>');
            $remote->put('exports/site.wxr', '<rss>previous export</rss>');

            $error = null;
            try {
                (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                    'provider' => 'yandex',
                    'providerError' => $case['failure'],
                ]);
            } catch (RuntimeException $throwable) {
                $error = $throwable;
            }

            $t->same($case['message'], $error?->getMessage());
            $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
            $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
        }
    },
    'sugarsync server side copy requires copied object id from location or metadata' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(
            supportedHashes: new HashSet(),
            serverSideMove: true,
            serverSideCopy: true,
        );
        $remote->put('library/site.wxr', '<rss>fresh export</rss>', [
            'modTime' => '2026-05-22T02:00:00Z',
            'id' => 'sugar-source',
        ]);
        $remote->put('exports/site.wxr', '<rss>previous export</rss>');

        $result = (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
            'provider' => 'sugarsync',
            'apiResult' => [
                'ref' => 'https://api.sugarsync.com/file/ref-from-metadata',
                'lastModified' => '2026-05-22T04:05:00Z',
            ],
        ]);
        $t->same('https://api.sugarsync.com/file/ref-from-metadata', $result['copied']->id);
        $t->same('2026-05-22T04:05:00Z', $remote->info('exports/site.wxr')->modTime);

        $remote->put('exports/site.wxr', '<rss>previous export</rss>');
        $error = null;
        try {
            (new SyncPlan())->serverSideCopyReplace($remote, 'library/site.wxr', 'exports/site.wxr', [
                'provider' => 'sugarsync',
                'apiResult' => [
                    'lastModified' => '2026-05-22T05:00:00Z',
                ],
            ]);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('no ID found in response', $error?->getMessage());
        $t->same('<rss>previous export</rss>', $remote->get('exports/site.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('library/site.wxr'));
    },
    'wordpress provider copy metadata example exposes onedrive quickxor and yandex md5 refreshes' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-provider-copy-metadata.php';

        $t->same('onedrive-copied-export', $example['onedriveCopiedId']);
        $t->same(hash('sha1', '<rss>fresh export</rss>'), $example['onedriveSha1']);
        $t->same('7d9eb7fc27ebe7034f98f473570308ca800738bc', $example['onedriveQuickXor']);
        $t->same('add-only', $example['onedrivePermissionMode']);
        $t->same(['onedrive:async-copy-job', 'onedrive:set-source-modtime', 'onedrive:metadata-permissions-add-only'], $example['onedriveMetadataRefresh']);
        $t->same('onedrive-copied-failok', $example['onedriveFailOkCopiedId']);
        $t->same([
            'onedrive:async-copy-job',
            'onedrive:set-source-modtime',
            'onedrive:metadata-permissions-add-only',
            'onedrive:metadata-permissions-failok',
        ], $example['onedriveFailOkMetadataRefresh']);
        $t->same('shared-drive#shared-export-copy', $example['onedriveSharedCopiedId']);
        $t->same([
            'onedrive:async-copy-job',
            'onedrive:set-source-modtime',
            'onedrive:metadata-permissions-add-only',
            'onedrive:remoteitem-shared-metadata',
        ], $example['onedriveSharedMetadataRefresh']);
        $t->same('application/rss+xml', $example['onedriveSharedMimeType']);
        $t->same('shared-drive#shared-export-copy', $example['onedriveSharedMetadata']['id']);
        $t->same('site-owner', $example['onedriveSharedMetadata']['created-by-id']);
        $t->same('Site Owner', $example['onedriveSharedMetadata']['created-by-display-name']);
        $t->same('migration-bot', $example['onedriveSharedMetadata']['last-modified-by-id']);
        $t->same('Migration Bot', $example['onedriveSharedMetadata']['last-modified-by-display-name']);
        $t->same('site-owner-account', $example['onedriveSharedMetadata']['shared-owner-id']);
        $t->same('reviewer-account', $example['onedriveSharedMetadata']['shared-by-id']);
        $t->same('users', $example['onedriveSharedMetadata']['shared-scope']);
        $t->same('2026-05-23T08:15:30Z', $example['onedriveSharedMetadata']['shared-time']);
        $t->same('shared-drive#shared-notes-copy', $example['onedrivePackageCopiedId']);
        $t->same('oneNote', $example['onedrivePackageMetadata']['package-type']);
        $t->same('shared-drive#shared-notes-copy', $example['onedrivePackageMetadata']['id']);
        $t->same([
            'onedrive:async-copy-job',
            'onedrive:set-source-modtime',
            'onedrive:package-metadata',
            'onedrive:remoteitem-shared-metadata',
        ], $example['onedrivePackageMetadataRefresh']);
        $t->same('can\'t open a OneNote file', $example['onedrivePackageOpenError']);
        $t->same('can\'t upload content to a OneNote file', $example['onedrivePackageUpdateError']);
        $t->same(hash('md5', '<rss>fresh export</rss>'), $example['yandexMd5']);
        $t->same(['yandex:new-object-metadata-read'], $example['yandexMetadataRefresh']);
        $t->same('exports/site.html', $example['dropboxPaperCopiedPath']);
        $t->same('exportable', $example['dropboxPaperExportType']);
        $t->same(-1, $example['dropboxPaperSize']);
        $t->same('exports/markdown.md', $example['dropboxMarkdownCopiedPath']);
        $t->same('markdown', $example['dropboxMarkdownExportFormat']);
        $t->same('md', $example['dropboxMarkdownExtension']);
        $t->same('hidden', $example['dropboxHiddenExportType']);
        $t->same(false, $example['dropboxHiddenListed']);
        $t->same(false, in_array('exports/hidden.paper', $example['dropboxExportsListing'], true));
        $t->same('exports/list-only.paper', $example['dropboxListOnlyCopiedPath']);
        $t->same('list-only', $example['dropboxListOnlyExportType']);
        $t->same(true, $example['dropboxListOnlyListed']);
        $t->same('Object not found: exports/list-only.paper', $example['dropboxListOnlyOpenError']);
        $t->same("dropbox: unknown export format 'pdf'", $example['dropboxUnknownFormatError']);
        $t->same('<paper>previous invalid export</paper>', $example['dropboxUnknownFormatPreserved']);
        $t->same(false, $example['dropboxUnknownFormatTempExists']);
        $t->same('2026-05-23T12:34:56Z', $example['yandexRcloneModified']);
        $t->same('failed to set custom property rclone_modified: custom properties are locked', $example['yandexSetModTimeError']);
        $t->same('failed to parse modtime from "not-a-time": cannot parse as RFC3339Nano', $example['yandexInvalidModTimeError']);
        $t->same('copy failed: too_many_write_operations', $example['dropboxCopyError']);
        $t->same(MemoryProvider::ERROR_CANT_COPY, $example['onedriveAccessDeniedError']);
        $t->same(MemoryProvider::ERROR_CANT_COPY, $example['onedriveCrossDriveError']);
        $t->same('sharepoint-wxr-copy', $example['onedriveSharePointCopiedId']);
        $t->same('<rss>fresh export</rss>', $example['onedriveSharePointBytes']);
        $t->same('exports/sharepoint-site.wxr.wpcopy', $example['onedriveSharePointSavedPath']);
        $t->same(false, $example['onedriveSharePointTempExists']);
        $t->same(MemoryProvider::ERROR_CANT_COPY, $example['onedriveSharePointPersonalError']);
        $t->same('<rss>previous personal export</rss>', $example['onedriveSharePointPersonalPreserved']);
        $t->same('<rss>previous export</rss>', $example['restoredAfterAccessDenied']);
        $t->same('async status result not JSON: "not-json": invalid character', $example['onedriveBadStatusError']);
        $t->same('no ID found in response', $example['sugarsyncMissingIdError']);
    },
    'wordpress onedrive shared copy fallback example streams wxr after server side denial' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-shared-copy-fallback.php';

        $t->same(true, $example['fallbackUsed']);
        $t->same(MemoryProvider::ERROR_CANT_COPY, $example['fallbackReason']);
        $t->same('exports/site.wxr', $example['copiedPath']);
        $t->same('<rss>fresh shared export</rss>', $example['copiedBytes']);
        $t->same('2026-05-23T08:00:00Z', $example['copiedModTime']);
        $t->same(['wp-artifact' => 'shared-wxr'], $example['copiedMetadata']);
        $t->same(true, $example['sourceStillAvailable']);
        $t->same(false, $example['temporaryObjectVisible']);
        $t->same([
            'serverSideAttempted' => true,
            'serverSideSucceeded' => false,
            'fallbackUsed' => true,
            'fallbackReason' => MemoryProvider::ERROR_CANT_COPY,
            'manualCopiedPath' => 'exports/site.wxr',
        ], $example['stats']);
    },
    'wordpress onedrive casefold copy guard example restores wxr export' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-casefold-copy-guard.php';

        $t->same('can\'t copy "exports/site.wxr" -> "EXPORTS/SITE.WXR" as are same name when lowercase', $example['caseFoldGuard']);
        $t->same('EXPORTS/SITE.WXR', $example['restoredPath']);
        $t->same('<rss>portable export</rss>', $example['restoredBytes']);
        $t->same(['wp-artifact' => 'published-export'], $example['restoredMetadata']);
        $t->same(false, $example['temporaryCopyVisible']);
        $t->same("server side copy: destination file exists already and can't rename", $example['removeExistingFirstError']);
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
    'wordpress metadata-set copy move example publishes handoff metadata' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-metadata-set-copy-move.php';

        $expectedMetadata = [
            'mtime' => '2004-03-03T04:05:06.499999999Z',
            'wp-artifact' => 'migration-handoff',
            'content-type' => 'application/rss+xml',
        ];

        $t->same('handoff/site.wxr', $example['copiedPath']);
        $t->same('2004-03-03T04:05:06.499999999Z', $example['copiedModTime']);
        $t->same('application/rss+xml', $example['copiedMimeType']);
        $t->same($expectedMetadata, $example['copiedMetadata']);
        $t->same([
            'mtime' => '2003-02-03T04:05:06.499999999Z',
            'wp-artifact' => 'draft-export',
        ], $example['sourceMetadata']);
        $t->same('wp-content/uploads/2026/05/hero.jpg', $example['movedPath']);
        $t->same($expectedMetadata, $example['movedMetadata']);
        $t->same(false, $example['temporaryUploadVisible']);
    },
    'wordpress backup-dir collision example preserves published media metadata' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-backup-dir-collision.php';

        $t->same('archive/publish/uploads/hero-previous.jpg', $example['backupPath']);
        $t->same('published hero bytes', $example['archivedBytes']);
        $t->same('2026-05-22T10:00:00Z', $example['archivedModTime']);
        $t->same(['wp-artifact' => 'published-media', 'alt' => 'homepage'], $example['archivedMetadata']);
        $t->same('fresh hero bytes', $example['publishedBytes']);
        $t->same(true, $example['staleArchiveReplaced']);
        $t->same(true, $example['sourcePreserved']);
    },
    'wordpress backup accounting noop example exposes safe preflight diagnostics' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-backup-accounting-noop.php';

        $t->same([
            'checkingTransfers' => 2,
            'renames' => 1,
            'deletedFiles' => 1,
            'deletedBytes' => strlen('stale hero archive'),
            'serverSideMoves' => 1,
        ], $example['backupAccounting']);
        $t->same(['Deleted', 'Moved (server-side) to: archive/publish/uploads/hero-previous.jpg'], $example['backupLogMessages']);
        $t->same(['missing-on-dst'], $example['backupLoggerTypes']);
        $t->same(true, $example['noopSkipped']);
        $t->same([
            'checkingTransfers' => 0,
            'renames' => 0,
            'deletedFiles' => 0,
            'deletedBytes' => 0,
            'serverSideMoves' => 0,
        ], $example['noopAccounting']);
        $t->same("don't need to copy/move publish/uploads/hero.jpg, it is already at target location", $example['noopLogMessage']);
        $t->same('match', $example['noopLoggerType']);
        $t->same('fresh hero bytes', $example['publishedBytes']);
    },
    'wordpress max transfer example preserves staged wxr after cutoff' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-copy-max-transfer.php';

        $t->same('restore/site-small.wxr', $example['smallCopiedPath']);
        $t->same('max transfer limit reached as set by --max-transfer', $example['hardError']);
        $t->same('max transfer limit reached as set by --max-transfer', $example['cautiousError']);
        $t->same('restore/site-large-soft.wxr', $example['softCopiedPath']);
        $t->same('max transfer limit reached as set by --max-transfer', $example['softAfterLimitError']);
        $t->same(false, $example['hardDestinationCreated']);
        $t->same(false, $example['cautiousDestinationCreated']);
        $t->same($example['stagedLargePreserved'], $example['softBytes']);
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
