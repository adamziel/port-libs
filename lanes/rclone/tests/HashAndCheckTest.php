<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\ChecksumFile;
use PortLibs\Rclone\HashListing;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\HashType;
use PortLibs\Rclone\LsJsonListing;
use PortLibs\Rclone\LsfListing;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\MultiHasher;
use PortLibs\Rclone\SyncPlan;

return [
    'maps upstream hash set operations and aliases' => static function (TestRunner $t): void {
        $hashes = new HashSet();
        $t->same(0, $hashes->count());
        $t->same([], $hashes->toArray());

        $hashes->add(HashType::MD5);
        $t->same(1, $hashes->count());
        $t->same(HashType::MD5, $hashes->getOne());
        $t->same([HashType::MD5], $hashes->toArray());
        $t->true($hashes->overlap(HashSet::supported())->contains(HashType::MD5));
        $t->true($hashes->subsetOf(HashSet::supported()));
        $t->true($hashes->subsetOf(new HashSet(HashType::MD5)));

        $hashes->add(HashType::SHA1);
        $t->same(2, $hashes->count());
        $t->same(HashType::MD5, $hashes->getOne());
        $t->true(!$hashes->subsetOf(new HashSet(HashType::MD5)));
        $t->true(!$hashes->subsetOf(new HashSet(HashType::SHA1)));
        $t->true($hashes->subsetOf(new HashSet(HashType::MD5, HashType::SHA1)));
        $t->same('[md5, sha1]', (string) $hashes);

        $overlap = $hashes->overlap(new HashSet(HashType::MD5));
        $t->same(1, $overlap->count());
        $t->true($overlap->contains(HashType::MD5));
        $t->true(!$overlap->contains(HashType::SHA1));

        foreach (['none' => HashType::NONE, 'None' => HashType::NONE, 'md5' => HashType::MD5, 'MD5' => HashType::MD5, 'sha1' => HashType::SHA1, 'SHA-1' => HashType::SHA1, 'SHA1' => HashType::SHA1, 'Sha1' => HashType::SHA1] as $input => $expected) {
            $t->same($expected, HashType::fromString($input));
        }
        $t->throws(InvalidArgumentException::class, static fn () => HashType::fromString('Sha-1'));
    },
    'maps upstream multi hasher fixtures for php supported hashes' => static function (TestRunner $t): void {
        $bytes = '';
        for ($i = 1; $i <= 14; $i++) {
            $bytes .= chr($i);
        }

        $hashes = MultiHasher::hashBytes($bytes, new HashSet(HashType::MD5, HashType::SHA1, HashType::CRC32, HashType::SHA256, HashType::SHA512));
        $t->same('bf13fc19e5151ac57d4252e0e0f87abe', $hashes[HashType::MD5]);
        $t->same('3ab6543c08a75f292a5ecedac87ec41642d12166', $hashes[HashType::SHA1]);
        $t->same('a6041d7e', $hashes[HashType::CRC32]);
        $t->same('c839e57675862af5c21bd0a15413c3ec579e0d5522dab600bc6c3489b05b8f54', $hashes[HashType::SHA256]);
        $t->same('008e7e9b5d94d37bf5e07c955890f730f137a41b8b0db16cb535a9b4cb5632c2bccff31685ec470130fe10e2258a0ab50ab587472258f3132ccf7d7d59fb91db', $hashes[HashType::SHA512]);

        $empty = MultiHasher::hashBytes('', new HashSet(HashType::MD5, HashType::SHA1, HashType::CRC32, HashType::SHA256));
        $t->same('d41d8cd98f00b204e9800998ecf8427e', $empty[HashType::MD5]);
        $t->same('da39a3ee5e6b4b0d3255bfef95601890afd80709', $empty[HashType::SHA1]);
        $t->same('00000000', $empty[HashType::CRC32]);
        $t->same('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $empty[HashType::SHA256]);
    },
    'checks providers using rclone combined report sigils' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();
        $source->put('rutabaga', 'is tasty');
        $target->put('rutabaga', 'is tasty');
        $source->put('potato2', str_repeat('-', 60));
        $target->put('empty space', '-');
        $source->put('changed.txt', 'local');
        $target->put('changed.txt', 'remote');

        $result = (new SyncPlan())->check($source, $target);
        $t->same(['rutabaga'], $result->matches);
        $t->same(['changed.txt'], $result->differ);
        $t->same(['empty space'], $result->missingOnSource);
        $t->same(['potato2'], $result->missingOnTarget);
        $t->same(3, $result->differences());
        $t->same(1, $result->matches());
        $t->same(['* changed.txt', '+ potato2', '- empty space', '= rutabaga'], $result->combinedLines());

        $oneWay = (new SyncPlan())->check($source, $target, true);
        $t->same([], $oneWay->missingOnSource);
        $t->same(['* changed.txt', '+ potato2', '= rutabaga'], $oneWay->combinedLines());
    },
    'parses upstream checksum file formats' => static function (TestRunner $t): void {
        $sums = ChecksumFile::parse(implode("\r\n", [
            '1  file1',
            '2 *file2',
            '3   file3 ',
            "4  \tfile3\t",
            '5 file5',
            "6\tfile6",
            '7   file3 ',
            '65A8E27D8879283831B664BD8B7F0AD4  wp-content/uploads/hero.jpg',
            '',
        ]));

        $t->same('1', $sums['file1']);
        $t->same('2', $sums['file2']);
        $t->same('3', $sums[' file3 ']);
        $t->same('4', $sums["\tfile3\t"]);
        $t->same('65a8e27d8879283831b664bd8b7f0ad4', $sums['wp-content/uploads/hero.jpg']);
        $t->true(!isset($sums['file5']));
        $t->true(!isset($sums['file6']));
        $t->same(5, count($sums));
    },
    'verifies checksum files against providers like upstream CheckSum' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('banana', 'Hello, World!');
        $provider->put('potato', 'I am the walrus');

        $banana = '65a8e27d8879283831b664bd8b7f0ad4';
        $potato = '87396e030ef3f5b35bbf85c0a09a4fb3';

        $result = ChecksumFile::check($provider, "{$banana}  banana\n", HashType::MD5);
        $t->same(['banana'], $result->matches);
        $t->same(['potato'], $result->missingOnSource);
        $t->same(['- potato', '= banana'], $result->combinedLines());

        $result = ChecksumFile::check($provider, "{$banana}  banana\n{$potato}  potato\n", HashType::MD5);
        $t->same(['banana', 'potato'], $result->matches);
        $t->same(0, $result->differences());

        $result = ChecksumFile::check($provider, "{$potato}  banana\n{$potato}  potato\n", HashType::MD5);
        $t->same(['banana'], $result->differ);
        $t->same(['potato'], $result->matches);
        $t->same(['* banana', '= potato'], $result->combinedLines());

        $result = ChecksumFile::check($provider, "{$banana}  banana\n{$potato}  potato\n{$potato}  orange\n", HashType::MD5);
        $t->same(['orange'], $result->missingOnTarget);
        $t->same(['+ orange', '= banana', '= potato'], $result->combinedLines());

        $result = ChecksumFile::check($provider, strtoupper($banana) . "  banana\n87396e030EF3f5b35BBf85c0a09a4FB3  potato\n", HashType::MD5);
        $t->same(['banana', 'potato'], $result->matches);
        $t->same(0, $result->differences());
    },
    'checksum verification honors one way filters and duplicate sum entries' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('banana', 'Hello, World!');
        $provider->put('potato', 'I am the walrus');
        $provider->put('wp-content/cache/page.html', '<html>cached</html>');

        $banana = hash('md5', 'Hello, World!');
        $cache = hash('md5', '<html>cached</html>');
        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '+ *',
        ]);

        $result = ChecksumFile::check($provider, implode("\n", [
            "{$banana}  banana",
            '00000000000000000000000000000000  banana',
            "{$banana}  orange",
            "{$cache}  wp-content/cache/page.html",
        ]), HashType::MD5, true, $filter);

        $t->same(['banana'], $result->matches);
        $t->same([], $result->differ);
        $t->same([], $result->missingOnSource);
        $t->same(['orange'], $result->missingOnTarget);
        $t->same(['+ orange', '= banana'], $result->combinedLines());
    },
    'lists hashes in rclone hashsum format' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('potato2', str_repeat('-', 60));
        $provider->put('empty space', '-');

        $t->same([
            '336d5ebc5436534e61d16e63ddfca327  empty space',
            'd6548b156ea68a4e003e786df99eee76  potato2',
        ], HashListing::lines($provider, HashType::MD5));
        $t->same('1B2M2Y8AsgTpgAmY7PhCfg==  -', HashListing::streamLine('', HashType::MD5, true));
        $t->same('00hq6RNueFa8QiEjhep5cJRHWAI=  -', HashListing::streamLine('Hello world!', HashType::SHA1, true));
    },
    'formats lsf path size and hash fields like upstream' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('file1', '');
        $provider->put('file2', str_repeat('x', 321));
        $provider->put('file3', str_repeat('y', 1234));
        $provider->put('subdir/file1', '');
        $provider->put('subdir/file2', 'z');

        $t->same(['file1', 'file2', 'file3', 'subdir/'], LsfListing::lines($provider));
        $t->same(['file1;0', 'file2;321', 'file3;1234', 'subdir/;-1'], LsfListing::lines($provider, ['format' => 'ps']));
        $t->same(['d41d8cd98f00b204e9800998ecf8427e;file1'], array_slice(LsfListing::lines($provider, ['format' => 'hp', 'filesOnly' => true]), 0, 1));
        $t->same(['file1', 'file2', 'file3'], LsfListing::lines($provider, ['filesOnly' => true]));
        $t->same(['subdir'], LsfListing::lines($provider, ['dirsOnly' => true, 'dirSlash' => false]));
        $t->same(['file1_+_0', 'file2_+_321', 'file3_+_1234', 'subdir/_+_-1', 'subdir/file1_+_0', 'subdir/file2_+_1'], LsfListing::lines($provider, ['format' => 'ps', 'separator' => '_+_', 'recurse' => true]));
    },
    'formats lsjson list entries from upstream table cases' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('file1', 'file1', [
            'modTime' => '2001-02-03T04:05:06Z',
            'mimeType' => 'text/plain; charset=utf-8',
            'metadata' => ['mtime' => '2001-02-03T04:05:06Z'],
        ]);
        $provider->put('sub/file2', 'sub/file2', [
            'modTime' => '2002-03-04T05:06:07Z',
            'mimeType' => 'text/plain; charset=utf-8',
        ]);

        $t->same([
            [
                'Path' => 'file1',
                'Name' => 'file1',
                'Size' => 5,
                'MimeType' => 'text/plain; charset=utf-8',
                'ModTime' => '2001-02-03T04:05:06Z',
                'IsDir' => false,
            ],
            [
                'Path' => 'sub',
                'Name' => 'sub',
                'Size' => -1,
                'MimeType' => 'inode/directory',
                'ModTime' => '',
                'IsDir' => true,
            ],
        ], LsJsonListing::items($provider));
        $t->same(['file1'], array_column(LsJsonListing::items($provider, '', ['filesOnly' => true]), 'Path'));
        $t->same(['sub'], array_column(LsJsonListing::items($provider, '', ['dirsOnly' => true]), 'Path'));
        $t->same(['file1', 'sub', 'sub/file2'], array_column(LsJsonListing::items($provider, '', ['recurse' => true]), 'Path'));
        $t->same(['sub/file2'], array_column(LsJsonListing::items($provider, 'sub'), 'Path'));

        $noModTime = LsJsonListing::items($provider, '', ['filesOnly' => true, 'noModTime' => true]);
        $t->same('', $noModTime[0]['ModTime']);
        $noMimeType = LsJsonListing::items($provider, '', ['filesOnly' => true, 'noMimeType' => true]);
        $t->true(!array_key_exists('MimeType', $noMimeType[0]));

        $hashItem = LsJsonListing::items($provider, '', ['filesOnly' => true, 'hashTypes' => ['MD5']])[0];
        $t->same(['md5' => '826e8142e6baabe8af779f5f490cf5f5'], $hashItem['Hashes']);
        $metadataItem = LsJsonListing::items($provider, '', ['filesOnly' => true, 'metadata' => true])[0];
        $t->same(['mtime' => '2001-02-03T04:05:06Z'], $metadataItem['Metadata']);
    },
    'formats lsjson stat entries from upstream table cases' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('file1', 'file1', ['modTime' => '2001-02-03T04:05:06Z']);
        $provider->put('sub/file2', 'sub/file2', ['modTime' => '2002-03-04T05:06:07Z']);

        $t->same('', LsJsonListing::stat($provider)['Path']);
        $t->same(null, LsJsonListing::stat($provider, '', ['filesOnly' => true]));
        $t->same('sub', LsJsonListing::stat($provider, 'sub')['Path']);
        $t->same('sub', LsJsonListing::stat($provider, 'sub/')['Path']);
        $t->same('file1', LsJsonListing::stat($provider, 'file1')['Path']);
        $t->same(null, LsJsonListing::stat($provider, 'notfound'));
        $t->same(null, LsJsonListing::stat($provider, 'sub', ['filesOnly' => true]));
        $t->same('file1', LsJsonListing::stat($provider, 'file1', ['filesOnly' => true])['Path']);
        $t->same('sub', LsJsonListing::stat($provider, 'sub', ['dirsOnly' => true])['Path']);
        $t->same(null, LsJsonListing::stat($provider, 'file1', ['dirsOnly' => true]));
    },
    'verifies wordpress checksum manifests with case-insensitive provider paths' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('wp-content/uploads/2026/05/Hero.JPG', 'new image bytes');
        $provider->put('database/site.sql', 'insert into wp_posts values (...)');

        $manifest = implode("\n", [
            strtoupper(hash('md5', 'new image bytes')) . '  wp-content/uploads/2026/05/hero.jpg',
            hash('md5', 'insert into wp_posts values (...)') . '  DATABASE/SITE.SQL',
        ]);

        $strict = ChecksumFile::check($provider, $manifest, HashType::MD5);
        $t->same(['database/site.sql', 'wp-content/uploads/2026/05/Hero.JPG'], $strict->missingOnSource);
        $t->same(['DATABASE/SITE.SQL', 'wp-content/uploads/2026/05/hero.jpg'], $strict->missingOnTarget);

        $caseInsensitive = ChecksumFile::check($provider, $manifest, HashType::MD5, false, null, true);
        $t->same(['database/site.sql', 'wp-content/uploads/2026/05/Hero.JPG'], $caseInsensitive->matches);
        $t->same(0, $caseInsensitive->differences());
    },
    'publishes a wordpress backup lsjson manifest with hashes and metadata' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
        foreach ($tree as $path => $bytes) {
            if (str_starts_with($path, 'wp-content/cache/') || str_ends_with($path, '.log') || str_ends_with($path, '.psd')) {
                continue;
            }
            $provider->put($path, $bytes, [
                'modTime' => '2026-05-22T00:00:00Z',
                'metadata' => ['wp-backup-scope' => 'portable-export'],
            ]);
        }

        $items = LsJsonListing::items($provider, '', [
            'recurse' => true,
            'hashTypes' => ['MD5'],
            'metadata' => true,
        ]);

        $paths = array_column($items, 'Path');
        $t->same([
            'database',
            'database/site.sql',
            'exports',
            'exports/site.wxr',
            'wp-content',
            'wp-content/uploads',
            'wp-content/uploads/2026',
            'wp-content/uploads/2026/05',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], $paths);

        $files = array_values(array_filter($items, static fn (array $item): bool => !$item['IsDir']));
        $t->same('database/site.sql', $files[0]['Path']);
        $t->same(['wp-backup-scope' => 'portable-export'], $files[0]['Metadata']);
        $t->true(isset($files[0]['Hashes']['md5']));
    },
    'copies filtered changed wordpress backup objects idempotently' => static function (TestRunner $t): void {
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

        $copied = (new SyncPlan())->copyChanged($source, $target, $filter);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], array_map(static fn ($info) => $info->path, $copied));
        $t->same([], (new SyncPlan())->changedPaths($source, $target, $filter));
        $t->same($source->get('wp-content/uploads/2026/05/hero.jpg'), $target->get('wp-content/uploads/2026/05/hero.jpg'));
        $t->throws(RuntimeException::class, static fn () => $target->get('wp-content/cache/page-cache.html'));
    },
];
