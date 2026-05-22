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
    'interactive dedupe by hash keeps chosen duplicate and quit stops later groups' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('database/site-a.sql', 'same sql bytes');
        $remote->put('database/site-b.sql', 'same sql bytes');
        $remote->put('exports/site-a.wxr', '<rss>same export</rss>');
        $remote->put('exports/site-b.wxr', '<rss>same export</rss>');

        $calls = 0;
        $result = (new SyncPlan())->deduplicateByHash(
            $remote,
            DeduplicateMode::INTERACTIVE,
            static function (array $group) use (&$calls): array|string {
                $calls++;

                return $calls === 1
                    ? ['action' => 'keep', 'keep' => 2]
                    : 'q';
            },
        );

        $t->same(true, $result['quit']);
        $t->same(2, $calls);
        $t->same('keep', $result['groups'][0]['action']);
        $t->same(1, count($result['groups'][0]['deleted']));
        $t->same('quit', $result['groups'][1]['action']);
        $t->same(3, count($remote->list()));

        $t->throws(InvalidArgumentException::class, static fn () => (new SyncPlan())->deduplicateByHash(
            $remote,
            DeduplicateMode::INTERACTIVE,
            static fn (): array => ['action' => 'rename'],
        ));
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
    'interactive dedupe by name deletes identical copies before keeping chosen conflict' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->putUnchecked('exports/site.wxr', '<rss>published export</rss>', ['modTime' => '2026-05-20T00:00:00Z']);
        $remote->putUnchecked('exports/site.wxr', '<rss>published export</rss>', ['modTime' => '2026-05-21T00:00:00Z']);
        $remote->putUnchecked('exports/site.wxr', '<rss>recovered draft</rss>', ['modTime' => '2026-05-22T00:00:00Z']);

        $result = (new SyncPlan())->deduplicateByName(
            $remote,
            DeduplicateMode::INTERACTIVE,
            interactiveChoice: static function (array $group): array {
                return [
                    'action' => 'keep',
                    'keep' => 2,
                ];
            },
        );

        $t->same(false, $result['quit']);
        $t->same('keep', $result['groups'][0]['action']);
        $t->same(1, count($result['groups'][0]['identicalDeleted']));
        $t->same(1, count($result['groups'][0]['deleted']));
        $t->same('<rss>recovered draft</rss>', $remote->get('exports/site.wxr'));
        $t->same(['exports/site.wxr'], array_map(static fn ($info) => $info->path, $remote->list('exports')));
    },
    'interactive dedupe by name can choose upstream rename action' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('exports/site-1.wxr', 'existing numbered export');
        $remote->putUnchecked('exports/site.wxr', 'published export');
        $remote->putUnchecked('exports/site.wxr', 'recovered export');

        $result = (new SyncPlan())->deduplicateByName(
            $remote,
            DeduplicateMode::INTERACTIVE,
            interactiveChoice: static fn (): array => ['action' => 'rename'],
        );

        $t->same('rename', $result['groups'][0]['action']);
        $t->same([
            'exports/site-1.wxr',
            'exports/site-2.wxr',
            'exports/site-3.wxr',
        ], array_map(static fn ($info) => $info->path, $remote->list('exports')));
        $t->same([
            'exports/site-2.wxr',
            'exports/site-3.wxr',
        ], array_map(static fn ($info) => $info->path, $result['groups'][0]['renamed']));
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
    'merge dirs moves later directory contents into the first directory like upstream' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('dupe1/one.txt', 'This is one', ['modTime' => '2026-05-20T00:00:00Z']);
        $remote->put('dupe2/two.txt', 'This is one too', ['modTime' => '2026-05-21T00:00:00Z']);
        $remote->put('dupe3/three.txt', 'This is another one', ['modTime' => '2026-05-22T00:00:00Z']);

        $result = $remote->mergeDirectories(['dupe1', 'dupe2', 'dupe3']);

        $t->same('dupe1', $result['target']->path);
        $t->same([
            'dupe1/one.txt',
            'dupe1/three.txt',
            'dupe1/two.txt',
        ], array_map(static fn ($info) => $info->path, $remote->list()));
        $t->same('This is one too', $remote->get('dupe1/two.txt'));
        $t->same('2026-05-22T00:00:00Z', $remote->info('dupe1/three.txt')->modTime);
        $t->same(['dupe1'], array_map(static fn ($info) => $info->path, $remote->directories()));
        $t->throws(RuntimeException::class, static fn () => $remote->directoryInfo('dupe2'));
        $t->throws(RuntimeException::class, static fn () => $remote->directoryInfo('dupe3'));
    },
    'duplicate directory merge picks largest first and leaves file conflicts for dedupe' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->put('exports-primary/site.wxr', '<rss>primary export</rss>', ['modTime' => '2026-05-22T00:00:00Z']);
        $remote->put('exports-primary/media/hero.jpg', 'hero image');
        $remote->put('exports-duplicate/site.wxr', '<rss>recovered draft</rss>', ['modTime' => '2026-05-21T00:00:00Z']);

        $merge = (new SyncPlan())->mergeDuplicateDirectories($remote, ['exports-duplicate', 'exports-primary']);

        $t->same(false, $merge['listed']);
        $t->same(['exports-primary', 'exports-duplicate'], $merge['ordered']);
        $t->same('exports-primary', $merge['target']?->path);
        $t->same([
            'exports-primary/media/hero.jpg',
            'exports-primary/site.wxr',
            'exports-primary/site.wxr',
        ], array_map(static fn ($info) => $info->path, $remote->list()));

        $renamed = (new SyncPlan())->deduplicateByName($remote, DeduplicateMode::RENAME);
        $t->same(['exports-primary/site-1.wxr', 'exports-primary/site-2.wxr'], array_map(
            static fn ($info) => $info->path,
            $renamed['groups'][0]['renamed'],
        ));
        $t->same([
            'exports-primary/media/hero.jpg',
            'exports-primary/site-1.wxr',
            'exports-primary/site-2.wxr',
        ], array_map(static fn ($info) => $info->path, $remote->list()));
    },
    'duplicate directory list mode reports provider ID duplicates without mutation' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->mkdir('uploads', ['id' => 'uploads-root']);
        $remote->mkdir('uploads/2026', ['id' => 'year-2026', 'parentId' => 'uploads-root']);
        $remote->mkdirUnchecked('uploads/2026/05', ['id' => 'month-primary', 'parentId' => 'year-2026']);
        $remote->mkdirUnchecked('uploads/2026/05', ['id' => 'month-recovered', 'parentId' => 'year-2026']);
        $remote->mkdirUnchecked('uploads/2026/05/thumbs', ['id' => 'thumbs-recovered', 'parentId' => 'month-recovered']);
        $remote->putUnchecked('uploads/2026/05/hero.jpg', 'published', ['id' => 'hero-primary', 'parentId' => 'month-primary']);
        $remote->putUnchecked('uploads/2026/05/hero.jpg', 'recovered', ['id' => 'hero-recovered', 'parentId' => 'month-recovered']);
        $remote->putUnchecked('uploads/2026/05/thumbs/hero-150x150.jpg', 'thumb', ['id' => 'thumb-object', 'parentId' => 'thumbs-recovered']);

        $result = (new SyncPlan())->listDuplicateDirectories($remote);

        $t->same(1, count($result['groups']));
        $t->same('uploads/2026/05', $result['groups'][0]['path']);
        $t->same('uploads/2026/05: 2 duplicates of this directory', $result['groups'][0]['report']);
        $t->same(['month-primary', 'month-recovered'], array_map(
            static fn ($info) => $info->id,
            $result['groups'][0]['directories'],
        ));
        $t->same(['year-2026', 'year-2026'], array_map(
            static fn ($info) => $info->parentId,
            $result['groups'][0]['directories'],
        ));
        $t->same([1, 3], $result['groups'][0]['counts']);
        $t->same([
            'month-primary',
            'month-recovered',
            'thumbs-recovered',
        ], array_values(array_filter(array_map(
            static fn ($info) => $info->id,
            $remote->directories('uploads/2026/05'),
        ))));
        $t->same([
            'uploads/2026/05/hero.jpg',
            'uploads/2026/05/hero.jpg',
            'uploads/2026/05/thumbs/hero-150x150.jpg',
        ], array_map(static fn ($info) => $info->path, $remote->list('uploads/2026/05')));
        $t->same([
            'month-primary',
            'month-recovered',
            'thumbs-recovered',
        ], array_values(array_filter(array_map(
            static fn ($info) => $info->parentId,
            $remote->list('uploads/2026/05'),
        ))));
    },
    'duplicate directory merge uses provider IDs and rewires source children to the kept directory' => static function (TestRunner $t): void {
        $remote = new MemoryProvider();
        $remote->mkdir('uploads', ['id' => 'uploads-root']);
        $remote->mkdir('uploads/2026', ['id' => 'year-2026', 'parentId' => 'uploads-root']);
        $remote->mkdirUnchecked('uploads/2026/05', ['id' => 'month-primary', 'parentId' => 'year-2026']);
        $remote->mkdirUnchecked('uploads/2026/05', ['id' => 'month-recovered', 'parentId' => 'year-2026']);
        $remote->mkdirUnchecked('uploads/2026/05/thumbs', ['id' => 'thumbs-primary', 'parentId' => 'month-primary']);
        $remote->putUnchecked('uploads/2026/05/hero.jpg', 'published hero', ['id' => 'hero-primary', 'parentId' => 'month-primary']);
        $remote->putUnchecked('uploads/2026/05/gallery.jpg', 'published gallery', ['id' => 'gallery-primary', 'parentId' => 'month-primary']);
        $remote->putUnchecked('uploads/2026/05/hero.jpg', 'recovered hero', ['id' => 'hero-recovered', 'parentId' => 'month-recovered']);

        $plan = new SyncPlan();
        $duplicates = $plan->findDuplicateDirectories($remote);
        $t->same([3, 1], array_map(
            static fn ($info) => $remote->directoryEntryCount($info),
            $duplicates[0]['directories'],
        ));

        $merge = $plan->mergeDuplicateDirectories($remote, $duplicates[0]['directories']);

        $t->same(false, $merge['listed']);
        $t->same('month-primary', $merge['target']?->id);
        $t->same(['month-primary', 'thumbs-primary'], array_values(array_filter(array_map(
            static fn ($info) => $info->id,
            $remote->directories('uploads/2026/05'),
        ))));
        $t->same([], $plan->findDuplicateDirectories($remote));
        $t->same([
            'uploads/2026/05/gallery.jpg',
            'uploads/2026/05/hero.jpg',
            'uploads/2026/05/hero.jpg',
        ], array_map(static fn ($info) => $info->path, $remote->list('uploads/2026/05')));
        $t->same(['month-primary', 'month-primary', 'month-primary'], array_map(
            static fn ($info) => $info->parentId,
            $remote->list('uploads/2026/05'),
        ));

        $renamed = $plan->deduplicateByName($remote, DeduplicateMode::RENAME);
        $t->same([
            'uploads/2026/05/hero-1.jpg',
            'uploads/2026/05/hero-2.jpg',
        ], array_map(static fn ($info) => $info->path, $renamed['groups'][0]['renamed']));
        $t->same([
            'uploads/2026/05/gallery.jpg',
            'uploads/2026/05/hero-1.jpg',
            'uploads/2026/05/hero-2.jpg',
        ], array_map(static fn ($info) => $info->path, $remote->list('uploads/2026/05')));
    },
];
