<?php

declare(strict_types=1);

use PortLibs\Rclone\DeleteMode;
use PortLibs\Rclone\FilterRuleSet;
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
];
