<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
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
