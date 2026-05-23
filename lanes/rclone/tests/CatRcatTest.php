<?php

declare(strict_types=1);

use PortLibs\Rclone\FilterRuleSet;
use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\HashType;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

return [
    'cat maps upstream offset count tail and separator table cases' => static function (TestRunner $t): void {
        foreach ([
            [0, -1, '', 'ABCDEFGHIJ012345678'],
            [0, 5, '', 'ABCDE01234'],
            [-3, -1, '', 'HIJ678'],
            [1, 3, '', 'BCD123'],
            [0, -1, "\n", "ABCDEFGHIJ\n012345678\n"],
        ] as [$offset, $count, $separator, $expected]) {
            $provider = new MemoryProvider();
            $provider->put('file1', 'ABCDEFGHIJ');
            $provider->put('file2', '012345678');

            $stats = null;
            $actual = (new SyncPlan())->cat(
                $provider,
                offset: $offset,
                count: $count,
                separator: $separator,
                stats: $stats,
            );

            $t->same($expected, $actual);
            $t->same(2, $stats['listed']);
            $t->same(2, $stats['opened']);
        }
    },
    'cat command resolves head tail discard and invalid flag combinations' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', '<rss>portable export</rss>');
        $provider->put('database/site.sql', 'CREATE TABLE wp_posts;');

        $plan = new SyncPlan();
        $headStats = null;
        $head = $plan->catCommand($provider, head: 5, separator: '|', stats: $headStats);
        $t->same('CREAT|<rss>|', $head);
        $t->same(2, $headStats['separators']);

        $tail = $plan->catCommand($provider, tail: 6);
        $t->same('posts;</rss>', $tail);

        $discardStats = null;
        $discarded = $plan->catCommand($provider, count: 3, discard: true, stats: $discardStats);
        $t->same('', $discarded);
        $t->same(6, $discardStats['bytes']);
        $t->same(true, $discardStats['discard']);

        $t->throws(RuntimeException::class, static fn () => $plan->catCommand($provider, head: 2, tail: 2));
        $t->throws(RuntimeException::class, static fn () => $plan->catCommand($provider, head: 2, offset: 1));
        $t->throws(RuntimeException::class, static fn () => $plan->catCommand($provider, tail: 2, count: 1));
    },
    'cat applies filters before opening listed objects' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', '<rss></rss>');
        $provider->put('database/site.sql', 'sql');
        $provider->put('wp-content/cache/page.html', '<html>cache</html>');
        $filter = FilterRuleSet::fromRules([
            '- wp-content/cache/**',
            '+ *',
        ]);

        $stats = null;
        $output = (new SyncPlan())->cat($provider, separator: "\n", filter: $filter, stats: $stats);

        $t->same("sql\n<rss></rss>\n", $output);
        $t->same(2, $stats['opened']);
        $t->same([
            ['path' => 'database/site.sql', 'offset' => 0, 'length' => null],
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => null],
        ], $provider->openLog());
    },
    'rcat uploads small and streaming inputs with metadata and checksums' => static function (TestRunner $t): void {
        $target = new MemoryProvider(false, new HashSet(HashType::MD5));
        $plan = new SyncPlan();

        $smallStats = null;
        $small = $plan->rcat(
            $target,
            'exports/small.wxr',
            '<rss>small</rss>',
            '2026-05-23T00:00:00Z',
            ['key' => 'value'],
            streamingUploadCutoff: 1024,
            stats: $smallStats,
        );
        $largeStats = null;
        $largeBytes = str_repeat('x', 8);
        $large = $plan->rcat(
            $target,
            'exports/large.wxr',
            $largeBytes,
            '2026-05-23T00:01:00Z',
            ['source' => 'stdin'],
            streamingUploadCutoff: 4,
            stats: $largeStats,
        );

        $t->same('put', $smallStats['uploadMode']);
        $t->same(true, $smallStats['smallUpload']);
        $t->same('putStream', $largeStats['uploadMode']);
        $t->same(false, $largeStats['smallUpload']);
        $t->same(strlen('<rss>small</rss>'), $small->size);
        $t->same(strlen($largeBytes), $large->size);
        $t->same(['key' => 'value'], $target->info('exports/small.wxr')->metadata);
        $t->same(hash('md5', '<rss>small</rss>'), $target->info('exports/small.wxr')->hashes[HashType::MD5]);
        $t->same(hash('md5', $largeBytes), $target->info('exports/large.wxr')->hashes[HashType::MD5]);
    },
    'rcat can ignore checksum option materialization' => static function (TestRunner $t): void {
        $target = new MemoryProvider(false, new HashSet(HashType::MD5));
        $stats = null;
        (new SyncPlan())->rcat(
            $target,
            'exports/no-checksum.wxr',
            '<rss>unchecked</rss>',
            ignoreChecksum: true,
            stats: $stats,
        );

        $t->same(HashType::NONE, $stats['checksumType']);
        $t->same(true, $stats['checksumIgnored']);
        $t->same([], $target->info('exports/no-checksum.wxr')->hashes);
    },
    'rcat size uses known-size put and unknown-size rcat paths' => static function (TestRunner $t): void {
        $target = new MemoryProvider();
        $plan = new SyncPlan();

        $knownStats = null;
        $known = $plan->rcatSize(
            $target,
            'known/potato1',
            '------------------------------------------------------------',
            60,
            '2026-05-23T00:02:00Z',
            stats: $knownStats,
        );
        $unknownStats = null;
        $unknown = $plan->rcatSize(
            $target,
            'unknown/potato2',
            '------------------------------------------------------------',
            -1,
            '2026-05-23T00:03:00Z',
            streamingUploadCutoff: 0,
            stats: $unknownStats,
        );

        $t->same(60, $known->size);
        $t->same('known/potato1', $known->path);
        $t->same('put', $knownStats['uploadMode']);
        $t->same(60, $unknown->size);
        $t->same('unknown/potato2', $unknown->path);
        $t->same('putStream', $unknownStats['uploadMode']);
        $t->same(60, $unknownStats['bytesRead']);
    },
    'rcat closes object inputs after reading them' => static function (TestRunner $t): void {
        $input = new class('streamed wxr bytes') {
            public bool $closed = false;
            private int $offset = 0;

            public function __construct(private readonly string $bytes)
            {
            }

            public function read(int $length): string
            {
                if ($this->offset >= strlen($this->bytes)) {
                    return '';
                }

                $chunk = substr($this->bytes, $this->offset, min(4, $length));
                $this->offset += strlen($chunk);

                return $chunk;
            }

            public function close(): void
            {
                $this->closed = true;
            }
        };

        $target = new MemoryProvider();
        (new SyncPlan())->rcat($target, 'exports/streamed.wxr', $input);

        $t->same(true, $input->closed);
        $t->same('streamed wxr bytes', $target->get('exports/streamed.wxr'));
    },
    'wordpress cat and rcat example publishes streamed export snippets' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-cat-rcat-wxr-stream.php';

        $t->same('<rss version="2.0"></rss>', $example['uploadedExport']);
        $t->same("CREATE TABLE wp_posts;\n<rss version=\"2.0\"></rss>\n", $example['catManifest']);
        $t->same('</rss>', $example['tailExport']);
        $t->same('putStream', $example['uploadStats']['uploadMode']);
        $t->same(2, $example['catStats']['opened']);
    },
];
