<?php

declare(strict_types=1);

use PortLibs\Rclone\DeduplicateMode;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
    'deduplicate mode parser accepts upstream command mode strings' => static function (TestRunner $t): void {
        $t->same(DeduplicateMode::INTERACTIVE, DeduplicateMode::normalize('interactive'));
        $t->same(DeduplicateMode::SKIP, DeduplicateMode::normalize('SKIP'));
        $t->same(DeduplicateMode::FIRST, DeduplicateMode::normalize('first'));
        $t->same(DeduplicateMode::NEWEST, DeduplicateMode::normalize('newest'));
        $t->same(DeduplicateMode::OLDEST, DeduplicateMode::normalize('oldest'));
        $t->same(DeduplicateMode::RENAME, DeduplicateMode::normalize('rename'));
        $t->same(DeduplicateMode::LARGEST, DeduplicateMode::normalize('largest'));
        $t->same(DeduplicateMode::SMALLEST, DeduplicateMode::normalize('smallest'));
        $t->same(DeduplicateMode::LIST, DeduplicateMode::normalize('list'));
        $t->throws(InvalidArgumentException::class, static fn () => DeduplicateMode::normalize('sideways'));
    },
    'dedupe by hash newest keeps newest duplicate content like upstream' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('exports/site-copy-a.wxr', '<rss>same export</rss>', ['modTime' => '2026-05-20T00:00:00Z']);
        $remote->put('exports/site-copy-b.wxr', '<rss>same export</rss>', ['modTime' => '2026-05-22T00:00:00Z']);
        $remote->put('exports/site-copy-c.wxr', '<rss>same export</rss>', ['modTime' => '2026-05-21T00:00:00Z']);
        $remote->put('database/site.sql', 'insert into wp_posts values (...)', ['modTime' => '2026-05-22T01:00:00Z']);

        $result = (new SyncPlan())->deduplicateByHash($remote, DeduplicateMode::NEWEST);

        $t->same('md5', $result['hashType']);
        $t->same(1, count($result['groups']));
        $t->same('exports/site-copy-b.wxr', $result['groups'][0]['kept']?->path);
        $t->same([
            'exports/site-copy-a.wxr',
            'exports/site-copy-c.wxr',
        ], array_map(static fn ($info) => $info->path, $result['groups'][0]['deleted']));
        $t->same([
            'database/site.sql',
            'exports/site-copy-b.wxr',
        ], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'dedupe by hash first keeps first listed duplicate and deletes later matches' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('b/site.sql', 'same sql bytes', ['modTime' => '2026-05-22T00:00:00Z']);
        $remote->put('a/site.sql', 'same sql bytes', ['modTime' => '2026-05-23T00:00:00Z']);
        $remote->put('c/site.sql', 'same sql bytes', ['modTime' => '2026-05-24T00:00:00Z']);

        $result = (new SyncPlan())->deduplicateByHash($remote, DeduplicateMode::FIRST);

        $t->same('a/site.sql', $result['groups'][0]['kept']?->path);
        $t->same(['b/site.sql', 'c/site.sql'], array_map(static fn ($info) => $info->path, $result['groups'][0]['deleted']));
        $t->same(['a/site.sql'], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'dedupe by hash skip and list report duplicates without deleting' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('exports/a.wxr', '<rss>same export</rss>');
        $remote->put('exports/b.wxr', '<rss>same export</rss>');

        $skip = (new SyncPlan())->deduplicateByHash($remote, DeduplicateMode::SKIP);
        $list = (new SyncPlan())->deduplicateByHash($remote, DeduplicateMode::LIST);

        $t->same(true, $skip['groups'][0]['skipped']);
        $t->same([], $skip['groups'][0]['deleted']);
        $t->same(true, $list['groups'][0]['skipped']);
        $t->same(['exports/a.wxr', 'exports/b.wxr'], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'dedupe by hash requires a provider hash and rejects interactive choices' => static function (TestRunner $t): void {
        $noHash = new MemoryProvider(supportedHashes: new HashSet());
        $noHash->put('exports/a.wxr', '<rss>same export</rss>');
        $noHash->put('exports/b.wxr', '<rss>same export</rss>');

        $plan = new SyncPlan();
        $t->throws(RuntimeException::class, static fn () => $plan->deduplicateByHash($noHash, DeduplicateMode::NEWEST));
        $t->throws(InvalidArgumentException::class, static fn () => $plan->deduplicateByHash(new MemoryProvider(), DeduplicateMode::INTERACTIVE));
        $t->throws(InvalidArgumentException::class, static fn () => $plan->deduplicateByHash(new MemoryProvider(), DeduplicateMode::RENAME));
    },
    'dedupe by name skip removes identical duplicate files before skipping remaining conflicts' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->putUnchecked('exports/site.wxr', 'same-wxr', ['modTime' => '2026-05-20T00:00:00Z']);
        $remote->putUnchecked('exports/site.wxr', 'same-wxr', ['modTime' => '2026-05-21T00:00:00Z']);
        $remote->putUnchecked('exports/site.wxr', 'larger-wxr', ['modTime' => '2026-05-22T00:00:00Z']);
        $remote->put('database/site.sql', 'insert into wp_posts values (...)');

        $result = (new SyncPlan())->deduplicateByName($remote, DeduplicateMode::SKIP);

        $t->same(1, count($result['groups']));
        $t->same('exports/site.wxr', $result['groups'][0]['path']);
        $t->same(1, count($result['groups'][0]['identicalDeleted']));
        $t->same(true, $result['groups'][0]['skipped']);
        $t->same(['exports/site.wxr', 'exports/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list('exports')));
        $t->same([8, 10], array_map(static fn ($info) => $info->size, $remote->list('exports')));
    },
    'dedupe by name newest keeps newest duplicate path like upstream' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->putUnchecked('exports/site.wxr', 'old export', ['modTime' => '2026-05-20T00:00:00Z']);
        $remote->putUnchecked('exports/site.wxr', 'middle export', ['modTime' => '2026-05-21T00:00:00Z']);
        $remote->putUnchecked('exports/site.wxr', 'new export body', ['modTime' => '2026-05-22T00:00:00Z']);

        $result = (new SyncPlan())->deduplicateByName($remote, DeduplicateMode::NEWEST);

        $remaining = $remote->list('exports');
        $t->same(1, count($remaining));
        $t->same('exports/site.wxr', $result['groups'][0]['kept']?->path);
        $t->same('2026-05-22T00:00:00Z', $remaining[0]->modTime);
        $t->same(2, count($result['groups'][0]['deleted']));
    },
    'dedupe by name rename skips existing numbered paths and preserves extensions' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('exports/site-1.wxr', 'existing numbered export');
        $remote->putUnchecked('exports/site.wxr', 'first duplicate');
        $remote->putUnchecked('exports/site.wxr', 'second duplicate');
        $remote->putUnchecked('exports/site.wxr', 'third duplicate');

        $result = (new SyncPlan())->deduplicateByName($remote, DeduplicateMode::RENAME);

        $t->same([
            'exports/site-1.wxr',
            'exports/site-2.wxr',
            'exports/site-3.wxr',
            'exports/site-4.wxr',
        ], array_map(static fn ($info) => $info->path, $remote->list('exports')));
        $t->same([
            'exports/site-2.wxr',
            'exports/site-3.wxr',
            'exports/site-4.wxr',
        ], array_map(static fn ($info) => $info->path, $result['groups'][0]['renamed']));
        $t->same(strlen('existing numbered export'), $remote->info('exports/site-1.wxr')->size);
    },
    'dedupe by name size-only removes same-size duplicates without provider hashes' => static function (TestRunner $t): void {
        $remote = new MemoryProvider(supportedHashes: new HashSet());
        $remote->putUnchecked('exports/site.wxr', 'abc', ['modTime' => '2026-05-20T00:00:00Z']);
        $remote->putUnchecked('exports/site.wxr', 'XYZ', ['modTime' => '2026-05-21T00:00:00Z']);
        $remote->putUnchecked('exports/site.wxr', 'longer', ['modTime' => '2026-05-22T00:00:00Z']);

        $result = (new SyncPlan())->deduplicateByName($remote, DeduplicateMode::SKIP, sizeOnly: true);

        $t->same(1, count($result['groups'][0]['identicalDeleted']));
        $t->same([3, 6], array_map(static fn ($info) => $info->size, $remote->list('exports')));
    },
    'dedupe by name ignores repeated provider IDs to avoid data loss' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->putUnchecked('exports/site.wxr', 'same export', ['id' => 'drive-object-id']);
        $remote->putUnchecked('exports/site.wxr', 'same export', ['id' => 'drive-object-id']);

        $result = (new SyncPlan())->deduplicateByName($remote, DeduplicateMode::SKIP);

        $t->same([], $result['groups'][0]['identicalDeleted']);
        $t->same([], $result['groups'][0]['remaining']);
        $t->same(2, count($remote->list('exports')));
    },
];
