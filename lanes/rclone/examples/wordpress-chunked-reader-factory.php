<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ChunkedReaderFactory;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ParallelChunkedReader;

$streamedProvider = new MemoryProvider();
$streamedProvider->put(
    'exports/streamed-site.wxr',
    '<rss><channel><item>streamed import</item></channel></rss>',
    ['unknownSize' => true],
);

$streamedReader = ChunkedReaderFactory::create($streamedProvider, 'exports/streamed-site.wxr', 8, 32, 4);
$streamedHeader = $streamedReader->read(strlen('<rss>'));

$largeProvider = new MemoryProvider();
$largeWxr = '<rss>'
    . str_repeat('m', ParallelChunkedReader::BUFFER_SIZE - strlen('<rss>'))
    . '<item>media library</item>'
    . str_repeat('n', 64)
    . '</rss>';
$largeProvider->put('exports/media-bundle.wxr', $largeWxr);

$largeReader = ChunkedReaderFactory::create(
    $largeProvider,
    'exports/media-bundle.wxr',
    1,
    ParallelChunkedReader::BUFFER_SIZE * 4,
    3,
);
$largeReader->open();
$largeInitialOpenCount = count($largeProvider->openLog());
$largeHeader = $largeReader->read(strlen('<rss>'));

return [
    'streamedReaderClass' => $streamedReader::class,
    'streamedHeader' => $streamedHeader,
    'streamedOpenLog' => $streamedProvider->openLog(),
    'largeReaderClass' => $largeReader::class,
    'largeChunkSize' => $largeReader->chunkSize(),
    'largeInitialOpenCount' => $largeInitialOpenCount,
    'largeHeader' => $largeHeader,
    'largeOpenLog' => $largeProvider->openLog(),
];
