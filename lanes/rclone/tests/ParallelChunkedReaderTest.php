<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ParallelChunkedReader;

$makeContent = static function (int $size): string {
    $pattern = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz<>/';

    return substr(str_repeat($pattern, intdiv($size, strlen($pattern)) + 1), 0, $size);
};

return [
    'parallel chunked reader rounds chunks and prefetches configured streams' => static function (TestRunner $t) use ($makeContent): void {
        $provider = new MemoryProvider();
        $size = ParallelChunkedReader::BUFFER_SIZE * 2 + 23;
        $content = $makeContent($size);
        $provider->put('exports/archive.bin', $content);

        $reader = new ParallelChunkedReader($provider, 'exports/archive.bin', 1, 2);
        $t->same(ParallelChunkedReader::BUFFER_SIZE, $reader->chunkSize());
        $reader->open();
        $t->same([
            ['path' => 'exports/archive.bin', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/archive.bin', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
        ], $provider->openLog());

        $firstReadLength = ParallelChunkedReader::BUFFER_SIZE + 5;
        $t->same(substr($content, 0, $firstReadLength), $reader->read($firstReadLength));
        $t->same([
            ['path' => 'exports/archive.bin', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/archive.bin', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/archive.bin', 'offset' => ParallelChunkedReader::BUFFER_SIZE * 2, 'length' => 23],
        ], $provider->openLog());
        $t->same(substr($content, $firstReadLength), $reader->read($size));
        $t->same('', $reader->read(1));
    },
    'parallel chunked reader seek reuses prefetched streams and ignores range length' => static function (TestRunner $t) use ($makeContent): void {
        $provider = new MemoryProvider();
        $size = ParallelChunkedReader::BUFFER_SIZE * 2 + 100;
        $content = $makeContent($size);
        $provider->put('exports/site.wxr', $content);

        $reader = new ParallelChunkedReader($provider, 'exports/site.wxr', ParallelChunkedReader::BUFFER_SIZE, 2);
        $t->same(substr($content, 0, 32), $reader->read(32));
        $t->same(16, $reader->seek(-16, SEEK_CUR));
        $t->same(substr($content, 16, 24), $reader->read(24));
        $t->same(ParallelChunkedReader::BUFFER_SIZE + 10, $reader->rangeSeek(ParallelChunkedReader::BUFFER_SIZE + 10, SEEK_SET, 7));
        $t->same(substr($content, ParallelChunkedReader::BUFFER_SIZE + 10, 20), $reader->read(20));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE * 2, 'length' => 100],
        ], $provider->openLog());

        $t->same(10, $reader->seek(10, SEEK_SET));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE * 2, 'length' => 100],
        ], $provider->openLog(), 'Seeking before the current stream should be lazy until the next read');
        $t->same(substr($content, 10, 12), $reader->read(12));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE * 2, 'length' => 100],
            ['path' => 'exports/site.wxr', 'offset' => 10, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE + 10, 'length' => ParallelChunkedReader::BUFFER_SIZE],
        ], $provider->openLog());
    },
    'parallel chunked reader restarts lazily outside prefetched streams and reports errors' => static function (TestRunner $t) use ($makeContent): void {
        $provider = new MemoryProvider();
        $size = ParallelChunkedReader::BUFFER_SIZE * 2 + 33;
        $content = $makeContent($size);
        $provider->put('exports/site.wxr', $content);

        $reader = new ParallelChunkedReader($provider, 'exports/site.wxr', ParallelChunkedReader::BUFFER_SIZE, 2);
        $reader->open();
        $t->same(ParallelChunkedReader::BUFFER_SIZE * 2 + 7, $reader->seek(ParallelChunkedReader::BUFFER_SIZE * 2 + 7, SEEK_SET));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
        ], $provider->openLog());
        $t->same(substr($content, ParallelChunkedReader::BUFFER_SIZE * 2 + 7, 9), $reader->read(9));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE * 2 + 7, 'length' => 26],
        ], $provider->openLog());

        $unknown = new MemoryProvider();
        $unknown->put('exports/streamed.wxr', '<rss></rss>', ['unknownSize' => true]);
        $unknownReader = new ParallelChunkedReader($unknown, 'exports/streamed.wxr', ParallelChunkedReader::BUFFER_SIZE, 2);
        $t->throws(RuntimeException::class, static fn () => $unknownReader->read(1));
        $t->throws(RuntimeException::class, static fn () => $reader->seek($size, SEEK_SET));

        $reader->close();
        $t->throws(RuntimeException::class, static fn () => $reader->read(1));
        $t->throws(RuntimeException::class, static fn () => $reader->seek(0));
        $t->throws(RuntimeException::class, static fn () => $reader->rangeSeek(0));
        $t->throws(RuntimeException::class, static fn () => $reader->open());
        $t->throws(RuntimeException::class, static fn () => $reader->close());
    },
    'parallel chunked reader closes prefetched streams when a later stream read fails' => static function (TestRunner $t) use ($makeContent): void {
        $provider = new MemoryProvider();
        $size = ParallelChunkedReader::BUFFER_SIZE * 2 + 12;
        $provider->put('exports/failing.wxr', $makeContent($size), [
            'readError' => new RuntimeException('provider read failed'),
            'readErrorAfterBytes' => ParallelChunkedReader::BUFFER_SIZE + 7,
        ]);

        $reader = new ParallelChunkedReader($provider, 'exports/failing.wxr', ParallelChunkedReader::BUFFER_SIZE, 2);
        try {
            $reader->open();
            $t->true(false, 'Expected prefetch read failure');
        } catch (RuntimeException $throwable) {
            $t->contains(
                'parallel chunked reader: failed to read stream at ' . ParallelChunkedReader::BUFFER_SIZE . ' size ' . ParallelChunkedReader::BUFFER_SIZE . ': provider read failed',
                $throwable->getMessage(),
            );
        }

        $t->same([
            ['path' => 'exports/failing.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/failing.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
        ], $provider->closeLog());
    },
    'parallel chunked reader close reports first close error after closing every stream' => static function (TestRunner $t) use ($makeContent): void {
        $provider = new MemoryProvider();
        $size = ParallelChunkedReader::BUFFER_SIZE * 2 + 3;
        $provider->put('exports/close-error.wxr', $makeContent($size), [
            'closeError' => new RuntimeException('provider close failed'),
        ]);

        $reader = new ParallelChunkedReader($provider, 'exports/close-error.wxr', ParallelChunkedReader::BUFFER_SIZE, 2);
        $reader->open();
        try {
            $reader->close();
            $t->true(false, 'Expected close failure');
        } catch (RuntimeException $throwable) {
            $t->contains(
                'parallel chunked reader: failed to read stream at 0 size ' . ParallelChunkedReader::BUFFER_SIZE . ': provider close failed',
                $throwable->getMessage(),
            );
        }

        $t->same([
            ['path' => 'exports/close-error.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/close-error.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
        ], $provider->closeLog());
    },
    'parallel chunked reader seek discards close errors for abandoned streams' => static function (TestRunner $t) use ($makeContent): void {
        $provider = new MemoryProvider();
        $size = ParallelChunkedReader::BUFFER_SIZE * 2 + 100;
        $content = $makeContent($size);
        $provider->put('exports/seek-close-error.wxr', $content, [
            'closeError' => new RuntimeException('provider close failed'),
        ]);

        $reader = new ParallelChunkedReader($provider, 'exports/seek-close-error.wxr', ParallelChunkedReader::BUFFER_SIZE, 2);
        $reader->open();
        $t->same(ParallelChunkedReader::BUFFER_SIZE + 10, $reader->seek(ParallelChunkedReader::BUFFER_SIZE + 10, SEEK_SET));
        $t->same([
            ['path' => 'exports/seek-close-error.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
        ], $provider->closeLog());
        $t->same(substr($content, ParallelChunkedReader::BUFFER_SIZE + 10, 8), $reader->read(8));

        try {
            $reader->close();
            $t->true(false, 'Expected remaining stream close failure');
        } catch (RuntimeException $throwable) {
            $t->contains(
                'parallel chunked reader: failed to read stream at ' . ParallelChunkedReader::BUFFER_SIZE . ' size ' . ParallelChunkedReader::BUFFER_SIZE . ': provider close failed',
                $throwable->getMessage(),
            );
        }
    },
    'wordpress parallel chunked wxr restore example keeps boundary and tail reads prefetched' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-parallel-chunked-wxr-restore.php';

        $t->same('<rss>', $example['header']);
        $t->same(2, $example['initialOpenCount']);
        $t->same($example['boundaryOffset'], $example['boundarySeekPosition']);
        $t->same($example['boundaryBytes'], $example['expectedBoundaryBytes']);
        $t->same($example['tailOffset'], $example['tailSeekPosition']);
        $t->same('</rss>', $example['tail']);
        $t->same(2, count($example['openLog']));
        $t->same([
            ['path' => 'exports/large-site.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/large-site.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => $example['secondStreamLength']],
        ], $example['openLog']);
    },
];
