<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
    'touch command creates missing empty object with timestamp and metadata' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();

        $stats = null;
        $result = (new SyncPlan())->touchCommand(
            $provider,
            'a/b/c.txt',
            [
                'timestamp' => '060102',
                'metadataSet' => ['testkey' => 'testvalue'],
            ],
            $stats,
        );

        $t->same('a/b/c.txt', $result['created']?->path);
        $t->same('', $provider->get('a/b/c.txt'));
        $t->same('2006-01-02T00:00:00Z', $provider->info('a/b/c.txt')->modTime);
        $t->same(['testkey' => 'testvalue'], $provider->info('a/b/c.txt')->metadata);
        $t->same(['a', 'a/b'], array_map(static fn ($info): string => $info->path, $provider->directories()));
        $t->same(1, $stats['created']);
        $t->same(0, $stats['touched']);
    },
    'touch no create and recursive skip missing paths without creating files' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $plan = new SyncPlan();

        $noCreateStats = null;
        $noCreate = $plan->touchCommand($provider, 'missing.txt', [
            'timestamp' => '121212',
            'noCreate' => true,
        ], $noCreateStats);
        $recursiveStats = null;
        $recursive = $plan->touchCommand($provider, 'missing-recursive', [
            'timestamp' => '2011-12-25T12:59:59',
            'recursive' => true,
        ], $recursiveStats);

        $t->same(true, $noCreate['skipped']);
        $t->same(true, $recursive['skipped']);
        $t->same(1, $noCreateStats['notCreated']);
        $t->same(1, $recursiveStats['notCreated']);
        $t->throws(RuntimeException::class, static fn () => $provider->info('missing.txt'));
        $t->throws(RuntimeException::class, static fn () => $provider->info('missing-recursive'));
    },
    'touch updates existing file despite no create and preserves bytes' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('a', 'aaa', ['modTime' => '2001-02-03T04:05:06Z']);

        $stats = null;
        $result = (new SyncPlan())->touchCommand($provider, 'a', [
            'timestamp' => '2011-12-25T12:59:59.123456789',
            'noCreate' => true,
        ], $stats);

        $t->same(['a'], array_map(static fn ($info): string => $info->path, $result['touched']));
        $t->same('aaa', $provider->get('a'));
        $t->same('2011-12-25T12:59:59.123456789Z', $provider->info('a')->modTime);
        $t->same(1, $stats['touched']);
        $t->same(0, $stats['created']);
    },
    'touch command reports timestamp parse failures' => static function (TestRunner $t): void {
        $error = null;
        try {
            (new SyncPlan())->touchCommand(new MemoryProvider(), 'bad', ['timestamp' => '2011-99-99T99:00:00']);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->contains('failed to parse timestamp argument', $error?->getMessage() ?? '');
    },
    'touch directory nonrecursive updates only direct child files' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('a/b/c');
        $provider->put('a/f1', '111', ['modTime' => '2001-02-03T04:05:06Z']);
        $provider->put('a/b/f2', '222', ['modTime' => '2001-02-03T04:05:06Z']);
        $provider->put('a/b/c/f3', '333', ['modTime' => '2001-02-03T04:05:06Z']);

        $stats = null;
        $result = (new SyncPlan())->touchCommand($provider, 'a', [
            'timestamp' => '2010-09-08T07:06:05',
        ], $stats);

        $t->same(true, $result['directory']);
        $t->same(['a/f1'], array_map(static fn ($info): string => $info->path, $result['touched']));
        $t->same('2010-09-08T07:06:05Z', $provider->info('a/f1')->modTime);
        $t->same('2001-02-03T04:05:06Z', $provider->info('a/b/f2')->modTime);
        $t->same('2001-02-03T04:05:06Z', $provider->info('a/b/c/f3')->modTime);
        $t->same(1, $stats['listed']);
        $t->same(1, $stats['touched']);
    },
    'touch recursive applies filters and dry run skip accounting' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('wp-content/uploads/2026/05/hero.jpg', 'hero', ['modTime' => '2026-05-20T00:00:00Z']);
        $provider->put('wp-content/uploads/2026/05/gallery.jpg', 'gallery', ['modTime' => '2026-05-20T00:00:00Z']);
        $provider->put('wp-content/cache/page.html', 'cache', ['modTime' => '2026-05-20T00:00:00Z']);
        $filter = FilterRuleSet::fromRules([
            '+ wp-content/uploads/**',
            '- *',
        ]);

        $plan = new SyncPlan();
        $dryRunStats = null;
        $dryRun = $plan->touchCommand($provider, 'wp-content', [
            'timestamp' => '2026-05-23T14:00:00',
            'recursive' => true,
            'dryRun' => true,
            'filter' => $filter,
        ], $dryRunStats);

        $t->same([], $dryRun['touched']);
        $t->same(2, $dryRunStats['listed']);
        $t->same(2, $dryRunStats['dryRunSkipped']);
        $t->same('2026-05-20T00:00:00Z', $provider->info('wp-content/uploads/2026/05/hero.jpg')->modTime);

        $applyStats = null;
        $applied = $plan->touchCommand($provider, 'wp-content', [
            'timestamp' => '2026-05-23T14:00:00',
            'recursive' => true,
            'filter' => $filter,
        ], $applyStats);

        $t->same([
            'wp-content/uploads/2026/05/gallery.jpg',
            'wp-content/uploads/2026/05/hero.jpg',
        ], array_map(static fn ($info): string => $info->path, $applied['touched']));
        $t->same('2026-05-23T14:00:00Z', $provider->info('wp-content/uploads/2026/05/gallery.jpg')->modTime);
        $t->same('2026-05-23T14:00:00Z', $provider->info('wp-content/uploads/2026/05/hero.jpg')->modTime);
        $t->same('2026-05-20T00:00:00Z', $provider->info('wp-content/cache/page.html')->modTime);
        $t->same(2, $applyStats['touched']);
    },
    'touch directory records set modtime errors without aborting later files' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('a/bad.txt', 'bad', ['modTime' => '2026-05-20T00:00:00Z']);
        $provider->put('a/good.txt', 'good', ['modTime' => '2026-05-20T00:00:00Z']);
        $provider->setModTimeError('a/bad.txt', "can't set modtime");

        $stats = null;
        $result = (new SyncPlan())->touchCommand($provider, 'a', [
            'timestamp' => '2026-05-23T15:00:00',
            'recursive' => true,
        ], $stats);

        $t->same(['a/good.txt'], array_map(static fn ($info): string => $info->path, $result['touched']));
        $t->same('2026-05-20T00:00:00Z', $provider->info('a/bad.txt')->modTime);
        $t->same('2026-05-23T15:00:00Z', $provider->info('a/good.txt')->modTime);
        $t->same(2, $stats['listed']);
        $t->same(1, $stats['touched']);
        $t->same(1, $stats['errors']);
        $t->same("failed to touch: can't set modtime", $stats['lastError']);
    },
    'touch single file set modtime errors are returned' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('locked.txt', 'locked', ['modTime' => '2026-05-20T00:00:00Z']);
        $provider->setModTimeError('locked.txt', 'remote object is immutable');

        $stats = null;
        $error = null;
        try {
            (new SyncPlan())->touchCommand($provider, 'locked.txt', [
                'timestamp' => '2026-05-23T16:00:00',
            ], $stats);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('failed to touch: remote object is immutable', $error?->getMessage());
        $t->same('2026-05-20T00:00:00Z', $provider->info('locked.txt')->modTime);
        $t->same(1, $stats['errors']);
    },
    'wordpress touch media timestamp repair example updates uploads only' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-touch-media-timestamps.php';

        $t->same([], $example['dryRunTouched']);
        $t->same(2, $example['dryRunStats']['dryRunSkipped']);
        $t->same([
            'wp-content/uploads/2026/05/gallery.jpg',
            'wp-content/uploads/2026/05/hero.jpg',
        ], $example['touchedUploads']);
        $t->same('2026-05-23T14:30:00Z', $example['heroModTime']);
        $t->same('2026-05-23T14:30:00Z', $example['galleryModTime']);
        $t->same('2026-05-20T00:00:00Z', $example['wxrModTime']);
        $t->same('2026-05-20T00:00:00Z', $example['cacheModTime']);
        $t->same(true, $example['missingRecursiveSkipped']);
    },
];
