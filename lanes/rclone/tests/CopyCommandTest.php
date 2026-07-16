<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
    'copy command copies a source file into destination directory with source leaf' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>portable export</rss>', [
            'modTime' => '2026-05-22T01:02:03Z',
        ]);

        $stats = null;
        $result = (new SyncPlan())->copyCommand(
            $remote,
            $local,
            'exports/site.wxr',
            'archive/portable',
            stats: $stats,
        );

        $t->same('copy', $result['command']);
        $t->same('file', $result['sourceType']);
        $t->same('archive/portable/site.wxr', $result['destinationPath']);
        $t->same('archive/portable/site.wxr', $result['file']['copied']?->path);
        $t->same('<rss>portable export</rss>', $remote->get('archive/portable/site.wxr'));
        $t->same('<rss>portable export</rss>', $local->get('exports/site.wxr'));
        $t->same('2026-05-22T01:02:03Z', $remote->info('archive/portable/site.wxr')->modTime);
        $t->same(1, $stats['filesCopied']);
        $t->same(0, $stats['filesSkipped']);
    },
    'copyto command copies a source file to exact destination and backs up overwritten file' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>fresh export</rss>');
        $remote->put('archive/site-import.wxr', '<rss>previous export</rss>');

        $stats = null;
        $result = (new SyncPlan())->copytoCommand(
            $remote,
            $local,
            'exports/site.wxr',
            'archive/site-import.wxr',
            [
                'backupPrefix' => 'backup',
                'suffix' => '.bak',
                'suffixKeepExtension' => true,
            ],
            $stats,
        );

        $t->same('copyto', $result['command']);
        $t->same('file', $result['sourceType']);
        $t->same('archive/site-import.wxr', $result['destinationPath']);
        $t->same('archive/site-import.wxr', $result['file']['copied']?->path);
        $t->same('backup/archive/site-import.bak.wxr', $result['file']['backup']?->path);
        $t->same('<rss>fresh export</rss>', $remote->get('archive/site-import.wxr'));
        $t->same('<rss>previous export</rss>', $remote->get('backup/archive/site-import.bak.wxr'));
        $t->same('<rss>fresh export</rss>', $local->get('exports/site.wxr'));
        $t->same(1, $stats['filesCopied']);
        $t->same(1, $stats['backupsMoved']);
    },
    'copyto command aggregates backup collision accounting into stats' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>fresh export</rss>');
        $remote->put('archive/site-import.wxr', '<rss>previous export</rss>');
        $remote->put('backup/archive/site-import.bak.wxr', '<rss>stale backup</rss>');

        $stats = null;
        $result = (new SyncPlan())->copytoCommand(
            $remote,
            $local,
            'exports/site.wxr',
            'archive/site-import.wxr',
            [
                'backupPrefix' => 'backup',
                'suffix' => '.bak',
                'suffixKeepExtension' => true,
            ],
            $stats,
        );

        $t->same('backup/archive/site-import.bak.wxr', $result['file']['backup']?->path);
        $t->same(1, $stats['backupsMoved']);
        $t->same(1, $stats['backupRenames']);
        $t->same(1, $stats['backupExistingDeletes']);
        $t->same(strlen('<rss>stale backup</rss>'), $stats['backupExistingDeleteBytes']);
        $t->same(2, $stats['backupCheckingTransfers']);
        $t->same('<rss>previous export</rss>', $remote->get('backup/archive/site-import.bak.wxr'));
        $t->same('<rss>fresh export</rss>', $remote->get('archive/site-import.wxr'));
    },
    'copyto dry-run records backup and copy intent without mutating providers' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>fresh export</rss>');
        $remote->put('archive/site-import.wxr', '<rss>previous export</rss>');

        $stats = null;
        $result = (new SyncPlan())->copytoCommand(
            $remote,
            $local,
            'exports/site.wxr',
            'archive/site-import.wxr',
            [
                'suffix' => '.bak',
                'suffixKeepExtension' => true,
                'dryRun' => true,
            ],
            $stats,
        );

        $t->same(true, $result['file']['dryRun']);
        $t->same(['move into backup dir', 'copy to archive/site-import.wxr'], $result['file']['dryRunActions']);
        $t->same('<rss>previous export</rss>', $remote->get('archive/site-import.wxr'));
        $t->same('<rss>fresh export</rss>', $local->get('exports/site.wxr'));
        $t->same(false, $remote->pathExists('archive/site-import.bak.wxr'));
        $t->same(0, $stats['filesCopied']);
        $t->same(0, $stats['backupsMoved']);
        $t->same(2, $stats['dryRunSkipped']);
    },
    'copyto interactive no skips transfer without mutating providers' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>fresh export</rss>');
        $remote->put('archive/site-import.wxr', '<rss>previous export</rss>');

        $seen = [];
        $stats = null;
        $result = (new SyncPlan())->copytoCommand(
            $remote,
            $local,
            'exports/site.wxr',
            'archive/site-import.wxr',
            [
                'interactive' => true,
                'interactiveChoice' => static function (array $context) use (&$seen): string {
                    $seen[] = $context['action'];

                    return 'n';
                },
            ],
            $stats,
        );

        $t->same(['copy to archive/site-import.wxr'], $seen);
        $t->same(true, $result['file']['skipped']);
        $t->same(true, $result['file']['skippedDestructive']);
        $t->same(['copy to archive/site-import.wxr'], $result['file']['skippedActions']);
        $t->same('<rss>previous export</rss>', $remote->get('archive/site-import.wxr'));
        $t->same('<rss>fresh export</rss>', $local->get('exports/site.wxr'));
        $t->same(0, $stats['filesCopied']);
        $t->same(1, $stats['filesSkipped']);
        $t->same(1, $stats['destructiveSkipped']);
    },
    'copyto interactive skip-all caches backup action while allowing copies' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site-a.wxr', '<rss>fresh a</rss>');
        $local->put('exports/site-b.wxr', '<rss>fresh b</rss>');
        $remote->put('archive/site-a.wxr', '<rss>previous a</rss>');
        $remote->put('archive/site-b.wxr', '<rss>previous b</rss>');

        $seen = [];
        $plan = new SyncPlan();
        $options = [
            'suffix' => '.bak',
            'interactive' => true,
            'interactiveChoice' => static function (array $context) use (&$seen): string {
                $seen[] = $context['action'];

                return $context['action'] === 'move into backup dir' ? 's' : 'y';
            },
        ];

        $firstStats = null;
        $first = $plan->copytoCommand($remote, $local, 'exports/site-a.wxr', 'archive/site-a.wxr', $options, $firstStats);
        $secondStats = null;
        $second = $plan->copytoCommand($remote, $local, 'exports/site-b.wxr', 'archive/site-b.wxr', $options, $secondStats);

        $t->same([
            'move into backup dir',
            'copy to archive/site-a.wxr',
            'copy to archive/site-b.wxr',
        ], $seen);
        $t->same(['move into backup dir'], $first['file']['skippedActions']);
        $t->same(['move into backup dir'], $second['file']['skippedActions']);
        $t->same('<rss>fresh a</rss>', $remote->get('archive/site-a.wxr'));
        $t->same('<rss>fresh b</rss>', $remote->get('archive/site-b.wxr'));
        $t->same(false, $remote->pathExists('archive/site-a.wxr.bak'));
        $t->same(false, $remote->pathExists('archive/site-b.wxr.bak'));
        $t->same(1, $firstStats['filesCopied']);
        $t->same(1, $secondStats['filesCopied']);
        $t->same(1, $firstStats['destructiveSkipped']);
        $t->same(1, $secondStats['destructiveSkipped']);
    },
    'copyto interactive do-all caches transfer action' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>fresh one</rss>');
        $remote->put('archive/site.wxr', '<rss>previous</rss>');

        $seen = [];
        $plan = new SyncPlan();
        $options = [
            'interactive' => true,
            'interactiveChoice' => static function (array $context) use (&$seen): string {
                $seen[] = $context['action'];

                return '!';
            },
        ];

        $firstStats = null;
        $first = $plan->copytoCommand($remote, $local, 'exports/site.wxr', 'archive/site.wxr', $options, $firstStats);
        $local->put('exports/site.wxr', '<rss>fresh two</rss>');
        $secondStats = null;
        $second = $plan->copytoCommand($remote, $local, 'exports/site.wxr', 'archive/site.wxr', $options, $secondStats);

        $t->same(['copy to archive/site.wxr'], $seen);
        $t->same('archive/site.wxr', $first['file']['copied']?->path);
        $t->same('archive/site.wxr', $second['file']['copied']?->path);
        $t->same('<rss>fresh two</rss>', $remote->get('archive/site.wxr'));
        $t->same(1, $firstStats['filesCopied']);
        $t->same(1, $secondStats['filesCopied']);
        $t->same(0, $firstStats['destructiveSkipped']);
        $t->same(0, $secondStats['destructiveSkipped']);
    },
    'copy command directory copies contents and optional empty source dirs without deleting source' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $destination = new MemoryProvider();
        $source->mkdir('wp-content/uploads', ['metadata' => ['root' => 'not-synced']]);
        $source->mkdir('wp-content/uploads/2026/05', [
            'modTime' => '2026-05-20T00:00:00Z',
            'metadata' => ['wp-scope' => 'uploads-month'],
        ]);
        $source->mkdir('wp-content/uploads/empty-review', [
            'metadata' => ['wp-empty' => '1'],
        ]);
        $source->put('wp-content/uploads/2026/05/hero.jpg', 'image bytes');
        $source->put('wp-content/cache/page.html', 'cache bytes');

        $stats = null;
        $result = (new SyncPlan())->copyCommand(
            $destination,
            $source,
            'wp-content/uploads',
            'archive/uploads',
            [
                'createEmptySrcDirs' => true,
                'filter' => FilterRuleSet::fromRules([
                    '- wp-content/cache/**',
                    '+ *',
                ]),
            ],
            $stats,
        );

        $t->same('directory', $result['sourceType']);
        $t->same(['archive/uploads/2026/05/hero.jpg'], array_map(static fn ($info): string => $info->path, $result['directory']['copied']));
        $t->same('image bytes', $destination->get('archive/uploads/2026/05/hero.jpg'));
        $t->same(['wp-empty' => '1'], $destination->directoryInfo('archive/uploads/empty-review')->metadata);
        $t->same('2026-05-20T00:00:00Z', $destination->directoryInfo('archive/uploads/2026/05')->modTime);
        $t->same('image bytes', $source->get('wp-content/uploads/2026/05/hero.jpg'));
        $t->same(['wp-empty' => '1'], $source->directoryInfo('wp-content/uploads/empty-review')->metadata);
        $t->throws(RuntimeException::class, static fn () => $destination->get('archive/uploads/cache/page.html'));
        $t->same('cache bytes', $source->get('wp-content/cache/page.html'));
        $t->same(1, $stats['filesCopied']);
        $t->true($stats['createdDirectories'] >= 2);
    },
    'copy command directory dry-run skips placeholder and object writes' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $destination = new MemoryProvider();
        $source->mkdir('site/empty-review', ['metadata' => ['wp-empty' => '1']]);
        $source->put('site/hero.jpg', 'image bytes');

        $stats = null;
        $result = (new SyncPlan())->copyCommand(
            $destination,
            $source,
            'site',
            'publish',
            [
                'createEmptySrcDirs' => true,
                'dryRun' => true,
            ],
            $stats,
        );

        $t->same([], $result['directory']['copied']);
        $t->same([], $result['directory']['createdDirectories']);
        $t->same(['copy to publish/hero.jpg'], $result['directory']['fileResults'][0]['dryRunActions']);
        $t->same(false, $destination->pathExists('publish/hero.jpg'));
        $t->throws(RuntimeException::class, static fn () => $destination->directoryInfo('publish/empty-review'));
        $t->same('image bytes', $source->get('site/hero.jpg'));
        $t->same(['wp-empty' => '1'], $source->directoryInfo('site/empty-review')->metadata);
        $t->same(0, $stats['filesCopied']);
        $t->same(0, $stats['createdDirectories']);
        $t->same(2, $stats['dryRunSkipped']);
        $t->same(1, $stats['dryRunDirectoriesSkipped']);
    },
    'copyto directory acts like copy dir without creating empty source dirs' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $destination = new MemoryProvider();
        $source->mkdir('site/empty-review', [
            'metadata' => ['wp-empty' => '1'],
        ]);
        $source->put('site/exports/site.wxr', '<rss>portable export</rss>');
        $source->put('site/wp-content/uploads/2026/05/hero.jpg', 'image bytes');

        $stats = null;
        $result = (new SyncPlan())->copytoCommand(
            $destination,
            $source,
            'site',
            'restore',
            stats: $stats,
        );

        $t->same('directory', $result['sourceType']);
        $t->same([
            'restore/exports/site.wxr',
            'restore/wp-content/uploads/2026/05/hero.jpg',
        ], array_map(static fn ($info): string => $info->path, $result['directory']['copied']));
        $t->same('<rss>portable export</rss>', $destination->get('restore/exports/site.wxr'));
        $t->same('image bytes', $destination->get('restore/wp-content/uploads/2026/05/hero.jpg'));
        $t->throws(RuntimeException::class, static fn () => $destination->directoryInfo('restore/empty-review'));
        $t->same(['wp-empty' => '1'], $source->directoryInfo('site/empty-review')->metadata);
        $t->same(2, $stats['filesCopied']);
        $t->same(0, $stats['createdDirectories']);
    },
    'wordpress copy command media promotion example preserves command boundaries' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-copy-command-media-promotion.php';

        $t->same('publish/media/hero.jpg', $example['copyDestination']);
        $t->same('publish/media/hero-renamed.jpg', $example['copytoDestination']);
        $t->same('publish/uploads/2026/05/hero.jpg', $example['directoryCopiedPaths'][0]);
        $t->same('publish/media/hero-renamed.bak.jpg', $example['backupPath']);
        $t->same(false, $example['cacheCopied']);
        $t->same(true, $example['sourcePreserved']);
        $t->same(3, $example['filesCopied']);
    },
    'wordpress copy and move dry-run preflight example preserves provider state' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-copy-move-dry-run-preflight.php';

        $t->same(['move into backup dir', 'copy to publish/media/hero-renamed.jpg'], $example['copytoDryRunActions']);
        $t->same(['move into backup dir', 'move to archive/media/hero-renamed.jpg'], $example['movetoDryRunActions']);
        $t->same(['copy to publish/uploads/hero.jpg'], $example['directoryCopyDryRunActions']);
        $t->same(true, $example['remotePublishPreserved']);
        $t->same(true, $example['remoteArchivePreserved']);
        $t->same(true, $example['sourcePreserved']);
        $t->same(false, $example['dryRunCreatedBackup']);
        $t->same(6, $example['dryRunSkipped']);
    },
    'wordpress interactive copy and move preflight example honors destructive choices' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-interactive-copy-move-preflight.php';

        $t->same(['copy to publish/media/hero-renamed.jpg'], $example['copySkippedActions']);
        $t->same('<rss>previous publish</rss>', $example['publishBytes']);
        $t->same('archive bytes', $example['archiveBytes']);
        $t->same(false, $example['archiveBackupCreated']);
        $t->same(true, $example['copySourcePreserved']);
        $t->same(false, $example['moveSourcePreserved']);
        $t->same([
            'copy to publish/media/hero-renamed.jpg',
            'move into backup dir',
            'move to archive/media/hero-renamed.jpg',
        ], $example['choicesSeen']);
    },
];
