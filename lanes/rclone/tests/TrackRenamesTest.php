<?php

declare(strict_types=1);

use PortLibs\Rclone\DeleteMode;
use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;
use PortLibs\Rclone\TrackRenamesStrategy;

return [
    'parses upstream track renames strategy flags' => static function (TestRunner $t): void {
        $t->same(0, TrackRenamesStrategy::parse('')->flags());
        $t->same(2, TrackRenamesStrategy::parse('modtime')->flags());
        $t->same(1, TrackRenamesStrategy::parse('hash')->flags());
        $t->same(0, TrackRenamesStrategy::parse('size')->flags());
        $t->same(3, TrackRenamesStrategy::parse('modtime,hash')->flags());
        $t->same(3, TrackRenamesStrategy::parse('hash,modtime,size')->flags());
        $t->throws(InvalidArgumentException::class, static fn () => TrackRenamesStrategy::parse('size,boom'));
    },
    'track renames default hash strategy moves destination candidates before delete after' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('potato', 'Potato Content', ['modTime' => '2026-05-22T00:00:00Z']);
        $source->put('yaml', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);
        $target->put('potato', 'Potato Content', ['modTime' => '2026-05-22T00:00:00Z']);
        $target->put('yam', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);
        $target->put('stale.txt', 'old target only');

        $result = (new SyncPlan())->syncWithTrackRenames($source, $target);

        $t->same(true, $result['trackRenamesEnabled']);
        $t->same(null, $result['disabledReason']);
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same([], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same(['stale.txt'], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same(['potato', 'yaml'], array_map(static fn ($info) => $info->path, $target->list()));
        $t->same('Yam Content', $target->get('yaml'));
        $t->throws(RuntimeException::class, static fn () => $target->get('yam'));
    },
    'track renames hash strategy falls back when providers share no hash' => static function (TestRunner $t): void {
        $source = new MemoryProvider(false, new HashSet());
        $target = new MemoryProvider(false, new HashSet());
        $source->put('yaml', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);
        $target->put('yam', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);

        $result = (new SyncPlan())->syncWithTrackRenames($source, $target);

        $t->same(false, $result['trackRenamesEnabled']);
        $t->same('source and destination do not have a common hash', $result['disabledReason']);
        $t->same([], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same(['yam'], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same('Yam Content', $target->get('yaml'));
        $t->throws(RuntimeException::class, static fn () => $target->get('yam'));
    },
    'track renames falls back when destination cannot move server side' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider(serverSideMove: false);
        $source->put('yaml', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);
        $target->put('yam', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);

        $result = (new SyncPlan())->syncWithTrackRenames($source, $target);

        $t->same(false, $result['trackRenamesEnabled']);
        $t->same('destination does not support server-side move or copy', $result['disabledReason']);
        $t->same([], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same(['yam'], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same('Yam Content', $target->get('yaml'));
        $t->throws(RuntimeException::class, static fn () => $target->get('yam'));
    },
    'track renames modtime strategy selects the first destination candidate within window' => static function (TestRunner $t): void {
        $source = new MemoryProvider(false, new HashSet());
        $target = new MemoryProvider(false, new HashSet());
        $source->put('yaml', 'Yam Content', ['modTime' => '2026-05-22T01:00:00Z']);
        $target->put('other', 'Yam Content', ['modTime' => '2026-05-23T01:00:00Z']);
        $target->put('yam', 'Yam Content', ['modTime' => '2026-05-22T01:00:00.500000Z']);

        $result = (new SyncPlan())->syncWithTrackRenames($source, $target, trackRenamesStrategy: 'modtime');

        $t->same(true, $result['trackRenamesEnabled']);
        $t->same(['yaml'], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same([], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same(['other'], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same('Yam Content', $target->get('yaml'));
        $t->throws(RuntimeException::class, static fn () => $target->get('yam'));
        $t->throws(RuntimeException::class, static fn () => $target->get('other'));
    },
    'track renames leaf strategy matches basename and size without hashes' => static function (TestRunner $t): void {
        $source = new MemoryProvider(false, new HashSet());
        $target = new MemoryProvider(false, new HashSet());
        $source->put('yam', 'Yam Content');
        $target->put('sub/yam', 'Yam Content');

        $result = (new SyncPlan())->syncWithTrackRenames($source, $target, trackRenamesStrategy: 'leaf');

        $t->same(['yam'], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same([], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same([], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same(['yam'], array_map(static fn ($info) => $info->path, $target->list()));
    },
    'track renames disables no traverse and rejects delete before' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('exports/site-renamed.wxr', '<rss>portable export</rss>');
        $target->put('exports/site.wxr', '<rss>portable export</rss>');

        $stats = null;
        $result = (new SyncPlan())->syncWithTrackRenames(
            $source,
            $target,
            noTraverse: true,
            noTraverseStats: $stats,
        );

        $t->same(['exports/site-renamed.wxr'], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same(false, $stats['enabled']);
        $t->same('sync delete mode requires destination traversal', $stats['disabledReason']);
        $t->same(true, $stats['targetListUsed']);
        $t->same([], $stats['targetLookups']);
        $t->same('<rss>portable export</rss>', $target->get('exports/site-renamed.wxr'));
        $t->throws(RuntimeException::class, static fn () => (new SyncPlan())->syncWithTrackRenames(
            $source,
            $target,
            deleteMode: DeleteMode::BEFORE,
        ));
    },
    'track renames wordpress uploads while archiving unmatched stale artifacts' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        $source->put('wp-content/uploads/2026/05/hero-renamed.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
        $source->put('exports/site.wxr', $tree['exports/site.wxr']);
        $source->put('database/site.sql', $tree['database/site.sql']);

        $target->put('wp-content/uploads/2026/05/hero.jpg', $tree['wp-content/uploads/2026/05/hero.jpg']);
        $target->put('exports/old-site.wxr', '<rss>old export</rss>');
        $target->put('wp-content/cache/orphan.html', '<html>cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- archive/**',
            '- wp-content/cache/**',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $plan = new SyncPlan();
        $result = $plan->syncWithTrackRenames(
            $source,
            $target,
            $filter,
            backupPrefix: 'archive/2026-05-22',
            suffix: '-previous',
            suffixKeepExtension: true,
        );

        $t->same(['wp-content/uploads/2026/05/hero-renamed.jpg'], array_map(static fn ($info) => $info->path, $result['renamed']));
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
        ], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same(['archive/2026-05-22/exports/old-site-previous.wxr'], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same($tree['wp-content/uploads/2026/05/hero.jpg'], $target->get('wp-content/uploads/2026/05/hero-renamed.jpg'));
        $t->throws(RuntimeException::class, static fn () => $target->get('wp-content/uploads/2026/05/hero.jpg'));
        $t->same('<rss>old export</rss>', $target->get('archive/2026-05-22/exports/old-site-previous.wxr'));
        $t->same('<html>cache</html>', $target->get('wp-content/cache/orphan.html'));
        $t->same([], $plan->deletePaths($source, $target, $filter));
    },
];
