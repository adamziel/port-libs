<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
    'ignore case sync skips equal differently cased destination without renaming' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('existing', 'potato', ['modTime' => '2026-05-22T12:00:00Z']);
        $target->put('EXISTING', 'potato', ['modTime' => '2026-05-22T12:00:00Z']);

        $plan = new SyncPlan();
        $copied = $plan->copyChanged($source, $target, ignoreCaseSync: true);

        $t->same([], array_map(static fn ($info) => $info->path, $copied));
        $t->same([], $plan->changedPaths($source, $target, ignoreCaseSync: true));
        $t->same([], $plan->deletePaths($source, $target, ignoreCaseSync: true));
        $t->same(['EXISTING'], array_map(static fn ($info) => $info->path, $target->list()));
        $t->same('potato', $target->get('EXISTING'));
        $t->throws(RuntimeException::class, static fn () => $target->get('existing'));
    },
    'ignore case sync updates matched destination path without fixing casing' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('existing', 'fresh', ['modTime' => '2026-05-22T12:00:00Z']);
        $target->put('EXISTING', 'stale', ['modTime' => '2026-05-21T12:00:00Z']);

        $copied = (new SyncPlan())->copyChanged($source, $target, ignoreCaseSync: true);

        $t->same(['EXISTING'], array_map(static fn ($info) => $info->path, $copied));
        $t->same(['EXISTING'], array_map(static fn ($info) => $info->path, $target->list()));
        $t->same('fresh', $target->get('EXISTING'));
        $t->throws(RuntimeException::class, static fn () => $target->get('existing'));
    },
    'ignore case sync deletion planning treats differently cased matches as present' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('existing', 'potato');
        $target->put('EXISTING', 'potato');
        $target->put('orphan', 'stale');

        $plan = new SyncPlan();

        $t->same(['EXISTING', 'orphan'], $plan->deletePaths($source, $target));
        $t->same(['orphan'], $plan->deletePaths($source, $target, ignoreCaseSync: true));
        $deleted = $plan->deleteDestinationOnly($source, $target, ignoreCaseSync: true);
        $t->same(['orphan'], array_map(static fn ($info) => $info->path, $deleted));
        $t->same('potato', $target->get('EXISTING'));
    },
    'ignore case sync compare dest probes matched destination casing' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $compare = new MemoryProvider();
        $source->put('existing', 'fresh', ['modTime' => '2026-05-22T12:00:00Z']);
        $target->put('EXISTING', 'stale', ['modTime' => '2026-05-21T12:00:00Z']);
        $compare->put('EXISTING', 'fresh', ['modTime' => '2026-05-22T12:00:00Z']);

        $copied = (new SyncPlan())->copyChanged($source, $target, compareDest: [$compare], ignoreCaseSync: true);

        $t->same([], array_map(static fn ($info) => $info->path, $copied));
        $t->same('stale', $target->get('EXISTING'));
        $t->throws(RuntimeException::class, static fn () => $target->get('existing'));
    },
    'ignore case sync copy dest archives and writes matched destination casing' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $copyDest = new MemoryProvider();
        $source->put('existing', 'fresh', ['modTime' => '2026-05-22T12:00:00Z']);
        $target->put('EXISTING', 'stale', ['modTime' => '2026-05-21T12:00:00Z']);
        $copyDest->put('EXISTING', 'fresh', ['modTime' => '2026-05-22T12:00:00Z']);

        $copied = (new SyncPlan())->copyChanged(
            $source,
            $target,
            backupPrefix: 'backup',
            copyDest: [$copyDest],
            ignoreCaseSync: true,
        );

        $t->same(['EXISTING'], array_map(static fn ($info) => $info->path, $copied));
        $t->same(['EXISTING', 'backup/EXISTING'], array_map(static fn ($info) => $info->path, $target->list()));
        $t->same('fresh', $target->get('EXISTING'));
        $t->same('stale', $target->get('backup/EXISTING'));
        $t->throws(RuntimeException::class, static fn () => $target->get('existing'));
        $t->throws(RuntimeException::class, static fn () => $target->get('backup/existing'));
    },
    'ignore case sync skips wordpress artifact recopy on case sensitive providers' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        foreach ($tree as $path => $bytes) {
            $source->put($path, $bytes);
        }

        $target->put('WP-CONTENT/UPLOADS/2026/05/HERO.JPG', $tree['wp-content/uploads/2026/05/hero.jpg']);
        $target->put('EXPORTS/SITE.WXR', $tree['exports/site.wxr']);
        $target->put('wp-content/cache/page/index.html', '<html>stale cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- archive/**',
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ], ignoreCase: true);

        $plan = new SyncPlan();
        $copied = $plan->copyChanged($source, $target, $filter, ignoreCaseSync: true);

        $t->same([
            'database/site.sql',
            'wp-content/uploads/2026/05/hero.webp',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same('WP-CONTENT/UPLOADS/2026/05/HERO.JPG', $target->info('WP-CONTENT/UPLOADS/2026/05/HERO.JPG')->path);
        $t->same('EXPORTS/SITE.WXR', $target->info('EXPORTS/SITE.WXR')->path);
        $t->same($tree['wp-content/uploads/2026/05/hero.jpg'], $target->get('WP-CONTENT/UPLOADS/2026/05/HERO.JPG'));
        $t->same($tree['exports/site.wxr'], $target->get('EXPORTS/SITE.WXR'));
        $t->same([], $plan->deletePaths($source, $target, $filter, ignoreCaseSync: true));
        $t->same('<html>stale cache</html>', $target->get('wp-content/cache/page/index.html'));
    },
    'ignore case sync hydrates wordpress copy dest using matched remote casing' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $copyDest = new MemoryProvider();
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        foreach ($tree as $path => $bytes) {
            $source->put($path, $bytes);
        }

        $target->put('WP-CONTENT/UPLOADS/2026/05/HERO.JPG', 'previous hero bytes');
        $target->put('EXPORTS/SITE.WXR', '<rss>previous export</rss>');
        $target->put('wp-content/cache/page/index.html', '<html>stale cache</html>');
        $copyDest->put('WP-CONTENT/UPLOADS/2026/05/HERO.JPG', $tree['wp-content/uploads/2026/05/hero.jpg']);
        $copyDest->put('EXPORTS/SITE.WXR', $tree['exports/site.wxr']);
        $copyDest->put('wp-content/uploads/2026/05/hero.webp', $tree['wp-content/uploads/2026/05/hero.webp']);
        $copyDest->put('database/site.sql', $tree['database/site.sql']);

        $filter = FilterRuleSet::fromRules([
            '- archive/**',
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ], ignoreCase: true);

        $plan = new SyncPlan();
        $copied = $plan->copyChanged(
            $source,
            $target,
            $filter,
            backupPrefix: 'archive/2026-05-22',
            copyDest: [$copyDest],
            ignoreCaseSync: true,
        );

        $t->same([
            'database/site.sql',
            'EXPORTS/SITE.WXR',
            'WP-CONTENT/UPLOADS/2026/05/HERO.JPG',
            'wp-content/uploads/2026/05/hero.webp',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same($tree['wp-content/uploads/2026/05/hero.jpg'], $target->get('WP-CONTENT/UPLOADS/2026/05/HERO.JPG'));
        $t->same($tree['exports/site.wxr'], $target->get('EXPORTS/SITE.WXR'));
        $t->same($tree['database/site.sql'], $target->get('database/site.sql'));
        $t->same('previous hero bytes', $target->get('archive/2026-05-22/WP-CONTENT/UPLOADS/2026/05/HERO.JPG'));
        $t->same('<rss>previous export</rss>', $target->get('archive/2026-05-22/EXPORTS/SITE.WXR'));
        $t->same('<html>stale cache</html>', $target->get('wp-content/cache/page/index.html'));
        $t->same([], $plan->deletePaths($source, $target, $filter, ignoreCaseSync: true));
    },
    'fix case renames equal destination objects without transferring' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider(true);
        $source->put('existing', 'potato');
        $target->put('EXISTING', 'potato');

        $copied = (new SyncPlan())->copyChanged($source, $target, fixCase: true);

        $t->same([], array_map(static fn ($info) => $info->path, $copied));
        $t->same(['existing'], array_map(static fn ($info) => $info->path, $target->list()));
        $t->same('potato', $target->get('EXISTING'));
        $t->same('existing', $target->info('EXISTING')->path);
    },
    'fix case repairs nested directories before copying changed leaf objects' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider(true);

        $source->put('existing', 'potato');
        $source->put('existingbutdifferent', 'donut');
        $source->put('subdira/subdirb/subdirc/hello', 'donut');
        $source->put('subdira/subdirb/subdirc/subdird/filewithoutcasedifferences', 'donut');

        $target->put('EXISTING', 'potato');
        $target->put('EXISTINGBUTDIFFERENT', 'lemonade');
        $target->put('SUBDIRA/subdirb/SUBDIRC/HELLO', 'lemonade');
        $target->put('SUBDIRA/subdirb/SUBDIRC/subdird/filewithoutcasedifferences', 'lemonade');

        $copied = (new SyncPlan())->copyChanged($source, $target, fixCase: true);

        $t->same([
            'existingbutdifferent',
            'subdira/subdirb/subdirc/hello',
            'subdira/subdirb/subdirc/subdird/filewithoutcasedifferences',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same([
            'existing',
            'existingbutdifferent',
            'subdira/subdirb/subdirc/hello',
            'subdira/subdirb/subdirc/subdird/filewithoutcasedifferences',
        ], array_map(static fn ($info) => $info->path, $target->list()));
        $t->same('donut', $target->get('SUBDIRA/subdirb/SUBDIRC/HELLO'));
        $t->same('subdira/subdirb/subdirc', $target->directoryInfo('SUBDIRA/subdirb/SUBDIRC')->path);
    },
    'fix case is suppressed by immutable mode like upstream sync' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider(true);
        $source->put('existing', 'potato');
        $target->put('EXISTING', 'potato');

        $copied = (new SyncPlan())->copyChanged($source, $target, immutable: true, fixCase: true);

        $t->same([], array_map(static fn ($info) => $info->path, $copied));
        $t->same(['EXISTING'], array_map(static fn ($info) => $info->path, $target->list()));
        $t->same('EXISTING', $target->info('existing')->path);
    },
    'fix case sync repairs wordpress backup casing while leaving excluded cache leaf casing untouched' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider(true);
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        foreach ($tree as $path => $bytes) {
            $source->put($path, $bytes);
        }

        $target->put('WP-CONTENT/UPLOADS/2026/05/HERO.JPG', $tree['wp-content/uploads/2026/05/hero.jpg']);
        $target->put('EXPORTS/SITE.WXR', '<rss>stale</rss>');
        $target->put('WP-CONTENT/CACHE/PAGE/INDEX.HTML', '<html>old cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $copied = (new SyncPlan())->copyChanged($source, $target, $filter, fixCase: true);

        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.webp',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same('wp-content/uploads/2026/05/hero.jpg', $target->info('WP-CONTENT/UPLOADS/2026/05/HERO.JPG')->path);
        $t->same('exports/site.wxr', $target->info('EXPORTS/SITE.WXR')->path);
        $t->same($tree['exports/site.wxr'], $target->get('exports/site.wxr'));
        $t->same('wp-content/CACHE/PAGE/INDEX.HTML', $target->info('wp-content/cache/page/index.html')->path);
    },
];
