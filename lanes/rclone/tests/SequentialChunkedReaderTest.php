<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SequentialChunkedReader;

return [
    'sequential chunked reader grows chunks and caps at max size' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $content = implode('', range('A', 'Z'));
        $provider->put('exports/archive.bin', $content);

        $reader = new SequentialChunkedReader($provider, 'exports/archive.bin', 4, 10);

        $t->same('ABCD', $reader->read(4));
        $t->same('EFGHIJKL', $reader->read(8));
        $t->same('MNOPQRSTUVWXYZ', $reader->read(20));
        $t->same('', $reader->read(1));
        $t->same([
            ['path' => 'exports/archive.bin', 'offset' => 0, 'length' => 4],
            ['path' => 'exports/archive.bin', 'offset' => 4, 'length' => 8],
            ['path' => 'exports/archive.bin', 'offset' => 12, 'length' => 10],
            ['path' => 'exports/archive.bin', 'offset' => 22, 'length' => 10],
        ], $provider->openLog());
    },
    'sequential chunked reader range seek is lazy and custom length resets to initial chunk' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $content = '0123456789abcdefghij';
        $provider->put('exports/site.wxr', $content);

        $reader = new SequentialChunkedReader($provider, 'exports/site.wxr', 4, 16);
        $t->same(5, $reader->rangeSeek(5, SEEK_SET, 3));
        $t->same([], $provider->openLog(), 'RangeSeek should not reopen until Read');
        $t->same('567', $reader->read(3));
        $t->same('89ab', $reader->read(4));
        $t->same('cdefghij', $reader->read(8));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 5, 'length' => 3],
            ['path' => 'exports/site.wxr', 'offset' => 8, 'length' => 4],
            ['path' => 'exports/site.wxr', 'offset' => 12, 'length' => 8],
        ], $provider->openLog());
    },
    'sequential chunked reader seeks relative to current and end positions' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', 'ABCDEFGHIJ');

        $reader = new SequentialChunkedReader($provider, 'exports/site.wxr', 4, 8);
        $t->same('ABCD', $reader->read(4));
        $t->same(2, $reader->seek(-2, SEEK_CUR));
        $t->same('CDE', $reader->read(3));
        $t->same(8, $reader->rangeSeek(-2, SEEK_END, 2));
        $t->same('IJ', $reader->read(8));
        $t->throws(RuntimeException::class, static fn () => $reader->rangeSeek(10, SEEK_SET, 1));

        $unknown = new MemoryProvider();
        $unknown->put('exports/streamed.wxr', '<rss></rss>', ['unknownSize' => true]);
        $unknownReader = new SequentialChunkedReader($unknown, 'exports/streamed.wxr', 4, 8);
        $t->throws(RuntimeException::class, static fn () => $unknownReader->rangeSeek(0, SEEK_END, 1));
    },
    'sequential chunked reader can disable chunking and reports closed errors' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', '0123456789abcdef');

        $reader = new SequentialChunkedReader($provider, 'exports/site.wxr', 0, 0);
        $reader->open();
        $t->same('01234', $reader->read(5));
        $t->same(7, $reader->seek(7, SEEK_SET));
        $t->same('789abcdef', $reader->read(20));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => null],
            ['path' => 'exports/site.wxr', 'offset' => 7, 'length' => null],
        ], $provider->openLog());

        $reader->close();
        $t->throws(RuntimeException::class, static fn () => $reader->read(1));
        $t->throws(RuntimeException::class, static fn () => $reader->seek(0));
        $t->throws(RuntimeException::class, static fn () => $reader->rangeSeek(0));
        $t->throws(RuntimeException::class, static fn () => $reader->open());
        $t->throws(RuntimeException::class, static fn () => $reader->close());
    },
    'wordpress chunked wxr restore example exposes lazy tail range reads' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-chunked-wxr-restore.php';

        $t->same('<rss><ch', $example['header']);
        $t->same('annel><item>post', $example['nextChunk']);
        $t->same($example['tailOffset'], $example['tailSeekPosition']);
        $t->same(2, $example['openCountBeforeTailRead']);
        $t->same('</rss>', $example['tail']);
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => 8],
            ['path' => 'exports/site.wxr', 'offset' => 8, 'length' => 16],
            ['path' => 'exports/site.wxr', 'offset' => $example['tailOffset'], 'length' => 6],
        ], $example['openLog']);
    },
];
