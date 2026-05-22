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
];
