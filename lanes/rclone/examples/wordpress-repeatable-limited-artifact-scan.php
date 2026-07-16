<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\RepeatableReader;

$tree = require __DIR__ . '/../fixtures/wordpress-backup-tree.php';
$wxr = $tree['exports/site.wxr'];
$reader = RepeatableReader::limitBuffer($wxr . 'next-archive-member', str_repeat("\0", 64), strlen($wxr));

$probe = $reader->read(5);
$reader->seek(0, SEEK_SET);

$restored = '';
while (($chunk = $reader->read(8)) !== '') {
    $restored .= $chunk;
}

return [
    'probe' => $probe,
    'isWxr' => $probe === '<rss ',
    'restored' => $restored,
    'matchesOriginal' => $restored === $wxr,
    'excludedTrailingBytes' => !str_contains($restored, 'next-archive-member'),
    'cachedArtifactBytes' => $reader->cacheLength(),
];
