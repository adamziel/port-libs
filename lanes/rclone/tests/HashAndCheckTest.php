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
use PortLibs\Rclone\ReaderComparison;
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

        foreach (['none' => HashType::NONE, 'None' => HashType::NONE, 'md5' => HashType::MD5, 'MD5' => HashType::MD5, 'sha1' => HashType::SHA1, 'SHA-1' => HashType::SHA1, 'SHA1' => HashType::SHA1, 'Sha1' => HashType::SHA1, 'quickxor' => HashType::QUICKXOR, 'QuickXorHash' => HashType::QUICKXOR] as $input => $expected) {
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
    'maps upstream onedrive quickxor hash vectors' => static function (TestRunner $t): void {
        $vectors = [
            '' => '0000000000000000000000000000000000000000',
            'Sg==' => '4a00000000000000000000000100000000000000',
            'h490d57Pqz5q2rtT' => '8778041deee08967acc2076adcc62ea600000000',
        ];

        foreach ($vectors as $inputBase64 => $expectedHex) {
            $bytes = base64_decode($inputBase64, true);
            $t->same($expectedHex, MultiHasher::hashBytes($bytes, new HashSet(HashType::QUICKXOR))[HashType::QUICKXOR]);
        }
        $t->same('AAAAAAAAAAAAAAAAAAAAAAAAAAA=  -', HashListing::streamLine('', HashType::QUICKXOR, true));
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
    'checksum download mode hashes bytes when provider does not advertise hashes' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(false, new HashSet());
        $provider->put('banana', 'Hello, World!');
        $provider->put('potato', 'I am the walrus');

        $manifest = implode("\n", [
            hash('md5', 'Hello, World!') . '  banana',
            hash('md5', 'different bytes') . '  potato',
            hash('md5', 'missing bytes') . '  orange',
        ]);

        $t->same([], $provider->hashes('banana', new HashSet(HashType::MD5)));
        $t->throws(InvalidArgumentException::class, static fn () => ChecksumFile::check($provider, $manifest, HashType::MD5));

        $result = ChecksumFile::checkDownload($provider, $manifest, HashType::MD5);
        $t->same(['banana'], $result->matches);
        $t->same(['potato'], $result->differ);
        $t->same(['orange'], $result->missingOnTarget);
        $t->same(['* potato', '+ orange', '= banana'], $result->combinedLines());
    },
    'compares download readers with upstream read fill error precedence' => static function (TestRunner $t): void {
        $b65a = str_repeat("\0", 65 * 1024);
        $b65b = substr($b65a, 0, -1) . "\1";
        $b66 = str_repeat("\0", 66 * 1024);
        $sentinel = new RuntimeException('sentinel');
        $faulting = static function (string $bytes) use ($sentinel): object {
            return new class($bytes, $sentinel) {
                private int $offset = 0;

                public function __construct(
                    private readonly string $bytes,
                    private readonly RuntimeException $error,
                ) {
                }

                public function read(int $length): string
                {
                    if ($this->offset < strlen($this->bytes)) {
                        $chunk = substr($this->bytes, $this->offset, $length);
                        $this->offset += strlen($chunk);

                        return $chunk;
                    }

                    throw $this->error;
                }

                public function eof(): bool
                {
                    return false;
                }
            };
        };

        $same = ReaderComparison::checkEqualReaders($b65a, $b65a);
        $t->same(true, $same->equal);
        $t->same(null, $same->error);

        foreach ([[$b65a, $b65b], [$b65a, $b66], [$b66, $b65a]] as [$left, $right]) {
            $different = ReaderComparison::checkEqualReaders($left, $right);
            $t->same(false, $different->equal);
            $t->same(null, $different->error);
        }

        foreach ([[$b65a, $b65a], [$b65a, $b65b], [$b65a, $b66], [$b66, $b65a]] as [$left, $right]) {
            $leftError = ReaderComparison::checkEqualReaders($faulting($left), $right);
            $t->same(false, $leftError->equal);
            $t->same($sentinel, $leftError->error);
        }

        foreach ([[$b65a, $b65a], [$b65a, $b65b], [$b65a, $b66], [$b66, $b65a]] as [$left, $right]) {
            $rightError = ReaderComparison::checkEqualReaders($left, $faulting($right));
            $t->same(false, $rightError->equal);
            $t->same($sentinel, $rightError->error);
        }
    },
    'download check compares providers by bytes like upstream CheckDownload' => static function (TestRunner $t): void {
        $source = new MemoryProvider(false, new HashSet());
        $target = new MemoryProvider(false, new HashSet());

        $source->put('rutabaga', 'is tasty');
        $target->put('rutabaga', 'is tasty');
        $source->put('potato2', str_repeat('-', 60));
        $target->put('potato2', str_repeat('-', 60));
        $target->put('empty space', '-');
        $source->put('source-only.txt', 'only in source');
        $source->put('same-size-changed.txt', 'abc');
        $target->put('same-size-changed.txt', 'abx');
        $source->put('length-changed.txt', 'abc');
        $target->put('length-changed.txt', 'abcd');

        $result = (new SyncPlan())->checkDownload($source, $target);
        $t->same(['potato2', 'rutabaga'], $result->matches);
        $t->same(['length-changed.txt', 'same-size-changed.txt'], $result->differ);
        $t->same(['empty space'], $result->missingOnSource);
        $t->same(['source-only.txt'], $result->missingOnTarget);
        $t->same([], $result->errors);
        $t->same(4, $result->differences());
        $t->same([
            '* length-changed.txt',
            '* same-size-changed.txt',
            '+ source-only.txt',
            '- empty space',
            '= potato2',
            '= rutabaga',
        ], $result->combinedLines());

        $oneWay = (new SyncPlan())->checkDownload($source, $target, true);
        $t->same([], $oneWay->missingOnSource);
        $t->same(['+ source-only.txt'], array_values(array_filter(
            $oneWay->combinedLines(),
            static fn (string $line): bool => str_starts_with($line, '+ '),
        )));
    },
    'download check reports upstream error sigils for open and read failures' => static function (TestRunner $t): void {
        $source = new MemoryProvider();
        $target = new MemoryProvider();

        $source->put('plain-diff.txt', 'abc');
        $target->put('plain-diff.txt', 'abx');
        $source->put('read-fails-before-diff.txt', 'abc', [
            'readError' => new RuntimeException('source read failed'),
            'readErrorAfterBytes' => 3,
        ]);
        $target->put('read-fails-before-diff.txt', 'abx');
        $source->put('source-open-fails.txt', 'same', ['openError' => 'source open unavailable']);
        $target->put('source-open-fails.txt', 'same');
        $source->put('target-open-fails.txt', 'same');
        $target->put('target-open-fails.txt', 'same', ['openError' => 'target open unavailable']);

        $result = (new SyncPlan())->checkDownload($source, $target);
        $t->same(['plain-diff.txt'], $result->differ);
        $t->same([
            'read-fails-before-diff.txt',
            'source-open-fails.txt',
            'target-open-fails.txt',
        ], $result->errors);
        $t->same(1, $result->differences());
        $t->same(3, $result->errors());
        $t->same([
            '! read-fails-before-diff.txt',
            '! source-open-fails.txt',
            '! target-open-fails.txt',
            '* plain-diff.txt',
        ], $result->combinedLines());
        $t->same('failed to download: source read failed', $result->errorMessages['read-fails-before-diff.txt']);
        $t->same('failed to download: failed to open "source-open-fails.txt": source open unavailable', $result->errorMessages['source-open-fails.txt']);
        $t->same('failed to download: failed to open "target-open-fails.txt": target open unavailable', $result->errorMessages['target-open-fails.txt']);
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
        $provider->setObjectTier('file2', 'Cool');

        $t->same(['file1', 'file2', 'file3', 'subdir/'], LsfListing::lines($provider));
        $t->same(['file1;0', 'file2;321', 'file3;1234', 'subdir/;-1'], LsfListing::lines($provider, ['format' => 'ps']));
        $t->same(['d41d8cd98f00b204e9800998ecf8427e;file1'], array_slice(LsfListing::lines($provider, ['format' => 'hp', 'filesOnly' => true]), 0, 1));
        $t->same(['file1:', 'file2:Cool', 'file3:', 'subdir/:'], LsfListing::lines($provider, ['format' => 'pT', 'separator' => ':']));
        $t->same(['file1', 'file2', 'file3'], LsfListing::lines($provider, ['filesOnly' => true]));
        $t->same(['subdir'], LsfListing::lines($provider, ['dirsOnly' => true, 'dirSlash' => false]));
        $t->same(['file1_+_0', 'file2_+_321', 'file3_+_1234', 'subdir/_+_-1', 'subdir/file1_+_0', 'subdir/file2_+_1'], LsfListing::lines($provider, ['format' => 'ps', 'separator' => '_+_', 'recurse' => true]));
    },
    'formats lsjson tier only when provider exposes GetTier' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', '<rss>portable export</rss>', ['tier' => 'Archive']);

        $stat = LsJsonListing::stat($provider, 'exports/site.wxr');
        $t->same('Archive', $stat['Tier']);

        $hidden = new MemoryProvider(getTier: false);
        $hidden->put('exports/site.wxr', '<rss>portable export</rss>', ['tier' => 'Archive']);
        $hiddenStat = LsJsonListing::stat($hidden, 'exports/site.wxr');
        $t->true(!array_key_exists('Tier', $hiddenStat));
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
    'formats lsjson explicit directory modtimes and metadata like upstream listjson entries' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->mkdir('wp-content/uploads/2026/05', [
            'modTime' => '2026-05-22T00:00:00Z',
            'metadata' => ['mtime' => '2026-05-22T00:00:00Z', 'wp-scope' => 'uploads'],
            'id' => 'dir-uploads-may',
        ]);
        $provider->mkdirModTime('exports/incremental', '2026-05-21T00:00:00Z');
        $provider->put('wp-content/uploads/2026/05/hero.jpg', 'new image bytes', [
            'modTime' => '2026-05-22T00:05:00Z',
        ]);

        $items = LsJsonListing::items($provider, '', [
            'recurse' => true,
            'metadata' => true,
        ]);
        $byPath = [];
        foreach ($items as $item) {
            $byPath[$item['Path']] = $item;
        }

        $t->same('2026-05-22T00:00:00Z', $byPath['wp-content/uploads/2026/05']['ModTime']);
        $t->same(['mtime' => '2026-05-22T00:00:00Z', 'wp-scope' => 'uploads'], $byPath['wp-content/uploads/2026/05']['Metadata']);
        $t->same('dir-uploads-may', $byPath['wp-content/uploads/2026/05']['ID']);
        $t->same('2026-05-21T00:00:00Z', $byPath['exports/incremental']['ModTime']);
        $t->same('', $byPath['wp-content/uploads/2026']['ModTime']);
        $t->same('hero.jpg', $byPath['wp-content/uploads/2026/05/hero.jpg']['Name']);

        $dirNoModtime = LsJsonListing::stat($provider, 'wp-content/uploads/2026/05', ['noModTime' => true, 'metadata' => true]);
        $t->same('', $dirNoModtime['ModTime']);
        $t->same(['mtime' => '2026-05-22T00:00:00Z', 'wp-scope' => 'uploads'], $dirNoModtime['Metadata']);
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
    'formats lsjson stat using case-insensitive provider directory matching' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(true);
        $provider->put('wp-content/uploads/2026/05/Hero.JPG', 'new image bytes', [
            'modTime' => '2026-05-22T00:00:00Z',
        ]);
        $provider->put('database/site.sql', 'insert into wp_posts values (...)', [
            'modTime' => '2026-05-22T00:00:00Z',
        ]);

        $uploadDir = LsJsonListing::stat($provider, 'WP-CONTENT/UPLOADS');
        $t->same('wp-content/uploads', $uploadDir['Path']);
        $t->same('uploads', $uploadDir['Name']);
        $t->same(true, $uploadDir['IsDir']);

        $file = LsJsonListing::stat($provider, 'wp-content/uploads/2026/05/hero.jpg', [
            'hashTypes' => ['MD5'],
        ]);
        $t->same('wp-content/uploads/2026/05/Hero.JPG', $file['Path']);
        $t->same('Hero.JPG', $file['Name']);
        $t->same(false, $file['IsDir']);
        $t->same(['md5' => hash('md5', 'new image bytes')], $file['Hashes']);

        $t->same(null, LsJsonListing::stat($provider, 'DATABASE', ['filesOnly' => true]));
        $t->same('database', LsJsonListing::stat($provider, 'DATABASE', ['dirsOnly' => true])['Path']);
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
    'download-mode verifies wordpress checksum manifests without provider hash support' => static function (TestRunner $t): void {
        $provider = new MemoryProvider(false, new HashSet());
        foreach (require __DIR__ . '/../fixtures/wordpress-backup-tree.php' as $path => $bytes) {
            if (str_starts_with($path, 'wp-content/cache/') || str_ends_with($path, '.log') || str_ends_with($path, '.psd')) {
                continue;
            }

            $provider->put($path, $bytes);
        }

        $manifest = implode("\n", [
            hash('md5', 'new image bytes') . '  wp-content/uploads/2026/05/hero.jpg',
            hash('md5', 'generated webp bytes') . '  wp-content/uploads/2026/05/hero.webp',
            hash('md5', '<rss version="2.0"></rss>') . '  exports/site.wxr',
            hash('md5', 'insert into wp_posts values (...)') . '  database/site.sql',
        ]);

        $t->throws(InvalidArgumentException::class, static fn () => ChecksumFile::check($provider, $manifest, HashType::MD5));

        $result = ChecksumFile::checkDownload($provider, $manifest, HashType::MD5);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/hero.webp',
        ], $result->matches);
        $t->same(0, $result->differences());
    },
    'download check verifies wordpress restored provider artifacts without hashes' => static function (TestRunner $t): void {
        $source = new MemoryProvider(false, new HashSet());
        $target = new MemoryProvider(false, new HashSet());
        foreach (require __DIR__ . '/../fixtures/wordpress-backup-tree.php' as $path => $bytes) {
            if (str_starts_with($path, 'wp-content/cache/') || str_ends_with($path, '.log') || str_ends_with($path, '.psd')) {
                continue;
            }

            $source->put($path, $bytes);
            $target->put($path, $bytes);
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

        $result = (new SyncPlan())->checkDownload($source, $target, false, $filter);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.webp',
        ], $result->matches);
        $t->same(['wp-content/uploads/2026/05/hero.jpg'], $result->differ);
        $t->same([], $result->missingOnSource);
        $t->same([], $result->missingOnTarget);
        $t->same(1, $result->differences());
        $t->same(0, $result->errors());
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
