<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SequentialChunkedReader;

$provider = new MemoryProvider();
$wxr = '<rss><channel><item>post</item><item>media</item></channel></rss>';
$provider->put('exports/site.wxr', $wxr);

$reader = new SequentialChunkedReader($provider, 'exports/site.wxr', 8, 16);
$header = $reader->read(8);
$nextChunk = $reader->read(16);

$tailOffset = strlen($wxr) - strlen('</rss>');
$tailSeekPosition = $reader->rangeSeek($tailOffset, SEEK_SET, strlen('</rss>'));
$openCountBeforeTailRead = count($provider->openLog());
$tail = $reader->read(strlen('</rss>'));

return [
    'path' => 'exports/site.wxr',
    'header' => $header,
    'nextChunk' => $nextChunk,
    'tailOffset' => $tailOffset,
    'tailSeekPosition' => $tailSeekPosition,
    'openCountBeforeTailRead' => $openCountBeforeTailRead,
    'tail' => $tail,
    'openLog' => $provider->openLog(),
];
