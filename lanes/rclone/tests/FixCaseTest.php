<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
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
