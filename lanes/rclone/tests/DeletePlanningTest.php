<?php

declare(strict_types=1);

use PortLibs\Rclone\DeleteMode;
use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
    'plans destination only deletions for upstream sync delete modes' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('potato2', str_repeat('-', 60));
        $source->put('empty space', '-');
        $target->put('potato', 'SMALLER BUT SAME DATE');
        $target->put('empty space', '-');

        $plan = new SyncPlan();
        $t->same(['potato'], $plan->deletePaths($source, $target));
        $t->same(['potato'], $plan->deletePaths($source, $target, null, DeleteMode::BEFORE));
        $t->same(['potato'], $plan->deletePaths($source, $target, null, DeleteMode::DURING));
        $t->same(['potato'], $plan->deletePaths($source, $target, null, DeleteMode::ONLY));
        $t->same([], $plan->deletePaths($source, $target, null, DeleteMode::OFF));

        $deleted = $plan->deleteDestinationOnly($source, $target, null, DeleteMode::AFTER);
        $t->same(['potato'], array_map(static fn ($info) => $info->path, $deleted));
        $t->throws(RuntimeException::class, static fn () => $target->get('potato'));
        $t->same(['empty space'], array_map(static fn ($info) => $info->path, $target->list()));
        $t->throws(InvalidArgumentException::class, static fn () => $plan->deletePaths($source, $target, null, 'sideways'));
    },
    'stops destination deletions after max delete threshold' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $target->put('a-small.txt', str_repeat('a', 10));
        $target->put('b-medium.txt', str_repeat('b', 60));
        $target->put('c-large.txt', str_repeat('c', 100));

        $error = null;
        try {
            (new SyncPlan())->deleteDestinationOnly(
                $source,
                $target,
                maxDelete: 2,
            );
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('--max-delete threshold reached', $error?->getMessage());
        $t->throws(RuntimeException::class, static fn () => $target->get('a-small.txt'));
        $t->throws(RuntimeException::class, static fn () => $target->get('b-medium.txt'));
        $t->same(str_repeat('c', 100), $target->get('c-large.txt'));
    },
    'stops destination deletions before exceeding max delete size' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $target->put('a-small.txt', str_repeat('a', 10));
        $target->put('b-medium.txt', str_repeat('b', 60));
        $target->put('c-unknown-size.txt', str_repeat('u', 100), ['unknownSize' => true]);
        $target->put('d-large.txt', str_repeat('d', 100));

        $error = null;
        try {
            (new SyncPlan())->deleteDestinationOnly(
                $source,
                $target,
                maxDeleteSize: 70,
            );
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('--max-delete-size threshold reached', $error?->getMessage());
        $t->throws(RuntimeException::class, static fn () => $target->get('a-small.txt'));
        $t->throws(RuntimeException::class, static fn () => $target->get('b-medium.txt'));
        $t->throws(RuntimeException::class, static fn () => $target->get('c-unknown-size.txt'));
        $t->same(str_repeat('d', 100), $target->get('d-large.txt'));

        $target = new MemoryProvider();
        $target->put('a-small.txt', str_repeat('a', 10));
        $target->put('b-medium.txt', str_repeat('b', 60));
        $target->put('c-large.txt', str_repeat('c', 100));
        $deleted = (new SyncPlan())->deleteDestinationOnly($source, $target, maxDeleteSize: 170);
        $t->same(['a-small.txt', 'b-medium.txt', 'c-large.txt'], array_map(static fn ($info) => $info->path, $deleted));
        $t->same([], $target->list());
    },
    'leaves excluded destination files unless delete excluded is enabled' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('wp-content/uploads/2026/05/hero.jpg', 'new image bytes');
        $source->put('wp-content/cache/page/index.html', '<html>current cache</html>');
        $target->put('wp-content/uploads/2026/05/hero.jpg', 'old image bytes');
        $target->put('wp-content/uploads/2025/12/old.jpg', 'stale upload');
        $target->put('wp-content/cache/page/index.html', '<html>old cache</html>');
        $target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '+ wp-content/uploads/**',
            '- *',
        ]);

        $plan = new SyncPlan();
        $t->same(['wp-content/uploads/2025/12/old.jpg'], $plan->deletePaths($source, $target, $filter));
        $t->same([
            'wp-content/cache/orphan.html',
            'wp-content/cache/page/index.html',
            'wp-content/uploads/2025/12/old.jpg',
        ], $plan->deletePaths($source, $target, $filter, DeleteMode::AFTER, true));
    },
    'copy planning does not delete destination only files' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('new.txt', 'new');
        $target->put('old.txt', 'old');

        $plan = new SyncPlan();
        $t->same(['new.txt'], array_map(static fn ($info) => $info->path, $plan->copyChanged($source, $target)));
        $t->same('old', $target->get('old.txt'));
        $t->same(['old.txt'], $plan->deletePaths($source, $target));
    },
    'ignore existing skips changed destination objects like upstream sync' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('existing', 'newpotatoes');
        $source->put('missing', 'fresh');
        $target->put('existing', 'potato');

        $copied = (new SyncPlan())->copyChanged($source, $target, ignoreExisting: true);

        $t->same(['missing'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('potato', $target->get('existing'));
        $t->same('fresh', $target->get('missing'));
    },
    'immutable copies new objects but refuses modified destination objects' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('archive/site-2026-05-22.wxr', '<rss>current</rss>');

        $copied = (new SyncPlan())->copyChanged($source, $target, immutable: true);
        $t->same(['archive/site-2026-05-22.wxr'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('<rss>current</rss>', $target->get('archive/site-2026-05-22.wxr'));

        $source->put('archive/site-2026-05-22.wxr', '<rss>rewritten</rss>');
        $error = null;
        try {
            (new SyncPlan())->copyChanged($source, $target, immutable: true);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('immutable file modified', $error?->getMessage());
        $t->same('<rss>current</rss>', $target->get('archive/site-2026-05-22.wxr'));
    },
    'no check dest transfers every source object without archiving overwritten targets' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('a.txt', 'same');
        $source->put('b.txt', 'new');
        $target->put('a.txt', 'same');
        $target->put('b.txt', 'old');

        $copied = (new SyncPlan())->copyChanged(
            $source,
            $target,
            backupPrefix: 'backup',
            noCheckDest: true,
        );

        $t->same(['a.txt', 'b.txt'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('same', $target->get('a.txt'));
        $t->same('new', $target->get('b.txt'));
        $t->throws(RuntimeException::class, static fn () => $target->get('backup/b.txt'));
    },
    'no traverse copy probes destination objects without matching directories' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->mkdir('empty-source-dir');
        $source->put('sub dir/hello world', 'hello world');
        $source->put('root.txt', 'fresh');
        $target->put('sub dir/hello world', 'hello world');
        $target->put('root.txt', 'stale');
        $target->put('orphan.txt', 'orphan');

        $stats = null;
        $copied = (new SyncPlan())->copyChanged($source, $target, noTraverse: true, noTraverseStats: $stats);

        $t->same(['root.txt'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('fresh', $target->get('root.txt'));
        $t->same('hello world', $target->get('sub dir/hello world'));
        $t->same('orphan', $target->get('orphan.txt'));
        $t->same(true, $stats['enabled']);
        $t->same(false, $stats['noCheckDest']);
        $t->same(false, $stats['targetListUsed']);
        $t->same(['root.txt', 'sub dir/hello world'], $stats['targetLookups']);
        $t->same(['root.txt', 'sub dir/hello world'], $stats['targetMatches']);
        $t->same([], $stats['targetMisses']);
        $t->same(['empty-source-dir', 'sub dir'], $stats['sourceOnlyDirectories']);
    },
    'no traverse no check dest skips destination object probes' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('a.txt', 'same');
        $source->put('b.txt', 'new');
        $target->put('a.txt', 'same');
        $target->put('b.txt', 'old');

        $stats = null;
        $copied = (new SyncPlan())->copyChanged(
            $source,
            $target,
            noCheckDest: true,
            noTraverse: true,
            noTraverseStats: $stats,
        );

        $t->same(['a.txt', 'b.txt'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('same', $target->get('a.txt'));
        $t->same('new', $target->get('b.txt'));
        $t->same(true, $stats['enabled']);
        $t->same(true, $stats['noCheckDest']);
        $t->same([], $stats['targetLookups']);
        $t->same([], $stats['targetMatches']);
        $t->same([], $stats['targetMisses']);
    },
    'no traverse wordpress backup copy probes only included artifact destinations' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        foreach ($tree as $path => $bytes) {
            $source->put($path, $bytes);
        }
        $target->put('exports/site.wxr', $tree['exports/site.wxr']);
        $target->put('wp-content/uploads/2026/05/hero.jpg', 'old image bytes');
        $target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $stats = null;
        $copied = (new SyncPlan())->copyChanged($source, $target, $filter, noTraverse: true, noTraverseStats: $stats);

        $t->same([
            'database/site.sql',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], $stats['targetLookups']);
        $t->same(['exports/site.wxr', 'wp-content/uploads/2026/05/hero.jpg'], $stats['targetMatches']);
        $t->same(['database/site.sql', 'wp-content/uploads/2026/05/hero.webp'], $stats['targetMisses']);
        $t->same(true, in_array('wp-content/uploads/2026/05', $stats['sourceOnlyDirectories'], true));
        $t->same(false, in_array('wp-content/cache', $stats['sourceOnlyDirectories'], true));
        $t->same('<html>stale cache</html>', $target->get('wp-content/cache/orphan.html'));
    },
    'ignore times transfers identical destination objects unconditionally' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('existing', 'potato', ['modTime' => '2026-05-22T12:00:00Z']);
        $target->put('existing', 'potato', ['modTime' => '2026-05-21T12:00:00Z']);

        $copied = (new SyncPlan())->copyChanged($source, $target, ignoreTimes: true);

        $t->same(['existing'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('potato', $target->get('existing'));
        $t->same('2026-05-22T12:00:00Z', $target->info('existing')->modTime);
    },
    'modtime only differences update destination timestamp without transfer' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('empty space', '-', ['modTime' => '2026-05-22T12:00:00Z']);
        $target->put('empty space', '-', ['modTime' => '2026-05-21T12:00:00Z']);

        $copied = (new SyncPlan())->copyChanged($source, $target);

        $t->same([], array_map(static fn ($info) => $info->path, $copied));
        $t->same('2026-05-22T12:00:00Z', $target->info('empty space')->modTime);

        $target->setModTime('empty space', '2026-05-20T12:00:00Z');
        $copied = (new SyncPlan())->copyChanged($source, $target, noUpdateModTime: true);

        $t->same([], array_map(static fn ($info) => $info->path, $copied));
        $t->same('2026-05-20T12:00:00Z', $target->info('empty space')->modTime);
    },
    'refresh times updates no-hash destination timestamps without transferring' => static function (TestRunner $t): void {
        $source = new MemoryProvider(false, new HashSet());
        $target = new MemoryProvider(false, new HashSet());
        $source->put('media.bin', 'abcdef', ['modTime' => '2026-05-22T12:00:00Z']);
        $target->put('media.bin', 'uvwxyz', ['modTime' => '2026-05-21T12:00:00Z']);

        $copied = (new SyncPlan())->copyChanged($source, $target);
        $t->same(['media.bin'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('abcdef', $target->get('media.bin'));
        $t->same('2026-05-22T12:00:00Z', $target->info('media.bin')->modTime);

        $target = new MemoryProvider(false, new HashSet());
        $target->put('media.bin', 'uvwxyz', ['modTime' => '2026-05-21T12:00:00Z']);

        $copied = (new SyncPlan())->copyChanged($source, $target, refreshTimes: true);
        $t->same([], array_map(static fn ($info) => $info->path, $copied));
        $t->same('uvwxyz', $target->get('media.bin'));
        $t->same('2026-05-22T12:00:00Z', $target->info('media.bin')->modTime);
    },
    'refresh times still transfers when a common hash differs' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('media.bin', 'abcdef', ['modTime' => '2026-05-22T12:00:00Z']);
        $target->put('media.bin', 'uvwxyz', ['modTime' => '2026-05-21T12:00:00Z']);

        $copied = (new SyncPlan())->copyChanged($source, $target, refreshTimes: true);

        $t->same(['media.bin'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('abcdef', $target->get('media.bin'));
        $t->same('2026-05-22T12:00:00Z', $target->info('media.bin')->modTime);
    },
    'update older skips newer destinations and checks older or near-equal files like upstream' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('one', 'one', ['modTime' => '2026-05-20T00:00:00Z']);
        $source->put('two', 'two', ['modTime' => '2026-05-22T00:00:00Z']);
        $source->put('three', 'three', ['modTime' => '2026-05-21T00:00:00Z']);
        $source->put('four', 'four', ['modTime' => '2026-05-21T00:00:00Z']);
        $source->put('five', 'five', ['modTime' => '2026-05-21T00:00:00Z']);

        $target->put('one', 'ONE', ['modTime' => '2026-05-21T00:00:00Z']);
        $target->put('two', 'TWO', ['modTime' => '2026-05-21T00:00:00Z']);
        $target->put('three', 'THREE', ['modTime' => '2026-05-21T00:00:00.500000Z']);
        $target->put('four', 'FOURFOUR', ['modTime' => '2026-05-21T00:00:00.500000Z']);

        $copied = (new SyncPlan())->copyChanged($source, $target, updateOlder: true, modifyWindowSeconds: 1);

        $t->same(['five', 'four', 'two'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('ONE', $target->get('one'));
        $t->same('two', $target->get('two'));
        $t->same('2026-05-22T00:00:00Z', $target->info('two')->modTime);
        $t->same('THREE', $target->get('three'));
        $t->same('four', $target->get('four'));
        $t->same('five', $target->get('five'));

        $source->put('three', 'three', ['modTime' => '2026-05-21T00:00:00Z']);
        $target->put('three', 'THREE', ['modTime' => '2026-05-21T00:00:00.500000Z']);

        $copied = (new SyncPlan())->copyChanged($source, $target, updateOlder: true, modifyWindowSeconds: 1, checksum: true);
        $t->same(['three'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('three', $target->get('three'));
    },
    'directory equality follows upstream DirsEqual modtime options' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->mkdirModTime('wp-content/uploads/2026/05', '2026-05-22T00:00:00Z');
        $target->mkdirModTime('wp-content/uploads/2026/05', '2026-05-22T00:00:00Z');

        $plan = new SyncPlan();
        $t->same(true, $plan->dirsEqual($source, $target, 'wp-content/uploads/2026/05'));

        $target->setDirectoryModTime('wp-content/uploads/2026/05', '2026-05-22T00:00:00.500000Z');
        $t->same(true, $plan->dirsEqual($source, $target, 'wp-content/uploads/2026/05', modifyWindowSeconds: 1));

        $target->setDirectoryModTime('wp-content/uploads/2026/05', '2026-05-23T00:00:00Z');
        $t->same(false, $plan->dirsEqual($source, $target, 'wp-content/uploads/2026/05'));
        $t->same(true, $plan->dirsEqual($source, $target, 'wp-content/uploads/2026/05', updateOlder: true));
        $t->same(false, $plan->dirsEqual($source, $target, 'wp-content/uploads/2026/05', ignoreTimes: true));
        $t->same(true, $plan->dirsEqual($source, $target, 'wp-content/uploads/2026/05', immutable: true));
        $t->same(true, $plan->dirsEqual($source, $target, 'wp-content/uploads/2026/05', setDirModTime: false, setDirMetadata: false));
        $t->same(false, $plan->dirsEqual($source, $target, 'wp-content/uploads/2026/06'));
    },
    'delayed directory modtime updates run deepest first after changed child objects' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();

        $source->mkdir('wp-content', [
            'modTime' => '2026-05-22T00:00:00Z',
            'metadata' => ['wp-scope' => 'content-root'],
        ]);
        $source->mkdir('wp-content/uploads', [
            'modTime' => '2026-05-22T00:01:00Z',
            'metadata' => ['wp-scope' => 'uploads-root'],
        ]);
        $source->mkdir('wp-content/uploads/2026', [
            'modTime' => '2026-05-22T00:02:00Z',
        ]);
        $source->mkdir('wp-content/uploads/2026/05', [
            'modTime' => '2026-05-22T00:03:00Z',
            'metadata' => ['wp-scope' => 'uploads-month'],
        ]);
        $source->mkdir('wp-content/cache', [
            'modTime' => '2026-05-22T00:04:00Z',
            'metadata' => ['wp-scope' => 'cache'],
        ]);
        $source->put('wp-content/uploads/2026/05/hero.jpg', 'new image bytes');

        foreach ([
            'wp-content',
            'wp-content/uploads',
            'wp-content/uploads/2026',
            'wp-content/uploads/2026/05',
            'wp-content/cache',
        ] as $dir) {
            $target->mkdir($dir, [
                'modTime' => '2026-05-20T00:00:00Z',
                'metadata' => ['wp-scope' => 'stale'],
            ]);
        }
        $target->put('wp-content/uploads/2026/05/hero.jpg', 'old image bytes');

        $plan = new SyncPlan();
        $copied = $plan->copyChanged($source, $target);
        $updated = $plan->setDelayedDirectoryModTimes(
            $source,
            $target,
            $copied,
            setDirMetadata: true,
        );

        $t->same(['wp-content/uploads/2026/05/hero.jpg'], array_map(static fn ($info) => $info->path, $copied));
        $t->same([
            'wp-content/uploads/2026/05',
            'wp-content/uploads/2026',
            'wp-content/uploads',
            'wp-content',
        ], array_map(static fn ($info) => $info->path, $updated));
        $t->same('2026-05-22T00:03:00Z', $target->directoryInfo('wp-content/uploads/2026/05')->modTime);
        $t->same([
            'wp-scope' => 'uploads-month',
            'mtime' => '2026-05-22T00:03:00Z',
        ], $target->directoryInfo('wp-content/uploads/2026/05')->metadata);
        $t->same([
            'wp-scope' => 'content-root',
            'mtime' => '2026-05-22T00:00:00Z',
        ], $target->directoryInfo('wp-content')->metadata);
        $t->same('2026-05-20T00:00:00Z', $target->directoryInfo('wp-content/cache')->modTime);
        $t->same(['wp-scope' => 'stale'], $target->directoryInfo('wp-content/cache')->metadata);
    },
    'delayed directory modtimes skip empty dirs unless copy empty source dirs is enabled' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->mkdirModTime('exports/incremental/empty', '2026-05-22T01:00:00Z');

        $plan = new SyncPlan();
        $t->same([], $plan->setDelayedDirectoryModTimes($source, $target, ['exports/incremental/empty']));
        $t->throws(RuntimeException::class, static fn () => $target->directoryInfo('exports/incremental/empty'));

        $updated = $plan->setDelayedDirectoryModTimes(
            $source,
            $target,
            ['exports/incremental/empty'],
            copyEmptySourceDirs: true,
        );

        $t->same([
            'exports/incremental/empty',
            'exports/incremental',
            'exports',
        ], array_map(static fn ($info) => $info->path, $updated));
        $t->same('2026-05-22T01:00:00Z', $target->directoryInfo('exports/incremental/empty')->modTime);
        $t->same(null, $target->directoryInfo('exports/incremental')->modTime);
        $t->same([], $plan->setDelayedDirectoryModTimes(
            $source,
            $target,
            ['exports/incremental/empty'],
            copyEmptySourceDirs: true,
            noUpdateDirModTime: true,
        ));
    },
    'compare dest skips copies when an upstream reference matches source bytes' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $compare = new MemoryProvider();
        $source->put('one', 'onet2');
        $source->put('two', 'two');
        $source->put('three', 'threet3');
        $target->put('one', 'one');
        $compare->put('one', 'onet2');
        $compare->put('two', 'two');
        $compare->put('three', 'three');

        $copied = (new SyncPlan())->copyChanged($source, $target, compareDest: [$compare]);

        $t->same(['three'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('one', $target->get('one'));
        $t->throws(RuntimeException::class, static fn () => $target->get('two'));
        $t->same('threet3', $target->get('three'));
    },
    'multiple compare dest references are checked in upstream order' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $first = new MemoryProvider();
        $second = new MemoryProvider();
        $source->put('a-one', 'one');
        $source->put('b-two', 'two');
        $source->put('c-three', 'three');
        $first->put('a-one', 'one');
        $first->put('b-two', 'stale');
        $second->put('b-two', 'two');

        $copied = (new SyncPlan())->copyChanged($source, $target, compareDest: [$first, $second]);

        $t->same(['c-three'], array_map(static fn ($info) => $info->path, $copied));
        $t->throws(RuntimeException::class, static fn () => $target->get('a-one'));
        $t->throws(RuntimeException::class, static fn () => $target->get('b-two'));
        $t->same('three', $target->get('c-three'));
    },
    'copy dest uses matching upstream reference and archives overwritten targets' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $copyDest = new MemoryProvider();
        $source->put('a-one', 'onet2');
        $source->put('b-two', 'two');
        $source->put('c-three', 'threet3');
        $target->put('a-one', 'one');
        $copyDest->put('a-one', 'onet2');
        $copyDest->put('b-two', 'two');
        $copyDest->put('c-three', 'three');

        $copied = (new SyncPlan())->copyChanged(
            $source,
            $target,
            backupPrefix: 'BackupDir',
            copyDest: [$copyDest],
        );

        $t->same(['a-one', 'b-two', 'c-three'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('one', $target->get('BackupDir/a-one'));
        $t->same('onet2', $target->get('a-one'));
        $t->same('two', $target->get('b-two'));
        $t->same('threet3', $target->get('c-three'));
    },
    'validates backup dir roots like upstream BackupDir' => static function (TestRunner $t): void {
        $t->same('remote:backup', SyncPlan::resolveBackupRoot('remote:dst', 'remote:src', 'remote:backup'));
        $t->same('remote:dst', SyncPlan::resolveBackupRoot('remote:dst', 'remote:src', suffix: '.bak'));
        $t->same('remote:dst', SyncPlan::resolveBackupRoot('remote:dst', 'remote:src', 'remote:dst', 'one', '.bak'));

        foreach ([
            ['other:backup', '', true, 'parameter to --backup-dir has to be on the same remote as destination'],
            ['remote:dst/archive', '', true, "destination and parameter to --backup-dir mustn't overlap"],
            ['remote:src/archive', '', true, "source and parameter to --backup-dir mustn't overlap"],
            ['remote:dst', 'one', true, "destination and parameter to --backup-dir mustn't be the same"],
            ['remote:backup', '', false, "can't use --backup-dir on a remote which doesn't support server-side move or copy"],
            ['', '', true, 'internal error: BackupDir called when --backup-dir and --suffix both empty'],
        ] as [$backupRoot, $sourceFileName, $supportsMove, $message]) {
            $error = null;
            try {
                SyncPlan::resolveBackupRoot('remote:dst', 'remote:src', $backupRoot, $sourceFileName, '', $supportsMove);
            } catch (RuntimeException $throwable) {
                $error = $throwable->getMessage();
            }
            $t->same($message, $error);
        }
    },
    'moves overwritten and deleted destination files into backup dir like upstream sync' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('one', 'oneA');
        $source->put('two', 'two');
        $target->put('one', 'one');
        $target->put('two', 'two');
        $target->put('three.txt', 'three');

        $plan = new SyncPlan();
        $copied = $plan->copyChanged($source, $target, null, backupPrefix: 'backup');
        $moved = $plan->deleteDestinationOnly($source, $target, backupPrefix: 'backup');

        $t->same(['one'], array_map(static fn ($info) => $info->path, $copied));
        $t->same(['backup/three.txt'], array_map(static fn ($info) => $info->path, $moved));
        $t->same('oneA', $target->get('one'));
        $t->same('two', $target->get('two'));
        $t->same('one', $target->get('backup/one'));
        $t->same('three', $target->get('backup/three.txt'));
        $t->throws(RuntimeException::class, static fn () => $target->get('three.txt'));

        $source->put('one', 'oneBB');
        $target->put('three.txt', 'threeA');
        $plan->copyChanged($source, $target, null, backupPrefix: 'backup');
        $plan->deleteDestinationOnly($source, $target, backupPrefix: 'backup');

        $t->same('oneBB', $target->get('one'));
        $t->same('oneA', $target->get('backup/one'));
        $t->same('threeA', $target->get('backup/three.txt'));
    },
    'adds backup suffix before extensions when suffix keep extension is set' => static function (TestRunner $t): void {
        $t->same('backup/three.txt.bak', SyncPlan::backupPath('three.txt', 'backup', '.bak'));
        $t->same('backup/three-2019-01-01.txt', SyncPlan::backupPath('three.txt', 'backup', '-2019-01-01', true));
        $t->same('backup/file-2019-01-01.tar.gz', SyncPlan::backupPath('file.tar.gz', 'backup', '-2019-01-01', true));
        $t->same('backup/file.badextension-2019-01-01.gz', SyncPlan::backupPath('file.badextension.gz', 'backup', '-2019-01-01', true));
    },
    'uses destination as backup dir when only suffix is configured' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('one', 'oneA');
        $source->put('two', 'two');
        $target->put('one', 'one');
        $target->put('two', 'two');
        $target->put('three.txt', 'three');

        $filter = FilterRuleSet::fromRules([
            '- *.bak',
            '+ *',
        ]);

        $plan = new SyncPlan();
        $plan->copyChanged($source, $target, $filter, suffix: '.bak');
        $plan->deleteDestinationOnly($source, $target, $filter, suffix: '.bak');

        $t->same('oneA', $target->get('one'));
        $t->same('one', $target->get('one.bak'));
        $t->same('three', $target->get('three.txt.bak'));
        $t->same([], $plan->deletePaths($source, $target, $filter));
    },
    'prunes stale wordpress backup artifacts after a filtered sync' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        foreach ($tree as $path => $bytes) {
            $source->put($path, $bytes);
        }

        $target->put('wp-content/uploads/2026/05/hero.jpg', 'old image bytes');
        $target->put('wp-content/uploads/2024/01/obsolete.jpg', 'obsolete image bytes');
        $target->put('exports/old-site.wxr', '<rss>old</rss>');
        $target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $plan = new SyncPlan();
        $plan->copyChanged($source, $target, $filter);
        $deleted = $plan->deleteDestinationOnly($source, $target, $filter);

        $t->same([
            'exports/old-site.wxr',
            'wp-content/uploads/2024/01/obsolete.jpg',
        ], array_map(static fn ($info) => $info->path, $deleted));
        $t->throws(RuntimeException::class, static fn () => $target->get('exports/old-site.wxr'));
        $t->throws(RuntimeException::class, static fn () => $target->get('wp-content/uploads/2024/01/obsolete.jpg'));
        $t->same('<html>stale cache</html>', $target->get('wp-content/cache/orphan.html'));
        $t->same([], $plan->changedPaths($source, $target, $filter));
        $t->same([], $plan->deletePaths($source, $target, $filter));
    },
    'max delete protects wordpress backup pruning' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        foreach (require __DIR__ . '/../fixtures/wordpress-backup-tree.php' as $path => $bytes) {
            $source->put($path, $bytes);
        }

        $target->put('exports/old-site.wxr', '<rss>old</rss>');
        $target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');
        $target->put('wp-content/uploads/2024/01/obsolete.jpg', 'obsolete image bytes');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $plan = new SyncPlan();
        $error = null;
        try {
            $plan->deleteDestinationOnly($source, $target, $filter, maxDelete: 1);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('--max-delete threshold reached', $error?->getMessage());
        $t->throws(RuntimeException::class, static fn () => $target->get('exports/old-site.wxr'));
        $t->same('obsolete image bytes', $target->get('wp-content/uploads/2024/01/obsolete.jpg'));
        $t->same('<html>stale cache</html>', $target->get('wp-content/cache/orphan.html'));
    },
    'moves stale wordpress backup artifacts into backup dir before pruning' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        foreach (require __DIR__ . '/../fixtures/wordpress-backup-tree.php' as $path => $bytes) {
            $source->put($path, $bytes);
        }

        $target->put('exports/old-site.wxr', '<rss>old</rss>');
        $target->put('wp-content/uploads/2024/01/obsolete.jpg', 'obsolete image bytes');
        $target->put('wp-content/uploads/2026/05/hero.jpg', 'previous published hero');
        $target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $plan = new SyncPlan();
        $copied = $plan->copyChanged(
            $source,
            $target,
            $filter,
            backupPrefix: 'archive/2026-05-22',
            suffix: '-previous',
            suffixKeepExtension: true,
        );
        $error = null;
        try {
            $plan->deleteDestinationOnly(
                $source,
                $target,
                $filter,
                maxDelete: 1,
                backupPrefix: 'archive/2026-05-22',
                suffix: '-previous',
                suffixKeepExtension: true,
            );
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same('--max-delete threshold reached', $error?->getMessage());
        $t->same('previous published hero', $target->get('archive/2026-05-22/wp-content/uploads/2026/05/hero-previous.jpg'));
        $t->same('<rss>old</rss>', $target->get('archive/2026-05-22/exports/old-site-previous.wxr'));
        $t->same('obsolete image bytes', $target->get('wp-content/uploads/2024/01/obsolete.jpg'));
        $t->same('<html>stale cache</html>', $target->get('wp-content/cache/orphan.html'));
    },
    'copy dest mirror hydrates wordpress backups while preserving backup dir archives' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $copyDest = new MemoryProvider();
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        foreach ($tree as $path => $bytes) {
            $source->put($path, $bytes);
            if (str_starts_with($path, 'wp-content/cache/') || str_ends_with($path, '.log') || str_ends_with($path, '.psd')) {
                continue;
            }
            $copyDest->put($path, $bytes);
        }

        $target->put('wp-content/uploads/2026/05/hero.jpg', 'previous hero bytes');
        $target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $copied = (new SyncPlan())->copyChanged(
            $source,
            $target,
            $filter,
            backupPrefix: 'archive/2026-05-22',
            copyDest: [$copyDest],
        );

        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same('previous hero bytes', $target->get('archive/2026-05-22/wp-content/uploads/2026/05/hero.jpg'));
        $t->same($tree['wp-content/uploads/2026/05/hero.jpg'], $target->get('wp-content/uploads/2026/05/hero.jpg'));
        $t->same($tree['database/site.sql'], $target->get('database/site.sql'));
        $t->same('<html>stale cache</html>', $target->get('wp-content/cache/orphan.html'));
    },
    'immutable wordpress archive sync preserves existing backup artifacts' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('exports/site-2026-05-22.wxr', '<rss version="2.0"></rss>');
        $source->put('database/site-2026-05-22.sql', 'insert into wp_posts values (...)');
        $target->put('exports/site-2026-05-22.wxr', '<rss version="2.0"></rss>');

        $copied = (new SyncPlan())->copyChanged($source, $target, immutable: true);
        $t->same(['database/site-2026-05-22.sql'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('<rss version="2.0"></rss>', $target->get('exports/site-2026-05-22.wxr'));
        $t->same('insert into wp_posts values (...)', $target->get('database/site-2026-05-22.sql'));

        $source->put('exports/site-2026-05-22.wxr', '<rss>rewritten archive</rss>');
        $error = null;
        try {
            (new SyncPlan())->copyChanged($source, $target, immutable: true);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('immutable file modified', $error?->getMessage());
        $t->same('<rss version="2.0"></rss>', $target->get('exports/site-2026-05-22.wxr'));
    },
    'update older wordpress archive sync preserves newer remote artifacts' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('database/site.sql', 'fresh sql dump', ['modTime' => '2026-05-22T00:00:00Z']);
        $source->put('exports/site.wxr', '<rss>source export</rss>', ['modTime' => '2026-05-21T00:00:00Z']);
        $source->put('wp-content/uploads/2026/05/hero.jpg', 'image-A', ['modTime' => '2026-05-22T00:00:00Z']);

        $target->put('database/site.sql', 'stale sql dump', ['modTime' => '2026-05-21T00:00:00Z']);
        $target->put('exports/site.wxr', '<rss>remote recovery export</rss>', ['modTime' => '2026-05-23T00:00:00Z']);
        $target->put('wp-content/uploads/2026/05/hero.jpg', 'image-B', ['modTime' => '2026-05-22T00:00:00.500000Z']);
        $target->put('wp-content/cache/orphan.html', '<html>cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $copied = (new SyncPlan())->copyChanged(
            $source,
            $target,
            $filter,
            updateOlder: true,
            modifyWindowSeconds: 1,
            checksum: true,
        );

        $t->same([
            'database/site.sql',
            'wp-content/uploads/2026/05/hero.jpg',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same('fresh sql dump', $target->get('database/site.sql'));
        $t->same('<rss>remote recovery export</rss>', $target->get('exports/site.wxr'));
        $t->same('image-A', $target->get('wp-content/uploads/2026/05/hero.jpg'));
        $t->same('<html>cache</html>', $target->get('wp-content/cache/orphan.html'));
    },
    'refresh times wordpress no-hash archive sync repairs timestamps without replacing artifacts' => static function (TestRunner $t): void {
        $source = new MemoryProvider(false, new HashSet());
        $target = new MemoryProvider(false, new HashSet());
        $source->put('exports/site.wxr', '<rss>portable export</rss>', ['modTime' => '2026-05-22T00:00:00Z']);
        $source->put('database/site.sql', 'insert into wp_posts values (...)', ['modTime' => '2026-05-22T00:00:00Z']);
        $source->put('wp-content/uploads/2026/05/hero.jpg', 'new image bytes', ['modTime' => '2026-05-22T00:00:00Z']);

        $target->put('exports/site.wxr', '<rss>portable export</rss>', ['modTime' => '2026-05-20T00:00:00Z']);
        $target->put('database/site.sql', 'insert into wp_posts values (...)', ['modTime' => '2026-05-20T00:00:00Z']);
        $target->put('wp-content/cache/orphan.html', '<html>cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $copied = (new SyncPlan())->copyChanged($source, $target, $filter, refreshTimes: true);

        $t->same(['wp-content/uploads/2026/05/hero.jpg'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('<rss>portable export</rss>', $target->get('exports/site.wxr'));
        $t->same('2026-05-22T00:00:00Z', $target->info('exports/site.wxr')->modTime);
        $t->same('insert into wp_posts values (...)', $target->get('database/site.sql'));
        $t->same('2026-05-22T00:00:00Z', $target->info('database/site.sql')->modTime);
        $t->same('<html>cache</html>', $target->get('wp-content/cache/orphan.html'));
    },
];
