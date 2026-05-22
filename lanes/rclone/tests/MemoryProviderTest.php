<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\Glob;
use PortLibs\Rclone\RootedMemoryProvider;
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
    'memory provider walks direct and bounded-depth listings like upstream fstest' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('file name.txt', 'root file');
        $provider->put('nested/other.txt', 'nested file');
        $provider->put('nested/second/deep/file.txt', 'deep file');

        $level1 = $provider->walk('', 1);
        $t->same(['file name.txt'], array_map(static fn ($info) => $info->path, $level1['objects']));
        $t->same(['nested'], array_map(static fn ($info) => $info->path, $level1['directories']));

        $level2 = $provider->walk('', 2);
        $t->same(['file name.txt', 'nested/other.txt'], array_map(static fn ($info) => $info->path, $level2['objects']));
        $t->same(['nested', 'nested/second'], array_map(static fn ($info) => $info->path, $level2['directories']));

        $subdir = $provider->walk('nested', 1);
        $t->same(['nested/other.txt'], array_map(static fn ($info) => $info->path, $subdir['objects']));
        $t->same(['nested/second'], array_map(static fn ($info) => $info->path, $subdir['directories']));
        $t->throws(RuntimeException::class, static fn () => $provider->walk('does not exist', 1));
    },
    'memory provider purges subtrees while preserving unrelated objects' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('file name.txt', 'root file');
        $provider->put('dirToPurge/fileToPurge.txt', 'purge me');
        $provider->mkdir('dirToPurge/empty-child');
        $provider->put('kept/side.txt', 'keep me');

        $purged = $provider->purge('dirToPurge');

        $t->same(['dirToPurge/fileToPurge.txt'], array_map(static fn ($info) => $info->path, $purged['objects']));
        $t->same(['dirToPurge/empty-child', 'dirToPurge'], array_map(static fn ($info) => $info->path, $purged['directories']));
        $t->same(['file name.txt', 'kept/side.txt'], array_map(static fn ($info) => $info->path, $provider->list()));
        $t->throws(RuntimeException::class, static fn () => $provider->purge('dirToPurge'));
    },
    'rooted memory provider rebases listings and purges through the shared backing provider' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('site/wp-content/uploads/2026/05/hero.jpg', 'hero image');
        $provider->put('site/wp-content/uploads/2026/05/thumbs/hero-150x150.jpg', 'thumb image');
        $provider->put('site/exports/site.wxr', '<rss></rss>');

        $rooted = new RootedMemoryProvider($provider, 'site/wp-content/uploads/2026/05');
        $rooted->put('gallery.jpg', 'gallery image');
        $direct = $rooted->walk('', 1);

        $t->same('site/wp-content/uploads/2026/05', $rooted->root());
        $t->same(['gallery.jpg', 'hero.jpg'], array_map(static fn ($info) => $info->path, $direct['objects']));
        $t->same(['thumbs'], array_map(static fn ($info) => $info->path, $direct['directories']));

        $purgedThumbs = $rooted->purge('thumbs');
        $t->same(['thumbs/hero-150x150.jpg'], array_map(static fn ($info) => $info->path, $purgedThumbs['objects']));
        $t->throws(RuntimeException::class, static fn () => $provider->get('site/wp-content/uploads/2026/05/thumbs/hero-150x150.jpg'));
        $t->same('gallery image', $provider->get('site/wp-content/uploads/2026/05/gallery.jpg'));

        $rooted->purge('');
        $t->throws(RuntimeException::class, static fn () => $provider->get('site/wp-content/uploads/2026/05/gallery.jpg'));
        $t->same('<rss></rss>', $provider->get('site/exports/site.wxr'));
    },
    'memory provider public links map upstream fstest file directory and missing boundaries' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('file name.txt', 'root file');
        $provider->put('nested/other.txt', 'nested file');

        $fileLink = $provider->publicLink('file name.txt', 120);
        $secondFileLink = $provider->publicLink('nested/other.txt', 120);
        $directoryLink = $provider->publicLink('nested', 120);

        $t->true(str_starts_with($fileLink, 'https://rclone.local/share/object/'));
        $t->true(str_starts_with($directoryLink, 'https://rclone.local/share/directory/'));
        $t->true($fileLink !== $secondFileLink);
        $t->true($fileLink !== $directoryLink);
        $t->same($fileLink, $provider->publicLink('file name.txt', 120));
        $t->same('', $provider->publicLink('file name.txt', 120, true));
        $t->same($fileLink, $provider->publicLink('file name.txt', 120));
        $t->throws(RuntimeException::class, static fn () => $provider->publicLink('missing.txt', 120));
    },
    'rooted memory provider public link can share the subremote root' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('site/wp-content/uploads/2026/05/hero.jpg', 'hero image');
        $provider->put('site/exports/site.wxr', '<rss></rss>');

        $rooted = new RootedMemoryProvider($provider, 'site/wp-content/uploads/2026/05');
        $rootLink = $rooted->publicLink('', 120);
        $fileLink = $rooted->publicLink('hero.jpg', 120);

        $t->true(str_starts_with($rootLink, 'https://rclone.local/share/directory/'));
        $t->true(str_starts_with($fileLink, 'https://rclone.local/share/object/'));
        $t->true($rootLink !== $fileLink);
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
    'memory provider set object metadata updates mtime and mimetype like upstream' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', '<rss>portable export</rss>', [
            'modTime' => '2003-02-03T04:05:06.499999999Z',
            'mimeType' => 'text/plain',
            'metadata' => [
                'mtime' => '2003-02-03T04:05:06.499999999Z',
                'potato' => 'jersey',
            ],
        ]);

        $updated = $provider->setObjectMetadata('exports/site.wxr', [
            'mtime' => '2004-03-03T04:05:06.499999999Z',
            'potato' => 'royal',
            'content-type' => 'application/rss+xml',
        ]);

        $t->same('exports/site.wxr', $updated->path);
        $t->same('2004-03-03T04:05:06.499999999Z', $updated->modTime);
        $t->same('application/rss+xml', $updated->mimeType);
        $t->same([
            'mtime' => '2004-03-03T04:05:06.499999999Z',
            'potato' => 'royal',
            'content-type' => 'application/rss+xml',
        ], $provider->info('exports/site.wxr')->metadata);
        $t->same('<rss>portable export</rss>', $provider->get('exports/site.wxr'));
    },
    'memory provider set directory metadata replaces existing metadata like upstream' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('wp-content/uploads/2026/05', [
            'modTime' => '2001-02-03T04:05:06.499999999Z',
            'metadata' => [
                'mtime' => '2001-02-03T04:05:06.499999999Z',
                'potato' => 'jersey',
            ],
        ]);

        $updated = $provider->setDirectoryMetadata('wp-content/uploads/2026/05', [
            'mtime' => '2002-02-03T04:05:06.499999999Z',
            'potato' => 'king edwards',
        ]);

        $t->same('wp-content/uploads/2026/05', $updated->path);
        $t->same('2002-02-03T04:05:06.499999999Z', $updated->modTime);
        $t->same([
            'mtime' => '2002-02-03T04:05:06.499999999Z',
            'potato' => 'king edwards',
        ], $provider->directoryInfo('wp-content/uploads/2026/05')->metadata);
        $t->throws(RuntimeException::class, static fn () => $provider->setDirectoryMetadata('wp-content/uploads/2026/04', ['potato' => 'missing']));
    },
    'memory provider set tier and get tier match upstream object contract' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', '<rss>portable export</rss>', ['tier' => 'Standard']);

        $t->same(true, $provider->supportsSetTier());
        $t->same(true, $provider->supportsGetTier());
        $t->same('Standard', $provider->getObjectTier('exports/site.wxr'));

        $updated = $provider->setObjectTier('exports/site.wxr', 'Archive');
        $t->same('exports/site.wxr', $updated->path);
        $t->same('Archive', $provider->getObjectTier('exports/site.wxr'));
        $t->same('Archive', $provider->info('exports/site.wxr')->tier);
        $t->same('<rss>portable export</rss>', $provider->get('exports/site.wxr'));
        $t->throws(RuntimeException::class, static fn () => $provider->setObjectTier('exports/missing.wxr', 'Archive'));

        $noSetTier = new MemoryProvider(setTier: false);
        $noSetTier->put('exports/site.wxr', '<rss></rss>', ['tier' => 'Standard']);
        $t->same(false, $noSetTier->supportsSetTier());
        $t->throws(RuntimeException::class, static fn () => $noSetTier->setObjectTier('exports/site.wxr', 'Archive'));

        $noGetTier = new MemoryProvider(getTier: false);
        $noGetTier->put('exports/site.wxr', '<rss></rss>', ['tier' => 'Standard']);
        $t->same(false, $noGetTier->supportsGetTier());
        $t->throws(RuntimeException::class, static fn () => $noGetTier->getObjectTier('exports/site.wxr'));
    },
    'sync plan settier applies filtered tier changes over listed objects' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', '<rss>portable export</rss>', ['tier' => 'Hot']);
        $provider->put('database/site.sql', 'insert into wp_posts values (...)', ['tier' => 'Hot']);
        $provider->put('wp-content/uploads/2026/05/hero.jpg', 'image bytes', ['tier' => 'Cool']);
        $provider->put('wp-content/cache/page.html', '<html>cached</html>', ['tier' => 'Hot']);

        $filter = FilterRuleSet::fromRules([
            '+ exports/*.wxr',
            '+ database/*.sql',
            '+ wp-content/uploads/**',
            '- *',
        ]);

        $updated = (new SyncPlan())->setTier($provider, 'Archive', $filter);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
        ], array_map(static fn ($info) => $info->path, $updated));
        $t->same('Archive', $provider->getObjectTier('exports/site.wxr'));
        $t->same('Archive', $provider->getObjectTier('database/site.sql'));
        $t->same('Archive', $provider->getObjectTier('wp-content/uploads/2026/05/hero.jpg'));
        $t->same('Hot', $provider->getObjectTier('wp-content/cache/page.html'));

        $single = (new SyncPlan())->setTierFile($provider, 'exports/site.wxr', 'Standard');
        $t->same('exports/site.wxr', $single->path);
        $t->same('Standard', $provider->getObjectTier('exports/site.wxr'));
        $t->throws(RuntimeException::class, static fn () => (new SyncPlan())->setTier(new MemoryProvider(setTier: false), 'Archive'));
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
    'wordpress rooted uploads purge example exposes monthly provider listing boundaries' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-rooted-upload-purge.php';

        $t->same('wp-content/uploads/2026/05', $example['root']);
        $t->same(['gallery.jpg', 'hero.jpg'], $example['directObjects']);
        $t->same(['thumbs'], $example['directDirectories']);
        $t->same(['thumbs/hero-150x150.jpg'], $example['purgedObjects']);
        $t->same(false, $example['thumbnailStillExists']);
        $t->same('next month image', $example['nextMonthPreserved']);
        $t->same('<rss version="2.0"></rss>', $example['exportPreserved']);
    },
    'wordpress public link example exposes share and unlink boundaries' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-public-link-share.php';

        $t->true(str_starts_with($example['wxrLink'], 'https://rclone.local/share/object/'));
        $t->true(str_starts_with($example['uploadsLink'], 'https://rclone.local/share/directory/'));
        $t->same($example['wxrLink'], $example['repeatWxrLink']);
        $t->true($example['wxrLink'] !== $example['uploadsLink']);
        $t->same(true, $example['missingErrored']);
        $t->same('', $example['unlinkResult']);
        $t->same($example['wxrLink'], $example['relinkedWxr']);
        $t->same('wp-content/uploads/2026/05', $example['root']);
    },
    'wordpress settier example archives portable artifacts without tiering cache' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-settier-archive.php';

        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], $example['updatedPaths']);
        $t->same('Archive', $example['wxrTier']);
        $t->same('Archive', $example['sqlTier']);
        $t->same('Archive', $example['uploadTier']);
        $t->same('Hot', $example['cacheTier']);
        $t->same('Hot', $example['sourceAssetTier']);
        $t->same('Archive', $example['wxrJsonTier']);
        $t->same([
            'database/site.sql|Archive',
            'exports/site.wxr|Archive',
            'wp-content/cache/page/index.html|Hot',
            'wp-content/debug.log|Hot',
            'wp-content/uploads/2026/05/hero.jpg|Archive',
            'wp-content/uploads/2026/05/hero.webp|Archive',
            'wp-content/uploads/2026/05/private-draft.psd|Hot',
        ], $example['lsfTierLines']);
    },
];
