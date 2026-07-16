<?php

declare(strict_types=1);

use PortLibs\Rclone\DeleteMode;
use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ObjectInfo;
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
    'operations delete filters ListFn objects before deletion' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('empty-dir');
        $provider->put('small', '1234567890');
        $provider->put('medium', str_repeat('-', 60));
        $provider->put('large', str_repeat('A', 100));

        $stats = null;
        $result = (new SyncPlan())->deleteContents(
            $provider,
            includeObject: static fn (ObjectInfo $info): bool => $info->size <= 60,
            stats: $stats,
        );

        $t->same(['medium', 'small'], array_map(static fn ($info): string => $info->path, $result['deleted']));
        $t->same([], $result['prunedDirectories']);
        $t->same([
            'listed' => 3,
            'deletes' => 2,
            'deleteBytes' => 70,
            'errors' => 0,
            'lastError' => null,
            'dryRunObjectSkipped' => 0,
        ], $stats);
        $t->same(str_repeat('A', 100), $provider->get('large'));
        $t->same('empty-dir', $provider->directoryInfo('empty-dir')->path);
    },
    'operations delete dry run accounts file attempts without provider mutation' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('database/site.sql', 'insert');
        $provider->put('exports/site.wxr', '<rss></rss>');

        $stats = null;
        $result = (new SyncPlan())->deleteContents($provider, dryRun: true, stats: $stats);

        $t->same([], $result['deleted']);
        $t->same([
            'listed' => 2,
            'deletes' => 2,
            'deleteBytes' => 17,
            'errors' => 0,
            'lastError' => null,
            'dryRunObjectSkipped' => 2,
        ], $stats);
        $t->same('insert', $provider->get('database/site.sql'));
        $t->same('<rss></rss>', $provider->get('exports/site.wxr'));
    },
    'operations delete aggregates provider errors after attempting listed files' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('bad-one.txt', 'bad1');
        $provider->put('bad-two.txt', 'bad22');
        $provider->put('ok.txt', 'ok');
        $provider->setDeleteError('bad-one.txt', 'permission denied');
        $provider->setDeleteError('bad-two.txt', 'object locked');

        $stats = null;
        $error = null;
        try {
            (new SyncPlan())->deleteContents($provider, stats: $stats);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('failed to delete 2 files', $error?->getMessage());
        $t->same([
            'listed' => 3,
            'deletes' => 3,
            'deleteBytes' => 11,
            'errors' => 2,
            'lastError' => 'object locked',
            'dryRunObjectSkipped' => 0,
        ], $stats);
        $t->same('bad1', $provider->get('bad-one.txt'));
        $t->same('bad22', $provider->get('bad-two.txt'));
        $t->throws(RuntimeException::class, static fn () => $provider->get('ok.txt'));
    },
    'operations delete reports max delete threshold as aggregate command failure' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('a-small.txt', str_repeat('a', 10));
        $provider->put('b-medium.txt', str_repeat('b', 60));
        $provider->put('c-large.txt', str_repeat('c', 100));

        $stats = null;
        $error = null;
        try {
            (new SyncPlan())->deleteContents($provider, maxDelete: 2, stats: $stats);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('failed to delete 1 files', $error?->getMessage());
        $t->same([
            'listed' => 3,
            'deletes' => 2,
            'deleteBytes' => 70,
            'errors' => 1,
            'lastError' => '--max-delete threshold reached',
            'dryRunObjectSkipped' => 0,
        ], $stats);
        $t->throws(RuntimeException::class, static fn () => $provider->get('a-small.txt'));
        $t->throws(RuntimeException::class, static fn () => $provider->get('b-medium.txt'));
        $t->same(str_repeat('c', 100), $provider->get('c-large.txt'));
    },
    'match listings ignores duplicate source objects after the first provider entry' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('exports/site.wxr', '<rss>current export</rss>');
        $source->putUnchecked('exports/site.wxr', '<rss>stale duplicate export</rss>');
        $target->put('exports/site.wxr', '<rss>current export</rss>');

        $plan = new SyncPlan();
        $t->same([], $plan->changedPaths($source, $target));
        $t->same([], array_map(static fn ($info) => $info->path, $plan->copyChanged($source, $target)));
        $t->same('<rss>current export</rss>', $target->get('exports/site.wxr'));

        $target = new MemoryProvider();
        $copied = $plan->copyChanged($source, $target);
        $t->same(['exports/site.wxr'], array_map(static fn ($info) => $info->path, $copied));
        $t->same('<rss>current export</rss>', $target->get('exports/site.wxr'));
    },
    'match listings reports duplicate destination diagnostics while keeping the first entry' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('exports/site.wxr', '<rss>current export</rss>');
        $source->put('database/site.sql', 'insert into wp_posts values (...)');
        $target->put('exports/site.wxr', '<rss>current export</rss>');
        $target->putUnchecked('exports/site.wxr', '<rss>stale duplicate export</rss>');
        $target->put('exports/old-site.wxr', '<rss>old export</rss>');

        $plan = new SyncPlan();
        $diagnostics = $plan->matchListingDiagnostics($source, $target);

        $t->same(['exports/site.wxr'], array_map(static fn (array $pair): string => $pair['source']->path, $diagnostics['matches']));
        $t->same(['database/site.sql'], array_map(static fn ($info): string => $info->path, $diagnostics['sourceOnly']));
        $t->same(['exports/old-site.wxr'], array_map(static fn ($info): string => $info->path, $diagnostics['destinationOnly']));
        $t->same(['exports/site.wxr'], array_map(static fn (array $duplicate): string => $duplicate['path'], $diagnostics['duplicateDestinations']));
        $t->same(['Duplicate object found in destination - ignoring'], array_map(static fn (array $duplicate): string => $duplicate['message'], $diagnostics['duplicateDestinations']));
        $t->same(hash('sha256', '<rss>current export</rss>'), $diagnostics['duplicateDestinations'][0]['kept']->sha256);
        $t->same(hash('sha256', '<rss>stale duplicate export</rss>'), $diagnostics['duplicateDestinations'][0]['ignored']->sha256);

        $copied = $plan->copyChanged($source, $target);
        $t->same(['database/site.sql'], array_map(static fn ($info): string => $info->path, $copied));
        $t->same('<rss>current export</rss>', $target->get('exports/site.wxr'));
    },
    'match listings keeps same-remote directories separate from objects' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->mkdir('exports');
        $source->put('exports', '<rss>directory marker object</rss>');
        $target->mkdir('exports');
        $target->put('exports', '<rss>directory marker object</rss>');

        $diagnostics = (new SyncPlan())->matchListingDiagnostics($source, $target, includeDirectories: true);

        $t->same([
            'directory:exports',
            'object:exports',
        ], array_map(
            static fn (array $pair): string => (ListDirectory::isDirectory($pair['source']) ? 'directory:' : 'object:')
                . $pair['source']->path,
            $diagnostics['matches'],
        ));
        $t->same([], $diagnostics['sourceOnly']);
        $t->same([], $diagnostics['destinationOnly']);
        $t->same([], $diagnostics['duplicateSources']);
        $t->same([], $diagnostics['duplicateDestinations']);
    },
    'match listings reports duplicate directories separately from same-path objects' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->mkdir('exports', ['id' => 'source-dir']);
        $source->put('exports', '<rss>directory marker object</rss>');
        $target->mkdir('exports', ['id' => 'target-dir']);
        $target->mkdirUnchecked('exports', ['id' => 'interrupted-dir']);
        $target->put('exports', '<rss>directory marker object</rss>');

        $diagnostics = (new SyncPlan())->matchListingDiagnostics($source, $target, includeDirectories: true);

        $t->same([
            'directory:exports',
            'object:exports',
        ], array_map(
            static fn (array $pair): string => (ListDirectory::isDirectory($pair['source']) ? 'directory:' : 'object:')
                . $pair['source']->path,
            $diagnostics['matches'],
        ));
        $t->same(['directory'], array_map(static fn (array $duplicate): string => $duplicate['type'], $diagnostics['duplicateDestinations']));
        $t->same(['Duplicate directory found in destination - ignoring'], array_map(static fn (array $duplicate): string => $duplicate['message'], $diagnostics['duplicateDestinations']));
        $t->same('target-dir', $diagnostics['duplicateDestinations'][0]['kept']->id);
        $t->same('interrupted-dir', $diagnostics['duplicateDestinations'][0]['ignored']->id);
    },
    'match listings rejects out of order source and destination entry streams' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('b.wxr', 'b');
        $source->put('a.sql', 'a');
        $target->put('z.wxr', 'z');
        $target->put('c.sql', 'c');
        $plan = new SyncPlan();

        try {
            $plan->matchListingDiagnosticsFromEntries(
                [$source->info('b.wxr'), $source->info('a.sql')],
                [],
            );
            throw new RuntimeException('source order guard did not fire');
        } catch (RuntimeException $throwable) {
            $t->same('Out of order listing in source', $throwable->getMessage());
        }

        try {
            $plan->matchListingDiagnosticsFromEntries(
                [],
                [$target->info('z.wxr'), $target->info('c.sql')],
            );
            throw new RuntimeException('destination order guard did not fire');
        } catch (RuntimeException $throwable) {
            $t->same('Out of order listing in destination', $throwable->getMessage());
        }
    },
    'match listings treats object before same-path directory as out of order' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $source->mkdir('exports', ['id' => 'source-dir']);
        $source->put('exports', '<rss>directory marker object</rss>');

        try {
            (new SyncPlan())->matchListingDiagnosticsFromEntries(
                [$source->info('exports'), $source->directoryInfo('exports')],
                [],
            );
            throw new RuntimeException('directory type order guard did not fire');
        } catch (RuntimeException $throwable) {
            $t->same('Out of order listing in source', $throwable->getMessage());
        }
    },
    'wordpress duplicate source listing example preserves first export entry' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-duplicate-source-listing.php';

        $t->same(['database/site.sql'], $example['changedBeforeCopy']);
        $t->same(['database/site.sql'], $example['copied']);
        $t->same('<rss version="2.0"></rss>', $example['targetExportBytes']);
        $t->same('insert into wp_posts values (...)', $example['targetDatabaseBytes']);
    },
    'wordpress duplicate destination diagnostics example surfaces ignored export' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-duplicate-destination-diagnostics.php';

        $t->same(['exports/site.wxr'], $example['matches']);
        $t->same(['database/site.sql'], $example['sourceOnly']);
        $t->same([], $example['destinationOnly']);
        $t->same(['exports/site.wxr'], $example['duplicateDestinationPaths']);
        $t->same(['Duplicate object found in destination - ignoring'], $example['duplicateDestinationMessages']);
        $t->same(hash('sha256', '<rss>interrupted stale duplicate</rss>'), $example['ignoredDuplicateHashes'][0]);
        $t->same(['database/site.sql'], $example['copied']);
        $t->same('<rss version="2.0"></rss>', $example['targetExportBytes']);
        $t->same('<html>stale cache</html>', $example['cacheLeftUntouched']);
    },
    'wordpress duplicate directory and marker object diagnostics example separates entry types' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-duplicate-directory-file-diagnostics.php';

        $t->same([
            'directory:wp-content/uploads/2026/05',
            'object:wp-content/uploads/2026/05',
        ], $example['uploadPathMatches']);
        $t->same(['wp-content/uploads/2026/05'], $example['duplicateDestinationPaths']);
        $t->same(['directory'], $example['duplicateDestinationTypes']);
        $t->same(['Duplicate directory found in destination - ignoring'], $example['duplicateDestinationMessages']);
        $t->same(['published-month'], $example['keptDirectoryIds']);
        $t->same(['interrupted-restore-month'], $example['ignoredDirectoryIds']);
        $t->same(hash('sha256', 'directory marker bytes'), $example['markerObjectHash']);
    },
    'wordpress match listing order guard example reports unsorted provider batch' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-matchlisting-order-guard.php';

        $t->same([
            'wp-content/uploads/2026/05/hero.jpg',
            'database/site.sql',
            'exports/site.wxr',
        ], $example['sourceEntryOrder']);
        $t->same('Out of order listing in source', $example['error']);
        $t->same([], $example['matches']);
        $t->same('<html>stale cache</html>', $example['cacheLeftUntouched']);
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
    'sync delete mode disables no traverse destination probes' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('exports/site.wxr', '<rss>current</rss>');
        $source->put('database/site.sql', 'insert into wp_posts values (...)');
        $target->put('exports/site.wxr', '<rss>stale</rss>');
        $target->put('database/site.sql', 'insert into wp_posts values (...)');
        $target->put('exports/old-site.wxr', '<rss>old</rss>');

        $plan = new SyncPlan();
        $stats = null;
        $copied = $plan->copyChanged(
            $source,
            $target,
            noTraverse: true,
            noTraverseStats: $stats,
            syncDeleteMode: DeleteMode::AFTER,
        );
        $deleted = $plan->deleteDestinationOnly($source, $target, deleteMode: DeleteMode::AFTER);

        $t->same(['exports/site.wxr'], array_map(static fn ($info) => $info->path, $copied));
        $t->same(['exports/old-site.wxr'], array_map(static fn ($info) => $info->path, $deleted));
        $t->same(false, $stats['enabled']);
        $t->same('sync delete mode requires destination traversal', $stats['disabledReason']);
        $t->same(true, $stats['targetListUsed']);
        $t->same([], $stats['targetLookups']);
        $t->same([], $stats['targetMatches']);
        $t->same([], $stats['targetMisses']);
        $t->same([], $stats['sourceOnlyDirectories']);
        $t->same('<rss>current</rss>', $target->get('exports/site.wxr'));
        $t->throws(RuntimeException::class, static fn () => $target->get('exports/old-site.wxr'));
    },
    'delete before sync uses no traverse only for the copy pass' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('database/site.sql', 'insert into wp_posts values (...)');
        $source->put('exports/site.wxr', '<rss>current</rss>');
        $source->put('wp-content/uploads/2026/05/hero.jpg', 'new image bytes');

        $target->put('database/site.sql', 'insert into wp_posts values (...)');
        $target->put('exports/site.wxr', '<rss>stale</rss>');
        $target->put('exports/old-site.wxr', '<rss>old</rss>');

        $plan = new SyncPlan();
        $stats = null;
        $result = $plan->syncWithDeleteMode(
            $source,
            $target,
            deleteMode: DeleteMode::BEFORE,
            noTraverse: true,
            noTraverseStats: $stats,
        );

        $t->same(['exports/old-site.wxr'], array_map(static fn ($info) => $info->path, $result['deleted']));
        $t->same([
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
        ], array_map(static fn ($info) => $info->path, $result['copied']));
        $t->same(false, $result['deletePassNoTraverse']['enabled']);
        $t->same('sync delete mode requires destination traversal', $result['deletePassNoTraverse']['disabledReason']);
        $t->same(true, $result['deletePassNoTraverse']['targetListUsed']);
        $t->same(true, $stats['enabled']);
        $t->same(null, $stats['disabledReason']);
        $t->same(false, $stats['targetListUsed']);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
        ], $stats['targetLookups']);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
        ], $stats['targetMatches']);
        $t->same(['wp-content/uploads/2026/05/hero.jpg'], $stats['targetMisses']);
        $t->same('<rss>current</rss>', $target->get('exports/site.wxr'));
        $t->throws(RuntimeException::class, static fn () => $target->get('exports/old-site.wxr'));
    },
    'delete before sync moves pruned and overwritten objects through backup dir options' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('database/site.sql', 'insert into wp_posts values (...)');
        $source->put('exports/site.wxr', '<rss>current</rss>');
        $source->put('wp-content/uploads/2026/05/hero.jpg', 'new image bytes');

        $target->put('exports/site.wxr', '<rss>stale published export</rss>');
        $target->put('exports/old-site.wxr', '<rss>old export</rss>');
        $target->put('wp-content/uploads/2024/01/obsolete.jpg', 'obsolete image bytes');

        $plan = new SyncPlan();
        $stats = null;
        $result = $plan->syncWithDeleteMode(
            $source,
            $target,
            deleteMode: DeleteMode::BEFORE,
            noTraverse: true,
            noTraverseStats: $stats,
            backupPrefix: 'archive/2026-05-22',
            suffix: '-previous',
            suffixKeepExtension: true,
            maxDelete: 2,
        );

        $t->same([
            'archive/2026-05-22/exports/old-site-previous.wxr',
            'archive/2026-05-22/wp-content/uploads/2024/01/obsolete-previous.jpg',
        ], array_map(static fn ($info): string => $info->path, $result['deleted']));
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
        ], array_map(static fn ($info): string => $info->path, $result['copied']));
        $t->same(false, $result['deletePassNoTraverse']['enabled']);
        $t->same(true, $stats['enabled']);
        $t->same('<rss>stale published export</rss>', $target->get('archive/2026-05-22/exports/site-previous.wxr'));
        $t->same('<rss>old export</rss>', $target->get('archive/2026-05-22/exports/old-site-previous.wxr'));
        $t->same('obsolete image bytes', $target->get('archive/2026-05-22/wp-content/uploads/2024/01/obsolete-previous.jpg'));
        $t->same('<rss>current</rss>', $target->get('exports/site.wxr'));
        $t->same('insert into wp_posts values (...)', $target->get('database/site.sql'));
        $t->throws(RuntimeException::class, static fn () => $target->get('exports/old-site.wxr'));
        $t->throws(RuntimeException::class, static fn () => $target->get('wp-content/uploads/2024/01/obsolete.jpg'));
    },
    'delete before sync prunes empty destination directories after backup dir moves' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('database/site.sql', 'insert into wp_posts values (...)');
        $source->put('exports/site.wxr', '<rss>current</rss>');
        $source->put('wp-content/uploads/2026/05/hero.jpg', 'new image bytes');

        $target->put('exports/site.wxr', '<rss>stale published export</rss>');
        $target->mkdir('exports/retired');
        $target->put('exports/retired/old-site.wxr', '<rss>old export</rss>');
        $target->mkdir('wp-content/uploads/2024');
        $target->mkdir('wp-content/uploads/2024/01');
        $target->put('wp-content/uploads/2024/01/obsolete.jpg', 'obsolete image bytes');
        $target->mkdir('wp-content/cache');
        $target->put('wp-content/cache/orphan.html', '<html>stale cache</html>');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ exports/retired/**',
            '+ database/*.sql',
            '- *',
        ]);

        $result = (new SyncPlan())->syncWithDeleteMode(
            $source,
            $target,
            $filter,
            deleteMode: DeleteMode::BEFORE,
            backupPrefix: 'archive/2026-05-22',
            suffix: '-previous',
            suffixKeepExtension: true,
        );

        $t->same([
            'archive/2026-05-22/exports/retired/old-site-previous.wxr',
            'archive/2026-05-22/wp-content/uploads/2024/01/obsolete-previous.jpg',
        ], array_map(static fn ($info): string => $info->path, $result['deleted']));
        $t->same([
            'wp-content/uploads/2024/01',
            'wp-content/uploads/2024',
            'exports/retired',
        ], array_map(static fn ($info): string => $info->path, $result['deletePassPrunedDirectories']));
        $t->same([], $result['prunedDirectories']);
        $t->same('<rss>old export</rss>', $target->get('archive/2026-05-22/exports/retired/old-site-previous.wxr'));
        $t->same('obsolete image bytes', $target->get('archive/2026-05-22/wp-content/uploads/2024/01/obsolete-previous.jpg'));
        $t->throws(RuntimeException::class, static fn () => $target->directoryInfo('exports/retired'));
        $t->throws(RuntimeException::class, static fn () => $target->directoryInfo('wp-content/uploads/2024'));
        $t->same('<html>stale cache</html>', $target->get('wp-content/cache/orphan.html'));
        $t->same('archive/2026-05-22/exports/retired', $target->directoryInfo('archive/2026-05-22/exports/retired')->path);
    },
    'rmdirs removes empty subtrees and root candidate like upstream operations' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('A1');
        $provider->mkdir('A1/B1');
        $provider->mkdir('A1/B1/C1');
        $provider->put('A1/B1/C1/one', 'aaa');
        $provider->mkdir('A2');
        $provider->mkdir('A1/B2');
        $provider->mkdir('A1/B2/C2');
        $provider->mkdir('A1/B1/C3');
        $provider->mkdir('A3');
        $provider->mkdir('A3/B3');
        $provider->mkdir('A3/B3/C4');
        $provider->put('A1/two', 'bbb');

        $plan = new SyncPlan();
        $removed = $plan->removeEmptyDirectories($provider, 'A3/B3/C4');
        $t->same(['A3/B3/C4'], array_map(static fn ($info): string => $info->path, $removed));
        $t->same('A3/B3', $provider->directoryInfo('A3/B3')->path);

        $plan->removeEmptyDirectories($provider);
        $t->same([
            'A1',
            'A1/B1',
            'A1/B1/C1',
        ], array_map(static fn ($info): string => $info->path, $provider->directories()));

        $provider->delete('A1/B1/C1/one');
        $provider->delete('A1/two');
        $removed = $plan->removeEmptyDirectories($provider);
        $t->same(['A1/B1/C1', 'A1/B1', 'A1', ''], array_map(static fn ($info): string => $info->path, $removed));
        $t->same([], $provider->directories());
    },
    'rmdirs leave root preserves requested empty root directory' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('A1');
        $provider->mkdir('A1/B1');
        $provider->mkdir('A1/B1/C1');

        $removed = (new SyncPlan())->removeEmptyDirectories($provider, 'A1', leaveRoot: true);

        $t->same(['A1/B1/C1', 'A1/B1'], array_map(static fn ($info): string => $info->path, $removed));
        $t->same(['A1'], array_map(static fn ($info): string => $info->path, $provider->directories()));
    },
    'rmdirs applies include filters to empty directory candidates' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('A1');
        $provider->mkdir('A1/B1');
        $provider->mkdir('A1/B1/C1');

        $filter = FilterRuleSet::fromRules([
            '+ /A1/B1/**',
            '- *',
        ]);
        $removed = (new SyncPlan())->removeEmptyDirectories($provider, filter: $filter);

        $t->same(['A1/B1/C1', 'A1/B1'], array_map(static fn ($info): string => $info->path, $removed));
        $t->same(['A1'], array_map(static fn ($info): string => $info->path, $provider->directories()));
    },
    'rmdirs counts provider rmdir errors caused by filtered out files' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('A1');
        $provider->put('A1/excluded.tmp', 'keep');
        $provider->mkdir('A2');
        $provider->put('A2/excluded.tmp', 'keep');
        $provider->mkdir('A3');

        $filter = FilterRuleSet::fromRules([
            '- *.tmp',
            '+ /A1/**',
            '+ /A2/**',
            '+ /A3/**',
            '- *',
        ]);

        $error = null;
        try {
            (new SyncPlan())->removeEmptyDirectories($provider, filter: $filter);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('failed to remove directories: 2 errors: last error: Directory not empty: A2', $error?->getMessage());
        $t->same('A1', $provider->directoryInfo('A1')->path);
        $t->same('A2', $provider->directoryInfo('A2')->path);
        $t->throws(RuntimeException::class, static fn () => $provider->directoryInfo('A3'));
        $t->same('keep', $provider->get('A1/excluded.tmp'));
        $t->same('keep', $provider->get('A2/excluded.tmp'));
    },
    'try rmdir accounts attempts without counting provider errors' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('dir');
        $provider->mkdir('dir/subdir');
        $provider->put('dir/subdir/keep.txt', 'keep');

        $stats = null;
        $error = null;
        try {
            (new SyncPlan())->tryRemoveDirectory($provider, 'dir/subdir', stats: $stats);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('Directory not empty: dir/subdir', $error?->getMessage());
        $t->same([
            'deletedDirs' => 1,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 0,
        ], $stats);
        $t->same('dir/subdir', $provider->directoryInfo('dir/subdir')->path);
        $t->same('keep', $provider->get('dir/subdir/keep.txt'));
    },
    'rmdir counts missing directory errors after try rmdir accounting' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('dir');

        $stats = null;
        $error = null;
        try {
            (new SyncPlan())->removeDirectory($provider, 'dir/missing', stats: $stats);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('Directory not found: dir/missing', $error?->getMessage());
        $t->same([
            'deletedDirs' => 1,
            'errors' => 1,
            'lastError' => 'Directory not found: dir/missing',
            'dryRunSkipped' => 0,
        ], $stats);
        $t->same('dir', $provider->directoryInfo('dir')->path);
    },
    'rmdir dry run skips provider rmdir including missing directories' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('dir');
        $provider->mkdir('dir/empty');

        $stats = null;
        $plan = new SyncPlan();
        $t->same(null, $plan->removeDirectory($provider, 'dir/missing', dryRun: true, stats: $stats));
        $t->same(null, $plan->removeDirectory($provider, 'dir/empty', dryRun: true, stats: $stats));

        $t->same([
            'deletedDirs' => 2,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 2,
        ], $stats);
        $t->same('dir/empty', $provider->directoryInfo('dir/empty')->path);
    },
    'rmdir removes empty directories and records deleted directory stats' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('dir');
        $provider->mkdir('dir/empty');

        $stats = null;
        $removed = (new SyncPlan())->removeDirectory($provider, 'dir/empty', stats: $stats);

        $t->same('dir/empty', $removed?->path);
        $t->same([
            'deletedDirs' => 1,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 0,
        ], $stats);
        $t->throws(RuntimeException::class, static fn () => $provider->directoryInfo('dir/empty'));
        $t->same('dir', $provider->directoryInfo('dir')->path);
    },
    'purge direct provider dry run skips fallback and provider mutation' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('dir/file.txt', 'content');
        $provider->mkdir('dir/empty');

        $stats = null;
        $result = (new SyncPlan())->purge($provider, 'dir', dryRun: true, stats: $stats);

        $t->same([], array_map(static fn ($info): string => $info->path, $result['objects']));
        $t->same([], array_map(static fn ($info): string => $info->path, $result['directories']));
        $t->same(true, $result['usedDirectPurge']);
        $t->same(false, $result['usedFallback']);
        $t->same(null, $result['directError']);
        $t->same([
            'deletedDirs' => 1,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 1,
            'deletes' => 0,
            'deleteBytes' => 0,
            'dryRunObjectSkipped' => 0,
            'directPurgeAttempts' => 1,
        ], $stats);
        $t->same('content', $provider->get('dir/file.txt'));
        $t->same('dir/empty', $provider->directoryInfo('dir/empty')->path);
    },
    'purge fallback dry run accounts listed objects and empty directories' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(directPurge: false);
        $provider->mkdir('dir');
        $provider->put('dir/one.txt', 'abc');
        $provider->put('dir/two.txt', 'defg');
        $provider->mkdir('dir/empty');

        $stats = null;
        $result = (new SyncPlan())->purge($provider, 'dir', dryRun: true, stats: $stats);

        $t->same(false, $result['usedDirectPurge']);
        $t->same(true, $result['usedFallback']);
        $t->same([], array_map(static fn ($info): string => $info->path, $result['objects']));
        $t->same([], array_map(static fn ($info): string => $info->path, $result['directories']));
        $t->same([
            'deletedDirs' => 1,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 1,
            'deletes' => 2,
            'deleteBytes' => 7,
            'dryRunObjectSkipped' => 2,
            'directPurgeAttempts' => 0,
        ], $stats);
        $t->same('abc', $provider->get('dir/one.txt'));
        $t->same('defg', $provider->get('dir/two.txt'));
        $t->same('dir/empty', $provider->directoryInfo('dir/empty')->path);
    },
    'purge falls back when direct provider returns cant purge' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(directPurgeError: MemoryProvider::ERROR_CANT_PURGE);
        $provider->mkdir('dir');
        $provider->put('dir/file.txt', 'abc');
        $provider->mkdir('dir/empty');

        $stats = null;
        $result = (new SyncPlan())->purge($provider, 'dir', stats: $stats);

        $t->same(false, $result['usedDirectPurge']);
        $t->same(true, $result['usedFallback']);
        $t->same(MemoryProvider::ERROR_CANT_PURGE, $result['directError']);
        $t->same(['dir/file.txt'], array_map(static fn ($info): string => $info->path, $result['objects']));
        $t->same(['dir/empty', 'dir'], array_map(static fn ($info): string => $info->path, $result['directories']));
        $t->same([
            'deletedDirs' => 3,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 0,
            'deletes' => 1,
            'deleteBytes' => 3,
            'dryRunObjectSkipped' => 0,
            'directPurgeAttempts' => 1,
        ], $stats);
        $t->throws(RuntimeException::class, static fn () => $provider->get('dir/file.txt'));
        $t->throws(RuntimeException::class, static fn () => $provider->directoryInfo('dir'));
    },
    'purge direct provider fatal errors are counted without fallback' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(directPurgeError: "can't purge root directory");
        $provider->put('dir/file.txt', 'abc');

        $stats = null;
        $error = null;
        try {
            (new SyncPlan())->purge($provider, 'dir', stats: $stats);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same("can't purge root directory", $error?->getMessage());
        $t->same([
            'deletedDirs' => 1,
            'errors' => 1,
            'lastError' => "can't purge root directory",
            'dryRunSkipped' => 0,
            'deletes' => 0,
            'deleteBytes' => 0,
            'dryRunObjectSkipped' => 0,
            'directPurgeAttempts' => 1,
        ], $stats);
        $t->same('abc', $provider->get('dir/file.txt'));
    },
    'cleanup unsupported providers error before dry run can skip destructiveness' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->putTrashedObject('trash/old-export.wxr', '<rss>old</rss>');

        $stats = null;
        $error = null;
        try {
            (new SyncPlan())->cleanUp($provider, dryRun: true, stats: $stats);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same(MemoryProvider::ERROR_CANT_CLEANUP, $error?->getMessage());
        $t->same([
            'cleanupCalls' => 0,
            'dryRunSkipped' => 0,
            'cleanedObjects' => 0,
            'cleanedDirectories' => 0,
            'errors' => 1,
            'lastError' => MemoryProvider::ERROR_CANT_CLEANUP,
        ], $stats);
        $t->same(0, $provider->cleanUpCalls());
        $t->same(['trash/old-export.wxr'], array_map(static fn ($info): string => $info->path, $provider->trashedObjects()));
    },
    'cleanup dry run skips supported provider cleanup without mutation' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(cleanUp: true);
        $provider->putTrashedObject('trash/old-export.wxr', '<rss>old</rss>');
        $provider->mkdirTrashedDirectory('trash/old-uploads');

        $stats = null;
        $result = (new SyncPlan())->cleanUp($provider, dryRun: true, stats: $stats);

        $t->same(false, $result['providerCalled']);
        $t->same(true, $result['dryRun']);
        $t->same([], array_map(static fn ($info): string => $info->path, $result['objects']));
        $t->same([], array_map(static fn ($info): string => $info->path, $result['directories']));
        $t->same([
            'cleanupCalls' => 0,
            'dryRunSkipped' => 1,
            'cleanedObjects' => 0,
            'cleanedDirectories' => 0,
            'errors' => 0,
            'lastError' => null,
        ], $stats);
        $t->same(0, $provider->cleanUpCalls());
        $t->same(['trash/old-export.wxr'], array_map(static fn ($info): string => $info->path, $provider->trashedObjects()));
        $t->same(['trash/old-uploads'], array_map(static fn ($info): string => $info->path, $provider->trashedDirectories()));
    },
    'cleanup removes provider trash while preserving visible objects' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(cleanUp: true);
        $provider->put('exports/site.wxr', '<rss>current</rss>');
        $provider->putTrashedObject('exports/site.wxr#v1', '<rss>old</rss>');
        $provider->putTrashedObject('wp-content/uploads/2024/01/old.jpg', 'old image');
        $provider->mkdirTrashedDirectory('wp-content/uploads/2024/01');
        $provider->mkdirTrashedDirectory('wp-content/uploads/2024');

        $stats = null;
        $result = (new SyncPlan())->cleanUp($provider, stats: $stats);

        $t->same(true, $result['providerCalled']);
        $t->same(false, $result['dryRun']);
        $t->same([
            'exports/site.wxr#v1',
            'wp-content/uploads/2024/01/old.jpg',
        ], array_map(static fn ($info): string => $info->path, $result['objects']));
        $t->same([
            'wp-content/uploads/2024/01',
            'wp-content/uploads/2024',
        ], array_map(static fn ($info): string => $info->path, $result['directories']));
        $t->same([
            'cleanupCalls' => 1,
            'dryRunSkipped' => 0,
            'cleanedObjects' => 2,
            'cleanedDirectories' => 2,
            'errors' => 0,
            'lastError' => null,
        ], $stats);
        $t->same(1, $provider->cleanUpCalls());
        $t->same('<rss>current</rss>', $provider->get('exports/site.wxr'));
        $t->same([], $provider->trashedObjects());
        $t->same([], $provider->trashedDirectories());
    },
    'cleanup provider errors are counted without clearing trash' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(cleanUp: true, cleanUpError: 'could not empty remote trash');
        $provider->putTrashedObject('trash/old-export.wxr', '<rss>old</rss>');

        $stats = null;
        $error = null;
        try {
            (new SyncPlan())->cleanUp($provider, stats: $stats);
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('could not empty remote trash', $error?->getMessage());
        $t->same([
            'cleanupCalls' => 1,
            'dryRunSkipped' => 0,
            'cleanedObjects' => 0,
            'cleanedDirectories' => 0,
            'errors' => 1,
            'lastError' => 'could not empty remote trash',
        ], $stats);
        $t->same(1, $provider->cleanUpCalls());
        $t->same(['trash/old-export.wxr'], array_map(static fn ($info): string => $info->path, $provider->trashedObjects()));
    },
    'delete before sync stops before copy pass when max delete guard trips' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('database/site.sql', 'insert into wp_posts values (...)');
        $source->put('exports/site.wxr', '<rss>current</rss>');

        $target->put('exports/site.wxr', '<rss>stale published export</rss>');
        $target->put('exports/old-site.wxr', '<rss>old export</rss>');
        $target->put('wp-content/uploads/2024/01/obsolete.jpg', 'obsolete image bytes');

        $plan = new SyncPlan();
        $stats = null;
        $error = null;
        try {
            $plan->syncWithDeleteMode(
                $source,
                $target,
                deleteMode: DeleteMode::BEFORE,
                noTraverse: true,
                noTraverseStats: $stats,
                backupPrefix: 'archive/2026-05-22',
                suffix: '-previous',
                suffixKeepExtension: true,
                maxDelete: 1,
            );
        } catch (RuntimeException $throwable) {
            $error = $throwable;
        }

        $t->same('--max-delete threshold reached', $error?->getMessage());
        $t->same(null, $stats);
        $t->same('<rss>old export</rss>', $target->get('archive/2026-05-22/exports/old-site-previous.wxr'));
        $t->same('obsolete image bytes', $target->get('wp-content/uploads/2024/01/obsolete.jpg'));
        $t->same('<rss>stale published export</rss>', $target->get('exports/site.wxr'));
        $t->throws(RuntimeException::class, static fn () => $target->get('database/site.sql'));
    },
    'wordpress sync no traverse example reports traversal disablement before pruning' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-sync-notraverse-disabled.php';

        $t->same(false, $example['noTraverseEnabled']);
        $t->same('sync delete mode requires destination traversal', $example['noTraverseDisabledReason']);
        $t->same(true, $example['targetListUsed']);
        $t->same([], $example['targetLookups']);
        $t->same([
            'database/site.sql',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], $example['copied']);
        $t->same(['exports/old-site.wxr'], $example['deleted']);
        $t->same('<html>stale cache</html>', $example['cacheLeftUntouched']);
    },
    'wordpress delete before example probes only copy pass destinations' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-delete-before-notraverse-sync.php';

        $t->same(false, $example['deletePassNoTraverseEnabled']);
        $t->same('sync delete mode requires destination traversal', $example['deletePassNoTraverseReason']);
        $t->same(true, $example['copyPassNoTraverseEnabled']);
        $t->same(false, $example['copyPassTargetListUsed']);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], $example['copyPassTargetLookups']);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], $example['copied']);
        $t->same(['exports/old-site.wxr'], $example['deleted']);
        $t->same('<html>stale cache</html>', $example['cacheLeftUntouched']);
    },
    'wordpress delete before backup limit example aborts before copy pass' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-delete-before-backup-limit.php';

        $t->same('--max-delete threshold reached', $example['error']);
        $t->same(false, $example['copyPassRan']);
        $t->same('<rss>old export</rss>', $example['archivedOldExportBytes']);
        $t->same('obsolete image bytes', $example['obsoleteUploadStillPresent']);
        $t->same('<rss>stale published export</rss>', $example['publishedExportBytes']);
        $t->same(false, $example['databaseCopied']);
        $t->same('<html>stale cache</html>', $example['cacheLeftUntouched']);
    },
    'wordpress delete before empty directory prune example keeps backup archive dirs' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-delete-before-empty-dir-prune.php';

        $t->same([
            'wp-content/uploads/2024/01',
            'wp-content/uploads/2024',
            'exports/retired',
        ], $example['prunedDirectories']);
        $t->same('<rss>old export</rss>', $example['archivedOldExportBytes']);
        $t->same('obsolete image bytes', $example['archivedObsoleteUploadBytes']);
        $t->same(false, $example['staleExportDirectoryExists']);
        $t->same(false, $example['staleUploadDirectoryExists']);
        $t->same(true, $example['backupArchiveDirectoryExists']);
        $t->same('<html>stale cache</html>', $example['cacheLeftUntouched']);
    },
    'wordpress rmdirs upload prune example leaves upload root and non-empty months' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-rmdirs-upload-prune.php';

        $t->same([
            'wp-content/uploads/2024/01',
            'wp-content/uploads/2024',
        ], $example['prunedDirectories']);
        $t->same(true, $example['uploadRootExists']);
        $t->same(true, $example['currentMonthExists']);
        $t->same(false, $example['staleMonthExists']);
        $t->same('current image bytes', $example['currentUploadBytes']);
        $t->same('<html>cache</html>', $example['cacheLeftUntouched']);
    },
    'wordpress rmdir dry run preflight example records deletion intent without mutation' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-rmdir-dry-run-preflight.php';

        $t->same(null, $example['dryRunRemoved']);
        $t->same(true, $example['staleLeafExistsAfterDryRun']);
        $t->same('wp-content/uploads/2024/01', $example['removedAfterApply']);
        $t->same(false, $example['staleLeafExistsAfterApply']);
        $t->same('Directory not found: wp-content/uploads/2023/12', $example['missingError']);
        $t->same([
            'deletedDirs' => 2,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 2,
        ], $example['dryRunStats']);
        $t->same([
            'deletedDirs' => 1,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 0,
        ], $example['applyStats']);
        $t->same([
            'deletedDirs' => 1,
            'errors' => 1,
            'lastError' => 'Directory not found: wp-content/uploads/2023/12',
            'dryRunSkipped' => 0,
        ], $example['missingStats']);
        $t->same('current image bytes', $example['currentUploadBytes']);
    },
    'wordpress purge fallback preflight example keeps media through dry run then removes thumbnails' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-purge-fallback-preflight.php';

        $t->same(true, $example['dryRunUsedDirectPurge']);
        $t->same(false, $example['dryRunUsedFallback']);
        $t->same([
            'deletedDirs' => 1,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 1,
            'deletes' => 0,
            'deleteBytes' => 0,
            'dryRunObjectSkipped' => 0,
            'directPurgeAttempts' => 1,
        ], $example['dryRunStats']);
        $t->same(true, $example['thumbnailStillExistsAfterDryRun']);
        $t->same(true, $example['appliedUsedFallback']);
        $t->same(MemoryProvider::ERROR_CANT_PURGE, $example['appliedDirectError']);
        $t->same([
            'wp-content/uploads/2026/05/thumbs/hero-150x150.jpg',
            'wp-content/uploads/2026/05/thumbs/hero-300x300.jpg',
        ], $example['purgedObjects']);
        $t->same(['wp-content/uploads/2026/05/thumbs'], $example['purgedDirectories']);
        $t->same([
            'deletedDirs' => 2,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 0,
            'deletes' => 2,
            'deleteBytes' => 36,
            'dryRunObjectSkipped' => 0,
            'directPurgeAttempts' => 1,
        ], $example['applyStats']);
        $t->same(false, $example['thumbsDirectoryExistsAfterApply']);
        $t->same('current image bytes', $example['currentUploadBytes']);
        $t->same('<rss version="2.0"></rss>', $example['exportPreserved']);
    },
    'wordpress delete command preflight example removes only large cache artifacts' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-delete-command-preflight.php';

        $t->same([], $example['dryRunDeleted']);
        $t->same([
            'listed' => 4,
            'deletes' => 1,
            'deleteBytes' => 120,
            'errors' => 0,
            'lastError' => null,
            'dryRunObjectSkipped' => 1,
        ], $example['dryRunStats']);
        $t->same(true, $example['largeCacheExistsAfterDryRun']);
        $t->same(['wp-content/cache/page/rendered-block-fragment.bin'], $example['appliedDeleted']);
        $t->same([
            'listed' => 4,
            'deletes' => 1,
            'deleteBytes' => 120,
            'errors' => 0,
            'lastError' => null,
            'dryRunObjectSkipped' => 0,
        ], $example['applyStats']);
        $t->same(false, $example['largeCacheExistsAfterApply']);
        $t->same('<html>small cached page</html>', $example['smallCacheBytes']);
        $t->same('current image bytes', $example['currentUploadBytes']);
        $t->same('<rss version="2.0"></rss>', $example['exportPreserved']);
    },
    'wordpress cleanup empty trash example preserves visible backup artifacts' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-cleanup-empty-trash.php';

        $t->same(false, $example['dryRunProviderCalled']);
        $t->same([
            'cleanupCalls' => 0,
            'dryRunSkipped' => 1,
            'cleanedObjects' => 0,
            'cleanedDirectories' => 0,
            'errors' => 0,
            'lastError' => null,
        ], $example['dryRunStats']);
        $t->same([
            'exports/site.wxr#version-2026-05-01',
            'wp-content/uploads/2024/01/retired.jpg',
        ], $example['trashObjectsAfterDryRun']);
        $t->same([
            'exports/site.wxr#version-2026-05-01',
            'wp-content/uploads/2024/01/retired.jpg',
        ], $example['cleanedObjects']);
        $t->same([
            'wp-content/uploads/2024/01',
            'wp-content/uploads/2024',
        ], $example['cleanedDirectories']);
        $t->same([
            'cleanupCalls' => 1,
            'dryRunSkipped' => 0,
            'cleanedObjects' => 2,
            'cleanedDirectories' => 2,
            'errors' => 0,
            'lastError' => null,
        ], $example['applyStats']);
        $t->same([], $example['trashObjectsAfterApply']);
        $t->same([], $example['trashDirectoriesAfterApply']);
        $t->same('current image bytes', $example['currentUploadBytes']);
        $t->same('<rss version="2.0"></rss>', $example['currentExportBytes']);
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
