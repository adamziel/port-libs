<?php

declare(strict_types=1);

use PortLibs\Rclone\ChunkedReaderFactory;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ParallelChunkedReader;
use PortLibs\Rclone\SequentialChunkedReader;

$makeContent = static function (int $size): string {
    $pattern = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz<>/';

    return substr(str_repeat($pattern, intdiv($size, strlen($pattern)) + 1), 0, $size);
};

return [
    'chunked reader factory chooses sequential for nonpositive chunks and negative streams' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', '<rss><item>post</item></rss>');

        $reader = ChunkedReaderFactory::create($provider, 'exports/site.wxr', 0, 0, -2);

        $t->true($reader instanceof SequentialChunkedReader);
        $t->same('<rss>', $reader->read(5));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => null],
        ], $provider->openLog());
    },
    'chunked reader factory keeps sequential for one stream and clamps max below initial' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/site.wxr', 'abcdefghijkl');

        $reader = ChunkedReaderFactory::create($provider, 'exports/site.wxr', 4, 2, 1);

        $t->true($reader instanceof SequentialChunkedReader);
        $t->same('abcdefghijkl', $reader->read(12));
        $t->same([
            ['path' => 'exports/site.wxr', 'offset' => 0, 'length' => 4],
            ['path' => 'exports/site.wxr', 'offset' => 4, 'length' => 4],
            ['path' => 'exports/site.wxr', 'offset' => 8, 'length' => 4],
        ], $provider->openLog());
    },
    'chunked reader factory chooses parallel for known sized objects with multiple streams' => static function (TestRunner $t) use ($makeContent): void {
        $provider = new MemoryProvider();
        $size = ParallelChunkedReader::BUFFER_SIZE * 2 + 1;
        $provider->put('exports/media-bundle.wxr', $makeContent($size));

        $reader = ChunkedReaderFactory::create($provider, 'exports/media-bundle.wxr', 1, ParallelChunkedReader::BUFFER_SIZE * 4, 2);

        $t->true($reader instanceof ParallelChunkedReader);
        $t->same(ParallelChunkedReader::BUFFER_SIZE, $reader->chunkSize());
        $reader->open();
        $t->same([
            ['path' => 'exports/media-bundle.wxr', 'offset' => 0, 'length' => ParallelChunkedReader::BUFFER_SIZE],
            ['path' => 'exports/media-bundle.wxr', 'offset' => ParallelChunkedReader::BUFFER_SIZE, 'length' => ParallelChunkedReader::BUFFER_SIZE],
        ], $provider->openLog());
    },
    'chunked reader factory falls back to sequential for unknown sized objects despite multiple streams' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('exports/streamed-site.wxr', '<rss><item>streamed</item></rss>', ['unknownSize' => true]);

        $reader = ChunkedReaderFactory::create($provider, 'exports/streamed-site.wxr', 4, 16, 4);

        $t->true($reader instanceof SequentialChunkedReader);
        $t->same('<rss><', $reader->read(6));
        $t->same([
            ['path' => 'exports/streamed-site.wxr', 'offset' => 0, 'length' => 4],
            ['path' => 'exports/streamed-site.wxr', 'offset' => 4, 'length' => 8],
        ], $provider->openLog());
    },
    'wordpress chunked reader factory example chooses restore strategy by provider size' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-chunked-reader-factory.php';

        $t->same(SequentialChunkedReader::class, $example['streamedReaderClass']);
        $t->same('<rss>', $example['streamedHeader']);
        $t->same([
            ['path' => 'exports/streamed-site.wxr', 'offset' => 0, 'length' => 8],
        ], $example['streamedOpenLog']);
        $t->same(ParallelChunkedReader::class, $example['largeReaderClass']);
        $t->same(ParallelChunkedReader::BUFFER_SIZE, $example['largeChunkSize']);
        $t->same(2, $example['largeInitialOpenCount']);
        $t->same('<rss>', $example['largeHeader']);
    },
];
