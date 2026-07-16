<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
    'move command moves a source file into destination directory with source leaf' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>portable export</rss>', [
            'modTime' => '2026-05-22T01:02:03Z',
        ]);

        $stats = null;
        $result = (new SyncPlan())->moveCommand(
            $remote,
            $local,
            'exports/site.wxr',
            'archive/portable',
            stats: $stats,
        );

        $t->same('move', $result['command']);
        $t->same('file', $result['sourceType']);
        $t->same('archive/portable/site.wxr', $result['destinationPath']);
        $t->same('archive/portable/site.wxr', $result['file']['moved']?->path);
        $t->same('<rss>portable export</rss>', $remote->get('archive/portable/site.wxr'));
        $t->same('2026-05-22T01:02:03Z', $remote->info('archive/portable/site.wxr')->modTime);
        $t->throws(RuntimeException::class, static fn () => $local->get('exports/site.wxr'));
        $t->same(1, $stats['filesMoved']);
        $t->same(1, $stats['filesDeletedFromSource']);
    },
    'moveto command renames a source file to exact destination and backs up overwritten file' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>fresh export</rss>');
        $remote->put('archive/site-import.wxr', '<rss>previous export</rss>');

        $stats = null;
        $result = (new SyncPlan())->movetoCommand(
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

        $t->same('moveto', $result['command']);
        $t->same('file', $result['sourceType']);
        $t->same('archive/site-import.wxr', $result['destinationPath']);
        $t->same('archive/site-import.wxr', $result['file']['moved']?->path);
        $t->same('backup/archive/site-import.bak.wxr', $result['file']['backup']?->path);
        $t->same('<rss>fresh export</rss>', $remote->get('archive/site-import.wxr'));
        $t->same('<rss>previous export</rss>', $remote->get('backup/archive/site-import.bak.wxr'));
        $t->throws(RuntimeException::class, static fn () => $local->get('exports/site.wxr'));
        $t->same(1, $stats['backupsMoved']);
    },
    'moveto dry-run records backup and move intent without mutating providers' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>fresh export</rss>');
        $remote->put('archive/site-import.wxr', '<rss>previous export</rss>');

        $stats = null;
        $result = (new SyncPlan())->movetoCommand(
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
        $t->same(['move into backup dir', 'move to archive/site-import.wxr'], $result['file']['dryRunActions']);
        $t->same('<rss>previous export</rss>', $remote->get('archive/site-import.wxr'));
        $t->same('<rss>fresh export</rss>', $local->get('exports/site.wxr'));
        $t->same(false, $remote->pathExists('archive/site-import.bak.wxr'));
        $t->same(0, $stats['filesMoved']);
        $t->same(0, $stats['filesDeletedFromSource']);
        $t->same(0, $stats['backupsMoved']);
        $t->same(2, $stats['dryRunSkipped']);
    },
    'moveto interactive skip after backup preserves source and archived destination' => static function (TestRunner $t): void {
        $local = new MemoryProvider();
        $remote = new MemoryProvider();
        $local->put('exports/site.wxr', '<rss>fresh export</rss>');
        $remote->put('archive/site-import.wxr', '<rss>previous export</rss>');

        $seen = [];
        $stats = null;
        $result = (new SyncPlan())->movetoCommand(
            $remote,
            $local,
            'exports/site.wxr',
            'archive/site-import.wxr',
            [
                'backupPrefix' => 'backup',
                'suffix' => '.bak',
                'suffixKeepExtension' => true,
                'interactive' => true,
                'interactiveChoice' => static function (array $context) use (&$seen): string {
                    $seen[] = $context['action'];

                    return $context['action'] === 'move into backup dir' ? 'y' : 'n';
                },
            ],
            $stats,
        );

        $t->same(['move into backup dir', 'move to archive/site-import.wxr'], $seen);
        $t->same(['move to archive/site-import.wxr'], $result['file']['skippedActions']);
        $t->same(null, $result['file']['moved']);
        $t->same('backup/archive/site-import.bak.wxr', $result['file']['backup']?->path);
        $t->same('<rss>previous export</rss>', $remote->get('backup/archive/site-import.bak.wxr'));
        $t->same('<rss>fresh export</rss>', $local->get('exports/site.wxr'));
        $t->same(false, $remote->pathExists('archive/site-import.wxr'));
        $t->same(0, $stats['filesMoved']);
        $t->same(0, $stats['filesDeletedFromSource']);
        $t->same(1, $stats['backupsMoved']);
        $t->same(1, $stats['destructiveSkipped']);
    },
    'move command directory fallback creates empty destination dirs and prunes empty source dirs' => static function (TestRunner $t): void {
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
        $result = (new SyncPlan())->moveCommand(
            $destination,
            $source,
            'wp-content/uploads',
            'archive/uploads',
            [
                'createEmptySrcDirs' => true,
                'deleteEmptySrcDirs' => true,
                'filter' => FilterRuleSet::fromRules([
                    '- wp-content/cache/**',
                    '+ *',
                ]),
            ],
            $stats,
        );

        $t->same('directory', $result['sourceType']);
        $t->same(false, $result['directory']['usedDirMove']);
        $t->same(['archive/uploads/2026/05/hero.jpg'], array_map(static fn ($info): string => $info->path, $result['directory']['moved']));
        $t->same('image bytes', $destination->get('archive/uploads/2026/05/hero.jpg'));
        $t->same(['wp-empty' => '1'], $destination->directoryInfo('archive/uploads/empty-review')->metadata);
        $t->same('2026-05-20T00:00:00Z', $destination->directoryInfo('archive/uploads/2026/05')->modTime);
        $t->throws(RuntimeException::class, static fn () => $source->get('wp-content/uploads/2026/05/hero.jpg'));
        $t->throws(RuntimeException::class, static fn () => $source->directoryInfo('wp-content/uploads/empty-review'));
        $t->same('cache bytes', $source->get('wp-content/cache/page.html'));
        $t->same(1, $stats['filesMoved']);
        $t->same(1, $stats['filesDeletedFromSource']);
        $t->true($stats['createdDirectories'] >= 2);
        $t->true($stats['prunedSourceDirectories'] >= 2);
    },
    'moveto directory uses provider directory move when source and destination share a provider' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('wp-content/uploads/2026/05', [
            'metadata' => ['wp-scope' => 'uploads-month'],
        ]);
        $provider->put('wp-content/uploads/2026/05/hero.jpg', 'image bytes');

        $stats = null;
        $result = (new SyncPlan())->movetoCommand(
            $provider,
            $provider,
            'wp-content/uploads',
            'archive/uploads',
            stats: $stats,
        );

        $t->same('directory', $result['sourceType']);
        $t->same(true, $result['directory']['usedDirMove']);
        $t->same(['archive/uploads'], array_map(static fn ($info): string => $info->path, $result['directory']['moved']));
        $t->same('image bytes', $provider->get('archive/uploads/2026/05/hero.jpg'));
        $t->same(['wp-scope' => 'uploads-month'], $provider->directoryInfo('archive/uploads/2026/05')->metadata);
        $t->throws(RuntimeException::class, static fn () => $provider->directoryInfo('wp-content/uploads'));
        $t->same(true, $stats['usedDirMove']);
    },
    'case-insensitive moveto dry-run records rename intent without changing casing' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(caseInsensitive: true);
        $provider->put('hello', 'world');

        $stats = null;
        $result = (new SyncPlan())->movetoCommand(
            $provider,
            $provider,
            'hello',
            'HELLO',
            ['dryRun' => true],
            $stats,
        );

        $t->same(true, $result['file']['dryRun']);
        $t->same(['rename to HELLO'], $result['file']['dryRunActions']);
        $t->same(['hello'], array_map(static fn ($info): string => $info->path, $provider->list()));
        $t->same('world', $provider->get('hello'));
        $t->same(0, $stats['filesMoved']);
        $t->same(0, $stats['filesDeletedFromSource']);
        $t->same(1, $stats['dryRunSkipped']);
    },
    'wordpress move command media relocation example preserves command boundaries' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-move-command-media-relocation.php';

        $t->same('archive/media/hero.jpg', $example['moveDestination']);
        $t->same('archive/media/hero-renamed.jpg', $example['movetoDestination']);
        $t->same('archive/uploads/2026/05/hero.jpg', $example['directoryMovedPaths'][0]);
        $t->same('archive/media/hero-renamed.bak.jpg', $example['backupPath']);
        $t->same(false, $example['cacheMoved']);
        $t->same(3, $example['filesMoved']);
        $t->same(3, $example['sourceDeletes']);
    },
];
