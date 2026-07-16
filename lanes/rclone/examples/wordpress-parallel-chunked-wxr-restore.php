<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ParallelChunkedReader;

$provider = new MemoryProvider();
$wxr = '<rss>'
    . str_repeat('a', ParallelChunkedReader::BUFFER_SIZE - strlen('<rss>') - 4)
    . 'ABCD'
    . '<item>post</item>'
    . str_repeat('b', 64)
    . '</rss>';
$provider->put('exports/large-site.wxr', $wxr);

$reader = new ParallelChunkedReader($provider, 'exports/large-site.wxr', ParallelChunkedReader::BUFFER_SIZE, 2);
$reader->open();
$initialOpenCount = count($provider->openLog());
$header = $reader->read(strlen('<rss>'));

$boundaryOffset = ParallelChunkedReader::BUFFER_SIZE - 4;
$boundarySeekPosition = $reader->rangeSeek($boundaryOffset, SEEK_SET, 4);
$boundaryBytes = $reader->read(12);

$tailOffset = strlen($wxr) - strlen('</rss>');
$tailSeekPosition = $reader->rangeSeek($tailOffset, SEEK_SET, strlen('</rss>'));
$tail = $reader->read(strlen('</rss>'));

return [
    'path' => 'exports/large-site.wxr',
    'header' => $header,
    'initialOpenCount' => $initialOpenCount,
    'boundaryOffset' => $boundaryOffset,
    'boundarySeekPosition' => $boundarySeekPosition,
    'boundaryBytes' => $boundaryBytes,
    'expectedBoundaryBytes' => substr($wxr, $boundaryOffset, 12),
    'tailOffset' => $tailOffset,
    'tailSeekPosition' => $tailSeekPosition,
    'tail' => $tail,
    'secondStreamLength' => strlen($wxr) - ParallelChunkedReader::BUFFER_SIZE,
    'openLog' => $provider->openLog(),
];
