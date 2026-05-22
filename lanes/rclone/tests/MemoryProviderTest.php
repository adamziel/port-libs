<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\Glob;
use PortLibs\Rclone\SyncPlan;

return [
    'memory provider stores object metadata and copies content' => static function (TestRunner $t): void {
        $a = new MemoryProvider();
        $b = new MemoryProvider();
        $info = $a->put('/site/export.wxr', 'content', ['modTime' => '2026-05-22T01:02:03Z']);
        $a->copyTo('site/export.wxr', $b, 'backup/export.wxr');
        $t->same(7, $info->size);
        $t->same('content', $b->get('backup/export.wxr'));
        $t->same('2026-05-22T01:02:03Z', $b->info('backup/export.wxr')->modTime);
    },
    'memory provider reads objects with upstream seek and range open semantics' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $contents = implode('', array_map(
            static fn (int $i): string => chr(ord('a') + ($i % 26)),
            range(0, 99),
        ));
        $provider->put('file name.txt', $contents);

        $t->same($contents, $provider->readObject('file name.txt'));
        $t->same(substr($contents, 50), $provider->readObject('file name.txt', ['seekOffset' => 50]));
        $t->same($contents, $provider->readObject('file name.txt', ['seekOffset' => -100]));
        $t->same(substr($contents, 5, 11), $provider->readObject('file name.txt', ['rangeStart' => 5, 'rangeEnd' => 15]));
        $t->same(substr($contents, 80), $provider->readObject('file name.txt', ['rangeStart' => 80, 'rangeEnd' => -1]));
        $t->same(substr($contents, 81), $provider->readObject('file name.txt', ['rangeStart' => 81, 'rangeEnd' => 100000]));
        $t->same(substr($contents, 80), $provider->readObject('file name.txt', ['rangeStart' => -1, 'rangeEnd' => 20]));
    },
    'memory provider update keeps remote path while replacing bytes and metadata' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', '<rss>old export</rss>', [
            'modTime' => '2026-05-21T00:00:00Z',
            'mimeType' => 'application/rss+xml',
            'metadata' => ['wp-version' => '6.8'],
        ]);

        $updated = $provider->updateObject('exports/site.wxr', '<rss>fresh export</rss>', [
            'sourcePath' => 'temporary-upload-name-should-be-ignored.bin',
            'modTime' => '2026-05-22T00:00:00Z',
            'mimeType' => 'application/xml',
            'metadata' => ['wp-version' => '6.9', 'rclonetest' => 'potato'],
        ]);

        $t->same('exports/site.wxr', $updated->path);
        $t->same(strlen('<rss>fresh export</rss>'), $updated->size);
        $t->same('2026-05-22T00:00:00Z', $updated->modTime);
        $t->same('application/xml', $updated->mimeType);
        $t->same(['wp-version' => '6.9', 'rclonetest' => 'potato'], $updated->metadata);
        $t->same('<rss>fresh export</rss>', $provider->get('exports/site.wxr'));
        $t->throws(RuntimeException::class, static fn () => $provider->get('temporary-upload-name-should-be-ignored.bin'));
    },
    'memory provider put stream and update accept unknown source sizes without unknown result size' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();

        $put = $provider->putStream('exports/piped-data.wxr', '<rss>streamed export</rss>', [
            'unknownSize' => true,
            'modTime' => '2026-05-22T01:00:00Z',
        ]);
        $provider->put('exports/site.wxr', '<rss>old export</rss>');
        $updated = $provider->updateObject('exports/site.wxr', '<rss>updated from stream</rss>', [
            'unknownSize' => true,
            'sourcePath' => 'ignored-stream-name',
        ]);

        $t->same(strlen('<rss>streamed export</rss>'), $put->size);
        $t->same(strlen('<rss>updated from stream</rss>'), $updated->size);
        $t->same('<rss>streamed export</rss>', $provider->get('exports/piped-data.wxr'));
        $t->same('<rss>updated from stream</rss>', $provider->get('exports/site.wxr'));
    },
    'memory provider can model case-insensitive provider object lookup' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(true);
        $provider->put('wp-content/uploads/2026/05/Hero.JPG', 'image bytes');
        $provider->put('DATABASE/SITE.SQL', 'sql bytes');

        $t->true($provider->isCaseInsensitive());
        $t->same('image bytes', $provider->get('WP-CONTENT/UPLOADS/2026/05/hero.jpg'));
        $t->same('wp-content/uploads/2026/05/Hero.JPG', $provider->info('wp-content/uploads/2026/05/hero.jpg')->path);
        $t->same('DATABASE/SITE.SQL', $provider->info('database/site.sql')->path);

        $provider->put('database/site.sql', 'new sql bytes');
        $t->same('new sql bytes', $provider->get('DATABASE/SITE.SQL'));
        $t->same(['database/site.sql'], array_map(static fn ($info) => $info->path, $provider->list('DATABASE')));
    },
    'memory provider creates explicit directories with upstream modtime metadata boundaries' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(true);
        $provider->mkdirModTime('wp-content/uploads/2026/05', '2026-05-22T00:00:00Z');
        $provider->put('wp-content/uploads/2026/05/Hero.JPG', 'image bytes');
        $provider->put('exports/site.wxr', '<rss></rss>');

        $t->same([
            'exports',
            'wp-content',
            'wp-content/uploads',
            'wp-content/uploads/2026',
            'wp-content/uploads/2026/05',
        ], array_map(static fn ($info) => $info->path, $provider->directories()));
        $t->same('2026-05-22T00:00:00Z', $provider->directoryInfo('WP-CONTENT/UPLOADS/2026/05')->modTime);

        $provider->setDirectoryModTime('WP-CONTENT/UPLOADS/2026/05', '2026-05-23T00:00:00Z');
        $t->same('2026-05-23T00:00:00Z', $provider->directoryInfo('wp-content/uploads/2026/05')->modTime);
        $t->same(null, $provider->directoryInfo('wp-content/uploads/2026')->modTime);
    },
    'set directory modtime obeys upstream no update and missing directory behavior' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('sub/file.txt', 'content');

        $t->same(null, $provider->setDirectoryModTime('sub', '2026-05-22T00:00:00Z', true));
        $t->same(null, $provider->directoryInfo('sub')->modTime);

        $updated = $provider->setDirectoryModTime('sub', '2026-05-22T00:00:00Z');
        $t->same('sub', $updated?->path);
        $t->same('2026-05-22T00:00:00Z', $provider->directoryInfo('sub')->modTime);
        $t->throws(RuntimeException::class, static fn () => $provider->setDirectoryModTime('missing', '2026-05-22T00:00:00Z'));
    },
    'sync plan reports missing and checksum changed paths' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('a.txt', 'one');
        $source->put('b.txt', 'two');
        $target->put('a.txt', 'changed');
        $t->same(['a.txt', 'b.txt'], (new SyncPlan())->changedPaths($source, $target));
    },
    'rclone path globs compile like upstream filter glob tests' => static function (TestRunner $t): void {
        $t->same('(^|/)potato$', Glob::pathToRegex('potato'));
        $t->same('^potato$', Glob::pathToRegex('/potato'));
        $t->same('(^|/)[^/]*\.jpg$', Glob::pathToRegex('*.jpg'));
        $t->same('(^|/)a(b|c|d)e$', Glob::pathToRegex('a{b,c,d}e'));
        $t->same('(^|/)potato.*sausage$', Glob::pathToRegex('potato**sausage'));
        $t->same('(?i)(^|/)[^/]*\.jpg$', Glob::pathToRegex('*.jpg', true));
    },
    'rclone path globs reject upstream invalid patterns' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => Glob::pathToRegex('***'));
        $t->throws(InvalidArgumentException::class, static fn () => Glob::pathToRegex('ab]c'));
        $t->throws(InvalidArgumentException::class, static fn () => Glob::pathToRegex('ab[c'));
        $t->throws(InvalidArgumentException::class, static fn () => Glob::pathToRegex('ab{c'));
    },
    'filter rules honor upstream first match include exclude order' => static function (TestRunner $t): void {
        $filter = FilterRuleSet::fromRules([
            '+ cleared',
            '!',
            '- /file1.jpg',
            '+ /file2.png',
            '+ /*.jpg',
            '- /*.png',
            '- /potato',
            '+ /sausage1',
            '+ /sausage2*',
            '+ /sausage3**',
            '+ /a/*.jpg',
            '- *',
        ]);

        $t->same(false, $filter->includes('cleared'));
        $t->same(false, $filter->includes('file1.jpg'));
        $t->same(true, $filter->includes('file2.png'));
        $t->same(false, $filter->includes('FILE2.png'));
        $t->same(false, $filter->includes('afile2.png'));
        $t->same(true, $filter->includes('file3.jpg'));
        $t->same(false, $filter->includes('file4.png'));
        $t->same(false, $filter->includes('potato'));
        $t->same(true, $filter->includes('sausage1'));
        $t->same(false, $filter->includes('sausage1/potato'));
        $t->same(true, $filter->includes('sausage2potato'));
        $t->same(false, $filter->includes('sausage2/potato'));
        $t->same(true, $filter->includes('sausage3/potato'));
        $t->same(true, $filter->includes('a/one.jpg'));
        $t->same(false, $filter->includes('a/one.png'));
        $t->same(false, $filter->includes('unicorn'));
    },
    'filter rules can ignore case like rclone filter option' => static function (TestRunner $t): void {
        $filter = FilterRuleSet::fromRules([
            '+ /file2.png',
            '+ /sausage3**',
            '- *',
        ], true);

        $t->same(true, $filter->includes('file2.png'));
        $t->same(true, $filter->includes('FILE2.png'));
        $t->same(true, $filter->includes('SAUSAGE3/sub'));
    },
    'sync plan applies rclone filters to WordPress backup objects' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        foreach ($tree as $path => $bytes) {
            $source->put($path, $bytes);
        }
        $target->put('wp-content/uploads/2026/05/hero.jpg', 'old image bytes');

        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '- *.log',
            '- *.psd',
            '+ wp-content/uploads/**',
            '+ exports/*.wxr',
            '+ database/*.sql',
            '- *',
        ]);

        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], (new SyncPlan())->changedPaths($source, $target, $filter));
    },
    'wordpress fstest object open update example exposes WXR stream boundaries' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-fstest-object-open-update.php';

        $t->same('<rss ', $example['rangePreview']);
        $t->same('</rss>', $example['rangeTail']);
        $t->same('exports/site.wxr', $example['updatedPath']);
        $t->same(false, $example['ignoredSourceVisible']);
        $t->same(strlen('<rss version="2.0"><channel><item>post</item></channel></rss>'), $example['updatedSize']);
        $t->same(strlen('<rss version="2.0"></rss>'), $example['putStreamSize']);
    },
];
