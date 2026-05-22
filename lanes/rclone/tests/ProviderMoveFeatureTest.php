<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
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
